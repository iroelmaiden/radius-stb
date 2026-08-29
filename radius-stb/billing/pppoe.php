<?php
require_once 'config.php';
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $package_id = intval($_POST['package_id'] ?? 0);
        $ip_address = trim($_POST['ip_address'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $billing_type = $_POST['billing_type'] ?? 'postpaid';
        $billing_date = intval($_POST['billing_date'] ?? 1);

        if (empty($username) || empty($password)) {
            setFlash('Username dan Password wajib diisi.', 'danger');
            header('Location: pppoe.php');
            exit;
        }

        $escU = $conn->real_escape_string($username);
        $escP = $conn->real_escape_string($password);
        $escIP = $conn->real_escape_string($ip_address);
        $escComment = $conn->real_escape_string($comment);
        $escNama = $conn->real_escape_string($nama_lengkap);
        $escAlamat = $conn->real_escape_string($alamat);
        $escPhone = $conn->real_escape_string($phone);

        $check = $conn->query("SELECT id FROM pppoe_users WHERE username = '$escU'");
        if ($check->num_rows > 0) {
            setFlash('Username sudah digunakan.', 'danger');
            header('Location: pppoe.php');
            exit;
        }

        $conn->query("INSERT INTO pppoe_users (username, password, nama_lengkap, alamat, phone, package_id, ip_address, comment, billing_type, billing_date) VALUES ('$escU', '$escP', '$escNama', '$escAlamat', '$escPhone', $package_id, '$escIP', '$escComment', '$billing_type', $billing_date)");

        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Cleartext-Password', ':=', '$escP')");
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Framed-Protocol', ':=', 'PPP')");

        if ($ip_address) {
            $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Framed-IP-Address', ':=', '$escIP')");
        }

        if ($package_id > 0) {
            $pkg = $conn->query("SELECT * FROM pppoe_profiles WHERE id = $package_id");
            if ($pkg->num_rows > 0) {
                $pkgData = $pkg->fetch_assoc();
                if ($pkgData['rate_limit']) {
                    $rl = $conn->real_escape_string($pkgData['rate_limit']);
                    $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Mikrotik-Rate-Limit', ':=', '$rl')");
                }
            }
        }

        $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe', 1)");

        setFlash("PPPoE user '$username' berhasil ditambahkan.", 'success');
        header('Location: pppoe.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $package_id = intval($_POST['package_id'] ?? 0);
        $ip_address = trim($_POST['ip_address'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $billing_type = $_POST['billing_type'] ?? 'postpaid';
        $billing_date = intval($_POST['billing_date'] ?? 1);

        if (empty($username) || empty($password)) {
            setFlash('Username dan Password wajib diisi.', 'danger');
            header('Location: pppoe.php');
            exit;
        }

        $old = $conn->query("SELECT * FROM pppoe_users WHERE id = $id");
        if ($old->num_rows === 0) {
            setFlash('User tidak ditemukan.', 'danger');
            header('Location: pppoe.php');
            exit;
        }
        $oldData = $old->fetch_assoc();
        $oldUser = $conn->real_escape_string($oldData['username']);

        $escU = $conn->real_escape_string($username);
        $escP = $conn->real_escape_string($password);
        $escIP = $conn->real_escape_string($ip_address);
        $escComment = $conn->real_escape_string($comment);
        $escNama = $conn->real_escape_string($nama_lengkap);
        $escAlamat = $conn->real_escape_string($alamat);
        $escPhone = $conn->real_escape_string($phone);

        $conn->query("UPDATE pppoe_users SET username='$escU', password='$escP', nama_lengkap='$escNama', alamat='$escAlamat', phone='$escPhone', package_id=$package_id, ip_address='$escIP', comment='$escComment', billing_type='$billing_type', billing_date=$billing_date WHERE id=$id");

        if ($oldUser !== $escU) {
            $conn->query("UPDATE radcheck SET username='$escU' WHERE username='$oldUser'");
            $conn->query("UPDATE radreply SET username='$escU' WHERE username='$oldUser'");
            $conn->query("UPDATE radusergroup SET username='$escU' WHERE username='$oldUser'");
        }

        $conn->query("DELETE FROM radcheck WHERE username='$escU'");
        $conn->query("DELETE FROM radreply WHERE username='$escU'");

        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Cleartext-Password', ':=', '$escP')");
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Framed-Protocol', ':=', 'PPP')");

        if ($ip_address) {
            $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Framed-IP-Address', ':=', '$escIP')");
        }

        if ($package_id > 0) {
            $pkg = $conn->query("SELECT * FROM pppoe_profiles WHERE id = $package_id");
            if ($pkg->num_rows > 0) {
                $pkgData = $pkg->fetch_assoc();
                if ($pkgData['rate_limit']) {
                    $rl = $conn->real_escape_string($pkgData['rate_limit']);
                    $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Mikrotik-Rate-Limit', ':=', '$rl')");
                }
            }
        }

        $conn->query("DELETE FROM radusergroup WHERE username='$escU'");
        $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe', 1)");

        setFlash("PPPoE user '$username' berhasil diupdate.", 'success');
        header('Location: pppoe.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $row = $conn->query("SELECT username FROM pppoe_users WHERE id = $id");
        if ($row->num_rows > 0) {
            $uname = $conn->real_escape_string($row->fetch_assoc()['username']);
            $conn->query("DELETE FROM radcheck WHERE username = '$uname'");
            $conn->query("DELETE FROM radreply WHERE username = '$uname'");
            $conn->query("DELETE FROM radusergroup WHERE username = '$uname'");
            $conn->query("DELETE FROM pppoe_users WHERE id = $id");
        }
        setFlash('PPPoE user berhasil dihapus.', 'success');
        header('Location: pppoe.php');
        exit;
    }

    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $row = $conn->query("SELECT username, status FROM pppoe_users WHERE id = $id");
        if ($row->num_rows > 0) {
            $d = $row->fetch_assoc();
            $newStatus = $d['status'] === 'active' ? 'disabled' : 'active';
            $conn->query("UPDATE pppoe_users SET status = '$newStatus' WHERE id = $id");
            $uname = $conn->real_escape_string($d['username']);

            if ($newStatus === 'disabled') {
                $conn->query("DELETE FROM radcheck WHERE username = '$uname'");
                $conn->query("DELETE FROM radreply WHERE username = '$uname'");
                $conn->query("DELETE FROM radusergroup WHERE username = '$uname'");
            } else {
                $prow = $conn->query("SELECT * FROM pppoe_users WHERE id = $id");
                $pData = $prow->fetch_assoc();
                $escU = $conn->real_escape_string($pData['username']);
                $escP = $conn->real_escape_string($pData['password']);

                $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Cleartext-Password', ':=', '$escP')");
                $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$escU', 'Framed-Protocol', ':=', 'PPP')");

                if ($pData['ip_address']) {
                    $escIP = $conn->real_escape_string($pData['ip_address']);
                    $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Framed-IP-Address', ':=', '$escIP')");
                }

                if ($pData['package_id'] > 0) {
                    $pkg = $conn->query("SELECT * FROM pppoe_profiles WHERE id = {$pData['package_id']}");
                    if ($pkg->num_rows > 0) {
                        $pkgData = $pkg->fetch_assoc();
                        if ($pkgData['rate_limit']) {
                            $rl = $conn->real_escape_string($pkgData['rate_limit']);
                            $conn->query("INSERT INTO radreply (username, attribute, op, value) VALUES ('$escU', 'Mikrotik-Rate-Limit', ':=', '$rl')");
                        }
                    }
                }

                $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$escU', 'pppoe', 1)");
            }

            setFlash("User '$uname' " . ($newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan') . ".", 'success');
        }
        header('Location: pppoe.php');
        exit;
    }

    if ($action === 'isolir') {
        $id = intval($_POST['id'] ?? 0);
        require_once 'includes/radius_helper.php';
        $row = $conn->query("SELECT username, isolir_status FROM pppoe_users WHERE id = $id");
        if ($row->num_rows > 0) {
            $d = $row->fetch_assoc();
            $uname = $conn->real_escape_string($d['username']);

            if ($d['isolir_status'] === 'active') {
                $result = isolirApply($conn, $id);
                setFlash($result['msg'], $result['ok'] ? 'warning' : 'danger');
            } else {
                $result = isolirRelease($conn, $id);
                setFlash($result['msg'], $result['ok'] ? 'success' : 'danger');
            }
        }
        header('Location: pppoe.php');
        exit;
    }
}

$search = $_GET['search'] ?? '';
$filter_billing = $_GET['filter_billing'] ?? '';
$filter_isolir = $_GET['filter_isolir'] ?? '';
$where = [];
if ($search) {
    $ss = $conn->real_escape_string($search);
    $where[] = "(p.username LIKE '%$ss%' OR p.nama_lengkap LIKE '%$ss%' OR p.phone LIKE '%$ss%' OR p.comment LIKE '%$ss%')";
}
if ($filter_billing) {
    $where[] = "p.billing_type = '" . $conn->real_escape_string($filter_billing) . "'";
}
if ($filter_isolir) {
    $where[] = "p.isolir_status = '" . $conn->real_escape_string($filter_isolir) . "'";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $conn->query("
    SELECT p.*, v.name AS package_name, v.rate_limit
    FROM pppoe_users p
    LEFT JOIN pppoe_profiles v ON p.package_id = v.id
    $whereSQL
    ORDER BY p.id DESC
")->fetch_all(MYSQLI_ASSOC);

$packages = $conn->query("SELECT * FROM pppoe_profiles ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$total = count($users);
$activeCount = $disabledCount = $isolirCount = 0;
foreach ($users as $u) {
    if ($u['isolir_status'] === 'isolir') { $isolirCount++; continue; }
    if ($u['status'] === 'active') $activeCount++; else $disabledCount++;
}

require_once 'includes/header.php';
?>

<h4 class="mb-3"><i class="fas fa-network-wired"></i> User PPPoE</h4>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card green"><div class="card-body py-2">
            <small class="text-muted">Total</small>
            <h4 class="mb-0"><?= number_format($total) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card blue"><div class="card-body py-2">
            <small class="text-muted">Active</small>
            <h4 class="mb-0 text-success"><?= number_format($activeCount) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card red"><div class="card-body py-2">
            <small class="text-muted">Disabled</small>
            <h4 class="mb-0 text-danger"><?= number_format($disabledCount) ?></h4>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card yellow"><div class="card-body py-2">
            <small class="text-muted">Isolir</small>
            <h4 class="mb-0 text-warning"><?= number_format($isolirCount) ?></h4>
        </div></div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="search" class="form-control form-control-sm" style="width:180px" placeholder="Cari username/nama/phone..." value="<?= htmlspecialchars($search) ?>">
        <select name="filter_billing" class="form-select form-select-sm" style="width:130px">
            <option value="">Semua Billing</option>
            <option value="prepaid" <?= $filter_billing === 'prepaid' ? 'selected' : '' ?>>Prepaid</option>
            <option value="postpaid" <?= $filter_billing === 'postpaid' ? 'selected' : '' ?>>Postpaid</option>
        </select>
        <select name="filter_isolir" class="form-select form-select-sm" style="width:120px">
            <option value="">Semua Status</option>
            <option value="active" <?= $filter_isolir === 'active' ? 'selected' : '' ?>>Normal</option>
            <option value="isolir" <?= $filter_isolir === 'isolir' ? 'selected' : '' ?>>Isolir</option>
        </select>
        <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        <?php if ($search || $filter_billing || $filter_isolir): ?><a href="pppoe.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
    </form>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pppoeModal" onclick="resetPppoeForm()">
        <i class="fas fa-plus"></i> Tambah User
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Phone</th>
                    <th>Profile</th>
                    <th>IP</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th width="170">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada user PPPoE</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr class="<?= $u['isolir_status'] === 'isolir' ? 'table-warning' : '' ?>">
                    <td><?= $u['id'] ?></td>
                    <td><code class="text-primary fw-bold"><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><small><?= htmlspecialchars($u['nama_lengkap'] ?? '-') ?></small></td>
                    <td><small><?= htmlspecialchars($u['phone'] ?? '-') ?></small></td>
                    <td><small><?= htmlspecialchars($u['package_name'] ?? '-') ?></small></td>
                    <td><small><?= htmlspecialchars($u['ip_address'] ?? 'Dynamic') ?></small></td>
                    <td>
                        <?php if ($u['billing_type'] === 'prepaid'): ?>
                            <span class="badge bg-info">Prepaid</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Postpaid (tgl <?= $u['billing_date'] ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['isolir_status'] === 'isolir'): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-lock"></i> Isolir</span>
                        <?php elseif ($u['status'] === 'active'): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fas fa-times"></i> Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="isolir">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm <?= $u['isolir_status'] === 'isolir' ? 'btn-success' : 'btn-warning' ?>" title="<?= $u['isolir_status'] === 'isolir' ? 'Lepas Isolir' : 'Isolir' ?>">
                                <i class="fas fa-<?= $u['isolir_status'] === 'isolir' ? 'lock-open' : 'lock' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" title="<?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <i class="fas fa-<?= $u['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                            </button>
                        </form>
                        <a href="billing.php?user_id=<?= $u['id'] ?>" class="btn btn-info btn-sm" title="Tagihan"><i class="fas fa-file-invoice-dollar"></i></a>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#pppoeModal"
                            onclick="editPppoe(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
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

<div class="modal fade" id="pppoeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="pppoeForm">
                <input type="hidden" name="action" id="pppoeAction" value="add">
                <input type="hidden" name="id" id="pppoeId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="pppoeModalTitle"><i class="fas fa-network-wired"></i> Tambah User PPPoE</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-muted mb-3"><i class="fas fa-user"></i> Data Pelanggan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" id="pppoeNama" class="form-control" placeholder="Nama pelanggan">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. HP (WhatsApp)</label>
                            <input type="text" name="phone" id="pppoePhone" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <input type="text" name="alamat" id="pppoeAlamat" class="form-control" placeholder="Alamat pelanggan">
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-network-wired"></i> Akun PPPoE</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="pppoeUsername" class="form-control" required placeholder="username_pppoe">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="text" name="password" id="pppoePassword" class="form-control" required placeholder="password">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profile</label>
                            <select name="package_id" id="pppoePackage" class="form-select">
                                <option value="0">-- Tanpa Profile --</option>
                                <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?> - <?= formatRupiah($pkg['price']) ?> (<?= $pkg['rate_limit'] ?? '-' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IP Address <small class="text-muted">(opsional)</small></label>
                            <input type="text" name="ip_address" id="pppoeIp" class="form-control" placeholder="10.0.0.100 atau dynamic">
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-credit-card"></i> Billing</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Billing</label>
                            <select name="billing_type" id="pppoeBillingType" class="form-select">
                                <option value="postpaid">Postpaid (Bayar Akhir Bulan)</option>
                                <option value="prepaid">Prepaid (Bayar Di Muka)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="billingDateGroup">
                            <label class="form-label">Tanggal Jatuh Tempo</label>
                            <input type="number" name="billing_date" id="pppoeBillingDate" class="form-control" min="1" max="28" value="1">
                            <small class="text-muted">Hari dalam bulan (1-28)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <input type="text" name="comment" id="pppoeComment" class="form-control" placeholder="Keterangan" maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('pppoeBillingType').addEventListener('change', function() {
    document.getElementById('billingDateGroup').style.display = this.value === 'postpaid' ? '' : 'none';
});
document.getElementById('billingDateGroup').style.display = document.getElementById('pppoeBillingType').value === 'postpaid' ? '' : 'none';

function resetPppoeForm() {
    document.getElementById('pppoeForm').reset();
    document.getElementById('pppoeAction').value = 'add';
    document.getElementById('pppoeId').value = '';
    document.getElementById('pppoeModalTitle').innerHTML = '<i class="fas fa-network-wired"></i> Tambah User PPPoE';
    document.getElementById('billingDateGroup').style.display = '';
}

function editPppoe(u) {
    document.getElementById('pppoeAction').value = 'edit';
    document.getElementById('pppoeId').value = u.id;
    document.getElementById('pppoeNama').value = u.nama_lengkap || '';
    document.getElementById('pppoePhone').value = u.phone || '';
    document.getElementById('pppoeAlamat').value = u.alamat || '';
    document.getElementById('pppoeUsername').value = u.username;
    document.getElementById('pppoePassword').value = u.password;
    document.getElementById('pppoePackage').value = u.package_id || 0;
    document.getElementById('pppoeIp').value = u.ip_address || '';
    document.getElementById('pppoeBillingType').value = u.billing_type || 'postpaid';
    document.getElementById('pppoeBillingDate').value = u.billing_date || 1;
    document.getElementById('pppoeComment').value = u.comment || '';
    document.getElementById('pppoeModalTitle').innerHTML = '<i class="fas fa-network-wired"></i> Edit User #' + u.id;
    document.getElementById('billingDateGroup').style.display = u.billing_type === 'postpaid' ? '' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
