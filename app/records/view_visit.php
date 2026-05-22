<?php
require_once '../config/config.php';

$visit_id = trim($_GET['id'] ?? '');
if (empty($visit_id)) {
    header("Location: index.php");
    exit;
}

// Main visit query
$stmt = $conn->prepare("
    SELECT v.*, 
           p.patient_id as pat_id, p.name as patient_name, p.phone, p.email,
           p.dob, p.age, p.gender, p.blood_group, p.address,
           p.emergency_contact_name, p.emergency_contact_phone,
           p.created_at as patient_since,
           d.name as department_name,
           doc.name as doctor_name, doc.phone as doctor_phone,
           dep2.name as doctor_dept
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN departments dep2 ON doc.department_id = dep2.id
    WHERE v.visit_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    header("Location: index.php");
    exit;
}

// Triage
$triage = $conn->prepare("SELECT * FROM triage WHERE visit_id = ? LIMIT 1");
$triage->bind_param("i", $visit['id']);
$triage->execute();
$triage = $triage->get_result()->fetch_assoc();

// Consultation
$consult = $conn->prepare("
    SELECT c.*, doc.name as doctor_name 
    FROM consultations c 
    JOIN doctors doc ON c.doctor_id = doc.id
    WHERE c.visit_id = ? 
    LIMIT 1
");
$consult->bind_param("i", $visit['id']);
$consult->execute();
$consult = $consult->get_result()->fetch_assoc();

// Prescription items (if consultation exists)
$rx_items = [];
if ($consult) {
    $rx = $conn->prepare("
        SELECT pi.* FROM prescription_items pi
        JOIN prescriptions pr ON pi.prescription_id = pr.id
        WHERE pr.consultation_id = ?
    ");
    $rx->bind_param("i", $consult['id']);
    $rx->execute();
    $rx_items = $rx->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Lab orders
$lab_orders = $conn->prepare("
    SELECT lo.*, lr.results, lr.remarks 
    FROM lab_orders lo
    LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE lo.visit_id = ?
    ORDER BY lo.ordered_at ASC
");
$lab_orders->bind_param("i", $visit['id']);
$lab_orders->execute();
$lab_orders = $lab_orders->get_result()->fetch_all(MYSQLI_ASSOC);

// Scans
$scans = $conn->prepare("SELECT * FROM scans WHERE visit_id = ? ORDER BY uploaded_at ASC");
$scans->bind_param("i", $visit['id']);
$scans->execute();
$scans = $scans->get_result()->fetch_all(MYSQLI_ASSOC);

// Bill
$bill = $conn->prepare("SELECT * FROM bills WHERE visit_id = ? LIMIT 1");
$bill->bind_param("i", $visit['id']);
$bill->execute();
$bill = $bill->get_result()->fetch_assoc();

// Visit log
$vlog = $conn->prepare("
    SELECT vl.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name, s.role
    FROM visit_log vl
    LEFT JOIN staff s ON vl.performed_by_staff_id = s.id
    WHERE vl.visit_id = ?
    ORDER BY vl.created_at ASC
");
$vlog->bind_param("i", $visit['id']);
$vlog->execute();
$visit_log = $vlog->get_result()->fetch_all(MYSQLI_ASSOC);

// Previous visits count
$prev = $conn->prepare("SELECT COUNT(*) as cnt FROM visits WHERE patient_id = ? AND visit_id != ?");
$prev->bind_param("is", $visit['patient_id'], $visit_id);
$prev->execute();
$prev_count = $prev->get_result()->fetch_assoc()['cnt'];

// Status config
$status_map = [
    'registered' => ['bg-slate-100 border-slate-200 text-slate-600 dark:bg-slate-500/20 dark:text-zink-200', 'Registered'],
    'triage'     => ['bg-yellow-100 border-yellow-200 text-yellow-700 dark:bg-yellow-500/20', 'Triage'],
    'consulting' => ['bg-sky-100 border-sky-200 text-sky-700 dark:bg-sky-500/20', 'Consulting'],
    'lab'        => ['bg-purple-100 border-purple-200 text-purple-700 dark:bg-purple-500/20', 'Lab'],
    'pharmacy'   => ['bg-orange-100 border-orange-200 text-orange-700 dark:bg-orange-500/20', 'Pharmacy'],
    'completed'  => ['bg-green-100 border-green-200 text-green-700 dark:bg-green-500/20', 'Completed'],
    'closed'     => ['bg-green-100 border-green-200 text-green-700 dark:bg-green-500/20', 'Closed'],
];
$s = $status_map[$visit['status']] ?? ['bg-slate-100 border-slate-200 text-slate-600', ucfirst($visit['status'])];

$priority_map = [
    'green'  => ['bg-green-500',  'text-green-700',  'Low'],
    'yellow' => ['bg-yellow-400', 'text-yellow-700', 'Moderate'],
    'orange' => ['bg-orange-500', 'text-orange-700', 'High'],
    'red'    => ['bg-red-500',    'text-red-700',    'Critical'],
];
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Visit <?= e($visit_id) ?> | MediFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include '../reception/sidenav.php'; ?>
<?php include '../reception/topnav.php'; ?>

<div class="relative min-h-screen">
<div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0">
<div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

    <!-- Breadcrumb -->
    <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
        <div class="grow">
            <h5 class="text-16">Visit Details</h5>
        </div>
        <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
            <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                <a href="index.php" class="text-slate-400">Reception</a>
            </li>
            <li class="text-slate-700 dark:text-zink-100"><?= e($visit_id) ?></li>
        </ul>
    </div>

    <!-- Top Bar: Visit Header -->
    <div class="card mb-5">
        <div class="card-body">
            <div class="flex flex-col md:flex-row md:items-center gap-4">

                <!-- Token -->
                <div class="flex items-center justify-center size-16 rounded-xl bg-custom-500 text-white text-2xl font-bold shrink-0">
                    <?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?>
                </div>

                <!-- Main info -->
                <div class="grow">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h4 class="text-lg font-bold"><?= e($visit['patient_name']) ?></h4>
                        <span class="px-2.5 py-0.5 text-xs font-medium rounded border <?= $s[0] ?>"><?= $s[1] ?></span>
                        <?php if ($triage && $triage['is_emergency']): ?>
                        <span class="px-2.5 py-0.5 text-xs font-bold rounded border bg-red-100 border-red-300 text-red-600 animate-pulse">🚨 EMERGENCY</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-zink-200">
                        <?= e($visit['pat_id']) ?> &nbsp;·&nbsp;
                        <?= e($visit['age']) ?>y &nbsp;·&nbsp;
                        <?= e($visit['gender']) ?> &nbsp;·&nbsp;
                        <?= e($visit['blood_group'] ?: 'Unknown blood group') ?>
                    </p>
                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-1">
                        <i data-lucide="phone" class="inline-block size-3 mr-1"></i><?= e($visit['phone']) ?>
                        <?php if ($visit['email']): ?>&nbsp;·&nbsp;<i data-lucide="mail" class="inline-block size-3 mr-1"></i><?= e($visit['email']) ?><?php endif; ?>
                    </p>
                </div>

                <!-- Visit meta -->
                <div class="flex flex-col gap-1 text-right shrink-0">
                    <p class="text-xs text-slate-400 dark:text-zink-300">Visit ID</p>
                    <p class="text-sm font-semibold text-custom-500"><?= e($visit_id) ?></p>
                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-1"><?= date('D, d M Y', strtotime($visit['visit_date'])) ?></p>
                    <p class="text-xs text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($visit['created_at'])) ?></p>
                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-2 shrink-0 print:hidden">
                    <button onclick="window.print()" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 btn bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500">
                        <i data-lucide="printer" class="size-4"></i> Print
                    </button>
                    <a href="index.php" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-600 btn bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500">
                        <i data-lucide="arrow-left" class="size-4"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Journey Progress Bar -->
    <div class="card mb-5 print:hidden">
        <div class="card-body py-4">
            <div class="flex items-center justify-between relative">
                <!-- Line -->
                <div class="absolute left-0 right-0 top-4 h-0.5 bg-slate-200 dark:bg-zink-500 z-0 mx-8"></div>
                <?php
                $steps = ['registered','triage','consulting','lab','pharmacy','completed'];
                $step_icons = ['user-plus','activity','stethoscope','flask-conical','pill','check-circle'];
                $step_labels = ['Registered','Triage','Consulting','Lab','Pharmacy','Completed'];
                $current_idx = array_search($visit['status'], $steps);
                if ($current_idx === false) $current_idx = 0;
                foreach ($steps as $i => $step):
                    $done = $i <= $current_idx;
                    $active = $i === $current_idx;
                ?>
                <div class="flex flex-col items-center gap-1 z-10 relative">
                    <div class="flex items-center justify-center size-8 rounded-full border-2 <?= $done ? 'bg-custom-500 border-custom-500 text-white' : 'bg-white dark:bg-zink-700 border-slate-200 dark:border-zink-500 text-slate-400' ?> <?= $active ? 'ring-2 ring-custom-200 ring-offset-1' : '' ?>">
                        <i data-lucide="<?= $step_icons[$i] ?>" class="size-3.5"></i>
                    </div>
                    <span class="text-xs <?= $done ? 'text-custom-500 font-medium' : 'text-slate-400 dark:text-zink-300' ?> whitespace-nowrap hidden md:block"><?= $step_labels[$i] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 mb-5">

        <!-- Left: Patient + Department -->
        <div class="xl:col-span-4 flex flex-col gap-5">

            <!-- Patient Card -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="user" class="size-4 text-custom-500"></i> Patient Info
                    </h6>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Patient ID</span>
                            <span class="font-medium"><?= e($visit['pat_id']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Full Name</span>
                            <span class="font-medium"><?= e($visit['patient_name']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Phone</span>
                            <span class="font-medium"><?= e($visit['phone']) ?></span>
                        </div>
                        <?php if ($visit['dob']): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">DOB</span>
                            <span class="font-medium"><?= date('d M Y', strtotime($visit['dob'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Age / Gender</span>
                            <span class="font-medium"><?= e($visit['age']) ?>y / <?= e($visit['gender']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Blood Group</span>
                            <span class="font-medium"><?= e($visit['blood_group'] ?: 'N/A') ?></span>
                        </div>
                        <?php if ($visit['address']): ?>
                        <div class="flex justify-between text-sm gap-4">
                            <span class="text-slate-500 dark:text-zink-300 shrink-0">Address</span>
                            <span class="font-medium text-right"><?= e($visit['address']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($visit['emergency_contact_name']): ?>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zink-500">
                            <p class="text-xs text-slate-400 dark:text-zink-300 mb-2">Emergency Contact</p>
                            <p class="text-sm font-medium"><?= e($visit['emergency_contact_name']) ?></p>
                            <p class="text-sm text-slate-500"><?= e($visit['emergency_contact_phone']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zink-500 flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Previous Visits</span>
                            <span class="font-medium text-custom-500"><?= $prev_count ?> visit<?= $prev_count != 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visit / Doctor Card -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="stethoscope" class="size-4 text-custom-500"></i> Visit Assignment
                    </h6>
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Department</span>
                            <span class="font-medium"><?= e($visit['department_name']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Doctor</span>
                            <span class="font-medium"><?= e($visit['doctor_name'] ?? 'Not Assigned') ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Token</span>
                            <span class="inline-flex items-center justify-center size-8 rounded bg-custom-500 text-white text-xs font-bold"><?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Visit Date</span>
                            <span class="font-medium"><?= date('d M Y', strtotime($visit['visit_date'])) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Registered At</span>
                            <span class="font-medium"><?= date('h:i A', strtotime($visit['created_at'])) ?></span>
                        </div>
                        <?php if ($visit['closed_at']): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Closed At</span>
                            <span class="font-medium"><?= date('h:i A', strtotime($visit['closed_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300">Status</span>
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded border <?= $s[0] ?>"><?= $s[1] ?></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right: Clinical sections -->
        <div class="xl:col-span-8 flex flex-col gap-5">

            <!-- Triage / Vitals -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="activity" class="size-4 text-yellow-500"></i> Triage & Vitals
                        <?php if ($triage): ?>
                            <?php $p = $priority_map[$triage['priority']] ?? ['bg-slate-400','text-slate-600','Unknown']; ?>
                            <span class="ml-auto flex items-center gap-1.5 text-xs font-medium <?= $p[1] ?>">
                                <span class="inline-block size-2 rounded-full <?= $p[0] ?>"></span>
                                <?= $p[2] ?> Priority
                            </span>
                        <?php endif; ?>
                    </h6>

                    <?php if ($triage): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                        <?php
                        $vitals = [
                            ['Blood Pressure', ($triage['bp_systolic'] && $triage['bp_diastolic']) ? $triage['bp_systolic'].'/'.$triage['bp_diastolic'] : '—', 'mmHg', 'heart-pulse'],
                            ['Pulse', $triage['pulse'] ?? '—', 'bpm', 'activity'],
                            ['Temperature', $triage['temperature'] ?? '—', '°C', 'thermometer'],
                            ['SpO2', $triage['spo2'] ?? '—', '%', 'wind'],
                            ['Weight', $triage['weight'] ?? '—', 'kg', 'scale'],
                        ];
                        foreach ($vitals as $v):
                        ?>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 text-center">
                            <i data-lucide="<?= $v[3] ?>" class="size-4 mx-auto mb-1 text-slate-400"></i>
                            <p class="text-lg font-bold text-slate-700 dark:text-zink-100"><?= e($v[1]) ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300"><?= $v[0] ?></p>
                            <?php if ($v[1] !== '—'): ?><p class="text-xs text-slate-400"><?= $v[2] ?></p><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($triage['chief_complaint']): ?>
                    <div class="p-3 rounded-md bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20 mb-3">
                        <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-400 mb-1">Chief Complaint</p>
                        <p class="text-sm text-slate-700 dark:text-zink-100"><?= e($triage['chief_complaint']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($triage['notes']): ?>
                    <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                        <p class="text-xs font-semibold text-slate-500 dark:text-zink-300 mb-1">Triage Notes</p>
                        <p class="text-sm"><?= e($triage['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-3">Triaged at <?= date('h:i A, d M Y', strtotime($triage['triaged_at'])) ?></p>

                    <?php else: ?>
                    <div class="flex flex-col items-center py-8 text-slate-300 dark:text-zink-500">
                        <i data-lucide="activity" class="size-10 mb-2"></i>
                        <p class="text-sm">No triage data recorded</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Consultation -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="stethoscope" class="size-4 text-sky-500"></i> Consultation
                    </h6>

                    <?php if ($consult): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <?php
                        $fields = [
                            ['Chief Complaint', $consult['chief_complaint']],
                            ['History',         $consult['history']],
                            ['Examination',     $consult['examination']],
                            ['Diagnosis',       $consult['diagnosis']],
                        ];
                        foreach ($fields as [$label, $val]):
                            if (empty($val)) continue;
                        ?>
                        <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                            <p class="text-xs font-semibold text-slate-500 dark:text-zink-300 mb-1"><?= $label ?></p>
                            <p class="text-sm"><?= e($val) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($consult['notes']): ?>
                    <div class="p-3 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 mb-3">
                        <p class="text-xs font-semibold text-sky-600 dark:text-sky-400 mb-1">Doctor Notes</p>
                        <p class="text-sm"><?= e($consult['notes']) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($consult['follow_up_date']): ?>
                    <div class="flex items-center gap-2 p-3 rounded-md bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20">
                        <i data-lucide="calendar" class="size-4 text-purple-500 shrink-0"></i>
                        <div>
                            <p class="text-xs font-semibold text-purple-600 dark:text-purple-400">Follow-up: <?= date('d M Y', strtotime($consult['follow_up_date'])) ?></p>
                            <?php if ($consult['follow_up_notes']): ?>
                            <p class="text-xs text-slate-500 dark:text-zink-300 mt-0.5"><?= e($consult['follow_up_notes']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-3">
                        Consulted by <strong><?= e($consult['doctor_name']) ?></strong> at <?= date('h:i A, d M Y', strtotime($consult['consulted_at'])) ?>
                    </p>

                    <?php else: ?>
                    <div class="flex flex-col items-center py-8 text-slate-300 dark:text-zink-500">
                        <i data-lucide="stethoscope" class="size-10 mb-2"></i>
                        <p class="text-sm">No consultation recorded</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Prescription -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                        <i data-lucide="pill" class="size-4 text-orange-500"></i> Prescription
                        <?php if (count($rx_items)): ?>
                        <span class="ml-auto text-xs text-slate-400"><?= count($rx_items) ?> item<?= count($rx_items) > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </h6>

                    <?php if (count($rx_items)): ?>
                    <div class="-mx-5 overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="bg-slate-50 dark:bg-zink-600 text-slate-500 dark:text-zink-200 ltr:text-left">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">#</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">Medicine</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">Dosage</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">Frequency</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">Duration</th>
                                    <th class="px-4 py-2.5 text-xs font-semibold border-y border-slate-200 dark:border-zink-500">Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rx_items as $i => $rx): ?>
                                <tr>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm text-slate-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm font-medium"><?= e($rx['medicine_name']) ?></td>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($rx['dosage'] ?? '—') ?></td>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($rx['frequency'] ?? '—') ?></td>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($rx['duration'] ?? '—') ?></td>
                                    <td class="px-4 py-2.5 border-y border-slate-200 dark:border-zink-500 text-sm text-slate-500"><?= e($rx['instructions'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="flex flex-col items-center py-8 text-slate-300 dark:text-zink-500">
                        <i data-lucide="pill" class="size-10 mb-2"></i>
                        <p class="text-sm">No prescription issued</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Lab Orders -->
    <?php if (count($lab_orders)): ?>
    <div class="card mb-5">
        <div class="card-body">
            <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="flask-conical" class="size-4 text-purple-500"></i> Lab Orders
                <span class="ml-auto text-xs text-slate-400"><?= count($lab_orders) ?> test<?= count($lab_orders) > 1 ? 's' : '' ?></span>
            </h6>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($lab_orders as $lo):
                    $lab_status_map = [
                        'ordered'    => ['bg-slate-100 border-slate-200 text-slate-600', 'Ordered'],
                        'collected'  => ['bg-yellow-100 border-yellow-200 text-yellow-700', 'Collected'],
                        'processing' => ['bg-sky-100 border-sky-200 text-sky-700', 'Processing'],
                        'completed'  => ['bg-green-100 border-green-200 text-green-700', 'Completed'],
                    ];
                    $ls = $lab_status_map[$lo['status']] ?? ['bg-slate-100 border-slate-200 text-slate-600', ucfirst($lo['status'])];
                ?>
                <div class="p-4 rounded-md border border-slate-200 dark:border-zink-500">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <h6 class="text-sm font-medium"><?= e($lo['test_name']) ?></h6>
                        <span class="px-2 py-0.5 text-xs rounded border <?= $ls[0] ?> shrink-0"><?= $ls[1] ?></span>
                    </div>
                    <p class="text-xs text-slate-400 dark:text-zink-300">Ordered: <?= date('h:i A, d M Y', strtotime($lo['ordered_at'])) ?></p>
                    <?php if ($lo['completed_at']): ?>
                    <p class="text-xs text-slate-400 dark:text-zink-300">Completed: <?= date('h:i A, d M Y', strtotime($lo['completed_at'])) ?></p>
                    <?php endif; ?>
                    <?php if ($lo['results']): ?>
                    <div class="mt-2 p-2 rounded bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                        <p class="text-xs font-semibold text-green-700 dark:text-green-400 mb-1">Results</p>
                        <p class="text-xs"><?= e($lo['results']) ?></p>
                        <?php if ($lo['remarks']): ?><p class="text-xs text-slate-500 mt-1"><?= e($lo['remarks']) ?></p><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scans -->
    <?php if (count($scans)): ?>
    <div class="card mb-5">
        <div class="card-body">
            <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="scan" class="size-4 text-indigo-500"></i> Scans
            </h6>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($scans as $sc): ?>
                <div class="p-4 rounded-md border border-slate-200 dark:border-zink-500">
                    <div class="flex items-center justify-between mb-2">
                        <h6 class="text-sm font-medium"><?= e($sc['scan_type']) ?></h6>
                        <span class="px-2 py-0.5 text-xs rounded border <?= $sc['status'] === 'completed' ? 'bg-green-100 border-green-200 text-green-700' : 'bg-yellow-100 border-yellow-200 text-yellow-700' ?>">
                            <?= ucfirst($sc['status']) ?>
                        </span>
                    </div>
                    <?php if ($sc['findings']): ?>
                    <p class="text-xs text-slate-600 dark:text-zink-200"><?= e($sc['findings']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-1"><?= date('h:i A, d M Y', strtotime($sc['uploaded_at'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Billing -->
    <div class="card mb-5">
        <div class="card-body">
            <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="receipt" class="size-4 text-green-500"></i> Billing
            </h6>

            <?php if ($bill): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <div class="space-y-2">
                        <?php
                        $fee_rows = [
                            ['Registration Fee', $bill['registration_fee']],
                            ['Consultation Fee', $bill['consultation_fee']],
                            ['Lab Fee',          $bill['lab_fee']],
                            ['Scan Fee',         $bill['scan_fee']],
                            ['Medicine Fee',     $bill['medicine_fee']],
                        ];
                        if ($bill['other_fee'] > 0) {
                            $fee_rows[] = [$bill['other_description'] ?: 'Other', $bill['other_fee']];
                        }
                        foreach ($fee_rows as [$label, $amount]):
                            if ($amount <= 0) continue;
                        ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 dark:text-zink-300"><?= e($label) ?></span>
                            <span><?= number_format($amount, 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($bill['discount'] > 0): ?>
                        <div class="flex justify-between text-sm text-red-500">
                            <span>Discount</span>
                            <span>- <?= number_format($bill['discount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-base font-bold pt-2 border-t border-slate-200 dark:border-zink-500">
                            <span>Total</span>
                            <span class="text-custom-500"><?= number_format($bill['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Bill Number</p>
                        <p class="text-sm font-medium"><?= e($bill['bill_number']) ?></p>
                    </div>
                    <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Payment Method</p>
                        <p class="text-sm font-medium capitalize"><?= e($bill['payment_method']) ?></p>
                    </div>
                    <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                        <p class="text-xs text-slate-400 dark:text-zink-300 mb-1">Payment Status</p>
                        <span class="px-2.5 py-0.5 text-xs font-medium rounded border <?= $bill['payment_status'] === 'paid' ? 'bg-green-100 border-green-200 text-green-700' : ($bill['payment_status'] === 'partial' ? 'bg-yellow-100 border-yellow-200 text-yellow-700' : 'bg-red-100 border-red-200 text-red-700') ?>">
                            <?= ucfirst($bill['payment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php if ($bill['notes']): ?>
            <div class="mt-3 p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <p class="text-xs font-semibold text-slate-500 dark:text-zink-300 mb-1">Notes</p>
                <p class="text-sm"><?= e($bill['notes']) ?></p>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="flex flex-col items-center py-8 text-slate-300 dark:text-zink-500">
                <i data-lucide="receipt" class="size-10 mb-2"></i>
                <p class="text-sm">No bill generated yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Visit Log / Timeline -->
    <?php if (count($visit_log)): ?>
    <div class="card mb-5 print:hidden">
        <div class="card-body">
            <h6 class="text-15 font-semibold mb-4 flex items-center gap-2">
                <i data-lucide="clock" class="size-4 text-slate-500"></i> Visit Timeline
            </h6>
            <div class="relative pl-6">
                <div class="absolute left-2 top-0 bottom-0 w-0.5 bg-slate-200 dark:bg-zink-500"></div>
                <?php foreach ($visit_log as $log): ?>
                <div class="relative mb-4 last:mb-0">
                    <div class="absolute -left-4 top-1 size-2 rounded-full bg-custom-500 border-2 border-white dark:border-zink-700"></div>
                    <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 bg-slate-50 dark:bg-zink-600">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-sm font-medium"><?= e($log['action']) ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300 shrink-0"><?= date('h:i A', strtotime($log['created_at'])) ?></p>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zink-300">
                            <?php if ($log['from_station']): ?><?= e($log['from_station']) ?> → <?php endif; ?>
                            <?= e($log['to_station']) ?>
                            <?php if ($log['staff_name']): ?> &nbsp;·&nbsp; <?= e($log['staff_name']) ?> (<?= e($log['role']) ?>)<?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include '../reception/footer.php'; ?>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

</body>
</html>