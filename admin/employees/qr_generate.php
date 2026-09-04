<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();
$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$employee_id) {
    $_SESSION['error'] = 'No employee specified';
    header('Location: qr.php');
    exit();
}

$employee = dbFetch("SELECT * FROM employees WHERE id = ? AND company_id = ?", [$employee_id, $company_id]);

if (!$employee) {
    $_SESSION['error'] = 'Employee not found';
    header('Location: qr.php');
    exit();
}

// Generate QR data
$qr_data = json_encode([
    'employee_id' => (int)$employee['id'],
    'code' => (string)$employee['employee_code'],
    'name' => (string)$employee['first_name'] . ' ' . (string)$employee['last_name']
]);

// Create QR code using phpqrcode library
require_once __DIR__ . '/../../includes/lib/phpqrcode/qrlib.php';

// Create temp directory
$temp_dir = __DIR__ . '/../../assets/temp/';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

$temp_file = $temp_dir . 'qr_' . md5($qr_data . time()) . '.png';

// Generate QR code
QRcode::png($qr_data, $temp_file, QR_ECLEVEL_H, 10, 2);

if (file_exists($temp_file)) {
    $qr_image_data = file_get_contents($temp_file);
    $qr_base64 = base64_encode($qr_image_data);
    
    // Delete temp file
    unlink($temp_file);
    
    // Save to database
    $stmt = $db->prepare("UPDATE employees SET qr_code = ? WHERE id = ?");
    $result = $stmt->execute([$qr_base64, $employee_id]);
    
    if ($result) {
        $_SESSION['success'] = "QR code generated successfully for " . $employee['first_name'] . ' ' . $employee['last_name'];
    } else {
        $_SESSION['error'] = "Failed to save QR code to database";
    }
} else {
    $_SESSION['error'] = "Failed to generate QR code image";
}

header('Location: qr.php');
exit();
?>