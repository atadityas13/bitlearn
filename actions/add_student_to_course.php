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
$nisn = trim((string) ($_POST['nisn'] ?? ''));
$page = max(1, (int) ($_POST['page'] ?? 1));
$q = trim((string) ($_POST['q'] ?? ''));

$redir = '../pages/course_view.php?id=' . $courseId . '&page=' . $page;
if ($q !== '') {
    $redir .= '&q=' . urlencode($q);
}

if ($courseId <= 0 || !CourseStudents::teacherOwnsCourse($conn, $teacherId, $courseId)) {
    $_SESSION['error'] = 'Aksi tidak diizinkan.';
    header('Location: ../pages/manage_courses.php');
    exit;
}

$result = CourseStudents::enrollByNisn($conn, $courseId, $nisn);
if ($result['ok']) {
    $name = isset($result['student']['name']) ? $result['student']['name'] : '';
    $_SESSION['success'] = $name !== ''
        ? $result['message'] . ' (' . $name . ')'
        : $result['message'];
} else {
    $_SESSION['error'] = $result['message'];
}

header('Location: ' . $redir);
exit;
