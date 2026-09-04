<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();

// Unlock all users
$stmt = $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL, status = 'active'");
$stmt->execute();

echo "✅ All accounts unlocked!<br><br>";

// Show users
$users = $db->query("SELECT id, username, email, role, status, login_attempts FROM users")->fetchAll();

echo "<h3>Users:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Attempts</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . $user['status'] . "</td>";
    echo "<td>" . $user['login_attempts'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><a href='login.php'>Go to Login</a>";
?>