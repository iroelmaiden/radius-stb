<?php
// Try different ports
$ports = [8728, 8729, 80, 443, 22, 23];
foreach ($ports as $port) {
    $s = @stream_socket_client("tcp://172.16.2.1:$port", $e, $m, 2);
    if ($s) {
        echo "Port $port: OPEN\n";
        stream_set_timeout($s, 3);
        $data = fread($s, 100);
        echo "  Response: [" . bin2hex($data) . "] (" . strlen($data) . " bytes)\n";
        fclose($s);
    } else {
        echo "Port $port: closed ($m)\n";
    }
}
