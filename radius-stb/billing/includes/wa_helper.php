<?php
require_once __DIR__ . '/../config.php';

function waSend($phone, $message) {
    $conn = db();
    $settings = $conn->query("SELECT * FROM whatsapp_settings WHERE is_active = 1 LIMIT 1")->fetch_assoc();
    if (!$settings) return ['ok' => false, 'msg' => 'WhatsApp not active'];

    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) === '62') {
        // already intl format
    } elseif (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }

    $provider = $settings['provider'];
    $api_url = $settings['api_url'];
    $api_key = $settings['api_key'];

    if ($provider === 'fonnte') {
        $data = [
            'target' => $phone,
            'message' => $message,
        ];
        $headers = [
            'Authorization: ' . $api_key,
            'Content-Type: application/json',
        ];
    } elseif ($provider === 'wablas') {
        $data = [
            'phone' => $phone,
            'message' => $message,
        ];
        $headers = [
            'Authorization: ' . $api_key,
            'Content-Type: application/json',
        ];
    } else {
        $data = [
            'phone' => $phone,
            'message' => $message,
        ];
        $headers = [
            'Content-Type: application/json',
        ];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'response' => $result];
    }
    return ['ok' => false, 'msg' => "HTTP $httpCode: $result"];
}

function waGetTemplate($name) {
    $conn = db();
    $row = $conn->query("SELECT message FROM wa_templates WHERE template_name='$name' AND is_active=1 LIMIT 1")->fetch_assoc();
    return $row ? $row['message'] : null;
}

function waReplaceVars($template, $vars) {
    foreach ($vars as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

function waNotifyBilling($user, $amount, $period, $dueDate) {
    $name = $user['nama_lengkap'] ?: $user['username'];
    $phone = $user['phone'];
    if (empty($phone)) return false;

    $tpl = waGetTemplate('tagihan');
    if (!$tpl) {
        $tpl = "Yth {nama},\n\nTagihan internet Anda:\nPeriode: {periode}\nJumlah: {amount}\nJatuh Tempo: {due_date}\n\nMohon lakukan pembayaran sebelum jatuh tempo.\nTerima kasih.";
    }

    $msg = waReplaceVars($tpl, [
        'nama' => $name,
        'periode' => $period,
        'amount' => 'Rp ' . number_format($amount, 0, ',', '.'),
        'due_date' => $dueDate,
    ]);

    return waSend($phone, $msg);
}

function waNotifyIsolir($user) {
    $name = $user['nama_lengkap'] ?: $user['username'];
    $phone = $user['phone'];
    if (empty($phone)) return false;

    $tpl = waGetTemplate('isolir');
    if (!$tpl) {
        $tpl = "Yth {nama},\n\nAkun internet Anda telah DIISOLIR karena tagihan belum terbayar.\nSilahkan lakukan pembayaran untuk mengaktifkan kembali layanan.\n\nTerima kasih.";
    }

    $msg = waReplaceVars($tpl, ['nama' => $name]);
    return waSend($phone, $msg);
}

function waNotifyPayment($user, $amount, $period) {
    $name = $user['nama_lengkap'] ?: $user['username'];
    $phone = $user['phone'];
    if (empty($phone)) return false;

    $tpl = waGetTemplate('bayar');
    if (!$tpl) {
        $tpl = "Yth {nama},\n\nPembayaran internet Anda telah diterima:\nPeriode: {periode}\nJumlah: {amount}\n\nLayanan Anda telah aktif kembali.\nTerima kasih.";
    }

    $msg = waReplaceVars($tpl, [
        'nama' => $name,
        'periode' => $period,
        'amount' => 'Rp ' . number_format($amount, 0, ',', '.'),
    ]);

    return waSend($phone, $msg);
}
