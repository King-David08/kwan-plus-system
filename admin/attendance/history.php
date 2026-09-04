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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0a0f1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            color: #e2e8f0;
        }

        .page-wrapper {
            padding: 24px 28px 40px;
            margin-left: 260px;
            margin-top: 72px;
            min-height: calc(100vh - 72px);
            background: #0a0f1a;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }

        .page-header .subtitle {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(99,102,241,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.08); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .btn-success { background: rgba(34,197,94,0.2); color: #34d399; border: 1px solid rgba(34,197,94,0.15); }
        .btn-success:hover { background: rgba(34,197,94,0.3); }

        .header-actions { display: flex; gap: 10px; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-box:hover {
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .stat-box .number {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }

        .stat-box .label {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .stat-box .number.text-success { color: #34d399; }
        .stat-box .number.text-warning { color: #fbbf24; }
        .stat-box .number.text-info { color: #60a5fa; }

        .filter-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 18px 22px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 24px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-form .form-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-form .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-form .form-group input,
        .filter-form .form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
            outline: none;
            transition: all 0.3s;
        }

        .filter-form .form-group input:focus,
        .filter-form .form-group select:focus {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
        }

        .filter-form .form-group select option {
            background: #1a2332;
            color: #e2e8f0;
        }

        .filter-form .form-group .btn {
            padding: 9px 18px;
        }

        .table-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th {
            padding: 14px 18px;
            text-align: left;
            background: rgba(255, 255, 255, 0.03);
            font-weight: 600;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            padding: 12px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            vertical-align: middle;
            color: #e2e8f0;
        }
        .table tr:hover td { background: rgba(255, 255, 255, 0.02); }
        .table tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: rgba(34, 197, 94, 0.15); color: #34d399; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .badge-warning { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .badge-secondary { background: rgba(255, 255, 255, 0.06); color: #94a3b8; }

        code {
            background: rgba(255, 255, 255, 0.06);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #94a3b8;
        }

        .text-center { text-align: center; }
        .text-muted { color: #64748b; }

        tfoot tr {
            background: rgba(255, 255, 255, 0.03) !important;
        }

        tfoot td {
            color: #ffffff !important;
            font-weight: 600 !important;
        }

        @media (max-width: 768px) {
            .page-wrapper { margin-left: 0; padding: 16px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; justify-content: center; }
            .filter-form { flex-direction: column; }
            .filter-form .form-group { width: 100%; }
        }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-bar" style="color:#818cf8;"></i> Attendance Reports</h1>
                <p class="subtitle">Monthly attendance summary for payroll</p>
            </div>
            <div class="header-actions">
                <a href="?month=<?= $month ?>&export=1" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="history.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> History
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number"><?= $total_employees ?></div>
                <div class="label">Employees</div>
            </div>
            <div class="stat-box">
                <div class="number text-success"><?= $total_present ?></div>
                <div class="label">Total Present</div>
            </div>
            <div class="stat-box">
                <div class="number text-success"><?= $total_on_time ?></div>
                <div class="label">On Time</div>
            </div>
            <div class="stat-box">
                <div class="number text-warning"><?= $total_late ?></div>
                <div class="label">Late</div>
            </div>
            <div class="stat-box">
                <div class="number text-info"><?= number_format($total_hours, 1) ?></div>
                <div class="label">Total Hours</div>
            </div>
        </div>

        <!-- Month Selector -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Month</label>
                    <input type="month" id="month" name="month" value="<?= $month ?>">
                </div>
                <div class="form-group" style="display:flex; gap:8px; align-items:center;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Table -->
        <div class="table-card">
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
                            <td colspan="8" class="text-center text-muted" style="padding:30px 0;">
                                <i class="fas fa-info-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>
                                No data for this month
                            </td>
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
                        <tr>
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
    </div>

    <script>
        // Close sidebar on outside click for mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (window.innerWidth <= 768 && sidebar && !sidebar.contains(event.target) && !toggle?.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
    </script>
</body>
</html>