<?php
require_once '../config/config.php';

$visit_id = $_GET['visit_id'] ?? '';

if (empty($visit_id)) {
    setFlash('error', 'No visit ID provided');
    header("Location: index.php");
    exit;
}

// 1. Get full visit data
$stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.email, p.dob, p.age, p.gender, p.blood_group, p.address, p.patient_id,
           d.name as department_name, d.id as department_id, doc.name as doctor_name, doc.id as doctor_id,
           t.priority, t.bp_systolic, t.bp_diastolic, t.pulse, t.temperature, t.spo2, t.weight, t.chief_complaint as triage_complaint, t.notes as triage_notes
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    WHERE v.visit_id = ?
");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visit) {
    setFlash('error', 'Visit not found');
    header("Location: index.php");
    exit;
}

// 2. Get existing consultation
$stmt = $conn->prepare("SELECT * FROM consultations WHERE visit_id = ?");
$stmt->bind_param("i", $visit['id']);
$stmt->execute();
$consultation = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Get lab orders
$stmt = $conn->prepare("
    SELECT lo.*, lr.results, lr.remarks,
           CASE WHEN lr.results IS NOT NULL THEN 'completed' ELSE lo.status END as display_status
    FROM lab_orders lo
    LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE lo.visit_id = ?
    ORDER BY lo.ordered_at DESC
");
$stmt->bind_param("i", $visit['id']);
$stmt->execute();
$lab_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4. Get existing scans
$stmt = $conn->prepare("SELECT * FROM scans WHERE visit_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $visit['id']);
$stmt->execute();
$scans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 5. Get existing prescription
$stmt_rx = $conn->prepare("
    SELECT pr.*, 
           GROUP_CONCAT(pi.medicine_name SEPARATOR '|||') as medicine_list,
           GROUP_CONCAT(pi.dosage SEPARATOR '|||') as dosage_list,
           GROUP_CONCAT(pi.frequency SEPARATOR '|||') as frequency_list,
           GROUP_CONCAT(pi.duration SEPARATOR '|||') as duration_list,
           GROUP_CONCAT(COALESCE(pi.instructions,'') SEPARATOR '|||') as instructions_list
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pi.prescription_id = pr.id
    WHERE pr.consultation_id = ?
    GROUP BY pr.id
");
$consult_id = $consultation['id'] ?? 0;
$stmt_rx->bind_param("i", $consult_id);
$stmt_rx->execute();
$rx = $stmt_rx->get_result()->fetch_assoc();
$stmt_rx->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $history = trim($_POST['history'] ?? '');
    $examination = trim($_POST['examination'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $follow_up_date = trim($_POST['follow_up_date'] ?? '') ?: null;
    $follow_up_notes = trim($_POST['follow_up_notes'] ?? '');
    $action_type = $_POST['action_type'] ?? 'save';
    
    $errors = [];
    if (empty($diagnosis)) $errors[] = "Diagnosis is required";
    
    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        header("Location: consult.php?visit_id=" . urlencode($visit_id));
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Insert or update consultation
        if ($consultation) {
            $stmt = $conn->prepare("
                UPDATE consultations SET 
                    chief_complaint = ?, history = ?, examination = ?, diagnosis = ?, notes = ?,
                    follow_up_date = ?, follow_up_notes = ?, consulted_at = NOW()
                WHERE visit_id = ?
            ");
            $stmt->bind_param("sssssssi", $chief_complaint, $history, $examination, $diagnosis, $notes, $follow_up_date, $follow_up_notes, $visit['id']);
            $stmt->execute();
            $consultation_id = $consultation['id'];
        } else {
            $stmt = $conn->prepare("
                INSERT INTO consultations (visit_id, doctor_id, chief_complaint, history, examination, diagnosis, notes, follow_up_date, follow_up_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $doc_id = $visit['doctor_id'] ?: null;
            $stmt->bind_param("iisssssss", 
                $visit['id'], $doc_id, $chief_complaint, $history, 
                $examination, $diagnosis, $notes, $follow_up_date, $follow_up_notes
            );
            $stmt->execute();
            $consultation_id = $conn->insert_id;
        }
        
        // Handle prescription — medicines[] is serialized as JSON via hidden input
        $medicines_json = trim($_POST['medicines_json'] ?? '[]');
        $medicines = json_decode($medicines_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $medicines = [];
        }

        // Filter out completely empty rows
        $medicines = array_filter($medicines, fn($m) => !empty(trim($m['name'] ?? '')));

        // Always replace prescription (even if empty, to clear old data)
        $conn->query("DELETE FROM prescription_items WHERE prescription_id IN (SELECT id FROM prescriptions WHERE consultation_id = $consultation_id)");
        $conn->query("DELETE FROM prescriptions WHERE consultation_id = $consultation_id");

        if (!empty($medicines)) {
            $rx_notes = trim($_POST['rx_notes'] ?? '');
            $stmt = $conn->prepare("INSERT INTO prescriptions (consultation_id, notes) VALUES (?, ?)");
            $stmt->bind_param("is", $consultation_id, $rx_notes);
            $stmt->execute();
            $prescription_id = $conn->insert_id;
            
            $stmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($medicines as $med) {
                $stmt->bind_param("isssss", 
                    $prescription_id,
                    trim($med['name'] ?? ''),
                    trim($med['dosage'] ?? ''),
                    trim($med['frequency'] ?? ''),
                    trim($med['duration'] ?? ''),
                    trim($med['instructions'] ?? '')
                );
                $stmt->execute();
            }
        }
        
        // Determine next status
        $has_pending_labs = $conn->query("SELECT COUNT(*) as cnt FROM lab_orders WHERE visit_id = {$visit['id']} AND status != 'completed'")->fetch_assoc()['cnt'];
        
        if ($action_type === 'lab' && $has_pending_labs > 0) {
            $new_status = 'lab';
        } else {
            $new_status = 'pharmacy';
        }
        
        $stmt = $conn->prepare("UPDATE visits SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $visit['id']);
        $stmt->execute();
        
        $conn->commit();
        
        if ($new_status === 'lab') {
            setFlash('success', 'Consultation saved. Patient sent to Lab.');
            header("Location: index.php");
        } else {
            setFlash('success', 'Consultation saved. Patient sent to Pharmacy.');
            header("Location: prescribe.php?visit_id=" . urlencode($visit_id));
        }
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', 'Save failed: ' . $e->getMessage());
        header("Location: consult.php?visit_id=" . urlencode($visit_id));
        exit;
    }
}

$flash = getFlash();

// Common lab tests for quick-add
$common_tests = ['CBC', 'Blood Sugar (Fasting)', 'Blood Sugar (Random)', 'Liver Function Test (LFT)', 'Kidney Function Test (KFT)', 'Urine Routine', 'Lipid Profile', 'Thyroid Profile', 'X-Ray Chest PA', 'ECG'];
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Consult Patient | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        [x-cloak] { display: none !important; }
        .vital-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
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
                    <h5 class="text-16">Consultation</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Queue</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Patient</li>
                </ul>
            </div>

            <!-- Flash -->
            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="consult.php?visit_id=<?= urlencode($visit_id) ?>"
                  x-data="consultForm(<?= $visit['id'] ?>, <?= htmlspecialchars(json_encode($common_tests), ENT_QUOTES) ?>)"
                  x-cloak
                  @submit="serializeMedicines">

                <!-- Hidden input to carry medicines JSON on submit -->
                <input type="hidden" name="medicines_json" x-ref="medicinesJson">

                <!-- Patient Info Bar -->
                <div class="flex flex-wrap items-center gap-4 p-4 mb-5 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                    <div class="flex items-center justify-center size-14 rounded-full bg-custom-100 dark:bg-custom-500/20 shrink-0">
                        <span class="text-xl font-bold text-custom-500"><?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="grow">
                        <h5 class="text-base font-semibold"><?= e($visit['patient_name']) ?></h5>
                        <p class="text-sm text-slate-500 dark:text-zink-200"><?= e($visit['patient_id']) ?> &middot; <?= e($visit['age']) ?>y &middot; <?= e($visit['gender']) ?> &middot; <?= e($visit['blood_group'] ?: 'N/A') ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($visit['bp_systolic']): ?>
                        <span class="vital-chip bg-slate-200 dark:bg-zink-500 text-slate-600 dark:text-zink-200">BP: <?= $visit['bp_systolic'] ?>/<?= $visit['bp_diastolic'] ?></span>
                        <?php endif; ?>
                        <?php if ($visit['temperature']): ?>
                        <span class="vital-chip <?= $visit['temperature'] > 37.5 ? 'bg-red-100 text-red-600' : 'bg-slate-200 text-slate-600 dark:bg-zink-500 dark:text-zink-200' ?>">Temp: <?= $visit['temperature'] ?>°C</span>
                        <?php endif; ?>
                        <?php if ($visit['pulse']): ?>
                        <span class="vital-chip bg-slate-200 dark:bg-zink-500 text-slate-600 dark:text-zink-200">Pulse: <?= $visit['pulse'] ?></span>
                        <?php endif; ?>
                        <?php if ($visit['spo2']): ?>
                        <span class="vital-chip bg-slate-200 dark:bg-zink-500 text-slate-600 dark:text-zink-200">SpO2: <?= $visit['spo2'] ?>%</span>
                        <?php endif; ?>
                        <?php if ($visit['weight']): ?>
                        <span class="vital-chip bg-slate-200 dark:bg-zink-500 text-slate-600 dark:text-zink-200">Wt: <?= $visit['weight'] ?>kg</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($visit['department_name']) ?></p>
                        <p class="text-sm font-medium"><?= e($visit['doctor_name'] ?? 'No doctor assigned') ?></p>
                    </div>
                </div>

                <!-- Chief Complaint from Triage -->
                <?php if ($visit['triage_complaint']): ?>
                <div class="mb-5 p-3 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20">
                    <p class="text-xs font-medium text-sky-500 mb-1">TRIAGE COMPLAINT</p>
                    <p class="text-sm"><?= e($visit['triage_complaint']) ?></p>
                </div>
                <?php endif; ?>

                <!-- Clinical Notes -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white shrink-0"><i data-lucide="clipboard-list" class="size-5"></i></div>
                            <div>
                                <h6 class="text-15 mb-0">Clinical Notes</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Chief complaint, history, and examination</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Chief Complaint</label>
                                <textarea name="chief_complaint" rows="2" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Patient's main complaint..."><?= $consultation ? e($consultation['chief_complaint']) : '' ?></textarea>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">History of Present Illness</label>
                                <textarea name="history" rows="3" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Onset, duration, severity, associated symptoms..."><?= $consultation ? e($consultation['history']) : '' ?></textarea>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Examination Findings</label>
                                <textarea name="examination" rows="3" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Physical examination findings..."><?= $consultation ? e($consultation['examination']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnosis -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-custom-500 text-white shrink-0"><i data-lucide="stethoscope" class="size-5"></i></div>
                            <div>
                                <h6 class="text-15 mb-0">Diagnosis <span class="text-red-500">*</span></h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Primary diagnosis and differential</p>
                            </div>
                        </div>
                        <textarea name="diagnosis" rows="3" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Primary diagnosis..."><?= $consultation ? e($consultation['diagnosis']) : '' ?></textarea>
                        <div>
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200 mt-4">Notes</label>
                            <textarea name="notes" rows="2" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Additional notes..."><?= $consultation ? e($consultation['notes']) : '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Lab Orders -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-full bg-purple-500 text-white shrink-0"><i data-lucide="flask-conical" class="size-5"></i></div>
                                <div>
                                    <h6 class="text-15 mb-0">Lab Orders <span class="text-slate-400 dark:text-zink-200 font-normal" x-text="'(' + labOrders.length + ')'"></span></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200">Order lab tests for this patient</p>
                                </div>
                            </div>
                            <button type="button" @click="showLabForm = !showLabForm" class="px-3 py-1.5 text-xs text-white rounded-md bg-purple-500 hover:bg-purple-600 transition-colors">
                                <i data-lucide="plus" class="inline-block size-3 ltr:mr-1 rtl:ml-1"></i> Add Test
                            </button>
                        </div>

                        <!-- Quick Add Lab Form -->
                        <div x-show="showLabForm" x-transition class="mb-4 p-4 rounded-md bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20">
                            <p class="text-xs font-medium text-purple-500 mb-2">QUICK ADD</p>
                            <div class="flex flex-wrap gap-2 mb-3">
                                <template x-for="test in commonTests" :key="test">
                                    <button type="button" @click="addLabOrder(test)" class="px-2.5 py-1 text-xs rounded-md border border-purple-200 dark:border-purple-500/30 text-purple-600 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-500/20 transition-colors" x-text="test"></button>
                                </template>
                            </div>
                            <div class="flex gap-2">
                                <input type="text" x-model="customLabTest" @keydown.enter.prevent="addLabOrder(customLabTest); customLabTest = ''" class="flex-1 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Or type custom test name...">
                                <button type="button" @click="addLabOrder(customLabTest); customLabTest = ''" :disabled="!customLabTest.trim()" class="px-3 py-1.5 text-xs text-white rounded-md bg-purple-500 hover:bg-purple-600 disabled:opacity-50 transition-colors">Add</button>
                            </div>
                        </div>

                        <!-- Lab Orders List -->
                        <div x-show="labOrders.length > 0">
                            <div class="flex flex-col gap-2">
                                <template x-for="(order, index) in labOrders" :key="order.id">
                                    <div class="flex items-center gap-3 p-3 rounded-md bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                        <span class="inline-flex items-center justify-center size-8 rounded bg-purple-100 dark:bg-purple-500/20 text-purple-500 text-xs font-bold shrink-0" x-text="index + 1"></span>
                                        <div class="grow min-w-0">
                                            <p class="text-sm font-medium truncate" x-text="order.test_name"></p>
                                            <p class="text-[10px]" :class="order.display_status === 'completed' ? 'text-green-500' : order.display_status === 'collected' ? 'text-sky-500' : 'text-slate-400'" x-text="'Status: ' + order.display_status"></p>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded" 
                                              :class="order.display_status === 'completed' ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'"
                                              x-text="order.display_status === 'completed' ? 'Results Ready' : order.display_status"></span>
                                        <button type="button" @click="removeLabOrder(index)" class="flex items-center justify-center size-7 rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                            <i data-lucide="x" class="size-3.5"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="labOrders.length === 0" class="py-4 text-center text-xs text-slate-400 dark:text-zink-300">
                            No lab tests ordered yet
                        </div>
                    </div>
                </div>

                <!-- Prescription -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-full bg-green-500 text-white shrink-0"><i data-lucide="pill" class="size-5"></i></div>
                                <div>
                                    <h6 class="text-15 mb-0">Prescription <span class="text-slate-400 dark:text-zink-200 font-normal" x-text="'(' + medicines.length + ')'"></span></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200">Add medicines to prescribe</p>
                                </div>
                            </div>
                            <button type="button" @click="addMedicine()" class="px-3 py-1.5 text-xs text-white rounded-md bg-green-500 hover:bg-green-600 transition-colors">
                                <i data-lucide="plus" class="inline-block size-3 ltr:mr-1 rtl:ml-1"></i> Add Medicine
                            </button>
                        </div>

                        <!-- Medicine Rows -->
                        <div x-show="medicines.length > 0" class="flex flex-col gap-3">
                            <template x-for="(med, index) in medicines" :key="index">
                                <div class="p-3 rounded-md border border-slate-200 dark:border-zink-500 bg-white dark:bg-zink-700">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium text-slate-400 dark:text-zink-300" x-text="'Medicine #' + (index + 1)"></span>
                                        <button type="button" @click="removeMedicine(index)" class="flex items-center justify-center size-6 rounded text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                                            <i data-lucide="trash-2" class="size-3"></i>
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                        <div class="md:col-span-2">
                                            <input type="text" x-model="med.name" placeholder="Medicine name" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                                        </div>
                                        <div>
                                            <input type="text" x-model="med.dosage" placeholder="e.g. 500mg" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                                        </div>
                                        <div>
                                            <input type="text" x-model="med.frequency" placeholder="e.g. 1+0+1" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                                        </div>
                                        <div>
                                            <input type="text" x-model="med.duration" placeholder="e.g. 5 days" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                                        </div>
                                        <div>
                                            <input type="text" x-model="med.instructions" placeholder="e.g. After food" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="medicines.length === 0" class="py-4 text-center text-xs text-slate-400 dark:text-zink-300">
                            No medicines added
                        </div>

                        <!-- Rx Notes -->
                        <div class="mt-3">
                            <input type="text" name="rx_notes" value="<?= $rx ? e($rx['notes']) : '' ?>" placeholder="e.g. Take all medicines as prescribed, complete the course" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm placeholder:text-slate-400 dark:placeholder:text-zink-200">
                        </div>
                    </div>
                </div>

                <!-- Follow-up -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex items-center justify-center size-10 rounded-full bg-orange-500 text-white shrink-0"><i data-lucide="calendar-check" class="size-5"></i></div>
                            <div>
                                <h6 class="text-15 mb-0">Follow-up</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Set follow-up date and instructions</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Follow-up Date</label>
                                <input type="date" name="follow_up_date" value="<?= $consultation ? e($consultation['follow_up_date']) : '' ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Follow-up Notes</label>
                                <input type="text" name="follow_up_notes" value="<?= $consultation ? e($consultation['follow_up_notes']) : '' ?>" placeholder="e.g. Review lab reports" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" name="action_type" value="pharmacy"
                            class="flex-1 px-4 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600">
                        <i data-lucide="check" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                        Save & Send to Pharmacy
                    </button>
                    <button type="submit" name="action_type" value="lab"
                            class="flex-1 px-4 py-2.5 text-white btn bg-purple-500 border-purple-500 hover:text-white hover:bg-purple-600 hover:border-purple-600 focus:text-white focus:bg-purple-600 focus:border-purple-600"
                            :disabled="labOrders.length === 0"
                            :class="labOrders.length === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                        <i data-lucide="flask-conical" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                        Save & Send to Lab
                    </button>
                    <a href="index.php" class="px-4 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 text-center sm:text-left">
                        Cancel
                    </a>
                </div>

            </form>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
    function consultForm(visitDbId, commonTests) {
        return {
            showLabForm: false,
            customLabTest: '',

            // commonTests is already a parsed array (passed via json_encode from PHP)
            commonTests: commonTests,

            labOrders: [],
            medicines: [],

            init() {
                this.loadLabOrders();
                this.loadExistingRx();
            },

            async loadLabOrders() {
                try {
                    const res = await fetch('actions.php?action=get_lab_orders&visit_id=<?= urlencode($visit_id) ?>');
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    if (data.success) {
                        this.labOrders = data.orders.map(o => ({ ...o }));
                    } else {
                        console.error('Lab orders error:', data.msg);
                    }
                } catch (e) {
                    console.error('Failed to load lab orders:', e);
                }
            },

            loadExistingRx() {
                <?php if ($rx && $rx['medicine_list']): ?>
                const names    = <?= json_encode(explode('|||', $rx['medicine_list'])) ?>;
                const dosages  = <?= json_encode(explode('|||', $rx['dosage_list'] ?? '')) ?>;
                const freqs    = <?= json_encode(explode('|||', $rx['frequency_list'] ?? '')) ?>;
                const durs     = <?= json_encode(explode('|||', $rx['duration_list'] ?? '')) ?>;
                const instrs   = <?= json_encode(explode('|||', $rx['instructions_list'] ?? '')) ?>;

                names.forEach((name, i) => {
                    this.medicines.push({
                        name:         name,
                        dosage:       dosages[i]  || '',
                        frequency:    freqs[i]    || '',
                        duration:     durs[i]     || '',
                        instructions: instrs[i]   || ''
                    });
                });
                <?php endif; ?>
            },

            // Serialize medicines array into the hidden input before the form submits
            serializeMedicines() {
                this.$refs.medicinesJson.value = JSON.stringify(this.medicines);
            },

            async addLabOrder(testName) {
                if (!testName.trim()) return;
                try {
                    const form = new FormData();
                    form.append('test_name', testName.trim());
                    const res = await fetch('actions.php?action=add_lab_order&visit_id=<?= urlencode($visit_id) ?>', { method: 'POST', body: form });
                    const data = await res.json();
                    if (data.success) {
                        this.labOrders.push({
                            id:             data.order_id,
                            test_name:      data.test_name,
                            status:         'ordered',
                            display_status: 'ordered'
                        });
                    } else {
                        alert(data.msg);
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            async removeLabOrder(index) {
                const order = this.labOrders[index];
                if (order.id) {
                    if (!confirm('Remove this lab order?')) return;
                    try {
                        await fetch('actions.php?action=remove_lab_order&id=' + order.id, { method: 'POST' });
                    } catch (e) {
                        console.error(e);
                    }
                }
                this.labOrders.splice(index, 1);
            },

            addMedicine() {
                this.medicines.push({ name: '', dosage: '', frequency: '', duration: '', instructions: '' });
            },

            removeMedicine(index) {
                this.medicines.splice(index, 1);
            }
        };
    }
    </script>

</body>
</html>