<?php
require_once '../../config.php';
requirePermission('reports');

// Date filter
 $date_from = $_GET['from'] ?? date('Y-m-01'); // First of month
 $date_to = $_GET['to'] ?? date('Y-m-d');

if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Summary stats
 $total_visits = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];
 $total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM bills WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to' AND payment_status = 'paid'")->fetch_assoc()['total'];
 $avg_revenue = $total_visits > 0 ? $total_revenue / $total_visits : 0;
 $unique_patients = $conn->query("SELECT COUNT(DISTINCT patient_id) as cnt FROM visits WHERE visit_date BETWEEN '$date_from' AND '$date_to'")->fetch_assoc()['cnt'];

// Department breakdown
 $dept_breakdown = $conn->query("
    SELECT d.name, COUNT(*) as visits, 
           COALESCE(SUM((SELECT total_amount FROM bills WHERE visit_id = v.id AND payment_status = 'paid')), 0) as revenue
    FROM visits v
    JOIN departments d ON v.department_id = d.id
    WHERE v.visit_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY d.name
    ORDER BY visits DESC
");

// Doctor breakdown
 $doc_breakdown = $conn->query("
    SELECT doc.name, d.name as department, COUNT(*) as visits,
           COALESCE(SUM((SELECT total_amount FROM bills WHERE visit_id = v.id AND payment_status = 'paid')), 0) as revenue
    FROM visits v
    JOIN doctors doc ON v.doctor_id = doc.id
    JOIN departments d ON doc.department_id = d.id
    WHERE v.visit_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY doc.id
    ORDER BY visits DESC
");

// Status breakdown
 $status_breakdown = $conn->query("
    SELECT status, COUNT(*) as cnt 
    FROM visits 
    WHERE visit_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY status
");

// Daily visits trend
 $daily_trend = $conn->query("
    SELECT visit_date, COUNT(*) as visits 
    FROM visits 
    WHERE visit_date BETWEEN '$date_from' AND '$date_to'
    GROUP BY visit_date
    ORDER BY visit_date ASC
");
 $daily_data = [];
while ($row = $daily_trend->fetch_assoc()) {
    $daily_data[] = $row;
}
 $max_daily = max(array_column($daily_data, 'visits')) ?: 1;

// Build status data
 $statuses = ['registered' => 0, 'triage' => 0, 'consulting' => 0, 'lab' => 0, 'pharmacy' => 0, 'completed' => 0, 'closed' => 0];
while ($row = $status_breakdown->fetch_assoc()) {
    $statuses[$row['status']] = $row['cnt'];
}

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Reports | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">
    <script src="../../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../../assets/css/starcode2.css">
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include '../sidenav.php'; ?>
<?php include '../topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Reports</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Reports</li>
                </ul>
            </div>

            <!-- Date Filter -->
            <form method="GET" class="card mb-5">
                <div class="card-body">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-zink-200 mb-1.5">From</label>
                            <input type="date" name="from" value="<?= $date_from ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-zink-200 mb-1.5">To</label>
                            <input type="date" name="to" value="<?= $date_to ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                        </div>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                            <i data-lucide="filter" class="size-4"></i> Apply
                        </button>
                        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                            <i data-lucide="printer" class="size-4"></i> Print
                        </button>
                    </div>
                </div>
            </form>

            <!-- Summary Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                <div class="card">
                    <div class="card-body p-4">
                        <p class="text-xs text-slate-500 dark:text-zink-200">Total Visits</p>
                        <p class="text-2xl font-bold text-custom-500"><?= number_format($total_visits) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <p class="text-xs text-slate-500 dark:text-zink-200">Unique Patients</p>
                        <p class="text-2xl font-bold text-blue-500"><?= number_format($unique_patients) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <p class="text-xs text-slate-500 dark:text-zink-200">Total Revenue</p>
                        <p class="text-2xl font-bold text-green-500"><?= number_format($total_revenue, 0) ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <p class="text-xs text-slate-500 dark:text-zink-200">Avg Revenue/Visit</p>
                        <p class="text-2xl font-bold text-purple-500"><?= number_format($avg_revenue, 0) ?></p>
                    </div>
                </div>
            </div>

            <!-- Daily Trend -->
            <div class="card mb-5">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Daily Visits Trend</h6>
                    <div class="flex items-end gap-0.5 h-40 overflow-x-auto">
                        <?php foreach ($daily_data as $d): 
                            $height = ($d['visits'] / $max_daily) * 100;
                            $day = date('d', strtotime($d['visit_date']));
                            $month = date('M', strtotime($d['visit_date']));
                        ?>
                        <div class="flex flex-col items-center justify-end min-w-[30px]">
                            <span class="text-[10px] text-slate-400 dark:text-zink-300 mb-1"><?= $d['visits'] ?></span>
                            <div class="w-6 rounded-t bg-custom-500 hover:bg-custom-600 transition-colors" style="height: <?= max($height, 4) ?>%" title="<?= $d['visit_date'] ?>: <?= $d['visits'] ?> visits"></div>
                            <span class="text-[8px] text-slate-400 dark:text-zink-300 mt-1"><?= $day ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="card mb-5">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Visit Status Distribution</h6>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                        <?php 
                        $status_config = [
                            'registered' => ['label' => 'Registered', 'color' => 'slate', 'icon' => 'clipboard-list'],
                            'triage' => ['label' => 'Triaged', 'color' => 'yellow', 'icon' => 'heart-pulse'],
                            'consulting' => ['label' => 'Consulting', 'color' => 'green', 'icon' => 'stethoscope'],
                            'lab' => ['label' => 'Lab', 'color' => 'sky', 'icon' => 'test-tube'],
                            'pharmacy' => ['label' => 'Pharmacy', 'color' => 'orange', 'icon' => 'pill'],
                            'completed' => ['label' => 'Completed', 'color' => 'purple', 'icon' => 'check-circle'],
                            'closed' => ['label' => 'Closed', 'color' => 'zinc', 'icon' => 'lock'],
                        ];
                        foreach ($statuses as $key => $count):
                            $cfg = $status_config[$key];
                        ?>
                        <div class="text-center p-3 rounded-md bg-<?= $cfg['color'] ?>-50 dark:bg-<?= $cfg['color'] ?>-500/10">
                            <i data-lucide="<?= $cfg['icon'] ?>" class="size-5 text-<?= $cfg['color'] ?>-500 mx-auto mb-2"></i>
                            <p class="text-lg font-bold text-<?= $cfg['color'] ?>-500"><?= $count ?></p>
                            <p class="text-[10px] text-slate-500 dark:text-zink-300"><?= $cfg['label'] ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Department Table -->
            <div class="card mb-5">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Department-wise Report</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-zink-600">
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Department</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Visits</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Revenue</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Avg/Visit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zink-700">
                                <?php while ($row = $dept_breakdown->fetch_assoc()):
                                    $avg = $row['visits'] > 0 ? $row['revenue'] / $row['visits'] : 0;
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                                    <td class="px-3 py-2.5 text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($row['name']) ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-zink-200 text-right"><?= $row['visits'] ?></td>
                                    <td class="px-3 py-2.5 text-sm text-green-600 dark:text-green-400 text-right font-mono"><?= number_format($row['revenue'], 0) ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-300 text-right font-mono"><?= number_format($avg, 0) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Doctor Table -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Doctor-wise Report</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-zink-600">
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Doctor</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Department</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Visits</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Revenue</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Avg/Visit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zink-700">
                                <?php while ($row = $doc_breakdown->fetch_assoc()):
                                    $avg = $row['visits'] > 0 ? $row['revenue'] / $row['visits'] : 0;
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zink-700/50">
                                    <td class="px-3 py-2.5 text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($row['name']) ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-200"><?= e($row['department']) ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-zink-200 text-right"><?= $row['visits'] ?></td>
                                    <td class="px-3 py-2.5 text-sm text-green-600 dark:text-green-400 text-right font-mono"><?= number_format($row['revenue'], 0) ?></td>
                                    <td class="px-3 py-2.5 text