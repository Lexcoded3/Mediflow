<?php
require_once '../config/config.php';
requirePermission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $role       = $_POST['role'] ?? 'receptionist';
        $password   = $_POST['password'] ?? 'password123';
        $phone      = trim($_POST['phone'] ?? '');

        if (empty($first_name) || empty($last_name)) {
            setFlash('error', 'First and last name are required');
        } else {
            // Generate staff_code
            $count      = $conn->query("SELECT COUNT(*) as cnt FROM staff")->fetch_assoc()['cnt'] + 1;
            $staff_code = 'STF-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $check = $conn->prepare("SELECT id FROM staff WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                setFlash('error', 'Email already exists');
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("INSERT INTO staff (staff_code, first_name, last_name, email, password, role, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssssss", $staff_code, $first_name, $last_name, $email, $hashed, $role, $phone);
                if ($stmt->execute()) {
                    setFlash('success', 'User added successfully');
                } else {
                    setFlash('error', 'Failed to add user: ' . $conn->error);
                }
            }
        }
        header("Location: users.php");
        exit;
    }

    if ($action === 'toggle_user') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        if ($id == $_SESSION['user_id']) {
            setFlash('error', 'Cannot disable your own account');
        } else {
            $stmt = $conn->prepare("UPDATE staff SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $status, $id);
            $stmt->execute();
        }
        header("Location: users.php");
        exit;
    }

    if ($action === 'reset_password') {
        $id     = (int)($_POST['id'] ?? 0);
        $hashed = password_hash('password123', PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE staff SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $id);
        $stmt->execute();
        setFlash('success', 'Password reset to: password123');
        header("Location: users.php");
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id == $_SESSION['user_id']) {
            setFlash('error', 'Cannot delete your own account');
        } else {
            $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            setFlash('success', 'User deleted');
        }
        header("Location: users.php");
        exit;
    }
}

// Fetch all staff
$users = $conn->query("
    SELECT s.*,
           CONCAT(s.first_name, ' ', s.last_name) AS name
    FROM staff s
    ORDER BY s.role, s.first_name ASC
");

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>User Management | MediFlow OPD</title>
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
                    <h5 class="text-16">User Management</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="settings/" class="text-slate-400 dark:text-zink-200">Settings</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Users</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i>
                    <span><?= e($flash['msg']) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                <!-- Add User Form -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Add New User</h6>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="add_user">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">First Name *</label>
                                <input type="text" name="first_name" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Last Name *</label>
                                <input type="text" name="last_name" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Email</label>
                                <input type="email" name="email" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Phone</label>
                                <input type="text" name="phone" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Password</label>
                                <input type="text" name="password" value="password123" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                <p class="text-[10px] text-slate-400 dark:text-zink-300 mt-1">Default: password123</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Role *</label>
                                <select name="role" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <?php foreach (ROLE_NAMES as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="w-full mt-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                                <i data-lucide="user-plus" class="size-4"></i> Add User
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Users List -->
                <div class="card lg:col-span-2">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">All Users (<?= $users->num_rows ?>)</h6>
                        <div class="space-y-2">
                            <?php while ($user = $users->fetch_assoc()):
                                $is_current  = ($user['id'] == $_SESSION['user_id']);
                                $role_colors = [
                                    'admin'          => 'bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400',
                                    'receptionist'   => 'bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400',
                                    'triage_nurse'   => 'bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400',
                                    'doctor'         => 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400',
                                    'lab_technician' => 'bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400',
                                    'radiologist'    => 'bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400',
                                    'pharmacist'     => 'bg-orange-100 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400',
                                    'billing'        => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400',
                                ];
                                $role_class = $role_colors[$user['role']] ?? 'bg-slate-100 dark:bg-zink-600 text-slate-600 dark:text-zink-200';
                            ?>
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-600 <?= $user['is_active'] != 1 ? 'opacity-50' : '' ?> <?= $is_current ? 'ring-2 ring-custom-500' : '' ?>">
                                <div class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-zink-600 shrink-0">
                                    <i data-lucide="<?= ROLE_ICONS[$user['role']] ?? 'user' ?>" class="size-5 text-slate-500 dark:text-zink-300"></i>
                                </div>
                                <div class="grow min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-slate-700 dark:text-zink-100">
                                            <?= e($user['name']) ?>
                                            <?= $is_current ? '<span class="text-[10px] text-custom-500">(you)</span>' : '' ?>
                                        </p>
                                        <span class="px-1.5 py-0.5 text-[10px] font-medium rounded <?= $role_class ?>">
                                            <?= ROLE_NAMES[$user['role']] ?? $user['role'] ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 dark:text-zink-300">
                                        <?= e($user['staff_code']) ?> · <?= e($user['email'] ?: 'No email') ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <?php if (!$is_current): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-sky-500 hover:bg-sky-50 dark:hover:bg-sky-500/10 transition-colors" title="Reset Password">
                                            <i data-lucide="key" class="size-4"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $user['is_active'] == 1 ? 0 : 1 ?>">
                                        <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-yellow-500 hover:bg-yellow-50 dark:hover:bg-yellow-500/10 transition-colors" title="<?= $user['is_active'] == 1 ? 'Disable' : 'Enable' ?>">
                                            <i data-lucide="<?= $user['is_active'] == 1 ? 'user-x' : 'user-check' ?>" class="size-4"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors" title="Delete">
                                            <i data-lucide="trash-2" class="size-4"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
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