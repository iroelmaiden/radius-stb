<?php
// Clean rules and add proper ones
$cmds = [
    // Remove all existing rules
    "/ip proxy access remove [find]",
    // Add deny rule for isolir pool
    "/ip proxy access add src-address=172.30.0.0/16 action=deny",
];

foreach ($cmds as $i => $cmd) {
    $escaped = escapeshellarg($cmd);
    $full = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 $escaped 2>&1";
    exec($full, $out, $ret);
    echo "CMD $i: RET=$ret OUT=" . implode(" ", $out) . "\n";
}

// Verify
$verify = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ip proxy access print detail' 2>&1";
exec($verify, $vout);
echo "\nVerify:\n";
foreach ($vout as $l) echo "  $l\n";
