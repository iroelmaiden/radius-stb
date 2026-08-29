<?php
require_once 'config.php';
require_once 'includes/auth.php';
requirePermission('users');

$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role = $_POST['role'] ?? 'operator';

        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            setFlash('Semua field wajib diisi.', 'danger');
            header('Location: users.php');
            exit;
        }

        if (strlen($password) < 6) {
            setFlash('Password minimal 6 karakter.', 'danger');
            header('Location: users.php');
            exit;
        }

        // Check unique username
        $check = $conn->query("SELECT id FROM users WHERE username='" . $conn->real_escape_string($username) . "'");
        if ($check->num_rows > 0) {
            setFlash('Username sudah digunakan.', 'danger');
            header('Location: users.php');
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $escU = $conn->real_escape_string($username);
        $escN = $conn->real_escape_string($nama_lengkap);
        $escR = $conn->real_escape_string($role);

        $conn->query("INSERT INTO users (username, password, nama_lengkap, role, status) VALUES ('$escU', '$hash', '$escN', '$escR', 'active')");

        setFlash("User '$username' berhasil ditambahkan.", 'success');
        header('Location: users.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role = $_POST['role'] ?? 'operator';
        $status = $_POST['status'] ?? 'active';

        if (empty($nama_lengkap)) {
            setFlash('Nama lengkap wajib diisi.', 'danger');
            header('Location: users.php');
            exit;
        }

        // Cannot change own role/status
        if ($id == $_SESSION['user_id']) {
            if ($role !== $_SESSION['role']) {
                setFlash('Tidak bisa mengubah role sendiri.', 'danger');
                header('Location: users.php');
                exit;
            }
            if ($status !== 'active') {
                setFlash('Tidak bisa menonaktifkan akun sendiri.', 'danger');
                header('Location: users.php');
                exit;
            }
        }

        $escN = $conn->real_escape_string($nama_lengkap);
        $escR = $conn->real_escape_string($role);
        $escS = $conn->real_escape_string($status);

        $conn->query("UPDATE users SET nama_lengkap='$escN', role='$escR', status='$escS' WHERE id=$id");

        setFlash('User berhasil diupdate.', 'success');
        header('Location: users.php');
        exit;
    }

    if ($action === 'change_password') {
        $id = intval($_POST['id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword)) {
            setFlash('Password baru wajib diisi.', 'danger');
            header('Location: users.php');
            exit;
        }

        if (strlen($newPassword) < 6) {
            setFlash('Password minimal 6 karakter.', 'danger');
            header('Location: users.php');
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            setFlash('Konfirmasi password tidak cocok.', 'danger');
            header('Location: users.php');
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$id");

        setFlash('Password berhasil diubah.', 'success');
        header('Location: users.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if ($id == $_SESSION['user_id']) {
            setFlash('Tidak bisa menghapus akun sendiri.', 'danger');
            header('Location: users.php');
            exit;
        }

        $conn->query("DELETE FROM users WHERE id=$id");

        setFlash('User berhasil dihapus.', 'success');
        header('Location: users.php');
        exit;
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY role ASC, username ASC")->fetch_all(MYSQLI_ASSOC);
$currentUser = currentUser();

require_once 'includes/header.php';
?>

<h4 class="mb-4"><i class="fas fa-users-cog"></i> Manajemen User</h4>

<div class="row mb-3">
    <div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Tambah User
        </button>
    </div>
    <div class="col-md-6 text-end">
        <small class="text-muted">
            <i class="fas fa-info-circle"></i>
            Role: <span class="badge bg-danger">Admin</span> Full Access |
            <span class="badge bg-warning">Teknisi</span> Tanpa Billing Settings & User Management |
            <span class="badge bg-info">Operator</span> Voucher & Billing Saja
        </small>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th width="50">ID</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th width="200">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada user</td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><code class="fw-bold"><?= htmlspecialchars($u['username']) ?></code></td>
                    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                    <td><?= getRoleBadge($u['role']) ?></td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-' ?></small></td>
                    <td>
                        <?php if ($u['id'] != $currentUser['id']): ?>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"
                            onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= htmlspecialchars(addslashes($u['nama_lengkap'])) ?>', '<?= $u['role'] ?>', '<?= $u['status'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#pwModal"
                            onclick="changePw(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                            <i class="fas fa-key"></i>
                        </button>
                        
                        <?php if ($u['id'] != $currentUser['id']): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                        </form>
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

<!-- Add User Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="username untuk login">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control" required placeholder="nama lengkap">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="operator">Operator</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="admin">Admin</option>
                        </select>
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

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="editUsername" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" id="editNama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="editRole" class="form-select" required>
                            <option value="operator">Operator</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="editStatus" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
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

<!-- Change Password Modal -->
<div class="modal fade" id="pwModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="id" id="pwId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Ganti Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <input type="text" id="pwUsername" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="ulangi password baru">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Ganti Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(id, username, nama, role, status) {
    document.getElementById('editId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editNama').value = nama;
    document.getElementById('editRole').value = role;
    document.getElementById('editStatus').value = status;
}
function changePw(id, username) {
    document.getElementById('pwId').value = id;
    document.getElementById('pwUsername').value = username;
}
</script>

<?php require_once 'includes/footer.php'; ?>
