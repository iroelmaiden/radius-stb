<?php
$cmds = [
    // Redirect HTTP isolir users to web proxy
    "/ip firewall nat add chain=dstnat src-address=172.30.0.0/16 dst-port=80 protocol=tcp action=dst-nat to-addresses=172.16.2.1 to-ports=8080 place-before=0 comment=ISOLIR-PROXY",
    
    // Allow isolir pool ke DNS
    "/ip firewall filter add chain=forward src-address=172.30.0.0/16 dst-port=53 protocol=udp action=accept place-before=0 comment=ISOLIR-POOL-DNS",
    "/ip firewall filter add chain=forward src-address=172.30.0.0/16 dst-port=53 protocol=tcp action=accept place-before=0 comment=ISOLIR-POOL-DNS-TCP",
    
    // Allow isolir pool ke STB (billing + isolir page)
    "/ip firewall filter add chain=forward src-address=172.30.0.0/16 dst-address=172.16.2.2 action=accept place-before=0 comment=ISOLIR-POOL-STB",
    
    // Allow isolir pool ke RADIUS
    "/ip firewall filter add chain=forward src-address=172.30.0.0/16 dst-address=172.16.2.2 dst-port=1812,1813 protocol=udp action=accept place-before=0 comment=ISOLIR-POOL-RADIUS",
    
    // Block all other internet dari isolir pool
    "/ip firewall filter add chain=forward src-address=172.30.0.0/16 action=drop place-before=0 comment=ISOLIR-POOL-BLOCK",
];

foreach ($cmds as $i => $cmd) {
    $escaped = escapeshellarg($cmd);
    $full = "sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 $escaped 2>&1";
    exec($full, $out, $ret);
    echo "CMD $i: RET=$ret OUT=" . implode(" ", $out) . "\n";
}
