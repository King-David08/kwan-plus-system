<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCompanyAccess();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

$month = $_GET['month'] ?? date('Y-m');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $month . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Code', 'Name', 'Division', 'Date', 'Check In', 'Check Out', 'Status', 'Hours']);
    
    $stmt = $db->prepare("
        SELECT 
            e.employee_code, 
            e.first_name, 
            e.last_name, 
            d.name as division_name, 
            a.date, 
            a.check_in_time, 
            a.check_out_time, 
            a.check_in_status,
            a.total_hours
        FROM attendance_logs a 
        JOIN employees e ON a.employee_id = e.id 
        LEFT JOIN divisions d ON d.id = e.division_id
        WHERE a.company_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
        ORDER BY a.date DESC
    ");
    $stmt->execute([$company_id, $month]);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['employee_code'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['division_name'] ?? 'Unassigned',
            $row['date'],
            $row['check_in_time'] ?? '—',
            $row['check_out_time'] ?? '—',
            $row['check_in_status'] ?? 'absent',
            number_format($row['total_hours'] ?? 0, 2)
        ]);
    }
    fclose($output);
    exit();
}

// Summary stats
$summary = $db->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN check_in_status = 'on_time' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN check_in_time IS NOT NULL THEN 1 ELSE 0 END) as present,
        SUM(total_hours) as total_hours
    FROM attendance_logs 
    WHERE company_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$summary->execute([$company_id, $month]);
$stats = $summary->fetch();

// Recent attendance for the month
$attendance = $db->prepare("
    SELECT 
        e.employee_code,
        e.first_name,
        e.last_name,
        d.name as division_name,
        a.date,
        a.check_in_time,
        a.check_out_time,
        a.check_in_status,
        a.total_hours
    FROM attendance_logs a 
    JOIN employees e ON a.employee_id = e.id 
    LEFT JOIN divisions d ON d.id = e.division_id
    WHERE a.company_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?
    ORDER BY a.date DESC, a.check_in_time DESC
    LIMIT 50
");
$attendance->execute([$company_id, $month]);
$recent_attendance = $attendance->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Kwan Plus</title>
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
        .btn-success { background: rgba(34,197,94,0.2); color: #34d399; border: 1px solid rgba(34,197,94,0.15); }
        .btn-success:hover { background: rgba(34,197,94,0.3); }
        .btn-secondary { background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.08); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }

        .header-actions { display: flex; gap: 10px; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
        }

        .stat-box .label {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .stat-box .number.text-success { color: #34d399; }
        .stat-box .number.text-warning { color: #fbbf24; }
        .stat-box .number.text-danger { color: #f87171; }
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

        .filter-form .form-group input::placeholder {
            color: #64748b;
        }

        .filter-form .form-group select option {
            background: #1a2332;
            color: #e2e8f0;
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

        code {
            background: rgba(255, 255, 255, 0.06);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #94a3b8;
        }

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

        .text-center { text-align: center; }
        .text-muted { color: #64748b; }

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
                <h1><i class="fas fa-chart-bar" style="color:#818cf8;"></i> Reports & Analytics</h1>
                <p class="subtitle">View attendance reports and analytics</p>
            </div>
            <div class="header-actions">
                <a href="?month=<?= urlencode($month) ?>&export=csv" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="../attendance/history.php" class="btn btn-secondary">
                    <i class="fas fa-history"></i> History
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="number text-info"><?= $stats['total_records'] ?? 0 ?></div>
                <div class="label">Total Scans</div>
            </div>
            <div class="stat-box">
                <div class="number text-success"><?= $stats['on_time'] ?? 0 ?></div>
                <div class="label">On Time Check-ins</div>
            </div>
            <div class="stat-box">
                <div class="number text-warning"><?= $stats['late'] ?? 0 ?></div>
                <div class="label">Late Check-ins</div>
            </div>
            <div class="stat-box">
                <div class="number text-info"><?= number_format($stats['total_hours'] ?? 0, 1) ?></div>
                <div class="label">Total Hours</div>
            </div>
        </div>

        <!-- Month Selector -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Month</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="form-group" style="display:flex; gap:8px; align-items:center;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Load Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Attendance -->
        <div class="table-card">
            <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.06);">
                <h3 style="font-size:15px;font-weight:600;color:#ffffff;">
                    <i class="fas fa-clipboard-list" style="color:#818cf8;"></i> Recent Attendance
                    <span style="font-size:13px;font-weight:400;color:#94a3b8;margin-left:8px;">
                        (<?= date('F Y', strtotime($month)) ?>)
                    </span>
                </h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Division</th>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Status</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_attendance)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding:30px 0;">
                                <i class="fas fa-info-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>
                                No attendance records found for this month
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_attendance as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><code><?= htmlspecialchars($row['employee_code']) ?></code></td>
                            <td><?= htmlspecialchars($row['division_name'] ?? 'Unassigned') ?></td>
                            <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                            <td><?= $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '—' ?></td>
                            <td>
                                <?php if ($row['check_in_status'] === 'on_time'): ?>
                                    <span class="badge badge-success"><i class="fas fa-check"></i> On Time</span>
                                <?php elseif ($row['check_in_status'] === 'late'): ?>
                                    <span class="badge badge-warning"><i class="fas fa-clock"></i> Late</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fas fa-times"></i> Absent</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '—' ?></td>
                            <td><?= $row['total_hours'] ? number_format($row['total_hours'], 1) . ' hrs' : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
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