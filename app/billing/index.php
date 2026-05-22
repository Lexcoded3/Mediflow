<?php
require_once '../config/config.php';

// Patients ready for billing (status = 'completed')
$waiting = $conn->query("
    SELECT v.visit_id, v.token_number, v.created_at, v.id as visit_db_id,
           p.name as patient_name, p.age, p.gender, p.patient_id, p.phone,
           d.name as department_name, doc.name as doctor_name,
           c.diagnosis, c.consulted_at,
           (SELECT COUNT(*) 
            FROM prescription_items pi 
            JOIN prescriptions pr ON pi.prescription_id = pr.id 
            WHERE pr.consultation_id = c.id) as med_count,
           (SELECT COUNT(*) FROM lab_orders WHERE visit_id = v.id) as lab_count,
           (SELECT COUNT(*) FROM scans WHERE visit_id = v.id) as scan_count
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE v.visit_date = CURDATE() AND v.status = 'completed'
    ORDER BY c.consulted_at ASC
");

/** * CRITICAL NOTE: 
 * If your database dump didn't show a 'bills' table, these queries will fail.
 * I have added a check to return 0 if the 'bills' table is missing.
 */

// Billed today
$res_billed = $conn->query("SELECT COUNT(*) as cnt FROM bills WHERE DATE(created_at) = CURDATE()");
$billed_today = $res_billed ? $res_billed->fetch_assoc()['cnt'] : 0;

// Today's revenue
$res_revenue = $conn->query("SELECT SUM(total_amount) as total FROM bills WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
$today_revenue = ($res_revenue && $row = $res_revenue->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

// Pending bills amount
$res_pending = $conn->query("SELECT SUM(total_amount) as total FROM bills WHERE DATE(created_at) = CURDATE() AND payment_status = 'pending'");
$pending_amount = ($res_pending && $row = $res_pending->fetch_assoc()) ? ($row['total'] ?? 0) : 0;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Billing | MediFlow OPD</title>
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
                    <h5 class="text-16">Billing</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Billing</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="clock" class="size-6 text-yellow-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Ready for Billing</p>
                                <h4 class="mt-1 text-2xl font-bold text-yellow-500"><?= $waiting->num_rows ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="receipt" class="size-6 text-green-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Billed Today</p>
                                <h4 class="mt-1 text-2xl font-bold text-green-500"><?= $billed_today ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                <i data-lucide="trending-up" class="size-6 text-custom-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Today's Revenue</p>
                                <h4 class="mt-1 text-2xl font-bold text-custom-500"><?= number_format($today_revenue, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-red-100 dark:bg-red-500/20 shrink-0">
                                <i data-lucide="alert-circle" class="size-6 text-red-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Pending Amount</p>
                                <h4 class="mt-1 text-2xl font-bold text-red-500"><?= number_format($pending_amount, 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Waiting Queue -->
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h6 class="text-15">Ready for Billing <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $waiting->num_rows ?>)</span></h6>
                        <div class="relative">
                            <input type="text" id="searchBilling" class="ltr:pl-8 rtl:pr-8 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Search patient..." autocomplete="off">
                            <i data-lucide="search" class="inline-block size-4 absolute ltr:left-2.5 rtl:right-2.5 top-2.5 text-slate-500 dark:text-zink-200 fill-slate-100 dark:fill-zink-600"></i>
                        </div>
                    </div>

                    <?php if ($waiting->num_rows > 0): ?>
                    <div class="flex flex-col gap-2" id="billingList">
                        <?php while ($p = $waiting->fetch_assoc()): ?>
                        <div class="billing-search-item flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                            <div class="flex items-center justify-center size-10 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                <i data-lucide="file-text" class="size-5 text-custom-500"></i>
                            </div>
                            <div class="grow min-w-0">
                                <p class="text-sm font-medium truncate"><?= e($p['patient_name']) ?></p>
                                <p class="text-xs text-slate-400 dark:text-zink-300">
                                    Token <?= str_pad($p['token_number'], 3, '0', STR_PAD_LEFT) ?> · <?= e($p['doctor_name']) ?> · <?= e($p['department_name']) ?>
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <?php if ($p['med_count'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400 rounded">
                                        <i data-lucide="pill" class="size-2.5"></i> <?= $p['med_count'] ?> meds
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($p['lab_count'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400 rounded">
                                        <i data-lucide="test-tube" class="size-2.5"></i> <?= $p['lab_count'] ?> labs
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($p['scan_count'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 rounded">
                                        <i data-lucide="scan" class="size-2.5"></i> <?= $p['scan_count'] ?> scans
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($p['consulted_at'])) ?></span>
                                <a href="bill.php?visit_id=<?= $p['visit_id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                                    <i data-lucide="receipt" class="size-3"></i> Bill
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="file-text" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No patients ready for billing</p>
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

        const searchInput = document.getElementById('searchBilling');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.billing-search-item').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

</body>
</html>