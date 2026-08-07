<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$return_status = isset($_POST['return_status']) ? (string)$_POST['return_status'] : 'all';
if (!in_array($return_status, ['all', 'ungraded', 'graded'], true)) {
    $return_status = 'all';
}
$return_course_id = isset($_POST['return_course_id']) ? (int)$_POST['return_course_id'] : 0;
$return_class_id = isset($_POST['return_class_id']) ? (int)$_POST['return_class_id'] : 0;
$return_q = isset($_POST['return_q']) ? trim((string)$_POST['return_q']) : '';
if (strlen($return_q) > 100) {
    $return_q = substr($return_q, 0, 100);
}
$allowed_limits = [10, 25, 50, 100];
$return_page = isset($_POST['return_page']) ? max(1, (int)$_POST['return_page']) : 1;
$return_per_page = isset($_POST['return_per_page']) && in_array((int)$_POST['return_per_page'], $allowed_limits, true)
    ? (int)$_POST['return_per_page']
    : 10;

$params = [];
if ($return_status !== 'all') {
    $params['status'] = $return_status;
}
if ($return_course_id > 0) {
    $params['course_id'] = $return_course_id;
}
if ($return_class_id > 0) {
    $params['class_id'] = $return_class_id;
}
if ($return_q !== '') {
    $params['q'] = $return_q;
}
if ($return_per_page !== 10) {
    $params['per_page'] = $return_per_page;
}
if ($return_page > 1) {
    $params['page'] = $return_page;
}
$redirect = '../pages/teacher_grading.php' . ($params ? ('?' . http_build_query($params)) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub_id = (int)$_POST['sub_id'];
    $grade = isset($_POST['grade']) ? (int)$_POST['grade'] : -1;
    $feedback = $conn->real_escape_string(trim((string)($_POST['feedback'] ?? '')));

    if ($grade < 0 || $grade > 100) {
        $_SESSION['error'] = 'Nilai harus antara 0 sampai 100.';
        header("Location: $redirect");
        exit;
    }

    $teacher_id = (int)$_SESSION['user_id'];
    $valid = $conn->query("SELECT s.id FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id WHERE s.id = $sub_id AND c.teacher_id = $teacher_id");

    if ($valid && $valid->num_rows > 0) {
        $conn->query("UPDATE submissions SET grade = $grade, feedback = '$feedback' WHERE id = $sub_id");
        $_SESSION['success'] = 'Nilai berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Submisi tidak ditemukan atau tidak berhak dinilai.';
    }
}

header("Location: $redirect");
exit;
