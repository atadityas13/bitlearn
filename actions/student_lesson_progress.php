<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';

header('Content-Type: application/json; charset=utf-8');

$role = isset($_SESSION['user_role']) ? trim(strtolower((string) $_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$teacherId = (int) $_SESSION['user_id'];
$courseId = (int) ($_GET['course_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);

if ($courseId <= 0 || $studentId <= 0 || !CourseStudents::teacherOwnsCourse($conn, $teacherId, $courseId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak diizinkan.']);
    exit;
}

$studentRes = $conn->query("
    SELECT id, name, username, profile_pic
    FROM users
    WHERE id = $studentId AND role = 'student'
    LIMIT 1
");
if (!$studentRes || $studentRes->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan.']);
    exit;
}
$student = $studentRes->fetch_assoc();

// Hanya materi yang tampil ke siswa (modul + materi published)
$completedIds = [];
$progRes = $conn->query("
    SELECT p.lesson_id
    FROM user_progress p
    INNER JOIN lessons l ON p.lesson_id = l.id
    INNER JOIN modules m ON l.module_id = m.id
    WHERE m.course_id = $courseId
      AND p.student_id = $studentId
      AND m.is_published = 1
      AND l.is_published = 1
");
if ($progRes) {
    while ($row = $progRes->fetch_assoc()) {
        $completedIds[(int) $row['lesson_id']] = true;
    }
}

$modules = [];
$total = 0;
$done = 0;
$modRes = $conn->query("
    SELECT id, title, order_num
    FROM modules
    WHERE course_id = $courseId
      AND is_published = 1
    ORDER BY order_num ASC, id ASC
");
if ($modRes) {
    while ($mod = $modRes->fetch_assoc()) {
        $mid = (int) $mod['id'];
        $lessons = [];
        $lesRes = $conn->query("
            SELECT id, title, content_type, order_num
            FROM lessons
            WHERE module_id = $mid
              AND is_published = 1
            ORDER BY order_num ASC, id ASC
        ");
        if ($lesRes) {
            while ($les = $lesRes->fetch_assoc()) {
                $lid = (int) $les['id'];
                $isDone = isset($completedIds[$lid]);
                $total++;
                if ($isDone) {
                    $done++;
                }
                $lessons[] = [
                    'id' => $lid,
                    'title' => $les['title'],
                    'content_type' => $les['content_type'] ?? '',
                    'completed' => $isDone,
                ];
            }
        }
        // Bab tanpa materi tampil tetap ditampilkan agar struktur jelas
        $modules[] = [
            'id' => $mid,
            'title' => $mod['title'],
            'lessons' => $lessons,
        ];
    }
}

$percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'student' => [
            'id' => (int) $student['id'],
            'name' => $student['name'],
            'username' => $student['username'],
            'profile_pic' => $student['profile_pic'],
        ],
        'completed' => $done,
        'total' => $total,
        'percent' => $percent,
        'modules' => $modules,
    ],
], JSON_UNESCAPED_UNICODE);
exit;
