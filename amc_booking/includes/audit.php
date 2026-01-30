<?php
/**
 * Audit Trail Functions
 * YOUR MAIN FEATURE!
 * 
 * Logs WHO did WHAT, WHEN, and WHAT CHANGED
 * ISO27001 Compliant - Records cannot be modified or deleted
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

/**
 * Log an action to the audit trail
 * 
 * @param int|null $userId - Who performed the action
 * @param string $action - What action (LOGIN, CREATE, UPDATE, DELETE, etc.)
 * @param string|null $tableName - Which table was affected
 * @param int|null $recordId - Which record was affected
 * @param array|null $oldValues - Previous values (for updates)
 * @param array|null $newValues - New values
 * @return bool
 */
function logAudit($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            getClientIP(),
            substr(getUserAgent(), 0, 500)
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with filters
 */
function getAuditLogs($filters = [], $limit = 50, $offset = 0) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "
        SELECT al.*, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filter by user
    if (!empty($filters['user_id'])) {
        $sql .= " AND al.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    // Filter by action
    if (!empty($filters['action'])) {
        $sql .= " AND al.action = ?";
        $params[] = $filters['action'];
    }
    
    // Filter by table
    if (!empty($filters['table_name'])) {
        $sql .= " AND al.table_name = ?";
        $params[] = $filters['table_name'];
    }
    
    // Filter by date range
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit statistics for dashboard
 */
function getAuditStats() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stats = [];
    
    // Total logs
    $stmt = $conn->query("SELECT COUNT(*) as total FROM audit_logs");
    $stats['total_logs'] = $stmt->fetch()['total'];
    
    // Logs today
    $stmt = $conn->query("SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE()");
    $stats['logs_today'] = $stmt->fetch()['today'];
    
    // Failed logins in last 24 hours
    $stmt = $conn->query("
        SELECT COUNT(*) as failed 
        FROM audit_logs 
        WHERE action = 'LOGIN_FAILED' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats['failed_logins_24h'] = $stmt->fetch()['failed'];
    
    // Actions breakdown
    $stmt = $conn->query("
        SELECT action, COUNT(*) as count 
        FROM audit_logs 
        GROUP BY action 
        ORDER BY count DESC
    ");
    $stats['actions_breakdown'] = $stmt->fetchAll();
    
    return $stats;
}

/**
 * Get history for a specific record
 */
function getRecordHistory($tableName, $recordId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT al.*, u.username
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.table_name = ? AND al.record_id = ?
        ORDER BY al.created_at DESC
    ");
    
    $stmt->execute([$tableName, $recordId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's activity history
 */
function getUserActivity($userId, $limit = 20) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM audit_logs 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}