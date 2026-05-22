<?php
require_once '../config/config.php';
requirePermission('reception');

$tab = $_GET['tab'] ?? 'patients';
$search = trim($_GET['search'] ?? '');

// ── Patient detail modal data ────────────────────────────────────────────────
$patient_detail = null;
$patient_visits = null;
if (isset($_GET['patient_id'])) {
    $pid = $conn->real_escape_string($_GET['patient_id']);
    $patient_detail = $conn->query("SELECT * FROM patients WHERE patient_id = '$pid'")->fetch_assoc();
    if ($patient_detail) {
        $patient_visits = $conn->query("
    SELECT v.*, d.name AS dept_name, doc.name AS doctor_name,
           COALESCE(b.total_amount, 0) AS bill_total, 
           COALESCE(b.payment_status, 'unpaid') AS bill_status
    FROM visits v
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN doctors doc ON doc.id = v.doctor_id
    LEFT JOIN bills b ON b.visit_id = v.id
    WHERE v.patient_id = {$patient_detail['id']}
    ORDER BY v.visit_date DESC, v.created_at DESC
    LIMIT 20
");
    }
}

// ── Patients list ────────────────────────────────────────────────────────────
$where = '';
if ($search) {
    $s = $conn->real_escape_string($search);
    $where = "WHERE p.name LIKE '%$s%' OR p.patient_id LIKE '%$s%' OR p.phone LIKE '%$s%'";
}
$patients = $conn->query("
    SELECT p.*,
           COUNT(v.id) AS total_visits,
           MAX(v.visit_date) AS last_visit
    FROM patients p
    LEFT JOIN visits v ON v.patient_id = p.id
    $where
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 100
");

// ── Today's queue ────────────────────────────────────────────────────────────
$queue = $conn->query("
    SELECT v.*, p.name AS patient_name, p.patient_id AS patient_code,
           p.phone, p.gender, p.age,
           d.name AS dept_name,
           doc.name AS doctor_name
    FROM visits v
    JOIN patients p ON p.id = v.patient_id
    JOIN departments d ON d.id = v.department_id
    LEFT JOIN doctors doc ON doc.id = v.doctor_id
    WHERE v.visit_date = CURDATE()
    ORDER BY v.token_number ASC
");

// ── Queue stats ──────────────────────────────────────────────────────────────
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'registered') AS waiting,
        SUM(status = 'triage') AS triage,
        SUM(status = 'consulting') AS consulting,
        SUM(status IN ('lab','pharmacy')) AS in_progress,
        SUM(status IN ('completed','closed')) AS done
    FROM visits WHERE visit_date = CURDATE()
")->fetch_assoc();

// ── Departments ──────────────────────────────────────────────────────────────
$departments = $conn->query("
    SELECT d.*,
           COUNT(DISTINCT doc.id) AS doctor_count,
           COUNT(DISTINCT CASE WHEN v.visit_date = CURDATE() THEN v.id END) AS today_visits,
           COUNT(DISTINCT CASE WHEN v.visit_date = CURDATE() AND v.status NOT IN ('completed','closed') THEN v.id END) AS active_visits
    FROM departments d
    LEFT JOIN doctors doc ON doc.department_id = d.id AND doc.status = 1
    LEFT JOIN visits v ON v.department_id = d.id
    GROUP BY d.id
    ORDER BY d.name ASC
");

$flash = getFlash();

$status_styles = [
    'registered' => ['bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400',   'circle-dot'],
    'triage'     => ['bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400',        'heart-pulse'],
    'consulting' => ['bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400','stethoscope'],
    'lab'        => ['bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400',        'test-tube'],
    'pharmacy'   => ['bg-orange-100 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400','pill'],
    'completed'  => ['bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400','circle-check'],
    'closed'     => ['bg-slate-100 dark:bg-zink-600 text-slate-500 dark:text-zink-300',     'archive'],
];
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Records | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        .tab-btn { transition: all .15s ease; }
        .tab-btn.active { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .dark .tab-btn.active { background: rgb(63,63,70); }
        @media print {
            body * { visibility: hidden; }
            #token-slip, #token-slip * { visibility: visible; }
            #token-slip { position: fixed; top: 0; left: 0; width: 100%; }
        }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm flex flex-col">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

         <!-- Breadcrumb -->
    <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
        <div class="grow">
            <h5 class="text-16">Records</h5>
        </div>
        <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
            <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                <a href="index.php" class="text-slate-400">Home</a>
            </li>
            <li class="text-slate-700 dark:text-zink-100">Records</li>
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

    <!-- Today's Stats Bar -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-5">
        <?php
        $stat_items = [
            ['label'=>'Total Today',  'val'=>$stats['total'],       'color'=>'text-slate-600',   'icon'=>'users'],
            ['label'=>'Waiting',      'val'=>$stats['waiting'],     'color'=>'text-blue-500',    'icon'=>'clock'],
            ['label'=>'Triage',       'val'=>$stats['triage'],      'color'=>'text-red-500',     'icon'=>'heart-pulse'],
            ['label'=>'Consulting',   'val'=>$stats['consulting'],  'color'=>'text-green-500',   'icon'=>'stethoscope'],
            ['label'=>'In Progress',  'val'=>$stats['in_progress'], 'color'=>'text-orange-500',  'icon'=>'activity'],
            ['label'=>'Completed',    'val'=>$stats['done'],        'color'=>'text-emerald-500', 'icon'=>'circle-check'],
        ];
        foreach ($stat_items as $s): ?>
        <div class="card mb-0">
            <div class="card-body py-3 px-4 flex items-center gap-3">
                <i data-lucide="<?= $s['icon'] ?>" class="w-5 h-5 <?= $s['color'] ?> shrink-0"></i>
                <div>
                    <p class="text-xl font-bold <?= $s['color'] ?> leading-tight"><?= $s['val'] ?? 0 ?></p>
                    <p class="text-[11px] text-slate-400"><?= $s['label'] ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="mb-4 flex gap-1 p-1 bg-slate-100 dark:bg-zink-700 rounded-lg w-fit">
        <?php
        $tabs = [
            'patients'    => ['users',          'Patients'],
            'queue'       => ['list-ordered',   "Today's Queue"],
            'departments' => ['building-2',     'Departments'],
        ];
        foreach ($tabs as $key => [$icon, $label]): ?>
        <a href="?tab=<?= $key ?>" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-slate-600 dark:text-zink-200 hover:text-slate-800 <?= $tab === $key ? 'active text-slate-800 dark:text-zink-100' : '' ?>">
            <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i> <?= $label ?>
            <?php if ($key === 'queue' && ($stats['total'] ?? 0) > 0): ?>
            <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-medium rounded-full bg-custom-500 text-white"><?= $stats['total'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════ TAB: PATIENTS -->
    <?php if ($tab === 'patients'): ?>
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                <h6 class="text-15">Patient Records <span class="text-sm font-normal text-slate-400">(<?= $patients->num_rows ?>)</span></h6>
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="tab" value="patients">
                    <div class="relative">
                        <input type="text" name="search" value="<?= e($search) ?>"
                               placeholder="Search name, ID, phone..."
                               class="form-input pl-8 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm min-w-[260px]">
                        <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    </div>
                    <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md">Search</button>
                    <?php if ($search): ?>
                    <a href="?tab=patients" class="px-3 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:text-zink-200 rounded-md">Clear</a>
                    <?php endif; ?>
                    <a href="records_print.php" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:text-zink-200 rounded-md">
    <i data-lucide="printer" class="w-4 h-4"></i> Print Records
</a>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-zink-500">
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Patient</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">ID</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Contact</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Age / Gender</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Blood</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Visits</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Last Visit</th>
                            <th class="py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php if ($patients->num_rows === 0): ?>
                        <tr><td colspan="8" class="py-10 text-center text-slate-400">
                            <i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2"></i>
                            <?= $search ? 'No patients found for "' . e($search) . '"' : 'No patients registered yet' ?>
                        </td></tr>
                        <?php else: ?>
                        <?php while ($p = $patients->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-custom-100 text-custom-600 font-bold text-sm shrink-0">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                    <span class="font-medium text-slate-700 dark:text-zink-100"><?= e($p['name']) ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-slate-500 dark:text-zink-300"><?= e($p['patient_id']) ?></td>
                            <td class="py-3 px-3 text-slate-500 dark:text-zink-300"><?= e($p['phone'] ?: '—') ?></td>
                            <td class="py-3 px-3 text-slate-500 dark:text-zink-300">
                                <?= e($p['age'] ?: '—') ?>
                                <?php if ($p['gender']): ?>
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded <?= $p['gender'] === 'Male' ? 'bg-blue-100 text-blue-500' : ($p['gender'] === 'Female' ? 'bg-pink-100 text-pink-500' : 'bg-slate-100 text-slate-500') ?>">
                                    <?= $p['gender'] ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3">
                                <?php if ($p['blood_group']): ?>
                                <span class="text-xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-500"><?= e($p['blood_group']) ?></span>
                                <?php else: ?>
                                <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-slate-500 dark:text-zink-300"><?= $p['total_visits'] ?></td>
                            <td class="py-3 px-3 text-slate-500 dark:text-zink-300 text-xs">
                                <?= $p['last_visit'] ? date('d M Y', strtotime($p['last_visit'])) : '—' ?>
                            </td>
                            <td class="py-3 px-3">
                                <a href="?tab=patients&patient_id=<?= $p['patient_id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                                   class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-custom-500 bg-custom-50 hover:bg-custom-100 dark:bg-custom-500/10 dark:hover:bg-custom-500/20 rounded-md transition-colors">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Patient Detail Panel -->
    <?php if ($patient_detail): ?>
    <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Profile Card -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h6 class="text-15">Patient Profile</h6>
                    <a href="?tab=patients<?= $search ? '&search='.urlencode($search) : '' ?>" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                </div>
                <div class="flex flex-col items-center text-center pb-4 mb-4 border-b border-slate-100 dark:border-zink-600">
                    <div class="flex items-center justify-center w-16 h-16 rounded-full bg-custom-100 text-custom-600 font-bold text-2xl mb-3">
                        <?= strtoupper(substr($patient_detail['name'], 0, 1)) ?>
                    </div>
                    <h5 class="font-semibold text-slate-700 dark:text-zink-100"><?= e($patient_detail['name']) ?></h5>
                    <p class="text-sm text-slate-400 font-mono mt-0.5"><?= e($patient_detail['patient_id']) ?></p>
                    <?php if ($patient_detail['blood_group']): ?>
                    <span class="mt-2 text-xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-500"><?= e($patient_detail['blood_group']) ?></span>
                    <?php endif; ?>
                </div>
                <dl class="space-y-2.5 text-sm">
                    <?php
                    $fields = [
                        ['Phone',    'phone',   'phone'],
                        ['Email',    'email',   'mail'],
                        ['Age',      'age',     'calendar'],
                        ['Gender',   'gender',  'user'],
                        ['DOB',      'dob',     'cake'],
                        ['Address',  'address', 'map-pin'],
                    ];
                    foreach ($fields as [$label, $key, $icon]):
                        $val = $patient_detail[$key] ?? '';
                        if (!$val) continue;
                        if ($key === 'dob') $val = date('d M Y', strtotime($val));
                    ?>
                    <div class="flex gap-2">
                        <i data-lucide="<?= $icon ?>" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5"></i>
                        <div>
                            <span class="text-slate-400 text-xs"><?= $label ?></span>
                            <p class="text-slate-700 dark:text-zink-100"><?= e($val) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($patient_detail['emergency_contact_name']): ?>
                    <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zink-600">
                        <p class="text-xs text-slate-400 mb-1">Emergency Contact</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($patient_detail['emergency_contact_name']) ?></p>
                        <p class="text-sm text-slate-500"><?= e($patient_detail['emergency_contact_phone'] ?? '') ?></p>
                    </div>
                    <?php endif; ?>
                </dl>
                <p class="text-[11px] text-slate-300 dark:text-zink-500 mt-4">
                    Registered <?= date('d M Y', strtotime($patient_detail['created_at'])) ?>
                </p>
            </div>
        </div>

        <!-- Visit History -->
        <div class="card lg:col-span-2">
            <div class="card-body">
                <h6 class="text-15 mb-4">Visit History</h6>
                <?php if (!$patient_visits || $patient_visits->num_rows === 0): ?>
                <div class="py-10 text-center text-slate-400">
                    <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2"></i>
                    <p>No visits recorded</p>
                </div>
                <?php else: ?>
                <div class="space-y-2">
                    <?php while ($v = $patient_visits->fetch_assoc()):
                        [$sstyle, $sicon] = $status_styles[$v['status']] ?? ['bg-slate-100 text-slate-500', 'circle'];
                    ?>
                    <div class="flex items-center gap-3 p-3 rounded-md border border-slate-100 dark:border-zink-600 hover:border-slate-200 dark:hover:border-zink-500 transition-colors">
                        <div class="text-center shrink-0 w-12">
                            <p class="text-lg font-bold text-slate-700 dark:text-zink-100 leading-tight"><?= date('d', strtotime($v['visit_date'])) ?></p>
                            <p class="text-[10px] text-slate-400 uppercase"><?= date('M Y', strtotime($v['visit_date'])) ?></p>
                        </div>
                        <div class="grow min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs text-slate-500 dark:text-zink-300"><?= e($v['visit_id']) ?></span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded <?= $sstyle ?>">
                                    <?= ucfirst($v['status']) ?>
                                </span>
                                <?php if ($v['bill_status'] === 'paid'): ?>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-600">Paid</span>
                                <?php elseif ($v['bill_total'] > 0): ?>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-600">Unpaid</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?= e($v['dept_name']) ?>
                                <?= $v['doctor_name'] ? ' · Dr. ' . e($v['doctor_name']) : '' ?>
                                · Token #<?= $v['token_number'] ?>
                            </p>
                        </div>
                        <?php if ($v['bill_total'] > 0): ?>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold text-slate-700 dark:text-zink-100">$<?= number_format($v['bill_total'], 2) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════ TAB: QUEUE -->
    <?php elseif ($tab === 'queue'): ?>
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h6 class="text-15">Today's Queue — <?= date('d F Y') ?></h6>
                <a href="?tab=queue" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 rounded-md">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                </a>

            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-zink-500">
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300 w-12">#</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Patient</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Visit ID</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Department</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Doctor</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Status</th>
                            <th class="text-left py-3 px-3 font-medium text-slate-500 dark:text-zink-300">Registered</th>
                            <th class="py-3 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php if ($queue->num_rows === 0): ?>
                        <tr><td colspan="8" class="py-10 text-center text-slate-400">
                            <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2"></i>
                            No visits registered today
                        </td></tr>
                        <?php else: ?>
                        <?php while ($v = $queue->fetch_assoc()):
                            [$sstyle, $sicon] = $status_styles[$v['status']] ?? ['bg-slate-100 text-slate-500', 'circle'];
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 dark:bg-zink-600 text-slate-600 dark:text-zink-200 font-bold text-xs">
                                    <?= $v['token_number'] ?>
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <p class="font-medium text-slate-700 dark:text-zink-100"><?= e($v['patient_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($v['patient_code']) ?> · <?= e($v['age'] ?? '?') ?> <?= $v['gender'] ? strtolower($v['gender'][0]) : '' ?></p>
                            </td>
                            <td class="py-3 px-3 font-mono text-xs text-slate-500 dark:text-zink-300"><?= e($v['visit_id']) ?></td>
                            <td class="py-3 px-3 text-slate-600 dark:text-zink-200"><?= e($v['dept_name']) ?></td>
                            <td class="py-3 px-3 text-slate-500 dark:text-zink-300 text-xs">
                                <?= $v['doctor_name'] ? 'Dr. ' . e($v['doctor_name']) : '<span class="text-slate-300">—</span>' ?>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded <?= $sstyle ?>">
                                    <i data-lucide="<?= $sicon ?>" class="w-3 h-3"></i>
                                    <?= ucfirst($v['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-xs text-slate-400">
                                <?= date('h:i A', strtotime($v['created_at'])) ?>
                            </td>
                            <td class="py-3 px-3">
                                <a href="../track.php?visit_id=<?= $v['visit_id'] ?>" target="_blank"
                                   class="inline-flex items-center gap-1 px-2 py-1 text-xs text-slate-500 hover:text-custom-500 bg-slate-100 dark:bg-zink-600 hover:bg-custom-50 dark:hover:bg-custom-500/10 rounded transition-colors">
                                    <i data-lucide="external-link" class="w-3 h-3"></i> Track
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════ TAB: DEPARTMENTS -->
    <?php elseif ($tab === 'departments'): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php while ($dept = $departments->fetch_assoc()):
            $docs = $conn->query("
                SELECT doc.*, df.fee
                FROM doctors doc
                LEFT JOIN doctor_fees df ON df.doctor_id = doc.id AND df.type = 'opd'
                WHERE doc.department_id = {$dept['id']} AND doc.status = 1
                ORDER BY doc.name ASC
            ");
        ?>
        <div class="card mb-0">
            <div class="card-body">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h6 class="font-semibold text-slate-700 dark:text-zink-100"><?= e($dept['name']) ?></h6>
                        <p class="text-xs text-slate-400 mt-0.5"><?= $dept['doctor_count'] ?> doctor<?= $dept['doctor_count'] != 1 ? 's' : '' ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-custom-500"><?= $dept['today_visits'] ?></p>
                        <p class="text-[10px] text-slate-400">today</p>
                    </div>
                </div>

                <?php if ($dept['active_visits'] > 0): ?>
                <div class="flex items-center gap-1.5 mb-3 px-2.5 py-1.5 rounded bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 text-xs">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                    <?= $dept['active_visits'] ?> active visit<?= $dept['active_visits'] != 1 ? 's' : '' ?> in progress
                </div>
                <?php endif; ?>

                <?php if ($docs->num_rows > 0): ?>
                <div class="space-y-2 mt-3 pt-3 border-t border-slate-100 dark:border-zink-600">
                    <?php while ($doc = $docs->fetch_assoc()): ?>
                    <div class="flex items-center gap-2.5">
                        <div class="flex items-center justify-center w-7 h-7 rounded-full bg-green-100 text-green-600 shrink-0">
                            <i data-lucide="stethoscope" class="w-3.5 h-3.5"></i>
                        </div>
                        <div class="grow min-w-0">
                            <p class="text-sm font-medium text-slate-700 dark:text-zink-100 truncate">Dr. <?= e($doc['name']) ?></p>
                            <?php if ($doc['phone']): ?>
                            <p class="text-xs text-slate-400"><?= e($doc['phone']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($doc['fee']): ?>
                        <span class="text-xs font-semibold text-slate-500 dark:text-zink-300 shrink-0">
                            $<?= number_format($doc['fee'], 0) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-xs text-slate-300 dark:text-zink-500 mt-3 pt-3 border-t border-slate-100 dark:border-zink-600">No doctors assigned</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

</div>
</div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
</body>
</html>