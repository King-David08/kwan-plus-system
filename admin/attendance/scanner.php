<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCompanyAccess();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();
$user = getCurrentUser();

// Get company settings
$settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);
if (!$settings) {
    dbInsert("INSERT INTO company_settings (company_id) VALUES (?)", [$company_id]);
    $settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);
}

// Handle scan request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
    header('Content-Type: application/json');
    
    $qr_payload = trim($_POST['qr_payload'] ?? '');
    $scan_type = $_POST['scan_type'] ?? 'check_in';
    $current_time = date('H:i:s');
    
    if (empty($qr_payload)) {
        echo json_encode(['success' => false, 'message' => 'No QR data']);
        exit();
    }
    
    // Try to decode QR data
    $qr_data = json_decode($qr_payload, true);
    $employee_id = null;
    
    if ($qr_data && isset($qr_data['employee_id'])) {
        $employee_id = $qr_data['employee_id'];
    } else {
        $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND company_id = ? AND status = 'active'");
        $stmt->execute([trim($qr_payload), $company_id]);
        $emp = $stmt->fetch();
        if ($emp) {
            $employee_id = $emp['id'];
        }
    }
    
    if (!$employee_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid QR code']);
        exit();
    }
    
    $employee = dbFetch("SELECT * FROM employees WHERE id = ? AND company_id = ? AND status = 'active'", [$employee_id, $company_id]);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }
    
    $today = date('Y-m-d');
    
    // Time validation - ONLY check when actually marking attendance
    $check_in_start = $settings['check_in_start'] ?? '05:00:00';
    $check_in_end = $settings['check_in_end'] ?? '09:00:00';
    $check_out_start = $settings['check_out_start'] ?? '17:00:00';
    
    try {
        if ($scan_type === 'check_in') {
            // Check if within check-in time
            if ($current_time < $check_in_start || $current_time > $check_in_end) {
                echo json_encode([
                    'success' => false, 
                    'message' => "⏰ Check-in only allowed between " . date('h:i A', strtotime($check_in_start)) . " and " . date('h:i A', strtotime($check_in_end))
                ]);
                exit();
            }
            
            $existing = dbFetch("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?", [$employee_id, $today]);
            if ($existing && $existing['check_in_time']) {
                echo json_encode(['success' => false, 'message' => $employee['first_name'] . ' already checked in']);
                exit();
            }
            
            dbInsert("INSERT INTO attendance_logs (company_id, employee_id, date, check_in_time, check_in_status, check_in_ip, device_info) VALUES (?, ?, ?, ?, 'on_time', ?, ?)", 
                [$company_id, $employee_id, $today, $current_time, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Check-in successful!',
                'employee' => $employee['first_name'] . ' ' . $employee['last_name'],
                'time' => date('h:i A', strtotime($current_time))
            ]);
        } elseif ($scan_type === 'check_out') {
            // Check if within check-out time (only check start time, no end limit)
            if ($current_time < $check_out_start) {
                echo json_encode([
                    'success' => false, 
                    'message' => "⏰ Check-out only allowed from " . date('h:i A', strtotime($check_out_start))
                ]);
                exit();
            }
            
            $existing = dbFetch("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?", [$employee_id, $today]);
            if (!$existing || !$existing['check_in_time']) {
                echo json_encode(['success' => false, 'message' => 'No check-in found']);
                exit();
            }
            if ($existing['check_out_time']) {
                echo json_encode(['success' => false, 'message' => $employee['first_name'] . ' already checked out']);
                exit();
            }
            
            $hours = (strtotime($current_time) - strtotime($existing['check_in_time'])) / 3600;
            dbQuery("UPDATE attendance_logs SET check_out_time = ?, total_hours = ?, check_out_ip = ? WHERE employee_id = ? AND date = ?", 
                [$current_time, round($hours, 2), $_SERVER['REMOTE_ADDR'], $employee_id, $today]);
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Check-out successful!',
                'employee' => $employee['first_name'] . ' ' . $employee['last_name'],
                'time' => date('h:i A', strtotime($current_time)),
                'total_hours' => round($hours, 2)
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get today's attendance
$today = date('Y-m-d');
$attendance_today = dbFetchAll("
    SELECT e.first_name, e.last_name, e.employee_code,
           al.check_in_time, al.check_in_status, al.check_out_time
    FROM employees e
    LEFT JOIN attendance_logs al ON al.employee_id = e.id AND al.date = CURDATE()
    WHERE e.company_id = ? AND e.status = 'active'
    ORDER BY al.check_in_time ASC
", [$company_id]);

$total = count($attendance_today);
$checked_in = array_filter($attendance_today, fn($a) => $a['check_in_time'] !== null);
$checked_out = array_filter($attendance_today, fn($a) => $a['check_out_time'] !== null);

// Time status for display
$current_hour = date('H:i:s');
$check_in_start = $settings['check_in_start'] ?? '05:00:00';
$check_in_end = $settings['check_in_end'] ?? '09:00:00';
$check_out_start = $settings['check_out_start'] ?? '17:00:00';

$is_check_in_open = ($current_hour >= $check_in_start && $current_hour <= $check_in_end);
$is_check_out_open = ($current_hour >= $check_out_start);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Kwan Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/kwan-plus-system/assets/js/html5-qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0f1a; font-family: Arial, sans-serif; color: #e2e8f0; }
        .page-wrapper { padding: 24px 28px 40px; margin-left: 260px; margin-top: 72px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #ffffff; }
        .page-header .subtitle { font-size: 14px; color: #94a3b8; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.08); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .btn-success { background: rgba(34,197,94,0.2); color: #34d399; border: 1px solid rgba(34,197,94,0.15); }
        .btn-success:hover { background: rgba(34,197,94,0.3); }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); padding: 16px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .stat-box .number { font-size: 24px; font-weight: 700; color: #ffffff; }
        .stat-box .label { font-size: 13px; color: #94a3b8; margin-top: 2px; }
        .stat-box .number.text-success { color: #34d399; }
        .stat-box .number.text-warning { color: #fbbf24; }
        .stat-box .number.text-danger { color: #f87171; }
        .time-info { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .time-box { background: rgba(26,35,50,0.6); padding: 12px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .time-box .label { font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; }
        .time-box .value { font-size: 18px; font-weight: 700; color: #ffffff; }
        .time-box .status { font-size: 13px; margin-top: 4px; }
        .time-box .status.open { color: #34d399; }
        .time-box .status.closed { color: #f87171; }
        .scanner-card { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); border-radius: 12px; border: 1px solid rgba(255,255,255,0.06); max-width: 700px; margin: 0 auto 24px; overflow: hidden; }
        .scanner-header { padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .scanner-header h2 { font-size: 18px; font-weight: 700; color: #ffffff; }
        .scanner-header p { font-size: 14px; color: #94a3b8; }
        .scanner-body { padding: 24px; }
        #qr-reader { width: 100%; min-height: 320px; border-radius: 8px; overflow: hidden; border: 2px dashed rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); }
        #qr-reader video { width: 100% !important; height: auto !important; }
        .scan-result { margin-top: 20px; padding: 16px 20px; border-radius: 8px; text-align: center; display: none; }
        .scan-result.success { display: block; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.25); color: #34d399; }
        .scan-result.error { display: block; background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.25); color: #f87171; }
        .scan-result.processing { display: block; background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.25); color: #60a5fa; }
        .scan-result .result-icon { font-size: 48px; display: block; margin-bottom: 6px; }
        .scan-result .result-title { font-size: 20px; font-weight: 700; }
        .scan-result .result-detail { font-size: 15px; margin-top: 4px; }
        .scan-type-toggle { display: flex; gap: 10px; margin: 12px 0; justify-content: center; }
        .scan-type-toggle label { display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; cursor: pointer; border: 2px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); font-weight: 500; font-size: 14px; color: #94a3b8; transition: all 0.2s; }
        .scan-type-toggle label.active { border-color: #6366f1; background: rgba(99,102,241,0.1); color: #ffffff; }
        .scan-type-toggle label input { display: none; }
        .attendance-table { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); border-radius: 12px; border: 1px solid rgba(255,255,255,0.06); overflow: hidden; margin-top: 24px; }
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th { padding: 14px 18px; text-align: left; background: rgba(255,255,255,0.03); font-weight: 600; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 12px; text-transform: uppercase; }
        .table td { padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #e2e8f0; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: rgba(34,197,94,0.15); color: #34d399; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #f87171; }
        .badge-warning { background: rgba(251,191,36,0.15); color: #fbbf24; }
        code { background: rgba(255,255,255,0.06); padding: 2px 8px; border-radius: 4px; color: #94a3b8; }
        .manual-section { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.06); }
        .manual-section h4 { font-size: 14px; font-weight: 600; color: #ffffff; margin-bottom: 12px; }
        .manual-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .manual-form input { flex: 1; padding: 10px 14px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; background: rgba(255,255,255,0.04); color: #e2e8f0; outline: none; min-width: 200px; }
        .manual-form input::placeholder { color: #64748b; }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .settings-link { color: #818cf8; text-decoration: none; font-size: 13px; }
        .settings-link:hover { text-decoration: underline; }
        .warning-banner { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.2); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; color: #fbbf24; }
        .warning-banner i { font-size: 20px; }
        .warning-banner .text { font-size: 14px; }
        @media (max-width: 768px) { .page-wrapper { margin-left: 0; padding: 16px; } .stats-row { grid-template-columns: 1fr 1fr; } .page-header { flex-direction: column; align-items: flex-start; gap: 12px; } .time-info { grid-template-columns: 1fr; } }
        @media (max-width: 480px) { .stats-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-camera" style="color:#818cf8;"></i> QR Scanner</h1>
                <p class="subtitle">Scan employee QR codes to mark attendance</p>
            </div>
            <div class="header-actions">
                <span style="font-size:14px;color:#94a3b8;"><i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y') ?></span>
                <span id="currentTime" style="font-size:14px;font-weight:600;color:#94a3b8;"><?= date('h:i:s A') ?></span>
                <a href="../settings/index.php" class="settings-link"><i class="fas fa-cog"></i> Settings</a>
                <a href="../employees/qr.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <!-- Warning if outside time -->
        <?php if (!$is_check_in_open && !$is_check_out_open): ?>
        <div class="warning-banner">
            <i class="fas fa-clock"></i>
            <span class="text">⏰ Outside attendance hours. Check-in available <?= date('h:i A', strtotime($check_in_start)) ?> - <?= date('h:i A', strtotime($check_in_end)) ?>, Check-out from <?= date('h:i A', strtotime($check_out_start)) ?></span>
        </div>
        <?php endif; ?>

        <!-- Time Info -->
        <div class="time-info">
            <div class="time-box">
                <div class="label">Check-in Time</div>
                <div class="value"><?= date('h:i A', strtotime($check_in_start)) ?> - <?= date('h:i A', strtotime($check_in_end)) ?></div>
                <div class="status <?= $is_check_in_open ? 'open' : 'closed' ?>">
                    <?= $is_check_in_open ? '🟢 Open' : '🔴 Closed' ?>
                </div>
            </div>
            <div class="time-box">
                <div class="label">Check-out Time</div>
                <div class="value"><?= date('h:i A', strtotime($check_out_start)) ?> - Unlimited</div>
                <div class="status <?= $is_check_out_open ? 'open' : 'closed' ?>">
                    <?= $is_check_out_open ? '🟢 Open' : '🔴 Closed' ?>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-box"><div class="number text-success"><?= count($checked_in) ?></div><div class="label">Checked In</div></div>
            <div class="stat-box"><div class="number text-warning">0</div><div class="label">Late</div></div>
            <div class="stat-box"><div class="number text-danger"><?= $total - count($checked_in) ?></div><div class="label">Not Checked In</div></div>
            <div class="stat-box"><div class="number"><?= count($checked_out) ?></div><div class="label">Checked Out</div></div>
        </div>

        <div class="scanner-card">
            <div class="scanner-header">
                <h2><i class="fas fa-camera" style="color:#818cf8;"></i> Scan QR Code</h2>
                <p>Position the employee's QR code within the camera frame</p>
            </div>

            <div class="scanner-body">
                <div class="scan-type-toggle">
                    <label class="active" id="checkInLabel">
                        <input type="radio" name="scanType" value="check_in" checked>
                        <i class="fas fa-sign-in-alt" style="color:#34d399;"></i> Check In
                    </label>
                    <label id="checkOutLabel">
                        <input type="radio" name="scanType" value="check_out">
                        <i class="fas fa-sign-out-alt" style="color:#f87171;"></i> Check Out
                    </label>
                </div>

                <div id="qr-reader"></div>

                <div id="scan-result" class="scan-result">
                    <span class="result-icon" id="resultIcon">📷</span>
                    <div class="result-title" id="resultTitle">Ready to Scan</div>
                    <div class="result-detail" id="resultDetail">Point camera at QR code</div>
                </div>
            </div>

            <div class="manual-section">
                <h4><i class="fas fa-keyboard" style="color:#94a3b8;"></i> Manual Entry</h4>
                <form id="manualForm" class="manual-form">
                    <input type="text" id="manualQR" placeholder="Paste QR data or Employee Code" required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Submit</button>
                </form>
            </div>
        </div>

        <div class="attendance-table">
            <div style="padding:16px 20px;border-bottom:1px solid rgba(255,255,255,0.06);">
                <h3 style="font-size:15px;font-weight:600;color:#ffffff;">Today's Attendance</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Check In</th>
                            <th>Status</th>
                            <th>Check Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_today)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:#64748b;">No employees found</td></tr>
                        <?php else: ?>
                        <?php foreach ($attendance_today as $att): ?>
                        <tr>
                            <td><?= htmlspecialchars($att['first_name'] . ' ' . $att['last_name']) ?></td>
                            <td><code><?= htmlspecialchars($att['employee_code']) ?></code></td>
                            <td><?= $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '—' ?></td>
                            <td>
                                <?php if ($att['check_in_status'] === 'on_time'): ?>
                                    <span class="badge badge-success">On Time</span>
                                <?php elseif ($att['check_in_status'] === 'late'): ?>
                                    <span class="badge badge-warning">Late</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Absent</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $att['check_out_time'] ? date('h:i A', strtotime($att['check_out_time'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Update time
        function updateTime() {
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString();
        }
        setInterval(updateTime, 1000);

        let isProcessing = false;
        let html5QrcodeScanner = null;
        let scanCooldown = false;

        document.querySelectorAll('.scan-type-toggle label').forEach(label => {
            label.addEventListener('click', function() {
                document.querySelectorAll('.scan-type-toggle label').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });

        function processScan(payload) {
            if (isProcessing || scanCooldown) return;
            isProcessing = true;

            const scanType = document.querySelector('input[name="scanType"]:checked').value;
            const resultDiv = document.getElementById('scan-result');

            resultDiv.className = 'scan-result processing';
            document.getElementById('resultIcon').textContent = '⏳';
            document.getElementById('resultTitle').textContent = 'Processing...';
            document.getElementById('resultDetail').textContent = 'Verifying QR data...';

            const formData = new FormData();
            formData.append('action', 'scan');
            formData.append('qr_payload', payload);
            formData.append('scan_type', scanType);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'scan-result success';
                    document.getElementById('resultIcon').textContent = '✅';
                    document.getElementById('resultTitle').textContent = data.message;
                    document.getElementById('resultDetail').textContent = data.employee + ' at ' + data.time;
                    
                    if (navigator.vibrate) navigator.vibrate(200);
                    
                    setTimeout(() => { 
                        location.reload(); 
                    }, 1500);
                } else {
                    resultDiv.className = 'scan-result error';
                    document.getElementById('resultIcon').textContent = '❌';
                    document.getElementById('resultTitle').textContent = 'Failed';
                    document.getElementById('resultDetail').textContent = data.message;
                    
                    if (navigator.vibrate) navigator.vibrate([100, 100, 100]);
                    
                    setTimeout(() => { 
                        isProcessing = false; 
                        scanCooldown = false;
                    }, 2000);
                }
            })
            .catch(err => {
                resultDiv.className = 'scan-result error';
                document.getElementById('resultIcon').textContent = '⚠️';
                document.getElementById('resultTitle').textContent = 'Error';
                document.getElementById('resultDetail').textContent = 'Connection failed';
                setTimeout(() => { 
                    isProcessing = false; 
                    scanCooldown = false;
                }, 2000);
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log('QR detected:', decodedText);
            
            const resultDiv = document.getElementById('scan-result');
            resultDiv.className = 'scan-result processing';
            document.getElementById('resultIcon').textContent = '📱';
            document.getElementById('resultTitle').textContent = 'QR Detected!';
            document.getElementById('resultDetail').textContent = 'Processing attendance...';
            
            processScan(decodedText);
            
            scanCooldown = true;
            setTimeout(() => {
                scanCooldown = false;
            }, 3000);
        }

        function onScanFailure(error) {}

        function startScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
            
            try {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader",
                    { 
                        fps: 15, 
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1.0
                    },
                    false
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                
                document.getElementById('scan-result').className = 'scan-result';
                document.getElementById('resultIcon').textContent = '📷';
                document.getElementById('resultTitle').textContent = 'Ready to Scan';
                document.getElementById('resultDetail').textContent = 'Point camera at QR code';
            } catch(e) {
                console.error('Scanner error:', e);
                document.getElementById('scan-result').className = 'scan-result error';
                document.getElementById('resultIcon').textContent = '❌';
                document.getElementById('resultTitle').textContent = 'Scanner Error';
                document.getElementById('resultDetail').textContent = e.message;
            }
        }

        document.getElementById('manualForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const val = document.getElementById('manualQR').value.trim();
            if (val) {
                processScan(val);
                document.getElementById('manualQR').value = '';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            startScanner();
        });

        window.addEventListener('beforeunload', function() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
        });
    </script>
</body>
</html>