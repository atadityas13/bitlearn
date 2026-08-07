<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$return_status = isset($_POST['return_status']) ? (string)$_POST['return_status'] : 'all';
if (!in_array($return_status, ['all', 'ungraded', 'graded'], true)) {
    $return_status = 'all';
}
$redirect = '../pages/teacher_grading.php' . ($return_status !== 'all' ? ('?status=' . urlencode($return_status)) : '');

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
