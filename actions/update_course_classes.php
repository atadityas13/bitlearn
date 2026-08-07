<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/manage_courses.php');
    exit;
}

$teacher_id = (int) $_SESSION['user_id'];
$course_id = (int) ($_POST['course_id'] ?? 0);

$check = $conn->query("SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id LIMIT 1");
if (!$check || $check->num_rows === 0) {
    $_SESSION['error'] = 'Akses terlarang.';
    header('Location: ../pages/manage_courses.php');
    exit;
}

$teacherClassIds = [];
$tcRes = $conn->query("SELECT id FROM classes WHERE teacher_id = $teacher_id");
if ($tcRes) {
    while ($row = $tcRes->fetch_assoc()) {
        $teacherClassIds[(int) $row['id']] = true;
    }
}

$selected = [];
if (isset($_POST['allowed_classes']) && is_array($_POST['allowed_classes'])) {
    foreach ($_POST['allowed_classes'] as $rawId) {
        $cid = (int) $rawId;
        if ($cid > 0 && isset($teacherClassIds[$cid])) {
            $selected[$cid] = true;
        }
    }
}

$conn->query("DELETE FROM course_classes WHERE course_id = $course_id");
foreach (array_keys($selected) as $cid) {
    $conn->query("INSERT INTO course_classes (course_id, class_id) VALUES ($course_id, $cid)");
}

$count = count($selected);
$_SESSION['success'] = $count > 0
    ? "Daftar kelas course diperbarui ($count rombel terhubung)."
    : 'Semua rombel dilepas dari course. Siswa hanya bisa masuk lewat kode gabung / penambahan manual.';

header('Location: ../pages/course_view.php?id=' . $course_id);
exit;
