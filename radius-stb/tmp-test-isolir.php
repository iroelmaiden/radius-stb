<?php
require_once '/var/www/html/billing/config.php';
require_once '/var/www/html/billing/includes/radius_helper.php';
$conn = db();

// Simulate isolir for iroel (user_id from pppoe_users table)
$row = $conn->query("SELECT * FROM pppoe_users WHERE username = 'iroel'")->fetch_assoc();
if (!$row) { echo "User iroel not found in pppoe_users table\n"; exit; }

$userId = $row['id'];
$username = $row['username'];
$ip = $row['ip_address'];

echo "User: $username (ID: $userId, IP: $ip)\n";
echo "---\n";

$result = isolirApply($conn, $userId);
echo "Result: " . ($result['ok'] ? 'OK' : 'FAIL') . "\n";
echo "Message: " . $result['msg'] . "\n";
