<?php
require_once 'config.php';
$conn = db();

function parseTimeLimit($str) {
    $str = strtolower(trim($str));
    if (empty($str)) return null;
    $total = 0;
    if (preg_match_all('/(\d+)\s*(w|d|h|m)/', $str, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $val = intval($m[1]);
            switch ($m[2]) {
                case 'w': $total += $val * 7 * 24 * 3600; break;
                case 'd': $total += $val * 24 * 3600; break;
                case 'h': $total += $val * 3600; break;
                case 'm': $total += $val * 60; break;
            }
        }
    }
    return $total > 0 ? $total : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate($_POST['_token'] ?? '')) {
        setFlash('danger', 'Invalid CSRF token');
        header('Location: vouchers.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $package_id = intval($_POST['package_id'] ?? 0);
        $quantity = max(1, min(100, intval($_POST['quantity'] ?? 10)));
        $char_count = max(4, min(16, intval($_POST['char_count'] ?? 8)));
        $char_type = $_POST['char_type'] ?? 'alphanum_upper';
        $user_eq_pass = isset($_POST['user_eq_pass']) ? 1 : 0;
        $time_limit_str = trim($_POST['time_limit'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $pkg = $conn->query("SELECT * FROM voucher_packages WHERE id = " . intval($package_id));
        if ($pkg->num_rows === 0) {
            setFlash('danger', 'Package not found.');
            header('Location: vouchers.php');
            exit;
        }
        $pkgData = $pkg->fetch_assoc();
        $rate_limit = $pkgData['rate_limit'] ?? '';
        $validity_days = intval($pkgData['validity_days'] ?? 7);

        $time_limit_seconds = parseTimeLimit($time_limit_str);
        $validity_seconds = $validity_days * 24 * 3600;

        if ($time_limit_str && $time_limit_seconds === null) {
            setFlash('danger', 'Format Time Limit salah. Gunakan format: 30d, 12h, 4w3d, dll.');
            header('Location: vouchers.php');
            exit;
        }
        if ($time_limit_seconds && $time_limit_seconds >= $validity_seconds) {
            setFlash('danger', "Time Limit ($time_limit_str) harus kurang dari masa aktif paket ($validity_days hari).");
            header('Location: vouchers.php');
            exit;
        }

        $generated = 0;
        $conn->begin_transaction();
        try {
            for ($i = 0; $i < $quantity; $i++) {
                $code = generateCustomCode($char_count, $char_type);
                $username = $user_eq_pass ? strtolower($code) : strtolower($code);
                $password = $user_eq_pass ? strtolower($code) : $code;
                $price = floatval($pkgData['price'] ?? 0);
                $now = date('Y-m-d H:i:s');
                $valid_until = date('Y-m-d H:i:s', strtotime("+$validity_days days"));

                $stmt = $conn->prepare("INSERT INTO vouchers (code, username, password, package_id, price, status, created_at, valid_until, duration_hours, time_limit, comment) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssidssdss', $code, $username, $password, $package_id, $price, $now, $valid_until, $pkgData['duration_hours'], $time_limit_str, $comment);

                if ($stmt->execute()) {
                    radiusSetPassword($conn, $username, $password);
                    if ($rate_limit) {
                        radiusSetRateLimit($conn, $username, $rate_limit);
                    }
                    radiusSetGroup($conn, $username, 'hotspot');
                    $generated++;
                }
                $stmt->close();
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('danger', 'Error generating vouchers: ' . $e->getMessage());
            header('Location: vouchers.php');
            exit;
        }

        setFlash('success', "$generated vouchers generated successfully.");
        header('Location: vouchers.php');
        exit;
    }

    if ($action === 'sell') {
        $voucher_id = intval($_POST['voucher_id'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $buyer_name = trim($_POST['buyer_name'] ?? '');
        $buyer_contact = trim($_POST['buyer_contact'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'cash';

        $stmt = $conn->prepare("SELECT v.*, p.name as package_name FROM vouchers v JOIN voucher_packages p ON v.package_id = p.id WHERE v.id = ?");
        $stmt->bind_param('i', $voucher_id);
        $stmt->execute();
        $voucher = $stmt->get_result();
        $stmt->close();

        if ($voucher->num_rows === 0) {
            setFlash('danger', 'Voucher not found.');
            header('Location: vouchers.php');
            exit;
        }
        $vData = $voucher->fetch_assoc();

        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE vouchers SET status = 'sold', sold_at = ?, selling_price = ? WHERE id = ?");
        $stmt->bind_param('sdi', $now, $selling_price, $voucher_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO transactions (voucher_id, username, package_name, selling_price, buyer_name, buyer_contact, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issdssss', $voucher_id, $vData['username'], $vData['package_name'], $selling_price, $buyer_name, $buyer_contact, $payment_method, $now);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Voucher marked as sold.');
        header('Location: vouchers.php');
        exit;
    }

    if ($action === 'delete') {
        $voucher_id = intval($_POST['voucher_id'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM vouchers WHERE id = ?");
        $stmt->bind_param('i', $voucher_id);
        $stmt->execute();
        $voucher = $stmt->get_result();
        $stmt->close();

        if ($voucher->num_rows === 0) {
            setFlash('danger', 'Voucher not found.');
            header('Location: vouchers.php');
            exit;
        }
        $vData = $voucher->fetch_assoc();

        $conn->begin_transaction();
        try {
            radiusDeleteUser($conn, $vData['username']);
            $stmt = $conn->prepare("DELETE FROM transactions WHERE voucher_id = ?");
            $stmt->bind_param('i', $voucher_id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM vouchers WHERE id = ?");
            $stmt->bind_param('i', $voucher_id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
        }

        setFlash('success', 'Voucher deleted successfully.');
        header('Location: vouchers.php');
        exit;
    }

    if ($action === 'bulk_delete') {
        $voucher_ids = $_POST['voucher_ids'] ?? [];
        if (empty($voucher_ids)) {
            setFlash('warning', 'No vouchers selected.');
            header('Location: vouchers.php');
            exit;
        }

        $deleted = 0;
        $conn->begin_transaction();
        try {
            foreach ($voucher_ids as $vid) {
                $vid = intval($vid);
                $stmt = $conn->prepare("SELECT username FROM vouchers WHERE id = ?");
                $stmt->bind_param('i', $vid);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();

                if ($result->num_rows > 0) {
                    $vData = $result->fetch_assoc();
                    radiusDeleteUser($conn, $vData['username']);
                    $stmt = $conn->prepare("DELETE FROM transactions WHERE voucher_id = ?");
                    $stmt->bind_param('i', $vid);
                    $stmt->execute();
                    $stmt->close();
                    $stmt = $conn->prepare("DELETE FROM vouchers WHERE id = ?");
                    $stmt->bind_param('i', $vid);
                    $stmt->execute();
                    $stmt->close();
                    $deleted++;
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
        }

        setFlash('success', "$deleted vouchers deleted successfully.");
        header('Location: vouchers.php');
        exit;
    }
}

$filter_package = intval($_GET['package'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_search = $_GET['search'] ?? '';
$filter_comment = $_GET['comment'] ?? '';

$whereConditions = [];
$params = [];
$paramTypes = '';

if ($filter_package > 0) {
    $whereConditions[] = "v.package_id = ?";
    $params[] = $filter_package;
    $paramTypes .= 'i';
}
if ($filter_status && in_array($filter_status, ['available', 'used', 'expired'])) {
    $whereConditions[] = "v.status = ?";
    $params[] = $filter_status;
    $paramTypes .= 's';
}
if ($filter_date_from) {
    $whereConditions[] = "DATE(v.created_at) >= ?";
    $params[] = $filter_date_from;
    $paramTypes .= 's';
}
if ($filter_date_to) {
    $whereConditions[] = "DATE(v.created_at) <= ?";
    $params[] = $filter_date_to;
    $paramTypes .= 's';
}
if ($filter_search) {
    $whereConditions[] = "(v.code LIKE ? OR v.username LIKE ?)";
    $searchTerm = "%$filter_search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $paramTypes .= 'ss';
}
if ($filter_comment) {
    $whereConditions[] = "v.comment LIKE ?";
    $params[] = "%$filter_comment%";
    $paramTypes .= 's';
}

$whereSQL = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM vouchers v $whereSQL");
if ($paramTypes) $stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$usedWhere = $whereSQL ? "$whereSQL AND v.status = 'used'" : "WHERE v.status = 'used'";
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM vouchers v $usedWhere");
if ($paramTypes) $stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$used = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$expiredWhere = $whereSQL ? "$whereSQL AND v.status = 'expired'" : "WHERE v.status = 'expired'";
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM vouchers v $expiredWhere");
if ($paramTypes) $stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$expired = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$availableWhere = $whereSQL ? "$whereSQL AND v.status = 'available'" : "WHERE v.status = 'available'";
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM vouchers v $availableWhere");
if ($paramTypes) $stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$available = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$groupsQ = $conn->query("SELECT DATE(v.created_at) as dg, COUNT(*) as cnt FROM vouchers v $whereSQL GROUP BY DATE(v.created_at) ORDER BY dg DESC");
$packages = $conn->query("SELECT * FROM voucher_packages ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$flash = getFlash();
require_once 'includes/header.php';
?>

<div class="container-fluid py-3">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-6 col-md-2 mb-2">
            <div class="card stat-card total shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-ticket-alt text-primary me-2 fa-lg"></i>
                        <div>
                            <div class="text-muted small">Total</div>
                            <div class="fw-bold fs-5"><?= number_format($total) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card stat-card available shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-2 fa-lg"></i>
                        <div>
                            <div class="text-muted small">Available</div>
                            <div class="fw-bold fs-5"><?= number_format($available) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card stat-card shadow-sm" style="border-left-color: #0dcaf0;">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock text-info me-2 fa-lg"></i>
                        <div>
                            <div class="text-muted small">Used</div>
                            <div class="fw-bold fs-5"><?= number_format($used) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card stat-card sold shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-times-circle text-danger me-2 fa-lg"></i>
                        <div>
                            <div class="text-muted small">Expired</div>
                            <div class="fw-bold fs-5"><?= number_format($expired) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 mb-2">
            <div class="card stat-card selected shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-hand-pointer text-warning me-2 fa-lg"></i>
                        <div>
                            <div class="text-muted small">Selected</div>
                            <div class="fw-bold fs-5" id="selectedCount">0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small">Package</label>
                    <select name="package" class="form-select form-select-sm">
                        <option value="">All Packages</option>
                        <?php foreach ($packages as $pkg): ?>
                        <option value="<?= $pkg['id'] ?>" <?= $filter_package == $pkg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pkg['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="available" <?= $filter_status === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="used" <?= $filter_status === 'used' ? 'selected' : '' ?>>Used</option>
                        <option value="expired" <?= $filter_status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code/Username" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Comment</label>
                    <input type="text" name="comment" class="form-control form-control-sm" placeholder="Filter comment" value="<?= htmlspecialchars($filter_comment) ?>">
                </div>
                <div class="col-12 col-md-2 d-flex gap-1 mt-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter"></i> Filter</button>
                    <a href="vouchers.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2 no-print">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#generateModal">
                <i class="fas fa-plus"></i> Generate Voucher
            </button>
            <button type="button" class="btn btn-info btn-sm" onclick="printSelected()">
                <i class="fas fa-print"></i> Cetak Terpilih
            </button>
            <form method="POST" id="bulkDeleteForm" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="bulk_delete">
                <div id="bulkIdsContainer"></div>
                <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                    <i class="fas fa-trash"></i> Hapus Terpilih
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm no-print">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>Code</th>
                            <th>Package</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Time Limit</th>
                            <th>Comment</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Valid Until</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $groupIdx = 0;
                        $groupsQ->data_seek(0);
                        while ($group = $groupsQ->fetch_assoc()) {
                            $dg = $group['dg'];
                            $cnt = $group['cnt'];
                            $gid = 'group_' . $groupIdx++;
                            $dateStr = date('d M Y', strtotime($dg));

                            $vQ = $conn->query("SELECT v.*, p.name as package_name FROM vouchers v LEFT JOIN voucher_packages p ON v.package_id = p.id WHERE DATE(v.created_at) = '$dg' " . ($whereSQL ? str_replace('WHERE', 'AND', $whereSQL) : '') . " ORDER BY v.created_at DESC");
                        ?>
                        <tr class="group-header table-secondary" onclick="toggleGroup('<?= $gid ?>')">
                            <td colspan="12">
                                <i class="fas fa-chevron-down me-2" id="chevron_<?= $gid ?>"></i>
                                <strong><?= $dateStr ?></strong>
                                <span class="badge bg-secondary ms-2"><?= $cnt ?> vouchers</span>
                            </td>
                        </tr>
                        <?php while ($row = $vQ->fetch_assoc()): ?>
                        <tr class="voucher-row group-items_<?= $gid ?>">
                            <td>
                                <input type="checkbox" class="form-check-input voucher-cb" value="<?= $row['id'] ?>" data-code="<?= htmlspecialchars($row['code']) ?>" data-username="<?= htmlspecialchars($row['username']) ?>" data-package="<?= htmlspecialchars($row['package_name'] ?? '') ?>">
                            </td>
                            <td><code class="text-primary fw-bold"><?= htmlspecialchars($row['code']) ?></code></td>
                            <td><?= htmlspecialchars($row['package_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><code><?= htmlspecialchars($row['password']) ?></code></td>
                            <td><?= htmlspecialchars($row['time_limit'] ?? '-') ?></td>
                            <td><small><?= htmlspecialchars($row['comment'] ?? '-') ?></small></td>
                            <td><?= formatRupiah($row['price']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'available'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Available</span>
                                <?php elseif ($row['status'] === 'used'): ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-clock"></i> Used</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Expired</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                            <td>
                                <?php if ($row['valid_until']): ?>
                                    <?= date('d M Y', strtotime($row['valid_until'])) ?>
                                    <?php if ($row['status'] === 'available' && strtotime($row['valid_until']) < time()): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation"></i></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'available'): ?>
                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#sellModal" data-id="<?= $row['id'] ?>" data-code="<?= htmlspecialchars($row['code']) ?>" data-price="<?= $row['price'] ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirmDelete()">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="voucher_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center py-3 text-muted no-print">
        <small>Total: <?= number_format($total) ?> vouchers</small>
    </div>
</div>

<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="generate">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog"></i> Generate Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Package</label>
                        <select name="package_id" class="form-select" required id="pkgSelect">
                            <option value="">-- Select Package --</option>
                            <?php foreach ($packages as $pkg): ?>
                            <option value="<?= $pkg['id'] ?>" data-validity="<?= $pkg['validity_days'] ?>" data-duration="<?= $pkg['duration_hours'] ?>"><?= htmlspecialchars($pkg['name']) ?> - <?= formatRupiah($pkg['price']) ?> (<?= $pkg['duration_hours'] ?>h, aktif <?= $pkg['validity_days'] ?>hari)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="pkgInfo" class="alert alert-info py-2 mb-3" style="display:none;">
                        <small><i class="fas fa-info-circle"></i> Masa aktif: <strong id="pkgValidity"></strong> hari | Durasi internet: <strong id="pkgDuration"></strong> jam</small>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Quantity (1-100)</label>
                            <input type="number" name="quantity" class="form-control" value="10" min="1" max="100">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Char Count (4-16)</label>
                            <input type="number" name="char_count" class="form-control" value="8" min="4" max="16">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Character Type</label>
                        <select name="char_type" class="form-select">
                            <option value="alphanum_upper">Alphanumeric Upper</option>
                            <option value="alphanum_lower">Alphanumeric Lower</option>
                            <option value="alphanum_mixed">Alphanumeric Mixed</option>
                            <option value="alpha_upper">Alpha Upper</option>
                            <option value="alpha_lower">Alpha Lower</option>
                            <option value="alpha_mixed">Alpha Mixed</option>
                            <option value="num">Numeric</option>
                            <option value="hex">Hexadecimal</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="user_eq_pass" class="form-check-input" id="userEqPass" checked>
                        <label class="form-check-label" for="userEqPass">Username = Password</label>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Time Limit <small class="text-muted">(opsional)</small></label>
                            <input type="text" name="time_limit" class="form-control" placeholder="30d, 12h, 4w3d">
                            <small class="text-muted">w=minggu, d=hari, h=jam, m=menit.</small>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Comment <small class="text-muted">(opsional)</small></label>
                            <input type="text" name="comment" class="form-control" placeholder="Keterangan voucher" maxlength="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-cog"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="sellModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="sell">
                <input type="hidden" name="voucher_id" id="sellVoucherId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-shopping-cart"></i> Sell Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Voucher Code</label>
                        <input type="text" class="form-control" id="sellVoucherCode" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Selling Price (Rp)</label>
                        <input type="number" name="selling_price" class="form-control" id="sellPrice" required min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Name</label>
                        <input type="text" name="buyer_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Contact</label>
                        <input type="text" name="buyer_contact" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-check"></i> Sell</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleGroup(groupId) {
    var rows = document.querySelectorAll('.group-items_' + groupId);
    var chevron = document.getElementById('chevron_' + groupId);
    var isHidden = rows[0] && rows[0].style.display === 'none';
    rows.forEach(function(row) { row.style.display = isHidden ? '' : 'none'; });
    if (chevron) {
        chevron.classList.toggle('fa-chevron-down', !isHidden);
        chevron.classList.toggle('fa-chevron-right', isHidden);
    }
}

document.getElementById('selectAll').addEventListener('change', function() {
    var checked = this.checked;
    document.querySelectorAll('.voucher-cb').forEach(function(cb) {
        var row = cb.closest('tr');
        if (row && row.style.display !== 'none') cb.checked = checked;
    });
    updateSelectedCount();
});

document.querySelectorAll('.voucher-cb').forEach(function(cb) {
    cb.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    document.getElementById('selectedCount').textContent = document.querySelectorAll('.voucher-cb:checked').length;
}

function printSelected() {
    var selected = document.querySelectorAll('.voucher-cb:checked');
    if (selected.length === 0) { alert('Select at least one voucher.'); return; }
    var ids = [];
    selected.forEach(function(cb) { ids.push(cb.value); });
    window.open('print-voucher.php?ids=' + ids.join(','), '_blank');
}

function bulkDelete() {
    var selected = document.querySelectorAll('.voucher-cb:checked');
    if (selected.length === 0) { alert('Select at least one voucher.'); return; }
    if (!confirm('Delete ' + selected.length + ' selected voucher(s)?')) return;
    var container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    selected.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'voucher_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    document.getElementById('bulkDeleteForm').submit();
}

function confirmDelete() { return confirm('Delete this voucher?'); }

var sellModal = document.getElementById('sellModal');
if (sellModal) {
    sellModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        document.getElementById('sellVoucherId').value = button.getAttribute('data-id');
        document.getElementById('sellVoucherCode').value = button.getAttribute('data-code');
        document.getElementById('sellPrice').value = button.getAttribute('data-price');
    });
}

var pkgSelect = document.getElementById('pkgSelect');
if (pkgSelect) {
    pkgSelect.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        var info = document.getElementById('pkgInfo');
        if (this.value) {
            document.getElementById('pkgValidity').textContent = opt.getAttribute('data-validity');
            document.getElementById('pkgDuration').textContent = opt.getAttribute('data-duration');
            info.style.display = '';
        } else {
            info.style.display = 'none';
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
