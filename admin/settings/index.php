<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireCompanyAccess();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

$message = '';
$error = '';

// Get current settings
$settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);

if (!$settings) {
    dbInsert("INSERT INTO company_settings (company_id) VALUES (?)", [$company_id]);
    $settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    
    $check_in_start = $_POST['check_in_start'] ?? '05:00:00';
    $check_in_end = $_POST['check_in_end'] ?? '09:00:00';
    $check_out_start = $_POST['check_out_start'] ?? '17:00:00';
    $check_out_end = $_POST['check_out_end'] ?? '23:59:00';
    $late_grace_period = (int)($_POST['late_grace_period'] ?? 15);
    
    try {
        $stmt = $db->prepare("UPDATE company_settings SET 
            check_in_start = ?, check_in_end = ?, 
            check_out_start = ?, check_out_end = ?,
            late_grace_period = ?
            WHERE company_id = ?");
        $stmt->execute([
            $check_in_start, $check_in_end,
            $check_out_start, $check_out_end,
            $late_grace_period,
            $company_id
        ]);
        
        $message = "Settings updated successfully!";
        $settings = dbFetch("SELECT * FROM company_settings WHERE company_id = ?", [$company_id]);
    } catch (PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Kwan Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0f1a; font-family: Arial, sans-serif; color: #e2e8f0; }
        .page-wrapper { padding: 24px 28px 40px; margin-left: 260px; margin-top: 72px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #ffffff; }
        .page-header .subtitle { font-size: 14px; color: #94a3b8; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: rgba(255,255,255,0.06); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.08); }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .card { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); border-radius: 12px; padding: 28px 32px; border: 1px solid rgba(255,255,255,0.06); max-width: 600px; transition: all 0.3s; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; font-size: 14px; background: rgba(255,255,255,0.04); color: #e2e8f0; outline: none; transition: all 0.3s; box-sizing: border-box; }
        .form-group input:focus { border-color: rgba(99,102,241,0.4); background: rgba(255,255,255,0.08); box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        .form-group input[type="time"] { color-scheme: dark; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 6px; }
        .form-actions { display: flex; gap: 12px; margin-top: 10px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.06); }
        .form-actions .btn { padding: 11px 28px; }
        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.15); color: #34d399; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.15); color: #f87171; }
        .text-muted { color: #94a3b8; font-size: 12px; display: block; margin-top: 4px; }
        .setting-section { margin-bottom: 8px; }
        .setting-section .section-title { font-size: 14px; font-weight: 600; color: #ffffff; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .header-actions { display: flex; gap: 10px; }
        @media (max-width: 768px) { .page-wrapper { margin-left: 0; padding: 16px; } .page-header { flex-direction: column; align-items: flex-start; gap: 12px; } .form-row { grid-template-columns: 1fr; gap: 0; } .card { padding: 20px; } .form-actions { flex-direction: column; } .form-actions .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-cog" style="color:#818cf8;"></i> Attendance Settings</h1>
                <p class="subtitle">Configure check-in and check-out time windows</p>
            </div>
            <div class="header-actions">
                <a href="../attendance/scanner.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Scanner
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="setting-section">
                    <div class="section-title"><i class="fas fa-sign-in-alt" style="color:#34d399;"></i> Check-in Settings</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="check_in_start">Start Time</label>
                            <input type="time" id="check_in_start" name="check_in_start" 
                                   value="<?= $settings['check_in_start'] ?? '05:00' ?>">
                        </div>
                        <div class="form-group">
                            <label for="check_in_end">End Time</label>
                            <input type="time" id="check_in_end" name="check_in_end" 
                                   value="<?= $settings['check_in_end'] ?? '09:00' ?>">
                        </div>
                    </div>
                    <div class="text-muted">Employees can only check-in during this time window</div>
                </div>

                <div class="setting-section" style="margin-top:20px;">
                    <div class="section-title"><i class="fas fa-sign-out-alt" style="color:#f87171;"></i> Check-out Settings</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="check_out_start">Start Time</label>
                            <input type="time" id="check_out_start" name="check_out_start" 
                                   value="<?= $settings['check_out_start'] ?? '17:00' ?>">
                        </div>
                        <div class="form-group">
                            <label for="check_out_end">End Time</label>
                            <input type="time" id="check_out_end" name="check_out_end" 
                                   value="<?= $settings['check_out_end'] ?? '23:59' ?>">
                            <span class="text-muted">23:59 = unlimited (no end time)</span>
                        </div>
                    </div>
                    <div class="text-muted">Employees can check-out from this time onwards</div>
                </div>

                <div class="setting-section" style="margin-top:20px;">
                    <div class="section-title"><i class="fas fa-clock" style="color:#fbbf24;"></i> Late Grace Period</div>
                    <div class="form-group">
                        <label for="late_grace_period">Grace Period (minutes)</label>
                        <input type="number" id="late_grace_period" name="late_grace_period" 
                               value="<?= $settings['late_grace_period'] ?? 15 ?>" min="0" max="60">
                        <span class="text-muted">Minutes after check-in end time before marking as late</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>

        <div style="margin-top:16px;padding:16px;background:rgba(26,35,50,0.6);border-radius:8px;border:1px solid rgba(255,255,255,0.06);max-width:600px;">
            <p style="font-size:13px;color:#94a3b8;">
                <i class="fas fa-info-circle" style="color:#818cf8;"></i>
                <strong>Current Time:</strong> <?= date('h:i:s A') ?> |
                <strong>Check-in:</strong> <?= date('h:i A', strtotime($settings['check_in_start'] ?? '05:00')) ?> - <?= date('h:i A', strtotime($settings['check_in_end'] ?? '09:00')) ?> |
                <strong>Check-out:</strong> <?= date('h:i A', strtotime($settings['check_out_start'] ?? '17:00')) ?> onwards
            </p>
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