<?php
session_start();
$required_role = null;
require_once '../config/config.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error        = '';
$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $field_errors['username'] = 'Username or email is required';
    }
    if (empty($password)) {
        $field_errors['password'] = 'Password is required';
    }

    if (empty($field_errors)) {
        $stmt = $conn->prepare("
    SELECT
        s.id, s.staff_code,
        CONCAT(s.first_name, ' ', s.last_name) AS name,
        s.email, s.password, s.role, s.is_active,
        d.id             AS doctor_id,
        d.department_id  AS dept_id
    FROM staff s
    LEFT JOIN doctors d ON (d.staff_id = s.id OR d.email = s.email)
    WHERE (s.staff_code = ? OR s.email = ?)
    LIMIT 1
");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
    if ($user['is_active'] != 1) {
        $error = 'Your account has been disabled. Please contact your administrator.';
    } else {
        loginUser(
    $user['id'],
    $user['staff_code'],
    $user['name'],
    $user['email'],
    $user['role'],
    $user['dept_id'],    // doctors.department_id
    $user['doctor_id']  // doctors.id
);

        $role_routes = [
            'admin'          => BASE_URL . 'mediflow/app/admin/index.php',
            'receptionist'   => BASE_URL . 'mediflow/app/reception/index.php',
            'triage_nurse'   => BASE_URL . 'mediflow/app/triage/index.php',
            'doctor'         => BASE_URL . 'mediflow/app/consultation/index.php',
            'lab_technician' => BASE_URL . 'mediflow/app/lab/index.php',
            'radiologist'    => BASE_URL . 'mediflow/app/scan/index.php',
            'pharmacist'     => BASE_URL . 'mediflow/app/pharmacy/index.php',
            'billing'        => BASE_URL . 'mediflow/app/billing/index.php',
        ];

        $login_success = true;
        $redirect_url  = $role_routes[trim(strtolower($user['role']))] ?? BASE_URL . 'auth/login.php';
    }
} else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light scroll-smooth" data-layout="vertical" data-sidebar="light" data-mode="light">
<head>
    <meta charset="utf-8">
    <title>Sign In — Mediflow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <script src="../assets/js/layout.js"></script>
    <link rel="stylesheet" href="../assets/css/starcode2.css">
    <style>
        /* ── Toast notification ── */
        #toast {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 9999;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            min-width: 300px;
            max-width: 400px;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            font-size: 0.875rem;
            line-height: 1.5;
            transform: translateX(120%);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(.22,.68,0,1.2), opacity 0.3s ease;
            pointer-events: none;
        }
        #toast.show {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }
        #toast.toast-error   { background: #fff1f2; border-left: 4px solid #f43f5e; color: #9f1239; }
        #toast.toast-success { background: #f0fdf4; border-left: 4px solid #22c55e; color: #14532d; }
        #toast.toast-warning { background: #fffbeb; border-left: 4px solid #f59e0b; color: #78350f; }
        .toast-icon { font-size: 1.1rem; margin-top: 1px; flex-shrink: 0; }
        .toast-close { margin-left: auto; cursor: pointer; opacity: 0.6; transition: opacity 0.2s; font-size: 1rem; flex-shrink: 0; }
        .toast-close:hover { opacity: 1; }

        /* ── Field error inline ── */
        .field-error {
            display: none;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.35rem;
            font-size: 0.8rem;
            color: #f43f5e;
            animation: slideDown 0.2s ease;
        }
        .field-error.visible { display: flex; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Input error state ── */
        .input-error {
            border-color: #f43f5e !important;
            background-color: #fff1f2 !important;
        }
        .input-error:focus { box-shadow: 0 0 0 3px rgba(244,63,94,0.15) !important; }

        /* ── Submit button loading ── */
        #submitBtn { position: relative; overflow: hidden; transition: all 0.2s ease; }
        #submitBtn .btn-text   { transition: opacity 0.2s; }
        #submitBtn .btn-loader { 
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        #submitBtn.loading .btn-text   { opacity: 0; }
        #submitBtn.loading .btn-loader { opacity: 1; }
        #submitBtn.loading { cursor: not-allowed; pointer-events: none; }
        #submitBtn:active:not(.loading) { transform: scale(0.98); }

        /* ── Spinner ── */
        .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Success overlay ── */
        #successOverlay {
            position: fixed; inset: 0; z-index: 9998;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(6px);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 1rem;
            opacity: 0; pointer-events: none;
            transition: opacity 0.4s ease;
        }
        #successOverlay.show { opacity: 1; pointer-events: auto; }
        .success-circle {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: #22c55e;
            display: flex; align-items: center; justify-content: center;
            transform: scale(0);
            transition: transform 0.4s cubic-bezier(.22,.68,0,1.4);
        }
        #successOverlay.show .success-circle { transform: scale(1); }
        .success-check {
            font-size: 2rem; color: white; line-height: 1;
        }
        .success-text {
            font-size: 1.1rem; font-weight: 500; color: #166534;
            opacity: 0; transform: translateY(8px);
            transition: opacity 0.3s ease 0.3s, transform 0.3s ease 0.3s;
        }
        #successOverlay.show .success-text { opacity: 1; transform: translateY(0); }

        /* ── Password toggle ── */
        .pw-toggle { cursor: pointer; user-select: none; }
        .pw-toggle:hover { color: #6366f1; }

        /* ── Shake animation for wrong creds ── */
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-6px); }
            40%      { transform: translateX(6px); }
            60%      { transform: translateX(-4px); }
            80%      { transform: translateX(4px); }
        }
        .shake { animation: shake 0.45s ease; }

        /* ── Attempt counter badge ── */
        #attemptBadge {
            display: none;
            font-size: 0.75rem;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
            margin-top: 0.5rem;
        }
        #attemptBadge.visible { display: inline-block; }

        /* ── Lockout state ── */
        #lockoutBar {
            display: none;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #9f1239;
            text-align: center;
            margin-bottom: 1rem;
        }
        #lockoutBar.visible { display: block; }
    </style>
</head>

<body class="font-public">

<!-- ── Toast ── -->
<div id="toast" role="alert" aria-live="assertive">
    <span class="toast-icon" id="toastIcon"></span>
    <div>
        <p class="font-medium" id="toastTitle" style="margin:0 0 2px;"></p>
        <p style="margin:0;opacity:0.85;" id="toastMsg"></p>
    </div>
    <span class="toast-close" onclick="hideToast()" title="Dismiss">✕</span>
</div>

<!-- ── Success overlay ── -->
<div id="successOverlay">
    <div class="success-circle">
        <span class="success-check">✓</span>
    </div>
    <p class="success-text">Signed in successfully — redirecting…</p>
</div>

<div class="relative flex flex-col w-full overflow-hidden xl:flex-row to-custom-800 bg-gradient-to-r from-custom-900 dark:to-custom-900 dark:from-custom-950">
    <!-- background pattern -->
    <div class="absolute inset-0 bg-pattern-2 opacity-20">
        <img src="data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' version='1.1' xmlns:xlink='http://www.w3.org/1999/xlink' xmlns:svgjs='http://svgjs.dev/svgjs' width='2000' height='1000' preserveAspectRatio='none' viewBox='0 0 2000 1000'%3e%3cg mask='url(%26quot%3b%23SvgjsMask1203%26quot%3b)' fill='none'%3e%3cpath d='M25.24 -18.32L52.09 -2.82L52.09 28.18L25.24 43.68L-1.61 28.18L-1.61 -2.82z' stroke='rgba(61%2c 97%2c 213%2c 1)' stroke-width='2'%3e%3c/path%3e%3c/g%3e%3cdefs%3e%3cmask id='SvgjsMask1203'%3e%3crect width='2000' height='1000' fill='white'%3e%3c/rect%3e%3c/mask%3e%3c/defs%3e%3c/svg%3e" alt="">
    </div>

    <!-- ── Login panel ── -->
    <div class="min-h-[calc(100vh_-_theme('spacing.4')_*_2)] mx-3 lg:w-[40rem] shrink-0 px-10 py-14 flex items-center justify-center m-4 bg-white rounded z-10 relative dark:bg-zink-700 dark:text-zink-100 md:mx-auto xl:mx-4">
        <div class="flex flex-col w-50 h-full">

            <!-- Language picker -->
            <div>
                <div class="relative dropdown text-end">
                    <button type="button" class="inline-flex items-center gap-3 transition-all duration-200 ease-linear dropdown-toggle btn border-slate-200 dark:border-zink-500 group/items focus:border-custom-500 dark:focus:border-custom-500" id="dropdownMenuButton" data-bs-toggle="dropdown">
                        <img src="../assets/images/us2.svg" alt="" class="object-cover h-5 rounded-full">
                        <h6 class="text-base font-medium transition-all duration-200 ease-linear text-slate-600 group-hover/items:text-custom-500 dark:text-zink-200 dark:group-hover/items:text-custom-500">English</h6>
                    </button>
                    <div class="absolute z-50 hidden p-3 mt-1 text-left list-none bg-white rounded-md shadow-md dropdown-menu min-w-[9rem] flex flex-col gap-3 dark:bg-zink-600" aria-labelledby="dropdownMenuButton">
                        <a href="#!" class="flex items-center gap-3 group/items">
                            <img src="../assets/images/us2.svg" alt="" class="object-cover h-4 rounded-full">
                            <h6 class="text-sm font-medium transition-all duration-200 ease-linear text-slate-600 group-hover/items:text-custom-500 dark:text-zink-200 dark:group-hover/items:text-custom-500">English</h6>
                        </a>
                    </div>
                </div>
            </div>

            <div class="my-auto">
                <div class="lg:w-[20rem] mx-auto mt-10">
                    <ul class="flex flex-wrap w-full gap-2 text-sm font-medium text-center nav-tabs">
                        <li class="group grow active">
                            <a href="javascript:void(0);" data-tab-toggle="" data-target="emailLogin"
                               class="inline-block px-4 w-full py-2 text-base transition-all duration-300 ease-linear rounded-md text-slate-500 bg-slate-100 dark:text-zink-200 dark:bg-zink-600 border border-transparent group-[.active]:bg-custom-500 dark:group-[.active]:bg-custom-500 group-[.active]:text-white dark:group-[.active]:text-white hover:text-custom-500 dark:hover:text-custom-500 -mb-[1px]">
                                <i data-lucide="mail" class="inline-block mr-1 size-4"></i>
                                <span class="align-middle">Email</span>
                            </a>
                        </li>
                        <li class="group grow">
                            <a href="javascript:void(0);" data-tab-toggle="" data-target="phoneLogin"
                               class="inline-block px-4 w-full py-2 text-base transition-all duration-300 ease-linear rounded-md text-slate-500 bg-slate-100 dark:text-zink-200 dark:bg-zink-600 border border-transparent group-[.active]:bg-custom-500 dark:group-[.active]:bg-custom-500 group-[.active]:text-white dark:group-[.active]:text-white hover:text-custom-500 dark:hover:text-custom-500 -mb-[1px]">
                                <i data-lucide="smartphone" class="inline-block mr-1 size-4"></i>
                                <span class="align-middle">Phone</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="lg:w-[25rem] mx-auto">
                    <div class="mt-5 tab-content">
                        <div class="block tab-pane" id="emailLogin">

                            <!-- Lockout bar -->
                            <div id="lockoutBar">
                                <strong>Too many failed attempts.</strong>
                                Account locked for <span id="lockoutTimer">30</span>s.
                            </div>

                            <form method="POST" class="mt-10" id="signInForm" novalidate autocomplete="off">

                                <!-- Global server error banner -->
                                <?php if (!empty($error)): ?>
                                <div class="flex items-start gap-3 p-3 mb-4 text-sm rounded-lg bg-red-50 border border-red-200 text-red-700" id="serverErrorBanner">
                                    <i data-lucide="alert-circle" class="size-4 mt-0.5 shrink-0"></i>
                                    <div>
                                        <p class="font-semibold mb-0.5">Sign in failed</p>
                                        <p class="opacity-90"><?= htmlspecialchars($error) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($login_success)): ?>
                                <script>
                                    window.addEventListener('DOMContentLoaded', function () {
                                        showSuccess();
                                        setTimeout(function () {
                                            window.location.href = '<?= htmlspecialchars($redirect_url ?? '/') ?>';
                                        }, 1800);
                                    });
                                </script>
                                <?php endif; ?>

                                <!-- Username -->
                                <div class="mb-4">
                                    <label for="username" class="inline-block mb-2 text-base font-medium">
                                        Username / Email
                                    </label>
                                    <div class="relative">
                                        <input id="username" type="text" name="username"
                                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                               autocomplete="username"
                                               class="form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 <?= isset($field_errors['username']) ? 'input-error' : '' ?>"
                                               placeholder="Enter username or email">
                                    </div>
                                    <div class="field-error <?= isset($field_errors['username']) ? 'visible' : '' ?>" id="usernameError">
                                        <i data-lucide="alert-circle" class="size-3"></i>
                                        <span><?= htmlspecialchars($field_errors['username'] ?? 'Username is required') ?></span>
                                    </div>
                                </div>

                                <!-- Password -->
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <label for="password" class="text-base font-medium">Password</label>
                                        <a href="forgot-password.php" class="text-sm text-custom-500 hover:text-custom-600 hover:underline transition-colors">
                                            Forgot password?
                                        </a>
                                    </div>
                                    <div class="relative">
                                        <input type="password" name="password" id="password"
                                               autocomplete="current-password"
                                               class="form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 pr-10 <?= isset($field_errors['password']) ? 'input-error' : '' ?>"
                                               placeholder="Enter password">
                                        <button type="button" id="pwToggle"
                                                class="pw-toggle absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-custom-500 transition-colors"
                                                tabindex="-1" aria-label="Toggle password visibility">
                                            <i data-lucide="eye" id="pwIcon" class="size-4"></i>
                                        </button>
                                    </div>
                                    <div class="field-error <?= isset($field_errors['password']) ? 'visible' : '' ?>" id="passwordError">
                                        <i data-lucide="alert-circle" class="size-3"></i>
                                        <span><?= htmlspecialchars($field_errors['password'] ?? 'Password is required') ?></span>
                                    </div>
                                </div>

                                <!-- Remember + attempt badge -->
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2">
                                        <input id="rememberMe"
                                               class="border rounded-sm appearance-none size-4 bg-slate-100 border-slate-200 dark:bg-zink-600 dark:border-zink-500 checked:bg-custom-500 checked:border-custom-500"
                                               type="checkbox" name="remember">
                                        <label for="rememberMe" class="inline-block text-base font-medium align-middle cursor-pointer">
                                            Remember me
                                        </label>
                                    </div>
                                    <span id="attemptBadge">⚠ <span id="attemptsLeft">2</span> attempts left</span>
                                </div>

                                <!-- Submit -->
                                <div class="mt-8">
                                    <button type="submit" id="submitBtn"
                                            class="w-full text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600 hover:border-custom-600 focus:ring focus:ring-custom-100 active:bg-custom-600">
                                        <span class="btn-text flex items-center justify-center gap-2">
                                            <i data-lucide="log-in" class="size-4"></i> Sign In
                                        </span>
                                        <span class="btn-loader">
                                            <div class="spinner"></div>
                                        </span>
                                    </button>
                                </div>

                                <!-- Divider -->
                                <div class="relative my-6">
                                    <div class="absolute inset-0 flex items-center">
                                        <div class="w-full border-t border-slate-200 dark:border-zink-600"></div>
                                    </div>
                                    <div class="relative flex justify-center">
                                        <span class="px-3 text-sm text-slate-400 bg-white dark:bg-zink-700">Secure login</span>
                                    </div>
                                </div>

                                <!-- Security note -->
                                <div class="flex items-center justify-center gap-2 text-xs text-slate-400">
                                    <i data-lucide="shield-check" class="size-3.5 text-green-500"></i>
                                    <span>256-bit encrypted · Session expires automatically</span>
                                </div>

                            </form>
                        </div>

                        <!-- Phone tab (unchanged) -->
                        <div class="hidden tab-pane" id="phoneLogin">
                            <form class="mt-10">
                                <div class="mb-3">
                                    <label for="phoneNumber" class="inline-block mb-2 text-base font-medium">Phone Number</label>
                                    <input type="number" id="phoneNumber"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400"
                                           placeholder="Enter phone">
                                </div>
                                <div class="mb-3">
                                    <label class="inline-block mb-2 text-base font-medium">Password</label>
                                    <input type="password"
                                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400"
                                           placeholder="Enter password">
                                </div>
                                <div class="mt-8">
                                    <button type="submit" class="w-full text-white btn bg-custom-500 border-custom-500 hover:bg-custom-600">Sign In</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <p class="mb-0 text-center text-15 text-slate-500 dark:text-zink-200">
                    © <script>document.write(new Date().getFullYear())</script> Mediflow.
                    Crafted with <i class="text-red-500 ri-heart-fill"></i> by
                    <a href="http://mediflow.in" class="underline transition-all duration-200 ease-linear text-slate-800 dark:text-zink-50 hover:text-custom-500">MediFlow</a>
                </p>
            </div>
        </div>
    </div>

    <!-- ── Right panel ── -->
    <div class="relative z-10 flex items-center justify-center min-h-screen px-10 grow py-14">
        <div>
            <a href="#!"><img src="../assets/images/logo-light.png" alt="" class="block mx-auto mb-14 h-7"></a>
            <img src="" alt="" class="block object-cover mx-auto shadow-lg md:max-w-md rounded-xl shadow-custom-800">
            <div class="mt-10 text-center">
                <h3 class="mb-3 capitalize text-custom-50">Advanced tools for optimizing clinical workflows</h3>
                <p class="max-w-2xl text-custom-300 text-16">
                    Streamline patient care with our integrated pharmacy and laboratory modules, designed to empower healthcare providers through precision tracking and automated notifications.
                </p>
            </div>
        </div>
    </div>
</div>

<script src='../assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="../assets/libs/%40popperjs/core/umd/popper.min.js"></script>
<script src="../assets/libs/tippy.js/tippy-bundle.umd.min.js"></script>
<script src="../assets/libs/simplebar/simplebar.min.js"></script>
<script src="../assets/libs/prismjs/prism.js"></script>
<script src="../assets/libs/lucide/umd/lucide.js"></script>
<script src="../assets/js/starcode.bundle.js"></script>

<script>
(function () {
    /* ─── constants ─── */
    const MAX_ATTEMPTS  = 5;
    const WARN_AT       = 3;   // show warning badge after this many failures
    const LOCKOUT_SECS  = 30;
    const STORAGE_KEY   = 'mf_login_attempts';

    /* ─── element refs ─── */
    const form          = document.getElementById('signInForm');
    const submitBtn     = document.getElementById('submitBtn');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const usernameError = document.getElementById('usernameError');
    const passwordError = document.getElementById('passwordError');
    const attemptBadge  = document.getElementById('attemptBadge');
    const attemptsLeft  = document.getElementById('attemptsLeft');
    const lockoutBar    = document.getElementById('lockoutBar');
    const lockoutTimer  = document.getElementById('lockoutTimer');
    const pwToggle      = document.getElementById('pwToggle');
    const pwIcon        = document.getElementById('pwIcon');

    /* ─── attempt tracking (localStorage) ─── */
    function getAttempts () {
        try {
            const d = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            return d;
        } catch { return {}; }
    }
    function saveAttempts (d) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(d));
    }
    function resetAttempts () {
        localStorage.removeItem(STORAGE_KEY);
        attemptBadge.classList.remove('visible');
        lockoutBar.classList.remove('visible');
    }
    function recordFailure () {
        const d   = getAttempts();
        d.count   = (d.count || 0) + 1;
        d.lastAt  = Date.now();
        saveAttempts(d);
        return d.count;
    }

    /* ─── lockout check on page load ─── */
    function checkLockout () {
        const d = getAttempts();
        if (!d.count || d.count < MAX_ATTEMPTS) return false;
        const elapsed = (Date.now() - (d.lastAt || 0)) / 1000;
        if (elapsed < LOCKOUT_SECS) {
            startLockoutUI(Math.ceil(LOCKOUT_SECS - elapsed));
            return true;
        }
        resetAttempts();
        return false;
    }

    function startLockoutUI (secs) {
        lockoutBar.classList.add('visible');
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        let remaining = secs;
        lockoutTimer.textContent = remaining;
        const iv = setInterval(function () {
            remaining--;
            lockoutTimer.textContent = remaining;
            if (remaining <= 0) {
                clearInterval(iv);
                lockoutBar.classList.remove('visible');
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                resetAttempts();
            }
        }, 1000);
    }

    /* ─── toast ─── */
    let toastTimeout;
    window.showToast = function (title, msg, type) {
        const el    = document.getElementById('toast');
        const icons = { error: '✕', success: '✓', warning: '⚠' };
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMsg').textContent   = msg;
        document.getElementById('toastIcon').textContent  = icons[type] || '!';
        el.className = 'show toast-' + type;
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(hideToast, 5000);
    };
    window.hideToast = function () {
        document.getElementById('toast').classList.remove('show');
    };

    /* ─── success overlay ─── */
    window.showSuccess = function () {
        document.getElementById('successOverlay').classList.add('show');
    };

    /* ─── inline field errors ─── */
    function setFieldError (input, errorEl, msg) {
        input.classList.add('input-error');
        errorEl.querySelector('span').textContent = msg;
        errorEl.classList.add('visible');
    }
    function clearFieldError (input, errorEl) {
        input.classList.remove('input-error');
        errorEl.classList.remove('visible');
    }

    /* ─── live clear on input ─── */
    usernameInput.addEventListener('input', function () {
        clearFieldError(usernameInput, usernameError);
    });
    passwordInput.addEventListener('input', function () {
        clearFieldError(passwordInput, passwordError);
    });

    /* ─── password toggle ─── */
    pwToggle.addEventListener('click', function () {
        const isText = passwordInput.type === 'text';
        passwordInput.type = isText ? 'password' : 'text';
        // swap lucide icon
        pwIcon.setAttribute('data-lucide', isText ? 'eye' : 'eye-off');
        lucide.createIcons();
    });

    /* ─── client-side validation ─── */
    function validate () {
        let ok = true;
        const u = usernameInput.value.trim();
        const p = passwordInput.value;

        if (!u) {
            setFieldError(usernameInput, usernameError, 'Username or email is required');
            ok = false;
        }
        if (!p) {
            setFieldError(passwordInput, passwordError, 'Password is required');
            ok = false;
        } else if (p.length < 6) {
            setFieldError(passwordInput, passwordError, 'Password must be at least 6 characters');
            ok = false;
        }
        return ok;
    }

    /* ─── form submit ─── */
    form.addEventListener('submit', function (e) {
        // Block if locked out
        if (checkLockout()) {
            e.preventDefault();
            showToast('Account locked', 'Too many failed attempts. Please wait.', 'error');
            return;
        }

        if (!validate()) {
            e.preventDefault();
            // Shake the form
            form.classList.add('shake');
            setTimeout(function () { form.classList.remove('shake'); }, 500);
            return;
        }

        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
    });

    /* ─── handle PHP errors (server returned error) ─── */
    <?php if (!empty($error)): ?>
    (function () {
        const count = recordFailure();
        const left  = MAX_ATTEMPTS - count;

        // shake
        if (form) {
            form.classList.add('shake');
            setTimeout(function () { form.classList.remove('shake'); }, 500);
        }

        if (count >= MAX_ATTEMPTS) {
            startLockoutUI(LOCKOUT_SECS);
            showToast('Account locked', 'Maximum attempts reached. Wait ' + LOCKOUT_SECS + 's.', 'error');
        } else if (count >= WARN_AT) {
            attemptsLeft.textContent = left;
            attemptBadge.classList.add('visible');
            showToast(
                'Sign in failed',
                '<?= addslashes($error) ?> ' + left + ' attempt' + (left === 1 ? '' : 's') + ' remaining.',
                'error'
            );
        } else {
            showToast('Sign in failed', '<?= addslashes($error) ?>', 'error');
        }
    })();
    <?php endif; ?>

    <?php if (!empty($field_errors)): ?>
    showToast('Check your inputs', 'Please fix the highlighted fields.', 'warning');
    <?php endif; ?>

    /* ─── init ─── */
    checkLockout();
    lucide.createIcons();

})();
</script>
</body>
</html>