<?php
require_once '../config/config.php';

 $order_id = (int)($_GET['order_id'] ?? 0);

if (empty($order_id)) {
    setFlash('error', 'No order ID provided');
    header("Location: index.php");
    exit;
}

// Get scan order + visit + patient info
 $stmt = $conn->prepare("
    SELECT so.*, v.visit_id, v.token_number, v.status as visit_status, v.id as visit_db_id,
           p.name as patient_name, p.age, p.gender, p.patient_id, p.phone, p.blood_group,
           d.name as department_name, doc.name as doctor_name,
           c.diagnosis, c.chief_complaint
    FROM scan_orders so
    JOIN visits v ON so.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE so.id = ?
");
 $stmt->bind_param("i", $order_id);
 $stmt->execute();
 $order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    setFlash('error', 'Scan order not found');
    header("Location: index.php");
    exit;
}

// Check if results already entered
 $existing_result = $conn->prepare("SELECT * FROM scan_results WHERE scan_order_id = ?");
 $existing_result->bind_param("i", $order_id);
 $existing_result->execute();
 $existing = $existing_result->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $findings = trim($_POST['findings'] ?? '');
    $impression = trim($_POST['impression'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($findings)) {
        $error = "Findings are required";
    } else {
        if ($existing) {
            $stmt = $conn->prepare("UPDATE scan_results SET findings = ?, impression = ?, remarks = ? WHERE scan_order_id = ?");
            $stmt->bind_param("sssi", $findings, $impression, $remarks, $order_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO scan_results (scan_order_id, findings, impression, remarks) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $order_id, $findings, $impression, $remarks);
        }
        
        if ($stmt->execute()) {
            $conn->query("UPDATE scan_orders SET status = 'completed' WHERE id = $order_id");
            
            // Check if all scans and labs for this visit are done
            $visit_db_id = $order['visit_db_id'];
            $pending_scans = $conn->query("
                SELECT COUNT(*) as cnt FROM scan_orders 
                WHERE visit_id = $visit_db_id AND status IN ('ordered', 'scheduled')
            ")->fetch_assoc()['cnt'];
            
            $pending_labs = $conn->query("
                SELECT COUNT(*) as cnt FROM lab_orders 
                WHERE visit_id = $visit_db_id AND status IN ('ordered', 'collected')
            ")->fetch_assoc()['cnt'];
            
            if ($pending_scans == 0 && $pending_labs == 0) {
                $current_status = $order['visit_status'];
                if ($current_status === 'lab') {
                    $conn->query("UPDATE visits SET status = 'pharmacy' WHERE id = $visit_db_id");
                } else {
                    $conn->query("UPDATE visits SET status = 'consulting' WHERE id = $visit_db_id");
                }
            }
            
            setFlash('success', 'Scan results saved successfully');
            header("Location: index.php");
            exit;
        } else {
            $error = "Failed to save results";
        }
    }
}

 $flash = getFlash();

// Scan type icons
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
 $scan_icon = $scan_icons[$order['scan_type']] ?? 'image';
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Enter Scan Results | MediFlow OPD</title>
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
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto" style="max-width: 900px;">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="flex items-center gap-3 grow">
                    <a href="index.php" class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-zink-200 hover:text-slate-700 dark:hover:text-zink-100 transition-colors">
                        <i data-lucide="arrow-left" class="size-4"></i> Back to Queue
                    </a>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Scan</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Enter Results</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="mb-4 px-4 py-3 rounded-md border bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
                <div class="flex items-center gap-2"><i data-lucide="alert-circle" class="size-5 shrink-0"></i><span><?= $error ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Existing Results Banner -->
            <?php if ($existing): ?>
            <div class="mb-4 px-4 py-3 rounded-md border bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="size-5 shrink-0"></i>
                    <span>Results already entered on <?= date('M d, Y h:i A', strtotime($existing['entered_at'])) ?> — you can update below</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Patient Info Card -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center size-14 rounded-lg bg-purple-100 dark:bg-purple-500/20 shrink-0">
                            <i data-lucide="<?= $scan_icon ?>" class="size-7 text-purple-500"></i>
                        </div>
                        <div class="grow">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h6 class="text-15 mb-0"><?= e($order['patient_name']) ?></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200"><?= $order['age'] ?>y, <?= $order['gender'] ?> · <span class="font-mono"><?= e($order['patient_id']) ?></span></p>
                                </div>
                                <span class="text-lg font-mono font-bold text-custom-500"><?= str_pad($order['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-3 border-t border-slate-200 dark:border-zink-600">
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Scan Type</p>
                                    <p class="text-sm font-medium mt-0.5"><?= e($order['scan_type']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Body Part</p>
                                    <p class="text-sm font-medium mt-0.5"><?= e($order['body_part']) ?: 'N/A' ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Complaint</p>
                                    <p class="text-sm font-medium mt-0.5 truncate"><?= e($order['chief_complaint']) ?: 'N/A' ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Diagnosis</p>
                                    <p class="text-sm font-medium mt-0.5 truncate"><?= e($order['diagnosis']) ?: 'N/A' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Form -->
            <form method="POST">
                <!-- Findings -->
                <div class="card mb-5">
                    <div class="card-body">
                        <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-2">
                            Findings <span class="text-red-500">*</span>
                        </label>
                        <textarea name="findings" required rows="12" id="findingsArea"
                                  class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200 font-mono text-sm resize-y"
                                  placeholder="Enter detailed scan findings...

Example for X-Ray Chest PA View:
================================
Heart size: Normal
Cardiothoracic ratio: 0.45
Lung fields: Clear bilaterally
Costophrenic angles: Sharp
Pleural effusion: None
Bony structures: No abnormality detected

Impression: Normal chest X-ray"><?= $existing['findings'] ?? '' ?></textarea>
                        <p class="text-xs text-slate-400 dark:text-zink-300 mt-2">
                            Character count: <span id="charCount"><?= strlen($existing['findings'] ?? '') ?></span>
                        </p>
                    </div>
                </div>

                <!-- Impression -->
                <div class="card mb-5">
                    <div class="card-body">
                        <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-2">Impression / Conclusion</label>
                        <textarea name="impression" rows="3"
                                  class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                  placeholder="E.g., No significant abnormality detected"><?= $existing['impression'] ?? '' ?></textarea>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="card mb-5">
                    <div class="card-body">
                        <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-2">Remarks</label>
                        <textarea name="remarks" rows="2"
                                  class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                  placeholder="Any additional notes..."><?= $existing['remarks'] ?? '' ?></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-purple-500 hover:bg-purple-600 rounded-md transition-colors">
                        <i data-lucide="save" class="size-4"></i> Save Results
                    </button>
                    <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                        Cancel
                    </a>
                </div>
            </form>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Character count
        const findingsArea = document.getElementById('findingsArea');
        const charCount = document.getElementById('charCount');
        if (findingsArea && charCount) {
            findingsArea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
    });
    </script>

</body>
</html>