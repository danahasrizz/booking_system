<?php
/**
 * Admin Dashboard
 * Admin can view all bookings, approve/reject, and manage system
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/booking.php';
require_once __DIR__ . '/../../includes/audit.php';

startSecureSession();
requireRole(['admin']); // Only admin can access

$user = getCurrentUser();
$bookings = getBookings(null, 'admin');
$auditStats = getAuditStats();

$success = isset($_GET['success']) ? sanitize($_GET['success']) : '';
$error = isset($_GET['error']) ? sanitize($_GET['error']) : '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? '';
        $bookingId = $_POST['booking_id'] ?? 0;
        
        if ($action === 'approve') {
            $result = approveBooking($bookingId, $user['user_id']);
            if ($result['success']) {
                header('Location: dashboard.php?success=Booking approved');
                exit();
            }
            $error = $result['message'];
        } elseif ($action === 'reject') {
            $result = rejectBooking($bookingId, $user['user_id']);
            if ($result['success']) {
                header('Location: dashboard.php?success=Booking rejected');
                exit();
            }
            $error = $result['message'];
        } elseif ($action === 'delete') {
            $result = deleteBooking($bookingId, $user['user_id'], 'admin');
            if ($result['success']) {
                header('Location: dashboard.php?success=Booking deleted');
                exit();
            }
            $error = $result['message'];
        }
    }
}

// Count pending bookings
$pendingCount = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AMC Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #1e3a5f;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            display: block;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .stat-card {
            border-left: 4px solid;
            border-radius: 8px;
        }
        .stat-card.primary { border-left-color: #2e5a8f; }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .status-pending { color: #f39c12; }
        .status-approved { color: #27ae60; }
        .status-rejected { color: #e74c3c; }
        .status-cancelled { color: #95a5a6; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="p-3 text-white">
                    <h5>🏭 AMC Booking</h5>
                    <small>Welcome, <?= sanitize($user['username']) ?></small><br>
                    <span class="badge bg-danger">Admin</span>
                </div>
                <nav>
                    <a href="dashboard.php" class="active">📊 Dashboard</a>
                    <a href="audit_logs.php">📋 Audit Logs</a>
                    <a href="../user/dashboard.php">📅 My Bookings</a>
                    <a href="../../logout.php">🚪 Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2>Admin Dashboard</h2>
                <p class="text-muted">Manage bookings and monitor system activity</p>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card primary">
                            <div class="card-body">
                                <h6 class="text-muted">Total Bookings</h6>
                                <h2><?= count($bookings) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <h6 class="text-muted">Pending Approval</h6>
                                <h2><?= $pendingCount ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card success">
                            <div class="card-body">
                                <h6 class="text-muted">Audit Logs Today</h6>
                                <h2><?= $auditStats['logs_today'] ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card danger">
                            <div class="card-body">
                                <h6 class="text-muted">Failed Logins (24h)</h6>
                                <h2><?= $auditStats['failed_logins_24h'] ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- All Bookings Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>📅 All Bookings</span>
                        <span class="badge bg-primary"><?= count($bookings) ?> total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Facility</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bookings)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No bookings yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bookings as $b): ?>
                                            <tr>
                                                <td>#<?= $b['booking_id'] ?></td>
                                                <td><?= sanitize($b['booked_by']) ?></td>
                                                <td><?= sanitize($b['facility_name']) ?></td>
                                                <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                                                <td><?= date('H:i', strtotime($b['start_time'])) ?> - <?= date('H:i', strtotime($b['end_time'])) ?></td>
                                                <td><?= sanitize(substr($b['purpose'], 0, 30)) ?>...</td>
                                                <td>
                                                    <span class="status-<?= $b['status'] ?>">
                                                        ● <?= ucfirst($b['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($b['status'] === 'pending'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                                                                ✓
                                                            </button>
                                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-warning">
                                                                ✗
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Delete this booking permanently?')">
                                                            🗑
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>