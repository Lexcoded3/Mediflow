<?php
require_once '../config/config.php';
requirePermission('consultation');

// ── Resolve doctor identity ───────────────────────────────────────────────────
$doctor_id = $_SESSION['doctor_id'] ?? null;

if (!$doctor_id) {
    // Fallback: look up by staff_id in case session was set before the patch
    $sid = (int)$_SESSION['user_id'];
    $row = $conn->query("SELECT id, department_id FROM doctors WHERE staff_id = $sid LIMIT 1")->fetch_assoc();
    if ($row) {
        $doctor_id = $row['id'];
        $_SESSION['doctor_id'] = $doctor_id;
        $_SESSION['dept_id']   = $row['department_id'];
    }
}

if (!$doctor_id) {
    setFlash('error', 'Your account is not linked to a doctor profile. Contact the administrator.');
    header('Location: ../auth/login.php');
    exit;
}

$did = (int)$doctor_id;

// ── My queue: triage-done patients assigned to me ────────────────────────────
$waiting = $conn->query("
    SELECT v.visit_id, v.token_number, v.created_at, v.id AS visit_row_id,
           p.name AS patient_name, p.age, p.gender, p.patient_id AS patient_code,
           d.name AS department_name,
           t.priority, t.bp_systolic, t.bp_diastolic, t.temperature, t.pulse,
           t.chief_complaint, t.triaged_at
    FROM visits v
    JOIN patients p  ON p.id = v.patient_id
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE v.visit_date = CURDATE()
      AND v.status = 'triage'
      AND v.doctor_id = $did
    ORDER BY
        CASE COALESCE(t.priority, 'green')
            WHEN 'red'    THEN 1
            WHEN 'orange' THEN 2
            WHEN 'yellow' THEN 3
            ELSE 4
        END,
        v.token_number ASC
");

// ── Currently consulting (mine) ───────────────────────────────────────────────
$in_progress = $conn->query("
    SELECT v.visit_id, v.token_number,
           p.name AS patient_name, p.age, p.gender,
           d.name AS department_name,
           t.priority,
           c.consulted_at
    FROM visits v
    JOIN patients p  ON p.id = v.patient_id
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN triage t       ON t.visit_id = v.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE v.visit_date = CURDATE()
      AND v.status = 'consulting'
      AND v.doctor_id = $did
    ORDER BY c.consulted_at DESC
");

// ── Awaiting lab results (mine) ───────────────────────────────────────────────
$lab_waiting = $conn->query("
    SELECT v.visit_id, v.token_number,
           p.name AS patient_name, p.age,
           d.name AS department_name,
           t.priority,
           COUNT(lo.id)                                          AS total_tests,
           SUM(lo.status = 'completed')                         AS completed_tests
    FROM visits v
    JOIN patients p  ON p.id = v.patient_id
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN triage t    ON t.visit_id = v.id
    LEFT JOIN lab_orders lo ON lo.visit_id = v.id
    WHERE v.visit_date = CURDATE()
      AND v.status = 'lab'
      AND v.doctor_id = $did
    GROUP BY v.id
    ORDER BY v.token_number ASC
");

// ── Today's summary stats ─────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*)                                    AS total,
        SUM(v.status = 'triage')                   AS waiting,
        SUM(v.status = 'consulting')               AS consulting,
        SUM(v.status = 'lab')                      AS lab,
        SUM(v.status IN ('completed','closed'))    AS done
    FROM visits v
    WHERE v.visit_date = CURDATE() AND v.doctor_id = $did
")->fetch_assoc();

// ── Doctor profile for header ─────────────────────────────────────────────────
$doctor = $conn->query("
    SELECT doc.name, doc.qualification, doc.specialization,
           dep.name AS department_name
    FROM doctors doc
    LEFT JOIN departments dep ON dep.id = doc.department_id
    WHERE doc.id = $did
")->fetch_assoc();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>My Queue | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        /* Auto-refresh indicator */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinning { animation: spin 1s linear infinite; }
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
    <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center">
        <div class="grow">
            <h5 class="text-16">My Queue</h5>
        </div>
        <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
            <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                <a href="index.php" class="text-slate-400">Home</a>
            </li>
            <li class="text-slate-700 dark:text-zink-100">Consultation</li>
        </ul>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600' : 'bg-red-50 border-red-200 text-red-600' ?>">
        <div class="flex items-center gap-2">
            <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i>
            <span><?= e($flash['msg']) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Doctor identity banner -->
    <div class="card mb-5 border-l-4 border-custom-500">
        <div class="card-body py-3">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                        <i data-lucide="stethoscope" class="size-5 text-custom-600 dark:text-custom-400"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-slate-700 dark:text-zink-100">Dr. <?= e($doctor['name']) ?></p>
                        <p class="text-xs text-slate-400">
                            <?= e($doctor['department_name']) ?>
                            <?php if ($doctor['specialization']): ?>
                            &middot; <?= e($doctor['specialization']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <i data-lucide="calendar" class="size-3.5"></i>
                    <?= date('l, d F Y') ?>
                    <span class="mx-1 text-slate-200 dark:text-zink-600">|</span>
                    <button id="refresh-btn" onclick="location.reload()"
                            class="inline-flex items-center gap-1 text-custom-500 hover:text-custom-600">
                        <i data-lucide="refresh-cw" class="size-3.5" id="refresh-icon"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-5">
        <?php
        $stat_items = [
            ['Total Today', $stats['total'],      'users',        'text-slate-600',   'bg-slate-100 dark:bg-zink-600'],
            ['Waiting',     $stats['waiting'],    'clock',        'text-yellow-600',  'bg-yellow-100 dark:bg-yellow-500/20'],
            ['Consulting',  $stats['consulting'], 'stethoscope',  'text-sky-600',     'bg-sky-100 dark:bg-sky-500/20'],
            ['In Lab',      $stats['lab'],        'flask-conical','text-purple-600',  'bg-purple-100 dark:bg-purple-500/20'],
            ['Completed',   $stats['done'],       'check-circle', 'text-emerald-600', 'bg-emerald-100 dark:bg-emerald-500/20'],
        ];
        foreach ($stat_items as [$label, $val, $icon, $tc, $bg]): ?>
        <div class="card mb-0">
            <div class="card-body py-3 px-4 flex items-center gap-3">
                <div class="flex items-center justify-center size-9 rounded-md <?= $bg ?> shrink-0">
                    <i data-lucide="<?= $icon ?>" class="size-4 <?= $tc ?>"></i>
                </div>
                <div>
                    <p class="text-xl font-bold <?= $tc ?> leading-tight"><?= $val ?? 0 ?></p>
                    <p class="text-[11px] text-slate-400"><?= $label ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Waiting Queue ─────────────────────────────────────────────────── -->
    <div class="card mb-5">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h6 class="text-15">
                    Waiting for Consultation
                    <span class="text-slate-400 font-normal">(<?= $waiting->num_rows ?>)</span>
                </h6>
                <?php if ($waiting->num_rows > 0): ?>
                <span class="inline-flex items-center gap-1 text-xs text-yellow-600 bg-yellow-100 dark:bg-yellow-500/20 px-2.5 py-1 rounded-full">
                    <i data-lucide="clock" class="size-3"></i> Triage complete
                </span>
                <?php endif; ?>
            </div>

            <?php if ($waiting->num_rows > 0): ?>
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="ltr:text-left bg-slate-100 dark:bg-zink-600 text-slate-500 dark:text-zink-200">
                        <tr>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 w-16">Token</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Patient</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Vitals</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Complaint</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Priority</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Wait</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php while ($w = $waiting->fetch_assoc()):
                            $wait_mins = $w['triaged_at'] ? round((time() - strtotime($w['triaged_at'])) / 60) : '—';
                            $is_urgent = in_array($w['priority'], ['red', 'orange']);
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50 <?= $is_urgent ? 'bg-red-50/40 dark:bg-red-500/5' : '' ?>">
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <div class="flex items-center gap-1.5">
                                    <?= priorityDot($w['priority']) ?>
                                    <span class="font-bold text-sm"><?= str_pad($w['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                                </div>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($w['patient_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($w['patient_code']) ?> &middot; <?= e($w['age']) ?>y &middot; <?= e($w['gender']) ?></p>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5 text-xs">
                                <?php if ($w['temperature']): ?>
                                <span class="<?= $w['temperature'] > 37.5 ? 'text-red-500 font-medium' : 'text-slate-500 dark:text-zink-300' ?>">
                                    <?= $w['temperature'] ?>°C
                                </span>
                                <?php endif; ?>
                                <?php if ($w['bp_systolic']): ?>
                                <span class="text-slate-300 mx-0.5">|</span>
                                <span class="<?= ($w['bp_systolic'] > 140 || $w['bp_diastolic'] > 90) ? 'text-red-500 font-medium' : 'text-slate-500 dark:text-zink-300' ?>">
                                    <?= $w['bp_systolic'] ?>/<?= $w['bp_diastolic'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($w['pulse']): ?>
                                <span class="text-slate-300 mx-0.5">|</span>
                                <span class="text-slate-500 dark:text-zink-300"><?= $w['pulse'] ?> bpm</span>
                                <?php endif; ?>
                                <?php if (!$w['temperature'] && !$w['bp_systolic']): ?>
                                <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5 text-xs text-slate-500 dark:text-zink-300 max-w-[180px]">
                                <span class="line-clamp-2 whitespace-normal"><?= e($w['chief_complaint'] ?: '—') ?></span>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <?php
                                $priority_badges = [
                                    'red'    => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
                                    'orange' => 'bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400',
                                    'yellow' => 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400',
                                    'green'  => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
                                ];
                                $pb = $priority_badges[$w['priority']] ?? 'bg-slate-100 text-slate-500';
                                ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded <?= $pb ?>">
                                    <?= ucfirst($w['priority'] ?? 'normal') ?>
                                </span>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5 text-xs">
                                <?php if (is_numeric($wait_mins)): ?>
                                <span class="<?= $wait_mins > 30 ? 'text-red-500 font-semibold' : 'text-slate-500 dark:text-zink-300' ?>">
                                    <?= $wait_mins ?>m
                                </span>
                                <?php else: ?>
                                <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <a href="consult.php?visit_id=<?= urlencode($w['visit_id']) ?>"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                                    <i data-lucide="play" class="size-3"></i> Consult
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="py-14 text-center">
                <i data-lucide="inbox" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-500"></i>
                <p class="text-slate-400 dark:text-zink-300 font-medium">No patients waiting</p>
                <p class="text-xs text-slate-300 dark:text-zink-500 mt-1">Patients assigned to you will appear here after triage</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── In Progress ───────────────────────────────────────────────────── -->
    <?php if ($in_progress->num_rows > 0): ?>
    <div class="card mb-5">
        <div class="card-body">
            <h6 class="text-15 mb-4">
                In Progress
                <span class="text-slate-400 font-normal">(<?= $in_progress->num_rows ?>)</span>
            </h6>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php while ($ip = $in_progress->fetch_assoc()): ?>
                <div class="p-4 rounded-lg border border-sky-200 dark:border-sky-500/30 bg-sky-50/50 dark:bg-sky-500/5">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center size-10 rounded-full bg-sky-100 dark:bg-sky-500/20 shrink-0">
                            <span class="text-sm font-bold text-sky-600 dark:text-sky-400">
                                <?= str_pad($ip['token_number'], 3, '0', STR_PAD_LEFT) ?>
                            </span>
                        </div>
                        <div class="grow min-w-0">
                            <p class="text-sm font-semibold text-slate-700 dark:text-zink-100 truncate"><?= e($ip['patient_name']) ?></p>
                            <p class="text-xs text-slate-400 mt-0.5"><?= e($ip['age']) ?>y &middot; <?= e($ip['gender']) ?></p>
                            <?php if ($ip['consulted_at']): ?>
                            <p class="text-[10px] text-slate-400 mt-1">
                                Started <?= date('h:i A', strtotime($ip['consulted_at'])) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?= priorityDot($ip['priority']) ?>
                    </div>
                    <div class="mt-3 pt-3 border-t border-sky-200/60 dark:border-sky-500/20">
                        <a href="consult.php?visit_id=<?= urlencode($ip['visit_id']) ?>"
                           class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-sky-600 dark:text-sky-400 bg-sky-100 dark:bg-sky-500/20 rounded-md hover:bg-sky-200 dark:hover:bg-sky-500/30 transition-colors">
                            <i data-lucide="pencil" class="size-3"></i> Continue Consultation
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Awaiting Lab ──────────────────────────────────────────────────── -->
    <?php if ($lab_waiting->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <h6 class="text-15 mb-4">
                Awaiting Lab Results
                <span class="text-slate-400 font-normal">(<?= $lab_waiting->num_rows ?>)</span>
            </h6>
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="ltr:text-left bg-slate-100 dark:bg-zink-600 text-slate-500 dark:text-zink-200">
                        <tr>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Token</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Patient</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Priority</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Tests</th>
                            <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php while ($lw = $lab_waiting->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5 font-bold text-sm">
                                <?= str_pad($lw['token_number'], 3, '0', STR_PAD_LEFT) ?>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($lw['patient_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($lw['age']) ?>y</p>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5"><?= priorityDot($lw['priority']) ?></td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5 text-xs text-slate-500 dark:text-zink-300">
                                <?= (int)$lw['completed_tests'] ?> / <?= (int)$lw['total_tests'] ?> complete
                                <?php if ($lw['completed_tests'] >= $lw['total_tests'] && $lw['total_tests'] > 0): ?>
                                <span class="ml-1 text-emerald-500 font-medium">✓ Ready</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3.5 py-3 first:pl-5 last:pr-5">
                                <a href="consult.php?visit_id=<?= urlencode($lw['visit_id']) ?>"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-500/20 rounded-md hover:bg-purple-200 dark:hover:bg-purple-500/30 transition-colors">
                                    <i data-lucide="flask-conical" class="size-3"></i> Review Results
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
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

    // Auto-refresh every 60s, spin the icon while loading
    let countdown = 60;
    const btn  = document.getElementById('refresh-btn');
    const icon = document.getElementById('refresh-icon');

    setInterval(() => {
        countdown--;
        if (countdown <= 0) {
            icon && icon.classList.add('spinning');
            location.reload();
        }
    }, 1000);
});
</script>
</body>
</html>