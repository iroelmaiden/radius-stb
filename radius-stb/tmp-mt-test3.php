<?php
// Try escaping brackets for MikroTik SSH
$cmds = [
    // Escape brackets with backslash
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server disconnect \\[find where name=iroel\\]'",
    // Use double quotes and escape inner
    'sshpass -p Kayangan119# ssh -o StrictHostKeyChecking=no admin@172.16.2.1 "/interface pppoe-server disconnect [find where name=iroel]"',
    // Simple test first
    "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server print'",
];

foreach ($cmds as $i => $cmd) {
    echo "CMD $i: $cmd\n";
    exec($cmd . " 2>&1", $out, $ret);
    echo "RET: $ret OUT: " . implode(" ", array_slice($out, 0, 3)) . "\n\n";
}
