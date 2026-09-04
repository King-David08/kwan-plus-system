<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();
$employee_id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'deactivate';

if (!$employee_id) {
    $_SESSION['error'] = 'No employee specified';
    header('Location: index.php');
    exit();
}

// Get employee
$employee = dbFetch("SELECT * FROM employees WHERE id = ? AND company_id = ?", [$employee_id, $company_id]);
if (!$employee) {
    $_SESSION['error'] = 'Employee not found';
    header('Location: index.php');
    exit();
}

// Process the action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmed_action = $_POST['action'] ?? '';
    
    if ($confirmed_action === 'deactivate') {
        $stmt = $db->prepare("UPDATE employees SET status = 'inactive' WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        
        auditLog('deactivate_employee', 'employees', $employee_id, ['status' => 'active'], ['status' => 'inactive']);
        $_SESSION['success'] = "Employee deactivated successfully!";
        header('Location: index.php');
        exit();
        
    } elseif ($confirmed_action === 'activate') {
        $stmt = $db->prepare("UPDATE employees SET status = 'active' WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        
        auditLog('activate_employee', 'employees', $employee_id, ['status' => 'inactive'], ['status' => 'active']);
        $_SESSION['success'] = "Employee activated successfully!";
        header('Location: index.php');
        exit();
        
    } elseif ($confirmed_action === 'delete') {
        // Check if employee has attendance records
        $count = dbCount("SELECT id FROM attendance_logs WHERE employee_id = ?", [$employee_id]);
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete employee with attendance records. Deactivate instead.";
            header('Location: index.php');
            exit();
        }
        
        $stmt = $db->prepare("DELETE FROM employees WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        
        auditLog('delete_employee', 'employees', $employee_id, $employee, null);
        $_SESSION['success'] = "Employee deleted successfully!";
        header('Location: index.php');
        exit();
    }
}

// Determine the action type for display
$action_type = '';
$action_color = '';
$action_icon = '';
$action_text = '';
$confirm_text = '';

if ($action === 'deactivate') {
    $action_type = 'Deactivate';
    $action_color = '#f87171';
    $action_icon = 'fa-user-slash';
    $action_text = 'This employee will be deactivated but their data will be preserved.';
    $confirm_text = 'Yes, Deactivate Employee';
} elseif ($action === 'activate') {
    $action_type = 'Activate';
    $action_color = '#34d399';
    $action_icon = 'fa-user-check';
    $action_text = 'This employee will be reactivated and can access the system again.';
    $confirm_text = 'Yes, Activate Employee';
} elseif ($action === 'delete') {
    $action_type = 'Delete';
    $action_color = '#ef4444';
    $action_icon = 'fa-trash';
    $action_text = 'This action cannot be undone. All employee data will be permanently removed.';
    $confirm_text = 'Yes, Delete Permanently';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action_type ?> Employee - Kwan Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0a0f1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            color: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .page-wrapper {
            padding: 24px 28px 40px;
            margin-left: 260px;
            margin-top: 72px;
            min-height: calc(100vh - 72px);
            background: #0a0f1a;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .card {
            background: rgba(26, 35, 50, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.06);
            text-align: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .card .icon-container {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            background: <?= $action === 'delete' ? 'rgba(239, 68, 68, 0.15)' : ($action === 'activate' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(251, 191, 36, 0.15)') ?>;
            color: <?= $action_color ?>;
            border: 1px solid <?= $action === 'delete' ? 'rgba(239, 68, 68, 0.2)' : ($action === 'activate' ? 'rgba(34, 197, 94, 0.2)' : 'rgba(251, 191, 36, 0.2)') ?>;
        }

        .card h2 {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .card .employee-name {
            font-size: 16px;
            color: #818cf8;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .card .employee-code {
            font-size: 13px;
            color: #94a3b8;
            display: block;
            margin-bottom: 16px;
        }

        .card .message {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .card .warning {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.12);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #f87171;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card .warning i {
            font-size: 16px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-danger {
            background: <?= $action === 'delete' ? '#ef4444' : ($action === 'activate' ? '#22c55e' : '#f59e0b') ?>;
            color: white;
        }

        .btn-danger:hover {
            background: <?= $action === 'delete' ? '#dc2626' : ($action === 'activate' ? '#16a34a' : '#d97706') ?>;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px <?= $action === 'delete' ? 'rgba(239, 68, 68, 0.3)' : ($action === 'activate' ? 'rgba(34, 197, 94, 0.3)' : 'rgba(251, 191, 36, 0.3)') ?>;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .detail-row .label {
            color: #94a3b8;
            font-size: 13px;
        }

        .detail-row .value {
            color: #ffffff;
            font-size: 13px;
            font-weight: 500;
        }

        .divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin: 16px 0;
        }

        @media (max-width: 768px) {
            .page-wrapper {
                margin-left: 0;
                padding: 16px;
            }

            .card {
                padding: 28px 24px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="page-wrapper">
        <div class="card">
            <div class="icon-container">
                <i class="fas <?= $action_icon ?>"></i>
            </div>

            <h2><?= $action_type ?> Employee</h2>
            <div class="employee-name"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></div>
            <span class="employee-code"><?= htmlspecialchars($employee['employee_code']) ?></span>

            <hr class="divider">

            <div class="detail-row">
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($employee['email'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Division</span>
                <span class="value"><?= htmlspecialchars($employee['division_name'] ?? 'Unassigned') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Position</span>
                <span class="value"><?= htmlspecialchars($employee['position'] ?? '—') ?></span>
            </div>
            <div class="detail-row">
                <span class="label">Current Status</span>
                <span class="value" style="color: <?= $employee['status'] === 'active' ? '#34d399' : '#f87171' ?>;">
                    <?= ucfirst(str_replace('_', ' ', $employee['status'])) ?>
                </span>
            </div>

            <hr class="divider">

            <div class="message"><?= $action_text ?></div>

            <?php if ($action === 'delete'): ?>
            <div class="warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>This action is permanent and cannot be undone.</span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $action ?>">
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas <?= $action_icon ?>"></i>
                        <?= $confirm_text ?>
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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