<?php
$s = @stream_socket_client('tcp://172.16.2.1:8728', $e, $m, 3);
if ($s) { echo "Connected!\n"; fclose($s); } else { echo "Failed: $m\n"; }
