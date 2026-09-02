<?php

define('BITLEARN_API', true);

// Tangkap fatal/exception agar Android selalu dapat JSON, bukan body kosong
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    // Ubah warning/notice menjadi exception agar tertangkap handler di atas
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-App-Version-Code, X-App-Update-Managed');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/CourseStudents.php';
require_once __DIR__ . '/../core/LessonSettings.php';
require_once __DIR__ . '/../core/AppVersion.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/StudentAccess.php';

if (empty($db_valid) || !isset($conn) || $conn->connect_error) {
    ApiResponse::error('Database belum siap. Periksa core/config.php di hosting.', 503);
}

try {
    CourseStudents::ensureExclusionsTable($conn);
} catch (Throwable $e) {
    // non-fatal: akses tetap jalan tanpa exclusion jika CREATE gagal
}

try {
    LessonSettings::ensureDwellColumn($conn);
} catch (Throwable $e) {
    // non-fatal
}

try {
    AppVersion::ensureTable($conn);
} catch (Throwable $e) {
    // non-fatal
}

// Matikan exception mysqli default agar kita kontrol sendiri (PHP 8.1+)
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

try {
    ApiAuth::ensureTokensTable($conn);
} catch (Throwable $e) {
    ApiResponse::error('Gagal menyiapkan autentikasi API: ' . $e->getMessage(), 500);
}

function api_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_input(): array
{
    $json = api_json_body();
    if (!empty($json)) {
        return array_merge($_POST, $json);
    }
    return $_POST;
}
