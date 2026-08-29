<?php
require_once 'config.php';
require_once 'includes/auth.php';
requireLogin();

$conn = db();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        setFlash('Semua field wajib diisi.', 'danger');
        header('Location: change-password.php');
        exit;
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($currentPassword, $row['password'])) {
        setFlash('Password lama salah.', 'danger');
        header('Location: change-password.php');
        exit;
    }

    if (strlen($newPassword) < 6) {
        setFlash('Password baru minimal 6 karakter.', 'danger');
        header('Location: change-password.php');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        setFlash('Konfirmasi password tidak cocok.', 'danger');
        header('Location: change-password.php');
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$hash' WHERE id={$user['id']}");

    setFlash('Password berhasil diubah.', 'success');
    header('Location: change-password.php');
    exit;
}

require_once 'includes/header.php';
?>

<h4 class="mb-4"><i class="fas fa-key"></i> Ganti Password</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-warning">
                <i class="fas fa-user"></i> <?= htmlspecialchars($user['nama_lengkap']) ?> (<?= ucfirst($user['role']) ?>)
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Ganti Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
