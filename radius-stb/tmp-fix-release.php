<?php
require_once '/var/www/html/billing/config.php';
$conn = db();

// Remove Framed-IP-Address for iroel (so they get normal IP from pool-PPPoE)
$conn->query("DELETE FROM radreply WHERE username = 'iroel' AND attribute = 'Framed-IP-Address'");
echo "Framed-IP-Address removed for iroel\n";

// Verify
$result = $conn->query("SELECT * FROM radreply WHERE username = 'iroel'");
echo "Remaining radreply:\n";
while ($row = $result->fetch_assoc()) {
    echo "  {$row['attribute']} = {$row['value']}\n";
}

// Disconnect iroel
$cmd = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active print' 2>&1";
exec($cmd, $out);
echo "\nActive sessions:\n";
foreach ($out as $l) echo "  $l\n";

// Find and remove iroel
$index = -1;
foreach ($out as $line) {
    if (preg_match('/^\s*(\d+)\s+R\s+iroel\s/', $line, $m)) {
        $index = intval($m[1]);
        break;
    }
}

if ($index >= 0) {
    $disconnect = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active remove $index' 2>&1";
    exec($disconnect, $dout, $dret);
    echo "\nDisconnect iroel (index $index): RET=$dret OUT=" . implode(" ", $dout) . "\n";
} else {
    echo "\niroel not found in active sessions\n";
}
