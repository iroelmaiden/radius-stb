<?php
require_once 'config.php';
$conn = db();

$today = date('Y-m-d');
$thisMonth = date('Y-m');

// Voucher stats
$voucherStats = $conn->query("SELECT status, COUNT(*) as cnt FROM vouchers GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$vCount = ['available'=>0, 'sold'=>0, 'used'=>0, 'expired'=>0];
foreach ($voucherStats as $vs) { $vCount[$vs['status']] = $vs['cnt']; }
$vTotal = array_sum($vCount);

// Today's usage (vouchers activated today)
$todayUsage = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(price),0) as revenue
    FROM vouchers WHERE status IN ('used','sold') AND activated_at IS NOT NULL AND DATE(activated_at) = '$today'")->fetch_assoc();

// This month usage
$monthUsage = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(price),0) as revenue
    FROM vouchers WHERE status IN ('used','sold') AND activated_at IS NOT NULL AND DATE(activated_at) BETWEEN '$thisMonth-01' AND LAST_DAY(NOW())")->fetch_assoc();

// PPPoE stats
$pppoeStats = $conn->query("SELECT status, COUNT(*) as cnt FROM pppoe_users GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$pCount = ['active'=>0, 'disabled'=>0, 'isolir'=>0];
foreach ($pppoeStats as $ps) { $pCount[$ps['status']] = $ps['cnt']; }
$pTotal = array_sum($pCount);

// PPPoE billing this month
$billThisMonth = $conn->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM pppoe_billing WHERE billing_period = '$thisMonth' GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$bCount = ['unpaid'=>0, 'paid'=>0, 'overdue'=>0];
$bTotal = ['unpaid'=>0, 'paid'=>0, 'overdue'=>0];
foreach ($billThisMonth as $bs) { $bCount[$bs['status']] = $bs['cnt']; $bTotal[$bs['status']] = $bs['total']; }

// Active sessions
$activeSessions = $conn->query("SELECT COUNT(*) as cnt FROM radacct WHERE acctstoptime IS NULL")->fetch_assoc()['cnt'];

// Recent usage
$recentUsage = $conn->query("
    SELECT v.code, v.username, v.price, v.activated_at, p.name as package_name, v.status
    FROM vouchers v 
    JOIN voucher_packages p ON v.package_id = p.id
    WHERE v.status IN ('used','sold') AND v.activated_at IS NOT NULL
    ORDER BY v.activated_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<h4 class="mb-3"><i class="fas fa-tachometer-alt"></i> Dashboard</h4>

<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total Voucher</small>
                    <h3 class="mb-0"><?= number_format($vTotal) ?></h3>
                </div>
                <i class="fas fa-ticket-alt fa-2x text-primary opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Tersedia</small>
                    <h3 class="mb-0 text-success"><?= number_format($vCount['available']) ?></h3>
                </div>
                <i class="fas fa-box-open fa-2x text-success opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card orange"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Terpakai</small>
                    <h3 class="mb-0 text-warning"><?= number_format($vCount['used'] + $vCount['sold']) ?></h3>
                </div>
                <i class="fas fa-check-circle fa-2x text-warning opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Expired</small>
                    <h3 class="mb-0 text-danger"><?= number_format($vCount['expired']) ?></h3>
                </div>
                <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">PPPoE Total</small>
                    <h3 class="mb-0"><?= number_format($pTotal) ?></h3>
                </div>
                <i class="fas fa-network-wired fa-2x text-info opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Session Aktif</small>
                    <h3 class="mb-0 text-primary"><?= number_format($activeSessions) ?></h3>
                </div>
                <i class="fas fa-wifi fa-2x text-primary opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Tagihan Lunas</small>
                    <h5 class="mb-0 text-success"><?= formatRupiah($bTotal['paid']) ?></h5>
                    <small class="text-muted"><?= $bCount['paid'] ?> tagihan</small>
                </div>
                <i class="fas fa-check-double fa-2x text-success opacity-50"></i>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Belum Bayar</small>
                    <h5 class="mb-0 text-danger"><?= formatRupiah($bTotal['unpaid'] + $bTotal['overdue']) ?></h5>
                    <small class="text-muted"><?= $bCount['unpaid'] + $bCount['overdue'] ?> tagihan</small>
                </div>
                <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-50"></i>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white"><i class="fas fa-calendar-day"></i> Hari Ini</div>
            <div class="card-body text-center">
                <h2 class="text-primary"><?= $todayUsage['cnt'] ?? 0 ?></h2>
                <p class="mb-1">Voucher Terpakai</p>
                <h4 class="text-success"><?= formatRupiah($todayUsage['revenue'] ?? 0) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-success text-white"><i class="fas fa-calendar-alt"></i> Bulan Ini (<?= date('M Y') ?>)</div>
            <div class="card-body text-center">
                <h2 class="text-success"><?= $monthUsage['cnt'] ?? 0 ?></h2>
                <p class="mb-1">Voucher Terpakai</p>
                <h4 class="text-success"><?= formatRupiah($monthUsage['revenue'] ?? 0) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-network-wired"></i> PPPoE</div>
            <div class="card-body">
                <div class="d-flex justify-content-around text-center">
                    <div><h4 class="text-success mb-0"><?= $pCount['active'] ?></h4><small>Active</small></div>
                    <div><h4 class="text-danger mb-0"><?= $pCount['disabled'] ?></h4><small>Disabled</small></div>
                    <div><h4 class="text-warning mb-0"><?= $pCount['isolir'] ?></h4><small>Isolir</small></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-file-invoice-dollar"></i> Tagihan <?= date('M') ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-around text-center">
                    <div><h4 class="text-success mb-0"><?= $bCount['paid'] ?></h4><small>Lunas</small></div>
                    <div><h4 class="text-danger mb-0"><?= $bCount['unpaid'] ?></h4><small>Belum</small></div>
                    <div><h4 class="text-warning mb-0"><?= $bCount['overdue'] ?></h4><small>Overdue</small></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-history"></i> Penggunaan Voucher Terakhir</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr><th width="130">Tanggal</th><th>Kode</th><th>Username</th><th>Paket</th><th>Harga</th></tr>
            </thead>
            <tbody>
                <?php if (empty($recentUsage)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada penggunaan voucher</td></tr>
                <?php else: ?>
                <?php foreach ($recentUsage as $r): ?>
                <tr>
                    <td><small><?= date('d/m/Y H:i', strtotime($r['activated_at'])) ?></small></td>
                    <td><strong><?= htmlspecialchars($r['code']) ?></strong></td>
                    <td><code><?= htmlspecialchars($r['username']) ?></code></td>
                    <td><?= htmlspecialchars($r['package_name']) ?></td>
                    <td><?= formatRupiah($r['price']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
