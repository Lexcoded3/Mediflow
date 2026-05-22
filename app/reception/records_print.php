<?php
require_once '../config/config.php';
requirePermission('reception');

// ── Filter inputs ────────────────────────────────────────────────────────────
$type       = $_GET['type']       ?? 'queue';        // queue | patients
$date_from  = $_GET['date_from']  ?? date('Y-m-d');
$date_to    = $_GET['date_to']    ?? date('Y-m-d');
$dept_id    = $_GET['dept_id']    ?? '';
$status     = $_GET['status']     ?? '';
$search     = trim($_GET['search'] ?? '');
$do_print   = isset($_GET['print']);

// ── Departments for filter dropdown ─────────────────────────────────────────
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");

// ── Build queue/visit results ────────────────────────────────────────────────
$results   = null;
$row_count = 0;

if ($type === 'queue') {
    $where_parts = [];
    $where_parts[] = "v.visit_date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "'";
    if ($dept_id)  $where_parts[] = "v.department_id = '" . $conn->real_escape_string($dept_id) . "'";
    if ($status)   $where_parts[] = "v.status = '" . $conn->real_escape_string($status) . "'";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where_parts[] = "(p.name LIKE '%$s%' OR p.patient_id LIKE '%$s%' OR v.visit_id LIKE '%$s%')";
    }
    $where = 'WHERE ' . implode(' AND ', $where_parts);

    $results = $conn->query("
        SELECT v.visit_id, v.visit_date, v.token_number, v.status, v.created_at,
               p.name AS patient_name, p.patient_id AS patient_code,
               p.age, p.gender, p.phone,
               d.name AS dept_name,
               doc.name AS doctor_name,
               COALESCE(b.total_amount, 0) AS bill_total,
               COALESCE(b.payment_status, 'unpaid') AS bill_status
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        JOIN departments d ON d.id = v.department_id
        LEFT JOIN doctors doc ON doc.id = v.doctor_id
        LEFT JOIN bills b ON b.visit_id = v.id
        $where
        ORDER BY v.visit_date DESC, v.token_number ASC
    ");
    $row_count = $results ? $results->num_rows : 0;

} elseif ($type === 'patients') {
    $where_parts = [];
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where_parts[] = "(p.name LIKE '%$s%' OR p.patient_id LIKE '%$s%' OR p.phone LIKE '%$s%')";
    }
    if ($dept_id) {
        $where_parts[] = "EXISTS (SELECT 1 FROM visits vx WHERE vx.patient_id = p.id AND vx.department_id = '" . $conn->real_escape_string($dept_id) . "')";
    }
    $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    $results = $conn->query("
        SELECT p.*,
               COUNT(v.id) AS total_visits,
               MAX(v.visit_date) AS last_visit,
               MIN(v.visit_date) AS first_visit
        FROM patients p
        LEFT JOIN visits v ON v.patient_id = p.id
        $where
        GROUP BY p.id
        ORDER BY p.name ASC
    ");
    $row_count = $results ? $results->num_rows : 0;
}

$status_labels = [
    'registered' => 'Registered',
    'triage'     => 'Triage',
    'consulting' => 'Consulting',
    'lab'        => 'Lab',
    'pharmacy'   => 'Pharmacy',
    'completed'  => 'Completed',
    'closed'     => 'Closed',
];

$status_print_styles = [
    'registered' => 'color:#2563EB',
    'triage'     => 'color:#DC2626',
    'consulting' => 'color:#16A34A',
    'lab'        => 'color:#0284C7',
    'pharmacy'   => 'color:#EA580C',
    'completed'  => 'color:#059669',
    'closed'     => 'color:#64748B',
];
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Print Records | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        /* ── Screen styles ── */
        .filter-panel { transition: all .2s ease; }

        /* ── Print styles ── */
        @media print {
            /* Hide everything except the printable area */
            body * { visibility: hidden !important; }
            #print-area, #print-area * { visibility: visible !important; }
            #print-area {
                position: fixed !important;
                inset: 0 !important;
                margin: 0 !important;
                padding: 20px 24px !important;
                background: #fff !important;
                font-family: 'Segoe UI', Arial, sans-serif !important;
                font-size: 11px !important;
                color: #1e293b !important;
            }
            .print-header { margin-bottom: 16px; border-bottom: 2px solid #1e293b; padding-bottom: 12px; }
            .print-meta   { display: flex; justify-content: space-between; align-items: flex-end; }
            .print-title  { font-size: 18px; font-weight: 700; color: #1e293b; }
            .print-sub    { font-size: 11px; color: #64748b; margin-top: 2px; }
            .print-badge  { font-size: 10px; padding: 2px 7px; border-radius: 20px; border: 1px solid currentColor; font-weight: 600; display: inline-block; }

            table { width: 100%; border-collapse: collapse; }
            thead th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                padding: 6px 8px;
                text-align: left;
                font-size: 10px;
                font-weight: 600;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: .04em;
                border-bottom: 1px solid #cbd5e1;
            }
            tbody td {
                padding: 5px 8px;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
            }
            tbody tr:last-child td { border-bottom: none; }
            .mono { font-family: 'Courier New', monospace; font-size: 10px; color: #64748b; }
            .print-footer {
                position: fixed;
                bottom: 16px;
                left: 24px;
                right: 24px;
                display: flex;
                justify-content: space-between;
                font-size: 9px;
                color: #94a3b8;
                border-top: 1px solid #e2e8f0;
                padding-top: 6px;
            }
            @page { margin: 12mm 10mm; size: A4 landscape; }
        }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm flex flex-col">
    <div class="grow group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Print Records</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                        <a href="index.php" class="text-slate-400">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                        <a href="records.php" class="text-slate-400">Records</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Print</li>
                </ul>
            </div>

            <!-- ── Filter Panel ── -->
            <div class="card mb-5 print:hidden">
                <div class="card-body">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-400"></i>
                        <h6 class="text-15">Filter &amp; Preview</h6>
                    </div>

                    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4" id="filter-form">

                        <!-- Report Type -->
                        <div class="xl:col-span-4">
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-2">Report Type</label>
                            <div class="flex gap-2">
                                <label class="flex items-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition-all
                                    <?= $type === 'queue' ? 'border-custom-500 bg-custom-50 dark:bg-custom-500/10 text-custom-600 dark:text-custom-400' : 'border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-300 hover:border-slate-300' ?>">
                                    <input type="radio" name="type" value="queue" <?= $type === 'queue' ? 'checked' : '' ?> class="hidden" onchange="this.form.submit()">
                                    <i data-lucide="list-ordered" class="w-4 h-4"></i>
                                    <span class="text-sm font-medium">Visit / Queue Report</span>
                                </label>
                                <label class="flex items-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition-all
                                    <?= $type === 'patients' ? 'border-custom-500 bg-custom-50 dark:bg-custom-500/10 text-custom-600 dark:text-custom-400' : 'border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-300 hover:border-slate-300' ?>">
                                    <input type="radio" name="type" value="patients" <?= $type === 'patients' ? 'checked' : '' ?> class="hidden" onchange="this.form.submit()">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                    <span class="text-sm font-medium">Patient Directory</span>
                                </label>
                            </div>
                        </div>

                        <?php if ($type === 'queue'): ?>
                        <!-- Date From -->
                        <div>
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1.5">Date From</label>
                            <input type="date" name="date_from" value="<?= e($date_from) ?>"
                                   class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                        </div>

                        <!-- Date To -->
                        <div>
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1.5">Date To</label>
                            <input type="date" name="date_to" value="<?= e($date_to) ?>"
                                   class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1.5">Status</label>
                            <select name="status" class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                                <option value="">All statuses</option>
                                <?php foreach ($status_labels as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Department -->
                        <div>
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1.5">Department</label>
                            <select name="dept_id" class="form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                                <option value="">All departments</option>
                                <?php
                                $departments->data_seek(0);
                                while ($d = $departments->fetch_assoc()):
                                ?>
                                <option value="<?= $d['id'] ?>" <?= $dept_id == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="<?= $type === 'queue' ? 'xl:col-span-2' : 'xl:col-span-3' ?>">
                            <label class="inline-block text-xs font-medium text-slate-600 dark:text-zink-200 mb-1.5">Search</label>
                            <div class="relative">
                                <input type="text" name="search" value="<?= e($search) ?>"
                                       placeholder="<?= $type === 'queue' ? 'Name, patient ID, visit ID...' : 'Name, patient ID, phone...' ?>"
                                       class="form-input pl-8 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="xl:col-span-4 flex items-center gap-3 pt-1">
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                                <i data-lucide="filter" class="w-4 h-4"></i> Apply Filters
                            </button>
                            <a href="?type=<?= $type ?>" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                                <i data-lucide="x" class="w-4 h-4"></i> Reset
                            </a>
                            <?php if ($results && $row_count > 0): ?>
                            <button type="button" onclick="window.print()"
                                    class="ml-auto inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-slate-700 hover:bg-slate-800 dark:bg-zink-600 dark:hover:bg-zink-500 rounded-md transition-colors">
                                <i data-lucide="printer" class="w-4 h-4"></i> Print / Save PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Preview / Results ── -->
            <?php if ($results && $row_count > 0): ?>

            <!-- Screen preview card -->
            <div class="card print:hidden">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h6 class="text-15">
                                <?= $type === 'queue' ? 'Visit / Queue Report' : 'Patient Directory' ?>
                            </h6>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?= $row_count ?> record<?= $row_count != 1 ? 's' : '' ?> found
                                <?php if ($type === 'queue'): ?>
                                · <?= date('d M Y', strtotime($date_from)) ?> → <?= date('d M Y', strtotime($date_to)) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <button onclick="window.print()"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-slate-700 hover:bg-slate-800 rounded-md transition-colors">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <?php elseif ($_SERVER['QUERY_STRING']): ?>
            <div class="card">
                <div class="card-body py-12 text-center text-slate-400">
                    <i data-lucide="search-x" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                    <p class="font-medium">No records match your filters</p>
                    <p class="text-sm mt-1">Try adjusting the date range or search terms</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body py-12 text-center text-slate-400">
                    <i data-lucide="printer" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                    <p class="font-medium">Configure filters above and apply to preview records</p>
                    <p class="text-sm mt-1">Default shows today's visits</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════ PRINTABLE AREA ═══════════ -->
            <?php if ($results && $row_count > 0): $results->data_seek(0); ?>
            <div id="print-area" style="display:none;">

                <!-- Print Header -->
                <div class="print-header">
                    <div class="print-meta">
                        <div>
                            <div class="print-title">MediFlow OPD</div>
                            <div class="print-sub">
                                <?= $type === 'queue' ? 'Visit / Queue Report' : 'Patient Directory' ?>
                                <?php if ($type === 'queue'): ?>
                                &nbsp;·&nbsp; <?= date('d M Y', strtotime($date_from)) ?> to <?= date('d M Y', strtotime($date_to)) ?>
                                <?php endif; ?>
                                <?php
                                $departments->data_seek(0);
                                if ($dept_id) {
                                    while ($d = $departments->fetch_assoc()) {
                                        if ($d['id'] == $dept_id) { echo ' · ' . htmlspecialchars($d['name']); break; }
                                    }
                                }
                                if ($status) echo ' · Status: ' . ($status_labels[$status] ?? $status);
                                if ($search) echo ' · Search: "' . htmlspecialchars($search) . '"';
                                ?>
                            </div>
                        </div>
                        <div style="text-align:right; font-size:10px; color:#64748b;">
                            <div><?= $row_count ?> record<?= $row_count != 1 ? 's' : '' ?></div>
                            <div>Printed: <?= date('d M Y, h:i A') ?></div>
                        </div>
                    </div>
                </div>

                <!-- ── QUEUE TABLE ── -->
                <?php if ($type === 'queue'): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Patient Name</th>
                            <th>Patient ID</th>
                            <th>Visit ID</th>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th style="text-align:right">Bill</th>
                            <th>Pmt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = $results->fetch_assoc()):
                            $sty = $status_print_styles[$v['status']] ?? 'color:#64748B';
                        ?>
                        <tr>
                            <td style="font-weight:600; color:#64748b; font-size:10px;"><?= $v['token_number'] ?></td>
                            <td style="font-weight:500;"><?= e($v['patient_name']) ?></td>
                            <td class="mono"><?= e($v['patient_code']) ?></td>
                            <td class="mono"><?= e($v['visit_id']) ?></td>
                            <td style="white-space:nowrap; font-size:10px;"><?= date('d M Y', strtotime($v['visit_date'])) ?></td>
                            <td><?= e($v['dept_name']) ?></td>
                            <td style="font-size:10px;"><?= $v['doctor_name'] ? 'Dr. ' . e($v['doctor_name']) : '—' ?></td>
                            <td>
                                <span class="print-badge" style="<?= $sty ?>">
                                    <?= $status_labels[$v['status']] ?? ucfirst($v['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right; font-weight:500;">
                                <?= $v['bill_total'] > 0 ? '$' . number_format($v['bill_total'], 2) : '—' ?>
                            </td>
                            <td>
                                <?php if ($v['bill_total'] > 0): ?>
                                <span class="print-badge" style="<?= $v['bill_status'] === 'paid' ? 'color:#059669' : 'color:#D97706' ?>">
                                    <?= ucfirst($v['bill_status']) ?>
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- ── PATIENT TABLE ── -->
                <?php elseif ($type === 'patients'): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Patient ID</th>
                            <th>Phone</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Blood</th>
                            <th style="text-align:center">Visits</th>
                            <th>First Visit</th>
                            <th>Last Visit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($p = $results->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:500;"><?= e($p['name']) ?></td>
                            <td class="mono"><?= e($p['patient_id']) ?></td>
                            <td style="font-size:10px;"><?= e($p['phone'] ?: '—') ?></td>
                            <td style="font-size:10px;"><?= e($p['age'] ?: '—') ?></td>
                            <td style="font-size:10px;"><?= e($p['gender'] ?: '—') ?></td>
                            <td>
                                <?php if ($p['blood_group']): ?>
                                <span class="print-badge" style="color:#DC2626"><?= e($p['blood_group']) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="text-align:center; font-weight:600;"><?= $p['total_visits'] ?></td>
                            <td style="white-space:nowrap; font-size:10px;"><?= $p['first_visit'] ? date('d M Y', strtotime($p['first_visit'])) : '—' ?></td>
                            <td style="white-space:nowrap; font-size:10px;"><?= $p['last_visit'] ? date('d M Y', strtotime($p['last_visit'])) : '—' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Print Footer -->
                <div class="print-footer">
                    <span>MediFlow OPD &nbsp;·&nbsp; Confidential Medical Record</span>
                    <span>Generated <?= date('d M Y \a\t h:i A') ?></span>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.container-fluid -->
    </div><!-- /.grow -->

    <?php include 'footer.php'; ?>
</div><!-- /.flex col -->
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Auto-submit type radio on change
    document.querySelectorAll('input[name="type"]').forEach(r => {
        r.addEventListener('change', () => document.getElementById('filter-form').submit());
    });

    // Show print area only during actual print
    window.addEventListener('beforeprint', () => {
        const el = document.getElementById('print-area');
        if (el) el.style.display = 'block';
    });
    window.addEventListener('afterprint', () => {
        const el = document.getElementById('print-area');
        if (el) el.style.display = 'none';
    });
});
</script>
</body>
</html>