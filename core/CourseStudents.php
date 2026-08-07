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
        if ($probe !== false) {
            self::$exclusionsReady = true;
            return true;
        }

        @$conn->query("
            CREATE TABLE IF NOT EXISTS course_exclusions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                student_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_course_student (course_id, student_id),
                KEY idx_course (course_id),
                KEY idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $probe = @$conn->query("SELECT 1 FROM course_exclusions LIMIT 1");
        if ($probe !== false) {
            self::$exclusionsReady = true;
            return true;
        }

        self::$exclusionsReady = false;
        return false;
    }

    public static function hasExclusionsTable(mysqli $conn): bool
    {
        return self::ensureExclusionsTable($conn);
    }

    /** Klausa SQL: siswa tidak dalam daftar exclusion course. */
    public static function notExcludedSql(string $courseAlias, string $studentIdExpr): string
    {
        return "NOT EXISTS (
            SELECT 1 FROM course_exclusions cx
            WHERE cx.course_id = {$courseAlias}.id
              AND cx.student_id = {$studentIdExpr}
        )";
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

    public static function unenroll(mysqli $conn, int $courseId, int $studentId): void
    {
        self::ensureExclusionsTable($conn);
        $conn->query("DELETE FROM enrollments WHERE course_id = $courseId AND student_id = $studentId");
        $conn->query("
            INSERT IGNORE INTO course_exclusions (course_id, student_id)
            VALUES ($courseId, $studentId)
        ");
    }

    public static function resetProgress(mysqli $conn, int $courseId, int $studentId): void
    {
        // Progress materi
        $conn->query("
            DELETE p FROM user_progress p
            INNER JOIN lessons l ON p.lesson_id = l.id
            INNER JOIN modules m ON l.module_id = m.id
            WHERE m.course_id = $courseId AND p.student_id = $studentId
        ");
        // Percobaan kuis
        $conn->query("
            DELETE qa FROM quiz_attempts qa
            INNER JOIN lessons l ON qa.lesson_id = l.id
            INNER JOIN modules m ON l.module_id = m.id
            WHERE m.course_id = $courseId AND qa.student_id = $studentId
        ");
        // Pengumpulan tugas
        $conn->query("
            DELETE s FROM submissions s
            INNER JOIN assignments a ON s.assignment_id = a.id
            WHERE a.course_id = $courseId AND s.student_id = $studentId
        ");
    }
}
