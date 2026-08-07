<?php
require_once '../core/config.php';
$r = isset($_SESSION['user_role']) ? trim(strtolower((string)$_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $r !== 'student') { header("Location: ../index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = (int)$_POST['assignment_id'];
    $course_id = (int)$_POST['course_id']; // For routing back
    $student_id = (int)$_SESSION['user_id'];

    $chka = $conn->query("SELECT id, due_date, is_published FROM assignments WHERE id = $assignment_id LIMIT 1");
    if ($chka && $chka->num_rows > 0) {
        $asn = $chka->fetch_assoc();
        if ((int)($asn['is_published'] ?? 0) !== 1) {
            $_SESSION['error'] = 'Tugas tidak tersedia.';
            header("Location: ../pages/lesson_viewer.php?course_id=$course_id&assignment_id=$assignment_id");
            exit;
        }

        $dueTs = strtotime((string)$asn['due_date']);
        $stillOpen = $conn->query("SELECT id FROM assignments WHERE id = $assignment_id AND due_date > NOW() LIMIT 1");
        if (($dueTs !== false && $dueTs < time()) || !$stillOpen || $stillOpen->num_rows === 0) {
            $_SESSION['error'] = 'Batas pengumpulan tugas sudah berakhir.';
            header("Location: ../pages/lesson_viewer.php?course_id=$course_id&assignment_id=$assignment_id");
            exit;
        }

        $existing = $conn->query("SELECT id FROM submissions WHERE assignment_id = $assignment_id AND student_id = $student_id LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $_SESSION['error'] = 'Anda sudah mengumpulkan tugas ini.';
            header("Location: ../pages/lesson_viewer.php?course_id=$course_id&assignment_id=$assignment_id");
            exit;
        }

        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'png', 'jpg', 'jpeg'];
            $filename = $_FILES['assignment_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $new_filename = 'ans_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $upload_dir . $new_filename)) {
                    $doc_path = $conn->real_escape_string($new_filename);

                    $sql = "INSERT INTO submissions (assignment_id, student_id, file_path) VALUES ($assignment_id, $student_id, '$doc_path')";
                    if ($conn->query($sql) === TRUE) {
                        $_SESSION['success'] = "Berkas tugas berhasil diunggah dan diserahkan!";
                    } else {
                        $_SESSION['error'] = "Gagal merekam jawaban: " . $conn->error;
                    }
                } else {
                    $_SESSION['error'] = 'Upload gagal pada sisi penyimpan server.';
                }
            } else {
                $_SESSION['error'] = 'Format tidak sah. Gunakan PDF/DOCS.';
            }
        } else {
            $_SESSION['error'] = 'File tiada atau rusak.';
        }
    } else {
        $_SESSION['error'] = 'Tugas tidak ditemukan.';
    }

    header("Location: ../pages/lesson_viewer.php?course_id=$course_id&assignment_id=$assignment_id");
    exit;
}
header("Location: ../pages/student_dashboard.php");
exit;
