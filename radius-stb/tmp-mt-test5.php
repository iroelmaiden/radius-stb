<?php
// Use /ppp active to disconnect
$cmds = [
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active remove 1'",
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active print detail'",
];

foreach ($cmds as $i => $cmd) {
    echo "--- CMD $i ---\n";
    exec($cmd . " 2>&1", $out, $ret);
    echo "RET: $ret\n";
    foreach (array_slice($out, 0, 10) as $line) echo "  $line\n";
    echo "\n";
}
