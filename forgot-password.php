<?php
$skip_auth_check = true;
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: /kwan-plus-system/index.php");
    exit();
}

$error = '';
$success = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) && empty($email)) {
        $error = "Please enter your username or email.";
    } else {
        $db = getDB();
        
        // Find user by username or email
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "No account found with these credentials.";
        } else {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Generate reset link
            $reset_link = BASE_URL . "reset-password.php?token=" . $token;
            
            // Display success without email
            $success = "Password reset link generated successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Kwan Plus Systems</title>
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
        .brand-icon .name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
        }
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
        .reset-link-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--brand-primary);
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            word-break: break-all;
        }
        .reset-link-box .label {
            font-size: 11px;
            color: var(--text-sub);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .reset-link-box .link {
            color: var(--brand-primary);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }
        .reset-link-box .link:hover { text-decoration: underline; }
        .text-center { text-align: center; margin-top: 16px; }
        .text-center a { color: var(--brand-primary); text-decoration: none; font-weight: 500; font-size: 14px; }
        .text-center a:hover { text-decoration: underline; }
        .security-note {
            margin-top: 20px;
            padding: 12px 16px;
            background: rgba(251, 191, 36, 0.08);
            border: 1px solid rgba(251, 191, 36, 0.15);
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .security-note i { color: #fbbf24; font-size: 16px; }
        .debug-note {
            margin-top: 12px;
            padding: 8px 12px;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-sub);
            text-align: center;
        }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 22px; }
            .reset-link-box .link { font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="brand-icon">
        <div class="icon"><i class="fas fa-key"></i></div>
        <span class="name">Kwan Plus</span>
    </div>

    <h1>Forgot Password</h1>
    <p class="subtitle">Enter your username or email to generate a reset link.</p>

    <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        
        <?php if ($reset_link): ?>
            <div class="reset-link-box">
                <div class="label">🔗 Your Reset Link (Valid 1 Hour)</div>
                <a href="<?= $reset_link ?>" class="link" target="_blank"><?= $reset_link ?></a>
            </div>
            <div class="debug-note">
                <i class="fas fa-info-circle"></i> Click the link above to reset your password.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="forgot-password.php">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <div class="form-group">
            <label>Username or Email</label>
            <input type="text" name="username" placeholder="Enter your username or email" required autofocus>
        </div>

        <button type="submit" class="btn-primary">Generate Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="security-note">
        <i class="fas fa-shield-alt"></i>
        <span>The reset link expires in 1 hour and can only be used once.</span>
    </div>

    <div class="text-center">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>
</body>
</html>