<?php
require_once '../../config.php';
requirePermission('settings');

 $tab = $_GET['tab'] ?? 'hospital';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_hospital') {
        $name = trim($_POST['hospital_name'] ?? '');
        $address = trim($_POST['hospital_address'] ?? '');
        $phone = trim($_POST['hospital_phone'] ?? '');
        $email = trim($_POST['hospital_email'] ?? '');
        $reg_no = trim($_POST['reg_number'] ?? '');
        
        // Upsert hospital settings
        $conn->query("DELETE FROM settings WHERE category = 'hospital'");
        $stmt = $conn->prepare("INSERT INTO settings (category, `key`, value) VALUES ('hospital', ?, ?)");
        $settings = [
            'name' => $name, 'address' => $address, 'phone' => $phone, 
            'email' => $email, 'reg_number' => $reg_no
        ];
        foreach ($settings as $k => $v) {
            $stmt->bind_param("ss", $k, $v);
            $stmt->execute();
        }
        setFlash('success', 'Hospital settings saved');
        header("Location: index.php?tab=hospital");
        exit;
    }
    
    if ($action === 'add_department') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                setFlash('success', 'Department added');
            } else {
                setFlash('error', 'Failed to add department');
            }
        }
        header("Location: index.php?tab=departments");
        exit;
    }
    
    if ($action === 'add_doctor') {
        $name = trim($_POST['name'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $opd_fee = (float)($_POST['opd_fee'] ?? 500);
        
        if (!empty($name) && $department_id > 0) {
            $stmt = $conn->prepare("INSERT INTO doctors (name, department_id, phone, email, qualification, specialization, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sissss", $name, $department_id, $phone, $email, $qualification, $specialization);
            if ($stmt->execute()) {
                // Add fee
                $doc_id = $stmt->insert_id;
                $conn->query("INSERT INTO doctor_fees (doctor_id, type, fee) VALUES ($doc_id, 'opd', $opd_fee)");
                setFlash('success', 'Doctor added');
            } else {
                setFlash('error', 'Failed to add doctor');
            }
        }
        header("Location: index.php?tab=doctors");
        exit;
    }
    
    if ($action === 'toggle_department') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        $conn->query("UPDATE departments SET status = $status WHERE id = $id");
        header("Location: index.php?tab=departments");
        exit;
    }
    
    if ($action === 'toggle_doctor') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 1);
        $conn->query("UPDATE doctors SET status = $status WHERE id = $id");
        header("Location: index.php?tab=doctors");
        exit;
    }
    
    if ($action === 'update_fee') {
        $id = (int)($_POST['fee_id'] ?? 0);
        $fee = (float)($_POST['fee'] ?? 0);
        $conn->query("UPDATE doctor_fees SET fee = $fee WHERE id = $id");
        setFlash('success', 'Fee updated');
        header("Location: index.php?tab=doctors");
        exit;
    }
}

// Load data
 $departments = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM doctors WHERE department_id = d.id AND status = 1) as doctor_count FROM departments ORDER BY name ASC");
 $doctors = $conn->query("
    SELECT doc.*, d.name as department_name, df.id as fee_id, df.fee as opd_fee 
    FROM doctors doc 
    JOIN departments d ON doc.department_id = d.id 
    LEFT JOIN doctor_fees df ON df.doctor_id = doc.id AND df.type = 'opd'
    ORDER BY doc.name ASC
");

// Hospital settings
 $hospital_settings = [];
 $settings_result = $conn->query("SELECT `key`, value FROM settings WHERE category = 'hospital'");
while ($row = $settings_result->fetch_assoc()) {
    $hospital_settings[$row['key']] = $row['value'];
}

 $flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth group" data-layout="vertical" data-sidebar="light" data-sidebar-size="lg" data-mode="light" data-topbar="light" data-skin="default" data-navbar="sticky" data-content="fluid" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Settings | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../../assets/images/favicon.ico">
    <script src="../../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../../assets/css/starcode2.css">
</head>

<body class="text-base bg-body-bg text-body font-public dark:text-zink-100 dark:bg-zink-800 group-data-[skin=bordered]:bg-body-bordered group-data-[skin=bordered]:dark:bg-zink-700">
<div class="group-data-[sidebar-size=sm]:min-h-sm group-data-[sidebar-size=sm]:relative">

<?php include '../sidenav.php'; ?>
<?php include '../topnav.php'; ?>

<div class="relative min-h-screen group-data-[sidebar-size=sm]:min-h-sm">

    <div class="group-data-[sidebar-size=lg]:ltr:md:ml-vertical-menu group-data-[sidebar-size=lg]:rtl:md:mr-vertical-menu group-data-[sidebar-size=md]:ltr:ml-vertical-menu-md group-data-[sidebar-size=md]:rtl:mr-vertical-menu-md group-data-[sidebar-size=sm]:ltr:ml-vertical-menu-sm group-data-[sidebar-size=sm]:rtl:mr-vertical-menu-sm pt-[calc(theme('spacing.header')_*_1)] pb-[calc(theme('spacing.header')_*_0.8)] px-4 group-data-[navbar=bordered]:pt-[calc(theme('spacing.header')_*_1.3)] group-data-[navbar=hidden]:pt-0 group-data-[layout=horizontal]:mx-auto group-data-[layout=horizontal]:max-w-screen-2xl group-data-[layout=horizontal]:px-0 group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:ltr:md:ml-auto group-data-[layout=horizontal]:group-data-[sidebar-size=lg]:rtl:md:mr-auto group-data-[layout=horizontal]:md:pt-[calc(theme('spacing.header')_*_1.6)] group-data-[layout=horizontal]:px-3 group-data-[layout=horizontal]:group-data-[navbar=hidden]:pt-[calc(theme('spacing.header')_*_0.9)]">
        <div class="container-fluid group-data-[content=boxed]:max-w-boxed mx-auto">

            <!-- Breadcrumb -->
            <div class="flex flex-col gap-2 py-4 md:flex-row md:items-center print:hidden">
                <div class="grow">
                    <h5 class="text-16">Settings</h5>
                </div>
                <ul class="flex items-center gap-2 text-sm font-normal shrink-0">
                    <li class="relative before:content-['\ea54'] before:font-remix ltr:before:-right-1 rtl:before:-left-1 before:absolute before:text-[18px] before:-top-[3px] ltr:pr-4 rtl:pl-4 before:text-slate-400 dark:text-zink-200">
                        <a href="../../index.php" class="text-slate-400 dark:text-zink-200">Home</a>
                    </li>
                    <li class="text-slate-700 dark:text-zink-100">Settings</li>
                </ul>
            </div>

            <?php if ($flash): ?>
            <div class="mb-4 px-4 py-3 rounded-md border <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-600 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?>">
                <div class="flex items-center gap-2"><i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="size-5 shrink-0"></i><span><?= $flash['msg'] ?></span></div>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="flex gap-1 mb-5 bg-slate-100 dark:bg-zink-700 p-1 rounded-lg w-fit">
                <a href="?tab=hospital" class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?= $tab === 'hospital' ? 'bg-white dark:bg-zink-600 text-slate-800 dark:text-zink-100 shadow-sm' : 'text-slate-500 dark:text-zink-300 hover:text-slate-700' ?>">
                    <i data-lucide="building" class="size-4 inline-block mr-1.5 -mt-0.5"></i>Hospital
                </a>
                <a href="?tab=departments" class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?= $tab === 'departments' ? 'bg-white dark:bg-zink-600 text-slate-800 dark:text-zink-100 shadow-sm' : 'text-slate-500 dark:text-zink-300 hover:text-slate-700' ?>">
                    <i data-lucide="layers" class="size-4 inline-block mr-1.5 -mt-0.5"></i>Departments
                </a>
                <a href="?tab=doctors" class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?= $tab === 'doctors' ? 'bg-white dark:bg-zink-600 text-slate-800 dark:text-zink-100 shadow-sm' : 'text-slate-500 dark:text-zink-300 hover:text-slate-700' ?>">
                    <i data-lucide="stethoscope" class="size-4 inline-block mr-1.5 -mt-0.5"></i>Doctors
                </a>
            </div>

            <!-- Hospital Tab -->
            <?php if ($tab === 'hospital'): ?>
            <div class="card">
                <div class="card-body">
                    <h6 class="text-15 mb-4">Hospital Information</h6>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="save_hospital">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Hospital Name</label>
                                <input type="text" name="hospital_name" value="<?= e($hospital_settings['name'] ?? 'MediFlow Hospital') ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Registration Number</label>
                                <input type="text" name="reg_number" value="<?= e($hospital_settings['reg_number'] ?? '') ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Address</label>
                                <textarea name="hospital_address" rows="2" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800"><?= e($hospital_settings['address'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Phone</label>
                                <input type="text" name="hospital_phone" value="<?= e($hospital_settings['phone'] ?? '') ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Email</label>
                                <input type="email" name="hospital_email" value="<?= e($hospital_settings['email'] ?? '') ?>" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                            <i data-lucide="save" class="size-4"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Departments Tab -->
            <?php if ($tab === 'departments'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Add Department</h6>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="add_department">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Name *</label>
                                <input type="text" name="name" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" placeholder="e.g., Cardiology">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Description</label>
                                <textarea name="description" rows="3" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" placeholder="Brief description..."></textarea>
                            </div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                                <i data-lucide="plus" class="size-4"></i> Add Department
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card lg:col-span-2">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">All Departments (<?= $departments->num_rows ?>)</h6>
                        <div class="space-y-2">
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-600 <?= $dept['status'] != 1 ? 'opacity-50' : '' ?>">
                                <div class="flex items-center justify-center size-10 rounded-md bg-slate-100 dark:bg-zink-600 shrink-0">
                                    <i data-lucide="layers" class="size-5 text-slate-500 dark:text-zink-300"></i>
                                </div>
                                <div class="grow min-w-0">
                                    <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($dept['name']) ?></p>
                                    <p class="text-xs text-slate-400 dark:text-zink-300"><?= $dept['doctor_count'] ?> doctor(s)</p>
                                </div>
                                <form method="POST" class="shrink-0">
                                    <input type="hidden" name="action" value="toggle_department">
                                    <input type="hidden" name="id" value="<?= $dept['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $dept['status'] == 1 ? 0 : 1 ?>">
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?= $dept['status'] == 1 ? 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400 hover:bg-red-100 dark:hover:bg-red-500/20 hover:text-red-600 dark:hover:text-red-400' : 'bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400 hover:bg-green-100 dark:hover:bg-green-500/20 hover:text-green-600 dark:hover:text-green-400' ?>">
                                        <?= $dept['status'] == 1 ? 'Active' : 'Inactive' ?>
                                    </button>
                                </form>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Doctors Tab -->
            <?php if ($tab === 'doctors'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">Add Doctor</h6>
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="add_doctor">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Name *</label>
                                <input type="text" name="name" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" placeholder="Dr. ...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Department *</label>
                                <select name="department_id" required class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                                    <option value="">Select Department</option>
                                    <?php 
                                    $dept_list = $conn->query("SELECT id, name FROM departments WHERE status = 1 ORDER BY name");
                                    while ($d = $dept_list->fetch_assoc()):
                                    ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Phone</label>
                                <input type="text" name="phone" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Qualification</label>
                                <input type="text" name="qualification" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" placeholder="MBBS, MD, etc.">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">Specialization</label>
                                <input type="text" name="specialization" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800" placeholder="Cardiology, etc.">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-zink-100 mb-1.5">OPD Fee</label>
                                <input type="number" name="opd_fee" value="500" min="0" step="0.01" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800">
                            </div>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                                <i data-lucide="plus" class="size-4"></i> Add Doctor
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card lg:col-span-2">
                    <div class="card-body">
                        <h6 class="text-15 mb-4">All Doctors (<?= $doctors->num_rows ?>)</h6>
                        <div class="space-y-2">
                            <?php while ($doc = $doctors->fetch_assoc()): ?>
                            <div class="flex items-center gap-3 p-3 rounded-md border border-slate-200 dark:border-zink-600 <?= $doc['status'] != 1 ? 'opacity-50' : '' ?>">
                                <div class="flex items-center justify-center size-10 rounded-md bg-green-100 dark:bg-green-500/20 shrink-0">
                                    <i data-lucide="stethoscope" class="size-5 text-green-500"></i>
                                </div>
                                <div class="grow min-w-0">
                                    <p class="text-sm font-medium text-slate-700 dark:text-zink-100"><?= e($doc['name']) ?></p>
                                    <p class="text-xs text-slate-400 dark:text-zink-300"><?= e($doc['department_name']) ?><?php if ($doc['qualification']): ?> · <?= e($doc['qualification']) ?><?php endif; ?></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <form method="POST" class="inline-flex">
                                        <input type="hidden" name="action" value="update_fee">
                                        <input type="hidden" name="fee_id" value="<?= $doc['fee_id'] ?>">
                                        <input type="number" name="fee" value="<?= $doc['opd_fee'] ?? 500 ?>" min="0" step="0.01" class="w-20 text-right form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 text-xs py-1">
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_doctor">
                                        <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $doc['status'] == 1 ? 0 : 1 ?>">
                                        <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?= $doc['status'] == 1 ? 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400' : 'bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400' ?>">
                                            <?= $doc['status'] == 1 ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include '../footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    </script>

</body>
</html>