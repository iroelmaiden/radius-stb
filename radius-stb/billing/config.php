<?php
// Load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        die("File .env tidak ditemukan. Copy .env.example ke .env lalu isi credential-nya.");
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load from .env in project root
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    $envFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/.env';
}
loadEnv($envFile);

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'radius');
define('DB_USER', getenv('DB_USER') ?: 'radius');
define('DB_PASS', getenv('DB_PASS'));

// Application settings
define('APP_NAME', getenv('APP_NAME') ?: 'Hotspot Billing');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8081');

// Database connection
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Koneksi database gagal: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}

// Format currency
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Start session and load helpers
session_start();
require_once __DIR__ . '/includes/radius_helper.php';
