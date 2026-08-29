<?php
// Try removing iroel (index 1) - also try with name
$cmds = [
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active remove 1'",
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active remove numbers=1'",
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active set 1 disabled=yes'",
];

foreach ($cmds as $i => $cmd) {
    echo "--- CMD $i: $cmd ---\n";
    exec($cmd . " 2>&1", $out, $ret);
    echo "RET: $ret OUT: " . implode(" ", $out) . "\n\n";
}
