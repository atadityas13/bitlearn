<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lesson_id = (int)$_POST['lesson_id'];
    $course_id = (int)$_POST['course_id']; // For routing
    $teacher_id = $_SESSION['user_id'];

    // Verify ownership
    $chk = $conn->query("SELECT l.id FROM lessons l JOIN modules m ON l.module_id = m.id JOIN courses c ON m.course_id = c.id WHERE l.id = $lesson_id AND c.teacher_id = $teacher_id");
    if(!$chk || $chk->num_rows === 0) {
        $_SESSION['error'] = 'Akses ditolak.';
        header("Location: ../pages/manage_courses.php"); exit;
    }

    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $content_type = $conn->real_escape_string($_POST['content_type']);
    $existing_content_type = $conn->real_escape_string($_POST['existing_content_type']);
    
    $is_prereq = empty($_POST['is_prerequisite_of']) ? "NULL" : (int)$_POST['is_prerequisite_of'];

    if (empty($title)) {
        $_SESSION['error'] = 'Lesson title required.';
        header("Location: ../pages/edit_lesson.php?id=$lesson_id"); exit;
    }

    $update_fields = array();
    $update_fields[] = "title = '$title'";
    $update_fields[] = "description = '$description'";
    $update_fields[] = "content_type = '$content_type'";
    $is_published = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 0;
    $update_fields[] = "is_prerequisite_of = $is_prereq";
    $update_fields[] = "is_published = $is_published";

    require_once '../core/LessonSettings.php';
    LessonSettings::ensureDwellColumn($conn);
    $dwell_minutes = LessonSettings::parseDwellMinutesFromPost($_POST, 1);
    $update_fields[] = "dwell_minutes = $dwell_minutes";

    if ($content_type === 'video_embed' || $content_type === 'slideshow' || $content_type === 'pdf_embed') {
        $url_embed = trim($_POST['url_embed']);
        if(preg_match('/src="([^"]+)"/', $url_embed, $match)) {
            $url_embed = $match[1];
        } 
        
        if ($content_type === 'video_embed') {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url_embed, $match)) {
                $url_embed = "https://www.youtube.com/embed/" . $match[1] . "?enablejsapi=1";
            }
        } else if ($content_type === 'pdf_embed' || $content_type === 'slideshow') {
            // Google Drive / Slides Autoconverter Logic!
            if (preg_match('%drive\.google\.com/file/d/([^/]+)%i', $url_embed, $match)) {
                // Using viewerng for better compatibility across browsers (prevents "Sorry, cannot open" error)
                $url_embed = "https://docs.google.com/viewerng/viewer?embedded=true&url=https://drive.google.com/uc?id=" . $match[1];
            } else if (preg_match('%docs\.google\.com/presentation/d/([^/]+)%i', $url_embed, $match)) {
                $url_embed = "https://docs.google.com/presentation/d/" . $match[1] . "/embed";
            }
        }
        
        $url_embed = $conn->real_escape_string($url_embed);
        $update_fields[] = "url_embed = '$url_embed'";
        $update_fields[] = "document_path = NULL";
    } 
    else if ($content_type === 'document_upload') {
        // If file uploaded, update it, else keep old. If type changed, old data might be irrelevent, but we'll accept file input.
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'png', 'jpeg'];
            $filename = $_FILES['document_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_filename = time() . '_' . rand(1000,9999) . '.' . $ext;
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_dir . $new_filename)) {
                    $dp = $conn->real_escape_string($new_filename);
                    $update_fields[] = "document_path = '$dp'";
                    $update_fields[] = "url_embed = NULL";
                }
            } else {
                $_SESSION['error'] = 'Invalid file format.';
                header("Location: ../pages/edit_lesson.php?id=$lesson_id"); exit;
            }
        } else {
            // Keep existing if same type
            if ($existing_content_type !== 'document_upload') {
                $update_fields[] = "document_path = NULL"; // reset
            }
        }
    }
    else if ($content_type === 'quiz') {
        $update_fields[] = "url_embed = NULL";
        $update_fields[] = "document_path = NULL";
    }

    $set_sql = implode(", ", $update_fields);
    $sql = "UPDATE lessons SET $set_sql WHERE id = $lesson_id";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['success'] = 'Materi berhasil diperbarui!';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan error: ' . $conn->error;
    }
}

header("Location: ../pages/course_view.php?id=$course_id"); exit;
?>
