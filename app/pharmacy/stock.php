<?php
require_once '../config/config.php';

$flash = getFlash();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD NEW MEDICINE ---
    if ($action === 'add') {
        $name        = trim($_POST['medicine_name'] ?? '');
        $generic     = trim($_POST['generic_name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $unit        = trim($_POST['unit'] ?? 'Tablet');
        $stock_qty   = (int)($_POST['stock_qty'] ?? 0);
        $min_stock   = (int)($_POST['min_stock'] ?? 10);
        $price       = (float)($_POST['price'] ?? 0);

        if (empty($name)) {
            setFlash('error', 'Medicine name is required');
            header("Location: stock.php"); exit;
        }

        $stmt = $conn->prepare("INSERT INTO pharmacy_stock (medicine_name, generic_name, category, unit, stock_qty, min_stock, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssssiid", $name, $generic, $category, $unit, $stock_qty, $min_stock, $price);
        if ($stmt->execute()) {
            setFlash('success', "Medicine '$name' added successfully");
        } else {
            setFlash('error', 'Failed to add medicine');
        }
        header("Location: stock.php"); exit;
    }

    // --- EDIT MEDICINE ---
    if ($action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['medicine_name'] ?? '');
        $generic     = trim($_POST['generic_name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $unit        = trim($_POST['unit'] ?? 'Tablet');
        $min_stock   = (int)($_POST['min_stock'] ?? 10);
        $price       = (float)($_POST['price'] ?? 0);
        $status      = (int)($_POST['status'] ?? 1);

        $stmt = $conn->prepare("UPDATE pharmacy_stock SET medicine_name=?, generic_name=?, category=?, unit=?, min_stock=?, price=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ssssiidi", $name, $generic, $category, $unit, $min_stock, $price, $status, $id);
        if ($stmt->execute()) {
            setFlash('success', "Medicine updated successfully");
        } else {
            setFlash('error', 'Failed to update medicine');
        }
        header("Location: stock.php"); exit;
    }

    // --- RESTOCK ---
    if ($action === 'restock') {
        $id       = (int)($_POST['id'] ?? 0);
        $qty      = (int)($_POST['qty'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $staff_id = $_SESSION['staff_id'] ?? null;

        if ($qty <= 0) {
            setFlash('error', 'Quantity must be greater than 0');
            header("Location: stock.php"); exit;
        }

        $conn->begin_transaction();
        try {
            // Get current stock
            $s = $conn->prepare("SELECT * FROM pharmacy_stock WHERE id = ? FOR UPDATE");
            $s->bind_param("i", $id);
            $s->execute();
            $stock = $s->get_result()->fetch_assoc();
            $s->close();

            if (!$stock) throw new Exception("Medicine not found");

            $prev_qty = $stock['stock_qty'];
            $new_qty  = $prev_qty + $qty;

            // Update stock
            $u = $conn->prepare("UPDATE pharmacy_stock SET stock_qty = ?, updated_at = NOW() WHERE id = ?");
            $u->bind_param("ii", $new_qty, $id);
            $u->execute();
            $u->close();

            // Find matching drug for movement log
            $d = $conn->prepare("SELECT id FROM drugs WHERE LOWER(drug_name) LIKE LOWER(?) AND is_active = 1 LIMIT 1");
            $like = '%' . $stock['medicine_name'] . '%';
            $d->bind_param("s", $like);
            $d->execute();
            $drug = $d->get_result()->fetch_assoc();
            $d->close();

            if ($drug) {
                $mov_type = 'stock_in';
                $ref_type = 'purchase';
                $m = $conn->prepare("INSERT INTO stock_movements (drug_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, performed_by_staff_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $m->bind_param("isiisssi", $drug['id'], $mov_type, $qty, $prev_qty, $new_qty, $ref_type, $notes, $staff_id);
                $m->execute();
                $m->close();
            }

            $conn->commit();
            setFlash('success', "Restocked {$stock['medicine_name']} by $qty units. New stock: $new_qty");
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('error', 'Restock failed: ' . $e->getMessage());
        }
        header("Location: stock.php"); exit;
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE pharmacy_stock SET status = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setFlash('success', 'Medicine deactivated successfully');
        } else {
            setFlash('error', 'Failed to deactivate medicine');
        }
        header("Location: stock.php"); exit;
    }
}

// 1. Initialize Filtering Variables FIRST
$search      = trim($_GET['search'] ?? '');
$filter_cat  = trim($_GET['category'] ?? '');
$filter_stat = $_GET['status'] ?? 'all';

$where  = []; // Initialize as array to use with implode later
$params = [];
$types  = '';

// 2. Build the WHERE clause logic
if ($search) {
    $where[]  = "(medicine_name LIKE ? OR generic_name LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($filter_cat) {
    $where[]  = "category = ?";
    $params[] = $filter_cat;
    $types   .= 's';
}
if ($filter_stat === 'active') {
    $where[] = "status = 1";
} elseif ($filter_stat === 'inactive') {
    $where[] = "status = 0";
} elseif ($filter_stat === 'low') {
    $where[] = "stock_qty <= min_stock AND status = 1";
} elseif ($filter_stat === 'out') {
    $where[] = "stock_qty = 0 AND status = 1";
} else {
    $where[] = "status = 1";
}

// 3. Now handle Pagination using the variables defined above
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 6;
$offset   = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) as total FROM pharmacy_stock";
if (!empty($where)) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages   = ceil($total_records / $per_page);
$count_stmt->close();

// 4. Fetch the actual paginated records
$sql = "SELECT * FROM pharmacy_stock";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY medicine_name ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$paginated_params = $params;
$paginated_types  = $types . 'ii';
$paginated_params[] = $per_page;
$paginated_params[] = $offset;

$stmt->bind_param($paginated_types, ...$paginated_params);
$stmt->execute();
$medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status = 1) as active,
        SUM(status = 1 AND stock_qty = 0) as out_of_stock,
        SUM(status = 1 AND stock_qty > 0 AND stock_qty <= min_stock) as low_stock,
        SUM(status = 1 AND stock_qty > min_stock) as adequate
    FROM pharmacy_stock
")->fetch_assoc();

// Categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM pharmacy_stock WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Stock Management | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>[x-cloak]{display:none!important}</style>
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
                    <h5 class="text-16">Stock Management</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400">
                        <a href="index.php" class="text-slate-400">Pharmacy</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Stock</li>
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

            <!-- Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="card">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-14 rounded-md bg-custom-100 dark:bg-custom-500/20 shrink-0">
                                <i data-lucide="package" class="size-5 text-custom-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Total Medicines</p>
                                <h4 class="text-xl font-bold"><?= $stats['total'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='stock.php?status=adequate'">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-14 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                <i data-lucide="check-circle" class="size-5 text-green-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Adequate Stock</p>
                                <h4 class="text-xl font-bold text-green-600"><?= $stats['adequate'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='stock.php?status=low'">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-14 rounded-md bg-yellow-100 dark:bg-yellow-500/20 shrink-0">
                                <i data-lucide="alert-triangle" class="size-5 text-yellow-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Low Stock</p>
                                <h4 class="text-xl font-bold text-yellow-600"><?= $stats['low_stock'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card cursor-pointer hover:shadow-md transition-shadow" onclick="window.location='stock.php?status=out'">
                    <div class="card-body">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-14 rounded-md bg-red-100 dark:bg-red-500/20 shrink-0">
                                <i data-lucide="package-x" class="size-5 text-red-500"></i>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 dark:text-zink-200">Out of Stock</p>
                                <h4 class="text-xl font-bold text-red-600"><?= $stats['out_of_stock'] ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div x-data="stockPage()">
            <div class="card">
                <div class="card-body">

                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <!-- change this line -->
                    <h6 class="text-15">Medicine Stock <span class="text-slate-400 font-normal">(<?= $total_records ?>)</span></h6>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Search -->
                            <form method="GET" class="flex items-center gap-2">
                                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search medicine..."
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm w-48 placeholder:text-slate-400">
                                <select name="category" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['category']) ?>" <?= $filter_cat === $cat['category'] ? 'selected' : '' ?>><?= e($cat['category']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="status" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-sm">
                                    <option value="all" <?= $filter_stat === 'all' ? 'selected' : '' ?>>All Active</option>
                                    <option value="low" <?= $filter_stat === 'low' ? 'selected' : '' ?>>Low Stock</option>
                                    <option value="out" <?= $filter_stat === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                                    <option value="inactive" <?= $filter_stat === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <button type="submit" class="px-3 py-2 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                                    <i data-lucide="search" class="size-4"></i>
                                </button>
                                <?php if ($search || $filter_cat || $filter_stat !== 'all'): ?>
                                <a href="stock.php" class="px-3 py-2 text-sm text-slate-500 rounded-md bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:hover:bg-zink-500 transition-colors">
                                    <i data-lucide="x" class="size-4"></i>
                                </a>
                                <?php endif; ?>
                            </form>
                            <!-- Add Button -->
                            <button type="button" @click="openAdd()"
                                    class="px-4 py-2 text-sm text-white rounded-md bg-green-500 hover:bg-green-600 transition-colors flex items-center gap-1.5">
                                <i data-lucide="plus" class="size-4"></i> Add Medicine
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <?php if (empty($medicines)): ?>
                    <div class="py-12 text-center">
                        <i data-lucide="package-x" class="size-12 mx-auto mb-3 text-slate-300 dark:text-zink-400"></i>
                        <p class="text-slate-400 dark:text-zink-300">No medicines found</p>
                    </div>
                    <?php else: ?>
                    <div class="-mx-5 overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="ltr:text-left rtl:text-right bg-slate-100 text-slate-500 dark:text-zink-200 dark:bg-zink-600">
                                <tr>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">#</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Medicine</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Category</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Unit</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Stock</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Min Stock</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Price</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Status</th>
                                    <th class="px-3.5 py-2.5 first:pl-5 last:pr-5 font-semibold border-y border-slate-200 dark:border-zink-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $i => $med):
                                    $is_out  = $med['stock_qty'] <= 0;
                                    $is_low  = !$is_out && $med['stock_qty'] <= $med['min_stock'];
                                    $is_good = !$is_out && !$is_low;
                                ?>
                                <tr class="<?= $is_out ? 'bg-red-50/40 dark:bg-red-500/5' : ($is_low ? 'bg-yellow-50/40 dark:bg-yellow-500/5' : '') ?>">
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-slate-400 text-sm"><?= $i + 1 ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <p class="text-sm font-medium"><?= e($med['medicine_name']) ?></p>
                                        <?php if ($med['generic_name']): ?>
                                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($med['generic_name']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm text-slate-500 dark:text-zink-200"><?= e($med['category'] ?: '-') ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= e($med['unit']) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <span class="font-bold text-sm <?= $is_out ? 'text-red-600' : ($is_low ? 'text-yellow-600' : 'text-green-600') ?>">
                                            <?= $med['stock_qty'] ?>
                                        </span>
                                        <?php if ($is_out): ?>
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-red-100 text-red-600 border border-red-200">OUT</span>
                                        <?php elseif ($is_low): ?>
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-medium rounded bg-yellow-100 text-yellow-600 border border-yellow-200">LOW</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm text-slate-500"><?= $med['min_stock'] ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500 text-sm"><?= number_format($med['price'], 2) ?></td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <?php if ($med['status']): ?>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded border bg-green-100 border-green-200 text-green-600">Active</span>
                                        <?php else: ?>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded border bg-slate-100 border-slate-200 text-slate-500">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3.5 py-2.5 first:pl-5 last:pr-5 border-y border-slate-200 dark:border-zink-500">
                                        <div class="flex items-center gap-1">
                                            <!-- Restock -->
                                            <button type="button"
                                                    @click="openRestock(<?= htmlspecialchars(json_encode(['id' => $med['id'], 'name' => $med['medicine_name'], 'current' => $med['stock_qty'], 'unit' => $med['unit']])) ?>)"
                                                    class="flex items-center justify-center size-8 rounded text-green-500 bg-green-50 hover:bg-green-100 dark:bg-green-500/10 dark:hover:bg-green-500/20 transition-colors" title="Restock">
                                                <i data-lucide="plus-circle" class="size-3.5"></i>
                                            </button>
                                            <!-- Edit -->
                                            <button type="button"
                                                    @click="openEdit(<?= htmlspecialchars(json_encode($med)) ?>)"
                                                    class="flex items-center justify-center size-8 rounded text-sky-500 bg-sky-50 hover:bg-sky-100 dark:bg-sky-500/10 dark:hover:bg-sky-500/20 transition-colors" title="Edit">
                                                <i data-lucide="pencil" class="size-3.5"></i>
                                            </button>
                                            <!-- Deactivate -->
                                            <?php if ($med['status']): ?>
                                            <button type="button"
                                                    @click="confirmDelete(<?= $med['id'] ?>, '<?= addslashes($med['medicine_name']) ?>')"
                                                    class="flex items-center justify-center size-8 rounded text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 transition-colors" title="Deactivate">
                                                <i data-lucide="trash-2" class="size-3.5"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_pages > 1): ?>
                            <div class="flex items-center justify-between mt-4 px-2">
                                <p class="text-sm text-slate-500 dark:text-zink-200">
                                    Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> medicines
                                </p>
                                <div class="flex items-center gap-1">
                                    <!-- Previous -->
                                    <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                       class="flex items-center justify-center size-8 rounded-md border border-slate-200 dark:border-zink-500 text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                                        <i data-lucide="chevron-left" class="size-4"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                                       class="flex items-center justify-center size-8 rounded-md border text-sm transition-colors
                                              <?= $p === $page
                                                  ? 'bg-custom-500 border-custom-500 text-white'
                                                  : 'border-slate-200 dark:border-zink-500 text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600' ?>">
                                        <?= $p ?>
                                    </a>
                                    <?php endfor; ?>

                                    <!-- Next -->
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                       class="flex items-center justify-center size-8 rounded-md border border-slate-200 dark:border-zink-500 text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                                        <i data-lucide="chevron-right" class="size-4"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============ MODALS ============ -->

            <!-- Add Medicine Modal -->
   

            <!-- Add Modal -->
            <div x-show="showAdd"  x-cloak x-transition.opacity class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 dark:bg-black/60 p-4">
                <div class="bg-white dark:bg-zink-700 rounded-lg shadow-xl w-70 max-w-md" @click.outside="showAdd = false">
                    <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-zink-500">
                        <h5 class="text-15 font-semibold">Add New Medicine</h5>
                        <button @click="showAdd = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-zink-200"><i data-lucide="x" class="size-5"></i></button>
                    </div>
                    <form method="POST" action="stock.php" class="p-5">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div class="sm:col-span-2">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Medicine Name <span class="text-red-500">*</span></label>
                                <input type="text" name="medicine_name" required placeholder="e.g. Paracetamol 500mg"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Generic Name</label>
                                <input type="text" name="generic_name" placeholder="e.g. Paracetamol"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Category</label>
                                <input type="text" name="category" placeholder="e.g. Analgesic"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Unit</label>
                                <select name="unit" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                                    <option>Tablet</option>
                                    <option>Capsule</option>
                                    <option>Syrup</option>
                                    <option>Injection</option>
                                    <option>Sachet</option>
                                    <option>Inhaler</option>
                                    <option>Cream</option>
                                    <option>Drops</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Initial Stock Qty</label>
                                <input type="number" name="stock_qty" value="0" min="0"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Min Stock Level</label>
                                <input type="number" name="min_stock" value="10" min="0"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Unit Price</label>
                                <input type="number" name="price" value="0.00" min="0" step="0.01"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2 border-t border-slate-100 dark:border-zink-500">
                            <button type="submit" class="flex-1 px-4 py-2.5 text-sm text-white rounded-md bg-green-500 hover:bg-green-600 transition-colors">
                                <i data-lucide="plus" class="inline-block size-4 mr-1"></i> Add Medicine
                            </button>
                            <button type="button" @click="showAdd = false" class="px-4 py-2.5 text-sm text-slate-500 rounded-md bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:hover:bg-zink-500 transition-colors">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Modal -->
            <div x-show="showEdit" x-transition.opacity class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 dark:bg-black/60 p-4">
                <div class="bg-white dark:bg-zink-700 rounded-lg shadow-xl w-70 max-w-md" @click.outside="showEdit = false">
                    <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-zink-500">
                        <h5 class="text-15 font-semibold">Edit Medicine</h5>
                        <button @click="showEdit = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-zink-200"><i data-lucide="x" class="size-5"></i></button>
                    </div>
                    <form method="POST" action="stock.php" class="p-5">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" x-model="editData.id">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div class="sm:col-span-2">
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Medicine Name <span class="text-red-500">*</span></label>
                                <input type="text" name="medicine_name" x-model="editData.medicine_name" required
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Generic Name</label>
                                <input type="text" name="generic_name" x-model="editData.generic_name"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Category</label>
                                <input type="text" name="category" x-model="editData.category"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Unit</label>
                                <select name="unit" x-model="editData.unit" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                                    <option>Tablet</option>
                                    <option>Capsule</option>
                                    <option>Syrup</option>
                                    <option>Injection</option>
                                    <option>Sachet</option>
                                    <option>Inhaler</option>
                                    <option>Cream</option>
                                    <option>Drops</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Min Stock Level</label>
                                <input type="number" name="min_stock" x-model="editData.min_stock" min="0"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Unit Price</label>
                                <input type="number" name="price" x-model="editData.price" min="0" step="0.01"
                                       class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                            </div>
                            <div>
                                <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Status</label>
                                <select name="status" x-model="editData.status" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2 border-t border-slate-100 dark:border-zink-500">
                            <button type="submit" class="flex-1 px-4 py-2.5 text-sm text-white rounded-md bg-custom-500 hover:bg-custom-600 transition-colors">
                                <i data-lucide="save" class="inline-block size-4 mr-1"></i> Save Changes
                            </button>
                            <button type="button" @click="showEdit = false" class="px-4 py-2.5 text-sm text-slate-500 rounded-md bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:hover:bg-zink-500 transition-colors">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Restock Modal -->
            <div x-show="showRestock" x-transition.opacity class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 dark:bg-black/60 p-4">
                <div class="bg-white dark:bg-zink-700 rounded-lg shadow-xl w-70 max-w-md" @click.outside="showRestock = false">
                    <div class="flex items-center justify-between p-5 border-b border-slate-200 dark:border-zink-500">
                        <h5 class="text-15 font-semibold">Restock Medicine</h5>
                        <button @click="showRestock = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-zink-200"><i data-lucide="x" class="size-5"></i></button>
                    </div>
                    <form method="POST" action="stock.php" class="p-5">
                        <input type="hidden" name="action" value="restock">
                        <input type="hidden" name="id" x-model="restockData.id">
                        <div class="mb-4 p-3 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                            <p class="text-sm font-semibold" x-text="restockData.name"></p>
                            <p class="text-xs text-slate-500 dark:text-zink-200 mt-0.5">
                                Current stock: <strong x-text="restockData.current + ' ' + restockData.unit + 's'"></strong>
                            </p>
                        </div>
                        <div class="mb-4">
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Quantity to Add <span class="text-red-500">*</span></label>
                            <input type="number" name="qty" required min="1" placeholder="e.g. 100"
                                   class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 text-lg font-semibold text-center placeholder:text-slate-400">
                        </div>
                        <div class="mb-4">
                            <label class="block mb-1.5 text-sm font-medium text-slate-700 dark:text-zink-200">Notes</label>
                            <input type="text" name="notes" placeholder="e.g. Purchase order #123"
                                   class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400 text-sm">
                        </div>
                        <div class="flex gap-3 pt-2 border-t border-slate-100 dark:border-zink-500">
                            <button type="submit" class="flex-1 px-4 py-2.5 text-sm text-white rounded-md bg-green-500 hover:bg-green-600 transition-colors">
                                <i data-lucide="plus-circle" class="inline-block size-4 mr-1"></i> Confirm Restock
                            </button>
                            <button type="button" @click="showRestock = false" class="px-4 py-2.5 text-sm text-slate-500 rounded-md bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 dark:hover:bg-zink-500 transition-colors">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirm Modal -->
            <div x-show="showDelete" x-transition.opacity class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 dark:bg-black/60 p-4">
                <div class="bg-white dark:bg-zink-700 rounded-lg shadow-xl w-70 max-w-sm p-6" @click.outside="showDelete = false">
                    <div class="flex items-center justify-center size-14 rounded-full bg-red-100 dark:bg-red-500/20 mx-auto mb-4">
                        <i data-lucide="alert-triangle" class="size-7 text-red-500"></i>
                    </div>
                    <h5 class="text-center text-15 font-semibold mb-2">Deactivate Medicine?</h5>
                    <p class="text-center text-sm text-slate-500 dark:text-zink-200 mb-5">
                        <strong x-text="deleteData.name"></strong> will be marked inactive and hidden from dispensing.
                    </p>
                    <form method="POST" action="stock.php" class="flex gap-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" x-model="deleteData.id">
                        <button type="submit" class="flex-1 px-4 py-2.5 text-sm text-white rounded-md bg-red-500 hover:bg-red-600 transition-colors">Yes, Deactivate</button>
                        <button type="button" @click="showDelete = false" class="flex-1 px-4 py-2.5 text-sm text-slate-500 rounded-md bg-slate-100 hover:bg-slate-200 dark:bg-zink-600 transition-colors">Cancel</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <?php include 'footer.php'; ?>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function stockPage() {
    return {
        showAdd:     <?= isset($_GET['add']) ? 'true' : 'false' ?>,
        showEdit:    false,
        showRestock: false,
        showDelete:  false,
        editData:    {},
        restockData: {},
        deleteData:  {},

        openAdd() { this.showAdd = true; },

        openEdit(med) {
            this.editData = {...med};
            this.showEdit = true;
        },

        openRestock(data) {
            this.restockData = {...data};
            this.showRestock = true;
        },

        confirmDelete(id, name) {
            this.deleteData = {id, name};
            this.showDelete = true;
        }
    };
}
</script>
</body>
</html>