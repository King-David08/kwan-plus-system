<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$companies = getUserCompanies();

// If no companies, redirect to dashboard with error
if (empty($companies)) {
    $_SESSION['error'] = 'No companies assigned to your account.';
    header('Location: dashboard.php');
    exit();
}

// If only one company, auto-select and redirect
if (count($companies) === 1) {
    switchCompany($companies[0]['id']);
    header('Location: dashboard.php');
    exit();
}

// Handle company selection
if (isset($_GET['select'])) {
    $company_id = (int)$_GET['select'];
    if (switchCompany($company_id)) {
        header('Location: dashboard.php');
        exit();
    }
}

// Fetch user display name (fallback to Admin)
$admin_name = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Business — Kwan Plus Systems</title>

    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-deep);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(30, 41, 59, 0.5) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            padding: 24px;
            color: var(--text-main);
            position: relative;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        /* HEADER & ANIMATED WELCOME */
        .welcome-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.25);
            font-size: 12px;
            font-weight: 600;
            color: #a5b4fc;
            margin-bottom: 16px;
            animation: fadeIn 0.8s ease forwards;
        }

        .badge-status i {
            font-size: 10px;
            color: #818cf8;
        }

        /* Animated Title Text - SLOWER & COOLER */
        .animated-welcome {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 12px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .animated-welcome .word {
            display: inline-flex;
            overflow: hidden;
        }

        .animated-welcome .char {
            display: inline-block;
            background: linear-gradient(135deg, #ffffff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transform: translateY(120%) scale(0.8) rotate(8deg);
            opacity: 0;
            animation: slideUpChar 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        /* Keyframes for text animation - SLOWER */
        @keyframes slideUpChar {
            0% {
                transform: translateY(120%) scale(0.8) rotate(8deg);
                opacity: 0;
            }
            50% {
                transform: translateY(-10%) scale(1.05) rotate(-2deg);
                opacity: 1;
            }
            100% {
                transform: translateY(0) scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        /* Glow effect on letters */
        .animated-welcome .char.glow {
            text-shadow: 0 0 30px rgba(99, 102, 241, 0.3);
        }

        .subtitle {
            font-size: 15px;
            color: var(--text-sub);
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.6;
            opacity: 0;
            animation: fadeIn 0.8s ease 1.2s forwards;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        /* COMPANY CARDS GRID - STAGGERED */
        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .company-card {
            background: var(--card-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 36px 28px;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            opacity: 0;
            transform: translateY(40px) scale(0.95);
            animation: cardAppear 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        /* Staggered card delays */
        .company-card:nth-child(1) { animation-delay: 1.0s; }
        .company-card:nth-child(2) { animation-delay: 1.3s; }
        .company-card:nth-child(3) { animation-delay: 1.6s; }
        .company-card:nth-child(4) { animation-delay: 1.9s; }
        .company-card:nth-child(5) { animation-delay: 2.2s; }
        .company-card:nth-child(6) { animation-delay: 2.5s; }

        @keyframes cardAppear {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .company-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--brand-primary), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .company-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 30px 60px -12px rgba(99, 102, 241, 0.25);
        }

        .company-card:hover::before {
            opacity: 1;
        }

        /* Icon Container */
        .icon-wrapper {
            width: 72px;
            height: 72px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
            color: #818cf8;
            transition: all 0.3s ease;
        }

        .company-card:hover .icon-wrapper {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-primary-hover));
            color: #ffffff;
            box-shadow: 0 10px 25px var(--brand-glow);
            transform: scale(1.1) rotate(-4deg);
        }

        .company-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .company-card p {
            font-size: 13px;
            color: var(--text-sub);
            margin-bottom: 24px;
            font-weight: 500;
        }

        .btn-select {
            width: 100%;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            color: #ffffff;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .company-card:hover .btn-select {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            box-shadow: 0 6px 20px var(--brand-glow);
        }

        /* FOOTER */
        .page-footer {
            margin-top: 48px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            opacity: 0;
            animation: fadeIn 0.8s ease 2.5s forwards;
        }

        @media (max-width: 640px) {
            .animated-welcome {
                font-size: 28px;
            }
            .company-card {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body>

    <div class="container">

        <!-- WELCOME HEADER WITH ANIMATED TEXT -->
        <div class="welcome-header">
            <div class="badge-status">
                <i class="fas fa-shield-halved"></i>
                <span>Authenticated Admin Session</span>
            </div>

            <!-- Dynamically Generated Animated Welcome Header -->
            <h1 class="animated-welcome" id="animatedHeading">
                <!-- JavaScript will populate animated letters dynamically -->
            </h1>

            <p class="subtitle">Select a business terminal to launch your dashboard and manage operations.</p>
        </div>

        <!-- COMPANY SELECTION GRID -->
        <div class="company-grid">
            <?php foreach ($companies as $company): 
                $type = strtolower($company['type']);
                
                // Set appropriate FontAwesome icons & labels
                if ($type === 'restaurant') {
                    $icon = 'fa-utensils';
                    $label = 'Restaurant Operations';
                } elseif ($type === 'bar') {
                    $icon = 'fa-glass-martini-alt';
                    $label = 'Bar & Lounge Terminal';
                } else {
                    $icon = 'fa-store';
                    $label = 'Combined Portal';
                }
            ?>
            <a href="?select=<?= $company['id'] ?>" class="company-card">
                <div class="icon-wrapper">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <h3><?= htmlspecialchars($company['name']) ?></h3>
                <p><?= $label ?></p>
                <span class="btn-select">
                    <span>Enter Workspace</span>
                    <i class="fas fa-arrow-right" style="font-size: 11px;"></i>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="page-footer">
            © <?= date('Y') ?> Kwan Plus Systems • Secure Business Portal
        </div>

    </div>

    <!-- ANIMATION SCRIPT FOR WELCOME TEXT -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const adminName = <?= json_encode(ucfirst($admin_name)) ?>;
            const textToAnimate = `Welcome Back, ${adminName}!`;
            const heading = document.getElementById('animatedHeading');

            // Split greeting into words and characters for letter-by-letter animation
            const words = textToAnimate.split(' ');
            let charDelay = 0.12; // Slower start delay

            words.forEach((wordText, wordIndex) => {
                const wordSpan = document.createElement('span');
                wordSpan.classList.add('word');

                [...wordText].forEach((char, charIndex) => {
                    const charSpan = document.createElement('span');
                    charSpan.classList.add('char');
                    charSpan.textContent = char;
                    // Slower animation: 0.08s between each character
                    charSpan.style.animationDelay = `${charDelay}s`;
                    charDelay += 0.08; 
                    wordSpan.appendChild(charSpan);
                });

                // Add space after each word (except the last one)
                if (wordIndex < words.length - 1) {
                    const spaceSpan = document.createElement('span');
                    spaceSpan.textContent = ' ';
                    spaceSpan.style.display = 'inline-block';
                    spaceSpan.style.width = '8px';
                    wordSpan.appendChild(spaceSpan);
                }

                heading.appendChild(wordSpan);
            });

            // Add glow effect to random letters for cool effect
            setTimeout(() => {
                const chars = document.querySelectorAll('.char');
                chars.forEach((char, index) => {
                    setTimeout(() => {
                        char.classList.add('glow');
                    }, index * 80);
                });
            }, 500);
        });
    </script>

</body>
</html>