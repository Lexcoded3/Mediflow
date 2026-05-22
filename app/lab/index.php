<?php
require_once '../config/config.php';

// All pending lab orders
 $pending = $conn->query("
    SELECT lo.id as order_id, lo.test_name, lo.status, lo.ordered_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           d.name as department_name, doc.name as doctor_name,
           t.priority
    FROM lab_orders lo
    JOIN visits v ON lo.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE lo.status IN ('ordered', 'collected')
    ORDER BY 
        CASE COALESCE(t.priority, 'green')
            WHEN 'red' THEN 1
            WHEN 'orange' THEN 2
            WHEN 'yellow' THEN 3
            ELSE 4
        END ASC,
        lo.ordered_at ASC
");

// Collected samples (ready to process)
 $collected = $conn->query("
    SELECT lo.id as order_id, lo.test_name, lo.ordered_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.patient_id,
           doc.name as doctor_name
    FROM lab_orders lo
    JOIN visits v ON lo.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE lo.status = 'collected'
    ORDER BY lo.ordered_at ASC
");

// Completed today
 $completed = $conn->query("
    SELECT COUNT(*) as cnt FROM lab_orders lo
    JOIN visits v ON lo.visit_id = v.id
    WHERE lo.status = 'completed' AND DATE(lo.ordered_at) = CURDATE()
")->fetch_assoc()['cnt'];

// Pending (ordered + collected)
 $pending_total = $pending->num_rows + $collected->num_rows;

 $flash = getFlash();

// function orderStatusBadge($status) {
//     $map = [
//         'ordered'   => ['bg-slate-100 border-slate-200 text-slate-500 dark:bg-slate-500/20 dark:border-slate-500/20 dark:text-zink-200', 'Ordered'],
//         'collected' => ['bg-sky-100 border-sky-200 text-sky-600 dark:bg-sky-500/20 dark:border-sky-500/20', 'Collected'],
//         'processing'=> ['bg-orange-100 border-orange-200 text-orange-600 dark:bg-orange-500/20 dark:border-orange-500/20', 'Processing'],
//         'completed' => ['bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20', 'Completed'],
//     ];
//     if (isset($map[$status])) {
//         list($classes, $label) = $map[$status];
//         return "<span class='px-2.5 py-0.5 text-xs inline-block font-medium rounded border $classes'>$label</span>";
//     }
//     return '';
// }

// function priorityDot($priority) {
//     $colors = ['red' => 'bg-red-500', 'orange' => 'bg-orange-500', 'yellow' => 'bg-yellow-500', 'green' => 'bg-green-500'];
//     $color = $colors[$priority] ?? 'bg-green-500';
//     return "<span class='inline-block size-2.5 rounded-full $color'></span>";
// }
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Lab | MediFlow OPD</title>
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
                    <h5 class="text-16">Laboratory</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Lab</li>
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
                                <h4 class="mt-1 text-2xl font-bold text-yellow-500"><?= $pending_total ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                <i data-lucide="test-tube" class="size-6 text-sky-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Collected Samples</p>
                                <h4 class="mt-1 text-2xl font-bold text-sky-500"><?= $collected->num_rows ?></h4>
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

            <!-- Collected Samples - Priority Action -->
            <?php if ($collected->num_rows > 0): ?>
            <div class="card mb-5">
                <div class="card-body">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center justify-center size-8 rounded-full bg-sky-100 dark:bg-sky-500/20 shrink-0">
                            <i data-lucide="test-tube" class="size-4 text-sky-500"></i>
                        </div>
                        <div>
                            <h6 class="text-15 mb-0">Ready to Process <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $collected->num_rows ?> samples collected)</span></h6>
                            <p class="text-xs text-slate-500 dark:text-zink-200">These samples have been collected and are ready for testing</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <?php while ($c = $collected->fetch_assoc()): ?>
                        <div class="p-3 rounded-md border border-sky-200 dark:border-sky-500/30 bg-sky-50/50 dark:bg-sky-500/5">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-semibold"><?= e($c['patient_name']) ?></span>
                                <?= priorityDot($c['priority']) ?>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-zink-200 mb-2"><?= e($c['patient_id']) ?> &middot; Token <?= str_pad($c['token_number'], 3, '0', STR_PAD_LEFT) ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-3"><?= e($c['doctor_name']) ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-sky-600 dark:text-sky-400"><?= e($c['test_name']) ?></span>
                                <a href="enter_results.php?order_id=<?= $c['order_id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-white bg-sky-500 hover:bg-sky-600 rounded-md transition-colors">
                                    <i data-lucide="edit-3" class="size-3"></i> Enter Results
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Orders -->
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h6 class="text-15">Pending Orders <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $pending->num_rows ?>)</span></h6>
                        <div class="relative">
                            <input type="text" id="searchLab" class="ltr:pl-8 rtl:pr-8 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Search by patient or test..." autocomplete="off">
                            <i data-lucide="search" class="inline-block size-4 absolute ltr:left-2.5 rtl:right-2.5 top-2.5 text-slate-500 dark:text-zink-200 fill-slate-100 dark:fill-zink-600"></i>
                        </div>
                    </div>

                    <?php if ($pending->num_rows > 0): ?>
                    <div class="flex flex-col gap-2" id="labList">
                        <?php while ($p = $pending->fetch_assoc()): ?>
                        <div class="lab-search-item flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                            <?= priorityDot($p['priority']) ?>
                            <div class="grow min-w-0">
                                <p class="text-sm font-medium truncate"><?= e($p['test_name']) ?></p>
                                <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($p['patient_name']) ?> &middot; Token <?= str_pad($p['token_number'], 3, '0', STR_PAD_LEFT) ?> &middot; <?= e($p['doctor_name']) ?></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <?= orderStatusBadge($p['status']) ?>
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
                        <i data-lucide="flask-conical" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No pending lab orders</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Today Section -->
            <?php if ($completed > 0): ?>
            <div class="mt-5 mb-5 px-4 py-3 rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="size-5 text-green-500"></i>
                    <span class="text-sm font-medium text-green-600 dark:text-green-400"><?= $completed ?> lab result(s) entered today</span>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Lab search filter
        const searchInput = document.getElementById('searchLab');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.lab-search-item').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

</body>
</html>