<?php
require_once 'config.php';
$conn = db();

$stale_count = 0;
$count = 0;
$validity_count = 0;
$delete_count = 0;

// 1. Bersihkan stale session (>180s tanpa update - MikroTik interim accounting)
$conn->query("
    UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Stale-Session'
    WHERE acctstoptime IS NULL
    AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(acctupdatetime) > 180
");
$stale_count = $conn->affected_rows;

// 2. Expire vouchers yang sudah lewat durasi (used + activated)
$result = $conn->query("
    SELECT v.id, v.username, v.activated_at, v.duration_hours
    FROM vouchers v
    WHERE v.status = 'used' 
    AND v.activated_at IS NOT NULL
    AND TIMESTAMPDIFF(SECOND, v.activated_at, NOW()) >= (v.duration_hours * 3600)
");

if ($result && $result->num_rows > 0) {
    while ($v = $result->fetch_assoc()) {
        $username = $conn->real_escape_string($v['username']);
        $conn->query("UPDATE vouchers SET status='expired' WHERE id={$v['id']}");
        $conn->query("DELETE FROM radcheck WHERE username='$username'");
        $conn->query("DELETE FROM radreply WHERE username='$username'");
        $conn->query("DELETE FROM radusergroup WHERE username='$username'");
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Cleartext-Password', ':=', 'EXPIRED_$username')");
        $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Reply-Message', ':=', 'Masa aktif voucher sudah habis. Silakan beli voucher baru.')");
        $count++;
    }
}

// 3. Expire vouchers available/sold yang sudah lewat masa aktif (valid_until)
$conn->query("
    UPDATE vouchers SET status='expired'
    WHERE status IN ('available', 'sold')
    AND valid_until IS NOT NULL
    AND valid_until < NOW()
");
$validity_count = $conn->affected_rows;

if ($validity_count > 0) {
    $expired_v = $conn->query("SELECT username FROM vouchers WHERE status='expired' AND valid_until IS NOT NULL AND valid_until >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    if ($expired_v && $expired_v->num_rows > 0) {
        while ($ev = $expired_v->fetch_assoc()) {
            $uname = $conn->real_escape_string($ev['username']);
            $conn->query("DELETE FROM radcheck WHERE username='$uname'");
            $conn->query("DELETE FROM radreply WHERE username='$uname'");
            $conn->query("DELETE FROM radusergroup WHERE username='$uname'");
            $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$uname', 'Cleartext-Password', ':=', 'EXPIRED_$uname')");
            $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$uname', 'Reply-Message', ':=', 'Voucher sudah expired. Silakan beli voucher baru.')");
        }
    }
}

// 4. Hapus voucher expired yang sudah sangat lama (>30 hari)
$conn->query("
    DELETE FROM vouchers 
    WHERE status='expired' 
    AND activated_at IS NULL
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$delete_count = $conn->affected_rows;

echo date('Y-m-d H:i:s') . " - Stale: $stale_count, Duration expired: $count, Validity expired: $validity_count, Old deleted: $delete_count\n";
