<?php
$cmd = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 '/ip firewall filter print without-paging'";
exec($cmd . " 2>&1", $out, $ret);
foreach ($out as $line) {
    if (stripos($line, 'ISOLIR') !== false || stripos($line, 'bytes') !== false || stripos($line, 'packets') !== false) {
        echo $line . "\n";
    }
}
