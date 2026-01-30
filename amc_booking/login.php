<?php
/**
 * Login Page
 */

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/user/dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter username and password';
        } else {
            $result = loginUser($username, $password);
            
            if ($result['success']) {
                if ($result['role'] === 'admin') {
                    header('Location: views/admin/dashboard.php');
                } else {
                    header('Location: views/user/dashboard.php');
                }
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get messages from URL
if (isset($_GET['error'])) {
    $error = sanitize($_GET['error']);
}
if (isset($_GET['success'])) {
    $success = sanitize($_GET['success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AMC Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a5f 0%, #2e5a8f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #1e3a5f;
            font-size: 1.8rem;
        }
        .btn-primary {
            background: #1e3a5f;
            border: none;
        }
        .btn-primary:hover {
            background: #2e5a8f;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>🏭 AMC Booking</h1>
            <p class="text-muted">Advanced Manufacturing Centre</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrfField() ?>
            
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="register.php">Don't have an account? Register</a>
        </div>
        
        <hr>
        
        <div class="text-muted small">
            <strong>Test Accounts:</strong><br>
            Admin: admin / Admin@123<br>
            Staff: staff1 / Staff@123<br>
            Student: student1 / Student@123
        </div>
    </div>
</body>
</html>