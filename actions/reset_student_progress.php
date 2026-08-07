<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';

$role = isset($_SESSION['user_role']) ? trim(strtolower((string) $_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/manage_courses.php');
    exit;
}

$teacherId = (int) $_SESSION['user_id'];
$courseId = (int) ($_POST['course_id'] ?? 0);
$studentId = (int) ($_POST['student_id'] ?? 0);
$page = max(1, (int) ($_POST['page'] ?? 1));
$q = trim((string) ($_POST['q'] ?? ''));

if ($courseId <= 0 || $studentId <= 0 || !CourseStudents::teacherOwnsCourse($conn, $teacherId, $courseId)) {
    $_SESSION['error'] = 'Aksi tidak diizinkan.';
    header('Location: ../pages/manage_courses.php');
    exit;
}

CourseStudents::resetProgress($conn, $courseId, $studentId);
$_SESSION['success'] = 'Progres siswa pada course ini berhasil direset.';

$redir = '../pages/course_view.php?id=' . $courseId . '&page=' . $page;
if ($q !== '') {
    $redir .= '&q=' . urlencode($q);
}
header('Location: ' . $redir);
exit;
