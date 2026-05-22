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
    SELECT v.*, p.name as patient_name, p.phone, p.age, p.gender, p.blood_group, p.patient_id, p.address,
           d.name as department_name, doc.name as doctor_name,
           c.chief_complaint, c.history, c.examination, c.diagnosis, c.notes as consult_notes, c.follow_up_date, c.follow_up_notes, c.consulted_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
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
    if ($row['medicine_name']) {
        $rx_items[] = $row;
    }
    $rx_notes = $row['rx_notes'];
}

// Get lab results for display
 $lab_results = $conn->prepare("
    SELECT lo.test_name, lr.results, lr.remarks, lr.entered_at
    FROM lab_orders lo
    JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE lo.visit_id = ? AND lo.status = 'completed'
    ORDER BY lo.ordered_at
");
 $lab_results->bind_param("s", $visit_id);
 $lab_results->execute();
 $lab_items = [];
while ($lr = $lab_results->get_result()->fetch_assoc()) {
    $lab_items[] = $lr;
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Prescription | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; }
            .card { border: none !important; box-shadow: none !important; }
        }
        .print-only { display: none; }
    </style>
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing(header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center no-print">
                <div class="grow">
                    <h5 class="text-16">Prescription</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Consultation</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Prescription</li>
                </ul>
            </div>

            <!-- Print Button -->
            <div class="mb-4 flex justify-end gap-2 no-print">
                <a href="consult.php?visit_id=<?= urlencode($visit_id) ?>" class="px-4 py-2 text-sm text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600">
                    <i data-lucide="arrow-left" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Back to Consult
                </a>
                <button onclick="window.print()" class="px-4 py-2 text-sm text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600">
                    <i data-lucide="printer" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Print
                </button>
            </div>

            <!-- Prescription Card -->
            <div class="card" id="prescription-card">
                <div class="card-body">

                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 mb-4 border-b-2 border-custom-500">
                        <div>
                            <h4 class="text-lg font-bold text-custom-500">MediFlow Hospital</h4>
                            <p class="text-xs text-slate-500">OPD Prescription</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>Print: <?= date('d M Y, h:i A') ?></p>
                        </div>
                    </div>

                    <!-- Patient Info -->
                    <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-dashed border-slate-300 dark:border-zink-500">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Patient</p>
                            <p class="text-sm font-semibold"><?= e($visit['patient_name']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($visit['patient_id']) ?> &middot; <?= e($visit['age']) ?>y &middot; <?= e($visit['gender']) ?> &middot; <?= e($visit['blood_group'] ?: 'N/A') ?></p>
                            <?php if ($visit['address']): ?>
                            <p class="text-xs text-slate-400"><?= e($visit['address']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Visit</p>
                            <p class="text-sm font-semibold"><?= e($visit['visit_id']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($visit['department_name']) ?></p>
                            <p class="text-xs text-slate-400"><?= e($visit['doctor_name'] ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <!-- Diagnosis -->
                    <div class="mb-4 pb-4 border-b border-dashed border-slate-300 dark:border-zink-500">
                        <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Diagnosis</p>
                        <p class="text-sm"><?= e($visit['diagnosis'] ?? 'N/A') ?></p>
                    </div>

                    <!-- Lab Results -->
                    <?php if (!empty($lab_items)): ?>
                    <div class="mb-4 pb-4 border-b border-dashed border-slate-300 dark:border-zink-500">
                        <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-2">Lab Results</p>
                        <div class="space-y-2">
                            <?php foreach ($lab_items as $lab): ?>
                            <div class="p-2 rounded bg-slate-50 dark:bg-zink-600">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-xs font-semibold"><?= e($lab['test_name']) ?></p>
                                    <span class="text-[10px] text-green-500 font-medium">Completed</span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-zink-200 whitespace-pre-wrap"><?= e($lab['results']) ?></p>
                                <?php if ($lab['remarks']): ?>
                                <p class="text-[10px] text-slate-400 mt-1">Remarks: <?= e($lab['remarks']) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Prescription Table -->
                    <div class="mb-4">
                        <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-2">Prescription</p>
                        <?php if (!empty($rx_items)): ?>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-slate-300 dark:border-zink-500">
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase w-8">#</th>
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase">Medicine</th>
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase">Dosage</th>
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase">Frequency</th>
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase">Duration</th>
                                    <th class="py-1.5 text-left text-[10px] text-slate-400 uppercase">Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rx_items as $i => $rx): ?>
                                <tr class="<?= $i < count($rx_items) - 1 ? 'border-b border-slate-200 dark:border-zink-500' : '' ?>">
                                    <td class="py-1.5 text-xs text-slate-400"><?= $i + 1 ?></td>
                                    <td class="py-1.5 text-xs font-semibold"><?= e($rx['medicine_name']) ?></td>
                                    <td class="py-1.5 text-xs"><?= e($rx['dosage']) ?></td>
                                    <td class="py-1.5 text-xs"><?= e($rx['frequency']) ?></td>
                                    <td class="py-1.5 text-xs"><?= e($rx['duration']) ?></td>
                                    <td class="py-1.5 text-xs text-slate-500 italic"><?= e($rx['instructions']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-sm text-slate-400 text-center py-4">No prescription written</p>
                        <?php endif; ?>
                    </div>

                    <!-- Rx Notes -->
                    <?php if ($rx_notes): ?>
                    <div class="p-3 rounded bg-slate-50 dark:bg-zink-600 mb-4">
                        <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Instructions</p>
                        <p class="text-xs"><?= e($rx_notes) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Follow-up -->
                    <?php if ($visit['follow_up_date']): ?>
                    <div class="p-3 rounded border border-dashed border-orange-300 dark:border-orange-500/30 bg-orange-50/50 dark:bg-orange-500/5">
                        <p class="text-[10px] text-orange-500 uppercase tracking-wider mb-1">Follow-up Required</p>
                        <p class="text-sm font-medium"><?= date('d M Y', strtotime($visit['follow_up_date'])) ?></p>
                        <?php if ($visit['follow_up_notes']): ?>
                        <p class="text-xs text-slate-500"><?= e($visit['follow_up_notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Doctor Sign -->
                    <div class="mt-6 pt-4 border-t border-slate-300 dark:border-zink-500 flex justify-between items-end">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Consulted By</p>
                            <p class="text-sm font-semibold"><?= e($visit['doctor_name'] ?? 'N/A') ?></p>
                            <p class="text-[10px] text-slate-400"><?= date('d M Y, h:i A', strtotime($visit['consulted_at'])) ?></p>
                        </div>
                        <div class="text-right">
                            <div class="w-32 border-t-2 border-slate-800 dark:border-zink-200 mt-6 mb-1"></div>
                            <p class="text-xs font-semibold"><?= e($visit['doctor_name'] ?? 'Doctor') ?></p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>