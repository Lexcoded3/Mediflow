<?php
require_once '../config/config.php';
requirePermission('pharmacy');


$visit_id = $_GET['visit_id'] ?? '';
if (empty($visit_id)) {
    setFlash('error', 'No visit ID provided');
    header("Location: index.php");
    exit;
}

// Get visit + patient + consultation
$stmt = $conn->prepare("
    SELECT v.*, p.name as patient_name, p.phone, p.age, p.gender, p.blood_group, p.patient_id,
           d.name as department_name, doc.name as doctor_name,
           c.id as consultation_id, c.diagnosis, c.chief_complaint, c.notes as consult_notes, c.follow_up_date, c.consulted_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE v.visit_id = ? AND v.status = 'pharmacy'
");
$stmt->bind_param("s", $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$visit) {
    setFlash('error', 'Visit not found or not ready for dispensing');
    header("Location: index.php");
    exit;
}

// Get prescription
$stmt = $conn->prepare("
    SELECT pr.id as prescription_id, pr.notes as rx_notes, pr.created_at
    FROM prescriptions pr
    JOIN consultations c ON c.id = pr.consultation_id
    WHERE c.visit_id = ?
");
$stmt->bind_param("i", $visit['id']);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get prescription items + match against pharmacy_stock
$medicines = [];
if ($rx) {
   $stmt = $conn->prepare("
    SELECT pi.*,
           ps.id as stock_id,
           ps.stock_qty,
           ps.price as unit_price,
           ps.unit,
           ps.min_stock
    FROM prescription_items pi
    LEFT JOIN pharmacy_stock ps ON LOWER(ps.medicine_name) LIKE LOWER(CONCAT('%', pi.medicine_name, '%'))
        AND ps.status = 1
    WHERE pi.prescription_id = ?
    ORDER BY pi.id ASC
");
    $stmt->bind_param("i", $rx['prescription_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($med = $result->fetch_assoc()) {
        $medicines[] = $med;
    }
    $stmt->close();
}

// Check if already dispensed
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dispensing_log WHERE visit_id = ?");
$stmt->bind_param("i", $visit['id']);
$stmt->execute();
$already_dispensed = $stmt->get_result()->fetch_assoc()['cnt'] > 0;
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_dispensed) {
    $dispense_items = $_POST['dispense'] ?? [];
    $global_notes   = trim($_POST['notes'] ?? '');
    $staff_id       = $_SESSION['staff_id'] ?? null; // adjust to your session key

    if (empty($dispense_items)) {
        setFlash('error', 'No medicines selected for dispensing');
        header("Location: dispense.php?visit_id=" . urlencode($visit_id));
        exit;
    }

    $conn->begin_transaction();
    try {
        foreach ($dispense_items as $item) {
            $stock_id  = (int)($item['stock_id'] ?? 0);
            $qty       = (int)($item['quantity'] ?? 0);
            $item_notes = trim($item['notes'] ?? $global_notes);

            if ($qty <= 0 || $stock_id <= 0) continue;

            // Get current stock details
            $s = $conn->prepare("SELECT * FROM pharmacy_stock WHERE id = ? AND status = 1 FOR UPDATE");
            $s->bind_param("i", $stock_id);
            $s->execute();
            $stock = $s->get_result()->fetch_assoc();
            $s->close();

            if (!$stock) continue;
            if ($stock['stock_qty'] < $qty) {
                throw new Exception("Insufficient stock for: " . $stock['medicine_name'] . " (Available: " . $stock['stock_qty'] . ")");
            }

            // Deduct from pharmacy_stock
            $s = $conn->prepare("UPDATE pharmacy_stock SET stock_qty = stock_qty - ?, updated_at = NOW() WHERE id = ?");
            $s->bind_param("ii", $qty, $stock_id);
            $s->execute();
            $s->close();

            // Try to find matching drug in drugs table for drug_id
            $d = $conn->prepare("SELECT id, batch_number, expiry_date FROM drugs WHERE LOWER(drug_name) LIKE LOWER(?) AND is_active = 1 LIMIT 1");
            $like = '%' . $stock['medicine_name'] . '%';
            $d->bind_param("s", $like);
            $d->execute();
            $drug = $d->get_result()->fetch_assoc();
            $d->close();

            $drug_id      = $drug['id'] ?? null;
            $batch_number = $drug['batch_number'] ?? null;
            $expiry_date  = $drug['expiry_date'] ?? null;

            if (!$drug_id) {
                // Insert a placeholder drug record if not found
                $ins = $conn->prepare("INSERT INTO drugs (drug_name, current_stock, is_active) VALUES (?, ?, 1)");
                $ins->bind_param("si", $stock['medicine_name'], $stock['stock_qty']);
                $ins->execute();
                $drug_id = $conn->insert_id;
                $ins->close();
            }

            // Insert dispensing_log
            $ins = $conn->prepare("
                INSERT INTO dispensing_log (visit_id, prescription_id, drug_id, quantity_dispensed, batch_number, expiry_date, notes, dispensed_by_staff_id, dispensed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->bind_param("iiiisssi",
                $visit['id'],
                $rx['prescription_id'],
                $drug_id,
                $qty,
                $batch_number,
                $expiry_date,
                $item_notes,
                $staff_id
            );
            $ins->execute();
            $ins->close();
        }

        // Update visit status to completed
        $u = $conn->prepare("UPDATE visits SET status = 'completed' WHERE id = ?");
        $u->bind_param("i", $visit['id']);
        $u->execute();
        $u->close();

        $conn->commit();
        setFlash('success', 'Medicines dispensed successfully. Patient visit completed.');
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        setFlash('error', 'Dispensing failed: ' . $e->getMessage());
        header("Location: dispense.php?visit_id=" . urlencode($visit_id));
        exit;
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Dispense Medicines | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include 'sidenav.php'; ?>
<?php include 'topnav.php'; ?>

<div class="relative min-h-screen">
    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Dispense Medicines</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                        <a href="index.php" class="text-slate-400">Pharmacy</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Dispense</li>
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

            <!-- Already Dispensed Banner -->
            <?php if ($already_dispensed): ?>
            <div class="mb-5 px-4 py-3 rounded-md border bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400">
                <div class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="size-5 shrink-0"></i>
                    <span class="font-medium">Medicines already dispensed for this visit.</span>
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
                    <p class="text-sm text-slate-500 dark:text-zink-200">
                        <?= e($visit['patient_id']) ?> &middot; <?= e($visit['age']) ?>y &middot; <?= e($visit['gender']) ?> &middot; <?= e($visit['blood_group'] ?: 'N/A') ?>
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-slate-400">Department</p>
                    <p class="text-sm font-medium"><?= e($visit['department_name']) ?></p>
                    <?php if ($visit['doctor_name']): ?>
                    <p class="text-xs text-slate-400 mt-1">Doctor</p>
                    <p class="text-sm font-medium"><?= e($visit['doctor_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnosis Info -->
            <?php if ($visit['diagnosis']): ?>
            <div class="mb-5 p-3 rounded-md bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20">
                <p class="text-xs font-medium text-sky-500 mb-1">DIAGNOSIS</p>
                <p class="text-sm"><?= e($visit['diagnosis']) ?></p>
            </div>
            <?php endif; ?>

            <?php if (empty($medicines)): ?>
            <!-- No Prescription -->
            <div class="card">
                <div class="card-body py-12 text-center">
                    <i data-lucide="file-x" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                    <p class="text-slate-500 dark:text-zink-200 font-medium">No prescription found for this visit</p>
                    <p class="text-sm text-slate-400 dark:text-zink-300 mt-1">The doctor may not have added any medicines</p>
                    <?php if (!$already_dispensed): ?>
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="notes" value="No medicines prescribed">
                        <button type="submit" class="px-4 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                            Complete Visit (No Medicines)
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>

            <!-- Prescription + Dispense Form -->
            <form method="POST" action="dispense.php?visit_id=<?= urlencode($visit_id) ?>">

                <div class="card mb-5">
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="flex items-center justify-center size-10 rounded-full bg-orange-500 text-white shrink-0">
                                <i data-lucide="pill" class="size-5"></i>
                            </div>
                            <div>
                                <h6 class="text-15 mb-0">Prescription Medicines</h6>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Review stock availability and confirm quantities to dispense</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4">
                            <?php foreach ($medicines as $i => $med): 
                                $in_stock    = $med['stock_id'] && $med['stock_qty'] > 0;
                                $low_stock   = $in_stock && $med['stock_qty'] <= $med['min_stock'];
                                $out_of_stock = !$med['stock_id'] || $med['stock_qty'] <= 0;
                            ?>
                            <div class="p-4 rounded-md border <?= $out_of_stock ? 'border-red-200 dark:border-red-500/30 bg-red-50/30 dark:bg-red-500/5' : ($low_stock ? 'border-yellow-200 dark:border-yellow-500/30 bg-yellow-50/30 dark:bg-yellow-500/5' : 'border-slate-200 dark:border-zink-500') ?>">
                                <div class="flex flex-wrap items-start gap-4">

                                    <!-- Checkbox -->
                                    <div class="pt-1 shrink-0">
                                        <input type="checkbox" 
                                               name="dispense[<?= $i ?>][stock_id]" 
                                               value="<?= (int)$med['stock_id'] ?>"
                                               id="med_<?= $i ?>"
                                               <?= $out_of_stock ? 'disabled' : 'checked' ?>
                                               class="size-4 rounded border-slate-300 text-custom-500 focus:ring-custom-500"
                                               onchange="toggleRow(<?= $i ?>, this.checked)">
                                    </div>

                                    <!-- Medicine Info -->
                                    <div class="grow min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <h6 class="text-sm font-semibold"><?= e($med['medicine_name']) ?></h6>
                                            <?php if ($out_of_stock): ?>
                                            <span class="px-2 py-0.5 text-[10px] font-medium rounded border bg-red-100 border-red-200 text-red-600 dark:bg-red-500/20 dark:border-red-500/20 dark:text-red-400">Out of Stock</span>
                                            <?php elseif ($low_stock): ?>
                                            <span class="px-2 py-0.5 text-[10px] font-medium rounded border bg-yellow-100 border-yellow-200 text-yellow-600 dark:bg-yellow-500/20 dark:border-yellow-500/20 dark:text-yellow-400">Low Stock (<?= $med['stock_qty'] ?> left)</span>
                                            <?php else: ?>
                                            <span class="px-2 py-0.5 text-[10px] font-medium rounded border bg-green-100 border-green-200 text-green-600 dark:bg-green-500/20 dark:border-green-500/20 dark:text-green-400">In Stock (<?= $med['stock_qty'] ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-zink-200">
                                            <?php if ($med['dosage']): ?><span>Dose: <strong><?= e($med['dosage']) ?></strong></span><?php endif; ?>
                                            <?php if ($med['frequency']): ?><span>Freq: <strong><?= e($med['frequency']) ?></strong></span><?php endif; ?>
                                            <?php if ($med['duration']): ?><span>Duration: <strong><?= e($med['duration']) ?></strong></span><?php endif; ?>
                                            <?php if ($med['instructions']): ?><span>Instructions: <strong><?= e($med['instructions']) ?></strong></span><?php endif; ?>
                                        </div>
                                        <?php if ($med['unit_price']): ?>
                                        <p class="text-xs text-slate-400 mt-1">Unit Price: <strong class="text-slate-600 dark:text-zink-200"><?= number_format($med['unit_price'], 2) ?></strong></p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quantity Input -->
                                    <div class="shrink-0 w-32" id="qty_row_<?= $i ?>">
                                        <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-zink-200">Qty to Dispense</label>
                                        <input type="number" 
                                               name="dispense[<?= $i ?>][quantity]" 
                                               value="1"
                                               min="1" 
                                               max="<?= (int)($med['stock_qty'] ?? 0) ?>"
                                               <?= $out_of_stock ? 'disabled' : '' ?>
                                               class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-center font-semibold">
                                        <?php if ($in_stock): ?>
                                        <p class="text-[10px] text-slate-400 mt-0.5 text-center"><?= $med['unit'] ?? 'units' ?> available: <?= $med['stock_qty'] ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Notes per medicine -->
                                    <div class="shrink-0 w-40" id="note_row_<?= $i ?>">
                                        <label class="block mb-1 text-xs font-medium text-slate-600 dark:text-zink-200">Notes</label>
                                        <input type="text" 
                                               name="dispense[<?= $i ?>][notes]"
                                               placeholder="Optional..."
                                               <?= $out_of_stock ? 'disabled' : '' ?>
                                               class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm placeholder:text-slate-400">
                                    </div>
                                </div>

                                <?php if ($out_of_stock): ?>
                                <p class="mt-2 text-xs text-red-500">
                                    <i data-lucide="alert-triangle" class="inline-block size-3 mr-1"></i>
                                    This medicine is not available in pharmacy stock. Please source manually or update stock.
                                </p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Global Notes -->
                        <div class="mt-4">
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">General Dispensing Notes</label>
                            <textarea name="notes" rows="2" placeholder="Any general notes for this dispensing..."
                                      class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <?php if (!$already_dispensed): ?>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600 hover:border-custom-600">
                        <i data-lucide="check-circle" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i>
                        Confirm Dispense & Complete Visit
                    </button>
                    <a href="index.php" class="px-4 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 text-center">
                        Cancel
                    </a>
                </div>
                <?php endif; ?>

            </form>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>

<script>
function toggleRow(index, checked) {
    const qtyRow  = document.getElementById('qty_row_'  + index);
    const noteRow = document.getElementById('note_row_' + index);
    const inputs  = [
        ...qtyRow.querySelectorAll('input'),
        ...noteRow.querySelectorAll('input')
    ];
    inputs.forEach(inp => {
        inp.disabled = !checked;
        inp.style.opacity = checked ? '1' : '0.4';
    });
}
</script>

</body>
</html>