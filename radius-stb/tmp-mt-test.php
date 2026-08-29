<?php
$cmd = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface pppoe-server disconnect [find name=iroel]'";
$output = [];
$retval = 0;
exec($cmd, $output, $retval);
echo "RET: $retval\n";
echo "OUT: " . implode("\n", $output) . "\n";
