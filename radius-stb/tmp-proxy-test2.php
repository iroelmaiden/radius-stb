<?php
// Try different parameter names and formats
$cmds = [
    "/ip proxy access add src-address=172.30.0.0/16 action=deny",
    "/ip proxy access add src=172.30.0.0/16 action=deny",
    "/ip proxy access add action=deny",
];

foreach ($cmds as $i => $cmd) {
    $escaped = escapeshellarg($cmd);
    $full = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 $escaped 2>&1";
    exec($full, $out, $ret);
    echo "CMD $i: RET=$ret OUT=" . implode(" ", $out) . "\n";
}
