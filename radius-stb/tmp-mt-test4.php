<?php
// Try different disconnect methods
$cmds = [
    // Disconnect by number (index)
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server disable 1'",
    // Try just the command without find
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server set 1'",
    // Test: just print with number
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server print detail'",
];

foreach ($cmds as $i => $cmd) {
    echo "--- CMD $i ---\n";
    echo "RUN: $cmd\n";
    exec($cmd . " 2>&1", $out, $ret);
    echo "RET: $ret\n";
    foreach (array_slice($out, 0, 10) as $line) echo "  $line\n";
    echo "\n";
}
