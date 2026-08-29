<?php
require_once '/var/www/html/billing/config.php';
require_once '/var/www/html/billing/includes/radius_helper.php';
$conn = db();

$row = $conn->query("SELECT * FROM pppoe_users WHERE username = 'iroel'")->fetch_assoc();
$userId = $row['id'];

echo "=== 1. ISOLIR ===\n";
$r1 = isolirApply($conn, $userId);
echo ($r1['ok'] ? 'OK' : 'FAIL') . ": " . $r1['msg'] . "\n";

echo "\n=== 2. RELEASE ===\n";
$r2 = isolirRelease($conn, $userId);
echo ($r2['ok'] ? 'OK' : 'FAIL') . ": " . $r2['msg'] . "\n";

echo "\n=== 3. VERIFY ===\n";
$check = $conn->query("SELECT * FROM radreply WHERE username = 'iroel'")->fetch_all(MYSQLI_ASSOC);
echo "radreply: " . json_encode($check) . "\n";
$grp = $conn->query("SELECT * FROM radusergroup WHERE username = 'iroel'")->fetch_assoc();
echo "group: " . ($grp['groupname'] ?? 'none') . "\n";
