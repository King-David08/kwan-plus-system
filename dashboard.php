<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'config/database.php';

$conn = getConnection();
$user = getCurrentUser();

// Get statistics based on role
if (isSuperAdmin()) {
    // Count employees
    $stmt = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    $totalEmployees = $stmt->fetch()['total'];
    
    // Count today's attendance
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE date = CURDATE() AND status = 'present'");
    $stmt->execute();
    $todayPresent = $stmt->fetch()['total'];
    
    // Count divisions
    $stmt = $conn->query("SELECT COUNT(*) as total FROM divisions");
    $totalDivisions = $stmt->fetch()['total'];
    
    // Get attendance by division
    $stmt = $conn->query("
        SELECT d.name, COUNT(a.id) as count 
        FROM divisions d
        LEFT JOIN employees e ON e.division_id = d.id
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE() AND a.status = 'present'
        GROUP BY d.id
    ");
    $divisionStats = $stmt->fetchAll();
} else {
    // Branch manager view - only their division
    $divisionId = getUserDivision();
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE division_id = ? AND status = 'active'");
    $stmt->execute([$divisionId]);
    $totalEmployees = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance a 
                            JOIN employees e ON e.id = a.employee_id 
                            WHERE e.division_id = ? AND a.date = CURDATE() AND a.status = 'present'");
    $stmt->execute([$divisionId]);
    $todayPresent = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT name FROM divisions WHERE id = ?");
    $stmt->execute([$divisionId]);
    $division = $stmt->fetch();
    $totalDivisions = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kwan Plus</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h1>
            <p><?= isSuperAdmin() ? 'Super Admin' : 'Branch Manager' ?></p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $totalEmployees ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $todayPresent ?></div>
                <div class="stat-label">Present Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalDivisions ?></div>
                <div class="stat-label">Divisions</div>
            </div>
        </div>
        
        <?php if (isSuperAdmin() && isset($divisionStats)): ?>
        <div class="card">
            <h3>Today's Attendance by Division</h3>
            <div class="division-stats">
                <?php foreach ($divisionStats as $stat): ?>
                <div class="division-stat">
                    <span class="division-name"><?= htmlspecialchars($stat['name']) ?></span>
                    <span class="division-count"><?= $stat['count'] ?> present</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <div class="action-grid">
                <?php if (isSuperAdmin()): ?>
                <a href="modules/employees/index.php" class="action-btn">👥 Manage Employees</a>
                <a href="modules/users/index.php" class="action-btn">👤 Manage Users</a>
                <?php endif; ?>
                <a href="modules/attendance/index.php" class="action-btn">📋 Mark Attendance</a>
                <a href="modules/attendance/reports.php" class="action-btn">📊 View Reports</a>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>