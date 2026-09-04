<?php
// index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header("Location: /kwan-plus-system/login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
    header("Location: /kwan-plus-system/modules/employees/index.php");
} else {
    header("Location: /kwan-plus-system/admin/dashboard.php");
}
exit();