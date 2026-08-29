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

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Reminder Check Started\n";

$today = date('Y-m-d');
$reminderDays = intval(getCronSetting($conn, 'reminder_days_before', '2'));
$notifyReminder = getCronSetting($conn, 'notify_invoice_issued', '0');

echo "[CONFIG] reminder_days_before=$reminderDays, notify_invoice_issued=$notifyReminder\n";

if ($notifyReminder !== '1') {
    echo "[SKIP] Reminder not enabled in billing settings\n";
    echo "[" . date('Y-m-d H:i:s') . "] PPPoE Reminder Check Finished\n";
    exit;
}

// Get unpaid bills due within reminder_days_before
$reminderDate = date('Y-m-d', strtotime("+{$reminderDays} days"));

$unpaidBills = $conn->query("
    SELECT b.*, p.username, p.nama_lengkap, p.phone, p.isolir_status, v.name AS package_name
    FROM pppoe_billing b
    LEFT JOIN pppoe_users p ON b.user_id = p.id
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    WHERE b.status = 'unpaid' 
    AND b.due_date <= '$reminderDate'
    AND b.due_date >= '$today'
    ORDER BY b.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($unpaidBills as $bill) {
    if (empty($bill['phone'])) {
        echo "[SKIP] {$bill['username']} - no phone number\n";
        continue;
    }

    $daysUntilDue = (strtotime($bill['due_date']) - strtotime($today)) / 86400;
    
    $result = waNotifyBilling($bill, $bill['amount'], $bill['billing_period'], date('d/m/Y', strtotime($bill['due_date'])));
    
    if ($result['ok']) {
        echo "[SENT] {$bill['username']} ({$bill['nama_lengkap']}) - due in {$daysUntilDue} days - {$bill['billing_period']}\n";
        $count++;
    } else {
        echo "[FAIL] {$bill['username']} - " . ($result['msg'] ?? 'Unknown error') . "\n";
    }
}

if ($count === 0) {
    echo "[OK] Tidak ada reminder yang perlu dikirim\n";
} else {
    echo "[DONE] $count reminder terkirim\n";
}

echo "[" . date('Y-m-d H:i:s') . "] PPPoE Reminder Check Finished\n";
