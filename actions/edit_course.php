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
$redirect = trim((string) ($_POST['redirect'] ?? ''));

$check = $conn->query("SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id LIMIT 1");
if (!$check || $check->num_rows === 0) {
    $_SESSION['error'] = 'Akses terlarang.';
    header('Location: ../pages/manage_courses.php');
    exit;
}

$title = $conn->real_escape_string(trim((string) ($_POST['title'] ?? '')));
$description = $conn->real_escape_string(trim((string) ($_POST['description'] ?? '')));
$enrollment_code = trim((string) ($_POST['enrollment_code'] ?? ''));
$code_val = $enrollment_code === '' ? 'NULL' : ("'" . $conn->real_escape_string($enrollment_code) . "'");

if ($title === '') {
    $_SESSION['error'] = 'Judul pelajaran wajib diisi.';
    header('Location: ../pages/manage_courses.php');
    exit;
}

$thumbnail_query_part = '';
if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === 0) {
    $upload_dir = '../uploads/thumbnails/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $filename = time() . '_' . str_replace(' ', '_', $_FILES['thumbnail_file']['name']);
    if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $upload_dir . $filename)) {
        $thumbnail_url = $conn->real_escape_string($filename);
        $thumbnail_query_part = ", thumbnail_url = '$thumbnail_url'";
    }
}

$sql = "UPDATE courses SET title = '$title', description = '$description', enrollment_code = $code_val $thumbnail_query_part WHERE id = $course_id";
if ($conn->query($sql) !== true) {
    $_SESSION['error'] = 'Gagal memperbarui info Course: ' . $conn->error;
    header('Location: ../pages/manage_courses.php');
    exit;
}

// Sinkronisasi rombel yang boleh akses course (fleksibel tambah/kurang)
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

$_SESSION['success'] = 'Course dan daftar kelas berhasil diperbarui.';

if ($redirect === 'course_view') {
    header('Location: ../pages/course_view.php?id=' . $course_id);
    exit;
}
header('Location: ../pages/manage_courses.php');
exit;
