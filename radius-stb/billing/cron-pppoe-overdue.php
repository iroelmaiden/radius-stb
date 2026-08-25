<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/wa_helper.php';
$conn = db();

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Overdue Check Started\n";

$today = date('Y-m-d');
$currentMonth = date('Y-m');

$overdue = $conn->query("
    SELECT b.*, p.username, p.nama_lengkap, p.phone, p.isolir_status, v.name AS package_name
    FROM pppoe_billing b
    LEFT JOIN pppoe_users p ON b.user_id = p.id
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    WHERE b.status = 'unpaid' AND b.due_date < '$today'
    ORDER BY b.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($overdue as $bill) {
    if (empty($bill['username'])) continue;

    $conn->query("UPDATE pppoe_billing SET status = 'overdue' WHERE id = {$bill['id']}");

    if ($bill['isolir_status'] === 'active') {
        $escU = $conn->real_escape_string($bill['username']);
        $conn->query("UPDATE pppoe_users SET isolir_status='isolir', isolir_date=NOW() WHERE id = {$bill['user_id']}");
        $conn->query("DELETE FROM radusergroup WHERE username='$escU'");
        $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe-isolir', 1)");

        if ($bill['phone']) {
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
