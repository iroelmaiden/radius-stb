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
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
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
