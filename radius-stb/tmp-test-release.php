<?php
require_once '/var/www/html/billing/config.php';
require_once '/var/www/html/billing/includes/radius_helper.php';
$conn = db();

$row = $conn->query("SELECT * FROM pppoe_users WHERE username = 'iroel'")->fetch_assoc();
$userId = $row['id'];

echo "=== RELEASE ISOLIR ===\n";
$result = isolirRelease($conn, $userId);
echo "Result: " . ($result['ok'] ? 'OK' : 'FAIL') . "\n";
echo "Message: " . $result['msg'] . "\n";
