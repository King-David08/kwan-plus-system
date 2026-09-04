<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();

echo "<h2>Creating Users</h2>";

try {
    // Delete existing users
    $db->exec("DELETE FROM users");
    echo "✅ Cleared existing users<br>";
} catch (PDOException $e) {
    // Table might not exist
    echo "⚠️ Could not clear users: " . $e->getMessage() . "<br>";
}

// Create users with correct password hash
$password = 'Admin@2026';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";

try {
    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, company_id, status, login_attempts) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Insert admin
    $stmt->execute(['admin', $hash, 'System Administrator', 'admin@kwanplus.com', 'super_admin', NULL, 'active', 0]);
    echo "✅ Admin created<br>";
    
    // Insert restaurant admin
    $stmt->execute(['rest_admin', $hash, 'Restaurant Manager', 'rest@kwanplus.com', 'company_admin', 1, 'active', 0]);
    echo "✅ Restaurant Admin created<br>";
    
    // Insert bar admin
    $stmt->execute(['bar_admin', $hash, 'Bar Manager', 'bar@kwanplus.com', 'company_admin', 2, 'active', 0]);
    echo "✅ Bar Admin created<br>";
    
    echo "<br><strong>✅ All users created successfully!</strong><br>";
    echo "<a href='login.php'>Go to Login</a>";
    
} catch (PDOException $e) {
    echo "❌ Error creating users: " . $e->getMessage() . "<br>";
    
    // Check if table exists
    try {
        $db->query("SELECT 1 FROM users LIMIT 1");
    } catch (PDOException $e2) {
        echo "<br>⚠️ Users table doesn't exist!<br>";
        echo "Please run the SQL script to create the users table first.<br>";
    }
}
?>