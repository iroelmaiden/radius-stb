<?php
require_once 'config.php';
$conn = db();

// Handle POST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $package_id = $_POST['package_id'] ?: null;
        $rate_limit = trim($_POST['rate_limit'] ?? '');
        $ip_address = trim($_POST['ip_address'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $comment = trim($_POST['comment'] ?? '');

        if ($username && $password) {
            if ($package_id) {
                $pkg = $conn->query("SELECT rate_limit FROM voucher_packages WHERE id=" . intval($package_id))->fetch_assoc();
                if ($pkg && $pkg['rate_limit']) {
                    $rate_limit = $pkg['rate_limit'];
                }
            }

            $stmt = $conn->prepare("INSERT INTO hotspot_users (username, password, nama_lengkap, phone, alamat, package_id, rate_limit, ip_address, status, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssissss', $username, $password, $nama_lengkap, $phone, $alamat, $package_id, $rate_limit, $ip_address, $status, $comment);

            if ($stmt->execute()) {
                syncUserToRadius($username, $password, $rate_limit, $status);
                setFlash('success', 'User hotspot berhasil ditambahkan');
            } else {
                setFlash('error', 'Gagal menambahkan user: ' . $conn->error);
            }
        } else {
            setFlash('error', 'Username dan password wajib diisi');
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $package_id = $_POST['package_id'] ?: null;
        $rate_limit = trim($_POST['rate_limit'] ?? '');
        $ip_address = trim($_POST['ip_address'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $comment = trim($_POST['comment'] ?? '');

        if ($id && $username) {
            $old = $conn->query("SELECT username FROM hotspot_users WHERE id=$id")->fetch_assoc();
            $old_username = $old ? $old['username'] : $username;

            if ($package_id) {
                $pkg = $conn->query("SELECT rate_limit FROM voucher_packages WHERE id=" . intval($package_id))->fetch_assoc();
                if ($pkg && $pkg['rate_limit']) {
                    $rate_limit = $pkg['rate_limit'];
                }
            }

            if ($password) {
                $stmt = $conn->prepare("UPDATE hotspot_users SET username=?, password=?, nama_lengkap=?, phone=?, alamat=?, package_id=?, rate_limit=?, ip_address=?, status=?, comment=? WHERE id=?");
                $stmt->bind_param('sssssissssi', $username, $password, $nama_lengkap, $phone, $alamat, $package_id, $rate_limit, $ip_address, $status, $comment, $id);
            } else {
                $stmt = $conn->prepare("UPDATE hotspot_users SET username=?, nama_lengkap=?, phone=?, alamat=?, package_id=?, rate_limit=?, ip_address=?, status=?, comment=? WHERE id=?");
                $stmt->bind_param('ssssissssi', $username, $nama_lengkap, $phone, $alamat, $package_id, $rate_limit, $ip_address, $status, $comment, $id);
            }

            if ($stmt->execute()) {
                if ($old_username !== $username) {
                    removeUserFromRadius($old_username);
                }
                syncUserToRadius($username, $password, $rate_limit, $status);
                setFlash('success', 'User hotspot berhasil diupdate');
            } else {
                setFlash('error', 'Gagal update user: ' . $conn->error);
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $user = $conn->query("SELECT username FROM hotspot_users WHERE id=$id")->fetch_assoc();
            if ($user) {
                removeUserFromRadius($user['username']);
                $conn->query("DELETE FROM hotspot_users WHERE id=$id");
                setFlash('success', 'User hotspot berhasil dihapus');
            }
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $user = $conn->query("SELECT username, status FROM hotspot_users WHERE id=$id")->fetch_assoc();
            if ($user) {
                $new_status = $user['status'] === 'active' ? 'disabled' : 'active';
                $conn->query("UPDATE hotspot_users SET status='$new_status' WHERE id=$id");
                $rate = $conn->query("SELECT rate_limit FROM hotspot_users WHERE id=$id")->fetch_assoc();
                syncUserToRadius($user['username'], null, $rate['rate_limit'], $new_status);
                setFlash('success', 'Status user berhasil diubah ke ' . $new_status);
            }
        }
    }

    header('Location: hotspot-users.php');
    exit;
}

function syncUserToRadius($username, $password, $rate_limit, $status) {
    global $conn;

    if ($status === 'disabled') {
        removeUserFromRadius($username);
        return;
    }

    $conn->query("DELETE FROM radcheck WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Cleartext-Password'");
    $stmt = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();

    $conn->query("DELETE FROM radreply WHERE username='" . $conn->real_escape_string($username) . "' AND attribute='Mikrotik-Rate-Limit'");
    if ($rate_limit) {
        $stmt = $conn->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
        $stmt->bind_param('ss', $username, $rate_limit);
        $stmt->execute();
    }

    $conn->query("DELETE FROM radusergroup WHERE username='" . $conn->real_escape_string($username) . "'");
    $stmt = $conn->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, 'hotspot', 1)");
    $stmt->bind_param('s', $username);
    $stmt->execute();
}

function removeUserFromRadius($username) {
    global $conn;
    $esc = $conn->real_escape_string($username);
    $conn->query("DELETE FROM radcheck WHERE username='$esc'");
    $conn->query("DELETE FROM radreply WHERE username='$esc'");
    $conn->query("DELETE FROM radusergroup WHERE username='$esc'");
}

$users = $conn->query("SELECT hu.*, hp.name as package_name, hp.rate_limit as pkg_rate FROM hotspot_users hu LEFT JOIN voucher_packages hp ON hu.package_id = hp.id ORDER BY hu.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$packages = $conn->query("SELECT * FROM voucher_packages ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<?php require_once 'includes/header.php'; ?>

<div class="content-area">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col">
                <h4 class="mb-0">
                    <i class="fas fa-user"></i> User Hotspot
                    <small class="text-muted fs-6">(Tanpa Voucher - Unlimited Time)</small>
                </h4>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                    <i class="fas fa-plus"></i> Tambah User
                </button>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="row mb-3">
            <div class="col-6 col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center py-2">
                        <h5 class="mb-0"><?= count($users) ?></h5>
                        <small>Total User</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center py-2">
                        <?php $active = count(array_filter($users, fn($u) => $u['status'] === 'active')); ?>
                        <h5 class="mb-0"><?= $active ?></h5>
                        <small>Active</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center py-2">
                        <?php $disabled = count(array_filter($users, fn($u) => $u['status'] === 'disabled')); ?>
                        <h5 class="mb-0"><?= $disabled ?></h5>
                        <small>Disabled</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center py-2">
                        <h5 class="mb-0"><?= count($packages) ?></h5>
                        <small>Paket</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Cari username/nama..." onkeyup="filterTable()">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="statusFilter" onchange="filterTable()">
                            <option value="">Semua Status</option>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover table-sm mb-0" id="usersTable">
                    <thead>
                        <tr>
                            <th width="40">#</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Paket</th>
                            <th>Rate Limit</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th width="120">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="9" class="text-center text-muted">Belum ada user hotspot</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $i => $u): ?>
                        <tr class="user-row" data-status="<?= $u['status'] ?>">
                            <td><?= $i + 1 ?></td>
                            <td><code><?= $u['username'] ?></code></td>
                            <td><?= $u['nama_lengkap'] ?: '-' ?></td>
                            <td><?= $u['package_name'] ?: '-' ?></td>
                            <td><small><?= $u['rate_limit'] ?: '-' ?></small></td>
                            <td><small><?= $u['ip_address'] ?: 'Dinamis' ?></small></td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d M Y', strtotime($u['created_at'])) ?></small></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                                        <i class="fas fa-<?= $u['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)' title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah User Hotspot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="formUsername" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger" id="pwRequired">*</span></label>
                        <input type="text" class="form-control" name="password" id="formPassword">
                        <small class="text-muted" id="pwHint">Kosongkan saat edit jika tidak ingin ganti password</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" id="formNama">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" name="phone" id="formPhone">
                        </div>
                        <div class="col-6">
                            <label class="form-label">IP Address</label>
                            <input type="text" class="form-control" name="ip_address" id="formIp" placeholder="Kosongkan = dinamis">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" id="formAlamat" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Paket</label>
                            <select class="form-select" name="package_id" id="formPackage" onchange="updateRateLimit()">
                                <option value="">-- Pilih Paket --</option>
                                <?php foreach ($packages as $p): ?>
                                <option value="<?= $p['id'] ?>" data-rate="<?= $p['rate_limit'] ?>"><?= $p['name'] ?> (<?= $p['rate_limit'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Rate Limit</label>
                            <input type="text" class="form-control" name="rate_limit" id="formRateLimit" placeholder="contoh: 5M/5M">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="formStatus">
                                <option value="active">Active</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Keterangan</label>
                            <input type="text" class="form-control" name="comment" id="formComment">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('modalTitle').textContent = 'Tambah User Hotspot';
    document.getElementById('formUsername').value = '';
    document.getElementById('formPassword').value = '';
    document.getElementById('formNama').value = '';
    document.getElementById('formPhone').value = '';
    document.getElementById('formIp').value = '';
    document.getElementById('formAlamat').value = '';
    document.getElementById('formPackage').value = '';
    document.getElementById('formRateLimit').value = '';
    document.getElementById('formStatus').value = 'active';
    document.getElementById('formComment').value = '';
    document.getElementById('pwRequired').textContent = '*';
    document.getElementById('pwHint').textContent = 'Kosongkan saat edit jika tidak ingin ganti password';
    document.getElementById('formPassword').setAttribute('required', 'required');
}

function editUser(u) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = u.id;
    document.getElementById('modalTitle').textContent = 'Edit User Hotspot';
    document.getElementById('formUsername').value = u.username;
    document.getElementById('formPassword').value = '';
    document.getElementById('formNama').value = u.nama_lengkap || '';
    document.getElementById('formPhone').value = u.phone || '';
    document.getElementById('formIp').value = u.ip_address || '';
    document.getElementById('formAlamat').value = u.alamat || '';
    document.getElementById('formPackage').value = u.package_id || '';
    document.getElementById('formRateLimit').value = u.rate_limit || '';
    document.getElementById('formStatus').value = u.status;
    document.getElementById('formComment').value = u.comment || '';
    document.getElementById('pwRequired').textContent = '';
    document.getElementById('pwHint').textContent = 'Kosongkan jika tidak ingin ganti password';
    document.getElementById('formPassword').removeAttribute('required');

    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

function updateRateLimit() {
    var sel = document.getElementById('formPackage');
    var opt = sel.options[sel.selectedIndex];
    var rate = opt.getAttribute('data-rate') || '';
    document.getElementById('formRateLimit').value = rate;
}

function filterTable() {
    var search = document.getElementById('searchInput').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('.user-row');
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        var rowStatus = row.getAttribute('data-status');
        var matchSearch = !search || text.includes(search);
        var matchStatus = !status || rowStatus === status;
        row.style.display = matchSearch && matchStatus ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
