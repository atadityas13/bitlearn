<?php

/**
 * Helper akses & manajemen siswa dalam course (unenroll / exclusion).
 */
class CourseStudents
{
    /** @var bool|null null=belum dicek, true=siap, false=gagal */
    private static $exclusionsReady = null;

    public static function ensureExclusionsTable(mysqli $conn): bool
    {
        if (self::$exclusionsReady === true) {
            return true;
        }
        if (self::$exclusionsReady === false) {
            return false;
        }

        $probe = @$conn->query("SELECT 1 FROM course_exclusions LIMIT 1");
        if ($probe === false) {
            @$conn->query("
                CREATE TABLE IF NOT EXISTS course_exclusions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    course_id INT NOT NULL,
                    student_id INT NOT NULL,
                    block_rejoin TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_course_student (course_id, student_id),
                    KEY idx_course (course_id),
                    KEY idx_student (student_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $probe = @$conn->query("SELECT 1 FROM course_exclusions LIMIT 1");
            if ($probe === false) {
                self::$exclusionsReady = false;
                return false;
            }
        }

        $col = @$conn->query("SHOW COLUMNS FROM course_exclusions LIKE 'block_rejoin'");
        if ($col && $col->num_rows === 0) {
            @$conn->query("
                ALTER TABLE course_exclusions
                ADD COLUMN block_rejoin TINYINT(1) NOT NULL DEFAULT 0 AFTER student_id
            ");
        }

        self::$exclusionsReady = true;
        return true;
    }

    public static function hasExclusionsTable(mysqli $conn): bool
    {
        return self::ensureExclusionsTable($conn);
    }

    public static function isExcluded(mysqli $conn, int $courseId, int $studentId): bool
    {
        if (!self::ensureExclusionsTable($conn)) {
            return false;
        }
        $res = $conn->query("
            SELECT id FROM course_exclusions
            WHERE course_id = $courseId AND student_id = $studentId
            LIMIT 1
        ");
        return $res && $res->num_rows > 0;
    }

    /** True jika guru mencentang "cegah masuk kembali". */
    public static function isBlockedFromRejoin(mysqli $conn, int $courseId, int $studentId): bool
    {
        if (!self::ensureExclusionsTable($conn)) {
            return false;
        }
        $res = $conn->query("
            SELECT id FROM course_exclusions
            WHERE course_id = $courseId AND student_id = $studentId AND block_rejoin = 1
            LIMIT 1
        ");
        return $res && $res->num_rows > 0;
    }

    public static function clearExclusion(mysqli $conn, int $courseId, int $studentId): void
    {
        if (!self::ensureExclusionsTable($conn)) {
            return;
        }
        $conn->query("
            DELETE FROM course_exclusions
            WHERE course_id = $courseId AND student_id = $studentId
        ");
    }

    public static function teacherOwnsCourse(mysqli $conn, int $teacherId, int $courseId): bool
    {
        $res = $conn->query("SELECT id FROM courses WHERE id = $courseId AND teacher_id = $teacherId LIMIT 1");
        return $res && $res->num_rows > 0;
    }

    /**
     * Keluarkan siswa dari course.
     * - Selalu hapus enrollment + tandai exclusion (agar akses rombel ikut hilang dari course).
     * - block_rejoin=true: siswa tidak bisa gabung lagi lewat kode.
     * - block_rejoin=false: siswa boleh gabung lagi lewat kode (exclusion dihapus saat enroll).
     */
    public static function unenroll(mysqli $conn, int $courseId, int $studentId, bool $blockRejoin = false): void
    {
        self::ensureExclusionsTable($conn);
        $conn->query("DELETE FROM enrollments WHERE course_id = $courseId AND student_id = $studentId");
        $flag = $blockRejoin ? 1 : 0;
        $conn->query("
            INSERT INTO course_exclusions (course_id, student_id, block_rejoin)
            VALUES ($courseId, $studentId, $flag)
            ON DUPLICATE KEY UPDATE block_rejoin = $flag
        ");
    }

    /**
     * Guru menambahkan siswa manual (NISN). Selalu menghapus exclusion / blokir.
     * @return array{ok:bool,message:string,student?:array}
     */
    public static function enrollByNisn(mysqli $conn, int $courseId, string $nisn): array
    {
        $nisn = trim($nisn);
        if ($nisn === '') {
            return ['ok' => false, 'message' => 'NISN wajib diisi.'];
        }

        $safe = $conn->real_escape_string($nisn);
        $res = $conn->query("
            SELECT id, name, username FROM users
            WHERE role = 'student' AND username = '$safe'
            LIMIT 1
        ");
        if (!$res || $res->num_rows === 0) {
            return ['ok' => false, 'message' => 'Siswa dengan NISN tersebut tidak ditemukan.'];
        }
        $student = $res->fetch_assoc();
        $studentId = (int) $student['id'];

        self::clearExclusion($conn, $courseId, $studentId);

        $check = $conn->query("
            SELECT id FROM enrollments
            WHERE course_id = $courseId AND student_id = $studentId
            LIMIT 1
        ");
        if ($check && $check->num_rows > 0) {
            return [
                'ok' => true,
                'message' => 'Siswa sudah terdaftar di course ini. Blokir (jika ada) telah dihapus.',
                'student' => $student,
            ];
        }

        $ok = $conn->query("INSERT INTO enrollments (course_id, student_id) VALUES ($courseId, $studentId)");
        if (!$ok) {
            return ['ok' => false, 'message' => 'Gagal menambahkan siswa ke course.'];
        }

        return [
            'ok' => true,
            'message' => 'Siswa berhasil ditambahkan ke course.',
            'student' => $student,
        ];
    }

    /**
     * Enroll siswa lewat kode (web/API). Hormati block_rejoin.
     * @return array{ok:bool,code:int,message:string,course?:array}
     */
    public static function enrollByCode(mysqli $conn, int $studentId, string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'code' => 422, 'message' => 'Kode pelajaran wajib diisi.'];
        }

        $safe = $conn->real_escape_string($code);
        $result = $conn->query("SELECT * FROM courses WHERE enrollment_code = '$safe' LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return ['ok' => false, 'code' => 404, 'message' => 'Kode pelajaran tidak valid atau tidak ditemukan.'];
        }
        $course = $result->fetch_assoc();
        $courseId = (int) $course['id'];

        if (self::isBlockedFromRejoin($conn, $courseId, $studentId)) {
            return [
                'ok' => false,
                'code' => 403,
                'message' => 'Anda tidak diizinkan masuk Pelajaran ini.',
            ];
        }

        // Soft exclusion (tanpa cegah): hapus agar bisa masuk kembali
        if (self::isExcluded($conn, $courseId, $studentId)) {
            self::clearExclusion($conn, $courseId, $studentId);
        }

        $check = $conn->query("
            SELECT id FROM enrollments
            WHERE course_id = $courseId AND student_id = $studentId
            LIMIT 1
        ");
        if ($check && $check->num_rows > 0) {
            return [
                'ok' => false,
                'code' => 409,
                'message' => 'Anda sudah tergabung dalam pelajaran ini.',
                'course' => $course,
            ];
        }

        $ok = $conn->query("INSERT INTO enrollments (course_id, student_id) VALUES ($courseId, $studentId)");
        if (!$ok) {
            return ['ok' => false, 'code' => 500, 'message' => 'Gagal bergabung ke mata pelajaran. Coba lagi.'];
        }

        return [
            'ok' => true,
            'code' => 200,
            'message' => 'Berhasil bergabung ke mata pelajaran',
            'course' => $course,
        ];
    }

    public static function resetProgress(mysqli $conn, int $courseId, int $studentId): void
    {
        $conn->query("
            DELETE p FROM user_progress p
            INNER JOIN lessons l ON p.lesson_id = l.id
            INNER JOIN modules m ON l.module_id = m.id
            WHERE m.course_id = $courseId AND p.student_id = $studentId
        ");
        $conn->query("
            DELETE qa FROM quiz_attempts qa
            INNER JOIN lessons l ON qa.lesson_id = l.id
            INNER JOIN modules m ON l.module_id = m.id
            WHERE m.course_id = $courseId AND qa.student_id = $studentId
        ");
        $conn->query("
            DELETE s FROM submissions s
            INNER JOIN assignments a ON s.assignment_id = a.id
            WHERE a.course_id = $courseId AND s.student_id = $studentId
        ");
    }
}
