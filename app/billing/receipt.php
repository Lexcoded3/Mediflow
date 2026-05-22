<?php
require_once '../config/config.php';

 $bill_number = $_GET['bill_number'] ?? '';

if (empty($bill_number)) {
    setFlash('error', 'No bill number provided');
    header("Location: index.php");
    exit;
}

// Get bill details
 $stmt = $conn->prepare("
    SELECT b.*, v.visit_id, v.token_number, v.visit_date,
           p.name as patient_name, p.phone, p.age, p.gender, p.patient_id, p.address,
           d.name as department_name, doc.name as doctor_name,
           c.diagnosis
    FROM bills b
    JOIN visits v ON b.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE b.bill_number = ?
");
 $stmt->bind_param("s", $bill_number);
 $stmt->execute();
 $bill = $stmt->get_result()->fetch_assoc();

if (!$bill) {
    setFlash('error', 'Bill not found');
    header("Location: index.php");
    exit;
}

// Hospital info (can be from config/settings)
 $hospital_name = 'MediFlow Hospital';
 $hospital_address = '123 Healthcare Avenue, Medical City';
 $hospital_phone = '+1 234 567 890';
 $hospital_logo = '../assets/images/logo.png';

 $payment_icons = [
    'cash' => 'banknote',
    'card' => 'credit-card',
    'upi' => 'smartphone',
    'online' => 'landmark',
    'insurance' => 'shield-check',
];

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Receipt - <?= e($bill_number) ?> | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        @media print {
            body { background: white !important; }
            .no-print { display: none !important; }
            .print-area { 
                box-shadow: none !important; 
                border: none !important;
                margin: 0 !important;
                padding: 20px !important;
            }
        }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Print Actions -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center no-print">
                <div class="flex items-center gap-3 grow">
                    <a href="index.php" class="inline-flex items-center gap-1 text-sm text-slate-500 dark:text-zink-200 hover:text-slate-700 dark:hover:text-zink-100 transition-colors">
                        <i data-lucide="arrow-left" class="size-4"></i> Back to Billing
                    </a>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                        <i data-lucide="printer" class="size-4"></i> Print Receipt
                    </button>
                </div>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border no-print <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Receipt -->
            <div class="print-area max-w-[700px] mx-auto bg-white dark:bg-zink-800 rounded-lg border border-slate-200 dark:border-zink-600 shadow-sm">
                
                <!-- Header -->
                <div class="p-6 border-b border-slate-200 dark:border-zink-600">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800 dark:text-zink-100"><?= $hospital_name ?></h2>
                            <p class="text-sm text-slate-500 dark:text-zink-300 mt-1"><?= $hospital_address ?></p>
                            <p class="text-sm text-slate-500 dark:text-zink-300"><?= $hospital_phone ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-custom-500">RECEIPT</p>
                            <p class="text-sm text-slate-500 dark:text-zink-300 font-mono"><?= e($bill_number) ?></p>
                            <p class="text-xs text-slate-400 dark:text-zink-300 mt-1"><?= date('M d, Y h:i A', strtotime($bill['created_at'])) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Patient Info -->
                <div class="p-6 border-b border-slate-200 dark:border-zink-600">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Patient Name</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100"><?= e($bill['patient_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Patient ID</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100 font-mono"><?= e($bill['patient_id']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Token Number</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100 font-mono"><?= str_pad($bill['token_number'], 3, '0', STR_PAD_LEFT) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Visit Date</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100"><?= date('M d, Y', strtotime($bill['visit_date'])) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Doctor</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100">Dr. <?= e($bill['doctor_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Department</p>
                            <p class="font-medium text-slate-700 dark:text-zink-100"><?= e($bill['department_name']) ?></p>
                        </div>
                    </div>
                    <?php if ($bill['diagnosis']): ?>
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zink-700">
                        <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Diagnosis</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($bill['diagnosis']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Fee Breakdown -->
                <div class="p-6 border-b border-slate-200 dark:border-zink-600">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-zink-600">
                                <th class="pb-2 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Description</th>
                                <th class="pb-2 text-right text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zink-700">
                            <?php if ($bill['registration_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200">Registration Fee</td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['registration_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($bill['consultation_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200">Consultation Fee</td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['consultation_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($bill['lab_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200">Laboratory Tests</td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['lab_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($bill['scan_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200">Diagnostic Scans</td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['scan_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($bill['medicine_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200">Medicine Dispensing</td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['medicine_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($bill['other_fee'] > 0): ?>
                            <tr>
                                <td class="py-2.5 text-sm text-slate-600 dark:text-zink-200"><?= e($bill['other_description'] ?: 'Other Charges') ?></td>
                                <td class="py-2.5 text-sm text-slate-700 dark:text-zink-100 text-right font-mono"><?= number_format($bill['other_fee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="mt-4 pt-4 border-t border-slate-200 dark:border-zink-600">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500 dark:text-zink-200">Subtotal</span>
                            <span class="text-slate-700 dark:text-zink-100 font-mono"><?= number_format($bill['subtotal'], 2) ?></span>
                        </div>
                        <?php if ($bill['discount'] > 0): ?>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500 dark:text-zink-200">Discount</span>
                            <span class="text-green-600 dark:text-green-400 font-mono">-<?= number_format($bill['discount'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-lg font-bold pt-2 border-t border-slate-200 dark:border-zink-600">
                            <span class="text-slate-800 dark:text-zink-100">Total</span>
                            <span class="text-custom-500 font-mono"><?= number_format($bill['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="p-6 border-b border-slate-200 dark:border-zink-600">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Payment Method</p>
                            <div class="flex items-center gap-1.5 mt-1">
                                <i data-lucide="<?= $payment_icons[$bill['payment_method']] ?? 'credit-card' ?>" class="size-4 text-slate-500 dark:text-zink-300"></i>
                                <span class="font-medium text-slate-700 dark:text-zink-100 capitalize"><?= e($bill['payment_method']) ?></span>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 dark:text-zink-300 uppercase">Payment Status</p>
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded mt-1 <?= $bill['payment_status'] === 'paid' ? 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400' : ($bill['payment_status'] === 'pending' ? 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400' : 'bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400') ?>">
                                <?= ucfirst($bill['payment_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-6">
                    <div class="flex justify-between items-end">
                        <div class="text-xs text-slate-400 dark:text-zink-300">
                            <p>Thank you for visiting <?= $hospital_name ?></p>
                            <p class="mt-1">This is a computer-generated receipt.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-40 border-t border-slate-300 dark:border-zink-500 pt-1">
                                <p class="text-xs text-slate-500 dark:text-zink-300">Authorized Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    </script>

</body>
</html>