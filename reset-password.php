<?php
$skip_auth_check = true;
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: forgot-password.php");
    exit();
}

$db = getDB();

// Verify token
$stmt = $db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Invalid or expired reset link. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ?, password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hash, $hash, $user['id']]);
        
        $success = "Password reset successfully! You can now login with your new password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Kwan Plus Systems</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #6366f1;
            --brand-primary-hover: #4f46e5;
            --brand-glow: rgba(99, 102, 241, 0.25);
            --bg-deep: #030712;
            --card-glass: rgba(15, 23, 42, 0.85);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-deep);
            background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(30, 41, 59, 0.5) 0px, transparent 50%);
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: var(--card-glass);
            backdrop-filter: blur(24px);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 50px 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.8);
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .brand-icon {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .brand-icon .icon {
            width: 48px;
            height: 48px;
            background: var(--brand-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            box-shadow: 0 8px 20px var(--brand-glow);
        }
        .brand-icon .name { font-size: 22px; font-weight: 700; color: var(--text-main); }
        h1 { font-size: 26px; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
        .subtitle { font-size: 14px; color: var(--text-sub); margin-bottom: 28px; }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-sub);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }
        .form-group input::placeholder { color: var(--text-sub); }
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: var(--brand-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { background: var(--brand-primary-hover); transform: translateY(-2px); box-shadow: 0 8px 24px var(--brand-glow); }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-info {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-info i { color: var(--brand-primary); font-size: 20px; }
        .user-info span { color: var(--text-main); font-weight: 500; }
        .user-info small { color: var(--text-sub); display: block; font-size: 11px; }
        .text-center { text-align: center; margin-top: 16px; }
        .text-center a { color: var(--brand-primary); text-decoration: none; font-weight: 500; font-size: 14px; }
        .text-center a:hover { text-decoration: underline; }
        .security-badge {
            margin-top: 16px;
            padding: 10px 14px;
            background: rgba(251, 191, 36, 0.06);
            border: 1px solid rgba(251, 191, 36, 0.1);
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .security-badge i { color: #fbbf24; }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="brand-icon">
        <div class="icon"><i class="fas fa-lock"></i></div>
        <span class="name">Reset Password</span>
    </div>

    <h1>Set New Password</h1>
    <p class="subtitle">Create a new password for your account.</p>

    <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <div class="text-center" style="margin-top: 20px;">
            <a href="login.php">← Back to Login</a>
        </div>
    <?php endif; ?>

    <?php if (!$error && !$success && $user): ?>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div>
                <span><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></span>
                <small><?= htmlspecialchars($user['email'] ?? '') ?></small>
            </div>
        </div>

        <form method="POST" action="reset-password.php?token=<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required placeholder="Min 6 characters">
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>

            <button type="submit" class="btn-primary">Reset Password</button>
        </form>

        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <span>This link is valid for 1 hour and can only be used once.</span>
        </div>
    <?php endif; ?>

    <div class="text-center" style="margin-top: 16px;">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
</body>
</html>