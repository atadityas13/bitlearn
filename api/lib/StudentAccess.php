<?php

class StudentAccess
{
    public static function accessibleCoursesSql(int $studentId): string
    {
        return "
            SELECT DISTINCT c.*
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $studentId
            LEFT JOIN course_classes cc ON c.id = cc.course_id
            LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $studentId
            WHERE e.id IS NOT NULL OR cs.student_id IS NOT NULL
        ";
    }

    public static function canAccessCourse(mysqli $conn, int $studentId, int $courseId): bool
    {
        $sql = "
            SELECT c.id
            FROM courses c
            LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $studentId
            LEFT JOIN course_classes cc ON c.id = cc.course_id
            LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $studentId
            WHERE c.id = $courseId AND (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
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
