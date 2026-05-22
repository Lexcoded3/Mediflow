<?php
 $user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Access Denied | MediFlow OPD</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/starcode2.css">
</head>
<body class="min-h-screen bg-body-bg dark:bg-zink-800 flex items-center justify-center p-4">
    <div class="text-center max-w-md">
        <div class="inline-flex items-center justify-center size-20 bg-red-100 dark:bg-red-500/20 rounded-full mb-6">
            <i data-lucide="shield-x" class="size-10 text-red-500"></i>
        </div>
        <h1 class="text-4xl font-bold text-slate-800 dark:text-zink-100 mb-2">403</h1>
        <h2 class="text-xl font-semibold text-slate-700 dark:text-zink-200 mb-4">Access Denied</h2>
        <p class="text-slate-500 dark:text-zink-300 mb-8">
            You don't have permission to access this page. 
            Your role is <strong><?= $user['role_name'] ?? 'Unknown' ?></strong>, 
            which doesn't include access to this module.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="<?= BASE_URL ?>mediflow/app/auth/" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-custom-500 hover:bg-custom-600 rounded-md transition-colors">
                <i data-lucide="home" class="size-4"></i> Go Home
            </a>
            <a href="<?= BASE_URL ?>mediflow/app/auth/logout.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-medium text-slate-600 dark:text-zink-200 bg-slate-100 dark:bg-zink-600 hover:bg-slate-200 dark:hover:bg-zink-500 rounded-md transition-colors">
                <i data-lucide="log-out" class="size-4"></i> Logout
            </a>
        </div>
    </div>
    <script src="../assets/js/layout.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    </script>
</body>
</html>