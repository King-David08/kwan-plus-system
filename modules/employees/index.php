<?php
require_once __DIR__ . '/../../includes/auth.php';
requireEmployee();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$user = getCurrentUser();

// Get employee details
$employee = dbFetch("SELECT * FROM employees WHERE user_id = ? OR email = ?", [$user['id'], $user['email']]);

// Get today's attendance
$today = date('Y-m-d');
$attendance = dbFetch("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?", [$employee['id'], $today]);

// Get this week's attendance
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_attendance = dbFetchAll("
    SELECT * FROM attendance_logs 
    WHERE employee_id = ? AND date >= ? 
    ORDER BY date DESC
", [$employee['id'], $week_start]);

// Get leave balance
$leave_balance = dbFetch("
    SELECT * FROM leave_balances 
    WHERE employee_id = ? AND year = YEAR(CURDATE())
", [$employee['id']]);

$current_time = date('h:i:s A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Kwan Plus</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Welcome, <?= htmlspecialchars($employee['first_name'] ?? $user['full_name']) ?>! 👋</h1>
                    <p class="text-muted">Employee Dashboard</p>
                </div>
                <div class="header-actions">
                    <span class="date-display"><?= date('l, F j, Y') ?></span>
                    <span class="time-display" id="currentTime"><?= $current_time ?></span>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <?= $attendance && $attendance['check_in_time'] ? date('h:i A', strtotime($attendance['check_in_time'])) : '—' ?>
                        </div>
                        <div class="stat-label">Today's Check In</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <?= count(array_filter($week_attendance, fn($a) => $a['check_in_time'] !== null)) ?>
                        </div>
                        <div class="stat-label">Days Present (This Week)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-content">
                        <div class="stat-number">
                            <?= $attendance && $attendance['total_hours'] ? number_format($attendance['total_hours'], 1) : '0' ?>
                        </div>
                        <div class="stat-label">Hours Today</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $leave_balance['remaining'] ?? 0 ?></div>
                        <div class="stat-label">Leave Balance</div>
                    </div>
                </div>
            </div>
            
            <!-- Your Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Your Attendance</h3>
                    <a href="../../admin/attendance/history.php" class="btn btn-sm btn-primary">View History</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Status</th>
                                <th>Check Out</th>
                                <th>Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($week_attendance)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No attendance records this week</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($week_attendance as $att): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($att['date'])) ?></td>
                                <td><?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '—' ?></td>
                                <td>
                                    <?php if ($att['check_in_status'] === 'on_time'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> On Time</span>
                                    <?php elseif ($att['check_in_status'] === 'late'): ?>
                                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Late</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fas fa-times"></i> Absent</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $att['check_out_time'] ? date('h:i A', strtotime($att['check_out_time'])) : '—' ?></td>
                                <td><?= $att['total_hours'] ? number_format($att['total_hours'], 1) . ' hrs' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString();
        }
        setInterval(updateTime, 1000);
    </script>

    <style>
        .stat-icon.purple {
            background: #f0e8fe;
            color: #9b59b6;
        }
    </style>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>