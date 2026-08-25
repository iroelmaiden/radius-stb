<?php
require_once 'config.php';
$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $rate_limit = trim($_POST['rate_limit'] ?? '');
        $session_timeout = intval($_POST['session_timeout'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (empty($name)) {
            setFlash('Nama profile wajib diisi.', 'danger');
            header('Location: pppoe-profiles.php');
            exit;
        }

        $escN = $conn->real_escape_string($name);
        $escRL = $conn->real_escape_string($rate_limit);
        $escC = $conn->real_escape_string($comment);

        $conn->query("INSERT INTO pppoe_profiles (name, rate_limit, session_timeout, price, comment) VALUES ('$escN', '$escRL', $session_timeout, $price, '$escC')");

        setFlash("Profile '$name' berhasil ditambahkan.", 'success');
        header('Location: pppoe-profiles.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $rate_limit = trim($_POST['rate_limit'] ?? '');
        $session_timeout = intval($_POST['session_timeout'] ?? 0);
        $price = floatval($_POST['price'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if (empty($name)) {
            setFlash('Nama profile wajib diisi.', 'danger');
            header('Location: pppoe-profiles.php');
            exit;
        }

        $escN = $conn->real_escape_string($name);
        $escRL = $conn->real_escape_string($rate_limit);
        $escC = $conn->real_escape_string($comment);

        $conn->query("UPDATE pppoe_profiles SET name='$escN', rate_limit='$escRL', session_timeout=$session_timeout, price=$price, comment='$escC' WHERE id=$id");

        setFlash("Profile '$name' berhasil diupdate.", 'success');
        header('Location: pppoe-profiles.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $inUse = $conn->query("SELECT COUNT(*) as cnt FROM pppoe_users WHERE package_id = $id")->fetch_assoc()['cnt'];
        if ($inUse > 0) {
            setFlash("Profile masih digunakan oleh $inUse user. Hapus user terlebih dahulu.", 'danger');
            header('Location: pppoe-profiles.php');
            exit;
        }

        $conn->query("DELETE FROM pppoe_profiles WHERE id = $id");
        setFlash('Profile berhasil dihapus.', 'success');
        header('Location: pppoe-profiles.php');
        exit;
    }
}

$profiles = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM pppoe_users WHERE package_id = p.id) as user_count FROM pppoe_profiles p ORDER BY p.id")->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<h4 class="mb-3"><i class="fas fa-layer-group"></i> PPPoE Profile</h4>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div><span class="badge bg-primary"><?= count($profiles) ?> profile(s)</span></div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal" onclick="resetForm()">
        <i class="fas fa-plus"></i> Tambah Profile
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th width="50">ID</th>
                    <th>Nama</th>
                    <th>Rate Limit</th>
                    <th>Session Timeout</th>
                    <th>Harga</th>
                    <th>Users</th>
                    <th>Comment</th>
                    <th width="120">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profiles)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada profile</td></tr>
                <?php else: ?>
                <?php foreach ($profiles as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($p['rate_limit'] ?? '-') ?></code></td>
                    <td><?= $p['session_timeout'] ? $p['session_timeout'] . ' detik' : '-' ?></td>
                    <td><?= formatRupiah($p['price']) ?></td>
                    <td><span class="badge bg-info"><?= $p['user_count'] ?></span></td>
                    <td><small><?= htmlspecialchars($p['comment'] ?? '-') ?></small></td>
                    <td>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal"
                            onclick="editProfile(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', '<?= htmlspecialchars(addslashes($p['rate_limit'] ?? '')) ?>', <?= $p['session_timeout'] ?? 0 ?>, <?= $p['price'] ?>, '<?= htmlspecialchars(addslashes($p['comment'] ?? '')) ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus profile ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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

<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="profileForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-layer-group"></i> Tambah Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Profile <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="fieldName" class="form-control" required placeholder="PPPoE 10M">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rate Limit</label>
                        <input type="text" name="rate_limit" id="fieldRateLimit" class="form-control" placeholder="10M/10M">
                        <small class="text-muted">Format Mikrotik: upload/download (contoh: 10M/10M, 512k/2M)</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Session Timeout (detik)</label>
                            <input type="number" name="session_timeout" id="fieldTimeout" class="form-control" placeholder="0 = tanpa batas" min="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Harga (Rp)</label>
                            <input type="number" name="price" id="fieldPrice" class="form-control" value="0" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <input type="text" name="comment" id="fieldComment" class="form-control" placeholder="Keterangan" maxlength="100">
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
function resetForm() {
    document.getElementById('profileForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-layer-group"></i> Tambah Profile';
}

function editProfile(id, name, rateLimit, timeout, price, comment) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = id;
    document.getElementById('fieldName').value = name;
    document.getElementById('fieldRateLimit').value = rateLimit;
    document.getElementById('fieldTimeout').value = timeout || '';
    document.getElementById('fieldPrice').value = price;
    document.getElementById('fieldComment').value = comment;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-layer-group"></i> Edit Profile #' + id;
}
</script>

<?php require_once 'includes/footer.php'; ?>
