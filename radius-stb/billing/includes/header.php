<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            position: fixed;
            top: 0;
            left: 0;
            width: 220px;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 10px 20px; display: block; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: #fff; }
        .sidebar .brand { padding: 20px; font-size: 1.2em; font-weight: bold; color: #3498db; border-bottom: 1px solid #34495e; }
        .sidebar .menu-header { color: #95a5a6; font-size: 11px; text-transform: uppercase; padding: 12px 20px 4px; letter-spacing: 1px; }
        .sidebar .sub-menu a { padding-left: 40px; font-size: 13px; }
        .content { padding: 20px; }
        .content-wrapper { margin-left: 220px; transition: margin-left 0.3s ease; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-card { border-left: 4px solid; }
        .stat-card.blue { border-left-color: #3498db; }
        .stat-card.green { border-left-color: #27ae60; }
        .stat-card.orange { border-left-color: #f39c12; }
        .stat-card.red { border-left-color: #e74c3c; }
        .stat-card.yellow { border-left-color: #f1c40f; }
        .table th { background: #f8f9fa; }
        .btn-action { margin: 2px; }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .sidebar-overlay.show { display: block; }

        .topbar {
            display: none;
            background: #2c3e50;
            color: #fff;
            padding: 10px 15px;
            position: sticky;
            top: 0;
            z-index: 998;
        }
        .topbar .brand-text { font-weight: bold; font-size: 1.1em; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 240px; }
            .sidebar.show { transform: translateX(0); }
            .content-wrapper { margin-left: 0 !important; padding: 10px !important; }
            .topbar { display: flex; align-items: center; justify-content: space-between; }
            .btn-hamburger { background: none; border: none; color: #fff; font-size: 1.3em; cursor: pointer; padding: 5px; }
            .stat-card { border-left-width: 3px; }
            .table-responsive { font-size: 13px; }
            .modal-dialog { margin: 10px; }
            .d-flex.gap-2 { flex-wrap: wrap; }
            .form-control-sm, .form-select-sm { font-size: 14px; }
        }

        @media print {
            .sidebar, .topbar, .sidebar-overlay, .no-print { display: none !important; }
            .content-wrapper { margin-left: 0 !important; padding: 10px !important; }
        }
    </style>
</head>
<body>
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="topbar">
        <button class="btn-hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <span class="brand-text"><i class="fas fa-wifi"></i> <?= APP_NAME ?></span>
        <span></span>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="brand">
            <i class="fas fa-wifi"></i> <?= APP_NAME ?>
        </div>
        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>

        <div class="menu-header">HOTSPOT</div>
        <a href="packages.php" class="sub-menu <?= $currentPage == 'packages.php' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> Profile
        </a>
        <a href="vouchers.php" class="sub-menu <?= $currentPage == 'vouchers.php' ? 'active' : '' ?>">
            <i class="fas fa-ticket-alt"></i> Voucher
        </a>
        <a href="hotspot-users.php" class="sub-menu <?= $currentPage == 'hotspot-users.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i> User Hotspot
        </a>

        <div class="menu-header">PPP</div>
        <a href="pppoe-profiles.php" class="sub-menu <?= $currentPage == 'pppoe-profiles.php' ? 'active' : '' ?>">
            <i class="fas fa-sliders-h"></i> Profile
        </a>
        <a href="pppoe.php" class="sub-menu <?= $currentPage == 'pppoe.php' ? 'active' : '' ?>">
            <i class="fas fa-network-wired"></i> User
        </a>
        <a href="billing.php" class="sub-menu <?= $currentPage == 'billing.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Billing
        </a>

        <div class="menu-header">MONITORING</div>
        <a href="users.php" class="sub-menu <?= $currentPage == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> User Aktif
        </a>

        <div class="menu-header">LAINNYA</div>
        <a href="reports.php" class="sub-menu <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Laporan
        </a>
        <a href="settings.php" class="sub-menu <?= $currentPage == 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>

    <div class="content-wrapper">
        <div class="content">
