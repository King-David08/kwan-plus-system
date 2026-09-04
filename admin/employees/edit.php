<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();
$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
    header('Location: index.php');
    exit();
}

// Get employee details
$employee = dbFetch("SELECT * FROM employees WHERE id = ? AND company_id = ?", [$employee_id, $company_id]);
if (!$employee) {
    $_SESSION['error'] = 'Employee not found';
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Get divisions
$divisions = dbFetchAll("SELECT id, name FROM divisions WHERE company_id = ? AND status = 'active'", [$company_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $division_id = $_POST['division_id'] ?? null;
    $position = trim($_POST['position'] ?? '');
    $hire_date = $_POST['hire_date'] ?? '';
    $employment_type = $_POST['employment_type'] ?? 'full_time';
    $salary_type = $_POST['salary_type'] ?? 'monthly';
    $salary_amount = $_POST['salary_amount'] ?? 0;
    $status = $_POST['status'] ?? 'active';
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required';
    } else {
        try {
            $old_data = [
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'email' => $employee['email'],
                'phone' => $employee['phone'],
                'division_id' => $employee['division_id'],
                'position' => $employee['position'],
                'status' => $employee['status']
            ];
            
            $stmt = $db->prepare("
                UPDATE employees SET
                    first_name = ?, last_name = ?, email = ?, phone = ?,
                    division_id = ?, position = ?, hire_date = ?,
                    employment_type = ?, salary_type = ?, salary_amount = ?,
                    status = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $first_name, $last_name, $email, $phone,
                $division_id, $position, $hire_date,
                $employment_type, $salary_type, $salary_amount,
                $status, $employee_id, $company_id
            ]);
            
            auditLog('edit_employee', 'employees', $employee_id, $old_data, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'status' => $status
            ]);
            
            $_SESSION['success'] = "Employee updated successfully!";
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $error = 'Error updating employee: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Kwan Plus</title>
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

        .btn-primary { 
            background: #6366f1; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #4f46e5; 
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        .btn-secondary { 
            background: rgba(255, 255, 255, 0.06); 
            color: #e2e8f0;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .btn-secondary:hover { 
            background: rgba(255, 255, 255, 0.12); 
        }

        /* Card */
        .card {
            background: rgba(26, 35, 50, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 28px 32px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            max-width: 800px;
            transition: all 0.3s;
        }

        .card:hover {
            border-color: rgba(255, 255, 255, 0.12);
        }

        .employee-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 6px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
            outline: none;
            transition: all 0.3s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .form-group input::placeholder {
            color: #64748b;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
        }

        .form-group select option {
            background: #1a2332;
            color: #e2e8f0;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .form-actions .btn {
            padding: 11px 28px;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.15);
            color: #34d399;
        }

        .text-muted {
            color: #94a3b8;
        }

        .required-star {
            color: #f87171;
        }

        .employee-code-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 20px;
            font-size: 13px;
            color: #818cf8;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .page-wrapper {
                margin-left: 0;
                padding: 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .employee-form .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
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
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-edit" style="color:#818cf8;"></i> Edit Employee</h1>
                <p class="subtitle">
                    <span class="employee-code-badge"><?= htmlspecialchars($employee['employee_code']) ?></span>
                </p>
            </div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" class="employee-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required-star">*</span></label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?= htmlspecialchars($_POST['first_name'] ?? $employee['first_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required-star">*</span></label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? $employee['last_name']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? $employee['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? $employee['phone']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="division_id">Division</label>
                        <select id="division_id" name="division_id">
                            <option value="">Select Division</option>
                            <?php foreach ($divisions as $div): ?>
                            <option value="<?= $div['id'] ?>" <?= (($employee['division_id'] ?? '') == $div['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($div['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="position">Position / Job Title</label>
                        <input type="text" id="position" name="position"
                               value="<?= htmlspecialchars($_POST['position'] ?? $employee['position']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="hire_date">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date"
                               value="<?= htmlspecialchars($_POST['hire_date'] ?? $employee['hire_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?= ($employee['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($employee['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="on_leave" <?= ($employee['status'] == 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                            <option value="terminated" <?= ($employee['status'] == 'terminated') ? 'selected' : '' ?>>Terminated</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="employment_type">Employment Type</label>
                        <select id="employment_type" name="employment_type">
                            <option value="full_time" <?= ($employee['employment_type'] == 'full_time') ? 'selected' : '' ?>>Full Time</option>
                            <option value="part_time" <?= ($employee['employment_type'] == 'part_time') ? 'selected' : '' ?>>Part Time</option>
                            <option value="contract" <?= ($employee['employment_type'] == 'contract') ? 'selected' : '' ?>>Contract</option>
                            <option value="temporary" <?= ($employee['employment_type'] == 'temporary') ? 'selected' : '' ?>>Temporary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="salary_type">Salary Type</label>
                        <select id="salary_type" name="salary_type">
                            <option value="monthly" <?= ($employee['salary_type'] == 'monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="hourly" <?= ($employee['salary_type'] == 'hourly') ? 'selected' : '' ?>>Hourly</option>
                            <option value="contract" <?= ($employee['salary_type'] == 'contract') ? 'selected' : '' ?>>Contract</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="salary_amount">Salary Amount (GHS)</label>
                    <input type="number" step="0.01" id="salary_amount" name="salary_amount"
                           value="<?= htmlspecialchars($_POST['salary_amount'] ?? $employee['salary_amount']) ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Employee
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