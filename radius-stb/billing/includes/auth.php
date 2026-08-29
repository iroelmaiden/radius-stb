<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $conn = db();
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, role, status FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result->fetch_assoc();
}

function currentUserRole() {
    $user = currentUser();
    return $user ? $user['role'] : null;
}

function hasPermission($permission) {
    $role = currentUserRole();
    if (!$role) return false;
    if ($role === 'admin') return true; // admin has all permissions
    
    $conn = db();
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM role_permissions WHERE role = ? AND permission = ?");
    $stmt->bind_param('ss', $role, $permission);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['cnt'] > 0;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requirePermission($permission) {
    requireLogin();
    if (!hasPermission($permission)) {
        $_SESSION['flash_error'] = 'Anda tidak memiliki akses ke halaman ini.';
        header('Location: index.php');
        exit;
    }
}

function loginUser($username, $password) {
    $conn = db();
    $stmt = $conn->prepare("SELECT id, username, password, nama_lengkap, role, status FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $user = $result->fetch_assoc();
    if (!$user) {
        return ['ok' => false, 'msg' => 'Username tidak ditemukan'];
    }
    
    if ($user['status'] !== 'active') {
        return ['ok' => false, 'msg' => 'Akun sudah dinonaktifkan'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['ok' => false, 'msg' => 'Password salah'];
    }
    
    // Update last_login
    $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];
    
    return ['ok' => true, 'user' => $user];
}

function logoutUser() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function getRoleBadge($role) {
    $colors = [
        'admin' => 'danger',
        'teknisi' => 'warning',
        'operator' => 'info',
    ];
    $color = $colors[$role] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($role) . '</span>';
}
