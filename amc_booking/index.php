<?php
/**
 * Index Page
 * Redirects to login or dashboard based on login status
 */

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

// If logged in, go to dashboard
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/user/dashboard.php');
    }
    exit();
}

// If not logged in, go to login
header('Location: login.php');
exit();