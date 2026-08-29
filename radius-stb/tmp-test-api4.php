<?php
$sock = stream_socket_client('tcp://172.16.2.1:8728', $errno, $errstr, 5);
if (!$sock) { echo "FAIL\n"; exit; }

stream_set_timeout($sock, 5);

// Try sending prologue first
echo "Sending prologue...\n";
fwrite($sock, chr(3) . '/d');  // Length 3, prologue
$data = fread($sock, 100);
echo "Response: [" . bin2hex($data) . "] (" . strlen($data) . " bytes)\n";

// Try raw /d
fwrite($sock, '/d');
$data = fread($sock, 100);
echo "Response2: [" . bin2hex($data) . "] (" . strlen($data) . " bytes)\n";

// Try sending login directly
fwrite($sock, '/login');
fwrite($sock, chr(0));
$data = fread($sock, 100);
echo "Response3: [" . bin2hex($data) . "] (" . strlen($data) . " bytes)\n";

fclose($sock);
