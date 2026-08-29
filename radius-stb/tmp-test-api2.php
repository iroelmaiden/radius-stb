<?php
$sock = @stream_socket_client('tcp://172.16.2.1:8728', $errno, $errstr, 5);
if (!$sock) { echo "Failed: $errno - $errstr\n"; exit; }

stream_set_timeout($sock, 5);
$null = chr(0);

// Read hello
$hello = fread($sock, 2);
$ver = fread($sock, 1);
echo "Hello: [" . bin2hex($hello) . "] Ver: [" . bin2hex($ver) . "]\n";

// Send /login
fwrite($sock, '/login' . $null);
$resp = fread($sock, 4096);
echo "Login challenge: [" . $resp . "]\n";

// Extract challenge
if (preg_match('/=ret=([a-f0-9]+)/', $resp, $m)) {
    $challenge = $m[1];
    echo "Challenge: $challenge\n";
    $hash = md5(chr(0) . 'Kayangan119#' . hex2bin($challenge));
    echo "Hash: $hash\n";
    
    $cmd = '/login=name=admin=response=00' . $hash;
    echo "Sending: [$cmd]\n";
    fwrite($sock, $cmd . $null);
    $resp2 = fread($sock, 4096);
    echo "Login result: [" . $resp2 . "]\n";
    
    if (strpos($resp2, '!done') !== false) {
        echo "LOGIN OK!\n";
        
        // Test command
        fwrite($sock, '/interface pppoe-server print' . $null);
        $resp3 = fread($sock, 4096);
        echo "PPPoE: [" . substr($resp3, 0, 300) . "]\n";
    }
} else {
    echo "No challenge found, trying plaintext...\n";
    fwrite($sock, '/login=name=admin=password=Kayangan119#' . $null);
    $resp2 = fread($sock, 4096);
    echo "Plaintext result: [" . $resp2 . "]\n";
}

fclose($sock);
