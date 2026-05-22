<?php
require_once '../config/config.php';

// Stats queries
 $today_registered = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE()")->fetch_assoc()['cnt'];
 $today_triage = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'triage'")->fetch_assoc()['cnt'];
 $today_consulting = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status = 'consulting'")->fetch_assoc()['cnt'];
 $today_completed = $conn->query("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE() AND status IN ('completed','closed')")->fetch_assoc()['cnt'];

// Departments with today's count
 $departments = $conn->query("
    SELECT d.id, d.name, 
           COALESCE(v.cnt, 0) as patient_count
    FROM departments d
    LEFT JOIN (
        SELECT department_id, COUNT(*) as cnt 
        FROM visits 
        WHERE visit_date = CURDATE() 
        GROUP BY department_id
    ) v ON d.id = v.department_id
    WHERE d.status = 1
    ORDER BY v.cnt DESC
");

// Today's visits list
 $today_visits = $conn->query("
    SELECT v.visit_id, v.token_number, v.status, v.created_at,
           p.name as patient_name, p.phone, p.age, p.gender,
           d.name as department_name,
           doc.name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE v.visit_date = CURDATE()
    ORDER BY v.token_number ASC
");

// Flash message
 $flash = getFlash();

// Status badge helper
function statusBadge($status) {
    $map = [
        'registered' => ['bg-slate-100 border-slate-200 text-slate-500 dark:bg-slate-500/20 dark:border-slate-500/20 dark:text-zink-200', 'Registered'],
        'triage'     => ['bg-yellow-100 border-yellow-200 text-yellow-600 dark:bg-yellow-500/20 dark:border-yellow-500/20', 'Triage'],
        'consulting' => ['bg-sky-100 border-sky-200 text-sky-600 dark:bg-sky-500/20 dark:border-sky-500/20', 'Consulting'],
        'lab'        => ['bg-purple-100 border-purple-200 text-purple-600 dark:bg-purple-500/20 dark:border-purple-500/20', 'Lab'],
        'pharmacy'   => ['bg-orange-100 border-orange-200 text-orange-600 dark:bg-orange-500/20 dark:border-orange-500/20', 'Pharmacy'],
        'completed'  => ['bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20', 'Completed'],
        'closed'     => ['bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20', 'Closed'],
    ];
    if (isset($map[$status])) {
        list($classes, $label) = $map[$status];
        return "<span class='px-2.5 py-0.5 text-xs inline-block font-medium rounded border $classes'>$label</span>";
    }
    return e($status);
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>OPD Reception | MediFlow</title>
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
                    <h5 class="text-16">OPD Reception</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Reception</li>
                </ul>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i>
                    <span><?= e($flash['msg']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                <i data-lucide="user-plus" class="size-6 text-sky-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Today Registered</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $today_registered ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="activity" class="size-6 text-yellow-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Waiting Triage</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $today_triage ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                <i data-lucide="stethoscope" class="size-6 text-purple-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">In Consultation</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $today_consulting ?></h4>
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
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Completed</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $today_completed ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action + Department Cards -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 mb-5">
                <div class="xl:col-span-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-15 mb-4">Quick Actions</h6>
                            <div class="flex flex-col gap-3">
                                <a href="register.php" class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-custom-50 hover:border-custom-200 dark:hover:bg-custom-500/10 dark:hover:border-custom-500/20 transition-all duration-200">
                                    <div class="flex items-center justify-center size-10 rounded-md bg-custom-500 shrink-0">
                                        <i data-lucide="user-plus" class="size-5 text-white"></i>
                                    </div>
                                    <div class="grow">
                                        <h6 class="text-sm font-medium">Register New Patient</h6>
                                        <p class="text-xs text-slate-500 dark:text-zink-200">Create OPD ticket</p>
                                    </div>
                                    <i data-lucide="chevron-right" class="size-4 text-slate-400 dark:text-zink-200"></i>
                                </a>
                                <a href="../records/history.php" class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-custom-50 hover:border-custom-200 dark:hover:bg-custom-500/10 dark:hover:border-custom-500/20 transition-all duration-200">
                                    <div class="flex items-center justify-center size-10 rounded-md bg-green-500 shrink-0">
                                        <i data-lucide="search" class="size-5 text-white"></i>
                                    </div>
                                    <div class="grow">
                                        <h6 class="text-sm font-medium">Search Patient</h6>
                                        <p class="text-xs text-slate-500 dark:text-zink-200">Find by ID or name</p>
                                    </div>
                                    <i data-lucide="chevron-right" class="size-4 text-slate-400 dark:text-zink-200"></i>
                                </a>
                                <a href="../records/index.php" class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-custom-50 hover:border-custom-200 dark:hover:bg-custom-500/10 dark:hover:border-custom-500/20 transition-all duration-200">
                                    <div class="flex items-center justify-center size-10 rounded-md bg-purple-500 shrink-0">
                                        <i data-lucide="file-text" class="size-5 text-white"></i>
                                    </div>
                                    <div class="grow">
                                        <h6 class="text-sm font-medium">View Records</h6>
                                        <p class="text-xs text-slate-500 dark:text-zink-200">Patient history</p>
                                    </div>
                                    <i data-lucide="chevron-right" class="size-4 text-slate-400 dark:text-zink-200"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="xl:col-span-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-15 mb-4">Department-wise Today</h6>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 text-center">
                                    <h5 class="text-xl font-bold text-custom-500"><?= (int)$dept['patient_count'] ?></h5>
                                    <p class="text-xs text-slate-500 dark:text-zink-200 mt-1 truncate"><?= e($dept['name']) ?></p>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Patient List -->
            <div class="card">
                <div class="card-body">
                    <div class="grid items-center grid-cols-1 gap-3 mb-5 xl:grid-cols-12">
                        <div class="xl:col-span-4">
                            <h6 class="text-15">Today's Patients <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $today_registered ?>)</span></h6>
                        </div>
                        <div class="xl:col-span-4">
                            <div class="relative">
                                <input type="text" id="searchInput" class="ltr:pl-8 rtl:pr-8 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Search by name, ID, phone..." autocomplete="off">
                                <i data-lucide="search" class="inline-block size-4 absolute ltr:left-2.5 rtl:right-2.5 top-2.5 text-slate-500 dark:text-zink-200 fill-slate-100 dark:fill-zink-600"></i>
                            </div>
                        </div>
                        <div class="xl:col-span-4 xl:col-start-9">
                            <a href="register.php" class="inline-flex items-center justify-center gap-2 w-full text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600">
                                <i data-lucide="plus" class="size-4"></i> New Patient
                            </a>
                        </div>
                    </div>

                    <div class="-mx-5 overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="ltr:text-left rtl:text-right bg-slate-100 text-slate-500 dark:text-zink-200 dark:bg-zink-600">
                                <tr>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Token</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Visit ID</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Patient</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Department</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Doctor</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Time</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Status</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Action</th>
                                </tr>
                            </thead>
                            <tbody id="patientTableBody">
                                <?php if ($today_visits->num_rows > 0): ?>
                                    <?php while ($v = $today_visits->fetch_assoc()): ?>
                                    <tr class="search-row">
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                            <span class="inline-flex items-center justify-center size-8 rounded bg-custom-500 text-white text-xs font-bold"><?= (int)$v['token_number'] ?></span>
                                        </td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500"><a href="../records/view_visit.php?id=<?= e($v['visit_id']) ?>" class="text-custom-500 hover:underline"><?= e($v['visit_id']) ?></a></td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                            <div>
                                                <h6 class="text-sm font-medium"><?= e($v['patient_name']) ?></h6>
                                                <p class="text-xs text-slate-500 dark:text-zink-200"><?= e($v['phone']) ?> &middot; <?= e($v['age']) ?>y &middot; <?= e($v['gender']) ?></p>
                                            </div>
                                        </td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($v['department_name']) ?></td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($v['doctor_name'] ?? 'Not Assigned') ?></td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm text-slate-500 dark:text-zink-200"><?= date('h:i A', strtotime($v['created_at'])) ?></td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500"><?= statusBadge($v['status']) ?></td>
                                        <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                            <div class="flex gap-1">
                                                <button onclick="openVisitModal('<?= e($v['visit_id']) ?>')" class="flex items-center justify-center transition-all duration-200 ease-linear rounded-md size-8 bg-slate-100 dark:bg-zink-600 dark:text-zink-200 text-slate-500 hover:text-custom-500 dark:hover:text-custom-500 hover:bg-custom-100 dark:hover:bg-custom-500/20" title="Quick View">
                                                    <i data-lucide="eye" class="size-4"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-3.5 py-8 text-center border-y border-slate-200 dark:border-zink-500 text-slate-400 dark:text-zink-200">
                                            <i data-lucide="inbox" class="size-10 mx-auto mb-2 text-slate-300 dark:text-zink-400"></i>
                                            <p>No patients registered today</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
<!-- View Visit Modal -->
<div id="visitModal" class="fixed inset-0 z-[1000] hidden">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" onclick="closeVisitModal()"></div>
    
    <!-- Modal -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl bg-white dark:bg-zink-700 rounded-md shadow-xl">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-zink-500">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-9 rounded-md bg-custom-100 dark:bg-custom-500/20">
                        <i data-lucide="clipboard-list" class="size-5 text-custom-500"></i>
                    </div>
                    <div>
                        <h5 class="text-15 font-semibold" id="modal_visit_id">Visit Details</h5>
                        <p class="text-xs text-slate-400 dark:text-zink-300" id="modal_visit_date"></p>
                    </div>
                </div>
                <button onclick="closeVisitModal()" class="flex items-center justify-center size-8 rounded-md text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
                    <i data-lucide="x" class="size-4"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-5 py-4 space-y-4">

                <!-- Loading -->
                <div id="modal_loading" class="flex items-center justify-center py-10">
                    <div class="flex items-center gap-3 text-slate-400 dark:text-zink-300">
                        <i data-lucide="loader" class="size-5 animate-spin"></i>
                        <span class="text-sm">Loading visit details...</span>
                    </div>
                </div>

                <!-- Content (hidden until loaded) -->
                <div id="modal_content" class="hidden">

                    <!-- Patient + Token row -->
                    <div class="flex items-center gap-4 p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                        <div class="flex items-center justify-center size-12 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                            <i data-lucide="user" class="size-6 text-custom-500"></i>
                        </div>
                        <div class="grow">
                            <h6 class="font-semibold text-sm" id="modal_patient_name"></h6>
                            <p class="text-xs text-slate-500 dark:text-zink-200" id="modal_patient_meta"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center justify-center size-12 rounded-md bg-custom-500 text-white text-lg font-bold" id="modal_token"></span>
                            <p class="text-xs text-slate-400 dark:text-zink-300 mt-1">Token</p>
                        </div>
                    </div>

                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4">
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Department</p>
                            <p class="text-sm font-medium" id="modal_department">—</p>
                        </div>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Doctor</p>
                            <p class="text-sm font-medium" id="modal_doctor">—</p>
                        </div>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Status</p>
                            <p class="text-sm font-medium" id="modal_status">—</p>
                        </div>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Phone</p>
                            <p class="text-sm font-medium" id="modal_phone">—</p>
                        </div>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Registered At</p>
                            <p class="text-sm font-medium" id="modal_time">—</p>
                        </div>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Blood Group</p>
                            <p class="text-sm font-medium" id="modal_blood">—</p>
                        </div>
                    </div>

                </div>

                <!-- Error -->
                <div id="modal_error" class="hidden text-center py-8 text-red-400">
                    <i data-lucide="alert-circle" class="size-8 mx-auto mb-2"></i>
                    <p class="text-sm">Failed to load visit details</p>
                </div>

            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-5 py-4 border-t border-slate-200 dark:border-zink-500">
                <button onclick="closeVisitModal()" class="px-4 py-2 text-sm text-slate-500 btn bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600">
                    Close
                </button>
                <a id="modal_full_link" href="#" class="px-4 py-2 text-sm text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600">
                    <i data-lucide="external-link" class="inline-block size-4 mr-1"></i>
                    Full Record
                </a>
            </div>

        </div>
    </div>
</div>
    <?php include 'footer.php'; ?>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Re-init Lucide icons for dynamic content
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Search filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('.search-row');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>
<script>
function openVisitModal(visitId) {
    const modal = document.getElementById('visitModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Reset state
    document.getElementById('modal_loading').classList.remove('hidden');
    document.getElementById('modal_content').classList.add('hidden');
    document.getElementById('modal_error').classList.add('hidden');
    document.getElementById('modal_visit_id').textContent = visitId;
    document.getElementById('modal_full_link').href = 'view_visit.php?id=' + visitId;

    // Re-init icons
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Fetch visit data
    fetch('api_visit.php?id=' + encodeURIComponent(visitId))
        .then(r => r.json())
        .then(data => {
            if (!data.found) throw new Error('Not found');

            const v = data.visit;
            document.getElementById('modal_visit_date').textContent   = v.visit_date;
            document.getElementById('modal_patient_name').textContent = v.patient_name;
            document.getElementById('modal_patient_meta').textContent = v.phone + ' · ' + v.age + 'y · ' + v.gender;
            document.getElementById('modal_token').textContent        = '#' + String(v.token_number).padStart(3, '0');
            document.getElementById('modal_department').textContent   = v.department_name;
            document.getElementById('modal_doctor').textContent       = v.doctor_name || 'Not Assigned';
            document.getElementById('modal_status').textContent       = v.status.charAt(0).toUpperCase() + v.status.slice(1);
            document.getElementById('modal_phone').textContent        = v.phone;
            document.getElementById('modal_time').textContent         = v.created_at;
            document.getElementById('modal_blood').textContent        = v.blood_group || 'N/A';

            document.getElementById('modal_loading').classList.add('hidden');
            document.getElementById('modal_content').classList.remove('hidden');

            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(() => {
            document.getElementById('modal_loading').classList.add('hidden');
            document.getElementById('modal_error').classList.remove('hidden');
        });
}

function closeVisitModal() {
    document.getElementById('visitModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeVisitModal();
});
</script>
</body>
</html>