<?php
require_once '../core/config.php';
require_once '../core/AppVersion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/manage_app_update.php');
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];
$data = [
    'latest_version_code' => $_POST['latest_version_code'] ?? 1,
    'latest_version_name' => $_POST['latest_version_name'] ?? '1.0.0',
    'min_version_code' => $_POST['min_version_code'] ?? 1,
    'force_update' => isset($_POST['force_update']) ? 1 : 0,
    'update_url' => $_POST['update_url'] ?? AppVersion::DEFAULT_PLAY_URL,
    'release_notes' => $_POST['release_notes'] ?? '',
];

if (AppVersion::saveConfig($conn, $data, $teacher_id, 'android')) {
    $_SESSION['success'] = 'Pengaturan update aplikasi berhasil disimpan.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan pengaturan update aplikasi.';
}

header('Location: ../pages/manage_app_update.php');
exit;
