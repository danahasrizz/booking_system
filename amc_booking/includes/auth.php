<?php
/**
 * Authentication Functions
 * - Login / Logout / Register
 * - Role-based access control (RBAC)
 * - Account lockout after failed attempts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/audit.php';

/**
 * Register a new user
 */
function registerUser($username, $email, $password, $role = 'student') {
    $username = cleanInput($username);
    $email = cleanInput($email);
    
    // Validate email
    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Validate password
    $passwordCheck = validatePassword($password);
    if (!$passwordCheck['valid']) {
        return ['success' => false, 'message' => $passwordCheck['message']];
    }
    
    // Validate username (3-50 alphanumeric characters)
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3-50 alphanumeric characters'];
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if username or email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $role]);
        
        $userId = $conn->lastInsertId();
        
        // Log to audit trail
        logAudit($userId, 'REGISTER', 'users', $userId, null, [
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
        
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        
    } catch (PDOException $e) {
        error_log("Registration Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Login user
 */
function loginUser($username, $password) {
    $username = cleanInput($username);
    
    // Rate limiting
    if (!checkRateLimit('login', 5, 300)) {
        logAudit(null, 'LOGIN_RATE_LIMITED', 'users', null, null, ['username' => $username]);
        return ['success' => false, 'message' => 'Too many attempts. Try again in 5 minutes.'];
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user
        $stmt = $conn->prepare("
            SELECT user_id, username, password_hash, role, is_active, 
                   failed_login_attempts, lockout_until
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // User not found
        if (!$user) {
            logAudit(null, 'LOGIN_FAILED', 'users', null, null, [
                'username' => $username,
                'reason' => 'user_not_found'
            ]);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check if locked out
        if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
            $mins = ceil((strtotime($user['lockout_until']) - time()) / 60);
            return ['success' => false, 'message' => "Account locked. Try again in {$mins} minutes."];
        }
        
        // Check if active
        if (!$user['is_active']) {
            logAudit($user['user_id'], 'LOGIN_FAILED', 'users', $user['user_id'], null, [
                'reason' => 'account_inactive'
            ]);
            return ['success' => false, 'message' => 'Account is deactivated'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Increment failed attempts
            $failedAttempts = $user['failed_login_attempts'] + 1;
            $lockoutUntil = null;
            
            // Lock after 5 failed attempts
            if ($failedAttempts >= 5) {
                $lockoutUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            }
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET failed_login_attempts = ?, lockout_until = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$failedAttempts, $lockoutUntil, $user['user_id']]);
            
            logAudit($user['user_id'], 'LOGIN_FAILED', 'users', $user['user_id'], null, [
                'reason' => 'wrong_password',
                'attempts' => $failedAttempts
            ]);
            
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Success! Reset failed attempts
        $stmt = $conn->prepare("
            UPDATE users 
            SET last_login = NOW(), failed_login_attempts = 0, lockout_until = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$user['user_id']]);
        
        // Regenerate session ID (prevent session fixation)
        session_regenerate_id(true);
        
        // Set session - ROLE IS STORED SERVER-SIDE (not in cookie!)
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Log successful login
        logAudit($user['user_id'], 'LOGIN', 'users', $user['user_id'], null, null);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'role' => $user['role'],
            'user_id' => $user['user_id']
        ];
        
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Login failed'];
    }
}

/**
 * Logout user
 */
function logoutUser() {
    startSecureSession();
    
    $userId = $_SESSION['user_id'] ?? null;
    
    // Log logout
    if ($userId) {
        logAudit($userId, 'LOGOUT', 'users', $userId, null, null);
    }
    
    // Clear session
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /amc_booking/login.php?error=Please login first');
        exit();
    }
}

/**
 * Require specific role(s) - RBAC
 */
function requireRole($allowedRoles) {
    requireLogin();
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Log unauthorized access attempt
        logAudit($_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', 'system', null, null, [
            'required_roles' => $allowedRoles,
            'user_role' => $_SESSION['role'],
            'page' => $_SERVER['REQUEST_URI']
        ]);
        
        header('Location: /amc_booking/views/user/dashboard.php?error=Access denied');
        exit();
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is staff or admin
 */
function isStaffOrAdmin() {
    return isLoggedIn() && in_array($_SESSION['role'], ['staff', 'admin']);
}