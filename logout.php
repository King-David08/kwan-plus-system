<?php
// logout.php
require_once __DIR__ . '/includes/auth.php';

$username = $_SESSION['full_name'] ?? 'User';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out — Kwan Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            padding: 20px;
        }

        .logout-container {
            background: white;
            border-radius: 16px;
            padding: 50px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.06);
            border: 1px solid #ecf0f1;
        }

        .logout-icon {
            width: 72px;
            height: 72px;
            background: #fef3e8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
        }

        .logout-container h2 { font-size: 22px; color: #1a2332; margin-bottom: 4px; }
        .logout-container p { color: #7f8c8d; font-size: 14px; margin-bottom: 24px; }
        .logout-container .user-name { font-weight: 600; color: #1a2332; }

        .btn-group { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }

        .btn-primary {
            padding: 10px 28px;
            background: #f26522;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn-primary:hover { background: #d95b1e; }

        .btn-secondary {
            padding: 10px 28px;
            background: transparent;
            color: #7f8c8d;
            border: 1px solid #e8eaed;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-secondary:hover { background: #f8f9fa; border-color: #bdc3c7; }

        .redirect-timer { margin-top: 16px; font-size: 13px; color: #7f8c8d; }
        .redirect-timer span { font-weight: 600; color: #f26522; }
        .logout-footer { margin-top: 20px; font-size: 12px; color: #bdc3c7; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logout-container { animation: fadeIn 0.4s ease; }
    </style>
</head>
<body>

<div class="logout-container">
    <div class="logout-icon">👋</div>

    <h2>You're Signed Out</h2>
    <p>
        <span class="user-name"><?= htmlspecialchars($username) ?></span>, 
        you have been successfully logged out.
    </p>

    <div class="btn-group">
        <a href="/kwan-plus-system/login.php" class="btn-primary">Sign In Again</a>
        <a href="/kwan-plus-system/index.php" class="btn-secondary">Go to Home</a>
    </div>

    <div class="redirect-timer">
        Redirecting to login in <span id="timer">5</span> seconds...
    </div>

    <div class="logout-footer">
        &copy; <?= date('Y') ?> Kwan Plus. All rights reserved.
    </div>
</div>

<script>
    let seconds = 5;
    const timerElement = document.getElementById('timer');
    const interval = setInterval(() => {
        seconds--;
        timerElement.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = '/kwan-plus-system/login.php';
        }
    }, 1000);
</script>

</body>
</html>