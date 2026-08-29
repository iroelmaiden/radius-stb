<?php
$cmd = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ip firewall address-list print'";
exec($cmd . " 2>&1", $out, $ret);
echo "Address list:\n";
foreach ($out as $line) echo "  $line\n";

$cmd2 = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ppp active print where name=iroel'";
exec($cmd2 . " 2>&1", $out2, $ret2);
echo "\nActive session:\n";
foreach ($out2 as $line) echo "  $line\n";
