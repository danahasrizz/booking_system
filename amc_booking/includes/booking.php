<?php
/**
 * Booking CRUD Operations
 * - Create, Read, Update, Delete bookings
 * - Role-based permissions
 * - All actions logged to audit trail
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/audit.php';

/**
 * CREATE - Add new booking
 */
function createBooking($userId, $facilityId, $date, $startTime, $endTime, $purpose) {
    $purpose = cleanInput($purpose);
    
    // Validate date is not in past
    if (strtotime($date) < strtotime('today')) {
        return ['success' => false, 'message' => 'Cannot book past dates'];
    }
    
    // Validate time range
    if (strtotime($startTime) >= strtotime($endTime)) {
        return ['success' => false, 'message' => 'End time must be after start time'];
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check facility exists
        $stmt = $conn->prepare("SELECT * FROM facilities WHERE facility_id = ? AND is_available = 1");
        $stmt->execute([$facilityId]);
        $facility = $stmt->fetch();
        
        if (!$facility) {
            return ['success' => false, 'message' => 'Facility not available'];
        }
        
        // Check for time conflicts
        $stmt = $conn->prepare("
            SELECT COUNT(*) as conflicts FROM bookings 
            WHERE facility_id = ? 
            AND booking_date = ? 
            AND status NOT IN ('cancelled', 'rejected')
            AND (
                (start_time < ? AND end_time > ?) OR
                (start_time < ? AND end_time > ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->execute([
            $facilityId, $date,
            $endTime, $startTime,
            $startTime, $endTime,
            $startTime, $endTime
        ]);
        
        if ($stmt->fetch()['conflicts'] > 0) {
            return ['success' => false, 'message' => 'Time slot already booked'];
        }
        
        // Create booking
        $stmt = $conn->prepare("
            INSERT INTO bookings (user_id, facility_id, booking_date, start_time, end_time, purpose)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $facilityId, $date, $startTime, $endTime, $purpose]);
        
        $bookingId = $conn->lastInsertId();
        
        // Log to audit trail
        logAudit($userId, 'CREATE', 'bookings', $bookingId, null, [
            'facility_id' => $facilityId,
            'facility_name' => $facility['name'],
            'date' => $date,
            'time' => "$startTime - $endTime",
            'purpose' => $purpose
        ]);
        
        return ['success' => true, 'message' => 'Booking created', 'booking_id' => $bookingId];
        
    } catch (PDOException $e) {
        error_log("Create Booking Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create booking'];
    }
}

/**
 * READ - Get bookings based on role
 */
function getBookings($userId = null, $role = 'student', $filters = []) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "
        SELECT 
            b.*,
            u.username as booked_by,
            f.name as facility_name,
            f.location as facility_location,
            a.username as approved_by_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN facilities f ON b.facility_id = f.facility_id
        LEFT JOIN users a ON b.approved_by = a.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Role-based access control
    if ($role === 'student') {
        // Students only see their own bookings
        $sql .= " AND b.user_id = ?";
        $params[] = $userId;
    } elseif ($role === 'staff') {
        // Staff see their own + all pending
        $sql .= " AND (b.user_id = ? OR b.status = 'pending')";
        $params[] = $userId;
    }
    // Admin sees all
    
    // Apply filters
    if (!empty($filters['status'])) {
        $sql .= " AND b.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['facility_id'])) {
        $sql .= " AND b.facility_id = ?";
        $params[] = $filters['facility_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND b.booking_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND b.booking_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    $sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * READ - Get single booking by ID
 */
function getBookingById($bookingId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            u.username as booked_by,
            f.name as facility_name,
            f.location as facility_location
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN facilities f ON b.facility_id = f.facility_id
        WHERE b.booking_id = ?
    ");
    $stmt->execute([$bookingId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * UPDATE - Modify booking
 */
function updateBooking($bookingId, $userId, $data, $role) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get current booking
    $oldBooking = getBookingById($bookingId);
    
    if (!$oldBooking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    // Check permission
    if ($role === 'student' && $oldBooking['user_id'] != $userId) {
        logAudit($userId, 'UNAUTHORIZED_UPDATE', 'bookings', $bookingId, null, ['reason' => 'not_owner']);
        return ['success' => false, 'message' => 'You can only update your own bookings'];
    }
    
    // Students can only update pending bookings
    if ($role === 'student' && $oldBooking['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Cannot update - booking already ' . $oldBooking['status']];
    }
    
    try {
        $updateFields = [];
        $params = [];
        
        // Update purpose
        if (isset($data['purpose'])) {
            $updateFields[] = "purpose = ?";
            $params[] = cleanInput($data['purpose']);
        }
        
        // Only staff/admin can change status
        if (isset($data['status']) && in_array($role, ['staff', 'admin'])) {
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
            
            if (in_array($data['status'], ['approved', 'rejected'])) {
                $updateFields[] = "approved_by = ?";
                $params[] = $userId;
            }
        }
        
        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'Nothing to update'];
        }
        
        $params[] = $bookingId;
        
        $sql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Log to audit trail
        logAudit($userId, 'UPDATE', 'bookings', $bookingId, $oldBooking, $data);
        
        return ['success' => true, 'message' => 'Booking updated'];
        
    } catch (PDOException $e) {
        error_log("Update Booking Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update booking'];
    }
}

/**
 * DELETE - Cancel or delete booking
 */
function deleteBooking($bookingId, $userId, $role) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $booking = getBookingById($bookingId);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    try {
        if ($role === 'admin') {
            // Admin can permanently delete
            $stmt = $conn->prepare("DELETE FROM bookings WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            
            logAudit($userId, 'DELETE', 'bookings', $bookingId, $booking, null);
            
            return ['success' => true, 'message' => 'Booking deleted'];
            
        } else {
            // Others can only cancel their own pending bookings
            if ($booking['user_id'] != $userId) {
                logAudit($userId, 'UNAUTHORIZED_DELETE', 'bookings', $bookingId, null, ['reason' => 'not_owner']);
                return ['success' => false, 'message' => 'You can only cancel your own bookings'];
            }
            
            if ($booking['status'] !== 'pending') {
                return ['success' => false, 'message' => 'Can only cancel pending bookings'];
            }
            
            $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            
            logAudit($userId, 'CANCEL', 'bookings', $bookingId, $booking, ['status' => 'cancelled']);
            
            return ['success' => true, 'message' => 'Booking cancelled'];
        }
        
    } catch (PDOException $e) {
        error_log("Delete Booking Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to process request'];
    }
}

/**
 * Approve booking (Staff/Admin only)
 */
function approveBooking($bookingId, $approverId) {
    $result = updateBooking($bookingId, $approverId, ['status' => 'approved'], 'admin');
    
    if ($result['success']) {
        logAudit($approverId, 'APPROVE', 'bookings', $bookingId, null, ['status' => 'approved']);
    }
    
    return $result;
}

/**
 * Reject booking (Staff/Admin only)
 */
function rejectBooking($bookingId, $approverId, $reason = '') {
    $result = updateBooking($bookingId, $approverId, ['status' => 'rejected'], 'admin');
    
    if ($result['success']) {
        logAudit($approverId, 'REJECT', 'bookings', $bookingId, null, [
            'status' => 'rejected',
            'reason' => $reason
        ]);
    }
    
    return $result;
}

/**
 * Get all facilities
 */
function getFacilities() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("SELECT * FROM facilities WHERE is_available = 1 ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}