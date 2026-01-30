<?php
/**
 * Security Functions
 * - CSRF Protection
 * - XSS Prevention
 * - Session Security
 * - Input Validation
 */

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 0);  // Set to 1 when using HTTPS
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_start();
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Get CSRF hidden input field for forms
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// Validate CSRF token
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize output to prevent XSS
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Clean input data
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate password strength
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "at least 8 characters";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "an uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "a lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "a number";
    }
    if (!preg_match('/[@$!%*?&#]/', $password)) {
        $errors[] = "a special character (@$!%*?&#)";
    }
    
    if (empty($errors)) {
        return ['valid' => true, 'message' => 'Password is strong'];
    }
    
    return [
        'valid' => false,
        'message' => 'Password must contain ' . implode(', ', $errors)
    ];
}

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

// Get user agent
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

// Rate limiting for login attempts
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
    $key = 'rate_' . $action . '_' . getClientIP();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Reset if time window passed
    if (time() - $_SESSION[$key]['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Check if exceeded
    if ($_SESSION[$key]['count'] >= $maxAttempts) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}