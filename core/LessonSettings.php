<?php

/**
 * Pengaturan materi (timer dwell PDF/slides, dll).
 */
class LessonSettings
{
    /** @var bool|null */
    private static $dwellReady = null;

    public static function ensureDwellColumn(mysqli $conn): bool
    {
        if (self::$dwellReady === true) {
            return true;
        }
        if (self::$dwellReady === false) {
            return false;
        }

        $probe = @$conn->query("SHOW COLUMNS FROM lessons LIKE 'dwell_minutes'");
        if ($probe && $probe->num_rows > 0) {
            self::$dwellReady = true;
            return true;
        }

        $ok = @$conn->query("
            ALTER TABLE lessons
            ADD COLUMN dwell_minutes INT NOT NULL DEFAULT 1
            COMMENT 'Menit wajib baca sebelum tombol selesai (PDF/slides)'
            AFTER is_published
        ");
        if ($ok) {
            self::$dwellReady = true;
            return true;
        }

        // Coba lagi baca (kolom mungkin sudah ada)
        $probe = @$conn->query("SHOW COLUMNS FROM lessons LIKE 'dwell_minutes'");
        self::$dwellReady = ($probe && $probe->num_rows > 0);
        return self::$dwellReady;
    }

    /** Ambil menit dari POST (0–180), default 1. */
    public static function parseDwellMinutesFromPost(array $post, int $default = 1): int
    {
        if (!isset($post['dwell_minutes']) || $post['dwell_minutes'] === '') {
            return $default;
        }
        $m = (int) $post['dwell_minutes'];
        if ($m < 0) {
            $m = 0;
        }
        if ($m > 180) {
            $m = 180;
        }
        return $m;
    }

    /**
     * Detik dwell efektif dari baris lesson.
     * Default 1 menit jika kolom kosong (bukan lagi 5 menit).
     */
    public static function dwellSeconds(array $lesson, int $fallbackMinutes = 1): int
    {
        if (array_key_exists('dwell_minutes', $lesson) && $lesson['dwell_minutes'] !== null && $lesson['dwell_minutes'] !== '') {
            return max(0, (int) $lesson['dwell_minutes']) * 60;
        }
        return max(0, $fallbackMinutes) * 60;
    }

    public static function formatDwellLabel(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Tidak ada waktu tunggu — tombol selesai langsung aktif';
        }
        if ($seconds < 60) {
            return 'Baca materi minimal ' . $seconds . ' detik (timer berhenti jika aplikasi tidak aktif)';
        }
        $m = (int) round($seconds / 60);
        if ($m === 1) {
            return 'Baca materi minimal 1 menit (timer berhenti jika aplikasi tidak aktif)';
        }
        return 'Baca materi minimal ' . $m . ' menit (timer berhenti jika aplikasi tidak aktif)';
    }
}
