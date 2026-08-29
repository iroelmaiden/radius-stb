<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
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
            height: 100vh;
            background: #2c3e50;
            position: fixed;
            top: 0;
            left: 0;
            width: 220px;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            transition: width 0.3s ease, transform 0.3s ease;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #34495e; border-radius: 4px; }
        .sidebar::-webkit-scrollbar-track { background: #2c3e50; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 10px 20px; display: block; font-size: 14px; white-space: nowrap; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; color: #fff; }
        .sidebar .brand { padding: 20px; font-size: 1.2em; font-weight: bold; color: #3498db; border-bottom: 1px solid #34495e; display: flex; align-items: center; justify-content: space-between; }
        .sidebar .brand .btn-minimize { background: none; border: none; color: #95a5a6; cursor: pointer; padding: 2px 6px; font-size: 12px; }
        .sidebar .brand .btn-minimize:hover { color: #fff; }
        .sidebar .menu-group { border-bottom: 1px solid #34495e; }
        .sidebar .menu-header { color: #95a5a6; font-size: 11px; text-transform: uppercase; padding: 12px 20px 4px; letter-spacing: 1px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; }
        .sidebar .menu-header:hover { color: #ecf0f1; }
        .sidebar .menu-header .toggle-icon { transition: transform 0.2s; font-size: 10px; }
        .sidebar .menu-header.collapsed .toggle-icon { transform: rotate(-90deg); }
        .sidebar .menu-items { overflow: hidden; transition: max-height 0.3s ease; }
        .sidebar .menu-items.collapsed { max-height: 0 !important; }
        .sidebar .sub-menu a { padding-left: 40px; font-size: 13px; }
        .sidebar .user-info { padding: 15px 20px; background: #1a252f; border-bottom: 1px solid #34495e; }
        .sidebar .user-info .name { color: #fff; font-weight: bold; font-size: 14px; }
        .sidebar .user-info .role { color: #95a5a6; font-size: 12px; }
        .sidebar .user-info a { padding: 5px 0; font-size: 12px; }
        .content { padding: 20px; }
        .content-wrapper { margin-left: 220px; transition: margin-left 0.3s ease; }
        .sidebar.collapsed { width: 60px; }
        .sidebar.collapsed .brand span,
        .sidebar.collapsed .user-info,
        .sidebar.collapsed .menu-header span,
        .sidebar.collapsed .menu-header .toggle-icon,
        .sidebar.collapsed .sub-menu a span,
        .sidebar.collapsed a span { display: none; }
        .sidebar.collapsed .brand { padding: 20px 15px; justify-content: center; }
        .sidebar.collapsed a { padding: 12px; text-align: center; font-size: 16px; }
        .sidebar.collapsed .sub-menu a { padding-left: 12px; font-size: 16px; }
        .sidebar.collapsed .menu-header { padding: 12px; justify-content: center; }
        .content-wrapper.expanded { margin-left: 60px; }
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
            .sidebar.collapsed { width: 240px; }
            .sidebar.collapsed .brand span,
            .sidebar.collapsed .user-info,
            .sidebar.collapsed .menu-header span,
            .sidebar.collapsed .menu-header .toggle-icon,
            .sidebar.collapsed .sub-menu a span,
            .sidebar.collapsed a span { display: block; }
            .sidebar.collapsed .brand { padding: 20px; justify-content: space-between; }
            .sidebar.collapsed a { padding: 10px 20px; text-align: left; font-size: 14px; }
            .sidebar.collapsed .sub-menu a { padding-left: 40px; font-size: 13px; }
            .sidebar.collapsed .menu-header { padding: 12px 20px 4px; justify-content: space-between; }
            .content-wrapper { margin-left: 0 !important; padding: 10px !important; }
            .content-wrapper.expanded { margin-left: 0; }
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
            <span><i class="fas fa-wifi"></i> <?= APP_NAME ?></span>
            <button class="btn-minimize" onclick="toggleSidebarMinimize()" title="Minimize">
                <i class="fas fa-chevron-left" id="minimizeIcon"></i>
            </button>
        </div>
        
        <div class="user-info">
            <div class="name"><i class="fas fa-user"></i> <?= htmlspecialchars($currentUser['nama_lengkap']) ?></div>
            <div class="role"><?= getRoleBadge($currentUser['role']) ?></div>
            <a href="change-password.php" class="text-warning"><i class="fas fa-key"></i> Ganti Password</a>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>

        <?php if (hasPermission('packages') || hasPermission('vouchers') || hasPermission('hotspot_users')): ?>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleMenuGroup(this)">
                <span>HOTSPOT</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="menu-items">
                <?php if (hasPermission('packages')): ?>
                <a href="packages.php" class="sub-menu <?= $currentPage == 'packages.php' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i> <span>Profile</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('vouchers')): ?>
                <a href="vouchers.php" class="sub-menu <?= $currentPage == 'vouchers.php' ? 'active' : '' ?>">
                    <i class="fas fa-ticket-alt"></i> <span>Voucher</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('hotspot_users')): ?>
                <a href="hotspot-users.php" class="sub-menu <?= $currentPage == 'hotspot-users.php' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> <span>User Hotspot</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('pppoe_profiles') || hasPermission('pppoe_users') || hasPermission('billing') || hasPermission('billing_settings')): ?>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleMenuGroup(this)">
                <span>PPP</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="menu-items">
                <?php if (hasPermission('pppoe_profiles')): ?>
                <a href="pppoe-profiles.php" class="sub-menu <?= $currentPage == 'pppoe-profiles.php' ? 'active' : '' ?>">
                    <i class="fas fa-sliders-h"></i> <span>Profile</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('pppoe_users')): ?>
                <a href="pppoe.php" class="sub-menu <?= $currentPage == 'pppoe.php' ? 'active' : '' ?>">
                    <i class="fas fa-network-wired"></i> <span>User</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('billing')): ?>
                <a href="billing.php" class="sub-menu <?= $currentPage == 'billing.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i> <span>Billing</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('billing_settings')): ?>
                <a href="billing-settings.php" class="sub-menu <?= $currentPage == 'billing-settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span>Billing Setting</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('monitoring')): ?>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleMenuGroup(this)">
                <span>MONITORING</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="menu-items">
                <a href="active-users.php" class="sub-menu <?= $currentPage == 'active-users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <span>User Aktif</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission('reports') || hasPermission('settings') || hasPermission('users')): ?>
        <div class="menu-group">
            <div class="menu-header" onclick="toggleMenuGroup(this)">
                <span>LAINNYA</span>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="menu-items">
                <?php if (hasPermission('reports')): ?>
                <a href="reports.php" class="sub-menu <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> <span>Laporan</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('settings')): ?>
                <a href="settings.php" class="sub-menu <?= $currentPage == 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('users')): ?>
                <a href="users.php" class="sub-menu <?= $currentPage == 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> <span>Manajemen User</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="content-wrapper">
        <div class="content">
