<?php
require_once 'config.php';
$conn = db();

$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Voucher stats
$voucherStats = $conn->query("SELECT status, COUNT(*) as cnt FROM vouchers GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$vCount = ['available'=>0, 'sold'=>0, 'used'=>0, 'expired'=>0];
foreach ($voucherStats as $vs) { $vCount[$vs['status']] = $vs['cnt']; }
$vTotal = array_sum($vCount);

// Revenue from transactions (sell action)
$statsTrans = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(selling_price),0) as revenue 
    FROM transactions 
    WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();

// Revenue from used/expired vouchers (auto count - activated vouchers)
$statsUsed = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(price),0) as revenue
    FROM vouchers
    WHERE status IN ('used','sold','expired')
    AND activated_at IS NOT NULL
    AND DATE(activated_at) BETWEEN '$date_from' AND '$date_to'")->fetch_assoc();

$totalTrans = ($statsTrans['total'] ?? 0) + ($statsUsed['total'] ?? 0);
$totalRevenue = ($statsTrans['revenue'] ?? 0) + ($statsUsed['revenue'] ?? 0);

// Package breakdown (combine transactions + used/expired vouchers)
$pkgStats = $conn->query("
    SELECT p.name, COUNT(v.id) as qty, COALESCE(SUM(v.price),0) as total 
    FROM vouchers v 
    JOIN voucher_packages p ON v.package_id = p.id
    WHERE v.status IN ('used','sold','expired')
    AND v.activated_at IS NOT NULL
    AND DATE(v.activated_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY p.name ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// Recent voucher usage (including expired)
$recentVouchers = $conn->query("
    SELECT v.id, v.code, v.username, v.price, v.selling_price, v.status, v.activated_at as sold_at, p.name as package_name,
           '-' as buyer_name
    FROM vouchers v 
    JOIN voucher_packages p ON v.package_id = p.id
    WHERE v.status IN ('used','sold','expired')
    AND v.activated_at IS NOT NULL
    AND DATE(v.activated_at) BETWEEN '$date_from' AND '$date_to'
    ORDER BY v.activated_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Recent manual transactions
$recentTrans = $conn->query("
    SELECT t.*, v.code, v.username, p.name as package_name 
    FROM transactions t 
    JOIN vouchers v ON t.voucher_id = v.id 
    JOIN voucher_packages p ON v.package_id = p.id
    WHERE DATE(t.created_at) BETWEEN '$date_from' AND '$date_to'
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Merge and sort
$allRecent = array_merge($recentVouchers, $recentTrans);
usort($allRecent, function($a, $b) {
    return strtotime($b['sold_at']) - strtotime($a['sold_at']);
});

// PPPoE stats
$pppoeStats = $conn->query("SELECT status, COUNT(*) as cnt FROM pppoe_users GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$pCount = ['active'=>0, 'disabled'=>0];
foreach ($pppoeStats as $ps) { $pCount[$ps['status']] = $ps['cnt']; }

// PPPoE billing stats
$billStats = $conn->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM pppoe_billing WHERE billing_period = '" . date('Y-m') . "' GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$bCount = ['unpaid'=>0, 'paid'=>0, 'overdue'=>0];
$bTotal = ['unpaid'=>0, 'paid'=>0, 'overdue'=>0];
foreach ($billStats as $bs) { $bCount[$bs['status']] = $bs['cnt']; $bTotal[$bs['status']] = $bs['total']; }

require_once 'includes/header.php';
?>

<h4 class="mb-3"><i class="fas fa-chart-bar"></i> Laporan</h4>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" class="form-control" name="from" value="<?= $date_from ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-control" name="to" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<h5 class="mb-3"><i class="fas fa-ticket-alt"></i> Status Voucher</h5>
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-2">
            <small class="text-muted">Total</small>
            <h4 class="mb-0"><?= number_format($vTotal) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-2">
            <small class="text-muted">Tersedia</small>
            <h4 class="mb-0 text-primary"><?= number_format($vCount['available']) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card orange"><div class="card-body py-2">
            <small class="text-muted">Terjual</small>
            <h4 class="mb-0 text-success"><?= number_format($vCount['used'] + $vCount['sold']) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-2">
            <small class="text-muted">Expired</small>
            <h4 class="mb-0 text-danger"><?= number_format($vCount['expired']) ?></h4>
        </div></div>
    </div>
</div>

<h5 class="mb-3"><i class="fas fa-network-wired"></i> Status PPPoE</h5>
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-2">
            <small class="text-muted">Total</small>
            <h4 class="mb-0"><?= number_format($pCount['active'] + $pCount['disabled']) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-2">
            <small class="text-muted">Active</small>
            <h4 class="mb-0 text-success"><?= number_format($pCount['active']) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-2">
            <small class="text-muted">Disabled</small>
            <h4 class="mb-0 text-danger"><?= number_format($pCount['disabled']) ?></h4>
        </div></div>
    </div>
</div>

<h5 class="mb-3"><i class="fas fa-file-invoice-dollar"></i> Tagihan Bulan Ini (<?= date('m/Y') ?>)</h5>
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-2">
            <small class="text-muted">Lunas</small>
            <h5 class="mb-0 text-success"><?= formatRupiah($bTotal['paid']) ?></h5>
            <small><?= number_format($bCount['paid']) ?> tagihan</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-2">
            <small class="text-muted">Belum Bayar</small>
            <h5 class="mb-0 text-danger"><?= formatRupiah($bTotal['unpaid']) ?></h5>
            <small><?= number_format($bCount['unpaid']) ?> tagihan</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card yellow"><div class="card-body py-2">
            <small class="text-muted">Overdue</small>
            <h5 class="mb-0 text-warning"><?= formatRupiah($bTotal['overdue']) ?></h5>
            <small><?= number_format($bCount['overdue']) ?> tagihan</small>
        </div></div>
    </div>
</div>

<h5 class="mb-3"><i class="fas fa-coins"></i> Pendapatan (<?= date('d/m', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?>)</h5>
<div class="row g-2 mb-4">
    <div class="col-md-4">
        <div class="card stat-card green"><div class="card-body">
            <h6 class="text-muted">Total Transaksi</h6>
            <h3><?= number_format($totalTrans) ?></h3>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card green"><div class="card-body">
            <h6 class="text-muted">Total Pendapatan</h6>
            <h3 class="text-success"><?= formatRupiah($totalRevenue) ?></h3>
        </div></div>
    </div>
</div>

<?php if ($pkgStats): ?>
<h6 class="mb-2">Per Paket:</h6>
<div class="table-responsive mb-4">
    <table class="table table-sm table-bordered" style="max-width:500px">
        <thead class="table-light"><tr><th>Paket</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>
            <?php foreach ($pkgStats as $ps): ?>
            <tr>
                <td><?= htmlspecialchars($ps['name']) ?></td>
                <td><?= $ps['qty'] ?></td>
                <td class="fw-bold"><?= formatRupiah($ps['total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h5 class="mb-3"><i class="fas fa-list"></i> Detail Penggunaan Voucher</h5>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Kode</th><th>Username</th><th>Paket</th><th>Harga</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($allRecent)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data pada periode ini</td></tr>
                <?php else: ?>
                <?php foreach ($allRecent as $t): ?>
                <tr>
                    <td><small><?= date('d/m/Y H:i', strtotime($t['sold_at'])) ?></small></td>
                    <td><strong><?= htmlspecialchars($t['code']) ?></strong></td>
                    <td><code><?= htmlspecialchars($t['username']) ?></code></td>
                    <td><?= htmlspecialchars($t['package_name']) ?></td>
                    <td><?= formatRupiah($t['selling_price'] ?? $t['price'] ?? 0) ?></td>
                    <td>
                        <?php if (($t['status'] ?? '') === 'sold'): ?>
                            <span class="badge bg-info">Terjual</span>
                        <?php elseif (($t['status'] ?? '') === 'expired'): ?>
                            <span class="badge bg-danger">Expired</span>
                        <?php else: ?>
                            <span class="badge bg-success">Terpakai</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
