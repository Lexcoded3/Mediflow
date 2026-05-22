<?php
require_once '../config/config.php';

// Date filter
$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to = $_GET['to'] ?? date('Y-m-d');

if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Completed scan results - Swapped scan_orders for scans
$results = $conn->prepare("
    SELECT s.id as order_id, s.scan_type, 'N/A' as body_part, s.status, s.uploaded_at as ordered_at,
           v.visit_id, v.token_number, v.visit_date,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           d.name as department_name, doc.name as doctor_name,
           sr.findings, sr.impression, '' as remarks, sr.created_at as entered_at
    FROM scans s
    JOIN visits v ON s.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN scan_results sr ON sr.visit_id = v.id
    WHERE s.status = 'completed' 
      AND DATE(s.uploaded_at) BETWEEN ? AND ?
    ORDER BY s.uploaded_at DESC
");
$results->bind_param("ss", $date_from, $date_to);
$results->execute();
$results_data = $results->get_result(); // Needed to fetch data later in the HTML

// Stats - Swapped scan_orders for scans
$total_scans = $conn->query("
    SELECT COUNT(*) as cnt FROM scans 
    WHERE status = 'completed' AND DATE(uploaded_at) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc()['cnt'];

$unique_patients = $conn->query("
    SELECT COUNT(DISTINCT p.id) as cnt 
    FROM scans s
    JOIN visits v ON s.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    WHERE s.status = 'completed' AND DATE(s.uploaded_at) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc()['cnt'];

// Breakdowns
$type_breakdown = $conn->query("
    SELECT s.scan_type, COUNT(*) as cnt 
    FROM scans s
    WHERE s.status = 'completed' AND DATE(s.uploaded_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY s.scan_type ORDER BY cnt DESC
");

$dept_breakdown = $conn->query("
    SELECT d.name, COUNT(*) as cnt 
    FROM scans s
    JOIN visits v ON s.visit_id = v.id
    JOIN departments d ON v.department_id = d.id
    WHERE s.status = 'completed' AND DATE(s.uploaded_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY d.name ORDER BY cnt DESC
");

$types = []; $max_type = 1;
while ($row = $type_breakdown->fetch_assoc()) {
    $types[] = $row;
    if ($row['cnt'] > $max_type) $max_type = $row['cnt'];
}

$depts = []; $max_dept = 1;
while ($row = $dept_breakdown->fetch_assoc()) {
    $depts[] = $row;
    if ($row['cnt'] > $max_dept) $max_dept = $row['cnt'];
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Scan Reports | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
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
                    <h5 class="text-16">Scan Reports</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Scan</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Reports</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

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
                        <a href="index.php" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                            <i data-lucide="arrow-left" class="size-4"></i> Back to Queue
                        </a>
                    </div>
                </div>
            </form>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                <i data-lucide="scan" class="size-6 text-purple-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Total Scans</p>
                                <h4 class="mt-1 text-2xl font-bold text-purple-500"><?= $total_scans ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                <i data-lucide="users" class="size-6 text-sky-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Unique Patients</p>
                                <h4 class="mt-1 text-2xl font-bold text-sky-500"><?= $unique_patients ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="layers" class="size-6 text-green-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Scan Types</p>
                                <h4 class="mt-1 text-2xl font-bold text-green-500"><?= count($types) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
                <!-- Scan Type Chart -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">By Scan Type</h6>
                        <?php if (empty($types)): ?>
                        <div class="py-8 text-center">
                            <i data-lucide="bar-chart-3" class="size-10 mx-auto mb-2 text-slate-300 dark:text-zink-400"></i>
                            <p class="text-sm text-slate-400 dark:text-zink-300">No data</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($types as $t): 
                                $width = ($t['cnt'] / $max_type) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-600 dark:text-zink-200"><?= e($t['scan_type']) ?></span>
                                    <span class="font-medium text-slate-500 dark:text-zink-300"><?= $t['cnt'] ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-zink-600 rounded-full h-2">
                                    <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: <?= $width ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Department Chart -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">By Department</h6>
                        <?php if (empty($depts)): ?>
                        <div class="py-8 text-center">
                            <i data-lucide="building" class="size-10 mx-auto mb-2 text-slate-300 dark:text-zink-400"></i>
                            <p class="text-sm text-slate-400 dark:text-zink-300">No data</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($depts as $d): 
                                $width = ($d['cnt'] / $max_dept) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-600 dark:text-zink-200"><?= e($d['name']) ?></span>
                                    <span class="font-medium text-slate-500 dark:text-zink-300"><?= $d['cnt'] ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-zink-600 rounded-full h-2">
                                    <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" style="width: <?= $width ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Detailed Results</h6>

                    <?php if ($results->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-zink-600">
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Token</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Patient</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Scan</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Impression</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Doctor</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Time</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-zink-600">
                                <?php while ($row = $results->get_result()->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zink-600/50 transition-colors">
                                    <td class="px-3 py-2.5 font-mono text-sm text-custom-500"><?= str_pad($row['token_number'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td class="px-3 py-2.5">
                                        <p class="text-sm font-medium"><?= e($row['patient_name']) ?></p>
                                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= $row['age'] ?>y, <?= $row['gender'] ?></p>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 rounded">
                                            <?= e($row['scan_type']) ?>
                                        </span>
                                        <?php if ($row['body_part']): ?>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mt-0.5"><?= e($row['body_part']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-200 max-w-[200px] truncate"><?= e($row['impression']) ?: '—' ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-200"><?= e($row['doctor_name']) ?></td>
                                    <td class="px-3 py-2.5 text-xs text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($row['entered_at'])) ?></td>
                                    <td class="px-3 py-2.5">
                                        <a href="enter_results.php?order_id=<?= $row['order_id'] ?>" class="inline-flex items-center gap-1 text-xs text-purple-500 hover:text-purple-600 font-medium">
                                            <i data-lucide="eye" class="size-3"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="file-text" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No completed scans in this period</p>
                    </div>
                    <?php endif; ?>
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