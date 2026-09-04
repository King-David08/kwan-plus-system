<?php
// Check if function exists before calling it
$companies = [];
$has_multiple = false;
$current_company = null;

if (function_exists('getUserCompanies')) {
    $companies = getUserCompanies();
    $has_multiple = count($companies) > 1;
    $current_company = getCurrentCompany();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="app-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <a href="dashboard.php" class="header-logo">
            <span class="logo-text">Kwan Plus</span>
        </a>
    </div>
    
    <div class="header-center">
        <?php if ($has_multiple && !empty($companies)): ?>
        <div class="company-selector">
            <select class="company-select" onchange="switchCompany(this.value)">
                <?php foreach ($companies as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($c['id'] == getCurrentCompanyId()) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($current_company): ?>
        <div class="company-badge">
            <span class="company-badge-name"><?= htmlspecialchars($current_company['name']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="header-right">
        <button class="header-btn notif-btn" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifCount">0</span>
        </button>
        
        <div class="user-menu">
            <button class="user-menu-btn" onclick="toggleUserMenu()">
                <span class="user-avatar-small">
                    <?= substr($_SESSION['full_name'] ?? 'U', 0, 1) ?>
                </span>
                <span class="user-name-header"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <div class="dropdown-avatar">
                            <?= substr($_SESSION['full_name'] ?? 'U', 0, 1) ?>
                        </div>
                        <div>
                            <div class="dropdown-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
                            <div class="dropdown-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? '')) ?></div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a href="settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                <a href="system.php" class="dropdown-item">
                    <i class="fas fa-server"></i> System
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= APP_URL ?>/logout.php" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Notification Panel -->
<div class="notification-panel" id="notificationPanel">
    <div class="panel-header">
        <h3>Notifications</h3>
        <button class="panel-close" onclick="toggleNotifications()">✕</button>
    </div>
    <div class="panel-body" id="notificationList">
        <div class="notification-empty">
            <span class="empty-icon">🔔</span>
            <p>No notifications</p>
        </div>
    </div>
</div>

<style>
/* ===== HEADER - GLASSMORPHISM ===== */
.app-header {
    background: rgba(26, 35, 50, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    color: #ffffff;
    padding: 0 24px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.sidebar-toggle {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #ffffff;
    cursor: pointer;
    padding: 8px 10px;
    font-size: 18px;
    border-radius: 8px;
    display: none;
    transition: all 0.3s;
}

.sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.12);
}

.header-logo {
    display: flex;
    align-items: center;
    color: #ffffff;
    text-decoration: none;
    font-size: 20px;
    font-weight: 700;
}

.logo-text {
    color: #ffffff;
    letter-spacing: 0.5px;
}

.header-center {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 0 20px;
}

.company-selector {
    max-width: 280px;
    width: 100%;
}

.company-select {
    width: 100%;
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    font-size: 14px;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    transition: all 0.3s;
}

.company-select:hover {
    background: rgba(255, 255, 255, 0.12);
}

.company-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
}

.company-select option {
    background: #1a2332;
    color: #ffffff;
}

.company-badge {
    display: flex;
    align-items: center;
    padding: 6px 16px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    font-size: 14px;
    color: #ffffff;
}

.company-badge-name {
    font-weight: 500;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-btn {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #ffffff;
    cursor: pointer;
    padding: 8px 10px;
    font-size: 18px;
    position: relative;
    border-radius: 8px;
    transition: all 0.3s;
}

.header-btn:hover {
    background: rgba(255, 255, 255, 0.12);
}

.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 50%;
    min-width: 18px;
    text-align: center;
}

.user-menu {
    position: relative;
}

.user-menu-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #ffffff;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.3s;
}

.user-menu-btn:hover {
    background: rgba(255, 255, 255, 0.12);
}

.user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: white;
}

.user-name-header {
    font-size: 14px;
    font-weight: 500;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: rgba(26, 35, 50, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    min-width: 220px;
    display: none;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.user-dropdown.open {
    display: block;
}

.dropdown-header {
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.04);
}

.dropdown-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dropdown-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
    color: white;
}

.dropdown-name {
    font-weight: 600;
    font-size: 15px;
    color: #ffffff;
}

.dropdown-role {
    font-size: 12px;
    color: #94a3b8;
}

.dropdown-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 4px 0;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    color: #94a3b8;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 14px;
}

.dropdown-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #ffffff;
}

.dropdown-item.text-danger {
    color: #ef4444;
}

.dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* Notifications */
.notification-panel {
    position: fixed;
    top: 72px;
    right: -400px;
    width: 380px;
    max-height: calc(100vh - 72px);
    background: rgba(26, 35, 50, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: -4px 0 30px rgba(0, 0, 0, 0.4);
    z-index: 999;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
    border-left: 1px solid rgba(255, 255, 255, 0.08);
}

.notification-panel.open {
    right: 0;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.panel-header h3 {
    margin: 0;
    font-size: 16px;
    color: #ffffff;
}

.panel-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #94a3b8;
}

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}

.notification-empty {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
}

/* Mobile */
@media (max-width: 768px) {
    .app-header {
        padding: 0 16px;
        height: 64px;
    }
    
    .sidebar-toggle {
        display: block;
    }
    
    .user-name-header {
        display: none;
    }
    
    .company-selector {
        max-width: 140px;
    }
    
    .company-select {
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .notification-panel {
        width: 100%;
        right: -100%;
        top: 64px;
    }
}
</style>

<script>
function switchCompany(companyId) {
    window.location.href = '/kwan-plus-system/admin/dashboard.php?switch_company=' + companyId;
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('open');
}

function toggleNotifications() {
    document.getElementById('notificationPanel').classList.toggle('open');
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const userMenu = document.querySelector('.user-menu');
    if (!userMenu?.contains(event.target)) {
        dropdown?.classList.remove('open');
    }
    
    const panel = document.getElementById('notificationPanel');
    const notifBtn = document.querySelector('.notif-btn');
    if (!notifBtn?.contains(event.target) && !panel?.contains(event.target)) {
        panel?.classList.remove('open');
    }
});

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