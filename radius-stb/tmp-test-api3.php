<?php
$sock = stream_socket_client('tcp://172.16.2.1:8728', $errno, $errstr, 5);
if (!$sock) { echo "FAIL: $errstr\n"; exit; }

stream_set_timeout($sock, 10);

// Read bytes one at a time to see what we get
echo "Reading hello...\n";
$data = '';
for ($i = 0; $i < 10; $i++) {
    $byte = fread($sock, 1);
    if ($byte === false || $byte === '') break;
    $data .= $byte;
    echo "Byte $i: [" . bin2hex($byte) . "] = [" . $byte . "]\n";
}
echo "Total hello: [" . bin2hex($data) . "]\n";
echo "Length: " . strlen($data) . "\n";

fclose($sock);
