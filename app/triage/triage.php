<?php
require_once '../config/config.php';

$visit_id = $_GET['visit_id'] ?? '';

if (empty($visit_id)) {
    setFlash('error', 'No visit ID provided');
    header("Location: index.php");
    exit;
}

// Get visit + patient info
$stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.age, p.gender, p.patient_id, p.blood_group,
           d.name as department_name, doc.name as doctor_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    WHERE v.visit_id = ? AND v.status = 'registered'
");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    setFlash('error', 'Visit not found or already triaged');
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bp_sys    = (int)($_POST['bp_systolic'] ?? 0);
    $bp_dia    = (int)($_POST['bp_diastolic'] ?? 0);
    $pulse     = (int)($_POST['pulse'] ?? 0);
    $temp      = (float)($_POST['temperature'] ?? 0);
    $spo2      = (int)($_POST['spo2'] ?? 0);
    $weight    = (float)($_POST['weight'] ?? 0);
    $priority  = $_POST['priority'] ?? 'green';
    $complaint = trim($_POST['chief_complaint'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $is_emergency = ($priority === 'red' || $priority === 'orange') ? 1 : 0;

    $errors = [];
    if ($pulse <= 0)      $errors[] = "Pulse is required";
    if ($temp <= 0)       $errors[] = "Temperature is required";
    if (empty($priority)) $errors[] = "Priority must be selected";

    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        header("Location: triage.php?visit_id=" . urlencode($visit_id));
        exit;
    }

    $bp_sys_val = $bp_sys ?: null;
    $bp_dia_val = $bp_dia ?: null;
    $temp_val   = $temp   ?: null;
    $spo2_val   = $spo2   ?: null;
    $weight_val = $weight ?: null;

    $conn->begin_transaction();
    try {
        // Insert triage record
        $stmt = $conn->prepare("
            INSERT INTO triage (visit_id, priority, bp_systolic, bp_diastolic, pulse, temperature, spo2, weight, chief_complaint, notes, is_emergency)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isiiiddsssi",
            $visit['id'], $priority, $bp_sys_val, $bp_dia_val, $pulse,
            $temp_val, $spo2_val, $weight_val,
            $complaint, $notes, $is_emergency
        );
        $stmt->execute();

        // Update visit status
        $stmt = $conn->prepare("UPDATE visits SET status = 'triage' WHERE id = ?");
        $stmt->bind_param("i", $visit['id']);
        $stmt->execute();

        $conn->commit();

        if ($is_emergency) {
            setFlash('success', 'Emergency patient recorded! Patient moved to emergency queue.');
            header("Location: emergency_queue.php");
        } else {
            setFlash('success', 'Triage completed! Patient sent to consultation queue.');
            header("Location: index.php");
        }
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', 'Triage failed: ' . $e->getMessage());
        header("Location: triage.php?visit_id=" . urlencode($visit_id));
        exit;
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Triage Patient | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        [x-cloak] { display: none !important; }
        .priority-card { cursor: pointer; transition: all 0.2s; }
        .priority-card:hover { transform: translateY(-2px); }
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
                <div class="grow">
                    <h5 class="text-16">Triage Patient</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Triage</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Patient</li>
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

            <!-- Patient Info Bar -->
            <div class="flex flex-wrap items-center gap-4 p-4 mb-5 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="flex items-center justify-center size-14 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                    <span class="text-xl font-bold text-custom-500"><?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="grow">
                    <h5 class="text-base font-semibold"><?= e($visit['patient_name']) ?></h5>
                    <p class="text-sm text-slate-500 dark:text-zink-200"><?= e($visit['patient_id']) ?> &middot; <?= e($visit['age']) ?>y &middot; <?= e($visit['gender']) ?> &middot; <?= e($visit['blood_group'] ?: 'N/A') ?></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-slate-400 dark:text-zink-300">Department</p>
                    <p class="text-sm font-medium"><?= e($visit['department_name']) ?></p>
                    <?php if ($visit['doctor_name']): ?>
                    <p class="text-xs text-slate-400 dark:text-zink-300 mt-1">Doctor</p>
                    <p class="text-sm font-medium"><?= e($visit['doctor_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Triage Form -->
            <form method="POST" action="triage.php?visit_id=<?= urlencode($visit_id) ?>" x-data="triageForm()">

                <!-- Vitals -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white shrink-0">
                                <i data-lucide="heart-pulse" class="size-5"></i>
                            </div>
                            <div>
                                <h6 class="text-15 mb-0">Vital Signs</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Record patient vitals — priority is auto-calculated from readings</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <!-- BP Systolic -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">BP Systolic</label>
                                <div class="relative">
                                    <input type="number" name="bp_systolic" x-model="bpSys" min="60" max="250"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-10 text-center text-lg font-semibold"
                                           placeholder="120">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">mmHg</span>
                                </div>
                                <p class="text-xs mt-1 text-red-500" x-show="bpSys > 0 && bpSys > 140">High</p>
                            </div>
                            <!-- BP Diastolic -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">BP Diastolic</label>
                                <div class="relative">
                                    <input type="number" name="bp_diastolic" x-model="bpDia" min="40" max="150"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-10 text-center text-lg font-semibold"
                                           placeholder="80">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">mmHg</span>
                                </div>
                                <p class="text-xs mt-1 text-red-500" x-show="bpDia > 0 && bpDia > 90">High</p>
                            </div>
                            <!-- Pulse -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Pulse <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="number" name="pulse" x-model="pulse" required min="30" max="200"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-12 text-center text-lg font-semibold"
                                           placeholder="72">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">bpm</span>
                                </div>
                                <p class="text-xs mt-1 text-red-500" x-show="pulse > 0 && (pulse > 100 || pulse < 60)">Abnormal</p>
                            </div>
                            <!-- Temperature -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Temperature <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="number" name="temperature" x-model="temp" required min="35" max="42" step="0.1"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-8 text-center text-lg font-semibold"
                                           placeholder="36.6">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">°C</span>
                                </div>
                                <p class="text-xs mt-1 text-red-500" x-show="temp > 0 && temp > 37.5">Fever</p>
                            </div>
                            <!-- SpO2 -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">SpO2</label>
                                <div class="relative">
                                    <input type="number" name="spo2" x-model="spo2" min="50" max="100"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-6 text-center text-lg font-semibold"
                                           placeholder="98">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">%</span>
                                </div>
                                <p class="text-xs mt-1 text-red-500" x-show="spo2 > 0 && spo2 < 95">Low</p>
                            </div>
                            <!-- Weight -->
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Weight (kg)</label>
                                <div class="relative">
                                    <input type="number" name="weight" x-model="weight" min="1" max="300" step="0.1"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 pr-6 text-center text-lg font-semibold"
                                           placeholder="65">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">kg</span>
                                </div>
                            </div>
                        </div>

                        <!-- BP Indicator (only shown when BP entered) -->
                        <div x-show="bpSys > 0 && bpDia > 0"
                             x-transition
                             class="mt-4 p-3 rounded-md border"
                             :class="bpClass">
                            <div class="flex items-center gap-2">
                                <i data-lucide="heart" class="size-4"></i>
                                <span class="text-sm font-medium" x-text="bpLabel"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Priority Selection -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white shrink-0">
                                    <i data-lucide="flag" class="size-5"></i>
                                </div>
                                <div>
                                    <h6 class="text-15 mb-0">Priority Level <span class="text-red-500">*</span></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200">Auto-calculated from vitals — override only if clinically needed</p>
                                </div>
                            </div>
                            <!-- Auto-calculated badge -->
                            <div x-show="calculatedPriority !== ''" class="shrink-0">
                                <span x-show="!isOverridden"
                                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-custom-100 text-custom-600 dark:bg-custom-500/20 dark:text-custom-400">
                                    <i data-lucide="cpu" class="size-3"></i> Auto-calculated
                                </span>
                                <span x-show="isOverridden"
                                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">
                                    <i data-lucide="pencil" class="size-3"></i> Manually overridden
                                </span>
                            </div>
                        </div>

                        <!-- Scoring explanation -->
                        <div x-show="calculatedPriority !== ''" x-transition class="mb-4 p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500 text-xs text-slate-500 dark:text-zink-300">
                            <span class="font-medium text-slate-600 dark:text-zink-200">Scoring factors: </span>
                            <span x-text="scoreBreakdown"></span>
                        </div>

                        <input type="hidden" name="priority" x-model="selectedPriority" :value="selectedPriority" required>

                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Green -->
                            <div class="priority-card p-4 rounded-md border-2 text-center transition-all relative"
                                 :class="selectedPriority === 'green'
                                     ? 'border-green-500 bg-green-50 dark:bg-green-500/10'
                                     : 'border-slate-200 dark:border-zink-500 hover:border-green-300 opacity-60'"
                                 @click="manualSelect('green')">
                                <!-- Auto badge on the calculated card -->
                                <span x-show="calculatedPriority === 'green' && !isOverridden"
                                      class="absolute -top-2 left-1/2 -translate-x-1/2 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-500 text-white whitespace-nowrap">
                                    <i data-lucide="zap" class="size-2.5"></i> Suggested
                                </span>
                                <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-green-100 dark:bg-green-500/20">
                                    <i data-lucide="check-circle" class="size-6 text-green-500"></i>
                                </div>
                                <h6 class="font-semibold text-green-600 dark:text-green-400">GREEN</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-1">Non-urgent</p>
                                <p class="text-[10px] text-slate-400 dark:text-zink-300 mt-2">Stable vitals, minor complaints</p>
                            </div>
                            <!-- Yellow -->
                            <div class="priority-card p-4 rounded-md border-2 text-center transition-all relative"
                                 :class="selectedPriority === 'yellow'
                                     ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-500/10'
                                     : 'border-slate-200 dark:border-zink-500 hover:border-yellow-300 opacity-60'"
                                 @click="manualSelect('yellow')">
                                <span x-show="calculatedPriority === 'yellow' && !isOverridden"
                                      class="absolute -top-2 left-1/2 -translate-x-1/2 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-500 text-white whitespace-nowrap">
                                    <i data-lucide="zap" class="size-2.5"></i> Suggested
                                </span>
                                <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-yellow-100 dark:bg-yellow-500/20">
                                    <i data-lucide="alert-triangle" class="size-6 text-yellow-500"></i>
                                </div>
                                <h6 class="font-semibold text-yellow-600 dark:text-yellow-400">YELLOW</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-1">Urgent</p>
                                <p class="text-[10px] text-slate-400 dark:text-zink-300 mt-2">Mild pain, fever, needs attention</p>
                            </div>
                            <!-- Orange -->
                            <div class="priority-card p-4 rounded-md border-2 text-center transition-all relative"
                                 :class="selectedPriority === 'orange'
                                     ? 'border-orange-500 bg-orange-50 dark:bg-orange-500/10'
                                     : 'border-slate-200 dark:border-zink-500 hover:border-orange-300 opacity-60'"
                                 @click="manualSelect('orange')">
                                <span x-show="calculatedPriority === 'orange' && !isOverridden"
                                      class="absolute -top-2 left-1/2 -translate-x-1/2 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-orange-500 text-white whitespace-nowrap">
                                    <i data-lucide="zap" class="size-2.5"></i> Suggested
                                </span>
                                <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-orange-100 dark:bg-orange-500/20">
                                    <i data-lucide="alert-octagon" class="size-6 text-orange-500"></i>
                                </div>
                                <h6 class="font-semibold text-orange-600 dark:text-orange-400">ORANGE</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-1">Very Urgent</p>
                                <p class="text-[10px] text-slate-400 dark:text-zink-300 mt-2">Severe pain, high fever, abnormal vitals</p>
                            </div>
                            <!-- Red -->
                            <div class="priority-card p-4 rounded-md border-2 text-center transition-all relative"
                                 :class="selectedPriority === 'red'
                                     ? 'border-red-500 bg-red-50 dark:bg-red-500/10'
                                     : 'border-slate-200 dark:border-zink-500 hover:border-red-300 opacity-60'"
                                 @click="manualSelect('red')">
                                <span x-show="calculatedPriority === 'red' && !isOverridden"
                                      class="absolute -top-2 left-1/2 -translate-x-1/2 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500 text-white whitespace-nowrap">
                                    <i data-lucide="zap" class="size-2.5"></i> Suggested
                                </span>
                                <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-red-100 dark:bg-red-500/20">
                                    <i data-lucide="siren" class="size-6 text-red-500"></i>
                                </div>
                                <h6 class="font-semibold text-red-600 dark:text-red-400">RED</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-1">EMERGENCY</p>
                                <p class="text-[10px] text-slate-400 dark:text-zink-300 mt-2">Life-threatening, immediate care needed</p>
                            </div>
                        </div>

                        <!-- Override reason (shown when manually changed from suggestion) -->
                        <div x-show="isOverridden" x-transition class="mt-4 p-3 rounded-md bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                            <div class="flex items-start gap-2">
                                <i data-lucide="pencil" class="size-4 text-amber-500 shrink-0 mt-0.5"></i>
                                <div class="grow">
                                    <p class="text-sm text-amber-700 dark:text-amber-400 font-medium mb-1">
                                        Priority changed from <strong x-text="calculatedPriority.toUpperCase()"></strong> to <strong x-text="selectedPriority.toUpperCase()"></strong>
                                    </p>
                                    <input type="text" name="override_reason" placeholder="Reason for override (optional)"
                                           class="form-input border-amber-200 dark:border-amber-500/30 focus:outline-none focus:border-amber-400 dark:text-zink-100 dark:bg-zink-700 text-sm w-full">
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Warning -->
                        <div x-show="selectedPriority === 'red' || selectedPriority === 'orange'"
                             x-transition
                             class="mt-4 p-3 rounded-md bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="size-5 text-red-500 shrink-0"></i>
                                <span class="text-sm text-red-600 dark:text-red-400 font-medium">
                                    Patient will be added to <strong>Emergency Queue</strong>
                                </span>
                            </div>
                        </div>

                        <!-- Waiting for vitals message -->
                        <div x-show="calculatedPriority === ''" x-transition class="mt-4 p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                            <div class="flex items-center gap-2 text-slate-400 dark:text-zink-300">
                                <i data-lucide="loader" class="size-4"></i>
                                <span class="text-sm">Enter pulse and temperature to auto-calculate priority</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chief Complaint & Notes -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white shrink-0">
                                <i data-lucide="clipboard-list" class="size-5"></i>
                            </div>
                            <div>
                                <h6 class="text-15 mb-0">Clinical Notes</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Chief complaint and observations</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Chief Complaint</label>
                                <textarea name="chief_complaint" rows="2"
                                          class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                          placeholder="What is the patient's main problem?"></textarea>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Additional Notes</label>
                                <textarea name="notes" rows="2"
                                          class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                          placeholder="Any other observations..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            :disabled="!selectedPriority"
                            class="px-6 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="(selectedPriority === 'red' || selectedPriority === 'orange') ? '!bg-red-500 !border-red-500 hover:!bg-red-600 hover:!border-red-600' : ''">
                        <i data-lucide="check" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                        <span x-text="(selectedPriority === 'red' || selectedPriority === 'orange') ? 'Send to Emergency' : 'Complete Triage'"></span>
                    </button>
                    <a href="index.php" class="px-6 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
    function triageForm() {
        return {
            bpSys: '',
            bpDia: '',
            pulse: '',
            temp: '',
            spo2: '',
            weight: '',
            selectedPriority: '',
            calculatedPriority: '',
            isOverridden: false,
            scoreBreakdown: '',

            init() {
                // Watch vitals and recalculate whenever they change
                this.$watch('pulse', () => this.recalculate());
                this.$watch('temp',  () => this.recalculate());
                this.$watch('spo2',  () => this.recalculate());
                this.$watch('bpSys', () => this.recalculate());
                this.$watch('bpDia', () => this.recalculate());
            },

            /**
             * Auto-priority scoring rules (evidence-based triage criteria):
             *
             * Each abnormal vital contributes a score. Final score maps to a priority level.
             *
             * RED  (score >= 4, or any single immediately life-threatening value)
             * ORANGE (score 3)
             * YELLOW (score 2)
             * GREEN  (score 0-1)
             *
             * Scoring:
             *   SpO2 < 85%                    → +4 (immediate RED)
             *   SpO2 85–<90%                  → +3
             *   SpO2 90–<95%                  → +2
             *   Pulse < 40 or > 150           → +4
             *   Pulse 40–<60 or 101–150       → +2
             *   Temp > 40°C or < 35.5°C       → +3
             *   Temp 38.5–40°C or 35.5–36°C  → +2
             *   Temp 37.6–38.5°C              → +1
             *   BP sys > 200 or < 80          → +3
             *   BP sys 181–200 or 80–90       → +2
             *   BP sys 141–180                → +1
             *   BP dia > 120                  → +3
             *   BP dia 91–120                 → +1
             */
            recalculate() {
                const pulse = parseFloat(this.pulse);
                const temp  = parseFloat(this.temp);
                const spo2  = parseFloat(this.spo2);
                const bpSys = parseFloat(this.bpSys);
                const bpDia = parseFloat(this.bpDia);

                // Need at least pulse + temp to calculate
                if (!(pulse > 0) || !(temp > 0)) {
                    this.calculatedPriority = '';
                    this.scoreBreakdown = '';
                    if (!this.isOverridden) this.selectedPriority = '';
                    return;
                }

                let score = 0;
                let factors = [];

                // --- SpO2 ---
                if (spo2 > 0) {
                    if (spo2 < 85) {
                        score += 4; factors.push('SpO2 critically low (<85%)');
                    } else if (spo2 < 90) {
                        score += 3; factors.push('SpO2 severely low (85–89%)');
                    } else if (spo2 < 95) {
                        score += 2; factors.push('SpO2 low (90–94%)');
                    }
                }

                // --- Pulse ---
                if (pulse < 40 || pulse > 150) {
                    score += 4; factors.push('Pulse critical (' + pulse + ' bpm)');
                } else if (pulse < 60 || pulse > 100) {
                    score += 2; factors.push('Pulse abnormal (' + pulse + ' bpm)');
                }

                // --- Temperature ---
                if (temp > 40 || temp < 35.5) {
                    score += 3; factors.push('Temperature extreme (' + temp + '°C)');
                } else if (temp >= 38.5 || temp < 36.0) {
                    score += 2; factors.push('Temperature abnormal (' + temp + '°C)');
                } else if (temp >= 37.6) {
                    score += 1; factors.push('Mild fever (' + temp + '°C)');
                }

                // --- BP (optional) ---
                if (bpSys > 0) {
                    if (bpSys > 200 || bpSys < 80) {
                        score += 3; factors.push('BP systolic critical (' + bpSys + ')');
                    } else if (bpSys > 180 || bpSys <= 90) {
                        score += 2; factors.push('BP systolic very abnormal (' + bpSys + ')');
                    } else if (bpSys > 140) {
                        score += 1; factors.push('BP systolic elevated (' + bpSys + ')');
                    }
                }
                if (bpDia > 0) {
                    if (bpDia > 120) {
                        score += 3; factors.push('BP diastolic critical (' + bpDia + ')');
                    } else if (bpDia > 90) {
                        score += 1; factors.push('BP diastolic elevated (' + bpDia + ')');
                    }
                }

                // Map score → priority
                let priority;
                if (score >= 4) {
                    priority = 'red';
                } else if (score === 3) {
                    priority = 'orange';
                } else if (score === 2) {
                    priority = 'yellow';
                } else {
                    priority = 'green';
                }

                this.calculatedPriority = priority;
                this.scoreBreakdown = factors.length
                    ? factors.join(' · ') + ' (score: ' + score + ')'
                    : 'All vitals within normal range (score: 0)';

                // Auto-apply unless the user has manually overridden
                if (!this.isOverridden) {
                    this.selectedPriority = priority;
                }
            },

            manualSelect(priority) {
                if (this.calculatedPriority !== '' && priority !== this.calculatedPriority) {
                    this.isOverridden = true;
                } else {
                    this.isOverridden = false;
                }
                this.selectedPriority = priority;
            },

            get bpClass() {
                if (!this.bpSys || !this.bpDia) return 'bg-slate-50 border-slate-200 dark:bg-zink-600 dark:border-zink-500';
                if (this.bpSys > 180 || this.bpDia > 120) return 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400';
                if (this.bpSys > 140 || this.bpDia > 90)  return 'bg-yellow-50 border-yellow-200 text-yellow-600 dark:bg-yellow-500/10 dark:border-yellow-500/20 dark:text-yellow-400';
                return 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400';
            },

            get bpLabel() {
                if (!this.bpSys || !this.bpDia) return 'Enter blood pressure readings';
                if (this.bpSys > 180 || this.bpDia > 120) return 'CRITICAL — Immediate attention required';
                if (this.bpSys > 140 || this.bpDia > 90)  return 'HIGH — Monitor closely';
                return 'NORMAL — Within healthy range';
            }
        };
    }
    </script>

</body>
</html>