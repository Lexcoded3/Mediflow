<?php
require_once '../config/config.php';

// Date filter
$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to = $_GET['to'] ?? date('Y-m-d');

if ($date_from > $date_to) {
    $temp = $date_from;
    $date_from = $date_to;
    $date_to = $temp;
}

// Dispensing history - Using 'dispensing_log' and fixing the Med count logic
$history = $conn->prepare("
    SELECT d.id, 'completed' as dispense_status, d.notes as dispense_notes, d.dispensed_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           doc.name as doctor_name,
           c.diagnosis,
           (SELECT COUNT(*) 
            FROM prescription_items pi 
            JOIN prescriptions pr ON pi.prescription_id = pr.id 
            WHERE pr.consultation_id = c.id) as med_count
    FROM dispensing_log d
    JOIN visits v ON d.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE DATE(d.dispensed_at) BETWEEN ? AND ?
    ORDER BY d.dispensed_at DESC
");
$history->bind_param("ss", $date_from, $date_to);
$history->execute();
$history_results = $history->get_result();

// Stats - Total visits dispensed
$total_dispensed = $conn->query("
    SELECT COUNT(*) as cnt FROM dispensing_log 
    WHERE DATE(dispensed_at) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc()['cnt'] ?? 0;

// Stats - Total individual medications given out
$total_meds = $conn->query("
    SELECT COUNT(*) as cnt 
    FROM prescription_items pi
    JOIN prescriptions pr ON pi.prescription_id = pr.id
    JOIN consultations c ON pr.consultation_id = c.id
    JOIN dispensing_log d ON c.visit_id = d.visit_id
    WHERE DATE(d.dispensed_at) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc()['cnt'] ?? 0;

// Partial count - Note: dispensing_log doesn't have a status column in your dump
// We will default this to 0 to prevent crashes
$partial_count = 0;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Dispensing History | MediFlow OPD</title>
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
                    <h5 class="text-16">Dispensing History</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Pharmacy</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">History</li>
                </ul>
            </div>

            <!-- Date Filter -->
            <form method="GET" class="card mb-5">
                <div class="card-body">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-zink-200 mb-1.5">From</label>
                            <input type="date" name="from" value="<?= $date_from ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-zink-200 mb-1.5">To</label>
                            <input type="date" name="to" value="<?= $date_to ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                        </div>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                            <i data-lucide="filter" class="size-4"></i> Apply
                        </button>
                        <a href="index.php" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                            <i data-lucide="arrow-left" class="size-4"></i> Back to Queue
                        </a>
                    </div>
                </div>
            </form>

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-1 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="package-check" class="size-6 text-green-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Dispensed</p>
                                <h4 class="mt-1 text-2xl font-bold text-green-500"><?= $total_dispensed ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                <i data-lucide="pill" class="size-6 text-custom-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Medicines</p>
                                <h4 class="mt-1 text-2xl font-bold text-custom-500"><?= $total_meds ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="alert-triangle" class="size-6 text-yellow-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Partial</p>
                                <h4 class="mt-1 text-2xl font-bold text-yellow-500"><?= $partial_count ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                <i data-lucide="calendar" class="size-6 text-sky-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Period</p>
                                <h4 class="mt-1 text-sm font-bold text-sky-500"><?= date('M d', strtotime($date_from)) ?> - <?= date('M d', strtotime($date_to)) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Dispensing Records</h6>

                    <?php if ($history->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-zink-600">
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Token</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Patient</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Doctor</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Diagnosis</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Meds</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Status</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Dispensed At</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-zink-300 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-zink-600">
                                <?php while ($row = $history->get_result()->fetch_assoc()): 
                                    $status_class = $row['dispense_status'] === 'complete' 
                                        ? 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400' 
                                        : 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400';
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zink-600/50 transition-colors">
                                    <td class="px-3 py-2.5 font-mono text-sm text-custom-500"><?= str_pad($row['token_number'], 3, '0', STR_PAD_LEFT) ?></td>
                                    <td class="px-3 py-2.5">
                                        <p class="text-sm font-medium"><?= e($row['patient_name']) ?></p>
                                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= $row['age'] ?>y, <?= $row['gender'] ?></p>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-zink-200"><?= e($row['doctor_name']) ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-500 dark:text-zink-200 max-w-[150px] truncate"><?= e($row['diagnosis']) ?: '—' ?></td>
                                    <td class="px-3 py-2.5 text-sm text-slate-600 dark:text-zink-200 text-center"><?= $row['med_count'] ?></td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded <?= $status_class ?>">
                                            <?= ucfirst($row['dispense_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($row['dispensed_at'])) ?></td>
                                    <td class="px-3 py-2.5">
                                        <a href="dispense.php?visit_id=<?= $row['visit_id'] ?>" class="inline-flex items-center gap-1 text-xs text-custom-500 hover:text-custom-600 font-medium">
                                            <i data-lucide="eye" class="size-3"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="package" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No dispensing records in this period</p>
                    </div>
                    <?php endif; ?>
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