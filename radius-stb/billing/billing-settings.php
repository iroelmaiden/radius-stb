<?php
require_once 'config.php';
$conn = db();

// Helper: get setting value
function getBillingSetting($conn, $key, $default = '') {
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

// Helper: save setting value
function saveBillingSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO billing_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('sss', $key, $value, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate($_POST['_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token');
        header('Location: billing-settings.php');
        exit;
    }

    // Save all settings
    saveBillingSetting($conn, 'billing_day', intval($_POST['billing_day'] ?? 20));
    saveBillingSetting($conn, 'invoice_generate_days_before', intval($_POST['invoice_generate_days_before'] ?? 7));
    saveBillingSetting($conn, 'overdue_grace_days', intval($_POST['overdue_grace_days'] ?? 3));
    saveBillingSetting($conn, 'reminder_days_before', intval($_POST['reminder_days_before'] ?? 2));
    saveBillingSetting($conn, 'isolir_time', $_POST['isolir_time'] ?? '00:15:00');
    saveBillingSetting($conn, 'notify_invoice_issued', isset($_POST['notify_invoice_issued']) ? '1' : '0');
    saveBillingSetting($conn, 'notify_payment_status', isset($_POST['notify_payment_status']) ? '1' : '0');
    saveBillingSetting($conn, 'notify_member_status', isset($_POST['notify_member_status']) ? '1' : '0');

    setFlash('success', 'Billing settings berhasil disimpan');
    header('Location: billing-settings.php');
    exit;
}

// Load current settings
$settings = [
    'billing_day' => getBillingSetting($conn, 'billing_day', '20'),
    'invoice_generate_days_before' => getBillingSetting($conn, 'invoice_generate_days_before', '7'),
    'overdue_grace_days' => getBillingSetting($conn, 'overdue_grace_days', '3'),
    'reminder_days_before' => getBillingSetting($conn, 'reminder_days_before', '2'),
    'isolir_time' => getBillingSetting($conn, 'isolir_time', '00:15:00'),
    'notify_invoice_issued' => getBillingSetting($conn, 'notify_invoice_issued', '0'),
    'notify_payment_status' => getBillingSetting($conn, 'notify_payment_status', '1'),
    'notify_member_status' => getBillingSetting($conn, 'notify_member_status', '0'),
];

$flash = getFlash();
require_once 'includes/header.php';
?>

<div class="content-area">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col">
                <h4 class="mb-0">
                    <i class="fas fa-cog"></i> Billing Setting
                </h4>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i> Billing Cycle
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Due date for postpaid payment method - Billing Cycle</label>
                            <input type="number" name="billing_day" class="form-control" value="<?= htmlspecialchars($settings['billing_day']) ?>" min="1" max="28">
                            <small class="text-muted">Tanggal jatuh tempo tagihan (1-28)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum days to generate invoice before due date</label>
                            <input type="number" name="invoice_generate_days_before" class="form-control" value="<?= htmlspecialchars($settings['invoice_generate_days_before']) ?>" min="1" max="30">
                            <small class="text-muted">Berapa hari sebelum due date invoice dibuat (isi 7 untuk tercepat)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle"></i> Overdue & Isolir
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Overdue grace period (days)</label>
                            <input type="number" name="overdue_grace_days" class="form-control" value="<?= htmlspecialchars($settings['overdue_grace_days']) ?>" min="0">
                            <small class="text-muted">Batas waktu pembayaran setelah jatuh tempo sebelum user di-suspend (0 = tidak pernah)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reminder notification (days before)</label>
                            <input type="number" name="reminder_days_before" class="form-control" value="<?= htmlspecialchars($settings['reminder_days_before']) ?>" min="0">
                            <small class="text-muted">Kirim notifikasi berapa hari sebelum jatuh tempo (0 = tidak pernah)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Isolir time</label>
                            <select name="isolir_time" class="form-select">
                                <?php
                                $times = ['00:05:00', '00:10:00', '00:15:00', '00:30:00', '01:00:00'];
                                foreach ($times as $t):
                                ?>
                                <option value="<?= $t ?>" <?= $settings['isolir_time'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Waktu user diisolir jika tagihan belum dibayar</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bell"></i> Notification Settings
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="notify_invoice_issued" class="form-check-input" id="notifyInvoice" value="1" <?= $settings['notify_invoice_issued'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyInvoice">Send notification when invoice issued</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="notify_payment_status" class="form-check-input" id="notifyPayment" value="1" <?= $settings['notify_payment_status'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyPayment">Send payment status notification</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="notify_member_status" class="form-check-input" id="notifyMember" value="1" <?= $settings['notify_member_status'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyMember">Send member status notification</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Settings
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
