<?php
require_once '../config/config.php';
requirePermission('admin');

 $today = date('Y-m-d');

// Today's stats
 $stats = [
    'registered' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE()")->fetch_assoc()['cnt'],
    'triaged' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status IN ('triage','consulting','lab','pharmacy','completed','closed')")->fetch_assoc()['cnt'],
    'consulting' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status IN ('consulting','lab','pharmacy','completed','closed')")->fetch_assoc()['cnt'],
    'completed' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status IN ('completed','closed')")->fetch_assoc()['cnt'],
    'revenue' => $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'")->fetch_assoc()['total'],
];

// Queue status
 $queues = [
    'reception' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'registered'")->fetch_assoc()['cnt'],
    'triage' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'triage'")->fetch_assoc()['cnt'],
    'consultation' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'consulting'")->fetch_assoc()['cnt'],
    'lab' => $conn->query("SELECT COUNT(*) as cnt FROM lab_orders WHERE DATE(ordered_at) = CURDATE() AND status IN ('ordered','collected')")->fetch_assoc()['cnt'],
    'scan' => $conn->query("SELECT COUNT(*) as cnt FROM scans WHERE DATE(uploaded_at) = CURDATE() AND status IN ('ordered','scheduled')")->fetch_assoc()['cnt'],
    'pharmacy' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'pharmacy'")->fetch_assoc()['cnt'],
    'billing' => $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'completed'")->fetch_assoc()['cnt'],
];

// Department distribution today
 $dept_stats = $conn->query("
    SELECT d.name, COUNT(*) as cnt 
    FROM visits v 
    JOIN departments d ON v.department_id = d.id 
    WHERE v.visit_date = CURDATE() 
    GROUP BY d.name 
    ORDER BY cnt DESC
");
 $dept_data = [];
 $max_dept = 1;
while ($row = $dept_stats->fetch_assoc()) {
    $dept_data[] = $row;
    if ($row['cnt'] > $max_dept) $max_dept = $row['cnt'];
}

// Hourly visits today
 $hourly = [];
for ($h = 8; $h <= 20; $h++) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND HOUR(created_at) = $h")->fetch_assoc()['cnt'];
    $hourly[] = ['hour' => $h, 'count' => $count];
}
 $max_hourly = max(array_column($hourly, 'count')) ?: 1;

// Priority distribution today
 $priority_stats = $conn->query("
    SELECT COALESCE(t.priority, 'green') as priority, COUNT(*) as cnt
    FROM visits v
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE v.visit_date = CURDATE()
    GROUP BY priority
");
 $priorities = ['red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0];
while ($row = $priority_stats->fetch_assoc()) {
    $priorities[$row['priority']] = $row['cnt'];
}

// Recent visits (last 10)
 $recent = $conn->query("
    SELECT v.visit_id, v.token_number, v.status, v.created_at,
           p.name as patient_name, p.age, p.gender,
           d.name as department_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    WHERE v.visit_date = CURDATE()
    ORDER BY v.created_at DESC
    LIMIT 10
");

// Emergency count today
 $emergency_count = $conn->query("SELECT COUNT(*) as cnt FROM triage WHERE is_emergency = 1 AND DATE(triaged_at) = CURDATE()")->fetch_assoc()['cnt'];

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Dashboard | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Dashboard</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Dashboard</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Emergency Alert -->
            <?php if ($emergency_count > 0): ?>
            <div class="mb-5 px-4 py-3 rounded-md border bg-red-50 border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-full bg-red-500 pulse-dot"></span>
                    <span class="text-sm font-medium text-red-600 dark:text-red-400"><?= $emergency_count ?> emergency case(s) active</span>
                    <a href="../triage/emergency_queue.php" class="ml-auto text-xs font-medium text-red-600 dark:text-red-400 hover:underline">View →</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-md bg-blue-100 dark:bg-blue-500/20 shrink-0">
                                <i data-lucide="user-plus" class="size-5 text-blue-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Registered</p>
                                <p class="text-xl font-bold text-blue-500"><?= $stats['registered'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-md bg-red-100 dark:bg-red-500/20 shrink-0">
                                <i data-lucide="heart-pulse" class="size-5 text-red-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Triaged</p>
                                <p class="text-xl font-bold text-red-500"><?= $stats['triaged'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="stethoscope" class="size-5 text-green-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Consulted</p>
                                <p class="text-xl font-bold text-green-500"><?= $stats['consulting'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                <i data-lucide="check-circle" class="size-5 text-purple-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Completed</p>
                                <p class="text-xl font-bold text-purple-500"><?= $stats['completed'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card col-span-2 lg:col-span-1">
                    <div class="card-body p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                <i data-lucide="trending-up" class="size-5 text-custom-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Revenue</p>
                                <p class="text-xl font-bold text-custom-500"><?= number_format($stats['revenue'], 0) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Queue Status -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="size-2 rounded-full bg-green-500 pulse-dot"></span>
                        <h6 class="text-15 mb-0">Live Queue Status</h6>
                        <span class="text-xs text-slate-400 dark:text-zink-300 ml-auto"><?= date('h:i A') ?></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                        <?php 
                        $queue_config = [
                            'reception' => ['icon' => 'clipboard-list', 'color' => 'blue', 'href' => '../reception/index.php'],
                            'triage' => ['icon' => 'heart-pulse', 'color' => 'red', 'href' => '../triage/index.php'],
                            'consultation' => ['icon' => 'stethoscope', 'color' => 'green', 'href' => '../consultation/index.php'],
                            'lab' => ['icon' => 'test-tube', 'color' => 'sky', 'href' => '../lab/index.php'],
                            'scan' => ['icon' => 'scan', 'color' => 'purple', 'href' => '../scan/index.php'],
                            'pharmacy' => ['icon' => 'pill', 'color' => 'orange', 'href' => '../pharmacy/index.php'],
                            'billing' => ['icon' => 'receipt', 'color' => 'yellow', 'href' => '../billing/index.php'],
                        ];
                        foreach ($queues as $key => $count):
                            $cfg = $queue_config[$key];
                        ?>
                        <a href="<?= $cfg['href'] ?>" class="flex flex-col items-center p-3 rounded-md border border-slate-200 dark:border-zink-600 hover:bg-slate-50 dark:hover:bg-zink-700 transition-colors text-center">
                            <i data-lucide="<?= $cfg['icon'] ?>" class="size-5 text-<?= $cfg['color'] ?>-500 mb-2"></i>
                            <span class="text-2xl font-bold text-<?= $cfg['color'] ?>-500"><?= $count ?></span>
                            <span class="text-[10px] text-slate-400 dark:text-zink-300 uppercase mt-1"><?= ucfirst($key) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
                <!-- Hourly Visits -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Visits by Hour (Today)</h6>
                        <div class="flex items-end gap-1 h-40">
                            <?php foreach ($hourly as $h): 
                                $height = ($h['count'] / $max_hourly) * 100;
                                $isCurrentHour = (date('G') == $h['hour']);
                            ?>
                            <div class="flex-1 flex flex-col items-center justify-end">
                                <span class="text-[10px] text-slate-400 dark:text-zink-300 mb-1"><?= $h['count'] ?: '' ?></span>
                                <div class="w-full rounded-t transition-all duration-500 <?= $isCurrentHour ? 'bg-custom-500' : 'bg-slate-200 dark:bg-zink-600' ?>" style="height: <?= max($height, 4) ?>%"></div>
                                <span class="text-[9px] text-slate-400 dark:text-zink-300 mt-1"><?= $h['hour'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Department Distribution -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">By Department (Today)</h6>
                        <?php if (empty($dept_data)): ?>
                        <div class="py-8 text-center">
                            <p class="text-sm text-slate-400 dark:text-zink-300">No data yet</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($dept_data as $d): 
                                $width = ($d['cnt'] / $max_dept) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-600 dark:text-zink-200"><?= e($d['name']) ?></span>
                                    <span class="font-medium text-slate-500 dark:text-zink-300"><?= $d['cnt'] ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-zink-600 rounded-full h-2">
                                    <div class="bg-custom-500 h-2 rounded-full transition-all duration-500" style="width: <?= $width ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Priority + Recent Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
                <!-- Priority Distribution -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Priority Distribution</h6>
                        <div class="space-y-3">
                            <?php 
                            $priority_config = [
                                'red' => ['label' => 'Emergency', 'color' => 'red'],
                                'orange' => ['label' => 'Urgent', 'color' => 'orange'],
                                'yellow' => ['label' => 'Semi-urgent', 'color' => 'yellow'],
                                'green' => ['label' => 'Normal', 'color' => 'green'],
                            ];
                            $total_priority = array_sum($priorities) ?: 1;
                            foreach ($priorities as $key => $count):
                                $cfg = $priority_config[$key];
                                $pct = ($count / $total_priority) * 100;
                            ?>
                            <div class="flex items-center gap-3">
                                <span class="inline-block size-3 rounded-full bg-<?= $cfg['color'] ?>-500"></span>
                                <span class="text-sm text-slate-600 dark:text-zink-200 grow"><?= $cfg['label'] ?></span>
                                <span class="text-sm font-medium text-slate-500 dark:text-zink-300"><?= $count ?></span>
                                <span class="text-xs text-slate-400 dark:text-zink-300 w-10 text-right"><?= number_format($pct, 0) ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Visits -->
                <div class="card lg:col-span-2">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Recent Visits</h6>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-zink-600">
                                        <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 dark:text-zink-300 uppercase">Token</th>
                                        <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 dark:text-zink-300 uppercase">Patient</th>
                                        <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 dark:text-zink-300 uppercase">Dept</th>
                                        <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 dark:text-zink-300 uppercase">Status</th>
                                        <th class="px-2 py-2 text-left text-[10px] font-medium text-slate-500 dark:text-zink-300 uppercase">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-zink-700">
                                    <?php 
                                    $status_colors = [
                                        'registered' => 'bg-slate-100 dark:bg-zink-600 text-slate-600 dark:text-zink-200',
                                        'triage' => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400',
                                        'consulting' => 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400',
                                        'lab' => 'bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400',
                                        'pharmacy' => 'bg-orange-100 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400',
                                        'completed' => 'bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400',
                                        'closed' => 'bg-slate-200 dark:bg-zink-500 text-slate-500 dark:text-zink-300',
                                    ];
                                    while ($row = $recent->fetch_assoc()):
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                                        <td class="px-2 py-2 font-mono text-xs text-custom-500"><?= str_pad($row['token_number'], 3, '0', STR_PAD_LEFT) ?></td>
                                        <td class="px-2 py-2 text-xs text-slate-700 dark:text-zink-100"><?= e($row['patient_name']) ?></td>
                                        <td class="px-2 py-2 text-xs text-slate-500 dark:text-zink-200"><?= e($row['department_name']) ?></td>
                                        <td class="px-2 py-2">
                                            <span class="inline-flex px-1.5 py-0.5 text-[10px] font-medium rounded <?= $status_colors[$row['status']] ?? '' ?>"><?= ucfirst($row['status']) ?></span>
                                        </td>
                                        <td class="px-2 py-2 text-[10px] text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    </script>

</body>
</html>