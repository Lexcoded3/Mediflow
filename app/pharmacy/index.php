<?php
require_once '../config/config.php';

// Patients ready for dispensing (status = 'pharmacy')
$waiting = $conn->query("
    SELECT v.visit_id, v.token_number, v.created_at, v.id as visit_db_id,
           p.name as patient_name, p.age, p.gender, p.patient_id, p.phone,
           d.name as department_name, doc.name as doctor_name,
           t.priority,
           c.diagnosis, c.consulted_at,
           (SELECT COUNT(*) 
            FROM prescription_items pi 
            JOIN prescriptions pr ON pi.prescription_id = pr.id 
            WHERE pr.consultation_id = c.id) as med_count
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN triage t ON t.visit_id = v.id
    LEFT JOIN consultations c ON c.visit_id = v.id
    WHERE v.visit_date = CURDATE() AND v.status = 'pharmacy'
    ORDER BY 
        CASE COALESCE(t.priority, 'green')
            WHEN 'red' THEN 1
            WHEN 'orange' THEN 2
            WHEN 'yellow' THEN 3
            ELSE 4
        END ASC,
        c.consulted_at ASC
");

// Dispensed today - Note: Your schema has 'dispensing_log', not 'dispensing'
$dispensed_today_res = $conn->query("
    SELECT COUNT(*) as cnt FROM dispensing_log 
    WHERE DATE(dispensed_at) = CURDATE()
");
$dispensed_today = $dispensed_today_res ? $dispensed_today_res->fetch_assoc()['cnt'] : 0;

// Pending count
$pending_count = $waiting ? $waiting->num_rows : 0;

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Pharmacy | MediFlow OPD</title>
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
                    <h5 class="text-16">Pharmacy</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="#!" class="text-slate-400 dark:text-zink-200">OPD</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Pharmacy</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="clock" class="size-6 text-yellow-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Waiting for Dispensing</p>
                                <h4 class="mt-1 text-2xl font-bold text-yellow-500"><?= $pending_count ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-12 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="package-check" class="size-6 text-green-500"></i>
                            </div>
                            <div class="grow">
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Dispensed Today</p>
                                <h4 class="mt-1 text-2xl font-bold text-green-500"><?= $dispensed_today ?></h4>
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
                                <p class="text-slate-500 dark:text-zink-200 text-sm">Total Medicines Today</p>
                                <h4 class="mt-1 text-2xl font-bold text-custom-500">
                                    <?php 
                                $total_meds = $conn->query("
                                    SELECT COUNT(*) as cnt 
                                    FROM prescription_items pi
                                    JOIN prescriptions pr ON pi.prescription_id = pr.id
                                    JOIN consultations c ON pr.consultation_id = c.id
                                    JOIN dispensing_log dl ON c.visit_id = dl.visit_id
                                    WHERE DATE(dl.dispensed_at) = CURDATE()
                                ")->fetch_assoc()['cnt'] ?? 0;

                                echo $total_meds;
                                ?>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Waiting Queue -->
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h6 class="text-15">Waiting for Dispensing <span class="text-slate-400 dark:text-zink-200 font-normal">(<?= $pending_count ?>)</span></h6>
                        <div class="flex items-center gap-2">
                            <a href="history.php" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                                <i data-lucide="history" class="size-3"></i> History
                            </a>
                            <div class="relative">
                                <input type="text" id="searchPharmacy" class="ltr:pl-8 rtl:pr-8 form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200" placeholder="Search patient..." autocomplete="off">
                                <i data-lucide="search" class="inline-block size-4 absolute ltr:left-2.5 rtl:right-2.5 top-2.5 text-slate-500 dark:text-zink-200 fill-slate-100 dark:fill-zink-600"></i>
                            </div>
                        </div>
                    </div>

                    <?php if ($pending_count > 0): ?>
                    <div class="flex flex-col gap-2" id="pharmacyList">
                        <?php while ($p = $waiting->fetch_assoc()): ?>
                        <div class="pharmacy-search-item flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                            <div class="flex items-center justify-center size-10 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="pill" class="size-5 text-green-500"></i>
                            </div>
                            <div class="grow min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium truncate"><?= e($p['patient_name']) ?></p>
                                    <?= priorityDot($p['priority']) ?>
                                </div>
                                <p class="text-xs text-slate-400 dark:text-zink-300">
                                    Token <?= str_pad($p['token_number'], 3, '0', STR_PAD_LEFT) ?> · <?= e($p['doctor_name']) ?> · <?= $p['med_count'] ?> medicine(s)
                                </p>
                                <?php if ($p['diagnosis']): ?>
                                <p class="text-xs text-slate-500 dark:text-zink-200 mt-0.5 truncate">
                                    <span class="font-medium">Dx:</span> <?= e($p['diagnosis']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] text-slate-400 dark:text-zink-300"><?= date('h:i A', strtotime($p['consulted_at'])) ?></span>
                                <a href="dispense.php?visit_id=<?= $p['visit_id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-white bg-green-500 hover:bg-green-600 rounded-md transition-colors">
                                    <i data-lucide="package-check" class="size-3"></i> Dispense
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="py-12 text-center">
                        <i data-lucide="pill" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No patients waiting for dispensing</p>
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

        const searchInput = document.getElementById('searchPharmacy');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.pharmacy-search-item').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

</body>
</html>