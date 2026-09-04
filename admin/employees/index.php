<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

// Filters
$search = $_GET['search'] ?? '';
$division_id = $_GET['division'] ?? '';
$status_filter = $_GET['status'] ?? 'active';

// Build query
$sql = "SELECT e.*, d.name as division_name 
        FROM employees e
        LEFT JOIN divisions d ON d.id = e.division_id
        WHERE e.company_id = ?";
$params = [$company_id];

if ($search) {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($division_id) {
    $sql .= " AND e.division_id = ?";
    $params[] = $division_id;
}

if ($status_filter) {
    $sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY e.first_name ASC";

$employees = dbFetchAll($sql, $params);

// Get divisions for filter
$divisions = dbFetchAll("SELECT id, name FROM divisions WHERE company_id = ? AND status = 'active'", [$company_id]);

// Count stats
$total = count($employees);
$active = count(array_filter($employees, fn($e) => $e['status'] === 'active'));
$inactive = count(array_filter($employees, fn($e) => $e['status'] === 'inactive'));
$on_leave = count(array_filter($employees, fn($e) => $e['status'] === 'on_leave'));

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Kwan Plus</title>
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
        .btn-info { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.15); }
        .btn-info:hover { background: rgba(59,130,246,0.3); }
        .btn-danger { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.15); }
        .btn-danger:hover { background: rgba(239,68,68,0.3); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

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
        .stat-box .number.text-danger { color: #f87171; }

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

        .filter-form .form-group input::placeholder {
            color: #64748b;
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

        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-placeholder {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .employee-name { font-weight: 500; color: #ffffff; }
        .employee-email { font-size: 12px; color: #94a3b8; }

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

        .action-buttons { display: flex; gap: 4px; flex-wrap: wrap; }

        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.15);
            color: #34d399;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .text-center { text-align: center; }
        .text-muted { color: #64748b; }

        .header-actions { display: flex; gap: 10px; }

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
                <h1><i class="fas fa-users" style="color:#818cf8;"></i> Employees</h1>
                <p class="subtitle">Manage your workforce across all divisions</p>
            </div>
            <div class="header-actions">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Employee
                </a>
                <a href="qr.php" class="btn btn-secondary">
                    <i class="fas fa-qrcode"></i> QR Codes
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="number"><?= $total ?></div>
                <div class="label">Total</div>
            </div>
            <div class="stat-box">
                <div class="number text-success"><?= $active ?></div>
                <div class="label">Active</div>
            </div>
            <div class="stat-box">
                <div class="number text-warning"><?= $on_leave ?></div>
                <div class="label">On Leave</div>
            </div>
            <div class="stat-box">
                <div class="number text-danger"><?= $inactive ?></div>
                <div class="label">Inactive</div>
            </div>
        </div>

        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group">
                    <label>Division</label>
                    <select name="division">
                        <option value="">All Divisions</option>
                        <?php foreach ($divisions as $div): ?>
                        <option value="<?= $div['id'] ?>" <?= $division_id == $div['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($div['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="on_leave" <?= $status_filter == 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                        <option value="terminated" <?= $status_filter == 'terminated' ? 'selected' : '' ?>>Terminated</option>
                        <option value="">All Status</option>
                    </select>
                </div>
                <div class="form-group" style="display:flex; gap:8px; align-items:center;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Division</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:30px 0;">
                                <i class="fas fa-info-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>
                                No employees found. 
                                <a href="add.php" style="color:#818cf8;font-weight:600;">Add your first employee</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td>
                                <div class="employee-cell">
                                    <div class="avatar-placeholder">
                                        <?= substr($emp['first_name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="employee-name">
                                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                        </div>
                                        <div class="employee-email"><?= htmlspecialchars($emp['email'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($emp['division_name'] ?? 'Unassigned') ?></td>
                            <td><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-secondary';
                                if ($emp['status'] === 'active') $badge_class = 'badge-success';
                                elseif ($emp['status'] === 'inactive') $badge_class = 'badge-danger';
                                elseif ($emp['status'] === 'on_leave') $badge_class = 'badge-warning';
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst(str_replace('_', ' ', $emp['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewEmployee('<?= $emp['id'] ?>')" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="qr.php?action=generate&id=<?= $emp['id'] ?>" class="btn btn-sm btn-secondary" title="Generate QR">
                                        <i class="fas fa-qrcode"></i>
                                    </a>
                                    <?php if ($emp['status'] === 'active'): ?>
                                    <a href="delete.php?id=<?= $emp['id'] ?>&action=deactivate" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Deactivate this employee?')"
                                       title="Deactivate">
                                        <i class="fas fa-user-slash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 id="modalEmployeeName">Employee Name</h2>
            <hr class="modal-divider">
            <div class="detail-row">
                <span class="label">Email</span>
                <span class="value" id="modalEmail">—</span>
            </div>
            <div class="detail-row">
                <span class="label">Phone</span>
                <span class="value" id="modalPhone">—</span>
            </div>
            <div class="detail-row">
                <span class="label">Division</span>
                <span class="value" id="modalDivision">—</span>
            </div>
            <div class="detail-row">
                <span class="label">Position</span>
                <span class="value" id="modalPosition">—</span>
            </div>
            <div class="detail-row">
                <span class="label">Hire Date</span>
                <span class="value" id="modalHireDate">—</span>
            </div>
            <div class="detail-row">
                <span class="label">Status</span>
                <span class="value" id="modalStatus">—</span>
            </div>
            <hr class="modal-divider">
            <div class="modal-actions">
                <a href="#" id="modalEditBtn" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active { display: flex; }

        .modal-content {
            background: rgba(26, 35, 50, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalFade 0.3s ease;
            position: relative;
        }

        @keyframes modalFade {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-close:hover { color: #ffffff; }

        .modal-content h2 {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .modal-divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin: 12px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .detail-row .label { color: #94a3b8; font-size: 13px; }
        .detail-row .value { color: #ffffff; font-size: 13px; font-weight: 500; text-align: right; }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            justify-content: flex-end;
        }

        .modal-actions .btn { padding: 8px 20px; font-size: 13px; }
    </style>

    <script>
        // Employee data from PHP
        const employeesData = <?= json_encode($employees) ?>;

        function viewEmployee(id) {
            const employee = employeesData.find(e => e.id == id);
            if (!employee) return;

            document.getElementById('modalEmployeeName').textContent = employee.first_name + ' ' + employee.last_name;
            document.getElementById('modalEmail').textContent = employee.email || '—';
            document.getElementById('modalPhone').textContent = employee.phone || '—';
            document.getElementById('modalDivision').textContent = employee.division_name || 'Unassigned';
            document.getElementById('modalPosition').textContent = employee.position || '—';
            document.getElementById('modalHireDate').textContent = employee.hire_date ? new Date(employee.hire_date).toLocaleDateString() : '—';
            
            const statusMap = {
                'active': 'Active',
                'inactive': 'Inactive',
                'on_leave': 'On Leave',
                'terminated': 'Terminated'
            };
            document.getElementById('modalStatus').textContent = statusMap[employee.status] || employee.status;

            document.getElementById('modalEditBtn').href = 'edit.php?id=' + employee.id;
            document.getElementById('viewModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

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