<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireCompanyAccess();
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();
$user = getCurrentUser();

$company = null;
if ($company_id) {
    $company = dbFetch("SELECT * FROM companies WHERE id = ?", [$company_id]);
}

if (!$company) {
    $companies = getUserCompanies();
    if (count($companies) === 1) {
        switchCompany($companies[0]['id']);
        header('Location: dashboard.php');
        exit();
    } elseif (count($companies) > 1) {
        header('Location: select-company.php');
        exit();
    }
}

$today = date('Y-m-d');
$current_time = date('h:i:s A');

// Statistics
$total_employees = dbFetch("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND status = 'active'", [$company_id])['total'] ?? 0;
$total_clients = dbFetch("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND status = 'active'", [$company_id])['total'] ?? 0;
$total_projects = dbFetch("SELECT COUNT(*) as total FROM divisions WHERE company_id = ? AND status = 'active'", [$company_id])['total'] ?? 0;
$total_events = dbFetch("SELECT COUNT(*) as total FROM attendance_logs WHERE company_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$company_id])['total'] ?? 0;

$payroll = dbFetch("SELECT SUM(salary_amount) as total FROM employees WHERE company_id = ? AND status = 'active'", [$company_id])['total'] ?? 0;

$today_stats = dbFetch("
    SELECT 
        COUNT(*) as checked_in,
        SUM(CASE WHEN check_in_status = 'on_time' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN check_in_status = 'absent' OR check_in_time IS NULL THEN 1 ELSE 0 END) as absent
    FROM attendance_logs 
    WHERE company_id = ? AND date = ?
", [$company_id, $today]);

$gender_stats = dbFetchAll("
    SELECT gender, COUNT(*) as count 
    FROM employees 
    WHERE company_id = ? AND status = 'active' AND gender IS NOT NULL
    GROUP BY gender
", [$company_id]);

$male_count = 0;
$female_count = 0;
$other_count = 0;
foreach ($gender_stats as $g) {
    if ($g['gender'] === 'male') $male_count = $g['count'];
    elseif ($g['gender'] === 'female') $female_count = $g['count'];
    else $other_count = $g['count'];
}

$weekly_trend = dbFetchAll("
    SELECT 
        DATE(date) as day,
        COUNT(*) as total,
        SUM(CASE WHEN check_in_status = 'on_time' THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN check_in_status = 'late' THEN 1 ELSE 0 END) as late
    FROM attendance_logs 
    WHERE company_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    GROUP BY DATE(date)
    ORDER BY day
", [$company_id]);

$monthly_earnings = dbFetch("
    SELECT SUM(salary_amount) as total 
    FROM employees 
    WHERE company_id = ? AND status = 'active' AND MONTH(hire_date) = MONTH(CURDATE())
", [$company_id])['total'] ?? 0;

$top_performers = dbFetchAll("
    SELECT 
        e.id, e.first_name, e.last_name, e.position,
        COUNT(CASE WHEN a.check_in_status = 'on_time' THEN 1 END) as on_time,
        COUNT(a.id) as total,
        ROUND((COUNT(CASE WHEN a.check_in_status = 'on_time' THEN 1 END) / NULLIF(COUNT(a.id), 0)) * 100, 0) as score
    FROM employees e
    LEFT JOIN attendance_logs a ON a.employee_id = e.id AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE e.company_id = ? AND e.status = 'active'
    GROUP BY e.id
    HAVING total > 0
    ORDER BY score DESC
    LIMIT 5
", [$company_id]);

$settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);
$break_start = $settings['break_start'] ?? '13:00:00';
$break_end = $settings['break_end'] ?? '13:45:00';
$target_hours = $settings['overtime_threshold'] ?? 8.00;
$break_duration = (strtotime($break_end) - strtotime($break_start)) / 60;

$employee = dbFetch("SELECT id FROM employees WHERE company_id = ? AND email = ? LIMIT 1", [$company_id, $user['email'] ?? '']);
$user_attendance = null;
if ($employee) {
    $user_attendance = dbFetch("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?", [$employee['id'], $today]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= htmlspecialchars($company['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0f1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            color: #e2e8f0;
        }

        .dashboard-wrapper {
            padding: 24px 28px 40px;
            margin-left: 260px;
            margin-top: 80px;
            min-height: calc(100vh - 80px);
            background: #0a0f1a;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
        }

        .page-header p {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .stat-card .stat-number {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.2;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .stat-card .stat-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
        }

        .stat-card .stat-change.up { color: #22c55e; }
        .stat-card .stat-change.down { color: #ef4444; }
        .stat-card .stat-change.neutral { color: #64748b; }

        /* ===== CHARTS SECTION ===== */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 20px 24px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .chart-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .chart-card .card-header h3 {
            font-size: 15px;
            font-weight: 600;
            color: #ffffff;
        }

        .chart-card .card-header a {
            font-size: 13px;
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
        }

        .chart-card .card-header a:hover {
            text-decoration: underline;
        }

        .chart-wrap {
            height: 200px;
            position: relative;
        }

        /* ===== RIGHT PANEL ===== */
        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .right-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .right-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .right-card .card-header h4 {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
        }

        .right-card .card-header .badge {
            font-size: 12px;
            font-weight: 600;
            color: #22c55e;
        }

        .right-card .big-number {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
        }

        .right-card .sub-text {
            font-size: 13px;
            color: #94a3b8;
        }

        .mini-chart {
            height: 50px;
            margin-top: 8px;
        }

        /* ===== BOTTOM GRID ===== */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .bottom-card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 20px 22px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .bottom-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .bottom-card .card-header h4 {
            font-size: 15px;
            font-weight: 600;
            color: #ffffff;
        }

        .bottom-card .card-header a {
            font-size: 13px;
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
        }

        .bottom-card .card-header a:hover {
            text-decoration: underline;
        }

        /* ===== PERFORMANCE LIST ===== */
        .perf-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .perf-item:last-child {
            border-bottom: none;
        }

        .perf-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 13px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .perf-name {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #ffffff;
        }

        .perf-bar {
            width: 100px;
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 3px;
            margin-right: 10px;
        }

        .perf-bar .fill {
            height: 100%;
            border-radius: 3px;
        }

        .perf-score {
            font-size: 13px;
            font-weight: 600;
            min-width: 36px;
            text-align: right;
            color: #ffffff;
        }

        /* ===== ATTENDANCE SECTION ===== */
        .attendance-clock {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 8px 0;
        }

        .attendance-clock .time {
            font-size: 28px;
            font-weight: 300;
            font-family: 'Courier New', monospace;
            color: #ffffff;
        }

        .attendance-clock .label {
            font-size: 12px;
            color: #94a3b8;
        }

        .attendance-buttons {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .attendance-buttons .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            color: white;
        }

        .btn-in { background: #3b82f6; }
        .btn-in:hover { background: #2563eb; }
        .btn-out { background: #ef4444; }
        .btn-out:hover { background: #dc2626; }
        .btn-break { background: #f59e0b; }
        .btn-break:hover { background: #d97706; }

        .attendance-status {
            background: rgba(34, 197, 94, 0.15);
            color: #34d399;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }

        .break-info {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 6px;
        }

        /* ===== GENDER BREAKDOWN ===== */
        .gender-breakdown {
            display: flex;
            gap: 20px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .gender-breakdown span {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #e2e8f0;
        }

        .gender-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .gender-dot.male { background: #3b82f6; }
        .gender-dot.female { background: #ec4899; }
        .gender-dot.other { background: #64748b; }

        .mini-donut {
            height: 60px;
            margin-top: 6px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 992px) {
            .charts-row { grid-template-columns: 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .dashboard-wrapper { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .attendance-clock { flex-direction: column; align-items: flex-start; }
            .attendance-buttons { width: 100%; }
            .attendance-buttons .btn { flex: 1; text-align: center; }
            .gender-breakdown { flex-direction: column; gap: 6px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .perf-bar { width: 60px; }
            .right-card .big-number { font-size: 22px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-wrapper">
        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($user['full_name'] ?? 'Admin') ?> — <?= htmlspecialchars($company['name']) ?></p>
        </div>

        <!-- ===== STATS CARDS ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_employees ?></div>
                <div class="stat-label">Total Employees</div>
                <div class="stat-change up">▲ 12% this month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $today_stats['on_time'] ?? 0 ?></div>
                <div class="stat-label">On Time Today</div>
                <div class="stat-change up">▲ <?= $total_employees > 0 ? round(($today_stats['on_time'] ?? 0) / $total_employees * 100) : 0 ?>% attendance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $today_stats['late'] ?? 0 ?></div>
                <div class="stat-label">Late Arrivals</div>
                <div class="stat-change down">▼ <?= $total_employees > 0 ? round(($today_stats['late'] ?? 0) / $total_employees * 100) : 0 ?>% of total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $today_stats['absent'] ?? 0 ?></div>
                <div class="stat-label">Absent Today</div>
                <div class="stat-change down">▼ <?= $total_employees > 0 ? round(($today_stats['absent'] ?? 0) / $total_employees * 100) : 0 ?>% absent</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($payroll, 0) ?></div>
                <div class="stat-label">Monthly Payroll</div>
                <div class="stat-change up">▲ <?= $payroll > 0 ? '+5%' : '$0' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_projects ?></div>
                <div class="stat-label">Active Divisions</div>
                <div class="stat-change neutral">● <?= $total_projects > 0 ? 'Running' : 'No divisions' ?></div>
            </div>
        </div>

        <!-- ===== CHARTS SECTION ===== -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="card-header">
                    <h3>Attendance Overview</h3>
                    <a href="attendance/reports.php">View Report →</a>
                </div>
                <div class="chart-wrap">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <div class="right-panel">
                <div class="right-card">
                    <div class="card-header">
                        <h4>Yearly Earnings</h4>
                        <span class="badge">+9% last year</span>
                    </div>
                    <div class="big-number">$<?= number_format($payroll, 0) ?></div>
                    <div class="sub-text">Total yearly earnings</div>
                    <div class="mini-chart">
                        <canvas id="yearlyChart"></canvas>
                    </div>
                </div>

                <div class="right-card">
                    <div class="card-header">
                        <h4>Monthly Earnings</h4>
                        <span class="badge">+9% last year</span>
                    </div>
                    <div class="big-number">$<?= number_format($monthly_earnings, 0) ?></div>
                    <div class="sub-text">This month's earnings</div>
                </div>
            </div>
        </div>

        <!-- ===== BOTTOM GRID ===== -->
        <div class="bottom-grid">
            <div class="bottom-card">
                <div class="card-header">
                    <h4>Top Performing Employees</h4>
                    <a href="employees/index.php">View All →</a>
                </div>
                <?php if (empty($top_performers)): ?>
                    <p style="color:#64748b;text-align:center;padding:16px 0;font-size:14px;">No data available</p>
                <?php else: ?>
                    <?php foreach ($top_performers as $i => $p): ?>
                    <div class="perf-item">
                        <div class="perf-avatar"><?= substr($p['first_name'], 0, 1) ?></div>
                        <span class="perf-name"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></span>
                        <div class="perf-bar">
                            <div class="fill" style="width: <?= $p['score'] ?>%; background: <?= $p['score'] >= 80 ? '#22c55e' : ($p['score'] >= 60 ? '#f59e0b' : '#ef4444') ?>;"></div>
                        </div>
                        <span class="perf-score"><?= $p['score'] ?>%</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="bottom-card">
                <div class="card-header">
                    <h4>Your Attendance</h4>
                    <span style="font-size:13px;color:#94a3b8;">Today</span>
                </div>

                <div class="attendance-clock">
                    <span class="time" id="clockDisplay"><?= $current_time ?></span>
                    <span class="label">Current Time</span>
                </div>

                <div class="break-info">
                    Break: <?= date('h:i A', strtotime($break_start)) ?> - <?= date('h:i A', strtotime($break_end)) ?> (<?= $break_duration ?> min)
                </div>

                <div class="attendance-buttons">
                    <?php if ($user_attendance && $user_attendance['check_in_time'] && !$user_attendance['check_out_time']): ?>
                        <button class="btn btn-break" onclick="takeBreak()">Break</button>
                        <button class="btn btn-out" onclick="clockOut()">Clock Out</button>
                    <?php elseif ($user_attendance && $user_attendance['check_in_time'] && $user_attendance['check_out_time']): ?>
                        <span class="attendance-status">✓ Completed</span>
                    <?php else: ?>
                        <button class="btn btn-in" onclick="clockIn()">Clock In</button>
                    <?php endif; ?>
                </div>

                <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255, 255, 255, 0.06);">
                    <div style="display:flex;gap:16px;margin-bottom:6px;">
                        <span style="font-size:13px;font-weight:500;color:#ffffff;">Gender Distribution</span>
                    </div>
                    <div class="gender-breakdown">
                        <span><span class="gender-dot male"></span> Male: <?= $male_count ?></span>
                        <span><span class="gender-dot female"></span> Female: <?= $female_count ?></span>
                        <span><span class="gender-dot other"></span> Other: <?= $other_count ?></span>
                    </div>
                    <div class="mini-donut">
                        <canvas id="genderChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ===== UPDATE CLOCK =====
        function updateClock() {
            const now = new Date();
            document.getElementById('clockDisplay').textContent = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);

        // ===== ATTENDANCE CHART =====
        const ctx1 = document.getElementById('attendanceChart').getContext('2d');
        const weeklyData = <?= json_encode($weekly_trend) ?>;
        const labels = weeklyData.length > 0 ? weeklyData.map(d => new Date(d.day).toLocaleDateString('en-US', { weekday: 'short' })) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const onTimeData = weeklyData.length > 0 ? weeklyData.map(d => d.on_time || 0) : [0,0,0,0,0,0,0];
        const lateData = weeklyData.length > 0 ? weeklyData.map(d => d.late || 0) : [0,0,0,0,0,0,0];

        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'On Time',
                        data: onTimeData,
                        backgroundColor: '#22c55e',
                        borderRadius: 3
                    },
                    {
                        label: 'Late',
                        data: lateData,
                        backgroundColor: '#f59e0b',
                        borderRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 11 },
                            color: '#94a3b8'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: 'rgba(255,255,255,0.06)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, color: '#94a3b8' }
                    }
                }
            }
        });

        // ===== YEARLY CHART =====
        const ctx2 = document.getElementById('yearlyChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['2020', '2021', '2022', '2023'],
                datasets: [{
                    data: [
                        <?= number_format($payroll * 0.6, 0) ?>,
                        <?= number_format($payroll * 0.75, 0) ?>,
                        <?= number_format($payroll * 0.9, 0) ?>,
                        <?= number_format($payroll, 0) ?>
                    ],
                    backgroundColor: ['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.15)', '#3b82f6', '#3b82f6'],
                    borderRadius: 3,
                    barThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { display: false },
                    x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#94a3b8' } }
                }
            }
        });

        // ===== GENDER CHART =====
        const ctx3 = document.getElementById('genderChart').getContext('2d');
        new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [<?= $male_count ?>, <?= $female_count ?>, <?= $other_count ?>],
                    backgroundColor: ['#3b82f6', '#ec4899', '#64748b'],
                    borderWidth: 2,
                    borderColor: 'rgba(26, 35, 50, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '72%'
            }
        });

        // ===== ATTENDANCE ACTIONS =====
        function clockIn() {
            window.location.href = 'attendance/scanner.php?action=clock_in';
        }

        function clockOut() {
            if (confirm('Are you sure you want to clock out?')) {
                window.location.href = 'attendance/scanner.php?action=clock_out';
            }
        }

        function takeBreak() {
            if (confirm('Start your break?')) {
                window.location.href = 'attendance/scanner.php?action=break';
            }
        }
    </script>

</body>
</html>