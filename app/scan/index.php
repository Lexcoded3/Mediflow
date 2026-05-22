<?php
require_once '../config/config.php';

// Pending scan orders - Using the 'scans' table instead of 'scan_orders'
$pending = $conn->query("
    SELECT s.id as order_id, s.scan_type, 'N/A' as body_part, s.status, s.uploaded_at as ordered_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           d.name as department_name, doc.name as doctor_name,
           t.priority
    FROM scans s
    JOIN visits v ON s.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE s.status IN ('ordered', 'scheduled')
    ORDER BY 
        CASE COALESCE(t.priority, 'green')
            WHEN 'red' THEN 1
            WHEN 'orange' THEN 2
            WHEN 'yellow' THEN 3
            ELSE 4
        END ASC,
        s.uploaded_at ASC
");

// Scheduled/Ready scans
$scheduled = $conn->query("
    SELECT s.id as order_id, s.scan_type, 'N/A' as body_part, s.status, s.uploaded_at as ordered_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           doc.name as doctor_name, t.priority
    FROM scans s
    JOIN visits v ON s.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE s.status = 'scheduled' AND DATE(s.uploaded_at) = CURDATE()
    ORDER BY s.uploaded_at ASC
");

// Completed scans count
$completed_res = $conn->query("
    SELECT COUNT(*) as cnt FROM scans 
    WHERE status = 'completed' AND DATE(uploaded_at) = CURDATE()
");
$completed = $completed_res ? $completed_res->fetch_assoc()['cnt'] : 0;

// Total today count
$total_today_res = $conn->query("
    SELECT COUNT(*) as cnt FROM scans 
    WHERE DATE(uploaded_at) = CURDATE()
");
$total_today = $total_today_res ? $total_today_res->fetch_assoc()['cnt'] : 0;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Scan Center | MediFlow OPD</title>
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
                    <h5 class="text-16">Scan Center</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Scan</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="clock" class="size-6 text-yellow-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Pending Orders</p>
                                <h4 class="mt-1 text-2xl font-bold text-yellow-500"><?= $pending->num_rows ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                <i data-lucide="scan" class="size-6 text-purple-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Total Today</p>
                                <h4 class="mt-1 text-2xl font-bold text-purple-500"><?= $total_today ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="check-circle" class="size-6 text-green-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Completed Today</p>
                                <h4 class="mt-1 text-2xl font-bold text-green-500"><?= $completed ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Orders -->
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h6 class="text-15">Pending Orders <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $pending->num_rows ?>)</span></h6>
                        <div class="flex items-center gap-2">
                            <a href="reports.php" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                                <i data-lucide="bar-chart-3" class="size-3"></i> Reports
                            </a>
                            <div class="relative">
                                <input type="text" id="searchScan" class="ltr:pl-8 rtl:pr-8 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Search..." autocomplete="off">
                                <i data-lucide="search" class="inline-block size-4 absolute ltr:left-2.5 rtl:right-2.5 top-2.5 text-slate-500 dark:text-zink-200 fill-slate-100 dark:fill-zink-600"></i>
                            </div>
                        </div>
                    </div>

                    <?php if ($pending->num_rows > 0): ?>
                    <div class="flex flex-col gap-2" id="scanList">
                        <?php while ($p = $pending->fetch_assoc()): 
                            $scan_icons = [
                                'X-Ray' => 'scan-line',
                                'CT Scan' => 'rotate-cw',
                                'MRI' => 'magnet',
                                'Ultrasound' => 'audio-waveform',
                                'ECG' => 'heart-pulse',
                                'EEG' => 'brain',
                                'Echo' => 'heart',
                                'DEXA' => 'bone',
                                'Mammography' => 'stethoscope',
                                'Fluoroscopy' => 'zap',
                            ];
                            $scan_icon = $scan_icons[$p['scan_type']] ?? 'image';
                        ?>
                        <div class="scan-search-item flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                            <div class="flex items-center justify-center size-10 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                <i data-lucide="<?= $scan_icon ?>" class="size-5 text-purple-500"></i>
                            </div>
                            <div class="grow min-w-0">
                                <p class="text-sm font-medium truncate"><?= e($p['scan_type']) ?> <?php if ($p['body_part']): ?>— <?= e($p['body_part']) ?><?php endif; ?></p>
                                <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($p['patient_name']) ?> · Token <?= str_pad($p['token_number'], 3, '0', STR_PAD_LEFT) ?> · <?= e($p['doctor_name']) ?></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <?= priorityDot($p['priority']) ?>
                                <span class="text-[10px] text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($p['ordered_at'])) ?></span>
                                <a href="enter_results.php?order_id=<?= $p['order_id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-white bg-purple-500 hover:bg-purple-600 rounded-md transition-colors">
                                    <i data-lucide="edit-3" class="size-3"></i> Enter
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="scan" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No pending scan orders</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Today Section -->
            <?php if ($completed > 0): ?>
            <div class="mt-5 px-4 py-3 rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="size-5 text-green-500"></i>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400"><?= $completed ?> scan(s) completed today</span>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Scan search filter
        const searchInput = document.getElementById('searchScan');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.scan-search-item').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

</body>
</html>