<?php
require_once '../config/config.php';

 $visit_id = $_GET['id'] ?? '';

if (empty($visit_id)) {
    setFlash('error', 'No visit ID provided');
    header("Location: index.php");
    exit;
}

// Get everything
 $stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.age, p.gender, p.blood_group, p.address, p.patient_id, p.dob,
           d.name as department_name, doc.name as doctor_name,
           t.priority, t.bp_systolic, t.bp_diastolic, t.pulse, t.temperature, t.spo2, t.weight, t.chief_complaint as triage_complaint, t.notes as triage_notes, t.triaged_at, t.is_emergency,
           c.chief_complaint, c.history, c.examination, c.diagnosis, c.notes as consult_notes, c.follow_up_date, c.follow_up_notes, c.consulted_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    LEFT JOIN consultations c ON c.visit_id = v.id
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

// Get prescription
 $prescription = $conn->prepare("
    SELECT pr.notes as rx_notes, pr.created_at,
           pi.medicine_name, pi.dosage, pi.frequency, pi.duration, pi.instructions
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pi.prescription_id = pr.id
    WHERE pr.consultation_id = (SELECT id FROM consultations WHERE visit_id = ?)
    ORDER BY pi.id ASC
");
 $prescription->bind_param("s", $visit_id);
 $prescription->execute();
 $rx_items = [];
 $rx_notes = '';
while ($row = $prescription->get_result()->fetch_assoc()) {
    if ($row['medicine_name']) $rx_items[] = $row;
    $rx_notes = $row['rx_notes'];
}

// Get lab results
 $lab_results = $conn->prepare("
    SELECT lo.test_name, lo.status, lr.results, lr.remarks, lr.entered_at
    FROM lab_orders lo
    LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE lo.visit_id = ?
    ORDER BY lo.ordered_at
");
 $lab_results->bind_param("s", $visit_id);
 $lab_results->execute();
 $lab_items = [];
while ($lr = $lab_results->get_result()->fetch_assoc()) $lab_items[] = $lr;

// Get scans
 $scan_list = $conn->prepare("SELECT * FROM scans WHERE visit_id = ? ORDER BY uploaded_at DESC");
 $scan_list->bind_param("s", $visit_id);
 $scan_list->execute();
 $scans = [];
while ($s = $scan_list->get_result()->fetch_assoc()) $scans[] = $s;
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Review Visit | MediFlow OPD</title>
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
                    <h5 class="text-16">Review Visit</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Consultation</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Review</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 mb-5">
                <?php if ($visit['status'] !== 'closed'): ?>
                <a href="prescribe.php?visit_id=<?= urlencode($visit_id) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                    <i data-lucide="printer" class="size-4"></i> Print Prescription
                </a>
                <?php if ($visit['status'] === 'consulting'): ?>
                <a href="consult.php?visit_id=<?= urlencode($visit_id) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 transition-colors">
                    <i data-lucide="pencil" class="size-4"></i> Edit Consultation
                </a>
                <?php endif; ?>
                <?php if ($visit['status'] === 'lab'): ?>
                <a href="consult.php?visit_id=<?= urlencode($visit_id) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-purple-600 dark:text-purple-400 bg-purple-100 dark:bg-purple-500/20 hover:bg-purple-200 dark:hover:bg-purple-500/30 transition-colors">
                    <i data-lucide="flask-conical" class="size-4"></i> Review Lab Results
                </a>
                <?php endif; ?>
                <a href="../records/view_visit.php?id=<?= urlencode($visit_id) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 transition-colors">
                    <i data-lucide="eye" class="size-4"></i> Full Visit View
                </a>
                <?php endif; ?>
            </div>

            <!-- Patient Info -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center justify-center size-16 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                            <span class="text-2xl font-bold text-custom-500"><?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="grow">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="text-lg font-semibold"><?= e($visit['patient_name']) ?></h4>
                                <?php if ($visit['is_emergency']): ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-red-500 text-white">Emergency</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-zink-200"><?= e($visit['patient_id']) ?> &middot; <?= e($visit['age']) ?>y &middot; <?= e($visit['gender']) ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($visit['phone']) ?> &middot; <?= e($visit['address'] ?: 'No address') ?></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($visit['visit_id']) ?></p>
                            <p class="text-sm font-medium"><?= e($visit['department_name']) ?></p>
                            <p class="text-xs text-slate-400"><?= e($visit['doctor_name'] ?? 'No doctor') ?></p>
                            <span class="px-2.5 py-0.5 text-xs inline-block font-medium rounded border 
                                <?php 
                                $status_colors = [
                                    'registered' => 'bg-slate-100 border-slate-200 text-slate-500 dark:bg-slate-500/20 dark:border-slate-500/20 dark:text-zink-200',
                                    'triage' => 'bg-yellow-100 border-yellow-200 text-yellow-600 dark:bg-yellow-500/20 dark:border-yellow-500/20',
                                    'consulting' => 'bg-sky-100 border-sky-200 text-sky-600 dark:bg-sky-500/20 dark:border-sky-500/20',
                                    'lab' => 'bg-purple-100 border-purple-200 text-purple-600 dark:bg-purple-500/20 dark:border-purple-500/20',
                                    'pharmacy' => 'bg-orange-100 border-orange-200 text-orange-600 dark:bg-orange-500/20 dark:border-orange-500/20',
                                    'completed' => 'bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20',
                                    'closed' => 'bg-green-100 border-green-200 text-green-500 dark:bg-green-500/20 dark:border-green-500/20',
                                ];
                                $sc = $status_colors[$visit['status']] ?? $status_colors['registered'];
                                ?><?= $sc ?>"><?= ucfirst($visit['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visit Timeline -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Left Column: Triage + Consultation -->
                <div class="xl:col-span-2 flex flex-col gap-5">

                    <!-- Triage -->
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center size-8 rounded-full bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                    <i data-lucide="activity" class="size-4 text-yellow-500"></i>
                                </div>
                                <h6 class="text-15 mb-0">Triage</h6>
                                <span class="ml-auto text-xs text-slate-400 dark:text-zink-300"><?= $visit['triaged_at'] ? date('h:i A', strtotime($visit['triaged_at'])) : 'N/A' ?></span>
                            </div>

                            <?php if ($visit['bp_systolic'] || $visit['temperature']): ?>
                            <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-4">
                                <?php if ($visit['bp_systolic']): ?>
                                <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                    <p class="text-[10px] text-slate-400">BP</p>
                                    <p class="text-sm font-semibold <?= ($visit['bp_systolic'] > 140 || $visit['bp_diastolic'] > 90) ? 'text-red-500' : '' ?>"><?= $visit['bp_systolic'] ?>/<?= $visit['bp_diastolic'] ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['temperature']): ?>
                                <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                    <p class="text-[10px] text-slate-400">Temp</p>
                                    <p class="text-sm font-semibold <?= $visit['temperature'] > 37.5 ? 'text-red-500' : '' ?>"><?= $visit['temperature'] ?>°C</p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['pulse']): ?>
                                <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                    <p class="text-[10px] text-slate-400">Pulse</p>
                                    <p class="text-sm font-semibold"><?= $visit['pulse'] ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['spo2']): ?>
                                <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                    <p class="text-[10px] text-slate-400">SpO2</p>
                                    <p class="text-sm font-semibold"><?= $visit['spo2'] ?>%</p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['weight']): ?>
                                <div class="p-2 rounded text-center bg-slate-50 dark:bg-zink-600">
                                    <p class="text-[10px] text-slate-400">Weight</p>
                                    <p class="text-sm font-semibold"><?= $visit['weight'] ?> kg</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($visit['triage_complaint']): ?>
                            <div class="mb-3 p-3 rounded-md bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/20">
                                <p class="text-xs font-medium text-yellow-600 dark:text-yellow-400 mb-1">Chief Complaint (Triage)</p>
                                <p class="text-sm"><?= e($visit['triage_complaint']) ?></p>
                            </div>
                            <?php endif; ?>

                            <p class="text-xs text-slate-400 dark:text-zink-200 mt-2">Priority: <span class="font-medium text-<?= $visit['priority'] === 'red' ? 'red' : $visit['priority'] === 'orange' ? 'orange' : $visit['priority'] === 'yellow' ? 'yellow' : 'green' ?>"><?= ucfirst($visit['priority'] ?? 'N/A' ?></span></p>
                        </div>
                    </div>

                    <!-- Consultation -->
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center size-8 rounded-full bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                    <i data-lucide="stethoscope" class="size-4 text-sky-500"></i>
                                </div>
                                <h6 class="text-15 mb-0">Consultation</h6>
                                <span class="ml-auto text-xs text-slate-400 dark:text-zink-300"><?= $visit['consulted_at'] ? date('h:i A', strtotime($visit['consulted_at'])) : 'Not consulted yet' ?></span>
                            </div>

                            <?php if ($visit['chief_complaint'] || $visit['history'] || $visit['examination']): ?>
                            <div class="space-y-3 mb-4">
                                <?php if ($visit['chief_complaint']): ?>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Chief Complaint</p>
                                    <p class="text-sm bg-slate-50 dark:bg-zink-600 p-2 rounded"><?= e($visit['chief_complaint']) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['history']): ?>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">History</p>
                                    <p class="text-sm bg-slate-50 dark:bg-zink-600 p-2 rounded whitespace-pre-line"><?= e($visit['history']) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($visit['examination']): ?>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Examination</p>
                                    <p class="text-sm bg-slate-50 dark:bg-zink-600 p-2 rounded whitespace-pre-line"><?= e($visit['examination']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($visit['diagnosis']): ?>
                            <div class="p-3 rounded-md bg-custom-50 dark:bg-custom-500/10 border border-custom-200 dark:border-custom-500/20 mb-4">
                                <p class="text-[10px] text-custom-500 uppercase tracking-wider mb-1">Diagnosis</p>
                                <p class="text-sm font-medium"><?= e($visit['diagnosis']) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($visit['consult_notes']): ?>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Doctor's Notes</p>
                                <p class="text-sm text-slate-500 dark:text-zink-200 whitespace-pre-line"><?= e($visit['consult_notes']) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($visit['follow_up_date']): ?>
                            <div class="mt-4 p-3 rounded-md border border-dashed border-orange-300 dark:border-orange-500/30 bg-orange-50/50 dark:bg-orange-500/5">
                                <p class="text-[10px] text-orange-500 uppercase tracking-wider mb-1">Follow-up</p>
                                <p class="text-sm font-medium"><?= date('d M Y', strtotime($visit['follow_up_date'])) ?></p>
                                <?php if ($visit['follow_up_notes']): ?>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-1"><?= e($visit['follow_up_notes']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Lab + Prescription -->
                <div class="flex flex-col gap-5">

                    <!-- Lab Results -->
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center size-8 rounded-full bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                    <i data-lucide="flask-conical" class="size-4 text-purple-500"></i>
                                </div>
                                <h6 class="text-15 mb-0">Lab Results <span class="text-slate-400 dark:text-zink-200 font-normal" x-text="'(' + labItems.length + ')'" x-init="labItems = <?= json_encode($lab_items) ?>"></span></h6>
                            </div>

                            <?php if (!empty($lab_items)): ?>
                            <div class="space-y-3">
                                <?php foreach ($lab_items as $lab): ?>
                                <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="text-sm font-medium"><?= e($lab['test_name']) ?></p>
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded 
                                            <?= $lab['status'] === 'completed' ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500' ?>"
                                        ><?= ucfirst($lab['status'] ?? 'Ordered') ?></span>
                                    </div>
                                    <?php if ($lab['results']): ?>
                                    <div class="mt-2 p-2 rounded bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                                        <p class="text-[10px] text-green-600 mb-1 font-medium">RESULTS</p>
                                        <p class="text-xs text-slate-600 dark:text-zink-200 whitespace-pre-line"><?= e($lab['results']) ?></p>
                                        <?php if ($lab['remarks']): ?>
                                        <p class="text-[10px] text-slate-400 mt-1">Remarks: <?= e($lab['remarks']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif ($lab['status'] === 'collected'): ?>
                                    <p class="text-xs text-sky-500 mt-1"><i data-lucide="test-tube" class="inline-block size-3 ltr:mr-1 rtl:ml-1"></i> Sample collected, waiting for results</p>
                                    <?php else: ?>
                                    <p class="text-xs text-slate-400 mt-1"><i data-lucucide="clock" class="inline-block size-3 ltr:mr-1 rtl:ml-1"></i> Waiting for sample collection</p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-sm text-slate-400 text-center py-4">No lab tests ordered</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Prescription -->
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center size-8 rounded-full bg-green-100 dark:bg-green-500/20 shrink-0">
                                    <i data-lucide="pill" class="size-4 text-green-500"></i>
                                </div>
                                <h6 class="text-15 mb-0">Prescription</h6>
                            </div>

                            <?php if (!empty($rx_items)): ?>
                            <div class="space-y-2">
                                <?php foreach ($rx_items as $i => $rx): ?>
                                <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 bg-white dark:bg-zink-700">
                                    <p class="text-sm font-semibold mb-1"><?= e($rx['medicine_name']) ?></p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zink-200">
                                        <?php if ($rx['dosage']): ?><span><?= e($rx['dosage']) ?></span><?php endif; ?>
                                        <?php if ($rx['frequency']): ?><span><?= e($rx['frequency']) ?></span><?php endif; ?>
                                        <?php if ($rx['duration']): ?><span><?= e($rx['duration']) ?></span><?php endif; ?>
                                    </div>
                                    <?php if ($rx['instructions']): ?>
                                    <p class="text-[10px] text-slate-400 mt-1 italic"><?= e($rx['instructions']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($rx_notes): ?>
                            <div class="mt-3 p-2 rounded bg-slate-50 dark:bg-zink-600">
                                <p class="text-[10px] text-slate-400 uppercase tracking-wider">Rx Notes</p>
                                <p class="text-xs text-slate-500 dark:text-zink-200"><?= e($rx_notes) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <p class="text-sm text-slate-400 text-center py-4">No prescription written</p>
                            <?php endif; ?>

                            <?php if (!empty($rx_items)): ?>
                            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-zink-500">
                                <a href="prescribe.php?visit_id=<?= urlencode($visit_id) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-white bg-green-500 hover:bg-green-600 rounded-md transition-colors w-full justify-center">
                                    <i data-lucide="printer" class="size-4"></i> Print Prescription
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Scans -->
                    <?php if (!empty($scans)): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center size-8 rounded-full bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                    <i data-lucide="scan" class="size-4 text-sky-500"></i>
                                </div>
                                <h6 class="text-15 mb-0">Scans <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= count($scans) ?>)</span></h6>
                            </div>

                            <div class="space-y-3">
                                <?php foreach ($scans as $scan): ?>
                                <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-sm font-medium"><?= e($scan['scan_type']) ?></p>
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded 
                                            <?= $scan['status'] === 'completed' ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600' ?>"
                                        ><?= $scan['status'] === 'completed' ? 'Ready' : 'Pending' ?></span>
                                    </div>
                                    <?php if ($scan['findings']): ?>
                                    <div class="p-2 rounded bg-slate-50 dark:bg-zink-600 mt-1">
                                        <p class="text-[10px] text-slate-400 mb-1">Findings</p>
                                        <p class="text-xs text-slate-600 dark:text-zink-200 whitespace-pre-line"><?= e($scan['findings']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-5 no-print">
                <a href="index.php" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600">
                    <i data-lucide="arrow-left" class="size-4"></i> Back to Queue
                </a>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>