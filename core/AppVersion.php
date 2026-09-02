<?php

/**
 * Konfigurasi versi aplikasi mobile (Android) untuk cek update.
 */
class AppVersion
{
    private static ?bool $tableReady = null;

    public const DEFAULT_PLAY_URL = 'https://play.google.com/store/apps/details?id=com.atadevlabs.bitlearn';

    public static function ensureTable(mysqli $conn): bool
    {
        if (self::$tableReady === true) {
            return true;
        }
        if (self::$tableReady === false) {
            return false;
        }

        $probe = @$conn->query('SELECT 1 FROM app_version_config LIMIT 1');
        if ($probe === false) {
            @$conn->query("
                CREATE TABLE IF NOT EXISTS app_version_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    platform VARCHAR(20) NOT NULL DEFAULT 'android',
                    latest_version_code INT NOT NULL DEFAULT 1,
                    latest_version_name VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                    min_version_code INT NOT NULL DEFAULT 1,
                    force_update TINYINT(1) NOT NULL DEFAULT 0,
                    update_url VARCHAR(500) DEFAULT NULL,
                    release_notes TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by INT DEFAULT NULL,
                    UNIQUE KEY uniq_platform (platform)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $probe = @$conn->query('SELECT 1 FROM app_version_config LIMIT 1');
            if ($probe === false) {
                self::$tableReady = false;
                return false;
            }
        }

        self::seedDefaults($conn);
        self::$tableReady = true;
        return true;
    }

    private static function seedDefaults(mysqli $conn): void
    {
        $res = $conn->query("SELECT id FROM app_version_config WHERE platform = 'android' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return;
        }
        $url = self::DEFAULT_PLAY_URL;
        $conn->query("
            INSERT INTO app_version_config
                (platform, latest_version_code, latest_version_name, min_version_code, force_update, update_url, release_notes)
            VALUES
                ('android', 3, '1.2', 1, 0, '$url', 'Versi awal manajemen update aplikasi.')
        ");
    }

    public static function getConfig(mysqli $conn, string $platform = 'android'): ?array
    {
        if (!self::ensureTable($conn)) {
            return null;
        }
        $platform = $conn->real_escape_string(strtolower(trim($platform)));
        $res = $conn->query("SELECT * FROM app_version_config WHERE platform = '$platform' LIMIT 1");
        if (!$res || $res->num_rows === 0) {
            return null;
        }
        return $res->fetch_assoc();
    }

    public static function readClientVersionCode(): int
    {
        $candidates = [
            $_SERVER['HTTP_X_APP_VERSION_CODE'] ?? null,
            $_SERVER['HTTP_X_APP_VERSION'] ?? null,
        ];
        foreach ($candidates as $v) {
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return max(0, (int)$v);
            }
        }
        if (isset($_GET['version_code']) && is_numeric($_GET['version_code'])) {
            return max(0, (int)$_GET['version_code']);
        }
        return 0;
    }

    /** App baru yang sudah punya manajemen update di splash. */
    public static function isManagedUpdateClient(): bool
    {
        $flag = $_SERVER['HTTP_X_APP_UPDATE_MANAGED'] ?? '';
        return in_array(strtolower(trim((string)$flag)), ['1', 'true', 'yes'], true);
    }

    /**
     * Fallback untuk app lama: blokir course jika belum versi terbaru admin.
     * App baru (X-App-Update-Managed) tidak memakai ini — update diurus splash.
     */
    public static function dashboardBlockNotice(mysqli $conn, int $clientCode): ?array
    {
        if (self::isManagedUpdateClient()) {
            return null;
        }

        $check = self::checkUpdate($conn, 'android', $clientCode);
        $latest = (int)$check['latest_version_code'];

        if ($clientCode > 0 && $clientCode >= $latest) {
            return null;
        }
        if ($clientCode <= 0 && $latest <= 1) {
            return null;
        }

        $url = trim((string)($check['update_url'] ?? self::DEFAULT_PLAY_URL));
        if ($url === '') {
            $url = self::DEFAULT_PLAY_URL;
        }

        return [
            'blocked' => true,
            'message' => 'Tidak dapat menampilkan course, silahkan update aplikasi ke versi terbaru di Play Store.' . "\n" . $url,
            'update_url' => $url,
            'latest_version_name' => $check['latest_version_name'],
            'latest_version_code' => $latest,
            'current_version_code' => $clientCode,
            'force_update' => true,
        ];
    }

    public static function saveConfig(mysqli $conn, array $data, int $teacherId, string $platform = 'android'): bool
    {
        if (!self::ensureTable($conn)) {
            return false;
        }

        $platformEsc = $conn->real_escape_string(strtolower(trim($platform)));
        $latestCode = max(1, (int)($data['latest_version_code'] ?? 1));
        $latestName = $conn->real_escape_string(trim((string)($data['latest_version_name'] ?? '1.0.0')));
        $minCode = max(1, (int)($data['min_version_code'] ?? 1));
        if ($minCode > $latestCode) {
            $minCode = $latestCode;
        }
        $force = !empty($data['force_update']) ? 1 : 0;
        $url = trim((string)($data['update_url'] ?? self::DEFAULT_PLAY_URL));
        if ($url === '') {
            $url = self::DEFAULT_PLAY_URL;
        }
        $urlEsc = $conn->real_escape_string($url);
        $notes = $conn->real_escape_string(trim((string)($data['release_notes'] ?? '')));

        $sql = "
            INSERT INTO app_version_config
                (platform, latest_version_code, latest_version_name, min_version_code, force_update, update_url, release_notes, updated_by)
            VALUES
                ('$platformEsc', $latestCode, '$latestName', $minCode, $force, '$urlEsc', '$notes', $teacherId)
            ON DUPLICATE KEY UPDATE
                latest_version_code = $latestCode,
                latest_version_name = '$latestName',
                min_version_code = $minCode,
                force_update = $force,
                update_url = '$urlEsc',
                release_notes = '$notes',
                updated_by = $teacherId
        ";
        return (bool)$conn->query($sql);
    }

    public static function checkUpdate(mysqli $conn, string $platform, int $currentCode): array
    {
        $currentCode = max(0, $currentCode);
        $cfg = self::getConfig($conn, $platform);

        if (!$cfg) {
            return [
                'platform' => $platform,
                'current_version_code' => $currentCode,
                'latest_version_code' => $currentCode,
                'latest_version_name' => (string)$currentCode,
                'min_version_code' => 1,
                'update_available' => false,
                'update_required' => false,
                'force_update' => false,
                'update_url' => self::DEFAULT_PLAY_URL,
                'release_notes' => '',
            ];
        }

        $latest = (int)$cfg['latest_version_code'];
        $min = (int)$cfg['min_version_code'];
        $forceFlag = (int)$cfg['force_update'] === 1;
        $url = trim((string)($cfg['update_url'] ?? ''));
        if ($url === '') {
            $url = self::DEFAULT_PLAY_URL;
        }

        $updateAvailable = $currentCode < $latest;
        $updateRequired = $currentCode < $min || ($forceFlag && $currentCode < $latest);

        return [
            'platform' => $platform,
            'current_version_code' => $currentCode,
            'latest_version_code' => $latest,
            'latest_version_name' => (string)$cfg['latest_version_name'],
            'min_version_code' => $min,
            'update_available' => $updateAvailable,
            'update_required' => $updateRequired,
            'force_update' => $updateRequired,
            'update_url' => $url,
            'release_notes' => (string)($cfg['release_notes'] ?? ''),
        ];
    }
}
