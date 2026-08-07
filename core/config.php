<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root'; // default xampp user
$db_pass = '';     // default xampp pass is empty
$db_name = 'bitlearn_db';

// Dynamically determine BASE_URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$app_dir = str_replace('\\', '/', dirname(__DIR__));
$base_path = str_replace($doc_root, '', $app_dir);

// Handle edge cases where str_replace doesn't work perfectly due to symlinks/casing
if (strpos($app_dir, $doc_root) === false && isset($_SERVER['SCRIPT_NAME'])) {
     $script_dir = dirname($_SERVER['SCRIPT_NAME']);
     // Approximate base path by removing subdirectories if we are in one
     $script_file = basename($_SERVER['PHP_SELF']);
     if ($script_file === 'install.php' || $script_file === 'index.php') {
         $base_path = rtrim($script_dir, '/');
     } else {
         $base_path = rtrim(dirname($script_dir), '/'); // Works for 1 level deep like /pages
     }
}

define('BASE_URL', $protocol . '://' . $host . $base_path);

$is_install_script = (basename($_SERVER['PHP_SELF']) === 'install.php');
$is_api_request = defined('BITLEARN_API') && BITLEARN_API === true;

// Create connection (silencing warnings so installer can handle errors gracefully)
$conn = @new mysqli($db_host, $db_user, $db_pass);

$db_valid = false;
if (!$conn->connect_error && $conn->select_db($db_name)) {
    // Additionally ensure our main table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'users'");
    if ($check_table && $check_table->num_rows > 0) {
        $db_valid = true;
    }
}

// Redirect to install or gracefully die if db connection fails
if (!$db_valid) {
    if ($is_api_request) {
        // API bootstrap will return JSON error
    } elseif (!$is_install_script) {
        if (file_exists(__DIR__ . '/../install.php')) {
            header("Location: " . BASE_URL . "/install.php");
            exit;
        } else {
            die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>Kesalahan Server: Gagal terhubung ke database. Harap cek <b>core/config.php</b>.</div>");
        }
    }
} else {
    $conn->set_charset("utf8mb4");
}
?>
