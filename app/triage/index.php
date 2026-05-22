<?php
require_once '../config/config.php';

// Patients waiting for triage
 $waiting = $conn->query("
    SELECT v.visit_id, v.token_number, v.created_at,
           p.name as patient_name, p.phone, p.age, p.gender, p.patient_id,
           d.name as department_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    WHERE v.visit_date = CURDATE() AND v.status = 'registered'
    ORDER BY v.token_number ASC
");

// Already triaged today
 $triaged = $conn->query("
    SELECT v.visit_id, v.token_number, v.status,
           p.name as patient_name, p.age, p.gender,
           d.name as department_name,
           t.priority, t.bp_systolic, t.bp_diastolic, t.temperature, t.pulse, t.spo2,
           t.triaged_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    JOIN triage t ON t.visit_id = v.id
    WHERE v.visit_date = CURDATE() AND v.status IN ('triage','consulting','lab','pharmacy','completed','closed')
    ORDER BY t.triaged_at DESC
");

// Emergency count
 $emergency_count = $conn->query("
    SELECT COUNT(*) as cnt FROM triage 
    WHERE is_emergency = 1 
    AND DATE(triaged_at) = CURDATE()
")->fetch_assoc()['cnt'];

 $flash = getFlash();

function priorityBadge($priority) {
    $map = [
        'green'  => ['bg-green-100 border-green-300 text-green-700 dark:bg-green-500/20 dark:border-green-500/30 dark:text-green-400', 'Green - Normal'],
        'yellow' => ['bg-yellow-100 border-yellow-300 text-yellow-700 dark:bg-yellow-500/20 dark:border-yellow-500/30 dark:text-yellow-400', 'Yellow - Urgent'],
        'orange' => ['bg-orange-100 border-orange-300 text-orange-700 dark:bg-orange-500/20 dark:border-orange-500/30 dark:text-orange-400', 'Orange - Very Urgent'],
        'red'    => ['bg-red-100 border-red-300 text-red-700 dark:bg-red-500/20 dark:border-red-500/30 dark:text-red-400', 'Red - Emergency'],
    ];
    if (isset($map[$priority])) {
        list($classes, $label) = $map[$priority];
        return "<span class='px-2.5 py-0.5 text-xs inline-block font-medium rounded border $classes'>$label</span>";
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Triage | MediFlow OPD</title>
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
                    <h5 class="text-16">Triage</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Triage</li>
                </ul>
            </div>

            <!-- Flash -->
            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i>
                    <span><?= $flash['msg'] ?></span>
                </div>
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
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Waiting for Triage</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $waiting->num_rows ?></h4>
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
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Triaged Today</p>
                                <h4 class="mt-1 text-2xl font-bold"><?= $triaged->num_rows ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='emergency_queue.php'">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-red-100 dark:bg-red-500/20 shrink-0">
                                <i data-lucide="siren" class="size-6 text-red-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Emergencies Today</p>
                                <h4 class="mt-1 text-2xl font-bold <?= $emergency_count > 0 ? 'text-red-500' : '' ?>"><?= $emergency_count ?></h4>
                            </div>
                            <i data-lucide="chevron-right" class="size-5 text-slate-300 dark:text-zink-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Waiting Queue -->
            <div class="card mb-5">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Waiting Queue <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $waiting->num_rows ?> patients)</span></h6>

                    <?php if ($waiting->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <?php while ($w = $waiting->fetch_assoc()): ?>
                        <div class="p-4 rounded-md border border-slate-200 dark:border-zink-500 hover:border-custom-300 dark:hover:border-custom-500/30 hover:shadow-sm transition-all">
                            <div class="flex items-start gap-3">
                                <div class="flex items-center justify-center size-12 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                    <span class="text-lg font-bold text-custom-500"><?= str_pad($w['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                                </div>
                                <div class="grow min-w-0">
                                    <h6 class="font-medium truncate"><?= e($w['patient_name']) ?></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200"><?= e($w['patient_id']) ?> &middot; <?= e($w['age']) ?>y &middot; <?= e($w['gender']) ?></p>
                                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-1"><?= e($w['department_name']) ?> &middot; <?= date('h:i A', strtotime($w['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zink-500">
                                <a href="triage.php?visit_id=<?= urlencode($w['visit_id']) ?>" class="flex items-center justify-center gap-2 w-full px-3 py-2 text-white text-sm rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                                    <i data-lucide="stethoscope" class="size-4"></i> Start Triage
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="inbox" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No patients waiting for triage</p>
                        <a href="../reception/register.php" class="inline-block mt-3 px-4 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">Register New Patient</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Already Triaged -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Triaged Today <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $triaged->num_rows ?> patients)</span></h6>

                    <?php if ($triaged->num_rows > 0): ?>
                    <div class="-mx-5 overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="ltr:text-left rtl:text-right bg-slate-100 text-slate-500 dark:text-zink-200 dark:bg-zink-600">
                                <tr>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Token</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Patient</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Department</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">BP</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Temp</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Pulse</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">SpO2</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Priority</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($t = $triaged->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 font-medium"><?= str_pad($t['token_number'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <div>
                                            <p class="text-sm font-medium"><?= e($t['patient_name']) ?></p>
                                            <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($t['age']) ?>y &middot; <?= e($t['gender']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($t['department_name']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= $t['bp_systolic'] && $t['bp_diastolic'] ? e($t['bp_systolic'] . '/' . $t['bp_diastolic']) : '<span class="text-slate-400">-</span>' ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm <?= $t['temperature'] && $t['temperature'] > 37.5 ? 'text-red-500 font-medium' : '' ?>"><?= $t['temperature'] ? e($t['temperature'] . '°C') : '<span class="text-slate-400">-</span>' ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= $t['pulse'] ? e($t['pulse'] . ' bpm') : '<span class="text-slate-400">-</span>' ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm <?= $t['spo2'] && $t['spo2'] < 95 ? 'text-red-500 font-medium' : '' ?>"><?= $t['spo2'] ? e($t['spo2'] . '%') : '<span class="text-slate-400">-</span>' ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500"><?= priorityBadge($t['priority']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <?php
                                        $status_map = [
                                            'triage'     => ['bg-yellow-100 border-yellow-200 text-yellow-600 dark:bg-yellow-500/20 dark:border-yellow-500/20', 'Triage'],
                                            'consulting' => ['bg-sky-100 border-sky-200 text-sky-600 dark:bg-sky-500/20 dark:border-sky-500/20', 'Consulting'],
                                            'lab'        => ['bg-purple-100 border-purple-200 text-purple-600 dark:bg-purple-500/20 dark:border-purple-500/20', 'Lab'],
                                            'pharmacy'   => ['bg-orange-100 border-orange-200 text-orange-600 dark:bg-orange-500/20 dark:border-orange-500/20', 'Pharmacy'],
                                            'completed'  => ['bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20', 'Done'],
                                            'closed'     => ['bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20', 'Closed'],
                                        ];
                                        $s = $status_map[$t['status']] ?? ['bg-slate-100 border-slate-200 text-slate-500', $t['status']];
                                        ?>
                                        <span class="px-2.5 py-0.5 text-xs inline-block font-medium rounded border <?= $s[0] ?>"><?= $s[1] ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="py-8 text-center text-slate-400 dark:text-zink-300">
                        <p>No patients triaged today yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>