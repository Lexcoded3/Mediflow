<?php
/**
 * history.php — Department Consultation History
 * Consultation module | mediflow
 *
 * Expects: ?dept_id=X
 * Shows all consultations for all patients seen in the given department.
 */

require_once '../config/config.php';
requirePermission('consultation');

// ── 1. Resolve department ────────────────────────────────────────────────────
$dept_id = (int) ($_GET['dept_id'] ?? 0);
if (!$dept_id) {
    header('Location: index.php');
    exit;
}

$dept_stmt = $conn->prepare("SELECT id, name FROM departments WHERE id = ? LIMIT 1");
$dept_stmt->bind_param("i", $dept_id);
$dept_stmt->execute();
$dept = $dept_stmt->get_result()->fetch_assoc();

if (!$dept) {
    header('Location: index.php');
    exit;
}

// ── 2. Filters ───────────────────────────────────────────────────────────────
$filter_status    = $_GET['status']     ?? '';
$filter_doctor    = (int)($_GET['doctor_id'] ?? 0);
$filter_date_from = $_GET['date_from']  ?? '';
$filter_date_to   = $_GET['date_to']    ?? '';
$search_query     = trim($_GET['q']     ?? '');

// ── 3. Build main query (MySQLi dynamic binding) ─────────────────────────────
$where_parts = ["v.department_id = ?"];
$bind_types  = "i";
$bind_values = [$dept_id];

if ($filter_status) {
    $where_parts[] = "v.status = ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_status;
}
if ($filter_doctor) {
    $where_parts[] = "v.doctor_id = ?";
    $bind_types   .= "i";
    $bind_values[] = $filter_doctor;
}
if ($filter_date_from) {
    $where_parts[] = "v.visit_date >= ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_parts[] = "v.visit_date <= ?";
    $bind_types   .= "s";
    $bind_values[] = $filter_date_to;
}
if ($search_query) {
    $where_parts[] = "(p.name LIKE ? OR p.patient_id LIKE ? OR c.diagnosis LIKE ? OR c.chief_complaint LIKE ?)";
    $bind_types   .= "ssss";
    $like = "%$search_query%";
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
    $bind_values[] = $like;
}

$where_sql = implode(' AND ', $where_parts);

$sql = "
    SELECT  v.id            AS visit_pk,
            v.visit_id      AS visit_ref,
            v.visit_date,
            v.status,
            d.id            AS doctor_id,
            d.name          AS doctor_name,
            d.specialization,
            p.id            AS patient_pk,
            p.patient_id    AS patient_ref,
            p.name          AS patient_name,
            p.dob,
            p.age,
            p.gender,
            p.blood_group,
            p.phone,
            c.id            AS consult_id,
            c.chief_complaint,
            c.diagnosis,
            c.notes         AS consult_notes,
            c.follow_up_date,
            c.follow_up_notes,
            t.bp_systolic,
            t.bp_diastolic,
            t.pulse,
            t.temperature,
            t.spo2,
            t.weight,
            t.priority,
            (SELECT COUNT(*) FROM lab_orders lo WHERE lo.visit_id = v.id) AS lab_count,
            (SELECT COUNT(*) FROM scans      s  WHERE s.visit_id  = v.id) AS scan_count
    FROM    visits      v
    JOIN    patients    p   ON p.id      = v.patient_id
    LEFT JOIN doctors   d   ON d.id      = v.doctor_id
    LEFT JOIN consultations c ON c.visit_id = v.id
    LEFT JOIN triage        t ON t.visit_id = v.id
    WHERE   $where_sql
    ORDER BY v.visit_date DESC, v.id DESC
";

$visits_stmt = $conn->prepare($sql);
$visits_stmt->bind_param($bind_types, ...$bind_values);
$visits_stmt->execute();
$all_visits = $visits_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 4. Doctors list for filter dropdown ─────────────────────────────────────
$doc_stmt = $conn->prepare("
    SELECT DISTINCT d.id, d.name
    FROM   visits v
    JOIN   doctors d ON d.id = v.doctor_id
    WHERE  v.department_id = ?
    ORDER  BY d.name
");
$doc_stmt->bind_param("i", $dept_id);
$doc_stmt->execute();
$dept_doctors = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── 5. Group visits by patient ───────────────────────────────────────────────
$patients = [];
foreach ($all_visits as $v) {
    $pid = $v['patient_pk'];
    if (!isset($patients[$pid])) {
        $patients[$pid] = [
            'patient_pk'   => $pid,
            'patient_ref'  => $v['patient_ref'],
            'patient_name' => $v['patient_name'],
            'dob'          => $v['dob'],
            'age'          => $v['age'],
            'gender'       => $v['gender'],
            'blood_group'  => $v['blood_group'],
            'phone'        => $v['phone'],
            'visits'       => [],
        ];
    }
    $patients[$pid]['visits'][] = $v;
}

// ── 6. Summary stats ─────────────────────────────────────────────────────────
$total_visits    = count($all_visits);
$total_patients  = count($patients);
$done_count      = count(array_filter($all_visits, fn($v) => in_array($v['status'], ['completed','closed'])));
$followup_count  = count(array_filter($all_visits, fn($v) => !empty($v['follow_up_date'])));

// ── Helpers ──────────────────────────────────────────────────────────────────
function status_class(string $s): string {
    return match($s) {
        'completed','closed' => 'status-done',
        'consulting'         => 'status-active',
        'lab','pharmacy'     => 'status-progress',
        default              => 'status-reg',
    };
}
function priority_class(string $p = ''): string {
    return match($p) {
        'red'    => 'pri-red',
        'orange' => 'pri-orange',
        'yellow' => 'pri-yellow',
        default  => 'pri-green',
    };
}
function age_from_dob(?string $dob, ?string $age_raw): string {
    if ($dob) {
        try {
            $diff = (new DateTime())->diff(new DateTime($dob));
            return $diff->y . ' yrs';
        } catch (Exception $e) {}
    }
    return $age_raw ? $age_raw . ' yrs' : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultation History — <?= htmlspecialchars($dept['name']) ?></title>
<link rel="stylesheet" href="https://unpkg.com/@tabler/icons-webfont/dist/tabler-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #f4f5f7;
    --surface:   #ffffff;
    --surface2:  #f9fafb;
    --border:    #e5e7eb;
    --border2:   #d1d5db;
    --text:      #111827;
    --text2:     #374151;
    --text3:     #6b7280;
    --text4:     #9ca3af;
    --accent:    #2563eb;
    --accent-bg: #eff6ff;
    --accent2:   #1d4ed8;
    --green:     #16a34a;
    --green-bg:  #f0fdf4;
    --amber:     #d97706;
    --amber-bg:  #fffbeb;
    --red:       #dc2626;
    --red-bg:    #fef2f2;
    --orange:    #ea580c;
    --orange-bg: #fff7ed;
    --radius:    10px;
    --radius-sm: 6px;
    --shadow:    0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,.08);
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
}

.page-wrap { padding: 24px; max-width: 1200px; }

/* ── Page header ─────────────────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.page-header-left h1 {
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.page-header-left h1 i { color: var(--accent); font-size: 22px; }
.breadcrumb { font-size: 12px; color: var(--text4); margin-top: 3px; }
.breadcrumb a { color: var(--text3); text-decoration: none; }
.breadcrumb a:hover { color: var(--accent); }

/* ── Stats row ───────────────────────────────────────────────────────── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
    box-shadow: var(--shadow);
}
.stat-card .stat-num   { font-size: 26px; font-weight: 700; line-height: 1; }
.stat-card .stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; }
.stat-card.accent { border-left: 3px solid var(--accent); }
.stat-card.green  { border-left: 3px solid var(--green); }
.stat-card.amber  { border-left: 3px solid var(--amber); }

/* ── Filters bar ─────────────────────────────────────────────────────── */
.filter-bar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 140px;
}
.filter-group label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--text4);
}
.filter-group input,
.filter-group select {
    padding: 7px 10px;
    border: 1px solid var(--border2);
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--text);
    background: var(--surface);
    height: 34px;
}
.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--accent);
}
.filter-group.search { flex: 1; min-width: 200px; }
.filter-actions { display: flex; gap: 8px; align-items: flex-end; }

/* ── Buttons ─────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid transparent;
    transition: background .12s, border-color .12s;
    white-space: nowrap;
    height: 34px;
}
.btn i { font-size: 15px; }
.btn-primary  { background: var(--accent); color: #fff; border-color: var(--accent2); }
.btn-primary:hover { background: var(--accent2); }
.btn-outline  { background: var(--surface); color: var(--text2); border-color: var(--border2); }
.btn-outline:hover { background: var(--bg); border-color: var(--accent); color: var(--accent); }
.btn-ghost    { background: transparent; color: var(--text3); border-color: transparent; }
.btn-ghost:hover { background: var(--bg); color: var(--text); }
.btn-sm { padding: 5px 10px; font-size: 12px; height: 28px; }
.btn-sm i { font-size: 13px; }

/* ── Patient group cards ─────────────────────────────────────────────── */
.patient-group {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 16px;
    overflow: hidden;
}

.patient-group-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    cursor: pointer;
    user-select: none;
    background: var(--surface);
    transition: background .12s;
}
.patient-group-header:hover { background: var(--surface2); }

.patient-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: var(--accent-bg);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.patient-header-info h3 { font-size: 15px; font-weight: 600; }
.patient-header-info .pid { font-size: 12px; color: var(--text3); margin-top: 1px; }

.patient-header-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.visit-count-badge {
    background: var(--accent-bg);
    color: var(--accent);
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
}
.chevron {
    color: var(--text4);
    transition: transform .2s;
    font-size: 18px;
    flex-shrink: 0;
}
.patient-group.open .chevron { transform: rotate(180deg); }

/* ── Visit list inside a patient group ──────────────────────────────── */
.visit-list {
    display: none;
    border-top: 1px solid var(--border);
}
.patient-group.open .visit-list { display: block; }

.visit-item {
    border-bottom: 1px solid var(--border);
    overflow: hidden;
}
.visit-item:last-child { border-bottom: none; }

.visit-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px 12px 24px;
    cursor: pointer;
    user-select: none;
    background: var(--surface2);
    transition: background .12s;
}
.visit-head:hover { background: #f0f2f5; }

.visit-date-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 44px;
    padding: 5px 8px;
    border-radius: var(--radius-sm);
    background: var(--accent-bg);
    color: var(--accent);
    flex-shrink: 0;
}
.visit-date-block .vday  { font-size: 16px; font-weight: 700; line-height: 1; }
.visit-date-block .vmon  { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
.visit-date-block .vyear { font-size: 10px; color: var(--text3); }

.visit-ref    { font-size: 11px; font-weight: 600; color: var(--text3); }
.visit-doctor { font-size: 13px; font-weight: 600; color: var(--text); }
.visit-dept   { font-size: 12px; color: var(--text3); margin-top: 1px; }

.visit-head-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.badge i { font-size: 11px; }
.status-done     { background: var(--green-bg);   color: var(--green); }
.status-active   { background: var(--accent-bg);  color: var(--accent); }
.status-progress { background: var(--amber-bg);   color: var(--amber); }
.status-reg      { background: var(--bg);          color: var(--text3); }
.pri-red    { background: var(--red-bg);    color: var(--red); }
.pri-orange { background: var(--orange-bg); color: var(--orange); }
.pri-yellow { background: var(--amber-bg);  color: var(--amber); }
.pri-green  { background: var(--green-bg);  color: var(--green); }
.badge-followup { background: #fdf4ff; color: #7e22ce; }
.badge-lab      { background: #f0f9ff; color: #0369a1; }
.badge-scan     { background: #f0fdf4; color: #15803d; }

/* expanded visit body */
.visit-body {
    display: none;
    padding: 16px 20px 16px 24px;
    background: var(--surface);
    border-top: 1px solid var(--border);
}
.visit-item.open .visit-body { display: block; }

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 12px;
}
.detail-section h4 {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text3);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.detail-section h4 i { font-size: 13px; color: var(--accent); }
.detail-row {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
}
.detail-row .dk { font-size: 12px; color: var(--text4); min-width: 90px; flex-shrink: 0; }
.detail-row .dv { font-size: 13px; color: var(--text2); font-weight: 500; }
.detail-text      { font-size: 13px; color: var(--text2); line-height: 1.6; }
.detail-text.empty { color: var(--text4); font-style: italic; }

.vitals-chips { display: flex; flex-wrap: wrap; gap: 7px; }
.vital-chip {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 5px 10px;
    font-size: 12px;
}
.vital-chip .vk { color: var(--text4); font-size: 10px; display: block; margin-bottom: 1px; }
.vital-chip .vv { font-weight: 600; color: var(--text); }

.followup-box {
    background: #fdf4ff;
    border: 1px solid #e9d5ff;
    border-radius: var(--radius-sm);
    padding: 9px 13px;
    display: flex;
    align-items: flex-start;
    gap: 9px;
    font-size: 13px;
    color: #6b21a8;
    margin-bottom: 10px;
}
.followup-box i { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

.visit-body-footer {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}

/* ── Empty/no-results ────────────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text3);
}
.empty-state i { font-size: 48px; margin-bottom: 12px; display: block; color: var(--text4); }
.empty-state p { font-size: 15px; }

/* ── Section label ───────────────────────────────────────────────────── */
.section-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--text3);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.results-note { font-size: 12px; color: var(--text4); font-weight: 400; text-transform: none; letter-spacing: 0; }
</style>
</head>
<body>

<?php include 'sidenav.php'; ?>

<div class="page-wrap">

    <!-- ── Page header ───────────────────────────────────────────────── -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>
                <i class="ti ti-building-hospital"></i>
                <?= htmlspecialchars($dept['name']) ?> — Consultation History
            </h1>
            <div class="breadcrumb">
                <a href="index.php">Dashboard</a> / Consultation History
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="index.php" class="btn btn-outline">
                <i class="ti ti-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ── Stats ─────────────────────────────────────────────────────── -->
    <div class="stats-row">
        <div class="stat-card accent">
            <div class="stat-num"><?= $total_patients ?></div>
            <div class="stat-label">Patients</div>
        </div>
        <div class="stat-card accent">
            <div class="stat-num"><?= $total_visits ?></div>
            <div class="stat-label">Total visits</div>
        </div>
        <div class="stat-card green">
            <div class="stat-num"><?= $done_count ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <?php if ($followup_count): ?>
        <div class="stat-card amber">
            <div class="stat-num"><?= $followup_count ?></div>
            <div class="stat-label">Follow-ups set</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Filters ───────────────────────────────────────────────────── -->
    <form method="GET" action="history.php">
        <input type="hidden" name="dept_id" value="<?= $dept_id ?>">
        <div class="filter-bar">
            <div class="filter-group search">
                <label>Search</label>
                <input type="text" name="q"
                       placeholder="Patient name, ID, diagnosis…"
                       value="<?= htmlspecialchars($search_query) ?>">
            </div>
            <div class="filter-group">
                <label>Doctor</label>
                <select name="doctor_id">
                    <option value="">All doctors</option>
                    <?php foreach ($dept_doctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>"
                        <?= $filter_doctor === (int)$doc['id'] ? 'selected' : '' ?>>
                        Dr. <?= htmlspecialchars($doc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="completed"  <?= $filter_status === 'completed'  ? 'selected' : '' ?>>Completed</option>
                    <option value="closed"     <?= $filter_status === 'closed'     ? 'selected' : '' ?>>Closed</option>
                    <option value="consulting" <?= $filter_status === 'consulting' ? 'selected' : '' ?>>Consulting</option>
                    <option value="lab"        <?= $filter_status === 'lab'        ? 'selected' : '' ?>>Lab</option>
                    <option value="pharmacy"   <?= $filter_status === 'pharmacy'   ? 'selected' : '' ?>>Pharmacy</option>
                    <option value="registered" <?= $filter_status === 'registered' ? 'selected' : '' ?>>Registered</option>
                </select>
            </div>
            <div class="filter-group">
                <label>From date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="filter-group">
                <label>To date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-filter"></i> Filter
                </button>
                <?php if ($search_query || $filter_status || $filter_doctor || $filter_date_from || $filter_date_to): ?>
                <a href="history.php?dept_id=<?= $dept_id ?>" class="btn btn-ghost">
                    <i class="ti ti-x"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- ── Patient groups ────────────────────────────────────────────── -->
    <div class="section-label">
        <span>
            <i class="ti ti-users" style="font-size:13px; vertical-align:middle; margin-right:4px;"></i>
            Patients &amp; their visits
        </span>
        <span class="results-note"><?= $total_patients ?> patient(s) · <?= $total_visits ?> visit(s)</span>
    </div>

    <?php if (empty($patients)): ?>
    <div class="empty-state">
        <i class="ti ti-folder-open"></i>
        <p>No consultation records found<?= ($search_query || $filter_status || $filter_doctor || $filter_date_from || $filter_date_to) ? ' matching your filters' : ' for this department' ?>.</p>
    </div>
    <?php else: ?>

    <?php foreach ($patients as $patient): ?>
    <?php
        $p_visits     = $patient['visits'];
        $p_age        = age_from_dob($patient['dob'], $patient['age']);
        $latest_visit = $p_visits[0]; // already sorted DESC
    ?>
    <div class="patient-group" id="pg-<?= $patient['patient_pk'] ?>">

        <!-- Patient header -->
        <div class="patient-group-header" onclick="toggleGroup(this.closest('.patient-group'))">
            <div class="patient-avatar"><i class="ti ti-user"></i></div>
            <div class="patient-header-info">
                <h3><?= htmlspecialchars($patient['patient_name']) ?></h3>
                <div class="pid">
                    <?= htmlspecialchars($patient['patient_ref']) ?>
                    <?php if ($patient['gender']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($patient['gender']) ?><?php endif; ?>
                    <?php if ($p_age !== '—'): ?> &nbsp;·&nbsp; <?= $p_age ?><?php endif; ?>
                    <?php if ($patient['phone']): ?> &nbsp;·&nbsp; <i class="ti ti-phone" style="font-size:11px"></i> <?= htmlspecialchars($patient['phone']) ?><?php endif; ?>
                </div>
            </div>
            <div class="patient-header-right">
                <?php if ($patient['blood_group']): ?>
                <span class="badge status-reg"><i class="ti ti-droplet"></i> <?= htmlspecialchars($patient['blood_group']) ?></span>
                <?php endif; ?>
                <span class="visit-count-badge">
                    <?= count($p_visits) ?> visit<?= count($p_visits) !== 1 ? 's' : '' ?>
                </span>
                <span style="font-size:11px; color:var(--text4);">
                    Last: <?= date('d M Y', strtotime($latest_visit['visit_date'])) ?>
                </span>
                <i class="ti ti-chevron-down chevron"></i>
            </div>
        </div>

        <!-- Visit list -->
        <div class="visit-list">
        <?php foreach ($p_visits as $v):
            $visit_date = new DateTime($v['visit_date']);
        ?>
        <div class="visit-item" id="vi-<?= $v['visit_pk'] ?>">

            <!-- Visit row header -->
            <div class="visit-head" onclick="toggleVisit(this.closest('.visit-item'))">
                <div class="visit-date-block">
                    <span class="vday"><?= $visit_date->format('d') ?></span>
                    <span class="vmon"><?= $visit_date->format('M') ?></span>
                    <span class="vyear"><?= $visit_date->format('Y') ?></span>
                </div>
                <div>
                    <div class="visit-ref"><?= htmlspecialchars($v['visit_ref']) ?></div>
                    <div class="visit-doctor">
                        <?= $v['doctor_name']
                            ? 'Dr. ' . htmlspecialchars($v['doctor_name'])
                            : '<span style="color:var(--text4)">No doctor assigned</span>' ?>
                    </div>
                </div>
                <div class="visit-head-right">
                    <span class="badge <?= status_class($v['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $v['status'])) ?>
                    </span>
                    <?php if (!empty($v['priority'])): ?>
                    <span class="badge <?= priority_class($v['priority']) ?>">
                        <i class="ti ti-activity"></i> <?= ucfirst($v['priority']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($v['lab_count'] > 0): ?>
                    <span class="badge badge-lab"><i class="ti ti-flask-conical"></i> <?= $v['lab_count'] ?> lab</span>
                    <?php endif; ?>
                    <?php if ($v['scan_count'] > 0): ?>
                    <span class="badge badge-scan"><i class="ti ti-scan-line"></i> <?= $v['scan_count'] ?> scan</span>
                    <?php endif; ?>
                    <?php if (!empty($v['follow_up_date'])): ?>
                    <span class="badge badge-followup">
                        <i class="ti ti-calendar-check"></i>
                        F/U <?= date('d M', strtotime($v['follow_up_date'])) ?>
                    </span>
                    <?php endif; ?>
                    <i class="ti ti-chevron-down chevron"></i>
                </div>
            </div>

            <!-- Visit expanded body -->
            <div class="visit-body">
                <div class="detail-grid">

                    <div class="detail-section">
                        <h4><i class="ti ti-stethoscope"></i> Consultation</h4>
                        <?php if ($v['consult_id']): ?>
                            <?php if (!empty($v['chief_complaint'])): ?>
                            <div class="detail-row">
                                <span class="dk">Complaint</span>
                                <span class="dv"><?= nl2br(htmlspecialchars($v['chief_complaint'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($v['diagnosis'])): ?>
                            <div class="detail-row">
                                <span class="dk">Diagnosis</span>
                                <span class="dv"><?= nl2br(htmlspecialchars($v['diagnosis'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($v['consult_notes'])): ?>
                            <div class="detail-row">
                                <span class="dk">Notes</span>
                                <span class="dv"><?= nl2br(htmlspecialchars($v['consult_notes'])) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!$v['chief_complaint'] && !$v['diagnosis'] && !$v['consult_notes']): ?>
                            <p class="detail-text empty">Consultation recorded — no notes entered.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="detail-text empty">No consultation recorded for this visit.</p>
                        <?php endif; ?>
                    </div>

                    <div class="detail-section">
                        <h4><i class="ti ti-activity"></i> Triage Vitals</h4>
                        <?php if ($v['bp_systolic'] || $v['pulse'] || $v['temperature'] || $v['spo2'] || $v['weight']): ?>
                        <div class="vitals-chips">
                            <?php if ($v['bp_systolic'] && $v['bp_diastolic']): ?>
                            <div class="vital-chip">
                                <span class="vk">Blood Pressure</span>
                                <span class="vv"><?= $v['bp_systolic'] ?>/<?= $v['bp_diastolic'] ?> mmHg</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($v['pulse']): ?>
                            <div class="vital-chip">
                                <span class="vk">Pulse</span>
                                <span class="vv"><?= $v['pulse'] ?> bpm</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($v['temperature']): ?>
                            <div class="vital-chip">
                                <span class="vk">Temp</span>
                                <span class="vv"><?= $v['temperature'] ?> °C</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($v['spo2']): ?>
                            <div class="vital-chip">
                                <span class="vk">SpO₂</span>
                                <span class="vv"><?= $v['spo2'] ?>%</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($v['weight']): ?>
                            <div class="vital-chip">
                                <span class="vk">Weight</span>
                                <span class="vv"><?= $v['weight'] ?> kg</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="detail-text empty">No triage vitals recorded.</p>
                        <?php endif; ?>
                    </div>

                </div><!-- /detail-grid -->

                <?php if (!empty($v['follow_up_date'])): ?>
                <div class="followup-box">
                    <i class="ti ti-calendar-event"></i>
                    <div>
                        <strong>Follow-up scheduled:</strong>
                        <?= date('d M Y', strtotime($v['follow_up_date'])) ?>
                        <?php if (!empty($v['follow_up_notes'])): ?>
                        <br><span style="font-weight:400"><?= htmlspecialchars($v['follow_up_notes']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="visit-body-footer">
                    <a href="consult.php?visit_id=<?= $v['visit_pk'] ?>" class="btn btn-outline btn-sm">
                        <i class="ti ti-stethoscope"></i> Open Consultation
                    </a>
                    <?php if ($v['lab_count'] > 0): ?>
                    <a href="lab.php?visit_id=<?= $v['visit_pk'] ?>" class="btn btn-outline btn-sm">
                        <i class="ti ti-flask-conical"></i> Lab Orders (<?= $v['lab_count'] ?>)
                    </a>
                    <?php endif; ?>
                    <?php if ($v['scan_count'] > 0): ?>
                    <a href="scans.php?visit_id=<?= $v['visit_pk'] ?>" class="btn btn-outline btn-sm">
                        <i class="ti ti-scan-line"></i> Scans (<?= $v['scan_count'] ?>)
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($v['follow_up_date'])): ?>
                    <a href="followup.php?visit_id=<?= $v['visit_pk'] ?>" class="btn btn-outline btn-sm">
                        <i class="ti ti-calendar-check"></i> Follow-up
                    </a>
                    <?php endif; ?>
                </div>
            </div><!-- /visit-body -->

        </div><!-- /visit-item -->
        <?php endforeach; ?>
        </div><!-- /visit-list -->

    </div><!-- /patient-group -->
    <?php endforeach; ?>

    <?php endif; ?>

</div><!-- /page-wrap -->

<script>
function toggleGroup(group) {
    group.classList.toggle('open');
}
function toggleVisit(item) {
    item.classList.toggle('open');
}
</script>
</body>
</html>