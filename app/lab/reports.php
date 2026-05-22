<?php
require_once '../config/config.php';

// --- Date Filter ---
$date_from = $_GET['from'] ?? date('Y-m-d');
$date_to   = $_GET['to'] ?? date('Y-m-d');

if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

// --- JSON API HANDLER ---
if (isset($_GET['section'])) {
    header('Content-Type: application/json');
    $section = $_GET['section'];

    if ($section === 'tests') {
        $stmt = $conn->prepare("
            SELECT test_name, COUNT(*) as cnt
            FROM lab_orders
            WHERE status = 'completed'
            AND DATE(entered_at) BETWEEN ? AND ?
            GROUP BY test_name
            ORDER BY cnt DESC
        ");
    } elseif ($section === 'departments') {
        $stmt = $conn->prepare("
            SELECT d.name, COUNT(*) as cnt
            FROM lab_orders lo
            JOIN visits v ON lo.visit_id = v.id
            JOIN departments d ON v.department_id = d.id
            WHERE lo.status = 'completed'
            AND DATE(lo.entered_at) BETWEEN ? AND ?
            GROUP BY d.name
            ORDER BY cnt DESC
        ");
    } elseif ($section === 'doctors') {
        $stmt = $conn->prepare("
            SELECT COALESCE(doc.name, 'Unassigned') as name, COUNT(*) as cnt
            FROM lab_orders lo
            JOIN visits v ON lo.visit_id = v.id
            LEFT JOIN doctors doc ON v.doctor_id = doc.id
            WHERE lo.status = 'completed'
            AND DATE(lo.entered_at) BETWEEN ? AND ?
            GROUP BY doc.name
            ORDER BY cnt DESC
        ");
    }

    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// --- SUMMARY STATS ---
$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt 
    FROM lab_orders 
    WHERE status = 'completed'
    AND DATE(ordered_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$total_tests = $stmt->get_result()->fetch_assoc()['cnt'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT visit_id) as cnt
    FROM lab_orders
    WHERE status = 'completed'
    AND DATE(ordered_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$unique_patients = $stmt->get_result()->fetch_assoc()['cnt'];

// --- DETAILED RESULTS ---
$stmt = $conn->prepare("
    SELECT lo.test_name, lr.results, lr.remarks, lr.entered_at,
           p.name as patient_name,
           d.name as department_name,
           COALESCE(doc.name, 'N/A') as doctor_name
    FROM lab_orders lo
    JOIN visits v ON lo.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN departments d ON v.department_id = d.id
    LEFT JOIN doctors doc ON v.doctor_id = doc.id
    LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE lo.status = 'completed'
    AND DATE(lr.entered_at) BETWEEN ? AND ?
    ORDER BY lr.entered_at DESC
");
$stmt->bind_param("ss", $date_from, $date_to);
$stmt->execute();
$results = $stmt->get_result();

// Collect all rows into array so we can both render the table AND pass to JS for sort/export
$all_rows = [];
while ($r = $results->fetch_assoc()) {
    $all_rows[] = $r;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Lab Reports | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        [x-cloak] { display: none !important; }
        .chart-bar-bg { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
        .chart-bar-fill { height: 100%; border-radius: 4px; background: currentColor; transition: width 0.3s; }

        /* Sortable column headers */
        .sortable-th {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .sortable-th:hover { background: #e2e8f0; }
        .dark .sortable-th:hover { background: #3f4a5a; }
        .sort-icon {
            display: inline-block;
            margin-left: 4px;
            opacity: 0.4;
            font-size: 10px;
            vertical-align: middle;
        }
        .sort-icon.active { opacity: 1; color: #1D9E75; }

        @media print {
            .print-hide { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
        }
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
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Lab Reports</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Lab</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Reports</li>
                </ul>
            </div>

            <!-- Date Filter -->
            <div class="card mb-5 print-hide">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-8 rounded-full bg-custom-500 text-white shrink-0">
                            <i data-lucide="calendar" class="size-4"></i>
                        </div>
                        <div x-data="{
                            from: '<?= $date_from ?>',
                            to: '<?= $date_to ?>',
                            submitUrl() {
                                window.location.href = 'reports.php?from=' + this.from + '&to=' + this.to;
                            },
                            resetDates() {
                                const today = new Date().toISOString().split('T')[0];
                                this.from = today;
                                this.to = today;
                                this.submitUrl();
                            }
                        }" class="flex flex-wrap items-end gap-2 grow">
                            <div class="relative">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">From</label>
                                <input type="date" x-model="from"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <span class="text-slate-400 dark:text-zink-200 pb-2">to</span>
                            <div class="relative">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">To</label>
                                <input type="date" x-model="to"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <button type="button" @click="submitUrl()" class="px-4 py-[9px] text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600 transition-colors shrink-0">
                                <i data-lucide="filter" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Filter
                            </button>
                            <button type="button" @click="resetDates()" class="px-4 py-[9px] text-slate-500 btn bg-white border-slate-200 hover:text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:text-zink-100 dark:hover:bg-zink-600 shrink-0">
                                <i data-lucide="x" class="inline-block size-4 ltr:mr-1 rtl:ml-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-purple-100 dark:bg-purple-500/20">
                            <i data-lucide="flask-conical" class="size-6 text-purple-500"></i>
                        </div>
                        <p class="text-slate-500 dark:text-zink-200 text-sm">Total Tests</p>
                        <h3 class="text-2xl font-bold mt-1"><?= $total_tests ?></h3>
                        <p class="text-xs text-slate-400 dark:text-zink-300">In selected period</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-green-100 dark:bg-green-500/20">
                            <i data-lucide="user-check" class="size-6 text-green-500"></i>
                        </div>
                        <p class="text-slate-500 dark:text-zink-200 text-sm">Unique Patients</p>
                        <h3 class="text-2xl font-bold mt-1"><?= $unique_patients ?></h3>
                        <p class="text-xs text-slate-400 dark:text-zink-300">Had lab tests done</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-yellow-100 dark:bg-yellow-500/20">
                            <i data-lucide="test-tube" class="size-6 text-yellow-500"></i>
                        </div>
                        <p class="text-slate-500 dark:text-zink-200 text-sm">Different Tests</p>
                        <h3 class="text-2xl font-bold mt-1" id="distinct-test-count">—</h3>
                        <p class="text-xs text-slate-400 dark:text-zink-300">Types of tests performed</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <div class="flex items-center justify-center mx-auto mb-2 size-12 rounded-full bg-sky-100 dark:bg-sky-500/20">
                            <i data-lucide="calendar-check" class="size-6 text-sky-500"></i>
                        </div>
                        <p class="text-slate-500 dark:text-zink-200 text-sm">Date Range</p>
                        <h3 class="text-lg font-bold mt-1"><?= date('d M Y', strtotime($date_from)) ?> — <?= date('d M Y', strtotime($date_to)) ?></h3>
                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= date('D', strtotime($date_from)) ?> — <?= date('D', strtotime($date_to)) ?></p>
                    </div>
                </div>
            </div>

            <!-- Test-wise Breakdown -->
            <div class="card mb-5 print-hide">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Tests Performed</h6>
                    <div class="space-y-3" x-data="{ results: [] }" x-init="
                        fetch('reports.php?from=<?= $date_from ?>&to=<?= $date_to ?>&section=tests')
                            .then(r => r.json())
                            .then(data => {
                                results = data;
                                document.getElementById('distinct-test-count').textContent = data.length;
                            });
                    ">
                        <template x-for="(item, index) in results" :key="index">
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                                <div class="flex items-center justify-center size-10 rounded-md bg-purple-100 dark:bg-purple-500/20 shrink-0">
                                    <span class="text-xs font-bold text-purple-600 dark:text-purple-400" x-text="index + 1"></span>
                                </div>
                                <div class="grow min-w-0">
                                    <p class="text-sm font-medium" x-text="item.test_name"></p>
                                    <div class="chart-bar-bg mt-1.5 w-full">
                                        <div class="chart-bar-fill text-purple-500"
                                             :style="'width: ' + ((results[0]?.cnt ? (item.cnt / results[0].cnt) : 0) * 100) + '%'"></div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-purple-600 dark:text-purple-400 shrink-0 w-12 text-right" x-text="item.cnt"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Department-wise Breakdown -->
            <div class="card mb-5 print-hide">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Department-wise Distribution</h6>
                    <div class="space-y-3" x-data="{ results: [] }" x-init="
                        fetch('reports.php?from=<?= $date_from ?>&to=<?= $date_to ?>&section=departments')
                            .then(r => r.json())
                            .then(data => { results = data; });
                    ">
                        <template x-for="(item, index) in results" :key="index">
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                                <div class="flex items-center justify-center size-10 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                    <i data-lucide="building" class="size-5 text-custom-500"></i>
                                </div>
                                <div class="grow min-w-0">
                                    <p class="text-sm font-medium" x-text="item.name"></p>
                                    <div class="chart-bar-bg mt-1.5 w-full">
                                        <div class="chart-bar-fill text-custom-500"
                                             :style="'width: ' + ((results[0]?.cnt ? (item.cnt / results[0].cnt) : 0) * 100) + '%'"></div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-custom-500 shrink-0 w-12 text-right" x-text="item.cnt"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Doctor-wise Breakdown -->
            <div class="card mb-5 print-hide">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Doctor-wise Tests</h6>
                    <div class="space-y-3" x-data="{ results: [] }" x-init="
                        fetch('reports.php?from=<?= $date_from ?>&to=<?= $date_to ?>&section=doctors')
                            .then(r => r.json())
                            .then(data => { results = data; });
                    ">
                        <template x-for="(item, index) in results" :key="index">
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors">
                                <div class="flex items-center justify-center size-10 rounded-md bg-sky-100 dark:bg-sky-500/20 shrink-0">
                                    <span class="text-xs font-bold text-sky-600 dark:text-sky-400" x-text="index + 1"></span>
                                </div>
                                <div class="grow min-w-0">
                                    <p class="text-sm font-medium" x-text="item.name || 'Unassigned'"></p>
                                    <div class="chart-bar-bg mt-1.5 w-full">
                                        <div class="chart-bar-fill text-sky-500"
                                             :style="'width: ' + Math.min((results[0]?.cnt ? (item.cnt / results[0].cnt) : 0) * 100, 100) + '%'"></div>
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-sky-600 dark:text-sky-400 shrink-0 w-12 text-right" x-text="item.cnt"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Detailed Results List -->
            <div class="card" id="results-card">
                <div class="card-body">

                    <!-- Table header with export buttons -->
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                        <h6 class="text-15">All Results (<?= count($all_rows) ?>)</h6>
                        <div class="flex items-center gap-2 print-hide">
                            <button onclick="exportCSV()"
                                class="px-3 py-[7px] text-sm text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600 transition-colors flex items-center gap-1.5">
                                <i data-lucide="download" class="size-4"></i> Export CSV
                            </button>
                            <button onclick="window.print()"
                                class="px-3 py-[7px] text-sm text-slate-600 btn bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors flex items-center gap-1.5">
                                <i data-lucide="printer" class="size-4"></i> Print
                            </button>
                        </div>
                    </div>

                    <div class="-mx-5 overflow-x-auto">
                        <table class="w-full whitespace-nowrap" id="results-table">
                            <thead class="ltr:text-left rtl:text-right bg-slate-100 text-slate-500 dark:text-zink-200 dark:bg-zink-600">
                                <tr>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 sortable-th" data-col="test_name">
                                        Test Name <span class="sort-icon" data-col="test_name">↕</span>
                                    </th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 sortable-th" data-col="patient_name">
                                        Patient <span class="sort-icon" data-col="patient_name">↕</span>
                                    </th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 sortable-th" data-col="department_name">
                                        Dept <span class="sort-icon" data-col="department_name">↕</span>
                                    </th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 sortable-th" data-col="doctor_name">
                                        Doctor <span class="sort-icon" data-col="doctor_name">↕</span>
                                    </th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500 sortable-th" data-col="entered_at">
                                        Date <span class="sort-icon active" data-col="entered_at">↓</span>
                                    </th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Results</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="results-tbody">
                                <?php foreach ($all_rows as $r): ?>
                                <tr>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm font-medium"><?= e($r['test_name']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($r['patient_name']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($r['department_name']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($r['doctor_name'] ?? 'N/A') ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-xs text-slate-400 dark:text-zink-300"><?= date('d M Y', strtotime($r['entered_at'])) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-xs max-w-[200px] truncate"><?= $r['results'] ? e(substr($r['results'], 0, 80)) . '…' : '-' ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-xs text-slate-400 dark:text-zink-300 max-w-[150px] truncate"><?= e($r['remarks'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($all_rows)): ?>
                    <div class="py-12 mt-5 text-center text-slate-400 dark:text-zink-300">
                        <i data-lucide="flask-conical" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p>No lab results found for this period</p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>
</div>

<!-- Pass PHP data to JS for sort/export -->
<script>
const TABLE_DATA = <?= json_encode($all_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // ── Sortable Table ─────────────────────────────────────────────────────────
    let sortCol   = 'entered_at';
    let sortDir   = 'desc';  // default: newest first (matches original SQL ORDER BY)

    const tbody   = document.getElementById('results-tbody');
    const headers = document.querySelectorAll('.sortable-th');

    // Column index map (matches <td> order in tbody)
    const COL_INDEX = {
        test_name:       0,
        patient_name:    1,
        department_name: 2,
        doctor_name:     3,
        entered_at:      4,
    };

    headers.forEach(th => {
        th.addEventListener('click', function () {
            const col = this.dataset.col;
            if (sortCol === col) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortCol = col;
                sortDir = 'asc';
            }
            updateSortIcons();
            sortTable();
        });
    });

    function updateSortIcons() {
        document.querySelectorAll('.sort-icon').forEach(icon => {
            const col = icon.dataset.col;
            if (!col) return;
            if (col === sortCol) {
                icon.classList.add('active');
                icon.textContent = sortDir === 'asc' ? '↑' : '↓';
            } else {
                icon.classList.remove('active');
                icon.textContent = '↕';
            }
        });
    }

    function sortTable() {
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            const idx = COL_INDEX[sortCol];
            const aVal = a.cells[idx]?.textContent.trim() ?? '';
            const bVal = b.cells[idx]?.textContent.trim() ?? '';

            // Date column: compare as real dates for accuracy
            if (sortCol === 'entered_at') {
                const aDate = new Date(TABLE_DATA[rows.indexOf(a)]?.entered_at || aVal);
                const bDate = new Date(TABLE_DATA[rows.indexOf(b)]?.entered_at || bVal);
                return sortDir === 'asc' ? aDate - bDate : bDate - aDate;
            }

            return sortDir === 'asc'
                ? aVal.localeCompare(bVal, undefined, { sensitivity: 'base' })
                : bVal.localeCompare(aVal, undefined, { sensitivity: 'base' });
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    // ── CSV Export ─────────────────────────────────────────────────────────────
    window.exportCSV = function () {
        if (!TABLE_DATA || TABLE_DATA.length === 0) {
            alert('No data to export.');
            return;
        }

        const headers = ['Test Name', 'Patient', 'Department', 'Doctor', 'Date', 'Results', 'Remarks'];
        const rows = TABLE_DATA.map(r => [
            r.test_name       ?? '',
            r.patient_name    ?? '',
            r.department_name ?? '',
            r.doctor_name     ?? 'N/A',
            r.entered_at      ?? '',
            (r.results        ?? '').replace(/[\r\n]+/g, ' '),
            (r.remarks        ?? ''),
        ]);

        const escape = v => '"' + String(v).replace(/"/g, '""') + '"';
        const csvContent = [headers, ...rows]
            .map(row => row.map(escape).join(','))
            .join('\r\n');

        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href     = url;
        link.download = `lab-report-<?= $date_from ?>-to-<?= $date_to ?>.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };
});
</script>

</body>
</html>