<?php
require_once 'config.php';
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'kick') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Admin-Kick' WHERE radacctid=$id AND acctstoptime IS NULL");
        setFlash("User berhasil di-kick!");
        header('Location: users.php'); exit;
    }
}

$active = $conn->query("
    SELECT r.radacctid, r.username, r.nasipaddress, r.acctstarttime, r.framedipaddress,
           v.code, v.status as vstatus, p.name as package_name, t.buyer_name
    FROM radacct r
    LEFT JOIN vouchers v ON v.username = r.username
    LEFT JOIN voucher_packages p ON v.package_id = p.id
    LEFT JOIN transactions t ON t.voucher_id = v.id
    WHERE r.acctstoptime IS NULL
    ORDER BY r.acctstarttime DESC
")->fetch_all(MYSQLI_ASSOC);

$all = $conn->query("
    SELECT r.radacctid, r.username, r.nasipaddress, r.acctstarttime, r.acctstoptime, r.acctterminatecause, r.framedipaddress,
           v.code, v.status as vstatus, p.name as package_name, t.buyer_name
    FROM radacct r
    LEFT JOIN vouchers v ON v.username = r.username
    LEFT JOIN voucher_packages p ON v.package_id = p.id
    LEFT JOIN transactions t ON t.voucher_id = v.id
    ORDER BY r.acctstarttime DESC LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<h4 class="mb-3"><i class="fas fa-users"></i> User Aktif</h4>

<div class="row mb-3">
    <div class="col"><div class="card stat-card green"><div class="card-body py-2"><small class="text-muted">Sedang Online</small><h4 class="mb-0 text-success"><?= count($active) ?></h4></div></div></div>
    <div class="col"><div class="card stat-card blue"><div class="card-body py-2"><small class="text-muted">Total Session</small><h4 class="mb-0"><?= count($all) ?></h4></div></div></div>
</div>

<?php if (empty($active)): ?>
<div class="alert alert-info"><i class="fas fa-info-circle"></i> Tidak ada user yang sedang aktif (online).</div>
<?php else: ?>

<div class="card mb-3">
    <div class="card-header bg-success text-white"><i class="fas fa-circle"></i> User Online (<?= count($active) ?>)</div>
    <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
    <thead class="table-light"><tr><th>Username</th><th>Paket</th><th>Pembeli</th><th>IP Client</th><th>NAS</th><th>Login Sejak</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($active as $a): ?>
    <tr>
        <td><code><?= htmlspecialchars($a['username']) ?></code></td>
        <td><?= htmlspecialchars($a['package_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($a['buyer_name'] ?? '-') ?></td>
        <td><?= htmlspecialchars($a['framedipaddress'] ?? '-') ?></td>
        <td><?= htmlspecialchars($a['nasipaddress']) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($a['acctstarttime'])) ?></td>
        <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Kick user ini?')">
                <input type="hidden" name="action" value="kick"><input type="hidden" name="id" value="<?= $a['radacctid'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" title="Kick"><i class="fas fa-times-circle"></i> Kick</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="fas fa-history"></i> Semua Session (terbaru)</div>
    <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0">
    <thead class="table-light"><tr><th>Username</th><th>Paket</th><th>Status</th><th>IP</th><th>Login</th><th>Logout</th><th>Sebab</th></tr></thead>
    <tbody>
    <?php foreach($all as $a): ?>
    <tr>
        <td><code><?= htmlspecialchars($a['username']) ?></code></td>
        <td><?= htmlspecialchars($a['package_name'] ?? '-') ?></td>
        <td><?= $a['acctstoptime'] ? '<span class="badge bg-secondary">Offline</span>' : '<span class="badge bg-success">Online</span>' ?></td>
        <td><?= htmlspecialchars($a['framedipaddress'] ?? '-') ?></td>
        <td><?= date('d/m/Y H:i', strtotime($a['acctstarttime'])) ?></td>
        <td><?= $a['acctstoptime'] ? date('d/m/Y H:i', strtotime($a['acctstoptime'])) : '-' ?></td>
        <td><?= htmlspecialchars($a['acctterminatecause'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
