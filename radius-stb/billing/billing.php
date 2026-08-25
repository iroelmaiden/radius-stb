<?php
require_once 'config.php';
$conn = db();

$user_id = intval($_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $period = trim($_POST['period'] ?? date('Y-m'));
        $billing_date = intval($_POST['billing_date'] ?? 1);

        $users = $conn->query("SELECT p.id, p.username, p.nama_lengkap, p.phone, v.price, v.name AS package_name
            FROM pppoe_users p
            LEFT JOIN pppoe_profiles v ON p.package_id = v.id
            WHERE p.billing_type = 'postpaid' AND p.status = 'active' AND v.price > 0
        ")->fetch_all(MYSQLI_ASSOC);

        $count = 0;
        $ym = explode('-', $period);
        $dueDate = sprintf('%s-%s-%02d', $ym[0], $ym[1], $billing_date);

        foreach ($users as $u) {
            $exists = $conn->query("SELECT id FROM pppoe_billing WHERE user_id = {$u['id']} AND billing_period = '$period'");
            if ($exists->num_rows > 0) continue;

            $amount = floatval($u['price']);
            $conn->query("INSERT INTO pppoe_billing (user_id, billing_period, amount, due_date, status) VALUES ({$u['id']}, '$period', $amount, '$dueDate', 'unpaid')");

            if (!empty($u['phone'])) {
                require_once 'includes/wa_helper.php';
                waNotifyBilling($u, $amount, $period, date('d/m/Y', strtotime($dueDate)));
            }

            $count++;
        }

        setFlash("Tagihan periode $period berhasil digenerate untuk $count user.", 'success');
        header('Location: billing.php');
        exit;
    }

    if ($action === 'pay') {
        $billing_id = intval($_POST['billing_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $method = $_POST['payment_method'] ?? 'cash';
        $notes = trim($_POST['notes'] ?? '');

        $brow = $conn->query("SELECT b.*, p.username, p.nama_lengkap, p.phone FROM pppoe_billing b LEFT JOIN pppoe_users p ON b.user_id = p.id WHERE b.id = $billing_id");
        if ($brow->num_rows === 0) {
            setFlash('Tagihan tidak ditemukan.', 'danger');
            header('Location: billing.php');
            exit;
        }
        $bdata = $brow->fetch_assoc();

        $remaining = $bdata['amount'] - $bdata['paid_amount'];
        if ($amount <= 0) $amount = $remaining;

        $newPaid = $bdata['paid_amount'] + $amount;
        $newStatus = $newPaid >= $bdata['amount'] ? 'paid' : 'unpaid';
        $paidDate = $newStatus === 'paid' ? date('Y-m-d H:i:s') : null;

        $conn->query("UPDATE pppoe_billing SET paid_amount = $newPaid, status = '$newStatus', paid_date = '$paidDate', notes = '$notes' WHERE id = $billing_id");

        $conn->query("INSERT INTO pppoe_payments (user_id, billing_id, amount, payment_method, notes, received_by) VALUES ({$bdata['user_id']}, $billing_id, $amount, '$method', '$notes', 'admin')");

        if ($newStatus === 'paid') {
            $conn->query("UPDATE pppoe_users SET last_paid_date = CURDATE(), isolir_status = 'active', isolir_date = NULL WHERE id = {$bdata['user_id']}");
            $uname = $conn->real_escape_string($bdata['username']);
            $conn->query("DELETE FROM radusergroup WHERE username='$uname'");
            $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$uname', 'pppoe', 1)");

            if (!empty($bdata['phone'])) {
                require_once 'includes/wa_helper.php';
                waNotifyPayment($bdata, $bdata['amount'], $bdata['billing_period']);
            }
        }

        setFlash("Pembayaran berhasil. " . ($newStatus === 'paid' ? 'Tagihan LUNAS.' : 'Sisa bayar: ' . formatRupiah($remaining - $amount)), 'success');
        header('Location: billing.php' . ($user_id ? "?user_id=$user_id" : ''));
        exit;
    }

    if ($action === 'send_wa_tagih') {
        $uid = intval($_POST['user_id'] ?? 0);
        $urow = $conn->query("SELECT * FROM pppoe_users WHERE id = $uid");
        if ($urow->num_rows > 0) {
            $udata = $urow->fetch_assoc();
            if (empty($udata['phone'])) {
                setFlash('Nomor HP pelanggan kosong.', 'danger');
                header('Location: billing.php?user_id=' . $uid);
                exit;
            }

            $unpaid = $conn->query("SELECT * FROM pppoe_billing WHERE user_id = $uid AND status IN ('unpaid','overdue') ORDER BY due_date ASC LIMIT 1")->fetch_assoc();
            if (!$unpaid) {
                setFlash('Tidak ada tagihan unpaid.', 'warning');
                header('Location: billing.php?user_id=' . $uid);
                exit;
            }

            require_once 'includes/wa_helper.php';
            $result = waNotifyBilling($udata, $unpaid['amount'], $unpaid['billing_period'], date('d/m/Y', strtotime($unpaid['due_date'])));
            if ($result['ok']) {
                setFlash('Tagihan berhasil dikirim ke ' . $udata['phone'], 'success');
            } else {
                setFlash('Gagal kirim: ' . ($result['msg'] ?? 'Error'), 'danger');
            }
        }
        header('Location: billing.php?user_id=' . $uid);
        exit;
    }

    if ($action === 'delete_bill') {
        $billing_id = intval($_POST['billing_id'] ?? 0);
        $conn->query("DELETE FROM pppoe_payments WHERE billing_id = $billing_id");
        $conn->query("DELETE FROM pppoe_billing WHERE id = $billing_id");
        setFlash('Tagihan berhasil dihapus.', 'success');
        header('Location: billing.php' . ($user_id ? "?user_id=$user_id" : ''));
        exit;
    }

    if ($action === 'delete_payment') {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $prow = $conn->query("SELECT * FROM pppoe_payments WHERE id = $payment_id");
        if ($prow->num_rows > 0) {
            $pdata = $prow->fetch_assoc();
            if ($pdata['billing_id']) {
                $brow = $conn->query("SELECT * FROM pppoe_billing WHERE id = {$pdata['billing_id']}");
                if ($brow->num_rows > 0) {
                    $bdata = $brow->fetch_assoc();
                    $newPaid = max(0, $bdata['paid_amount'] - $pdata['amount']);
                    $newStatus = $newPaid >= $bdata['amount'] ? 'paid' : 'unpaid';
                    $conn->query("UPDATE pppoe_billing SET paid_amount = $newPaid, status = '$newStatus', paid_date = NULL WHERE id = {$pdata['billing_id']}");
                }
            }
            $conn->query("DELETE FROM pppoe_payments WHERE id = $payment_id");
        }
        setFlash('Pembayaran berhasil dihapus.', 'success');
        header('Location: billing.php' . ($user_id ? "?user_id=$user_id" : ''));
        exit;
    }
}

$filter_period = $_GET['period'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where = [];
if ($user_id > 0) $where[] = "b.user_id = $user_id";
if ($filter_period) $where[] = "b.billing_period = '" . $conn->real_escape_string($filter_period) . "'";
if ($filter_status) $where[] = "b.status = '" . $conn->real_escape_string($filter_status) . "'";
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$billings = $conn->query("
    SELECT b.*, p.username, p.nama_lengkap, p.phone, v.name AS package_name
    FROM pppoe_billing b
    LEFT JOIN pppoe_users p ON b.user_id = p.id
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    $whereSQL
    ORDER BY b.due_date DESC, p.username ASC
")->fetch_all(MYSQLI_ASSOC);

$selectedUser = null;
if ($user_id > 0) {
    $urow = $conn->query("SELECT * FROM pppoe_users WHERE id = $user_id");
    if ($urow->num_rows > 0) $selectedUser = $urow->fetch_assoc();
}

$allPayments = [];
if ($user_id > 0) {
    $allPayments = $conn->query("SELECT * FROM pppoe_payments WHERE user_id = $user_id ORDER BY payment_date DESC")->fetch_all(MYSQLI_ASSOC);
}

$totalUnpaid = $totalPaid = $totalOverdue = $totalAmount = 0;
foreach ($billings as $b) {
    $totalAmount += $b['amount'];
    if ($b['status'] === 'paid') $totalPaid += $b['amount'];
    elseif ($b['status'] === 'overdue') $totalOverdue += $b['amount'];
    else $totalUnpaid += $b['amount'];
}

require_once 'includes/header.php';
?>

<h4 class="mb-3">
    <i class="fas fa-file-invoice-dollar"></i> Tagihan
    <?php if ($selectedUser): ?>
        - <?= htmlspecialchars($selectedUser['nama_lengkap'] ?: $selectedUser['username']) ?>
        <a href="billing.php" class="btn btn-secondary btn-sm ms-2"><i class="fas fa-arrow-left"></i> Semua</a>
    <?php endif; ?>
</h4>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-2">
            <small class="text-muted">Total Tagihan</small>
            <h5 class="mb-0"><?= formatRupiah($totalAmount) ?></h5>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-2">
            <small class="text-muted">Lunas</small>
            <h5 class="mb-0 text-success"><?= formatRupiah($totalPaid) ?></h5>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-2">
            <small class="text-muted">Belum Bayar</small>
            <h5 class="mb-0 text-danger"><?= formatRupiah($totalUnpaid) ?></h5>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card yellow"><div class="card-body py-2">
            <small class="text-muted">Overdue</small>
            <h5 class="mb-0 text-warning"><?= formatRupiah($totalOverdue) ?></h5>
        </div></div>
    </div>
</div>

<?php if (!$user_id): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fas fa-cogs"></i> Generate Tagihan Bulanan</div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="generate">
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Jatuh Tempo</label>
                <input type="number" name="billing_date" class="form-control" min="1" max="28" value="1">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary"><i class="fas fa-sync"></i> Generate Tagihan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedUser && $selectedUser['billing_type'] === 'postpaid'): ?>
<div class="card mb-3">
    <div class="card-header"><i class="fab fa-whatsapp text-success"></i> Kirim Tagih via WhatsApp</div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="send_wa_tagih">
            <input type="hidden" name="user_id" value="<?= $selectedUser['id'] ?>">
            <div class="col-md-5">
                <label class="form-label">No. HP</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($selectedUser['phone'] ?? '-') ?>" readonly>
            </div>
            <div class="col-md-5">
                <label class="form-label">Kirim ke:</label>
                <div class="form-control-plaintext fw-bold text-success">
                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($selectedUser['nama_lengkap'] ?: $selectedUser['username']) ?>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100"><i class="fab fa-whatsapp"></i> Kirim</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <?php if ($user_id): ?><input type="hidden" name="user_id" value="<?= $user_id ?>"><?php endif; ?>
        <input type="month" name="period" class="form-control form-control-sm" style="width:160px" value="<?= htmlspecialchars($filter_period) ?>">
        <select name="status" class="form-select form-select-sm" style="width:130px">
            <option value="">Semua Status</option>
            <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        </select>
        <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        <?php if ($filter_period || $filter_status): ?>
            <a href="billing.php<?= $user_id ? "?user_id=$user_id" : '' ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Periode</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Tagihan</th>
                    <th>Dibayar</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                    <th width="160">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($billings)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada tagihan</td></tr>
                <?php else: ?>
                <?php foreach ($billings as $b): ?>
                <tr>
                    <td><?= $b['id'] ?></td>
                    <td><small><?= htmlspecialchars($b['billing_period']) ?></small></td>
                    <td><code class="text-primary fw-bold"><?= htmlspecialchars($b['username']) ?></code></td>
                    <td><small><?= htmlspecialchars($b['nama_lengkap'] ?? '-') ?></small></td>
                    <td><strong><?= formatRupiah($b['amount']) ?></strong></td>
                    <td><small class="text-success"><?= formatRupiah($b['paid_amount']) ?></small></td>
                    <td>
                        <small class="<?= strtotime($b['due_date']) < time() && $b['status'] !== 'paid' ? 'text-danger fw-bold' : '' ?>">
                            <?= date('d/m/Y', strtotime($b['due_date'])) ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($b['status'] === 'paid'): ?>
                            <span class="badge bg-success">Lunas</span>
                        <?php elseif ($b['status'] === 'overdue'): ?>
                            <span class="badge bg-danger">Overdue</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($b['status'] !== 'paid'): ?>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payModal"
                            onclick="openPay(<?= $b['id'] ?>, <?= $b['amount'] ?>, <?= $b['paid_amount'] ?>, '<?= htmlspecialchars(addslashes($b['username'])) ?>')">
                            <i class="fas fa-money-bill"></i> Bayar
                        </button>
                        <?php endif; ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus tagihan ini?')">
                            <input type="hidden" name="action" value="delete_bill">
                            <input type="hidden" name="billing_id" value="<?= $b['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if ($user_id && $allPayments): ?>
<h5 class="mb-3"><i class="fas fa-history"></i> Riwayat Pembayaran</h5>
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Catatan</th>
                    <th width="60">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allPayments as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><small><?= date('d/m/Y H:i', strtotime($p['payment_date'])) ?></small></td>
                    <td><strong class="text-success"><?= formatRupiah($p['amount']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= strtoupper($p['payment_method']) ?></span></td>
                    <td><small><?= htmlspecialchars($p['notes'] ?? '-') ?></small></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pembayaran ini?')">
                            <input type="hidden" name="action" value="delete_payment">
                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="billing_id" id="payBillId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill"></i> Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>User:</strong> <span id="payUser"></span></div>
                    <div class="mb-2"><strong>Tagihan:</strong> <span id="payTotal"></span></div>
                    <div class="mb-2"><strong>Sudah Dibayar:</strong> <span id="payPaid" class="text-success"></span></div>
                    <div class="mb-3"><strong>Sisa:</strong> <span id="payRemain" class="text-danger fw-bold"></span></div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Bayar</label>
                        <input type="number" name="amount" id="payAmount" class="form-control" step="0.01" min="0" placeholder="0 = lunas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Metode</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Opsional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPay(id, total, paid, user) {
    document.getElementById('payBillId').value = id;
    document.getElementById('payUser').textContent = user;
    document.getElementById('payTotal').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
    document.getElementById('payPaid').textContent = 'Rp ' + Number(paid).toLocaleString('id-ID');
    var remain = total - paid;
    document.getElementById('payRemain').textContent = 'Rp ' + Number(remain).toLocaleString('id-ID');
    document.getElementById('payAmount').value = '';
    document.getElementById('payAmount').placeholder = '0 = lunas (' + Number(remain).toLocaleString('id-ID') + ')';
}
</script>

<?php require_once 'includes/footer.php'; ?>
