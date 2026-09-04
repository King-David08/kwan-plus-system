<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCompanyAccess();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

$month = $_GET['month'] ?? date('Y-m');
$export = isset($_GET['export']);

// Build report query
$sql = "SELECT 
            e.id,
            e.first_name, 
            e.last_name, 
            e.employee_code,
            d.name as division_name,
            COUNT(CASE WHEN a.check_in_time IS NOT NULL THEN 1 END) as days_present,
            COUNT(CASE WHEN a.check_in_status = 'on_time' THEN 1 END) as days_on_time,
            COUNT(CASE WHEN a.check_in_status = 'late' THEN 1 END) as days_late,
            COUNT(CASE WHEN a.check_out_time IS NOT NULL THEN 1 END) as days_checked_out,
            SUM(a.total_hours) as total_hours,
            SUM(a.overtime_hours) as total_overtime,
            AVG(a.total_hours) as avg_hours
        FROM employees e
        LEFT JOIN divisions d ON d.id = e.division_id
        LEFT JOIN attendance_logs a ON a.employee_id = e.id AND DATE_FORMAT(a.date, '%Y-%m') = ?
        WHERE e.company_id = ? AND e.status = 'active'
        GROUP BY e.id
        ORDER BY e.first_name";

$reports = dbFetchAll($sql, [$month, $company_id]);

// Calculate totals
$total_employees = count($reports);
$total_present = array_sum(array_column($reports, 'days_present'));
$total_on_time = array_sum(array_column($reports, 'days_on_time'));
$total_late = array_sum(array_column($reports, 'days_late'));
$total_hours = array_sum(array_column($reports, 'total_hours'));
$total_overtime = array_sum(array_column($reports, 'total_overtime'));

if ($export) {
    // Export to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Code', 'Name', 'Division', 'Days Present', 'On Time', 'Late', 'Checked Out', 'Total Hours', 'Overtime', 'Avg Hours']);
    
    foreach ($reports as $row) {
        fputcsv($output, [
            $row['employee_code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['division_name'] ?? 'Unassigned',
            $row['days_present'],
            $row['days_on_time'],
            $row['days_late'],
            $row['days_checked_out'],
            number_format($row['total_hours'] ?? 0, 2),
            number_format($row['total_overtime'] ?? 0, 2),
            number_format($row['avg_hours'] ?? 0, 2)
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Kwan Plus</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="app-wrapper">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>📊 Attendance Reports</h1>
                    <p class="text-muted">Monthly attendance summary for payroll</p>
                </div>
                <div class="header-actions">
                    <a href="?month=<?= $month ?>&export=1" class="btn btn-success">📥 Export CSV</a>
                    <a href="history.php" class="btn btn-secondary">History</a>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div class="stats-row">
                <div class="stat-mini">
                    <span class="stat-mini-number"><?= $total_employees ?></span>
                    <span class="stat-mini-label">Employees</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-number text-success"><?= $total_present ?></span>
                    <span class="stat-mini-label">Total Present</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-number text-success"><?= $total_on_time ?></span>
                    <span class="stat-mini-label">On Time</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-number text-warning"><?= $total_late ?></span>
                    <span class="stat-mini-label">Late</span>
                </div>
                <div class="stat-mini">
                    <span class="stat-mini-number text-info"><?= number_format($total_hours, 1) ?></span>
                    <span class="stat-mini-label">Total Hours</span>
                </div>
            </div>
            
            <!-- Month Selector -->
            <div class="card">
                <form method="GET" action="" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="month">Month</label>
                            <input type="month" id="month" name="month" value="<?= $month ?>">
                        </div>
                        <div class="form-group" style="align-self:flex-end;">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Report Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Code</th>
                                <th>Division</th>
                                <th>Present</th>
                                <th>On Time</th>
                                <th>Late</th>
                                <th>Hours</th>
                                <th>Overtime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No data for this month</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($reports as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td><code><?= htmlspecialchars($row['employee_code']) ?></code></td>
                                <td><?= htmlspecialchars($row['division_name'] ?? 'Unassigned') ?></td>
                                <td><span class="badge badge-success"><?= $row['days_present'] ?></span></td>
                                <td><?= $row['days_on_time'] ?></td>
                                <td><span class="badge badge-warning"><?= $row['days_late'] ?></span></td>
                                <td><?= number_format($row['total_hours'] ?? 0, 1) ?> hrs</td>
                                <td><?= number_format($row['total_overtime'] ?? 0, 1) ?> hrs</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($reports)): ?>
                        <tfoot>
                            <tr style="font-weight:600;background:#f8f9fa;">
                                <td colspan="3" style="text-align:right;">TOTALS:</td>
                                <td><?= $total_present ?></td>
                                <td><?= $total_on_time ?></td>
                                <td><?= $total_late ?></td>
                                <td><?= number_format($total_hours, 1) ?> hrs</td>
                                <td><?= number_format($total_overtime, 1) ?> hrs</td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>