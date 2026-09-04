<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// ============================================
// GENERAL HELPER FUNCTIONS
// ============================================

function formatCurrency($amount, $currency = 'GHS') {
    return $currency . ' ' . number_format($amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function formatTime($time, $format = 'h:i A') {
    if (!$time) return '—';
    return date($format, strtotime($time));
}

function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    if (!$datetime) return '—';
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}

// ============================================
// EMPLOYEE CODE GENERATION - FIXED
// ============================================

function generateEmployeeCode($company_id) {
    $db = getDB();
    
    // Get the highest employee code for this company (only active employees)
    $stmt = $db->prepare("
        SELECT employee_code 
        FROM employees 
        WHERE company_id = ? 
        AND employee_code LIKE 'EMP%' 
        AND employee_code != ''
        ORDER BY CAST(SUBSTRING(employee_code, 4) AS UNSIGNED) DESC 
        LIMIT 1
    ");
    $stmt->execute([$company_id]);
    $last = $stmt->fetch();
    
    if ($last && !empty($last['employee_code'])) {
        // Extract number from EMP0001
        $num = (int)substr($last['employee_code'], 3);
        $new_num = $num + 1;
    } else {
        $new_num = 1;
    }
    
    $new_code = 'EMP' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    
    // Double-check the code doesn't already exist (safety check)
    $check = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND company_id = ?");
    $check->execute([$new_code, $company_id]);
    if ($check->fetch()) {
        // If it exists, find the next available number
        $stmt = $db->prepare("
            SELECT MAX(CAST(SUBSTRING(employee_code, 4) AS UNSIGNED)) as max_num 
            FROM employees 
            WHERE company_id = ? AND employee_code LIKE 'EMP%' AND employee_code != ''
        ");
        $stmt->execute([$company_id]);
        $max = $stmt->fetch();
        $new_num = ($max['max_num'] ?? 0) + 1;
        $new_code = 'EMP' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }
    
    return $new_code;
}

function generateQRData($employee_id) {
    return json_encode([
        'employee_id' => $employee_id,
        'timestamp' => time(),
        'hash' => md5($employee_id . time() . 'kwanplus2026')
    ]);
}

function getStatusBadge($status) {
    $statuses = [
        'active' => ['class' => 'badge-success', 'text' => 'Active'],
        'inactive' => ['class' => 'badge-danger', 'text' => 'Inactive'],
        'terminated' => ['class' => 'badge-danger', 'text' => 'Terminated'],
        'on_leave' => ['class' => 'badge-warning', 'text' => 'On Leave'],
        'pending' => ['class' => 'badge-warning', 'text' => 'Pending'],
        'approved' => ['class' => 'badge-success', 'text' => 'Approved'],
        'rejected' => ['class' => 'badge-danger', 'text' => 'Rejected'],
        'on_time' => ['class' => 'badge-success', 'text' => 'On Time'],
        'late' => ['class' => 'badge-warning', 'text' => 'Late'],
        'absent' => ['class' => 'badge-danger', 'text' => 'Absent'],
    ];
    
    $status = $statuses[$status] ?? ['class' => 'badge-secondary', 'text' => ucfirst($status)];
    return '<span class="badge ' . $status['class'] . '">' . $status['text'] . '</span>';
}

function getCompanyTypeIcon($type) {
    $icons = [
        'restaurant' => '🍽️',
        'bar' => '🍺',
        'both' => '🏢'
    ];
    return $icons[$type] ?? '🏢';
}

function getCompanyTypeLabel($type) {
    $labels = [
        'restaurant' => 'Restaurant',
        'bar' => 'Bar & Lounge',
        'both' => 'Both'
    ];
    return $labels[$type] ?? ucfirst($type);
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^\+?[0-9]{8,15}$/', $phone);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

function uploadFile($file, $target_dir = null) {
    if (!$target_dir) {
        $target_dir = UPLOAD_PATH;
    }
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $errors = [];
    $max_size = MAX_FILE_SIZE;
    $allowed = ALLOWED_EXTENSIONS;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return ['success' => false, 'errors' => $errors];
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds limit';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed)) {
        $errors[] = 'File type not allowed';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    $filename = uniqid() . '.' . $extension;
    $target_path = $target_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'filename' => $filename, 'path' => $target_path];
    }
    
    return ['success' => false, 'errors' => ['Failed to save file']];
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

function addNotification($user_id, $title, $message, $type = 'info', $link = null, $company_id = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, company_id, title, message, type, link) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $company_id, $title, $message, $type, $link]);
}

function getNotifications($user_id, $limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function markNotificationRead($notification_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

// ============================================
// AUDIT LOG FUNCTIONS
// ============================================

function auditLog($action, $table = null, $record_id = null, $old_data = null, $new_data = null) {
    $db = getDB();
    $user_id = $_SESSION['user_id'] ?? null;
    $company_id = $_SESSION['company_id'] ?? null;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, company_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id,
            $company_id,
            $action,
            $table,
            $record_id,
            $old_data ? json_encode($old_data) : null,
            $new_data ? json_encode($new_data) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        return false;
    }
}