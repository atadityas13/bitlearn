<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $conn->real_escape_string(trim($_POST['enrollment_code']));
    $student_id = (int) $_SESSION['user_id'];

    $result = $conn->query("SELECT id FROM courses WHERE enrollment_code = '$code'");

    if ($result && $result->num_rows > 0) {
        $course_id = (int) $result->fetch_assoc()['id'];

        $check = $conn->query("SELECT id FROM enrollments WHERE course_id = $course_id AND student_id = $student_id");
        $already = $check && $check->num_rows > 0;
        $wasExcluded = CourseStudents::isExcluded($conn, $course_id, $student_id);

        if ($already) {
            if ($wasExcluded) {
                CourseStudents::clearExclusion($conn, $course_id, $student_id);
                $_SESSION['success'] = "Akses mata pelajaran dipulihkan!";
            } else {
                $_SESSION['error'] = "Anda sudah tergabung dalam pelajaran ini.";
            }
        } else {
            $ok = $conn->query("INSERT INTO enrollments (course_id, student_id) VALUES ($course_id, $student_id)");
            if ($ok) {
                CourseStudents::clearExclusion($conn, $course_id, $student_id);
                $_SESSION['success'] = "Berhasil bergabung ke mata pelajaran!";
            } else {
                $_SESSION['error'] = "Gagal bergabung ke mata pelajaran. Coba lagi.";
            }
        }
    } else {
        $_SESSION['error'] = "Kode pelajaran tidak valid atau tidak ditemukan.";
    }
}
header("Location: ../pages/student_dashboard.php");
exit;
