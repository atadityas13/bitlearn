<?php

define('BITLEARN_API', true);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/StudentAccess.php';

if (empty($db_valid) || !isset($conn) || $conn->connect_error) {
    ApiResponse::error('Database belum siap. Jalankan install.php terlebih dahulu.', 503);
}

ApiAuth::ensureTokensTable($conn);

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
