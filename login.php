<?php
$skip_auth_check = true;
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: /kwan-plus-system/index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $result = login($username, $password);
        if ($result['success']) {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
                header("Location: /kwan-plus-system/modules/employees/index.php");
            } else {
                header("Location: /kwan-plus-system/admin/dashboard.php");
            }
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Kwan Plus Systems</title>

    <!-- Montserrat Font -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 41, 59, 0.5) 0px, transparent 50%);
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        h1 {
            font-weight: 700;
            margin: 0;
            font-size: 26px;
            font-family: 'Montserrat', sans-serif;
            color: var(--text-main);
        }

        p {
            font-size: 14px;
            font-weight: 400;
            line-height: 20px;
            letter-spacing: 0.5px;
            margin: 16px 0 24px;
            color: var(--text-sub);
            font-family: 'Montserrat', sans-serif;
        }

        span {
            font-size: 12px;
            color: var(--text-sub);
            font-family: 'Montserrat', sans-serif;
        }

        a {
            color: #a5b4fc;
            font-size: 13px;
            text-decoration: none;
            margin: 12px 0;
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
        }

        a:hover {
            color: var(--brand-primary);
        }

        button {
            border-radius: 20px;
            border: 1px solid var(--brand-primary);
            background-color: var(--brand-primary);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 600;
            padding: 12px 45px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: transform 80ms ease-in, background 0.3s ease;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
        }

        button:active {
            transform: scale(0.95);
        }

        button:focus {
            outline: none;
        }

        button.btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        button.btn-primary:hover {
            background: var(--brand-primary-hover);
        }

        form {
            background: var(--card-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 50px;
            height: 100%;
            text-align: center;
        }

        input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            padding: 12px 15px;
            margin: 8px 0;
            width: 100%;
            border-radius: 4px;
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            color: var(--text-main);
            transition: all 0.3s ease;
        }

        input::placeholder {
            color: var(--text-sub);
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
        }

        .container {
            background: var(--card-glass);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 28px;
            border: 1px solid var(--card-border);
            box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.8);
            position: relative;
            overflow: hidden;
            width: 920px;
            max-width: 100%;
            min-height: 620px;
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
        }

        .sign-in-container {
            left: 0;
            width: 50%;
            z-index: 2;
        }

        .container.right-panel-active .sign-in-container {
            transform: translateX(100%);
        }

        .forgot-container {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.right-panel-active .forgot-container {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: show 0.6s;
        }

        @keyframes show {
            0%, 49.99% {
                opacity: 0;
                z-index: 1;
            }
            50%, 100% {
                opacity: 1;
                z-index: 5;
            }
        }

        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
        }

        .container.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }

        .overlay {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: 0 0;
            color: #FFFFFF;
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
            overflow: hidden;
        }

        .container.right-panel-active .overlay {
            transform: translateX(50%);
        }

        .overlay-panel {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 40px;
            text-align: center;
            top: 0;
            height: 100%;
            width: 50%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }

        .overlay-left {
            transform: translateX(-20%);
        }

        .container.right-panel-active .overlay-left {
            transform: translateX(0);
        }

        .overlay-right {
            right: 0;
            transform: translateX(0);
        }

        .container.right-panel-active .overlay-right {
            transform: translateX(20%);
        }

        .overlay-panel h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
            font-family: 'Montserrat', sans-serif;
        }

        .overlay-panel p {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            margin: 10px 0 25px;
            font-family: 'Montserrat', sans-serif;
        }

        /* ===== ANIMATED BARTENDER ===== */
        .bartender-container {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 180px;
            z-index: 10;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-10px); }
        }

        .bartender {
            width: 100%;
            height: 100%;
            position: relative;
        }

        /* Body */
        .bartender-body {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 65px;
            height: 75px;
            background: linear-gradient(180deg, #2c3e50, #1a2332);
            border-radius: 15px 15px 5px 5px;
        }

        /* Apron */
        .bartender-apron {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 55px;
            height: 55px;
            background: #2c3e50;
            border-radius: 0 0 5px 5px;
            border: 2px solid #34495e;
        }

        /* Head */
        .bartender-head {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 45px;
            height: 50px;
            background: #fdbb9a;
            border-radius: 50% 50% 30% 30%;
            animation: headBob 2s ease-in-out infinite;
        }

        @keyframes headBob {
            0%, 100% { transform: translateX(-50%) rotate(0deg); }
            25% { transform: translateX(-50%) rotate(5deg); }
            75% { transform: translateX(-50%) rotate(-5deg); }
        }

        /* Hair */
        .bartender-hair {
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 55px;
            height: 28px;
            background: #1a1a2e;
            border-radius: 50% 50% 0 0;
        }

        .bartender-hair::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 5px;
            width: 15px;
            height: 20px;
            background: #1a1a2e;
            border-radius: 0 0 50% 50%;
        }

        .bartender-hair::before {
            content: '';
            position: absolute;
            bottom: -15px;
            right: 5px;
            width: 15px;
            height: 20px;
            background: #1a1a2e;
            border-radius: 0 0 50% 50%;
        }

        /* Eyes */
        .bartender-eye {
            position: absolute;
            top: 20px;
            width: 7px;
            height: 7px;
            background: #1a1a2e;
            border-radius: 50%;
            animation: blink 4s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 90%, 100% { transform: scaleY(1); }
            95% { transform: scaleY(0); }
        }

        .bartender-eye.left {
            left: 10px;
        }

        .bartender-eye.right {
            right: 10px;
        }

        /* Smile */
        .bartender-smile {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            width: 14px;
            height: 7px;
            border-bottom: 2px solid #1a1a2e;
            border-radius: 0 0 50% 50%;
        }

        /* Arms */
        .bartender-arm {
            position: absolute;
            top: 32px;
            width: 22px;
            height: 45px;
            background: #fdbb9a;
            border-radius: 10px;
            animation: pourDrink 2.5s ease-in-out infinite;
        }

        @keyframes pourDrink {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(15deg); }
        }

        .bartender-arm.left {
            left: -12px;
            transform-origin: top right;
        }

        .bartender-arm.right {
            right: -12px;
            transform-origin: top left;
            animation-delay: 0.5s;
        }

        /* Shaker in hand */
        .bartender-shaker {
            position: absolute;
            top: -10px;
            right: -25px;
            width: 18px;
            height: 25px;
            background: #bdc3c7;
            border-radius: 5px 5px 3px 3px;
            border: 1px solid #95a5a6;
            animation: shake 1.5s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }

        .bartender-shaker::after {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 14px;
            height: 10px;
            background: #95a5a6;
            border-radius: 5px 5px 0 0;
        }

        /* Legs */
        .bartender-leg {
            position: absolute;
            bottom: -22px;
            width: 16px;
            height: 28px;
            background: #1a2332;
            border-radius: 0 0 5px 5px;
        }

        .bartender-leg.left {
            left: 15px;
        }

        .bartender-leg.right {
            right: 15px;
        }

        /* Glass on counter */
        .counter-glass {
            position: absolute;
            bottom: 72px;
            right: 35px;
            width: 18px;
            height: 22px;
            border: 2px solid rgba(255,255,255,0.4);
            border-radius: 0 0 5px 5px;
            background: rgba(255,255,255,0.1);
            animation: glowGlass 2s ease-in-out infinite;
        }

        @keyframes glowGlass {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .counter-glass::after {
            content: '🍹';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 18px;
        }

        /* Drink bubbles */
        .bubble {
            position: absolute;
            width: 5px;
            height: 5px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            animation: bubbleUp 3s ease-in-out infinite;
        }

        .bubble:nth-child(1) { bottom: 85px; left: 60px; animation-delay: 0s; }
        .bubble:nth-child(2) { bottom: 75px; left: 78px; animation-delay: 1s; }
        .bubble:nth-child(3) { bottom: 95px; left: 50px; animation-delay: 2s; }

        @keyframes bubbleUp {
            0% { transform: translateY(0) scale(1); opacity: 0.3; }
            100% { transform: translateY(-35px) scale(2); opacity: 0; }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 12px;
            width: 100%;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .form-group {
            width: 100%;
            margin-bottom: 4px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-sub);
            text-align: left;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Montserrat', sans-serif;
        }

        .forgot-link {
            font-size: 12px;
            color: var(--brand-primary);
            cursor: pointer;
            font-weight: 500;
            margin-top: 8px;
            display: inline-block;
            font-family: 'Montserrat', sans-serif;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .brand-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .brand-icon .icon {
            width: 44px;
            height: 44px;
            background: var(--brand-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0 8px 20px var(--brand-glow);
        }

        .brand-icon .name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
            font-family: 'Montserrat', sans-serif;
        }

        footer {
            background-color: transparent;
            color: var(--text-sub);
            font-size: 12px;
            margin-top: 20px;
            text-align: center;
            font-family: 'Montserrat', sans-serif;
        }

        @media (max-width: 768px) {
            .container {
                min-height: 600px;
                width: 100%;
            }

            .form-container {
                width: 100%;
            }

            .sign-in-container {
                width: 100%;
            }

            .container.right-panel-active .sign-in-container {
                transform: translateX(0);
            }

            .forgot-container {
                width: 100%;
                opacity: 0;
                z-index: 1;
            }

            .container.right-panel-active .forgot-container {
                transform: translateX(0);
                opacity: 1;
                z-index: 5;
                animation: show 0.6s;
            }

            .overlay-container {
                display: none;
            }

            .container.right-panel-active .overlay-container {
                display: none;
            }

            form {
                padding: 0 30px;
            }

            .overlay {
                display: none;
            }

            .bartender-container {
                display: none;
            }
        }

        @media (max-width: 480px) {
            form {
                padding: 0 20px;
            }

            .container {
                min-height: 520px;
            }

            button {
                padding: 10px 30px;
                font-size: 11px;
            }

            .bartender-container {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="container" id="container">
    <!-- ===== SIGN IN FORM ===== -->
    <div class="form-container sign-in-container">
        <form method="POST" action="login.php">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="brand-icon">
                <div class="icon"><i class="fas fa-utensils"></i></div>
                <span class="name">Kwan Plus</span>
            </div>

            <h1>Sign In</h1>
            <span>Enter your credentials to access your account</span>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username"
                    name="username" 
                    required 
                    autofocus 
                    placeholder="Enter your username" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    required 
                    placeholder="Enter your password"
                >
            </div>

            <a href="forgot-password.php" class="forgot-link">Forgot your password?</a>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>
    </div>

    <!-- ===== FORGOT PASSWORD SLIDER (OVERLAY) ===== -->
    <div class="overlay-container">
        <div class="overlay">
            <!-- Animated Bartender -->
            <div class="bartender-container">
                <div class="bartender">
                    <div class="bartender-head">
                        <div class="bartender-hair"></div>
                        <div class="bartender-eye left"></div>
                        <div class="bartender-eye right"></div>
                        <div class="bartender-smile"></div>
                    </div>
                    <div class="bartender-body">
                        <div class="bartender-apron"></div>
                        <div class="bartender-arm left"></div>
                        <div class="bartender-arm right">
                            <div class="bartender-shaker"></div>
                        </div>
                        <div class="bartender-leg left"></div>
                        <div class="bartender-leg right"></div>
                    </div>
                    <div style="position:absolute;bottom:-5px;left:-30px;width:200px;height:8px;background:linear-gradient(180deg,#8B7355,#6d5b3d);border-radius:5px;"></div>
                    <div class="counter-glass"></div>
                    <div class="bubble"></div>
                    <div class="bubble"></div>
                    <div class="bubble"></div>
                </div>
            </div>

            <div class="overlay-panel overlay-left">
                <h1>Reset Password?</h1>
                <p>Go to the forgot password page to reset your credentials securely.</p>
                <a href="forgot-password.php" class="ghost" style="display:inline-block;padding:12px 45px;border-radius:20px;border:1px solid #FFFFFF;color:#fff;text-decoration:none;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;transition:all 0.3s ease;">Reset Password</a>
            </div>
            <div class="overlay-panel overlay-right">
                <h1>Welcome Back!</h1>
                <p>Enter your credentials to access your staff portal.</p>
                <button class="ghost" id="signUp">Forgot Password?</button>
            </div>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Kwan Plus Systems. All rights reserved.</p>
</footer>

<script>
    const signUpButton = document.getElementById('signUp');
    const signInButton = document.getElementById('signIn');
    const container = document.getElementById('container');

    function openForgot() {
        container.classList.add("right-panel-active");
    }

    function openSignIn() {
        container.classList.remove("right-panel-active");
    }

    if (signUpButton) {
        signUpButton.addEventListener('click', openForgot);
    }

    if (signInButton) {
        signInButton.addEventListener('click', openSignIn);
    }

    <?php if ($error && ($_POST['action'] ?? '') === 'reset'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openForgot();
        });
    <?php endif; ?>
</script>

</body>
</html>