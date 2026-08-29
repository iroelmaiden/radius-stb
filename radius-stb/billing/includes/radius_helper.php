<?php
/**
 * FreeRADIUS Helper Functions
 * Shared functions for managing RADIUS tables (radcheck, radreply, radusergroup)
 */

/**
 * Add or update a user in radcheck (authentication)
 */
function radiusSetPassword($conn, $username, $password) {
    $conn->query("DELETE FROM radcheck WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Cleartext-Password'");
    $stmt = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
    $stmt->bind_param('ss', $username, $password);
    return $stmt->execute();
}

/**
 * Set rate limit in radreply
 */
function radiusSetRateLimit($conn, $username, $rateLimit) {
    $conn->query("DELETE FROM radreply WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Mikrotik-Rate-Limit'");
    if ($rateLimit) {
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
        $stmt->bind_param('ss', $username, $rateLimit);
        return $stmt->execute();
    }
    return true;
}

/**
 * Set Session-Timeout in radreply
 */
function radiusSetSessionTimeout($conn, $username, $seconds) {
    $conn->query("DELETE FROM radreply WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Session-Timeout'");
    if ($seconds > 0) {
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
        $stmt->bind_param('si', $username, $seconds);
        return $stmt->execute();
    }
    return true;
}

/**
 * Set Reply-Message in radreply (for expired messages)
 */
function radiusSetReplyMessage($conn, $username, $message) {
    $conn->query("DELETE FROM radreply WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Reply-Message'");
    if ($message) {
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Reply-Message', ':=', ?)");
        $stmt->bind_param('ss', $username, $message);
        return $stmt->execute();
    }
    return true;
}

/**
 * Assign user to a group
 */
function radiusSetGroup($conn, $username, $group = 'hotspot') {
    $conn->query("DELETE FROM radusergroup WHERE username='" . $conn->real_escape_string($username) . "'");
    $stmt = $conn->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
    $stmt->bind_param('ss', $username, $group);
    return $stmt->execute();
}

/**
 * Full sync: set password + rate limit + group for a hotspot user
 */
function radiusSyncHotspotUser($conn, $username, $password, $rateLimit = null, $status = 'active', $group = 'hotspot') {
    if ($status === 'disabled') {
        return radiusDeleteUser($conn, $username);
    }
    radiusSetPassword($conn, $username, $password);
    radiusSetRateLimit($conn, $username, $rateLimit);
    radiusSetGroup($conn, $username, $group);
    return true;
}

/**
 * Full sync for PPPoE user
 */
function radiusSyncPppoeUser($conn, $username, $password, $rateLimit = null, $status = 'active') {
    if ($status === 'disabled') {
        return radiusDeleteUser($conn, $username);
    }
    radiusSetPassword($conn, $username, $password);
    radiusSetRateLimit($conn, $username, $rateLimit);
    radiusSetGroup($conn, $username, 'pppoe');
    return true;
}

/**
 * Remove a user from all RADIUS tables
 */
function radiusDeleteUser($conn, $username) {
    $esc = $conn->real_escape_string($username);
    $conn->query("DELETE FROM radcheck WHERE username='$esc'");
    $conn->query("DELETE FROM radreply WHERE username='$esc'");
    $conn->query("DELETE FROM radusergroup WHERE username='$esc'");
    return true;
}

/**
 * Generate a random code
 */
function generateCustomCode($length = 8, $type = 'alphanum_upper') {
    switch ($type) {
        case 'numeric':
        case 'num':
            $chars = '0123456789';
            break;
        case 'alpha_upper':
        case 'alpha':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            break;
        case 'alpha_lower':
            $chars = 'abcdefghjkmnpqrstuvwxyz';
            break;
        case 'alpha_mixed':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
            break;
        case 'alphanum_upper':
        case 'uppercase':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            break;
        case 'alphanum_lower':
        case 'lowercase':
            $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
            break;
        case 'alphanum_mixed':
        case 'alphanumeric':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
            break;
        case 'hex':
            $chars = '0123456789ABCDEF';
            break;
        default:
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    }
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * MikroTik RouterOS API Helper
 * Connect to MikroTik via TCP API (port 8728) to run commands
 */

function mikrotik_connect($ip, $user, $pass, $port = 8728) {
    $sock = @stream_socket_client("tcp://{$ip}:{$port}", $errno, $errstr, 5);
    if (!$sock) return null;

    $null = chr(0);
    stream_set_timeout($sock, 5);

    // Read server hello
    $hello = fread($sock, 2);
    if ($hello !== '/d') { fclose($sock); return null; }
    fread($sock, 1); // version byte

    // Send /login (get challenge)
    fwrite($sock, '/login');
    fwrite($sock, $null);
    $response = fread($sock, 4096);

    // Extract challenge from response
    if (preg_match('/=ret=([a-f0-9]+)/', $response, $m)) {
        $challenge = $m[1];
        // CHAP-MD5: md5(0 + password + challenge)
        $hash = md5(chr(0) . $pass . hex2bin($challenge));
        fwrite($sock, '/login');
        fwrite($sock, '=name=' . $user);
        fwrite($sock, '=response=00' . $hash);
        fwrite($sock, $null);
        $response = fread($sock, 4096);
    } else {
        // Try plaintext login (some versions)
        fwrite($sock, '/login');
        fwrite($sock, '=name=' . $user);
        fwrite($sock, '=password=' . $pass);
        fwrite($sock, $null);
        $response = fread($sock, 4096);
    }

    if (strpos($response, '!done') === false) { fclose($sock); return null; }
    return $sock;
}

function mikrotik_command($sock, $command) {
    if (!$sock) return false;
    $null = chr(0);

    fwrite($sock, $command);
    fwrite($sock, $null);

    $response = '';
    while (true) {
        $line = '';
        while (true) {
            $byte = fread($sock, 1);
            if ($byte === $null || $byte === false) break;
            $line .= $byte;
        }
        $response .= $line . "\n";
        if (strpos($line, '!done') !== false) break;
    }

    return $response;
}

function mikrotik_close($sock) {
    if ($sock) fclose($sock);
}

/**
 * Execute MikroTik SSH command from STB
 */
function mikrotikSSH($command) {
    $ip = $_ENV['MT_IP'] ?? '172.16.2.1';
    $user = $_ENV['MT_USER'] ?? 'admin';
    $pass = $_ENV['MT_PASS'] ?? '';

    $escaped = escapeshellarg($command);
    $cmd = "sshpass -p " . escapeshellarg($pass) . " ssh -o StrictHostKeyChecking=no " . escapeshellarg($user) . "@" . escapeshellarg($ip) . " $escaped 2>&1";
    exec($cmd, $output, $retval);
    return ['ok' => $retval === 0, 'output' => implode("\n", $output), 'code' => $retval];
}

/**
 * Disconnect a PPPoE user on MikroTik by username
 * Uses /ppp active remove to disconnect the session
 */
function mikrotikDisconnectPppoe($username) {
    // First, get the active session index for this user
    $list = mikrotikSSH("/ppp active print");
    if (!$list['ok']) return ['ok' => false, 'msg' => 'Gagal akses MikroTik'];

    // Parse output to find the index for this user
    $lines = explode("\n", $list['output']);
    $index = -1;
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+R\s+' . preg_quote($username) . '\s/', $line, $m)) {
            $index = intval($m[1]);
            break;
        }
    }

    if ($index < 0) {
        return ['ok' => false, 'msg' => "User $username tidak sedang online"];
    }

    // Disconnect by index
    $result = mikrotikSSH("/ppp active remove $index");
    if ($result['ok']) {
        return ['ok' => true, 'msg' => "User $username disconnected"];
    }

    return ['ok' => false, 'msg' => "Gagal disconnect $username: " . $result['output']];
}

/**
 * Add IP to firewall address-list on MikroTik
 */
function mikrotikAddIsolirAddress($ip, $username) {
    $result = mikrotikSSH("/ip firewall address-list add list=pppoe-isolir address=$ip comment=$username timeout=0s");
    return ['ok' => $result['ok'], 'msg' => $result['output']];
}

/**
 * Remove IP from firewall address-list on MikroTik
 */
function mikrotikRemoveIsolirAddress($ip) {
    $result = mikrotikSSH("/ip firewall address-list remove [find where list=pppoe-isolir and address=$ip]");
    return ['ok' => $result['ok'], 'msg' => $result['output']];
}

/**
 * Disconnect PPPoE user + remove isolir address + set group to pppoe
 */
function isolirRelease($conn, $userId) {
    // Get user data
    $row = $conn->query("SELECT * FROM pppoe_users WHERE id = $userId")->fetch_assoc();
    if (!$row) return ['ok' => false, 'msg' => 'User not found'];

    $username = $row['username'];
    $ip = $row['ip_address'];

    // Update database
    $conn->query("UPDATE pppoe_users SET isolir_status='active', isolir_date=NULL WHERE id=$userId");

    // Update FreeRADIUS group
    $escU = $conn->real_escape_string($username);
    $conn->query("DELETE FROM radusergroup WHERE username='$escU'");
    $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe', 1)");

    // Remove Framed-IP-Address from radreply (so user gets normal IP from pool-PPPoE)
    $conn->query("DELETE FROM radreply WHERE username='$escU' AND attribute='Framed-IP-Address'");

    // Try to disconnect from MikroTik (user will reconnect with normal group)
    $disconnect = mikrotikDisconnectPppoe($username);

    // Try to remove isolir address from firewall
    if ($ip) {
        mikrotikRemoveIsolirAddress($ip);
    }

    $msg = "User $username released dari isolir (group → pppoe)";
    if (!$disconnect['ok']) {
        $msg .= ". WARNING: " . $disconnect['msg'] . ". User akan normal setelah reconnect manual.";
    }

    return ['ok' => true, 'msg' => $msg];
}

/**
 * Isolate a user: set group to pppoe-isolir + add firewall + disconnect
 */
function isolirApply($conn, $userId) {
    $row = $conn->query("SELECT * FROM pppoe_users WHERE id = $userId")->fetch_assoc();
    if (!$row) return ['ok' => false, 'msg' => 'User not found'];

    $username = $row['username'];
    $ip = $row['ip_address'];

    // Update database
    $conn->query("UPDATE pppoe_users SET isolir_status='isolir', isolir_date=NOW() WHERE id=$userId");

    // Update FreeRADIUS group
    $escU = $conn->real_escape_string($username);
    $conn->query("DELETE FROM radusergroup WHERE username='$escU'");
    $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe-isolir', 1)");

    // Assign IP from isolir pool (172.30.0.x)
    $usedIPs = $conn->query("SELECT value FROM radreply WHERE attribute='Framed-IP-Address' AND value LIKE '172.30.0.%'")->fetch_all(MYSQLI_ASSOC);
    $used = array_map(function($r) { return intval(explode('.', $r['value'])[3]); }, $usedIPs);
    $nextIP = 10;
    while (in_array($nextIP, $used) && $nextIP < 254) { $nextIP++; }
    if ($nextIP < 254) {
        $isolirIP = "172.30.0.$nextIP";
        $conn->query("DELETE FROM radreply WHERE username='$escU' AND attribute='Framed-IP-Address'");
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Framed-IP-Address', ':=', ?)");
        $stmt->bind_param('ss', $username, $isolirIP);
        $stmt->execute();
    }

    // Try to disconnect user (they will reconnect with isolir group + isolir IP)
    $disconnect = mikrotikDisconnectPppoe($username);

    // Try to add to isolir firewall address list
    if ($ip) {
        mikrotikAddIsolirAddress($ip, $username);
    }

    $msg = "User $username diisolir (group → pppoe-isolir)";
    if (!$disconnect['ok']) {
        $msg .= ". WARNING: " . $disconnect['msg'] . ". User akan diisolir saat reconnect.";
    }

    return ['ok' => true, 'msg' => $msg];
}

/**
 * Generate CSRF token
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input with CSRF token
 */
function csrfField() {
    return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
}

/**
 * Validate CSRF token
 */
function csrfValidate($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
