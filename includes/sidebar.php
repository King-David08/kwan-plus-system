<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$company_name = $_SESSION['company_name'] ?? 'Kwan Plus';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <span class="brand-icon"><i class="fas fa-chart-pie"></i></span>
            <span class="brand-name">Kwan Plus</span>
        </div>
        <div class="brand-company"><?= htmlspecialchars($company_name) ?></div>
    </div>
    
    <div class="sidebar-section">
        <div class="section-label">MAIN</div>
        <ul class="nav-menu">
            <li class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <a href="/kwan-plus-system/admin/dashboard.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-th-large"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <div class="section-label">MANAGEMENT</div>
        <ul class="nav-menu">
            <li class="nav-item <?= $current_dir == 'employees' ? 'active' : '' ?>">
                <a href="/kwan-plus-system/admin/employees/index.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                    <span class="nav-text">Employees</span>
                </a>
            </li>
            <li class="nav-item <?= $current_dir == 'attendance' ? 'active' : '' ?>">
                <a href="/kwan-plus-system/admin/attendance/scanner.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-clipboard-check"></i></span>
                    <span class="nav-text">Attendance</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <div class="section-label">ANALYTICS</div>
        <ul class="nav-menu">
            <li class="nav-item <?= $current_dir == 'reports' ? 'active' : '' ?>">
                <a href="/kwan-plus-system/admin/reports/index.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span class="nav-text">Reports</span>
                </a>
            </li>
            <li class="nav-item <?= $current_dir == 'settings' ? 'active' : '' ?>">
                <a href="/kwan-plus-system/admin/settings/index.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?= substr($_SESSION['full_name'] ?? 'U', 0, 1) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
                <div class="user-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? '')) ?></div>
            </div>
        </div>
        <a href="/kwan-plus-system/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<style>
/* ===== SIDEBAR - GLASSMORPHISM ===== */
.sidebar {
    width: 260px;
    background: rgba(26, 35, 50, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    color: #ffffff;
    height: 100vh;
    position: fixed;
    top: 72px;
    left: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
    overflow-y: auto;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    padding: 16px 0 20px;
}

.sidebar-brand {
    padding: 0 20px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
}

.brand-icon {
    font-size: 20px;
    color: #6366f1;
}

.brand-name {
    font-size: 18px;
}

.brand-company {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
    padding-left: 34px;
}

.sidebar-section {
    padding: 12px 0 4px;
}

.section-label {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 600;
    padding: 0 20px 8px;
    letter-spacing: 0.8px;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 2px 10px;
    border-radius: 8px;
}

.nav-item.active {
    background: rgba(99, 102, 241, 0.15);
}

.nav-item.active .nav-link {
    color: #818cf8;
    font-weight: 500;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 14px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.2s;
    font-size: 14px;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #ffffff;
}

.nav-icon {
    width: 20px;
    text-align: center;
    font-size: 15px;
    flex-shrink: 0;
}

.nav-text {
    flex: 1;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 16px 20px 0;
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.user-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 10px;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: white;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 14px;
    font-weight: 500;
    color: #ffffff;
}

.user-role {
    font-size: 11px;
    color: #94a3b8;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px;
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 2px;
}

/* Mobile */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
        top: 0;
        height: 100vh;
        padding-top: 72px;
        box-shadow: 2px 0 30px rgba(0, 0, 0, 0.4);
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}
</style>