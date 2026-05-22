<?php

// 1. Check if User is Logged In
if (!isset($_SESSION['id'])) {
    // We use the absolute path to ensure it always finds the login page
    // Make sure /TB/app/ matches your folder structure
    header("Location: /mediflow/app/auth/");
    exit;
}

// 2. Optional: Check for Specific Role
// This allows you to protect pages so only 'farmers' can see the farmer dashboard.
// If the file including this sets a $required_role, we check it.
if (isset($required_role)) {
    if ($_SESSION['role'] !== $required_role) {
        // User is logged in, but doesn't have permission for this page
        // Redirect them back to login (or you could send them to their own dashboard)
        header("Location: /mediflow/app/auth/");
        exit;
    }
}
?>