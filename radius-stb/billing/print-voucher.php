<?php
require_once 'config.php';

$ids = isset($_GET['ids']) ? $_GET['ids'] : '';
if (empty($ids)) {
    die('No voucher IDs provided.');
}

$idArr = array_map('intval', explode(',', $ids));
$placeholders = implode(',', $idArr);

$conn = db();
$result = $conn->query("SELECT v.*, p.name AS package_name
    FROM vouchers v
    LEFT JOIN voucher_packages p ON v.package_id = p.id
    WHERE v.id IN ($placeholders)
    ORDER BY v.id");

$vouchers = [];
while ($row = $result->fetch_assoc()) {
    $vouchers[] = $row;
}

$hotspotname = 'HOTSPOT';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Voucher</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 10px; }
        .voucher-card {
            width: 180px;
            border: 2px solid #000;
            padding: 8px;
            margin: 4px;
            display: inline-block;
            vertical-align: top;
            page-break-inside: avoid;
        }
        .voucher-header {
            font-size: 14px;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .voucher-header .name { float: left; }
        .voucher-header .num { float: right; }
        .voucher-label {
            text-align: center;
            font-size: 11px;
            margin-bottom: 4px;
        }
        .voucher-code {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            border: 1px solid #000;
            padding: 4px 0;
            margin-bottom: 4px;
        }
        .voucher-info {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            border: 1px solid #000;
            padding: 3px 0;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .voucher-card { margin: 2px; }
        }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:10px;">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>
    <span style="margin-left:10px;"><?= count($vouchers) ?> voucher(s)</span>
</div>

<?php foreach ($vouchers as $i => $v): ?>
    <?php
    $num = $i + 1;
    $username = $v['username'];
    $price = formatRupiah($v['price']);
    $timelimit = $v['time_limit'] ?? '-';
    ?>
    <div class="voucher-card">
        <div class="voucher-header">
            <span class="name"><?= htmlspecialchars($hotspotname) ?></span>
            <span class="num">[<?= $num ?>]</span>
        </div>
        <div class="voucher-label">Kode Voucher</div>
        <div class="voucher-code"><?= htmlspecialchars($username) ?></div>
        <div class="voucher-info"><?= htmlspecialchars($timelimit) ?> <?= $price ?></div>
    </div>
<?php endforeach; ?>

</body>
</html>
