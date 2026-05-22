<?php
require_once '../config/config.php';

 $visit_id = $_GET['visit_id'] ?? '';
 $token = $_GET['token'] ?? '';
 $is_new = ($_GET['new'] ?? '1') === '1';

if (empty($visit_id)) {
    header("Location: index.php");
    exit;
}

// Get visit + patient details
 $stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.patient_id,
           d.name as department_name, doc.name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE v.visit_id = ?
");
 $stmt->bind_param("s", $visit_id);
 $stmt->execute();
 $visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    setFlash('error', 'Visit not found');
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Registration Successful | MediFlow OPD</title>
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

            <!-- Success Card -->
            <div class="flex items-center justify-center" style="min-height: calc(100vh - 200px);">
                <div class="w-full max-w-lg">
                    <div class="card text-center">
                        <div class="card-body p-8">
                            <!-- Success Icon -->
                            <div class="flex items-center justify-center mx-auto mb-4 size-20 rounded-full bg-green-100 dark:bg-green-500/20">
                                <i data-lucide="check-circle" class="size-10 text-green-500"></i>
                            </div>

                            <h4 class="mb-2 text-xl font-semibold">Registration Successful!</h4>
                            <p class="mb-6 text-slate-500 dark:text-zink-200">
                                <?php if ($is_new): ?>
                                    New patient has been registered and OPD ticket created.
                                <?php else: ?>
                                    Returning patient visit has been recorded.
                                <?php endif; ?>
                            </p>

                            <!-- Visit Details -->
                            <div class="p-4 mb-6 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500 text-left">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Visit ID</p>
                                        <p class="font-semibold text-custom-500"><?= e($visit['visit_id']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Token Number</p>
                                        <p class="font-semibold text-2xl text-custom-500">
                                        <?php
require_once '../../config.php';

 $visit_id = $_GET['visit_id'] ?? '';
 $token = $_GET['token'] ?? '';
 $is_new = ($_GET['new'] ?? '1') === '1';

if (empty($visit_id)) {
    header("Location: index.php");
    exit;
}

// Get visit + patient details
 $stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.patient_id,
           d.name as department_name, doc.name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE v.visit_id = ?
");
 $stmt->bind_param("s", $visit_id);
 $stmt->execute();
 $visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    setFlash('error', 'Visit not found');
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Registration Successful | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">
    <script src="../../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../../assets/css/starcode2.css">
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include '../../sidenav.php'; ?>
<?php include '../../topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Success Card -->
            <div class="flex items-center justify-center" style="min-height: calc(100vh - 200px);">
                <div class="w-full max-w-lg">
                    <div class="card text-center">
                        <div class="card-body p-8">
                            <!-- Success Icon -->
                            <div class="flex items-center justify-center mx-auto mb-4 size-20 rounded-full bg-green-100 dark:bg-green-500/20">
                                <i data-lucide="check-circle" class="size-10 text-green-500"></i>
                            </div>

                            <h4 class="mb-2 text-xl font-semibold">Registration Successful!</h4>
                            <p class="mb-6 text-slate-500 dark:text-zink-200">
                                <?php if ($is_new): ?>
                                    New patient has been registered and OPD ticket created.
                                <?php else: ?>
                                    Returning patient visit has been recorded.
                                <?php endif; ?>
                            </p>

                            <!-- Visit Details -->
                            <div class="p-4 mb-6 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500 text-left">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Visit ID</p>
                                        <p class="font-semibold text-custom-500"><?= e($visit['visit_id']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Token Number</p>
                                        <p class="font-semibold text-2xl text-custom-500">#<?= str_pad($visit['token_number'], 3, 0, STR_PAD_LEFT) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Patient</p>
                                        <p class="font-medium"><?= e($visit['patient_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Patient ID</p>
                                        <p class="font-medium"><?= e($visit['patient_id']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Department</p>
                                        <p class="font-medium"><?= e($visit['department_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Doctor</p>
                                        <p class="font-medium"><?= e($visit['doctor_name'] ?? 'Not Assigned') ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Step -->
                            <div class="p-3 mb-6 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="arrow-right" class="size-4 text-sky-500 shrink-0"></i>
                                    <p class="text-sm text-sky-600 dark:text-sky-400">
                                        <strong>Next:</strong> Send patient to <strong>Triage</strong> for vitals check
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col sm:flex-row gap-3">
                                <a href="register.php" class="flex-1 px-4 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 text-center">
                                    <i data-lucide="plus" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Register Another
                                </a>
                                <a href="index.php" class="flex-1 px-4 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 text-center">
                                    <i data-lucide="list" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include '../../footer.php'; ?>

</div>
</div>

</body>
</html></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Patient</p>
                                        <p class="font-medium"><?= e($visit['patient_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Patient ID</p>
                                        <p class="font-medium"><?= e($visit['patient_id']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Department</p>
                                        <p class="font-medium"><?= e($visit['department_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Doctor</p>
                                        <p class="font-medium"><?= e($visit['doctor_name'] ?? 'Not Assigned') ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Step -->
                            <div class="p-3 mb-6 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="arrow-right" class="size-4 text-sky-500 shrink-0"></i>
                                    <p class="text-sm text-sky-600 dark:text-sky-400">
                                        <strong>Next:</strong> Send patient to <strong>Triage</strong> for vitals check
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col sm:flex-row gap-3">
                                <a href="register.php" class="flex-1 px-4 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 text-center">
                                    <i data-lucide="plus" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Register Another
                                </a>
                                <a href="index.php" class="flex-1 px-4 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 text-center">
                                    <i data-lucide="list" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</div>
</div>

</body>
</html>