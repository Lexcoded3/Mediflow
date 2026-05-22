<?php
//auth.php
/**
 * Authentication & Authorization Helpers (CLEAN VERSION)
 */

// ✅ Match EXACT DB roles
define('ROLE_PERMISSIONS', [
    'admin' => ['admin','dashboard','reception','triage','consultation','lab','scan','pharmacy','billing','settings','reports'],
    
    'receptionist' => ['reception'],
    'triage_nurse' => ['triage'],
    'doctor' => ['consultation','lab','scan'],
    'lab_technician' => ['lab'],
    'radiologist' => ['scan'],
    'pharmacist' => ['pharmacy'],
    'billing' => ['billing'],
]);

// Display names
define('ROLE_NAMES', [
    'admin' => 'Administrator',
    'receptionist' => 'Receptionist',
    'triage_nurse' => 'Nurse / Triage',
    'doctor' => 'Doctor',
    'lab_technician' => 'Lab Technician',
    'radiologist' => 'Radiologist',
    'pharmacist' => 'Pharmacist',
    'billing' => 'Billing Officer',
]);

// Icons
define('ROLE_ICONS', [
    'admin' => 'shield',
    'receptionist' => 'clipboard-list',
    'triage_nurse' => 'heart-pulse',
    'doctor' => 'stethoscope',
    'lab_technician' => 'test-tube',
    'radiologist' => 'scan',
    'pharmacist' => 'pill',
    'billing' => 'receipt',
]);

// ================= CORE =================

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;

    $role = $_SESSION['role'] ?? '';

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $role,
        'role_name' => ROLE_NAMES[$role] ?? 'Unknown',
        'role_icon' => ROLE_ICONS[$role] ?? 'user',
    ];
}

function hasPermission(string $module): bool {
    if (!isLoggedIn()) return false;

    $role = $_SESSION['role'] ?? '';
    $permissions = ROLE_PERMISSIONS[$role] ?? [];

    return in_array($module, $permissions);
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// ================= SECURITY =================

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

function requirePermission(string $module): void {
    requireLogin();

    if (!hasPermission($module)) {
        http_response_code(403);
        include BASE_PATH . 'auth/403.php';
        exit;
    }
}

// ================= AUTH =================

// Update this in auth.php
function loginUser(int $id, string $username, string $name, string $email, string $role, ?int $dept_id = null): void {
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = trim(strtolower($role));
    
    // Store department ID if it exists (for doctors)
    $_SESSION['dept_id'] = $dept_id; 

    $_SESSION['login_time'] = time();
}
function logoutUser(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

// ================= NAVIGATION =================

// 🔥 THIS FIXES YOUR MAIN PROBLEM
function redirectByRole(): void {
    if (!isLoggedIn()) return;

    $role = $_SESSION['role'];

    switch ($role) {
        case 'admin':
            header("Location: " . BASE_URL . "mediflow/app/admin/index.php");
            break;

        case 'receptionist':
            header("Location: " . BASE_URL . "mediflow/app/reception/index.php");
            break;

        case 'triage_nurse':
            header("Location: " . BASE_URL . "mediflow/app/triage/index.php");
            break;

        case 'doctor':
            header("Location: " . BASE_URL . "mediflow/app/consultation/index.php");
            break;

        case 'lab_technician':
            header("Location: " . BASE_URL . "mediflow/app/lab/index.php");
            break;

        case 'radiologist':
            header("Location: " . BASE_URL . "mediflow/app/scan/index.php");
            break;

        case 'pharmacist':
            header("Location: " . BASE_URL . "mediflow/app/pharmacy/index.php");
            break;

        case 'billing':
            header("Location: " . BASE_URL . "mediflow/app/billing/index.php");
            break;

        default:
            header("Location: " . BASE_URL . "auth/login.php");
    }

    exit;
}

function getAccessibleModules(): array {
    if (!isLoggedIn()) return [];
    return ROLE_PERMISSIONS[$_SESSION['role']] ?? [];
}