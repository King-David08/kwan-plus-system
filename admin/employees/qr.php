<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

// Get all employees with QR status
$employees = dbFetchAll("
    SELECT id, employee_code, first_name, last_name, qr_code, status 
    FROM employees 
    WHERE company_id = ? 
    ORDER BY first_name
", [$company_id]);

$total = count($employees);
$has_qr = count(array_filter($employees, fn($e) => !empty($e['qr_code'])));
$no_qr = $total - $has_qr;

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes - Kwan Plus</title>
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
        .btn-info { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.15); }
        .btn-info:hover { background: rgba(59,130,246,0.3); }
        .btn-success { background: rgba(34,197,94,0.2); color: #34d399; border: 1px solid rgba(34,197,94,0.15); }
        .btn-success:hover { background: rgba(34,197,94,0.3); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .header-actions { display: flex; gap: 10px; }
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); border-radius: 12px; padding: 16px 20px; border: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .stat-box .number { font-size: 24px; font-weight: 700; color: #ffffff; }
        .stat-box .label { font-size: 13px; color: #94a3b8; margin-top: 2px; }
        .stat-box .number.text-success { color: #34d399; }
        .stat-box .number.text-warning { color: #fbbf24; }
        .table-card { background: rgba(26,35,50,0.8); backdrop-filter: blur(12px); border-radius: 12px; border: 1px solid rgba(255,255,255,0.06); overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th { padding: 14px 18px; text-align: left; background: rgba(255,255,255,0.03); font-weight: 600; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 12px; text-transform: uppercase; }
        .table td { padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #e2e8f0; }
        .qr-preview { width: 50px; height: 50px; cursor: pointer; border-radius: 6px; background: white; padding: 4px; border: 1px solid rgba(255,255,255,0.08); }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: rgba(34,197,94,0.15); color: #34d399; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #f87171; }
        .badge-warning { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .badge-secondary { background: rgba(255,255,255,0.06); color: #94a3b8; }
        code { background: rgba(255,255,255,0.06); padding: 2px 8px; border-radius: 4px; color: #94a3b8; }
        .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; }
        .text-muted { color: #64748b; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: rgba(26,35,50,0.95); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 32px; max-width: 450px; width: 90%; text-align: center; }
        .modal-close { float: right; background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer; }
        .modal-close:hover { color: #ffffff; }
        .modal-content h3 { font-size: 18px; color: #ffffff; margin-bottom: 16px; }
        .modal-content .qr-display { background: white; padding: 16px; border-radius: 12px; display: inline-block; margin: 10px 0; }
        .modal-content .qr-name { font-size: 16px; font-weight: 500; color: #ffffff; margin: 10px 0; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 16px; }
        .modal-actions .btn { padding: 8px 20px; font-size: 13px; }
        @media (max-width: 768px) { .page-wrapper { margin-left: 0; padding: 16px; } .stats-row { grid-template-columns: 1fr 1fr; } .page-header { flex-direction: column; align-items: flex-start; gap: 12px; } }
        @media (max-width: 480px) { .stats-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-qrcode" style="color:#818cf8;"></i> QR Code Management</h1>
                <p class="subtitle">Generate and manage QR codes for employees</p>
            </div>
            <div class="header-actions">
                <a href="../attendance/scanner.php" class="btn btn-success">
                    <i class="fas fa-camera"></i> Scanner
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.15);color:#34d399;padding:12px 18px;border-radius:8px;margin-bottom:16px;">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.15);color:#f87171;padding:12px 18px;border-radius:8px;margin-bottom:16px;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box"><div class="number"><?= $total ?></div><div class="label">Total Employees</div></div>
            <div class="stat-box"><div class="number text-success"><?= $has_qr ?></div><div class="label">With QR Code</div></div>
            <div class="stat-box"><div class="number text-warning"><?= $no_qr ?></div><div class="label">Without QR</div></div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>QR Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                            <td><code><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                            <td>
                                <?php if (!empty($emp['qr_code'])): ?>
                                    <img src="data:image/png;base64,<?= $emp['qr_code'] ?>" 
                                         class="qr-preview"
                                         onclick="showQR('<?= $emp['id'] ?>', '<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>', '<?= htmlspecialchars($emp['employee_code']) ?>')">
                                <?php else: ?>
                                    <span class="text-muted">No QR</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $badge_class = 'badge-secondary';
                                if ($emp['status'] === 'active') $badge_class = 'badge-success';
                                elseif ($emp['status'] === 'inactive') $badge_class = 'badge-danger';
                                elseif ($emp['status'] === 'on_leave') $badge_class = 'badge-warning';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst(str_replace('_', ' ', $emp['status'])) ?></span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="qr_generate.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-qrcode"></i> Generate
                                    </a>
                                    <?php if (!empty($emp['qr_code'])): ?>
                                        <button class="btn btn-sm btn-info" onclick="showQR('<?= $emp['id'] ?>', '<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>', '<?= htmlspecialchars($emp['employee_code']) ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- QR View Modal -->
    <div class="modal-overlay" id="qrModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeQR()">&times;</button>
            <h3><i class="fas fa-qrcode" style="color:#818cf8;"></i> Employee QR Code</h3>
            <div class="qr-display"><div id="qrcode-modal"></div></div>
            <div class="qr-name" id="qrEmployeeName">Employee Name</div>
            <div class="modal-actions">
                <button onclick="downloadQR()" class="btn btn-success"><i class="fas fa-download"></i> Download</button>
                <button onclick="printQR()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
                <button onclick="closeQR()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        let currentEmployeeName = '';
        let currentEmployeeCode = '';
        let currentEmployeeId = '';
        let qrCodeInstance = null;

        function showQR(id, name, code) {
            currentEmployeeName = name;
            currentEmployeeCode = code;
            currentEmployeeId = id;
            
            const qrData = JSON.stringify({
                employee_id: id,
                code: code,
                name: name
            });
            
            const container = document.getElementById('qrcode-modal');
            container.innerHTML = '';
            
            qrCodeInstance = new QRCode(container, {
                text: qrData,
                width: 200,
                height: 200,
                colorDark: "#1a2332",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            document.getElementById('qrEmployeeName').textContent = name;
            document.getElementById('qrModal').classList.add('active');
        }

        function closeQR() {
            document.getElementById('qrModal').classList.remove('active');
            if (qrCodeInstance) {
                qrCodeInstance.clear();
                qrCodeInstance = null;
            }
        }

        function downloadQR() {
            const canvas = document.querySelector('#qrcode-modal canvas');
            if (canvas) {
                const link = document.createElement('a');
                link.download = `QR_${currentEmployeeCode}.png`;
                link.href = canvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        function printQR() {
            const canvas = document.querySelector('#qrcode-modal canvas');
            if (canvas) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>QR Code - ${currentEmployeeName}</title>
                            <style>
                                body { display:flex; justify-content:center; align-items:center; height:100vh; flex-direction:column; font-family:Arial, sans-serif; margin:0; background:white; }
                                .qr-box { text-align:center; padding:30px; border:2px solid #ecf0f1; border-radius:12px; }
                                .qr-box img { max-width:300px; }
                                .qr-box h2 { margin:10px 0 5px; font-size:20px; }
                                .qr-box p { margin:0; color:#7f8c8d; font-size:14px; }
                                .footer { margin-top:20px; font-size:12px; color:#999; }
                            </style>
                        </head>
                        <body>
                            <div class="qr-box">
                                <img src="${canvas.toDataURL('image/png')}" alt="QR Code">
                                <h2>${currentEmployeeName}</h2>
                                <p>${currentEmployeeCode}</p>
                                <div class="footer">Kwan Plus Enterprise</div>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }

        // Close modal on outside click
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQR();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQR();
            }
        });
    </script>
</body>
</html>