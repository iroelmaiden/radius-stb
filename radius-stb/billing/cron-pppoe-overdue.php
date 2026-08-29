<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/wa_helper.php';
$conn = db();

function getCronSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM billing_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Overdue Check Started\n";

$today = date('Y-m-d');
$currentMonth = date('Y-m');
$graceDays = intval(getCronSetting($conn, 'overdue_grace_days', '3'));
$notifyIsolir = getCronSetting($conn, 'notify_member_status', '0');

// Get overdue bills (past grace period)
$overdue = $conn->query("
    SELECT b.*, p.username, p.nama_lengkap, p.phone, p.isolir_status, p.ip_address, v.name AS package_name
    FROM pppoe_billing b
    LEFT JOIN pppoe_users p ON b.user_id = p.id
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    WHERE b.status = 'unpaid' AND b.due_date < DATE_SUB('$today', INTERVAL $graceDays DAY)
    ORDER BY b.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($overdue as $bill) {
    if (empty($bill['username'])) continue;

    // Mark as overdue
    $conn->query("UPDATE pppoe_billing SET status = 'overdue' WHERE id = {$bill['id']}");

    // Isolir user if not already isolated
    if ($bill['isolir_status'] === 'active') {
        $escU = $conn->real_escape_string($bill['username']);
        $conn->query("UPDATE pppoe_users SET isolir_status='isolir', isolir_date=NOW() WHERE id = {$bill['user_id']}");
        $conn->query("DELETE FROM radusergroup WHERE username='$escU'");
        $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe-isolir', 1)");

        // Disconnect user from MikroTik (they will reconnect with isolir group)
        require_once __DIR__ . '/includes/radius_helper.php';
        mikrotikDisconnectPppoe($bill['username']);

        // Add to isolir firewall address list if IP is known
        if (!empty($bill['ip_address'])) {
            mikrotikAddIsolirAddress($bill['ip_address'], $bill['username']);
        }

        if ($notifyIsolir === '1' && $bill['phone']) {
            waNotifyIsolir($bill);
        }

        echo "[ISOLIR] {$bill['username']} ({$bill['nama_lengkap']}) - overdue since {$bill['due_date']}\n";
        $count++;
    }
}

if ($count === 0) {
    echo "[OK] Tidak ada user overdue baru\n";
} else {
    echo "[DONE] $count user diisolir\n";
}

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Overdue Check Finished\n";
