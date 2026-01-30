<?php
/**
 * User Dashboard
 * Students/Staff can create and view their bookings
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/booking.php';

startSecureSession();
requireLogin();

$user = getCurrentUser();
$bookings = getBookings($user['user_id'], $user['role']);
$facilities = getFacilities();

$success = isset($_GET['success']) ? sanitize($_GET['success']) : '';
$error = isset($_GET['error']) ? sanitize($_GET['error']) : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $result = createBooking(
                $user['user_id'],
                $_POST['facility_id'],
                $_POST['booking_date'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['purpose']
            );
            
            if ($result['success']) {
                header('Location: dashboard.php?success=Booking created successfully');
                exit();
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'cancel') {
            $result = deleteBooking($_POST['booking_id'], $user['user_id'], $user['role']);
            
            if ($result['success']) {
                header('Location: dashboard.php?success=Booking cancelled');
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AMC Booking System</title>
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
                    <span class="badge bg-secondary"><?= ucfirst($user['role']) ?></span>
                </div>
                <nav>
                    <a href="dashboard.php" class="active">📋 My Bookings</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="../admin/dashboard.php">🔧 Admin Panel</a>
                    <?php endif; ?>
                    <a href="../../logout.php">🚪 Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2>My Dashboard</h2>
                <p class="text-muted">Book AMC facilities for your projects</p>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- New Booking Form -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                ➕ New Booking
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="create">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Facility</label>
                                        <select name="facility_id" class="form-select" required>
                                            <option value="">Select facility...</option>
                                            <?php foreach ($facilities as $f): ?>
                                                <option value="<?= $f['facility_id'] ?>">
                                                    <?= sanitize($f['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Date</label>
                                        <input type="date" name="booking_date" class="form-control" 
                                               min="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col">
                                            <label class="form-label">Start Time</label>
                                            <input type="time" name="start_time" class="form-control" required>
                                        </div>
                                        <div class="col">
                                            <label class="form-label">End Time</label>
                                            <input type="time" name="end_time" class="form-control" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Purpose</label>
                                        <textarea name="purpose" class="form-control" rows="2" 
                                                  placeholder="What will you use the facility for?" required></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        Book Now
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- My Bookings List -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                📅 My Bookings
                            </div>
                            <div class="card-body">
                                <?php if (empty($bookings)): ?>
                                    <p class="text-muted text-center py-4">No bookings yet</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Facility</th>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bookings as $b): ?>
                                                    <tr>
                                                        <td><?= sanitize($b['facility_name']) ?></td>
                                                        <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                                                        <td><?= date('H:i', strtotime($b['start_time'])) ?> - <?= date('H:i', strtotime($b['end_time'])) ?></td>
                                                        <td>
                                                            <span class="status-<?= $b['status'] ?>">
                                                                ● <?= ucfirst($b['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($b['status'] === 'pending'): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="action" value="cancel">
                                                                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                            onclick="return confirm('Cancel this booking?')">
                                                                        Cancel
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>