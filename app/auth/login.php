<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'app/' . getUserDefaultModule() . '/index.php';
    unset($_SESSION['redirect_after_login']);
    header("Location: " . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); // This will accept staff_code OR email
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        // FIXED: Using staff_code instead of username, and concatenating name
        $stmt = $conn->prepare("SELECT id, staff_code, CONCAT(first_name, ' ', last_name) as name, email, password, role, is_active as status FROM staff WHERE staff_code = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] != 1) {
                $error = 'Account is disabled. Contact administrator.';
            } else {
                // FIXED: Passing staff_code as the 'username' identifier
                loginUser($user['id'], $user['staff_code'], $user['name'], $user['email'], $user['role']);
                
                $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'app/' . getUserDefaultModule() . '/index.php';
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
                exit;
            }
        } else {
            $error = 'Invalid credentials';
        }
    }
}

function getUserDefaultModule(): string {
    $role = $_SESSION['user_role'] ?? ''; // loginUser usually sets 'user_role'
    $defaults = [
        'admin'          => 'dashboard',
        'receptionist'   => 'reception',     // Matches DB ENUM
        'triage_nurse'   => 'triage',        // Matches DB ENUM
        'doctor'         => 'consultation',
        'lab_technician' => 'lab',           // Matches DB ENUM
        'radiologist'    => 'scan',          // Matches DB ENUM
        'pharmacist'     => 'pharmacy',
        'billing'        => 'billing',
    ];
    return $defaults[$role] ?? 'reception';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/starcode2.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 dark:from-zink-900 dark:to-zink-800 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center size-16 bg-custom-500 rounded-2xl mb-4">
                <i data-lucide="activity" class="size-8 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-zink-100">MediFlow OPD</h1>
            <p class="text-slate-500 dark:text-zink-300 mt-1">Hospital Management System</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white dark:bg-zink-800 rounded-xl shadow-lg border border-slate-200 dark:border-zink-700 p-6">
            <h2 class="text-lg font-semibold text-slate-800 dark:text-zink-100 mb-1">Sign In</h2>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-6">Enter your credentials to continue</p>

            <?php if ($error): ?>
            <div class="mb-4 px-4 py-3 rounded-md border bg-red-50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
                <div class="flex items-center gap-2">
                    <i data-lucide="alert-circle" class="size-5 shrink-0"></i>
                    <span class="text-sm"><?= e($error) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-zink-200 mb-1.5">Username or Email</label>
                    <div class="relative">
                        <input type="text" name="username" required autofocus
                               class="ltr:pl-10 rtl:pr-10 form-input border-slate-200 dark:border-zink-600 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 w-full"
                               placeholder="Enter username or email"
                               value="<?= e($_POST['username'] ?? '') ?>">
                        <i data-lucide="user" class="inline-block size-4 absolute ltr:left-3 rtl:right-3 top-3 text-slate-400 dark:text-zink-300"></i>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 dark:text-zink-200 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" required
                               class="ltr:pl-10 rtl:pr-10 form-input border-slate-200 dark:border-zink-600 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 w-full"
                               placeholder="Enter password">
                        <i data-lucide="lock" class="inline-block size-4 absolute ltr:left-3 rtl:right-3 top-3 text-slate-400 dark:text-zink-300"></i>
                    </div>
                </div>

                <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="size-4"></i> Sign In
                </button>
            </form>
        </div>

        <!-- Demo Credentials -->
        <div class="mt-6 bg-white/50 dark:bg-zink-800/50 rounded-xl border border-slate-200 dark:border-zink-700 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase mb-3">Demo Credentials (password: password123)</p>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('admin', '')">
                    <i data-lucide="shield" class="size-3 text-purple-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Admin</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('reception1', '')">
                    <i data-lucide="clipboard-list" class="size-3 text-blue-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Reception</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('nurse1', '')">
                    <i data-lucide="heart-pulse" class="size-3 text-red-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Triage Nurse</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('dr_rahim', '')">
                    <i data-lucide="stethoscope" class="size-3 text-green-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Doctor</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('lab_tech', '')">
                    <i data-lucide="test-tube" class="size-3 text-sky-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Lab Tech</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('radiologist', '')">
                    <i data-lucide="scan" class="size-3 text-purple-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Radiologist</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('pharmacist', '')">
                    <i data-lucide="pill" class="size-3 text-orange-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Pharmacist</span>
                </div>
                <div class="flex items-center gap-2 px-2 py-1.5 rounded bg-slate-50 dark:bg-zink-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors" onclick="fillCreds('billing_staff', '')">
                    <i data-lucide="receipt" class="size-3 text-yellow-500"></i>
                    <span class="text-slate-600 dark:text-zink-200">Billing</span>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/layout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    function fillCreds(username) {
        document.querySelector('input[name="username"]').value = username;
        document.querySelector('input[name="password"]').value = 'password123';
        document.querySelector('form').submit();
    }
    </script>
</body>
</html>