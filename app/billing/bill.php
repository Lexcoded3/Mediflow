<?php
require_once '../config/config.php';

 $visit_id = $_GET['visit_id'] ?? '';

if (empty($visit_id)) {
    setFlash('error', 'No visit ID provided');
    header("Location: index.php");
    exit;
}

// Get visit + patient
 $stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.age, p.gender, p.blood_group, p.address, p.patient_id,
           d.name as department_name, d.id as department_id, doc.name as doctor_name, doc.id as doctor_id,
           c.diagnosis, c.consulted_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE v.visit_id = ? AND v.status = 'completed'
");
 $stmt->bind_param("s", $visit_id);
 $stmt->execute();
 $visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    setFlash('error', 'Visit not found or not ready for billing');
    header("Location: index.php");
    exit;
}

// Check if already billed
 $existing_bill = $conn->prepare("SELECT * FROM bills WHERE visit_id = ?");
 $existing_bill->bind_param("i", $visit['id']);
 $existing_bill->execute();
 $bill = $existing_bill->get_result()->fetch_assoc();

// Get fee structure
 $consultation_fee = $conn->query("
    SELECT fee FROM doctor_fees 
    WHERE doctor_id = {$visit['doctor_id']} AND type = 'opd'
")->fetch_assoc()['fee'] ?? 500;

 $registration_fee = 100; // Default

// Get lab tests count and total
 $lab_tests = $conn->query("
    SELECT test_name FROM lab_orders 
    WHERE visit_id = {$visit['id']} AND status = 'completed'
");
 $lab_count = $lab_tests->num_rows;
 $lab_fee_per_test = 200; // Default
 $lab_total = $lab_count * $lab_fee_per_test;

// Get scans count and total
 $scans = $conn->query("
    SELECT scan_type FROM scans 
    WHERE visit_id = {$visit['id']} AND status = 'completed'
");
 $scan_count = $scans->num_rows;
 $scan_fees = [
    'X-Ray' => 300,
    'CT Scan' => 2000,
    'MRI' => 3500,
    'Ultrasound' => 800,
    'ECG' => 200,
    'EEG' => 500,
    'Echo' => 600,
    'DEXA' => 1500,
    'Mammography' => 1200,
    'Fluoroscopy' => 400,
];
 $scan_total = 0;
while ($s = $scans->fetch_assoc()) {
    $scan_total += $scan_fees[$s['scan_type']] ?? 500;
}

$med_count = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM prescription_items pi
    JOIN prescriptions p ON p.id = pi.prescription_id
    JOIN consultations c ON c.id = p.consultation_id
    WHERE c.visit_id = {$visit['id']}
")->fetch_assoc()['cnt'] ?? 0;

$medicine_fee = $med_count * 50;

// Calculate totals
$subtotal = $registration_fee + $consultation_fee + $lab_total + $scan_total + $medicine_fee;
$discount = 0;
$tax = 0;
$total = $subtotal - $discount + $tax;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration_fee = (float)($_POST['registration_fee'] ?? 0);
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $lab_total = (float)($_POST['lab_total'] ?? 0);
    $scan_total = (float)($_POST['scan_total'] ?? 0);
    $medicine_fee = (float)($_POST['medicine_fee'] ?? 0);
    $other_fee = (float)($_POST['other_fee'] ?? 0);
    $other_desc = trim($_POST['other_desc'] ?? '');
    $discount = (float)($_POST['discount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_status = $_POST['payment_status'] ?? 'paid';
    $notes = trim($_POST['notes'] ?? '');
    
    $subtotal = $registration_fee + $consultation_fee + $lab_total + $scan_total + $medicine_fee + $other_fee;
    $total = $subtotal - $discount;
    
    // Generate bill number
    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($conn->query("SELECT COALESCE(MAX(id),0)+1 as next_id FROM bills")->fetch_assoc()['next_id'], 4, '0', STR_PAD_LEFT);
    
    if ($bill) {
        // Update existing bill
        $stmt = $conn->prepare("
            UPDATE bills SET 
                registration_fee = ?, consultation_fee = ?, lab_fee = ?, scan_fee = ?, medicine_fee = ?,
                other_fee = ?, other_description = ?, subtotal = ?, discount = ?, total_amount = ?,
                payment_method = ?, payment_status = ?, notes = ?, updated_at = NOW()
            WHERE visit_id = ?
        ");
        $stmt->bind_param("ddddddsdssssi", 
            $registration_fee, $consultation_fee, $lab_total, $scan_total, $medicine_fee,
            $other_fee, $other_desc, $subtotal, $discount, $total,
            $payment_method, $payment_status, $notes, $visit['id']
        );
        $bill_number = $bill['bill_number'];
    } else {
        // Insert new bill
        $stmt = $conn->prepare("
            INSERT INTO bills (bill_number, visit_id, registration_fee, consultation_fee, lab_fee, scan_fee, medicine_fee,
                               other_fee, other_description, subtotal, discount, total_amount, payment_method, payment_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sidddddddsdssss", 
            $bill_number, $visit['id'], $registration_fee, $consultation_fee, $lab_total, $scan_total, $medicine_fee,
            $other_fee, $other_desc, $subtotal, $discount, $total,
            $payment_method, $payment_status, $notes
        );
    }
    
    if ($stmt->execute()) {
        // Update visit status to closed
        $conn->query("UPDATE visits SET status = 'closed' WHERE id = " . $visit['id']);
        
        setFlash('success', 'Bill generated successfully');
        header("Location: receipt.php?bill_number=" . urlencode($bill_number));
        exit;
    } else {
        $error = "Failed to generate bill";
    }
}

// If bill exists, load its values
if ($bill) {
    $registration_fee = $bill['registration_fee'];
    $consultation_fee = $bill['consultation_fee'];
    $lab_total = $bill['lab_fee'];
    $scan_total = $bill['scan_fee'];
    $medicine_fee = $bill['medicine_fee'];
    $other_fee = $bill['other_fee'] ?? 0;
    $other_desc = $bill['other_description'] ?? '';
    $discount = $bill['discount'];
    $total = $bill['total_amount'];
    $bill_number = $bill['bill_number'];
}

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Generate Bill | MediFlow OPD</title>
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
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Billing</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Generate Bill</li>
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

            <!-- Already Billed Banner -->
            <?php if ($bill): ?>
            <div class="mb-4 px-4 py-3 rounded-md border bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-500/10 dark:border-blue-500/20 dark:text-blue-400">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="info" class="size-5 shrink-0"></i>
                        <span>Bill already generated: <strong><?= e($bill['bill_number']) ?></strong></span>
                    </div>
                    <a href="receipt.php?bill_number=<?= urlencode($bill['bill_number']) ?>" class="inline-flex items-center gap-1 text-xs font-medium hover:underline">
                        <i data-lucide="printer" class="size-3"></i> View Receipt
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Patient Info Card -->
            <div class="card mb-5 mt-4">
                <div class="card-body">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center size-14 rounded-lg bg-custom-100 dark:bg-custom-500/20 shrink-0">
                            <i data-lucide="user" class="size-7 text-custom-500"></i>
                        </div>
                        <div class="grow">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h6 class="text-15 mb-0"><?= e($visit['patient_name']) ?></h6>
                                    <p class="text-xs text-slate-500 dark:text-zink-200"><?= $visit['age'] ?>y, <?= $visit['gender'] ?> · <span class="font-mono"><?= e($visit['patient_id']) ?></span></p>
                                </div>
                                <span class="text-lg font-mono font-bold text-custom-500"><?= str_pad($visit['token_number'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4 pt-3 border-t border-slate-200 dark:border-zink-600">
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Doctor</p>
                                    <p class="text-sm font-medium mt-0.5"><?= e($visit['doctor_name']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Department</p>
                                    <p class="text-sm font-medium mt-0.5"><?= e($visit['department_name']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] uppercase text-slate-400 dark:text-zink-300 font-medium">Diagnosis</p>
                                    <p class="text-sm font-medium mt-0.5 truncate"><?= e($visit['diagnosis']) ?: 'N/A' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Billing Form -->
            <form method="POST" id="billForm">
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="list" class="size-5 text-custom-500"></i>
                            <h6 class="text-15 mb-0">Fee Breakdown</h6>
                        </div>

                        <div class="flex flex-col gap-3">
                          <!-- Registration Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="clipboard-list" class="size-4 text-slate-400"></i>
                                        <span class="text-sm text-slate-600 dark:text-zink-200">Registration Fee</span>
                                    </div>
                                    <input type="number" name="registration_fee" value="<?= $registration_fee ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>

                                <!-- Consultation Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="stethoscope" class="size-4 text-blue-400"></i>
                                        <span class="text-sm text-slate-600 dark:text-zink-200">Consultation Fee</span>
                                        <span class="text-xs text-slate-400 dark:text-zink-400">(Dr. <?= e($visit['doctor_name']) ?>)</span>
                                    </div>
                                    <input type="number" name="consultation_fee" value="<?= $consultation_fee ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>

                                <!-- Lab Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="test-tube" class="size-4 text-sky-500"></i>
                                        <span class="text-sm text-slate-600 dark:text-zink-200">Lab Tests</span>
                                        <span class="text-xs text-slate-400 dark:text-zink-400">(<?= $lab_count ?> test<?= $lab_count != 1 ? 's' : '' ?>)</span>
                                    </div>
                                    <input type="number" name="lab_total" value="<?= $lab_total ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>

                                <!-- Scan Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="scan" class="size-4 text-purple-500"></i>
                                        <span class="text-sm text-slate-600 dark:text-zink-200">Scans</span>
                                        <span class="text-xs text-slate-400 dark:text-zink-400">(<?= $scan_count ?> scan<?= $scan_count != 1 ? 's' : '' ?>)</span>
                                    </div>
                                    <input type="number" name="scan_total" value="<?= $scan_total ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>

                                <!-- Medicine Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="pill" class="size-4 text-green-500"></i>
                                        <span class="text-sm text-slate-600 dark:text-zink-200">Medicine Dispensing</span>
                                        <span class="text-xs text-slate-400 dark:text-zink-400">(<?= $med_count ?> item<?= $med_count != 1 ? 's' : '' ?>)</span>
                                    </div>
                                    <input type="number" name="medicine_fee" value="<?= $medicine_fee ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>

                                <!-- Other Fee -->
                                <div class="flex items-center justify-between p-3 rounded-md bg-slate-50 dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="plus-circle" class="size-4 text-orange-400"></i>
                                        <input type="text" name="other_desc" value="<?= $other_desc ?? '' ?>" placeholder="Other charges description..."
                                               class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5 w-48">
                                    </div>
                                    <input type="number" name="other_fee" value="<?= $other_fee ?? 0 ?>" min="0" step="0.01"
                                           class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-600 text-sm py-1.5">
                                </div>
                        </div>

                        <!-- Totals -->
                        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-zink-600 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-500 dark:text-zink-200">Subtotal</span>
                                <span id="subtotalDisplay" class="font-medium text-slate-700 dark:text-zink-100"><?= number_format($subtotal, 2) ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-500 dark:text-zink-200">Discount</span>
                                </div>
                                <input type="number" name="discount" value="<?= $discount ?>" min="0" step="0.01"
                                       class="w-28 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-sm py-1.5">
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-2 border-t border-slate-200 dark:border-zink-600">
                                <span class="text-slate-700 dark:text-zink-100">Total</span>
                                <span id="totalDisplay" class="text-custom-500"><?= number_format($total, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="credit-card" class="size-5 text-custom-500"></i>
                            <h6 class="text-15 mb-0">Payment Details</h6>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Payment Method</label>
                                <select name="payment_method" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="cash" <?= ($bill['payment_method'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="card" <?= ($bill['payment_method'] ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
                                    <option value="upi" <?= ($bill['payment_method'] ?? '') === 'upi' ? 'selected' : '' ?>>UPI</option>
                                    <option value="online" <?= ($bill['payment_method'] ?? '') === 'online' ? 'selected' : '' ?>>Online Transfer</option>
                                    <option value="insurance" <?= ($bill['payment_method'] ?? '') === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Payment Status</label>
                                <select name="payment_status" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="paid" <?= ($bill['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="pending" <?= ($bill['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="partial" <?= ($bill['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>>Partial</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                      placeholder="Any additional notes..."><?= $bill['notes'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <button type="submit" name="action" value="save" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-md transition-colors">
                        <i data-lucide="check" class="size-4"></i> Save & Generate Receipt
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

        // Auto-calculate totals
        const form = document.getElementById('billForm');
        if (form) {
            const inputs = form.querySelectorAll('input[name$="_fee"], input[name="discount"]');
            inputs.forEach(input => {
                input.addEventListener('input', calculateTotal);
            });

            function calculateTotal() {
                let subtotal = 0;
                ['registration_fee', 'consultation_fee', 'lab_total', 'scan_total', 'medicine_fee', 'other_fee'].forEach(name => {
                    const val = parseFloat(form.querySelector(`[name="${name}"]`).value) || 0;
                    subtotal += val;
                });
                const discount = parseFloat(form.querySelector('[name="discount"]').value) || 0;
                const total = subtotal - discount;

                document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
                document.getElementById('totalDisplay').textContent = total.toFixed(2);
            }
        }
    });
    </script>

</body>
</html>