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

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Auto Billing Started\n";

$currentMonth = date('Y-m');
$billingDay = intval(getCronSetting($conn, 'billing_day', '1'));
$notifyInvoice = getCronSetting($conn, 'notify_invoice_issued', '0');

$users = $conn->query("
    SELECT p.id, p.username, p.nama_lengkap, p.phone, p.billing_date, v.price, v.name AS package_name
    FROM pppoe_users p
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    WHERE p.billing_type = 'postpaid' AND p.status = 'active' AND v.price > 0
")->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($users as $u) {
    $exists = $conn->query("SELECT id FROM pppoe_billing WHERE user_id = {$u['id']} AND billing_period = '$currentMonth'");
    if ($exists->num_rows > 0) continue;

    $bd = $u['billing_date'] ?: $billingDay;
    $dueDate = date('Y-m-' . str_pad($bd, 2, '0', STR_PAD_LEFT));
    $amount = floatval($u['price']);

    $conn->query("INSERT INTO pppoe_billing (user_id, billing_period, amount, due_date, status) VALUES ({$u['id']}, '$currentMonth', $amount, '$dueDate', 'unpaid')");

    if ($notifyInvoice === '1' && $u['phone']) {
        waNotifyBilling($u, $amount, $currentMonth, date('d/m/Y', strtotime($dueDate)));
    }

    echo "[BILL] {$u['username']} - Rp " . number_format($amount) . " - due $dueDate\n";
    $count++;
}

echo "[DONE] $count tagihan digenerate untuk periode $currentMonth\n";
echo "[" . date('Y-m-d H:i:s') . "] PPPoE Auto Billing Finished\n";
