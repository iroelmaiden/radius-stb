<?php
// Test full flow: ping from isolir user to STB
$cmd = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ping 172.16.2.2 count=1'";
exec($cmd . " 2>&1", $out, $ret);
echo "Ping STB from MikroTik:\n";
foreach ($out as $l) echo "  $l\n";

// Check if there's bridge firewall
$cmd2 = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/interface bridge port print'";
exec($cmd2 . " 2>&1", $out2, $ret2);
echo "\nBridge ports:\n";
foreach ($out2 as $l) echo "  $l\n";
