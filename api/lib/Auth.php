<?php

class ApiAuth
{
    public static function ensureTokensTable(mysqli $conn): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        // Tanpa FOREIGN KEY: shared hosting / engine lama sering gagal & di PHP 8.1+ jadi exception
        $sql = "
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                device_name VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_token (token),
                KEY idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        try {
            if (!$conn->query($sql)) {
                throw new RuntimeException($conn->error ?: 'Gagal membuat tabel api_tokens');
            }
        } catch (Throwable $e) {
            // Coba lagi versi minimal
            $fallback = "
                CREATE TABLE IF NOT EXISTS api_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    device_name VARCHAR(100) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME DEFAULT NULL
                )
            ";
            try {
                if (!$conn->query($fallback)) {
                    throw new RuntimeException($conn->error ?: $e->getMessage());
                }
            } catch (Throwable $e2) {
                throw new RuntimeException('Gagal menyiapkan tabel api_tokens: ' . $e2->getMessage(), 0, $e2);
            }
        }

        $ready = true;
    }

    public static function createToken(mysqli $conn, int $userId, ?string $deviceName = null): string
    {
        self::ensureTokensTable($conn);
        $token = bin2hex(random_bytes(32));
        $tokenEsc = $conn->real_escape_string($token);
        $deviceEsc = $deviceName !== null ? "'" . $conn->real_escape_string($deviceName) . "'" : 'NULL';
        $expires = date('Y-m-d H:i:s', strtotime('+90 days'));

        try {
            $ok = $conn->query(
                "INSERT INTO api_tokens (user_id, token, device_name, expires_at)
                 VALUES ($userId, '$tokenEsc', $deviceEsc, '$expires')"
            );
            if (!$ok) {
                throw new RuntimeException($conn->error ?: 'Insert token gagal');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal membuat token: ' . $e->getMessage(), 0, $e);
        }

        return $token;
    }

    public static function revokeToken(mysqli $conn, string $token): void
    {
        self::ensureTokensTable($conn);
        $tokenEsc = $conn->real_escape_string($token);
        try {
            $conn->query("DELETE FROM api_tokens WHERE token = '$tokenEsc'");
        } catch (Throwable $e) {
            // ignore revoke errors
        }
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function userFromToken(mysqli $conn): ?array
    {
        self::ensureTokensTable($conn);
        $token = self::bearerToken();
        if (!$token) {
            return null;
        }
        $tokenEsc = $conn->real_escape_string($token);
        $sql = "
            SELECT u.id, u.name, u.username, u.email, u.role, u.profile_pic, t.token
            FROM api_tokens t
            JOIN users u ON u.id = t.user_id
            WHERE t.token = '$tokenEsc'
              AND (t.expires_at IS NULL OR t.expires_at > NOW())
            LIMIT 1
        ";
        try {
            $result = $conn->query($sql);
        } catch (Throwable $e) {
            return null;
        }
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    public static function requireStudent(mysqli $conn): array
    {
        $user = self::userFromToken($conn);
        if (!$user) {
            ApiResponse::error('Unauthorized. Token tidak valid atau sudah kedaluwarsa.', 401);
        }
        if (strtolower(trim($user['role'])) !== 'student') {
            ApiResponse::error('Akses ditolak. Hanya akun siswa yang diizinkan.', 403);
        }
        return $user;
    }

    public static function publicUser(array $user, string $baseUrl): array
    {
        $pic = null;
        if (!empty($user['profile_pic'])) {
            $pic = rtrim($baseUrl, '/') . '/uploads/' . ltrim($user['profile_pic'], '/');
        }
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'role' => $user['role'],
            'profile_pic_url' => $pic,
        ];
    }
}
