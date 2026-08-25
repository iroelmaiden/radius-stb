<?php
require_once 'config.php';

$conn = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $conn->real_escape_string($_POST['name']);
        $price = (int)$_POST['price'];
        $duration = (int)$_POST['duration_hours'];
        $validity = (int)$_POST['validity_days'];
        $rate_limit = $conn->real_escape_string($_POST['rate_limit']);
        $session_timeout = $duration * 3600;
        
        $sql = "INSERT INTO voucher_packages (name, price, duration_hours, validity_days, rate_limit, session_timeout) 
                VALUES ('$name', $price, $duration, $validity, '$rate_limit', $session_timeout)";
        
        if ($conn->query($sql)) {
            setFlash('Paket berhasil ditambahkan!');
        } else {
            setFlash('Gagal menambahkan paket: ' . $conn->error, 'danger');
        }
        header('Location: packages.php');
        exit;
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $price = (int)$_POST['price'];
        $duration = (int)$_POST['duration_hours'];
        $validity = (int)$_POST['validity_days'];
        $rate_limit = $conn->real_escape_string($_POST['rate_limit']);
        $session_timeout = $duration * 3600;
        
        $sql = "UPDATE voucher_packages SET 
                name='$name', price=$price, duration_hours=$duration, 
                validity_days=$validity, rate_limit='$rate_limit', session_timeout=$session_timeout 
                WHERE id=$id";
        
        if ($conn->query($sql)) {
            setFlash('Paket berhasil diupdate!');
        } else {
            setFlash('Gagal update paket: ' . $conn->error, 'danger');
        }
        header('Location: packages.php');
        exit;
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if package has vouchers
        $check = $conn->query("SELECT COUNT(*) as cnt FROM vouchers WHERE package_id = $id");
        $row = $check->fetch_assoc();
        
        if ($row['cnt'] > 0) {
            setFlash('Paket tidak bisa dihapus karena masih memiliki voucher!', 'warning');
        } else {
            $sql = "DELETE FROM voucher_packages WHERE id = $id";
            if ($conn->query($sql)) {
                setFlash('Paket berhasil dihapus!');
            } else {
                setFlash('Gagal menghapus paket: ' . $conn->error, 'danger');
            }
        }
        header('Location: packages.php');
        exit;
    }
}

// Get all packages
$packages = $conn->query("SELECT * FROM voucher_packages ORDER BY duration_hours");

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fas fa-box"></i> Manajemen Paket</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fas fa-plus"></i> Tambah Paket
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Paket</th>
                        <th>Harga</th>
                        <th>Durasi</th>
                        <th>Masa Aktif</th>
                        <th>Rate Limit</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($pkg = $packages->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($pkg['name']) ?></strong></td>
                        <td><?= formatRupiah($pkg['price']) ?></td>
                        <td><?= $pkg['duration_hours'] ?> jam</td>
                        <td><?= $pkg['validity_days'] ?? 7 ?> hari</td>
                        <td><code><?= htmlspecialchars($pkg['rate_limit']) ?></code></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $pkg['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus paket <?= htmlspecialchars($pkg['name']) ?>?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $pkg['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= $pkg['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Paket</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Paket</label>
                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($pkg['name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Harga (Rp)</label>
                                            <input type="number" class="form-control" name="price" value="<?= $pkg['price'] ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Durasi (jam)</label>
                                            <input type="number" class="form-control" name="duration_hours" value="<?= $pkg['duration_hours'] ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Masa Aktif (hari)</label>
                                            <input type="number" class="form-control" name="validity_days" value="<?= $pkg['validity_days'] ?? 7 ?>" required>
                                            <small class="text-muted">Berapa hari voucher berlaku dari tanggal pembuatan</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Rate Limit (Mikrotik-Rate-Limit)</label>
                                            <input type="text" class="form-control" name="rate_limit" value="<?= htmlspecialchars($pkg['rate_limit']) ?>" required>
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
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Paket Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Paket</label>
                        <input type="text" class="form-control" name="name" placeholder="contoh: 1 Hari" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" class="form-control" name="price" placeholder="5000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Durasi (jam)</label>
                        <input type="number" class="form-control" name="duration_hours" placeholder="24" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Masa Aktif (hari)</label>
                        <input type="number" class="form-control" name="validity_days" placeholder="7" value="7" required>
                        <small class="text-muted">Berapa hari voucher berlaku dari tanggal pembuatan</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rate Limit (Mikrotik-Rate-Limit)</label>
                        <input type="text" class="form-control" name="rate_limit" value="3M/4M 5M/5M 2250K/3M 27/20 8 375K/500K" required>
                        <small class="text-muted">Format: upload/download burst-limit burst-threshold burst-rate priority</small>
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

<?php require_once 'includes/footer.php'; ?>
