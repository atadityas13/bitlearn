<?php

class StudentAccess
{
    /** Filter exclusion hanya jika tabel siap; jika tidak, jangan JOIN agar daftar course tidak kosong. */
    private static function exclusionSqlParts(mysqli $conn, int $studentId): array
    {
        $use = class_exists('CourseStudents') && CourseStudents::hasExclusionsTable($conn);
        if (!$use) {
            return ['join' => '', 'where' => ''];
        }
        return [
            'join' => "LEFT JOIN course_exclusions cx ON cx.course_id = c.id AND cx.student_id = $studentId",
            'where' => 'AND cx.id IS NULL',
        ];
    }

    public static function accessibleCoursesSql(int $studentId, ?mysqli $conn = null): string
    {
        $exJoin = '';
        $exWhere = '';
        if ($conn instanceof mysqli) {
            $ex = self::exclusionSqlParts($conn, $studentId);
            $exJoin = $ex['join'];
            $exWhere = $ex['where'];
        } else {
            // Caller tanpa $conn: pakai NOT EXISTS (butuh tabel course_exclusions).
            $exWhere = "AND NOT EXISTS (
                SELECT 1 FROM course_exclusions cx
                WHERE cx.course_id = c.id AND cx.student_id = $studentId
            )";
        }

        return "
            SELECT DISTINCT c.*
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $studentId
            LEFT JOIN course_classes cc ON c.id = cc.course_id
            LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $studentId
            $exJoin
            WHERE (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
              $exWhere
        ";
    }

    public static function canAccessCourse(mysqli $conn, int $studentId, int $courseId): bool
    {
        $ex = self::exclusionSqlParts($conn, $studentId);

        $sql = "
            SELECT c.id
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $studentId
            LEFT JOIN course_classes cc ON c.id = cc.course_id
            LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $studentId
            {$ex['join']}
            WHERE c.id = $courseId
              AND (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
              {$ex['where']}
            LIMIT 1
        ";
        $res = $conn->query($sql);
        return $res && $res->num_rows > 0;
    }

    public static function coursePayload(array $course, string $baseUrl): array
    {
        $thumb = null;
        if (!empty($course['thumbnail_url'])) {
            $thumb = rtrim($baseUrl, '/') . '/uploads/thumbnails/' . ltrim($course['thumbnail_url'], '/');
        }
        return [
            'id' => (int) $course['id'],
            'title' => $course['title'],
            'description' => $course['description'] ?? '',
            'thumbnail_url' => $thumb,
            'teacher_id' => isset($course['teacher_id']) ? (int) $course['teacher_id'] : null,
            'created_at' => $course['created_at'] ?? null,
        ];
    }

    public static function absoluteUploadUrl(?string $path, string $baseUrl): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim($baseUrl, '/') . '/uploads/' . ltrim($path, '/');
    }

    /** Ambil URL src jika guru menempel kode iframe. */
    public static function extractEmbedSrc(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (preg_match('/src=["\']([^"\']+)["\']/i', $raw, $m)) {
            return trim($m[1]);
        }
        return $raw;
    }

    public static function normalizeVideoEmbedUrl(?string $url): ?string
    {
        $url = self::extractEmbedSrc($url);
        if ($url === null) {
            return null;
        }
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?playsinline=1&rel=0';
        }
        if (preg_match('%drive\.google\.com/file/d/([^/]+)%i', $url, $m)) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }
        return $url;
    }

    public static function normalizeSlideshowUrl(?string $url): ?string
    {
        $url = self::extractEmbedSrc($url);
        if ($url === null) {
            return null;
        }
        if (preg_match('%docs\.google\.com/presentation/d/([^/]+)%i', $url, $m)) {
            return 'https://docs.google.com/presentation/d/' . $m[1] . '/embed';
        }
        return $url;
    }

    public static function normalizePdfEmbedUrl(?string $url): ?string
    {
        $url = self::extractEmbedSrc($url);
        if ($url === null) {
            return null;
        }
        if (preg_match('%drive\.google\.com/file/d/([^/]+)%i', $url, $m)) {
            return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
        }
        if (preg_match('%docs\.google\.com/document/d/([^/]+)%i', $url, $m)) {
            return 'https://docs.google.com/document/d/' . $m[1] . '/preview';
        }
        return $url;
    }

    /**
     * Payload media untuk app: viewer_url siap ditampilkan di WebView/Image.
     */
    public static function buildLessonMedia(array $lesson, string $baseUrl): array
    {
        $type = strtolower(trim((string) ($lesson['content_type'] ?? '')));
        $urlEmbed = self::extractEmbedSrc($lesson['url_embed'] ?? null);
        $docPath = $lesson['document_path'] ?? null;
        // Fallback lama: beberapa data menyimpan nama file di url_embed
        if (empty($docPath) && $type === 'document_upload' && !empty($urlEmbed) && !preg_match('#^https?://#i', $urlEmbed)) {
            $docPath = $urlEmbed;
            $urlEmbed = null;
        }
        $documentUrl = self::absoluteUploadUrl($docPath, $baseUrl);
        $ext = $docPath ? strtolower(pathinfo($docPath, PATHINFO_EXTENSION)) : '';

        $viewerUrl = null;
        $mediaKind = 'text';

        switch ($type) {
            case 'video_embed':
                $mediaKind = 'video';
                $viewerUrl = self::normalizeVideoEmbedUrl($urlEmbed);
                break;
            case 'slideshow':
            case 'ppt_slideshow':
                $mediaKind = 'slideshow';
                $viewerUrl = self::normalizeSlideshowUrl($urlEmbed);
                break;
            case 'pdf_embed':
                $mediaKind = 'pdf';
                $viewerUrl = self::normalizePdfEmbedUrl($urlEmbed);
                break;
            case 'document_upload':
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true)) {
                    $mediaKind = 'image';
                    $viewerUrl = $documentUrl;
                } elseif ($ext === 'pdf') {
                    $mediaKind = 'pdf';
                    $viewerUrl = $documentUrl;
                } else {
                    $mediaKind = 'document';
                    $viewerUrl = $documentUrl;
                }
                break;
            case 'quiz':
                $mediaKind = 'quiz';
                break;
            default:
                if (!empty($urlEmbed)) {
                    $mediaKind = 'embed';
                    $viewerUrl = $urlEmbed;
                } elseif (!empty($documentUrl)) {
                    $mediaKind = 'document';
                    $viewerUrl = $documentUrl;
                }
                break;
        }

        return [
            'url_embed' => $urlEmbed,
            'document_url' => $documentUrl,
            'viewer_url' => $viewerUrl,
            'media_kind' => $mediaKind,
            'file_extension' => $ext !== '' ? $ext : null,
            'completion' => self::buildCompletionRule(
                $type,
                $mediaKind,
                $ext,
                $viewerUrl,
                isset($lesson['dwell_minutes']) ? (int) $lesson['dwell_minutes'] : null
            ),
        ];
    }

    public static function extractYoutubeId(?string $url): ?string
    {
        $url = self::extractEmbedSrc($url);
        if ($url === null) {
            return null;
        }
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Aturan penyelesaian materi — mirror logic web lesson_viewer.php,
     * dengan fallback lebih jelas untuk non-YouTube.
     * @param int|null $dwellMinutes menit dari pengaturan guru (PDF/slides)
     */
    public static function buildCompletionRule(
        string $contentType,
        string $mediaKind,
        string $ext,
        ?string $viewerUrl,
        ?int $dwellMinutes = null
    ): array {
        $type = strtolower(trim($contentType));
        $kind = strtolower(trim($mediaKind));
        $ext = strtolower(trim($ext));

        if (class_exists('LessonSettings')) {
            // no-op; label helper
        } else {
            @include_once dirname(__DIR__, 2) . '/core/LessonSettings.php';
        }

        if ($type === 'quiz' || $kind === 'quiz') {
            return [
                'mode' => 'quiz',
                'required_seconds' => 0,
                'youtube_id' => null,
                'pause_aware' => false,
                'label' => 'Selesaikan kuis untuk menandai materi selesai',
            ];
        }

        if ($type === 'video_embed' || $kind === 'video') {
            $yt = self::extractYoutubeId($viewerUrl);
            if ($yt) {
                return [
                    'mode' => 'video_ended',
                    'required_seconds' => 0,
                    'youtube_id' => $yt,
                    'pause_aware' => true,
                    'label' => 'Tonton video sampai selesai (tidak bisa dilewati)',
                ];
            }
            return [
                'mode' => 'dwell',
                'required_seconds' => 120,
                'youtube_id' => null,
                'pause_aware' => true,
                'label' => 'Tonton video minimal 2 menit (timer berhenti jika aplikasi tidak aktif)',
            ];
        }

        // PDF embed / slideshow: pakai menit dari guru
        if (in_array($type, ['slideshow', 'ppt_slideshow', 'pdf_embed'], true) || $kind === 'slideshow') {
            $minutes = $dwellMinutes !== null ? max(0, $dwellMinutes) : 1;
            $seconds = $minutes * 60;
            $label = class_exists('LessonSettings')
                ? LessonSettings::formatDwellLabel($seconds)
                : ('Baca materi minimal ' . max(0, $minutes) . ' menit');
            return [
                'mode' => 'dwell',
                'required_seconds' => $seconds,
                'youtube_id' => null,
                'pause_aware' => true,
                'label' => $label,
            ];
        }

        if ($type === 'document_upload' || $kind === 'pdf' || $kind === 'image' || $kind === 'document') {
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true) || $kind === 'image') {
                return [
                    'mode' => 'dwell',
                    'required_seconds' => 2,
                    'youtube_id' => null,
                    'pause_aware' => true,
                    'label' => 'Lihat gambar sebentar',
                ];
            }
            if ($ext === 'pdf' || $kind === 'pdf') {
                $minutes = $dwellMinutes !== null ? max(0, $dwellMinutes) : 1;
                $seconds = $minutes * 60;
                $label = class_exists('LessonSettings')
                    ? LessonSettings::formatDwellLabel($seconds)
                    : ('Baca PDF minimal ' . max(0, $minutes) . ' menit');
                return [
                    'mode' => 'dwell',
                    'required_seconds' => $seconds,
                    'youtube_id' => null,
                    'pause_aware' => true,
                    'label' => $label,
                ];
            }
            return [
                'mode' => 'download',
                'required_seconds' => 0,
                'youtube_id' => null,
                'pause_aware' => false,
                'label' => 'Unduh / buka berkas lampiran untuk membuka tombol selesai',
            ];
        }

        if ($kind === 'embed' || !empty($viewerUrl)) {
            return [
                'mode' => 'dwell',
                'required_seconds' => 10,
                'youtube_id' => null,
                'pause_aware' => true,
                'label' => 'Pelajari konten minimal 10 detik',
            ];
        }

        return [
            'mode' => 'none',
            'required_seconds' => 0,
            'youtube_id' => null,
            'pause_aware' => false,
            'label' => 'Baca deskripsi lalu tandai selesai',
        ];
    }

    /** Ekstensi dari nama file atau MIME (Android content URI sering tanpa ekstensi). */
    public static function resolveUploadExtension(array $file): string
    {
        $name = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== '' && preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
            return $ext;
        }
        $mime = strtolower((string) ($file['type'] ?? ''));
        $map = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/vnd.rar' => 'rar',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        return $map[$mime] ?? '';
    }

    public static function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Ukuran file terlalu besar untuk server.';
            case UPLOAD_ERR_PARTIAL:
                return 'File hanya terunggah sebagian. Coba lagi.';
            case UPLOAD_ERR_NO_FILE:
                return 'File tugas wajib diunggah.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Folder sementara server tidak tersedia.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server gagal menulis file.';
            case UPLOAD_ERR_EXTENSION:
                return 'Unggahan diblokir ekstensi PHP.';
            default:
                return 'File tugas wajib diunggah (kode: ' . $code . ').';
        }
    }

    public static function getCourseIdForLesson(mysqli $conn, int $lessonId): ?int
    {
        $res = $conn->query("
            SELECT modules.course_id
            FROM lessons
            JOIN modules ON lessons.module_id = modules.id
            WHERE lessons.id = $lessonId
            LIMIT 1
        ");
        if ($res && $res->num_rows > 0) {
            return (int) $res->fetch_assoc()['course_id'];
        }
        return null;
    }
}
