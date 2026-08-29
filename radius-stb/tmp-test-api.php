<?php
$_ENV['MT_IP'] = '172.16.2.1';
$_ENV['MT_USER'] = 'admin';
$_ENV['MT_PASS'] = 'Kayangan119#';

$sock = @stream_socket_client('tcp://172.16.2.1:8728', $errno, $errstr, 5);
if ($sock) {
    stream_set_timeout($sock, 5);
    $hello = fread($sock, 2);
    echo "Hello: " . bin2hex($hello) . "\n";
    fread($sock, 1);
    fwrite($sock, '/login');
    fwrite($sock, '=name=admin');
    fwrite($sock, '=password=Kayangan119#');
    fwrite($sock, chr(0));
    $resp = fread($sock, 4096);
    echo "Login response: " . substr($resp, 0, 100) . "\n";

    // Test: get active PPPoE users
    fwrite($sock, '/interface pppoe-server print');
    fwrite($sock, chr(0));
    $resp2 = fread($sock, 4096);
    echo "PPPoE response: " . substr($resp2, 0, 200) . "\n";

    fclose($sock);
    echo "Connection OK!\n";
} else {
    echo "Failed: $errno - $errstr\n";
}
