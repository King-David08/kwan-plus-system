<?php
// Application Configuration
define('APP_NAME', 'Kwan Plus Enterprise');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://localhost/kwan-plus-system');
define('BASE_URL', 'http://localhost/kwan-plus-system/'); // ADD THIS LINE
define('APP_TIMEZONE', 'Africa/Accra');

// Security
define('SALT_ROUNDS', 10);
define('SESSION_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 999);
define('LOCKOUT_TIME', 1800);

// File Uploads
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');

// QR Code
define('QR_CODE_SIZE', 300);
define('QR_CODE_MARGIN', 10);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function enforce_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}