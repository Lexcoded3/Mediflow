<?php
require_once '../config/config.php';

 $order_id = (int)($_GET['order_id'] ?? 0);

if (empty($order_id)) {
    setFlash('error', 'No order ID provided');
    header("Location: index.php");
    exit;
}

// Get lab order + visit + patient info
 $stmt = $conn->prepare("
    SELECT lo.*, v.visit_id, v.token_number, v.status as visit_status,
           p.name as patient_name, p.age, p.gender, p.patient_id, p.phone,
           d.name as department_name, doc.name as doctor_name,
           c.diagnosis, c.chief_complaint
    FROM lab_orders lo
    JOIN visits v ON lo.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE lo.id = ?
");
 $stmt->bind_param("i", $order_id);
 $stmt->execute();
 $order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    setFlash('error', 'Lab order not found');
    header("Location: index.php");
    exit;
}

// Check if results already entered
 $existing_result = $conn->prepare("SELECT * FROM lab_results WHERE lab_order_id = ?");
 $existing_result->bind_param("i", $order_id);
 $existing_result->execute();
 $result = $existing_result->get_result()->fetch_assoc();

// ... (Top part of your file remains the same until Handle form submission)

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = trim($_POST['results'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if (empty($results)) {
        setFlash('error', 'Results field is required');
        header("Location: enter_results.php?order_id=$order_id");
        exit;
    }
    
    try {
        if ($result) {
            $stmt = $conn->prepare("UPDATE lab_results SET results = ?, remarks = ?, entered_at = NOW() WHERE lab_order_id = ?");
            $stmt->bind_param("ssi", $results, $remarks, $order_id);
            $stmt->execute();
        } else {
            // Note: Fixed the column mismatch here (added remarks to VALUES)
            $stmt = $conn->prepare("INSERT INTO lab_results (lab_order_id, results, remarks, entered_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $order_id, $results, $remarks);
            $stmt->execute();
        }
        
        // Update lab order status to completed
        $stmt = $conn->prepare("UPDATE lab_orders SET status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        
        // FIX: Use prepare() for pending_count because you are passing a parameter
        $stmt_count = $conn->prepare("
            SELECT COUNT(*) as cnt FROM lab_orders 
            WHERE visit_id = (SELECT visit_id FROM lab_orders WHERE id = ?) 
            AND status != 'completed'
        ");
        $stmt_count->bind_param("i", $order_id);
        $stmt_count->execute();
        $pending_res = $stmt_count->get_result()->fetch_assoc();
        
        if ($pending_res['cnt'] == 0) {
            // All labs done, move back to consultation
            $visit_id_val = $order['visit_id'];
            $stmt = $conn->prepare("UPDATE visits SET status = 'consulting' WHERE id = ?");
            $stmt->bind_param("i", $visit_id_val);
            $stmt->execute();
            
            setFlash('success', 'Results saved! All lab tests completed — patient returned to Consultation.');
            header("Location: enter_results.php?visit_id=" . urlencode($visit_id_val));
        } else {
            setFlash('success', 'Results saved for ' . e($order['test_name']) . '. ' . $pending_res['cnt'] . ' more test(s) pending.');
            header("Location: index.php");
        }
        exit;
        
    } catch (Exception $e) {
        // Only rollback if you started a transaction (optional but good practice)
        setFlash('error', 'Failed to save results: ' . $e->getMessage());
        header("Location: enter_results.php?order_id=$order_id");
        exit;
    }
} // <--- THIS WAS MISSING (Closes the POST if block)

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Enter Lab Results | MediFlow OPD</title>
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

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing(header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Enter Lab Results</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Lab Queue</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px) before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <span class="text-slate-700 dark:text-zink-100"><?= e($order['test_name']) ?></span>
                    </li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Patient Info -->
            <div class="flex flex-wrap items-center gap-4 p-4 mb-5 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="flex items-center justify-center size-14 rounded-full bg-purple-100 dark:bg-purple-500/20 shrink-0">
                    <i data-lucide="flask-conical" class="size-7 text-purple-500"></i>
                </div>
                <div class="grow">
                    <h5 class="text-base font-semibold"><?= e($order['patient_name']) ?></h5>
                    <p class="text-sm text-slate-500 dark:text-zink-200"><?= e($order['patient_id']) ?> &middot; Token <?= str_pad($order['token_number'], 3, '0', STR_PAD_LEFT) ?></p>
                    <!-- Fixed the closing parenthesis below -->
                    <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($order['department_name']) ?> &middot; Dr. <?= e($order['doctor_name'] ?? 'N/A') ?></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-slate-400 dark:text-zink-300">Ordered</p>
                    <p class="text-sm font-medium"><?= date('d M Y, h:i A', strtotime($order['ordered_at'])) ?></p>
                </div>
            </div>

            <!-- Diagnosis Context -->
            <?php if ($order['chief_complaint'] || $order['diagnosis']): ?>
            <div class="mb-5 p-3 rounded-md bg-slate-100 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <?php if ($order['chief_complaint']): ?>
                <div class="mb-2">
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Chief Complaint</p>
                    <p class="text-sm"><?= e($order['chief_complaint']) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($order['diagnosis']): ?>
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">Diagnosis</p>
                    <p class="text-sm font-medium"><?= e($order['diagnosis']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Results Form -->
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center justify-center size-10 rounded-full bg-purple-500 text-white shrink-0">
                            <i data-lucide="file-text" class="size-5"></i>
                        </div>
                        <div>
                            <h6 class="text-15 mb-0">Lab Results</h6>
                            <p class="text-xs text-slate-500 dark:text-zink-200">Enter the test results for <?= e($order['test_name']) ?></p>
                        </div>
                    </div>

                    <?php if ($result): ?>
                    <div class="mb-4 p-3 rounded-md bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="check-circle" class="size-4 text-green-500"></i>
                            <span class="text-sm font-medium text-green-600 dark:text-green-400">Results already entered on <?= date('d M Y, h:i A', strtotime($result['entered_at'])) ?></span>
                        </div>
                        <div class="mt-2 p-2 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500">
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider mb-1">Previous Results</p>
                            <p class="text-sm whitespace-pre-line"><?= e($result['results']) ?></p>
                            <?php if ($result['remarks']): ?>
                            <p class="text-[10px] text-slate-400 mt-2">Remarks: <?= e($result['remarks']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="enter_results.php?order_id=<?= $order_id ?>">
                        <div class="mb-4">
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Results <span class="text-red-500">*</span></label>
                            <textarea name="results" rows="10" required
                                      class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                      placeholder="Enter lab results here...&#10;&#10;HB: 12.5&#10;&#10;WBC: 6.8&#10;&#10;RBC: 4.2&#10;&#10;Platelets: 250&#10;&#10;ESR: Normal&#10;&#10;PCV: 32&#10;&#10;LCV: 44&#10;&#10;MCHC: 26&#10;&#10;MPV: 9.6"
                            ><?= $result ? e($result['results']) : '' ?></textarea>
                        </div>

                        <div class="mb-5">
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Remarks</label>
                            <textarea name="remarks" rows="3"
                                      class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                                      placeholder="Any remarks about the test..."
                            ><?= $result ? e($result['remarks']) : '' ?></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="index.php" class="px-4 py-2.5 text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2.5 text-white btn bg-purple-500 border-purple-500 hover:bg-purple-600 focus:bg-purple-600 focus:bg-purple-600">
                                <i data-lucide="save" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Save Results
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>