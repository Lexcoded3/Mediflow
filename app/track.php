<?php
require_once 'config/config.php';
// No requireLogin() — this is public

$visit    = null;
$error    = null;
$visit_id = strtoupper(trim($_GET['visit_id'] ?? $_POST['visit_id'] ?? ''));

if ($visit_id) {
    $stmt = $conn->prepare("
    SELECT v.*,
           p.name AS patient_name,
           p.patient_id AS patient_code,
           d.name AS department_name,
           CONCAT(doc.first_name, ' ', doc.last_name) AS doctor_name
    FROM visits v
    JOIN patients p ON p.id = v.patient_id
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN staff doc ON doc.id = v.doctor_id
    WHERE v.visit_id = ?
");
    $stmt->bind_param("s", $visit_id);
    $stmt->execute();
    $visit = $stmt->get_result()->fetch_assoc();

    if (!$visit) {
        $error = "No visit found for <strong>" . e($visit_id) . "</strong>. Please check your Visit ID and try again.";
    }
}

// Journey steps definition
$steps = [
    ['key' => 'registered', 'label' => 'Registered',   'icon' => 'clipboard-check', 'desc' => 'Patient checked in at reception'],
    ['key' => 'triage',     'label' => 'Triage',        'icon' => 'heart-pulse',     'desc' => 'Vital signs & initial assessment'],
    ['key' => 'consulting', 'label' => 'Consultation',  'icon' => 'stethoscope',     'desc' => 'Doctor consultation'],
    ['key' => 'lab',        'label' => 'Laboratory',    'icon' => 'test-tube',       'desc' => 'Lab tests (if ordered)'],
    ['key' => 'pharmacy',   'label' => 'Pharmacy',      'icon' => 'pill',            'desc' => 'Collect medication'],
    ['key' => 'completed',  'label' => 'Completed',     'icon' => 'circle-check',    'desc' => 'Visit complete'],
];

$status_order = ['registered' => 0, 'triage' => 1, 'consulting' => 2, 'lab' => 3, 'pharmacy' => 4, 'completed' => 5, 'closed' => 5];
$current_step = $status_order[$visit['status'] ?? ''] ?? 0;

function stepState(int $step_index, int $current): string {
    if ($step_index < $current)  return 'done';
    if ($step_index === $current) return 'active';
    return 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Track Your Visit | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --teal-50:  #f0fdfa;
            --teal-100: #ccfbf1;
            --teal-200: #99f6e4;
            --teal-600: #0d9488;
            --teal-700: #0f766e;
            --teal-800: #115e59;
            --teal-900: #134e4a;
            --slate-50:  #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-900: #0f172a;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: #eef2f7;
            background-image: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(13,148,136,0.09) 0%, transparent 70%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1rem 4rem;
            gap: 1.125rem;
        }

        /* ── Brand ── */
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-mark {
            width: 38px;
            height: 38px;
            background: var(--teal-700);
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(15,118,110,0.35);
        }
        .brand-mark svg {
            width: 20px;
            height: 20px;
            stroke: #fff;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .brand-text strong {
            display: block;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--slate-900);
        }
        .brand-text span {
            display: block;
            font-size: 0.75rem;
            color: var(--slate-400);
            font-weight: 400;
        }

        /* ── Panel ── */
        .panel {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.07);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.06);
        }

        /* ── Search ── */
        .search-row { display: flex; gap: 0.5rem; }
        .search-row input {
            flex: 1;
            height: 44px;
            padding: 0 1rem;
            border: 1.5px solid var(--slate-200);
            border-radius: var(--radius-sm);
            font-family: 'DM Mono', monospace;
            font-size: 0.82rem;
            letter-spacing: 0.06em;
            color: var(--slate-900);
            background: var(--slate-50);
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            text-transform: uppercase;
        }
        .search-row input::placeholder {
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0;
            color: var(--slate-400);
            font-size: 0.85rem;
        }
        .search-row input:focus {
            border-color: var(--teal-600);
            box-shadow: 0 0 0 3px rgba(13,148,136,0.1);
            background: #fff;
        }
        .search-row button {
            height: 44px;
            padding: 0 1.25rem;
            background: var(--teal-700);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            white-space: nowrap;
        }
        .search-row button:hover  { background: var(--teal-600); }
        .search-row button:active { transform: scale(0.97); }

        /* ── Error ── */
        .error {
            margin-top: 0.875rem;
            padding: 0.75rem 1rem;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: var(--radius-sm);
            color: #be123c;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* ── Result panel ── */
        .result-panel { animation: slideUp 0.25s ease; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Completed banner ── */
        .completed-banner {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1rem;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: var(--radius-sm);
            color: #166534;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }
        .completed-banner svg {
            width: 16px;
            height: 16px;
            stroke: #16a34a;
            fill: none;
            stroke-width: 2.5;
            flex-shrink: 0;
        }

        /* ── Section label ── */
        .section-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--slate-400);
            margin-bottom: 0.625rem;
        }

        /* ── Patient bar ── */
        .patient-bar {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            background: var(--teal-50);
            border: 1px solid var(--teal-100);
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        .patient-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--teal-700);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        .patient-info { flex: 1; min-width: 0; }
        .patient-info h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--teal-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .patient-info p {
            font-size: 0.75rem;
            color: var(--teal-700);
            margin-top: 2px;
            opacity: 0.8;
        }
        .token-badge {
            flex-shrink: 0;
            text-align: center;
            background: var(--teal-700);
            border-radius: var(--radius-sm);
            padding: 0.45rem 0.875rem;
        }
        .token-badge .num {
            font-family: 'DM Mono', monospace;
            font-size: 1.4rem;
            font-weight: 500;
            color: #fff;
            line-height: 1;
        }
        .token-badge .lbl {
            font-size: 0.62rem;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.65);
            margin-top: 2px;
        }

        /* ── Steps ── */
        .steps { display: flex; flex-direction: column; }

        .step { display: flex; gap: 0.875rem; position: relative; }

        .step-connector {
            position: absolute;
            left: 15px;
            top: 36px;
            bottom: 0;
            width: 1.5px;
            background: var(--slate-200);
            z-index: 0;
        }
        .step:last-child .step-connector { display: none; }
        .step-done  .step-connector,
        .step-active .step-connector { background: var(--teal-200); }

        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
            margin-top: 2px;
        }
        .step-pending .step-icon {
            background: var(--slate-100);
            border: 1.5px solid var(--slate-200);
        }
        .step-done .step-icon  { background: var(--teal-600); }
        .step-active .step-icon {
            background: var(--teal-700);
            box-shadow: 0 0 0 5px rgba(13,148,136,0.15);
        }

        /* lucide icons inside step-icon */
        .step-icon i, .step-icon svg {
            width: 14px !important;
            height: 14px !important;
            stroke-width: 2;
        }
        .step-pending .step-icon i { color: var(--slate-300); }
        .step-done    .step-icon svg,
        .step-active  .step-icon i { color: #fff; }

        /* inline check svg for done state */
        .step-icon .check-svg {
            width: 14px;
            height: 14px;
            stroke: #fff;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .step-body { flex: 1; padding: 0 0 1.25rem; }

        .step-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2px;
        }
        .step-head h4 {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--slate-900);
        }
        .step-pending .step-head h4 { color: var(--slate-400); }
        .step-active  .step-head h4 { color: var(--teal-700); font-weight: 600; }

        .step-desc { font-size: 0.78rem; color: var(--slate-400); line-height: 1.5; }
        .step-pending .step-desc { color: var(--slate-300); }

        .active-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.68rem;
            font-weight: 600;
            background: var(--teal-100);
            color: var(--teal-800);
            border-radius: 99px;
            padding: 2px 8px;
            animation: breathe 2.2s ease-in-out infinite;
        }
        .active-pill::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--teal-600);
            display: block;
        }
        @keyframes breathe {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.5; }
        }

        .done-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            color: var(--teal-600);
            font-weight: 500;
            margin-top: 3px;
        }
        .done-tag svg {
            width: 11px;
            height: 11px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .doctor-name {
            font-size: 0.78rem;
            color: var(--teal-600);
            font-weight: 500;
            margin-top: 3px;
        }

        /* ── Footer hint ── */
        .refresh-hint {
            text-align: center;
            font-size: 0.75rem;
            color: var(--slate-400);
            margin-top: 0.5rem;
        }
        .refresh-hint a {
            color: var(--teal-600);
            text-decoration: none;
            font-weight: 500;
        }
        .refresh-hint a:hover { text-decoration: underline; }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            body { padding: 1.5rem 0.75rem 3rem; }
            .panel { padding: 1.25rem; }
            .search-row input { font-size: 0.78rem; }
        }
    </style>
</head>
<body>

<!-- Brand -->
<div class="brand">
    <div class="brand-mark">
        <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </div>
    <div class="brand-text">
        <strong>MediFlow OPD</strong>
        <span>Patient Visit Tracker</span>
    </div>
</div>

<!-- Search -->
<div class="panel">
    <form method="GET" class="search-row">
        <input
            type="text"
            name="visit_id"
            value="<?= e($visit_id) ?>"
            placeholder="Enter Visit ID — e.g. OPD-250101-001"
            maxlength="20"
            autocomplete="off"
            spellcheck="false"
        >
        <button type="submit">Track</button>
    </form>

    <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
    <?php endif; ?>
</div>

<!-- Result -->
<?php if ($visit): ?>
<div class="panel result-panel">

    <?php if (in_array($visit['status'], ['completed', 'closed'])): ?>
    <div class="completed-banner">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Your visit is complete — thank you for visiting MediFlow OPD!
    </div>
    <?php endif; ?>

    <!-- Patient info -->
    <p class="section-label">Patient</p>
    <div class="patient-bar">
        <div class="patient-avatar"><?= strtoupper(substr($visit['patient_name'], 0, 1)) ?></div>
        <div class="patient-info">
            <h3><?= e($visit['patient_name']) ?></h3>
            <p><?= e($visit['patient_code']) ?> &middot; <?= e($visit['department_name']) ?></p>
        </div>
        <div class="token-badge">
            <div class="num"><?= str_pad($visit['token_number'], 2, '0', STR_PAD_LEFT) ?></div>
            <div class="lbl">TOKEN</div>
        </div>
    </div>

    <!-- Journey steps -->
    <p class="section-label">Journey</p>
    <div class="steps">
        <?php foreach ($steps as $i => $step):
            $state = stepState($i, $current_step);
        ?>
        <div class="step step-<?= $state ?>">
            <div class="step-connector"></div>
            <div class="step-icon">
                <?php if ($state === 'done'): ?>
                    <svg class="check-svg" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: ?>
                    <i data-lucide="<?= $step['icon'] ?>"></i>
                <?php endif; ?>
            </div>
            <div class="step-body">
                <div class="step-head">
                    <h4><?= $step['label'] ?></h4>
                    <?php if ($state === 'active'): ?>
                        <span class="active-pill">In progress</span>
                    <?php endif; ?>
                </div>
                <p class="step-desc"><?= $step['desc'] ?></p>
                <?php if ($state === 'done'): ?>
                    <span class="done-tag">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Done
                    </span>
                <?php endif; ?>
                <?php if ($step['key'] === 'consulting' && $state !== 'pending' && !empty($visit['doctor_name'])): ?>
                    <p class="doctor-name">Dr. <?= e($visit['doctor_name']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="refresh-hint">
        Showing current status &mdash; <a href="?visit_id=<?= e($visit_id) ?>">Refresh</a> to update
    </p>

</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
</script>
</body>
</html>