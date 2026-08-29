<?php
// Try redirect with different formats
$cmds = [
    "/ip proxy access add src-address=172.30.0.0/16 action=redirect redirect-to=\"http://172.16.2.2:8081/isolir.php\"",
    "/ip proxy access add src-address=172.30.0.0/16 action=redirect \"redirect-to=http://172.16.2.2:8081/isolir.php\"",
    "/ip proxy access add src-address=172.30.0.0/16 action=redirect redirect-to=http://172.16.2.2:8081/isolir.php",
];

foreach ($cmds as $i => $cmd) {
    $escaped = escapeshellarg($cmd);
    $full = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 $escaped 2>&1";
    exec($full, $out, $ret);
    echo "CMD $i: RET=$ret OUT=" . implode(" ", $out) . "\n";
}
