<?php
require_once '../config/config.php';

$flash = getFlash();
$today = date('Y-m-d');

// Filters
$filter_status = $_GET['status'] ?? 'all';
$filter_date   = $_GET['date']   ?? $today;
$search        = trim($_GET['q'] ?? '');

// Build query
$where = ["DATE(lo.ordered_at) = '$filter_date'"];
if ($filter_status !== 'all') {
    $s = $conn->real_escape_string($filter_status);
    $where[] = "lo.status = '$s'";
}
if ($search) {
    $sq = $conn->real_escape_string($search);
    $where[] = "(p.name LIKE '%$sq%' OR lo.test_name LIKE '%$sq%' OR v.visit_id LIKE '%$sq%')";
}
$where_sql = implode(' AND ', $where);

$orders = $conn->query("
    SELECT lo.id, lo.test_name, lo.status, lo.ordered_at,
           v.visit_id, v.token_number,
           p.name as patient_name, p.age, p.gender, p.patient_id,
           lr.results, lr.remarks, lr.entered_at,
           CASE
               WHEN lr.results IS NOT NULL THEN 'completed'
               WHEN lo.status = 'collected'  THEN 'collected'
               ELSE lo.status
           END as display_status
    FROM lab_orders lo
    JOIN visits v ON v.id = lo.visit_id
    JOIN patients p ON p.id = v.patient_id
    LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
    WHERE $where_sql
    ORDER BY
        CASE lo.status WHEN 'ordered' THEN 1 WHEN 'collected' THEN 2 ELSE 3 END,
        lo.ordered_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Counts for filter tabs
$counts = [];
foreach (['ordered','collected','completed'] as $st) {
    $r = $conn->query("
        SELECT COUNT(*) as cnt FROM lab_orders lo
        JOIN visits v ON v.id = lo.visit_id
        JOIN patients p ON p.id = v.patient_id
        LEFT JOIN lab_results lr ON lr.lab_order_id = lo.id
        WHERE DATE(lo.ordered_at) = '$filter_date'
          AND lo.status = '$st'
    ");
    $counts[$st] = $r->fetch_assoc()['cnt'];
}
$counts['all'] = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Lab Orders | Consultation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
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
            <h5 class="text-16">Lab Orders</h5>
        </div>
        <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
            <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                <a href="index.php" class="text-slate-400 dark:text-zink-200">Dashboard</a>
            </li>
            <li class="text-slate-700 dark:text-zink-100">Lab Orders</li>
        </ul>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600' : 'bg-red-50 border-red-200 text-red-600' ?>">
        <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
    </div>
    <?php endif; ?>

    <!-- Filters bar -->
    <div class="card mb-5">
        <div class="card-body">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <!-- Search -->
                <div class="grow min-w-[180px]">
                    <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Search</label>
                    <div class="relative">
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Patient name, test or visit ID..." class="form-input ltr:pl-8 rtl:pr-8 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400">
                        <i data-lucide="search" class="absolute size-4 ltr:left-2.5 rtl:right-2.5 top-3 text-slate-400"></i>
                    </div>
                </div>
                <!-- Date -->
                <div>
                    <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Date</label>
                    <input type="date" name="date" value="<?= e($filter_date) ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                </div>
                <input type="hidden" name="status" value="<?= e($filter_status) ?>">
                <button type="submit" class="px-4 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">Filter</button>
                <a href="lab.php" class="px-4 py-2 text-sm text-slate-500 rounded-md border border-slate-200 hover:bg-slate-50 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-700 transition-colors">Reset</a>
            </form>
        </div>
    </div>

    <!-- Status tabs -->
    <div class="flex flex-wrap gap-2 mb-5">
        <?php
        $tabs = [
            'all'       => ['label' => 'All',       'color' => 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200'],
            'ordered'   => ['label' => 'Ordered',   'color' => 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400'],
            'collected' => ['label' => 'Collected', 'color' => 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400'],
            'completed' => ['label' => 'Completed', 'color' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400'],
        ];
        foreach ($tabs as $key => $tab):
            $active = $filter_status === $key;
        ?>
        <a href="?status=<?= $key ?>&date=<?= urlencode($filter_date) ?>&q=<?= urlencode($search) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors <?= $active ? $tab['color'] . ' ring-2 ring-offset-1 ring-current' : 'bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-200 hover:bg-slate-50 dark:hover:bg-zink-600' ?>">
            <?= $tab['label'] ?>
            <span class="inline-flex items-center justify-center size-5 rounded-full text-[11px] font-bold <?= $active ? 'bg-white/40' : 'bg-slate-100 dark:bg-zink-600' ?>"><?= $counts[$key] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders table / cards -->
    <div class="card" x-data="labPage()">
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
            <div class="py-16 text-center">
                <i data-lucide="flask-conical" class="size-12 mx-auto text-slate-300 dark:text-zink-500 mb-3"></i>
                <p class="text-sm text-slate-400 dark:text-zink-300">No lab orders found</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 dark:bg-zink-600 text-slate-500 dark:text-zink-200 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Token</th>
                            <th class="px-4 py-3 font-medium">Patient</th>
                            <th class="px-4 py-3 font-medium">Test</th>
                            <th class="px-4 py-3 font-medium">Ordered At</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Results</th>
                            <th class="px-4 py-3 font-medium text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php foreach ($orders as $o):
                            $status_badge = match($o['display_status']) {
                                'completed' => 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400',
                                'collected' => 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
                                default     => 'bg-yellow-100 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400',
                            };
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-zink-700 transition-colors">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center size-8 rounded-full bg-custom-100 dark:bg-custom-500/20 text-custom-500 text-xs font-bold">
                                    <?= str_pad($o['token_number'], 3, '0', STR_PAD_LEFT) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium"><?= e($o['patient_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($o['patient_id']) ?> &middot; <?= e($o['age']) ?>y <?= e($o['gender']) ?></p>
                            </td>
                            <td class="px-4 py-3 font-medium"><?= e($o['test_name']) ?></td>
                            <td class="px-4 py-3 text-slate-500 dark:text-zink-300 whitespace-nowrap">
                                <?= date('h:i A', strtotime($o['ordered_at'])) ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-[11px] font-medium rounded-full <?= $status_badge ?>">
                                    <?= ucfirst($o['display_status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 max-w-[200px]">
                                <?php if ($o['results']): ?>
                                <p class="text-sm truncate"><?= e($o['results']) ?></p>
                                <?php if ($o['remarks']): ?>
                                <p class="text-xs text-slate-400 truncate"><?= e($o['remarks']) ?></p>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-xs text-slate-300 dark:text-zink-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- View full results -->
                                    <?php if ($o['results']): ?>
                                    <button type="button"
                                            @click="openResults(<?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)"
                                            class="flex items-center justify-center size-8 rounded text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors"
                                            title="View Results">
                                        <i data-lucide="eye" class="size-4"></i>
                                    </button>
                                    <?php endif; ?>
                                    <!-- Go to patient consult -->
                                    <a href="consult.php?visit_id=<?= urlencode($o['visit_id']) ?>"
                                       class="flex items-center justify-center size-8 rounded text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors"
                                       title="Open Consultation">
                                        <i data-lucide="stethoscope" class="size-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Results Modal -->
        <div x-show="modal.open" x-cloak
             class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
             x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 bg-black/50" @click="modal.open = false"></div>
            <div class="relative w-full max-w-lg bg-white dark:bg-zink-700 rounded-xl shadow-2xl p-6 z-10">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h6 class="text-15 font-semibold" x-text="modal.test_name"></h6>
                        <p class="text-xs text-slate-400 dark:text-zink-300" x-text="modal.patient_name + ' · ' + modal.entered_at"></p>
                    </div>
                    <button @click="modal.open = false" class="flex items-center justify-center size-8 rounded-full hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                        <i data-lucide="x" class="size-4"></i>
                    </button>
                </div>
                <div class="mb-3">
                    <p class="text-xs font-medium text-slate-500 dark:text-zink-300 mb-1 uppercase tracking-wide">Results</p>
                    <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600 text-sm whitespace-pre-wrap" x-text="modal.results"></div>
                </div>
                <template x-if="modal.remarks">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-zink-300 mb-1 uppercase tracking-wide">Remarks</p>
                        <div class="p-3 rounded-md bg-slate-50 dark:bg-zink-600 text-sm" x-text="modal.remarks"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>
</div>
<?php include 'footer.php'; ?>
</div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function labPage() {
    return {
        modal: {
            open: false,
            test_name: '',
            patient_name: '',
            results: '',
            remarks: '',
            entered_at: ''
        },
        openResults(order) {
            this.modal.test_name    = order.test_name;
            this.modal.patient_name = order.patient_name;
            this.modal.results      = order.results;
            this.modal.remarks      = order.remarks || '';
            this.modal.entered_at   = order.entered_at ? new Date(order.entered_at).toLocaleString() : '';
            this.modal.open         = true;
        }
    };
}
</script>
</body>
</html>