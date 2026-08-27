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
