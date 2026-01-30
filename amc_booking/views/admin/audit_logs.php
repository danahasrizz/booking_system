<?php
/**
 * Audit Logs Page
 * YOUR MAIN FEATURE!
 * Shows WHO did WHAT, WHEN - for accountability and transparency
 */

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

startSecureSession();
requireRole(['admin']); // Only admin can view audit logs

$user = getCurrentUser();

// Get filters from URL
$filters = [
    'action' => $_GET['action'] ?? '',
    'table_name' => $_GET['table_name'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get audit logs
$logs = getAuditLogs($filters, 100);
$stats = getAuditStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - AMC Booking System</title>
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
        .action-LOGIN { background: #d4edda; color: #155724; }
        .action-LOGOUT { background: #d1ecf1; color: #0c5460; }
        .action-LOGIN_FAILED { background: #f8d7da; color: #721c24; }
        .action-CREATE { background: #cce5ff; color: #004085; }
        .action-UPDATE { background: #fff3cd; color: #856404; }
        .action-DELETE { background: #f8d7da; color: #721c24; }
        .action-CANCEL { background: #e2e3e5; color: #383d41; }
        .action-APPROVE { background: #d4edda; color: #155724; }
        .action-REJECT { background: #fff3cd; color: #856404; }
        .action-REGISTER { background: #d4edda; color: #155724; }
        .action-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
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
                    <a href="dashboard.php">📊 Dashboard</a>
                    <a href="audit_logs.php" class="active">📋 Audit Logs</a>
                    <a href="../user/dashboard.php">📅 My Bookings</a>
                    <a href="../../logout.php">🚪 Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2>📋 Audit Logs</h2>
                <p class="text-muted">Track WHO did WHAT, WHEN - Complete activity history for accountability</p>
                
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6>Total Logs</h6>
                                <h3><?= $stats['total_logs'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Logs Today</h6>
                                <h3><?= $stats['logs_today'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h6>Failed Logins (24h)</h6>
                                <h3><?= $stats['failed_logins_24h'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Actions Tracked</h6>
                                <h3><?= count($stats['actions_breakdown']) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">🔍 Filter Logs</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-select">
                                    <option value="">All Actions</option>
                                    <option value="LOGIN" <?= $filters['action'] === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
                                    <option value="LOGOUT" <?= $filters['action'] === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT</option>
                                    <option value="LOGIN_FAILED" <?= $filters['action'] === 'LOGIN_FAILED' ? 'selected' : '' ?>>LOGIN_FAILED</option>
                                    <option value="CREATE" <?= $filters['action'] === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                                    <option value="UPDATE" <?= $filters['action'] === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                                    <option value="DELETE" <?= $filters['action'] === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                                    <option value="CANCEL" <?= $filters['action'] === 'CANCEL' ? 'selected' : '' ?>>CANCEL</option>
                                    <option value="APPROVE" <?= $filters['action'] === 'APPROVE' ? 'selected' : '' ?>>APPROVE</option>
                                    <option value="REJECT" <?= $filters['action'] === 'REJECT' ? 'selected' : '' ?>>REJECT</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Table</label>
                                <select name="table_name" class="form-select">
                                    <option value="">All Tables</option>
                                    <option value="users" <?= $filters['table_name'] === 'users' ? 'selected' : '' ?>>users</option>
                                    <option value="bookings" <?= $filters['table_name'] === 'bookings' ? 'selected' : '' ?>>bookings</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="audit_logs.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Audit Logs Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>📜 Activity Log</span>
                        <span class="badge bg-primary"><?= count($logs) ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No logs found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>#<?= $log['log_id'] ?></td>
                                                <td>
                                                    <small>
                                                        <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                                                        <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                                    </small>
                                                </td>
                                                <td><?= sanitize($log['username'] ?? 'System') ?></td>
                                                <td>
                                                    <span class="action-badge action-<?= $log['action'] ?>">
                                                        <?= $log['action'] ?>
                                                    </span>
                                                </td>
                                                <td><code><?= $log['table_name'] ?? '-' ?></code></td>
                                                <td><?= $log['record_id'] ?? '-' ?></td>
                                                <td>
                                                    <?php if ($log['old_values'] || $log['new_values']): ?>
                                                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modal<?= $log['log_id'] ?>">
                                                            View
                                                        </button>
                                                        
                                                        <!-- Modal for details -->
                                                        <div class="modal fade" id="modal<?= $log['log_id'] ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Log #<?= $log['log_id'] ?> Details</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <?php if ($log['old_values']): ?>
                                                                            <h6>Old Values:</h6>
                                                                            <pre class="bg-light p-2"><?= json_encode(json_decode($log['old_values']), JSON_PRETTY_PRINT) ?></pre>
                                                                        <?php endif; ?>
                                                                        
                                                                        <?php if ($log['new_values']): ?>
                                                                            <h6>New Values:</h6>
                                                                            <pre class="bg-light p-2"><?= json_encode(json_decode($log['new_values']), JSON_PRETTY_PRINT) ?></pre>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><?= $log['ip_address'] ?></small></td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>