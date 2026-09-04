<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// ============================================
// COMPANY FUNCTIONS
// ============================================

function getUserCompanies($user_id = null) {
    $db = getDB();
    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    
    if (!$user_id) return [];
    
    $user = dbFetch("SELECT role FROM users WHERE id = ?", [$user_id]);
    if (!$user) return [];
    
    if ($user['role'] === 'super_admin') {
        return dbFetchAll("SELECT * FROM companies WHERE status = 'active' ORDER BY name");
    }
    
    return dbFetchAll("
        SELECT c.* FROM companies c
        JOIN users u ON u.company_id = c.id
        WHERE u.id = ? AND c.status = 'active'
    ", [$user_id]);
}

function getCurrentCompanyId() {
    return $_SESSION['company_id'] ?? null;
}

function getCurrentCompany() {
    $company_id = getCurrentCompanyId();
    if (!$company_id) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    return $stmt->fetch();
}

function switchCompany($company_id) {
    $db = getDB();
    $companies = getUserCompanies();
    $has_access = false;
    foreach ($companies as $c) {
        if ($c['id'] == $company_id) {
            $has_access = true;
            break;
        }
    }
    
    if (!$has_access) return false;
    
    $company = dbFetch("SELECT * FROM companies WHERE id = ? AND status = 'active'", [$company_id]);
    if ($company) {
        $_SESSION['company_id'] = $company['id'];
        $_SESSION['company_name'] = $company['name'];
        $_SESSION['company_type'] = $company['type'];
        return true;
    }
    return false;
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function login_user($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['division_id'] = $user['division_id'];
    $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true);
    
    $companies = getUserCompanies($user['id']);
    $_SESSION['company_ids'] = array_column($companies, 'id');
    
    if (count($companies) === 1) {
        $_SESSION['company_name'] = $companies[0]['name'] ?? null;
        $_SESSION['company_type'] = $companies[0]['type'] ?? null;
        $_SESSION['company_id'] = $companies[0]['id'];
    } elseif (count($companies) > 1) {
        $_SESSION['company_id'] = null;
        $_SESSION['company_name'] = null;
        $_SESSION['company_type'] = null;
    }
}

function login($username, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return ['success' => false, 'message' => 'Account is temporarily locked'];
    }
    
    $password_field = isset($user['password_hash']) ? 'password_hash' : 'password';
    if (!password_verify($password, $user[$password_field])) {
        $attempts = $user['login_attempts'] + 1;
        $lock_until = $attempts >= MAX_LOGIN_ATTEMPTS ? 
            date('Y-m-d H:i:s', time() + LOCKOUT_TIME) : null;
        
        $stmt = $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$attempts, $lock_until, $user['id']]);
        
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    $stmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    login_user($user);
    
    return ['success' => true, 'user' => $user];
}

function logout() {
    session_destroy();
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role'],
        'company_id' => $_SESSION['company_id'] ?? null,
        'division_id' => $_SESSION['division_id'] ?? null,
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}

// ============================================
// ROLE CHECKS
// ============================================

function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function isCompanyAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'company_admin';
}

function isBranchManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'branch_manager';
}

function isSupervisor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'supervisor';
}

function isHR() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

function hasPermission($permission) {
    if (isSuperAdmin()) return true;
    if (isCompanyAdmin()) return true;
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission, $permissions);
}

function hasCompanyAccess($company_id = null) {
    if (isSuperAdmin()) return true;
    $company_id = $company_id ?? getCurrentCompanyId();
    if (!$company_id) return false;
    $companies = getUserCompanies();
    foreach ($companies as $c) {
        if ($c['id'] == $company_id) return true;
    }
    return false;
}

// ============================================
// REQUIRE FUNCTIONS
// ============================================

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /kwan-plus-system/login.php');
        exit();
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: /kwan-plus-system/admin/dashboard.php');
        exit();
    }
}

function requireCompanyAdmin() {
    requireLogin();
    if (!isSuperAdmin() && !isCompanyAdmin()) {
        header('Location: /kwan-plus-system/admin/dashboard.php');
        exit();
    }
}

function requireCompanyAccess() {
    requireLogin();
    $companies = getUserCompanies();
    if (empty($companies)) {
        $_SESSION['error'] = 'No companies assigned to your account.';
        header('Location: /kwan-plus-system/admin/dashboard.php');
        exit();
    }
    if (count($companies) === 1 && empty($_SESSION['company_id'])) {
        $_SESSION['company_id'] = $companies[0]['id'];
        $_SESSION['company_name'] = $companies[0]['name'];
        $_SESSION['company_type'] = $companies[0]['type'];
        return;
    }
    if (count($companies) > 1 && empty($_SESSION['company_id'])) {
        header('Location: /kwan-plus-system/admin/select-company.php');
        exit();
    }
    if (!empty($_SESSION['company_id'])) {
        $valid = false;
        foreach ($companies as $c) {
            if ($c['id'] == $_SESSION['company_id']) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $_SESSION['company_id'] = null;
            $_SESSION['company_name'] = null;
            $_SESSION['company_type'] = null;
            header('Location: /kwan-plus-system/admin/select-company.php');
            exit();
        }
    }
}

function requireEmployee() {
    requireLogin();
    if (!isEmployee() && !isSuperAdmin() && !isCompanyAdmin()) {
        header('Location: /kwan-plus-system/admin/dashboard.php');
        exit();
    }
}

// ============================================
// PERMISSION HELPERS
// ============================================

function canViewEmployees() {
    return isSuperAdmin() || isCompanyAdmin() || isBranchManager() || isSupervisor();
}

function canManageEmployees() {
    return isSuperAdmin() || isCompanyAdmin() || isBranchManager();
}

function canViewAttendance() {
    return isSuperAdmin() || isCompanyAdmin() || isBranchManager() || isSupervisor() || isEmployee();
}

function canManageAttendance() {
    return isSuperAdmin() || isCompanyAdmin() || isBranchManager();
}

function canManageShifts() {
    return isSuperAdmin() || isCompanyAdmin();
}

function canManageLeave() {
    return isSuperAdmin() || isCompanyAdmin() || isHR();
}