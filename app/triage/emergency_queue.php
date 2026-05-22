<?php
require_once '../config/config.php';

// Emergency patients from today
 $emergencies = $conn->query("
    SELECT v.visit_id, v.token_number, v.status, v.created_at,
           p.name as patient_name, p.phone, p.age, p.gender, p.patient_id,
           d.name as department_name,
           t.priority, t.bp_systolic, t.bp_diastolic, t.temperature, t.pulse, t.spo2,
           t.chief_complaint, t.triaged_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    JOIN triage t ON t.visit_id = v.id
    WHERE v.visit_date = CURDATE() AND t.is_emergency = 1
    ORDER BY 
        CASE t.priority 
            WHEN 'red' THEN 1 
            WHEN 'orange' THEN 2 
            ELSE 3 
        END ASC,
        t.triaged_at ASC
");

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Emergency Queue | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        @keyframes pulse-red {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        }
        .pulse-red { animation: pulse-red 2s infinite; }
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
                <div class="flex items-center gap-3 grow">
                    <div class="flex items-center justify-center size-10 rounded-full bg-red-100 dark:bg-red-500/20 pulse-red">
                        <i data-lucide="siren" class="size-5 text-red-500"></i>
                    </div>
                    <div>
                        <h5 class="text-16 text-red-500">Emergency Queue</h5>
                        <p class="text-xs text-slate-500 dark:text-zink-200"><?= $emergencies->num_rows ?> patient(s) requiring immediate attention</p>
                    </div>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Triage</a>
                    </li>
                    <li class="text-red-500 font-medium">Emergency</li>
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

            <?php if ($emergencies->num_rows > 0): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <?php while ($e = $emergencies->fetch_assoc()): ?>
                <div class="card <?= $e['priority'] === 'red' ? 'border-red-300 dark:border-red-500/50' : 'border-orange-300 dark:border-orange-500/50' ?>">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="flex items-start gap-3 mb-4">
                            <div class="flex items-center justify-center size-14 rounded-full shrink-0 <?= $e['priority'] === 'red' ? 'bg-red-100 dark:bg-red-500/20 pulse-red' : 'bg-orange-100 dark:bg-orange-500/20' ?>">
                                <span class="text-xl font-bold <?= $e['priority'] === 'red' ? 'text-red-500' : 'text-orange-500' ?>"><?= str_pad($e['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <div class="grow">
                                <div class="flex items-center gap-2 mb-1">
                                    <h5 class="text-base font-semibold"><?= e($e['patient_name']) ?></h5>
                                    <?php if ($e['priority'] === 'red'): ?>
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-red-500 text-white">Critical</span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-orange-500 text-white">Very Urgent</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-zink-200"><?= e($e['patient_id']) ?> &middot; <?= e($e['age']) ?>y &middot; <?= e($e['gender']) ?></p>
                                <p class="text-xs text-slate-400 dark:text-zink-300 mt-1"><?= e($e['department_name']) ?> &middot; Triaged at <?= date('h:i A', strtotime($e['triaged_at'])) ?></p>
                            </div>
                        </div>

                        <!-- Chief Complaint -->
                        <?php if ($e['chief_complaint']): ?>
                        <div class="mb-4 p-3 rounded-md bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                            <p class="text-xs font-medium text-red-500 mb-1">CHIEF COMPLAINT</p>
                            <p class="text-sm"><?= e($e['chief_complaint']) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Vitals Grid -->
                        <div class="grid grid-cols-5 gap-2 mb-4">
                            <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300">BP</p>
                                <p class="text-sm font-semibold <?= ($e['bp_systolic'] > 140 || $e['bp_diastolic'] > 90) ? 'text-red-500' : '' ?>"><?= $e['bp_systolic'] ?>/<?= $e['bp_diastolic'] ?></p>
                            </div>
                            <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300">Temp</p>
                                <p class="text-sm font-semibold <?= $e['temperature'] > 37.5 ? 'text-red-500' : '' ?>"><?= $e['temperature'] ?>°C</p>
                            </div>
                            <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300">Pulse</p>
                                <p class="text-sm font-semibold <?= ($e['pulse'] > 100 || $e['pulse'] < 60) ? 'text-red-500' : '' ?>"><?= $e['pulse'] ?></p>
                            </div>
                            <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300">SpO2</p>
                                <p class="text-sm font-semibold <?= $e['spo2'] < 95 ? 'text-red-500' : '' ?>"><?= $e['spo2'] ?>%</p>
                            </div>
                            <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300">Status</p>
                                <?php
                                $s_map = [
                                    'triage'     => ['bg-yellow-100 text-yellow-600', 'Triage'],
                                    'consulting' => ['bg-sky-100 text-sky-600', 'Consulting'],
                                    'completed'  => ['bg-green-100 text-green-500', 'Done'],
                                    'closed'     => ['bg-green-100 text-green-500', 'Closed'],
                                ];
                                $s = $s_map[$e['status']] ?? ['bg-slate-100 text-slate-500', $e['status']];
                                ?>
                                <p class="text-xs font-medium rounded px-1 <?= $s[0] ?>"><?= $s[1] ?></p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <a href="../consultation/consult.php?visit_id=<?= urlencode($e['visit_id']) ?>" class="flex items-center justify-center gap-1 flex-1 px-3 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                                <i data-lucide="stethoscope" class="size-4"></i> Start Consultation
                            </a>
                            <a href="../records/view_visit.php?id=<?= urlencode($e['visit_id']) ?>" class="flex items-center justify-center px-3 py-2 text-sm text-slate-500 rounded-md bg-white border border-slate-200 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                                <i data-lucide="eye" class="size-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body py-16 text-center">
                    <div class="flex items-center justify-center mx-auto mb-4 size-20 rounded-full bg-green-100 dark:bg-green-500/20">
                        <i data-lucide="shield-check" class="size-10 text-green-500"></i>
                    </div>
                    <h5 class="text-lg font-semibold mb-2">No Emergencies</h5>
                    <p class="text-slate-400 dark:text-zink-300">No emergency patients in queue right now</p>
                    <a href="index.php" class="inline-block mt-4 px-4 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">Back to Triage</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>