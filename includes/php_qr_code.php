<?php
/**
 * QR Code Generator
 * Uses the phpqrcode library in includes/lib/phpqrcode/
 */

// Include the phpqrcode library
require_once __DIR__ . '/lib/phpqrcode/qrlib.php';

function generateQRCode($data, $size = 300) {
    // Create temp directory
    $temp_dir = __DIR__ . '/../assets/temp/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_file = $temp_dir . 'qr_' . md5($data . time()) . '.png';
    
    // Generate QR code using phpqrcode library
    QRcode::png($data, $temp_file, QR_ECLEVEL_H, 10, 2);
    
    if (file_exists($temp_file)) {
        $qr_data = file_get_contents($temp_file);
        $base64 = base64_encode($qr_data);
        unlink($temp_file);
        return $base64;
    }
    
    return false;
}

function generateEmployeeQR($employee_id, $employee_code, $employee_name) {
    $data = json_encode([
        'employee_id' => (int)$employee_id,
        'code' => (string)$employee_code,
        'name' => (string)$employee_name
    ]);
    
    return generateQRCode($data, 300);
}
?>