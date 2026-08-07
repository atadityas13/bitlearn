<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['enrollment_code'] ?? ''));
    $student_id = (int) $_SESSION['user_id'];
    $result = CourseStudents::enrollByCode($conn, $student_id, $code);

    if ($result['ok']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
}
header("Location: ../pages/student_dashboard.php");
exit;
