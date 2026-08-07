<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_id = (int)$_POST['module_id'];
    $course_id = (int)$_POST['course_id']; // just for routing back
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $content_type = $conn->real_escape_string($_POST['content_type']);
    $url_embed = $conn->real_escape_string(trim($_POST['url_embed']));
    
    $is_prerequisite_of = empty($_POST['is_prerequisite_of']) ? "NULL" : (int)$_POST['is_prerequisite_of'];
    $document_path = "";

    if (empty($title)) {
        $_SESSION['error'] = 'Lesson title is required.';
        header("Location: ../pages/add_lesson.php?module_id=$module_id&course_id=$course_id"); exit;
    }

    // Handle Document Upload
    if ($content_type === 'document_upload' && isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'png', 'jpeg'];
        $filename = $_FILES['document_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = time() . '_' . rand(1000,9999) . '.' . $ext;
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_dir . $new_filename)) {
                $document_path = $conn->real_escape_string($new_filename);
                $url_embed = ""; // Reset embed if doc uploaded
            } else {
                $_SESSION['error'] = 'Failed to upload document.';
                header("Location: ../pages/add_lesson.php?module_id=$module_id&course_id=$course_id"); exit;
            }
        } else {
            $_SESSION['error'] = 'Invalid file type.';
            header("Location: ../pages/add_lesson.php?module_id=$module_id&course_id=$course_id"); exit;
        }
    } else if ($content_type === 'video_embed' || $content_type === 'slideshow' || $content_type === 'pdf_embed') {
        // Simple extraction logic if they pasted full embed code instead of just URL
        if(preg_match('/src="([^"]+)"/', $url_embed, $match)) {
            $url_embed = $conn->real_escape_string($match[1]);
        }
        
        if ($content_type === 'video_embed') {
            // YouTube Autoconverter Logic!
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url_embed, $match)) {
                $video_id = $match[1];
                $url_embed = "https://www.youtube.com/embed/" . $video_id . "?enablejsapi=1";
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
    }

    $doc_path_val = empty($document_path) ? "NULL" : "'$document_path'";

    $is_published = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 1;

    require_once '../core/LessonSettings.php';
    LessonSettings::ensureDwellColumn($conn);
    $dwell_minutes = LessonSettings::parseDwellMinutesFromPost($_POST, 1);

    $sql = "INSERT INTO lessons (module_id, title, description, content_type, url_embed, document_path, is_prerequisite_of, is_published, dwell_minutes) 
            VALUES ($module_id, '$title', '$description', '$content_type', '$url_embed', $doc_path_val, $is_prerequisite_of, $is_published, $dwell_minutes)";
            
    if ($conn->query($sql) === TRUE) {
        $lesson_id = $conn->insert_id;
        $_SESSION['success'] = 'Materi berhasil ditambahkan!';
        if ($content_type === 'quiz') {
            header("Location: ../pages/builder_quiz.php?lesson_id=$lesson_id&course_id=$course_id"); exit;
        }
    } else {
        $_SESSION['error'] = 'Gagal menyimpan error: ' . $conn->error;
    }
}

header("Location: ../pages/course_view.php?id=$course_id"); exit;
?>
