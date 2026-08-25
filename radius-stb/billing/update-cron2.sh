#!/bin/bash
cat > /var/www/html/billing/cron-expire.php << 'PHPEOF'
<?php
require_once 'config.php';
$conn = db();

$stale_count = 0;
$count = 0;
$validity_count = 0;
$delete_count = 0;

// 1. Bersihkan stale session (>60s tanpa update)
$stale = $conn->query("
    UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Stale-Session'
    WHERE acctstoptime IS NULL
    AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(acctupdatetime) > 60
");
if ($stale) $stale_count = $stale->affected_rows;

// 2. Expire vouchers yang sudah lewat durasi (used + activated)
$result = $conn->query("
    SELECT v.id, v.username, v.activated_at, v.duration_hours
    FROM vouchers v
    WHERE v.status = 'used' 
    AND v.activated_at IS NOT NULL
    AND TIMESTAMPDIFF(SECOND, v.activated_at, NOW()) >= (v.duration_hours * 3600)
");

if ($result) {
    while ($v = $result->fetch_assoc()) {
        $username = $conn->real_escape_string($v['username']);
        $conn->query("UPDATE vouchers SET status='expired' WHERE id={$v['id']}");
        $conn->query("DELETE FROM radcheck WHERE username='$username'");
        $conn->query("DELETE FROM radreply WHERE username='$username'");
        $conn->query("DELETE FROM radusergroup WHERE username='$username'");
        $count++;
    }
}

// 3. Expire vouchers available yang sudah lewat masa aktif (valid_until)
$validity_expire = $conn->query("
    UPDATE vouchers SET status='expired'
    WHERE status='available'
    AND valid_until IS NOT NULL
    AND valid_until < NOW()
");
if ($validity_expire) $validity_count = $validity_expire->affected_rows;

if ($validity_count > 0) {
    $expired_v = $conn->query("SELECT username FROM vouchers WHERE status='expired' AND valid_until IS NOT NULL AND valid_until >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    if ($expired_v) {
        while ($ev = $expired_v->fetch_assoc()) {
            $uname = $conn->real_escape_string($ev['username']);
            $conn->query("DELETE FROM radcheck WHERE username='$uname'");
            $conn->query("DELETE FROM radreply WHERE username='$uname'");
            $conn->query("DELETE FROM radusergroup WHERE username='$uname'");
        }
    }
}

// 4. Hapus voucher expired yang sudah sangat lama (>30 hari)
$old = $conn->query("
    DELETE FROM vouchers 
    WHERE status='expired' 
    AND activated_at IS NULL
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
if ($old) $delete_count = $old->affected_rows;

echo date('Y-m-d H:i:s') . " - Stale: $stale_count, Duration expired: $count, Validity expired: $validity_count, Old deleted: $delete_count\n";
PHPEOF

echo "=== Done ==="
php /var/www/html/billing/cron-expire.php
