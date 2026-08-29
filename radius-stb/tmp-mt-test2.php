<?php
// Test different MikroTik commands
$cmds = [
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server disconnect [find where name=iroel]'",
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 \"/interface pppoe-server disconnect [find where name=iroel]\"",
];

foreach ($cmds as $i => $cmd) {
    echo "CMD $i: $cmd\n";
    exec($cmd, $out, $ret);
    echo "RET: $ret OUT: " . implode(" ", $out) . "\n\n";
}
