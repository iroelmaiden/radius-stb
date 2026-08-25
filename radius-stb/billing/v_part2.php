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
                    <tr class="gh table-secondary" onclick="toggleGroup('g<?= $grp['dg'] ?>')">
                        <td colspan="9"><i class="fas fa-chevron-down" id="ig<?= $grp['dg'] ?>"></i> <strong><?= date('d M Y', strtotime($grp['dg'])) ?></strong> (<?= $grp['cnt'] ?> voucher)</td>
                    </tr>
                    <?php
                    $vr = $conn->query("SELECT v.*, p.name as pkg_name, p.price FROM vouchers v JOIN voucher_packages p ON v.package_id = p.id WHERE DATE(v.created_at) = '" . $grp['dg'] . "' " . ($filter_status ? "AND v.status = '" . $conn->real_escape_string($filter_status) . "'" : "") . " ORDER BY v.created_at DESC");
                    while ($v = $vr->fetch_assoc()):
                    ?>
                    <tr class="vr gv g<?= $grp['dg'] ?>">
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
                    <div class="modal fade" id="sell<?= $v['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <form method="POST"><input type="hidden" name="action" value="sell"><input type="hidden" name="voucher_id" value="<?= $v['id'] ?>">
                            <div class="modal-header"><h5 class="modal-title">Jual Voucher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="alert alert-info"><strong>Kode:</strong> <?= $v['code'] ?><br><strong>Paket:</strong> <?= $v['pkg_name'] ?><br><strong>Harga:</strong> <?= formatRupiah($v['price']) ?></div>
                                <div class="mb-3"><label class="form-label">Nama Pembeli</label><input type="text" class="form-control" name="buyer_name" required></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Jual</button></div>
                        </form>
                    </div></div></div>
                    <?php endwhile; ?>
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
