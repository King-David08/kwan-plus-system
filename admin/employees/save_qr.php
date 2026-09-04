<?php
require_once __DIR__ . '/../../includes/auth.php';
requireCompanyAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$db = getDB();
$company_id = getCurrentCompanyId();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$employee_id = $input['employee_id'] ?? 0;
$qr_code = $input['qr_code'] ?? '';

if (!$employee_id || !$qr_code) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

// Verify employee belongs to company
$employee = dbFetch("SELECT id FROM employees WHERE id = ? AND company_id = ?", [$employee_id, $company_id]);

if (!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit();
}

// Save QR code to database
$stmt = $db->prepare("UPDATE employees SET qr_code = ? WHERE id = ?");
$result = $stmt->execute([$qr_code, $employee_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'QR code saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save']);
}