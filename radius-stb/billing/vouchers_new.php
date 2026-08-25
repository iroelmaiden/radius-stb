<?php
require_once 'config.php';
require_once 'includes/header.php';
$conn = db();

function generateCustomCode($length = 8, $type = 'alphanum') {
    $sets = [
        'alpha_upper'   => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'alpha_lower'   => 'abcdefghjkmnpqrstuvwxyz',
        'alpha_mixed'   => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz',
        'num'           => '23456789',
        'alphanum_upper'=> 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'alphanum_lower'=> 'abcdefghjkmnpqrstuvwxyz23456789',
        'alphanum_mixed'=> 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789',
        'hex'           => '0123456789abcdef',
    ];
    $chars = $sets[$type] ?? $sets['alphanum_upper'];
    $code = '';
    for ($i = 0; $i < $length; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $package_id = (int)$_POST['package_id'];
        $quantity = (int)$_POST['quantity'];
        $char_count = max(4, min(16, (int)($_POST['char_count'] ?? 8)));
        $char_type = $_POST['char_type'] ?? 'alphanum_upper';
        $user_eq_pass = isset($_POST['user_eq_pass']) ? 1 : 0;
        $pkg = $conn->query("SELECT * FROM voucher_packages WHERE id = $package_id")->fetch_assoc();
        if (!$pkg) { setFlash('Paket tidak ditemukan!', 'danger'); header('Location: vouchers.php'); exit; }
        $generated = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $code = generateCustomCode($char_count, $char_type);
            $username = strtolower($code);
            $password = $user_eq_pass ? $username : generateCustomCode($char_count, $char_type);
            $rate_limit = $conn->real_escape_string($pkg['rate_limit']);
            $session_timeout = $pkg['session_timeout'];
            if ($conn->query("INSERT INTO vouchers (code, package_id, username, password, status) VALUES ('$code', $package_id, '$username', '$password', 'available')")) {
                $generated++;
                $conn->query("INSERT IGNORE INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Cleartext-Password', ':=', '$password')");
                $conn->query("INSERT IGNORE INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Mikrotik-Rate-Limit', ':=', '$rate_limit')");
                $conn->query("INSERT IGNORE INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Session-Timeout', ':=', '$session_timeout')");
                $conn->query("INSERT IGNORE INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Simultaneous-Use', ':=', '1')");
                $conn->query("INSERT IGNORE INTO radreply (username, attribute, op, value) VALUES ('$username', 'Mikrotik-Rate-Limit', ':=', '$rate_limit')");
                $conn->query("INSERT IGNORE INTO radreply (username, attribute, op, value) VALUES ('$username', 'Session-Timeout', ':=', '$session_timeout')");
                $conn->query("INSERT IGNORE INTO radusergroup (username, groupname, priority) VALUES ('$username', 'hotspot', 1)");
            }
        }
        setFlash("$generated voucher berhasil dibuat!");
        header('Location: vouchers.php'); exit;
    }

    if ($action === 'sell') {
        $voucher_id = (int)$_POST['voucher_id'];
        $buyer = $conn->real_escape_string($_POST['buyer_name']);
        $voucher = $conn->query("SELECT v.*, p.price FROM vouchers v JOIN voucher_packages p ON v.package_id = p.id WHERE v.id = $voucher_id AND v.status = 'available'")->fetch_assoc();
        if ($voucher) {
            $conn->query("UPDATE vouchers SET status = 'sold', sold_at = NOW() WHERE id = $voucher_id");
            $conn->query("INSERT INTO transactions (voucher_id, buyer_name, price_sold) VALUES ($voucher_id, '$buyer', {$voucher['price']})");
            setFlash("Voucher {$voucher['code']} berhasil dijual ke $buyer!");
        } else { setFlash('Voucher tidak ditemukan atau sudah terjual!', 'danger'); }
        header('Location: vouchers.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $voucher = $conn->query("SELECT * FROM vouchers WHERE id = $id AND status = 'available'")->fetch_assoc();
        if ($voucher) {
            $un = $conn->real_escape_string($voucher['username']);
            $conn->query("DELETE FROM radcheck WHERE username = '$un'");
            $conn->query("DELETE FROM radreply WHERE username = '$un'");
            $conn->query("DELETE FROM radusergroup WHERE username = '$un'");
            $conn->query("DELETE FROM vouchers WHERE id = $id");
            setFlash("Voucher {$voucher['code']} berhasil dihapus!");
        } else { setFlash('Voucher tidak ditemukan atau sudah terjual!', 'danger'); }
        header('Location: vouchers.php'); exit;
    }

    if ($action === 'bulk_delete') {
        $ids = $_POST['voucher_ids'] ?? [];
        if (!empty($ids)) {
            $idList = implode(',', array_map('intval', $ids));
            $vDel = $conn->query("SELECT * FROM vouchers WHERE id IN ($idList) AND status = 'available'");
            $deleted = 0;
            while ($v = $vDel->fetch_assoc()) {
                $un = $conn->real_escape_string($v['username']);
                $conn->query("DELETE FROM radcheck WHERE username = '$un'");
                $conn->query("DELETE FROM radreply WHERE username = '$un'");
                $conn->query("DELETE FROM radusergroup WHERE username = '$un'");
                $deleted++;
            }
            $conn->query("DELETE FROM vouchers WHERE id IN ($idList) AND status = 'available'");
            setFlash("$deleted voucher berhasil dihapus!");
        } else { setFlash('Tidak ada voucher dipilih!', 'warning'); }
        header('Location: vouchers.php'); exit;
    }
}

$filter_package = isset($_GET['package']) ? (int)$_GET['package'] : 0;
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_search = $_GET['search'] ?? '';
$where = [];
if ($filter_package > 0) $where[] = "v.package_id = $filter_package";
if ($filter_status) $where[] = "v.status = '" . $conn->real_escape_string($filter_status) . "'";
if ($filter_date_from) $where[] = "DATE(v.created_at) >= '" . $conn->real_escape_string($filter_date_from) . "'";
if ($filter_date_to) $where[] = "DATE(v.created_at) <= '" . $conn->real_escape_string($filter_date_to) . "'";
if ($filter_search) { $s = $conn->real_escape_string($filter_search); $where[] = "(v.code LIKE '%$s%' OR v.username LIKE '%$s%')"; }
$whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$packages = $conn->query("SELECT * FROM voucher_packages ORDER BY duration_hours")->fetch_all(MYSQLI_ASSOC);
$pkgMap = [];
foreach ($packages as $p) $pkgMap[$p['id']] = $p;

$total = $conn->query("SELECT COUNT(*) as cnt FROM vouchers v $whereSQL")->fetch_assoc()['cnt'];
$aQ = "SELECT COUNT(*) as cnt FROM vouchers v WHERE v.status = 'available'";
$sQ = "SELECT COUNT(*) as cnt FROM vouchers v WHERE v.status = 'sold'";
if ($filter_package > 0) { $aQ .= " AND v.package_id = $filter_package"; $sQ .= " AND v.package_id = $filter_package"; }
if ($filter_date_from) { $aQ .= " AND DATE(v.created_at) >= '$filter_date_from'"; $sQ .= " AND DATE(v.created_at) >= '$filter_date_from'"; }
if ($filter_date_to) { $aQ .= " AND DATE(v.created_at) <= '$filter_date_to'"; $sQ .= " AND DATE(v.created_at) <= '$filter_date_to'"; }
$available = $conn->query($aQ)->fetch_assoc()['cnt'];
$sold = $conn->query($sQ)->fetch_assoc()['cnt'];

$groups = $conn->query("SELECT DATE(v.created_at) as dg, COUNT(*) as cnt FROM vouchers v $whereSQL GROUP BY DATE(v.created_at) ORDER BY dg DESC")->fetch_all(MYSQLI_ASSOC);
?>
<style>
.vr{transition:background .15s}.vr:hover{background:#f0f7ff}
.gh{cursor:pointer;user-select:none}.gh:hover{background:#e9ecef}
@media print{.no-print{display:none!important}.vp{page-break-inside:avoid;border:1px solid #333;padding:8px;margin:4px 0;font-size:11px}.vp .vc{font-size:14px;font-weight:bold}}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h4><i class="fas fa-ticket-alt"></i> Manajemen Voucher</h4>
    <div>
        <button class="btn btn-success me-1" onclick="printSelected()"><i class="fas fa-print"></i> Cetak</button>
        <button class="btn btn-danger me-1" onclick="bulkDelete()"><i class="fas fa-trash"></i> Hapus</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="fas fa-plus"></i> Generate</button>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-md-3"><div class="card stat-card blue"><div class="card-body"><h6 class="text-muted">Total</h6><h3><?= $total ?></h3></div></div></div>
    <div class="col-md-3"><div class="card stat-card green"><div class="card-body"><h6 class="text-muted">Tersedia</h6><h3 class="text-success"><?= $available ?></h3></div></div></div>
    <div class="col-md-3"><div class="card stat-card orange"><div class="card-body"><h6 class="text-muted">Terjual</h6><h3 class="text-warning"><?= $sold ?></h3></div></div></div>
    <div class="col-md-3"><div class="card stat-card red"><div class="card-body"><h6 class="text-muted">Dipilih</h6><h3 class="text-danger" id="countSelected">0</h3></div></div></div>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Paket</label>
                <select name="package" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($packages as $pkg): ?>
                    <option value="<?= $pkg['id'] ?>" <?= $filter_package == $pkg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pkg['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>Tersedia</option>
                    <option value="sold" <?= $filter_status == 'sold' ? 'selected' : '' ?>>Terjual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Kode/Username" value="<?= htmlspecialchars($filter_search) ?>">
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter"></i></button></div>
            <div class="col-md-1"><a href="vouchers.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-redo"></i></a></div>
        </form>
    </div>
</div>

<form id="bulkForm" method="POST">
<input type="hidden" name="action" value="bulk_delete">
<div class="card no-print">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                        <th>Kode</th><th>Paket</th><th>Username</th><th>Password</th><th>Harga</th><th>Status</th><th>Tgl</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $grp): ?>
                    <?php
                    $grpDate = $grp['dg'];
                    $grpDateEsc = $conn->real_escape_string($grpDate);
                    $statusFilter = $filter_status ? "AND v.status = '" . $conn->real_escape_string($filter_status) . "'" : "";
                    $vr = $conn->query("SELECT v.*, p.name as pkg_name, p.price FROM vouchers v JOIN voucher_packages p ON v.package_id = p.id WHERE DATE(v.created_at) = '$grpDateEsc' $statusFilter ORDER BY v.created_at DESC");
                    $vRows = [];
                    while ($row = $vr->fetch_assoc()) $vRows[] = $row;
                    ?>
                    <tr class="gh table-secondary" onclick="toggleGroup('g<?= $grpDate ?>')">
                        <td colspan="9"><i class="fas fa-chevron-down" id="ig<?= $grpDate ?>"></i> <strong><?= date('d M Y', strtotime($grpDate)) ?></strong> (<?= $grp['cnt'] ?> voucher)</td>
                    </tr>
                    <?php foreach ($vRows as $v): ?>
                    <tr class="vr gv g<?= $grpDate ?>">
                        <td><input type="checkbox" name="voucher_ids[]" value="<?= $v['id'] ?>" class="vcb" onchange="updateCount()"></td>
                        <td><strong><?= htmlspecialchars($v['code']) ?></strong></td>
                        <td><?= htmlspecialchars($v['pkg_name']) ?></td>
                        <td><code><?= htmlspecialchars($v['username']) ?></code></td>
                        <td><code><?= htmlspecialchars($v['password']) ?></code></td>
                        <td><?= formatRupiah($v['price']) ?></td>
                        <td><?php if ($v['status'] == 'available'): ?><span class="badge bg-success">Tersedia</span><?php elseif ($v['status'] == 'sold'): ?><span class="badge bg-warning">Terjual</span><?php else: ?><span class="badge bg-secondary">Expired</span><?php endif; ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
                        <td>
                            <?php if ($v['status'] == 'available'): ?>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#sell<?= $v['id'] ?>"><i class="fas fa-shopping-cart"></i></button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="if(confirm('Hapus?')){document.getElementById('delId').value='<?= $v['id'] ?>';document.getElementById('delForm').submit();}"><i class="fas fa-trash"></i></button>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</form>

<form id="delForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delId" value="">
</form>

<?php foreach ($groups as $grp): ?>
<?php
$grpDate = $grp['dg'];
$grpDateEsc = $conn->real_escape_string($grpDate);
$sellQ = "SELECT v.id, v.code, v.username, v.password, v.price, p.name as pkg_name FROM vouchers v JOIN voucher_packages p ON v.package_id = p.id WHERE DATE(v.created_at) = '$grpDateEsc' AND v.status = 'available' ORDER BY v.created_at DESC";
$sellR = $conn->query($sellQ);
while ($sv = $sellR->fetch_assoc()):
?>
<div class="modal fade" id="sell<?= $sv['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST"><input type="hidden" name="action" value="sell"><input type="hidden" name="voucher_id" value="<?= $sv['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Jual Voucher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info"><strong>Kode:</strong> <?= htmlspecialchars($sv['code']) ?><br><strong>Paket:</strong> <?= htmlspecialchars($sv['pkg_name']) ?><br><strong>Harga:</strong> <?= formatRupiah($sv['price']) ?></div>
            <div class="mb-3"><label class="form-label">Nama Pembeli</label><input type="text" class="form-control" name="buyer_name" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Jual</button></div>
    </form>
</div></div></div>
<?php endwhile; ?>
<?php endforeach; ?>

<div class="modal fade" id="generateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST"><input type="hidden" name="action" value="generate">
        <div class="modal-header"><h5 class="modal-title">Generate Voucher Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Pilih Paket</label>
                <select name="package_id" class="form-select" required><option value="">-- Pilih Paket --</option>
                <?php foreach ($packages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> - <?= formatRupiah($pkg['price']) ?></option>
                <?php endforeach; ?></select>
            </div>
            <div class="mb-3"><label class="form-label">Jumlah Voucher</label><input type="number" class="form-control" name="quantity" value="10" min="1" max="100" required></div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Panjang Karakter</label>
                    <input type="number" class="form-control" name="char_count" value="8" min="4" max="16" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Karakter</label>
                    <select name="char_type" class="form-select">
                        <option value="alphanum_upper">Huruf+Angka, Besar (ABC...123...)</option>
                        <option value="alphanum_lower">Huruf+Angka, Kecil (abc...123...)</option>
                        <option value="alphanum_mixed">Huruf+Angka, Campur (AbC...123...)</option>
                        <option value="alpha_upper">Huruf Saja, Besar (ABCDEFG...)</option>
                        <option value="alpha_lower">Huruf Saja, Kecil (abcdefg...)</option>
                        <option value="alpha_mixed">Huruf Saja, Campur (AbCdEfG...)</option>
                        <option value="num">Angka Saja (23456789)</option>
                        <option value="hex">Hex (0-9 a-f)</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="user_eq_pass" id="userEqPass" checked>
                    <label class="form-check-label" for="userEqPass">Username = Password</label>
                </div>
            </div>
            <div class="alert alert-warning"><i class="fas fa-info-circle"></i> Voucher otomatis terdaftar di RADIUS server.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Generate</button></div>
    </form>
</div></div></div>

<script>
function toggleGroup(g){
    var rows=document.querySelectorAll('.gv.'+g);
    var icon=document.getElementById('ig'+g);
    var show=rows[0]?rows[0].style.display:'none';
    rows.forEach(function(r){r.style.display=show==='none'?'':'none';});
    if(icon){icon.className=show==='none'?'fas fa-chevron-right':'fas fa-chevron-down';}
}
function toggleSelectAll(cb){
    document.querySelectorAll('.vcb').forEach(function(c){
        if(c.closest('tr').style.display!=='none')c.checked=cb.checked;
    });
    updateCount();
}
function updateCount(){
    var n=0;document.querySelectorAll('.vcb:checked').forEach(function(){n++;});
    document.getElementById('countSelected').textContent=n;
}
function bulkDelete(){
    var ids=[];
    document.querySelectorAll('.vcb:checked').forEach(function(c){ids.push(c.value);});
    if(ids.length===0){alert('Pilih voucher terlebih dahulu!');return;}
    if(!confirm('Hapus '+ids.length+' voucher?'))return;
    document.getElementById('bulkForm').submit();
}
function printSelected(){
    var ids=[];
    document.querySelectorAll('.vcb:checked').forEach(function(c){ids.push(c.value);});
    if(ids.length===0){alert('Pilih voucher untuk dicetak!');return;}
    var w=window.open('','_blank','width=800,height=600');
    var h='<html><head><title>Cetak Voucher</title><style>';
    h+='body{font-family:Arial,sans-serif;padding:20px;font-size:12px}';
    h+='.vp{border:1px solid #333;padding:10px;margin:6px 0;page-break-inside:avoid}';
    h+='.vc{font-size:16px;font-weight:bold;margin:4px 0}';
    h+='h2{text-align:center;margin-bottom:20px}';
    h+='@media print{button{display:none}}';
    h+='</style></head><body>';
    h+='<button onclick="window.print()">Cetak</button><hr>';
    h+='<h2>Voucher WiFi Hotspot</h2>';
    document.querySelectorAll('.vcb:checked').forEach(function(c){
        var tr=c.closest('tr');
        var tds=tr.querySelectorAll('td');
        h+='<div class="vp">';
        h+='<div class="vc">Username: '+tds[3].textContent.trim()+'</div>';
        h+='<div class="vc">Password: '+tds[4].textContent.trim()+'</div>';
        h+='<div>Paket: '+tds[2].textContent.trim()+' | Kode: '+tds[1].textContent.trim()+'</div>';
        h+='</div>';
    });
    h+='</body></html>';
    w.document.write(h);w.document.close();
}
</script>

<?php require_once 'includes/footer.php'; ?>
