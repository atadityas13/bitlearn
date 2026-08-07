<?php
require_once '../core/config.php';
$r = isset($_SESSION['user_role']) ? trim(strtolower((string) $_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $r !== 'student') {
    if ($r === 'teacher')
        header("Location: teacher_dashboard.php");
    else {
        session_destroy();
        header("Location: ../index.php?error=invalid_role");
    }
    exit;
}

$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$lesson_id_to_view = isset($_GET['lesson_id']) ? (int) $_GET['lesson_id'] : 0;
$assignment_id_to_view = isset($_GET['assignment_id']) ? (int) $_GET['assignment_id'] : 0;
$quiz_id_to_view = isset($_GET['quiz_id']) ? (int) $_GET['quiz_id'] : 0;
$student_id = $_SESSION['user_id'];

require_once '../core/CourseStudents.php';
CourseStudents::ensureExclusionsTable($conn);

// Verify enrollment
$enroll_check = $conn->query("
    SELECT c.id 
    FROM courses c 
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $student_id
    LEFT JOIN course_classes cc ON c.id = cc.course_id
    LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $student_id
    LEFT JOIN course_exclusions cx ON cx.course_id = c.id AND cx.student_id = $student_id
    WHERE c.id = $course_id
      AND (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
      AND cx.id IS NULL
");
if (!$enroll_check || $enroll_check->num_rows === 0) {
    header("Location: student_dashboard.php");
    exit;
}

$course = $conn->query("SELECT title FROM courses WHERE id = $course_id")->fetch_assoc();

// Curriculum Tree
$modules_result = $conn->query("SELECT * FROM modules WHERE course_id = $course_id AND is_published = 1 ORDER BY order_num ASC, id ASC");
$curriculum = [];
$first_lesson_id = 0;
$ordered_lesson_ids = [];

if ($modules_result) {
    while ($row = $modules_result->fetch_assoc()) {
        $mod_id = $row['id'];
        $lessons_result = $conn->query("SELECT * FROM lessons WHERE module_id = $mod_id AND is_published = 1 ORDER BY order_num ASC, id ASC");
        $lessons = [];
        if ($lessons_result) {
            while ($l = $lessons_result->fetch_assoc()) {
                if ($first_lesson_id === 0)
                    $first_lesson_id = $l['id'];
                $ordered_lesson_ids[] = $l['id'];
                $prog = $conn->query("SELECT id FROM user_progress WHERE student_id = $student_id AND lesson_id = " . $l['id']);
                $l['is_completed'] = ($prog && $prog->num_rows > 0);
                $lessons[] = $l;
            }
        }
        $row['lessons'] = $lessons;
        $curriculum[] = $row;
    }
}

$assignments_result = $conn->query("SELECT * FROM assignments WHERE course_id = $course_id AND is_published = 1");
$assignments = [];
if ($assignments_result) {
    while ($a = $assignments_result->fetch_assoc()) {
        $sidx = $a['id'];
        $chk = $conn->query("SELECT id FROM submissions WHERE student_id = $student_id AND assignment_id = $sidx");
        $a['is_submitted'] = ($chk && $chk->num_rows > 0);
        $assignments[] = $a;
    }
}

if ($lesson_id_to_view === 0 && $assignment_id_to_view === 0 && $quiz_id_to_view === 0 && $first_lesson_id !== 0) {
    $lesson_id_to_view = $first_lesson_id;
}

$current_lesson = null;
$lesson_locked = false;
$missing_prereq_title = "";
$current_assignment = null;
$assignment_locked = false;
$missing_assign_prereq_title = "";

if ($lesson_id_to_view > 0) {
    $current_lesson_result = $conn->query("SELECT * FROM lessons WHERE id = $lesson_id_to_view AND is_published = 1");
    if ($current_lesson_result && $current_lesson_result->num_rows > 0) {
        $current_lesson = $current_lesson_result->fetch_assoc();
        if (!empty($current_lesson['is_prerequisite_of'])) {
            $req_id = $current_lesson['is_prerequisite_of'];
            $req_check = $conn->query("SELECT id FROM user_progress WHERE student_id = $student_id AND lesson_id = $req_id");
            if (!$req_check || $req_check->num_rows === 0) {
                $lesson_locked = true;
                $req_title_res = $conn->query("SELECT title FROM lessons WHERE id = $req_id");
                if ($req_title_res && $req_title_res->num_rows > 0) {
                    $missing_prereq_title = $req_title_res->fetch_assoc()['title'];
                }
            }
        }
    }
} else if ($assignment_id_to_view > 0) {
    $current_assignment_result = $conn->query("SELECT * FROM assignments WHERE id = $assignment_id_to_view AND is_published = 1");
    if ($current_assignment_result && $current_assignment_result->num_rows > 0) {
        $current_assignment = $current_assignment_result->fetch_assoc();

        // CHECK ASSIGNMENT PREREQUISITE SEQUENTIAL LOGIC
        if (!empty($current_assignment['is_prerequisite_of'])) {
            $req_asn_id = $current_assignment['is_prerequisite_of'];
            $req_asn_check = $conn->query("SELECT id FROM submissions WHERE student_id = $student_id AND assignment_id = $req_asn_id");
            if (!$req_asn_check || $req_asn_check->num_rows === 0) {
                $assignment_locked = true;
                $req_asn_title_res = $conn->query("SELECT title FROM assignments WHERE id = $req_asn_id");
                if ($req_asn_title_res && $req_asn_title_res->num_rows > 0) {
                    $missing_assign_prereq_title = $req_asn_title_res->fetch_assoc()['title'];
                }
            }
        }
    }
}

$is_current_completed = false;
if ($current_lesson) {
    $cl_id = $current_lesson['id'];
    $chk = $conn->query("SELECT id FROM user_progress WHERE student_id = $student_id AND lesson_id = $cl_id");
    $is_current_completed = ($chk && $chk->num_rows > 0);
}

$is_current_submitted = false;
$existing_submission = null;
if ($current_assignment) {
    $ca_id = $current_assignment['id'];
    $chk = $conn->query("SELECT * FROM submissions WHERE student_id = $student_id AND assignment_id = $ca_id");
    if ($chk && $chk->num_rows > 0) {
        $is_current_submitted = true;
        $existing_submission = $chk->fetch_assoc();
    }
}

$page_title = $course['title'] . ' | Pelajar';
$hide_navbar = true;
require_once '../components/header.php';
?>

<!-- Mobile Hamburger Button & Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="viewer-container">
    <div id="viewerSidebar" class="viewer-sidebar">
        <div style="padding:1.5rem; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.1);">
            <a href="student_dashboard.php"
                style="color:var(--text-muted); text-decoration:none; font-size:0.9rem; display:block; margin-bottom:1rem;">
                <i class="uil uil-arrow-left"></i> Kembali ke Dasbor
            </a>
            <h3 style="font-size:1.1rem; line-height:1.4;"><?php echo htmlspecialchars($course['title']); ?></h3>
        </div>

        <div style="padding:1rem;">
            <?php foreach ($curriculum as $mod): ?>
                <div style="margin-bottom:1.5rem;">
                    <h5
                        style="color:var(--text-muted); text-transform:uppercase; font-size:0.75rem; letter-spacing:1px; margin-bottom:0.8rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.4rem;">
                        <?php echo htmlspecialchars($mod['title']); ?>
                    </h5>
                    <?php foreach ($mod['lessons'] as $les): ?>
                        <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $les['id']; ?>"
                            style="display:flex; align-items:flex-start; padding:0.8rem; border-radius:var(--radius-sm); margin-bottom:0.2rem; text-decoration:none; 
                           <?php echo ($lesson_id_to_view == $les['id']) ? 'background:rgba(79, 70, 229, 0.2); border-left:3px solid var(--primary);' : 'color:var(--text-main);'; ?> transition:background 0.2s;">
                            <?php if ($les['is_completed']): ?>
                                <i class="uil uil-check-circle"
                                    style="color:var(--secondary); font-size:1.2rem; margin-right:0.5rem; margin-top:-2px;"></i>
                            <?php else: ?>
                                <i class="uil uil-circle"
                                    style="color:var(--border); font-size:1.2rem; margin-right:0.5rem; margin-top:-2px;"></i>
                            <?php endif; ?>
                            <span
                                style="font-size:0.9rem; <?php echo ($lesson_id_to_view == $les['id']) ? 'font-weight:600; color:var(--primary);' : ''; ?>">
                                <?php echo htmlspecialchars($les['title']); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php if (count($assignments) > 0): ?>
                <div style="margin-top:2rem; margin-bottom:1rem;">
                    <h5
                        style="color:var(--text-muted); text-transform:uppercase; font-size:0.75rem; letter-spacing:1px; margin-bottom:0.8rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.4rem;">
                        <i class="uil uil-clipboard-notes"></i> Penugasan
                    </h5>
                    <?php foreach ($assignments as $a): ?>
                        <a href="?course_id=<?php echo $course_id; ?>&assignment_id=<?php echo $a['id']; ?>"
                            style="display:flex; align-items:flex-start; padding:0.8rem; border-radius:var(--radius-sm); margin-bottom:0.2rem; text-decoration:none; 
                           <?php echo ($assignment_id_to_view == $a['id']) ? 'background:rgba(245, 158, 11, 0.2); border-left:3px solid var(--warning);' : 'color:var(--text-main);'; ?> transition:background 0.2s;">
                            <?php if ($a['is_submitted']): ?>
                                <i class="uil uil-file-check-alt"
                                    style="color:var(--secondary); font-size:1.2rem; margin-right:0.5rem; margin-top:-2px;"></i>
                            <?php else: ?>
                                <i class="uil uil-file-alt"
                                    style="color:var(--border); font-size:1.2rem; margin-right:0.5rem; margin-top:-2px;"></i>
                            <?php endif; ?>
                            <span
                                style="font-size:0.9rem; <?php echo ($assignment_id_to_view == $a['id']) ? 'font-weight:600; color:var(--warning);' : ''; ?>">
                                <?php echo htmlspecialchars($a['title']); ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="viewer-main">
        <button class="btn-mobile-menu" onclick="toggleSidebar()"><i class="uil uil-bars"></i></button>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="max-width:900px; margin:0 auto 2rem auto;"><i
                    class="uil uil-check-circle"></i> <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="max-width:900px; margin:0 auto 2rem auto;"><i
                    class="uil uil-exclamation-circle"></i> <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
            </div><?php endif; ?>

        <?php if ($current_assignment): ?>
            <!-- ASSIGNMENT SECTION -->
            <div style="max-width:900px; margin:0 auto;">
                <?php if ($assignment_locked): ?>
                    <div
                        style="display:flex; height:100%; align-items:center; justify-content:center; flex-direction:column; padding:2rem; text-align:center;">
                        <div
                            style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.3); padding:3rem; border-radius:var(--radius); max-width:600px;">
                            <i class="uil uil-lock"
                                style="font-size:4rem; color:var(--warning); margin-bottom:1rem; display:block;"></i>
                            <h2 style="margin-bottom:1rem;">Tugas Terkunci</h2>
                            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Tugas ini mensyaratkan Anda untuk
                                mengerjakan dan mengumpulkan jawaban untuk tugas sebelumnya.</p>
                            <p
                                style="background:var(--background); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border); font-weight:bold;">
                                <i class="uil uil-info-circle" style="color:var(--warning);"></i> Wajib Dikumpulkan:
                                <?php echo htmlspecialchars($missing_assign_prereq_title); ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div
                        style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:3rem; margin-bottom:2rem;">
                        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
                            <i class="uil uil-clipboard-notes" style="font-size:3rem; color:var(--warning);"></i>
                            <div>
                                <h1 style="font-size:2rem; margin:0;">
                                    <?php echo htmlspecialchars($current_assignment['title']); ?>
                                </h1>
                                <p
                                    style="color:var(--text-muted); margin-top:0.2rem; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <span>Batas Waktu: <b
                                            style="color:var(--danger);"><?php echo date('d M Y, H:i', strtotime($current_assignment['due_date'])); ?></b></span>
                                    <span id="assign-timer"
                                        style="background:rgba(239, 68, 68, 0.1); color:var(--danger); padding:4px 12px; border-radius:50px; font-weight:700; font-family:monospace; font-size:0.9rem; border:1px solid rgba(239, 68, 68, 0.2);">
                                        Sisa: --:--:--
                                    </span>
                                </p>
                                <script>
                                    (function () {
                                        const deadline = new Date("<?php echo $current_assignment['due_date']; ?>").getTime();
                                        function update() {
                                            const now = new Date().getTime();
                                            const diff = deadline - now;
                                            const el = document.getElementById('assign-timer');
                                            if (!el) return;
                                            if (diff <= 0) {
                                                el.innerHTML = "WAKTU HABIS";
                                                el.style.background = "var(--danger)";
                                                el.style.color = "white";
                                                return;
                                            }
                                            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                                            const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                            const s = Math.floor((diff % (1000 * 60)) / 1000);
                                            let display = "Sisa: ";
                                            if (d > 0) display += d + "h ";
                                            display += (h < 10 ? "0" + h : h) + ":" + (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
                                            el.innerHTML = display;
                                        }
                                        update();
                                        setInterval(update, 1000);
                                    })();
                                </script>
                            </div>
                        </div>

                        <div
                            style="font-size:1.1rem; line-height:1.8; color:var(--text-main); margin-bottom:3rem; background:rgba(0,0,0,0.2); padding:1.5rem; border-radius:var(--radius-sm);">
                            <?php echo nl2br(htmlspecialchars($current_assignment['description'])); ?>

                            <?php if (!empty($current_assignment['file_path'])): ?>
                                <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px dashed var(--border);">
                                    <h4 style="margin-bottom:0.5rem; color:var(--primary);"><i class="uil uil-paperclip"></i>
                                        Dokumen Referensi / Soal</h4>
                                    <div style="display:flex; gap:0.8rem; flex-wrap:wrap;">
                                        <a href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_assignment['file_path']); ?>"
                                            target="_blank" class="btn btn-primary btn-sm"><i class="uil uil-file-download-alt"></i>
                                            Unduh</a>
                                        <button onclick="openPreview('<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_assignment['file_path']); ?>', 'Soal: <?php echo addslashes($current_assignment['title']); ?>')" class="btn btn-secondary btn-sm">
                                            <i class="uil uil-eye"></i> Lihat
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h3 style="border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem; margin-bottom:1.5rem;">
                            Pengumpulan Tugas</h3>

                        <?php if ($is_current_submitted): ?>
                            <div
                                style="background:rgba(16, 185, 129, 0.1); border:1px solid rgba(16, 185, 129, 0.3); padding:2rem; border-radius:var(--radius-sm); display:flex; flex-direction:column; align-items:center; text-align:center;">
                                <i class="uil uil-check-circle"
                                    style="font-size:4rem; color:var(--secondary); margin-bottom:1rem;"></i>
                                <h3 style="color:var(--secondary); margin-bottom:0.5rem;">Tugas Sudah Dikumpulkan!</h3>
                                <p style="color:var(--text-muted); margin-bottom:1.5rem;">Waktu kumpul:
                                    <?php echo date('d M Y, H:i', strtotime($existing_submission['created_at'])); ?>
                                </p>
                                <div style="display:flex; gap:0.8rem; margin-bottom:1.5rem;">
                                    <a href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($existing_submission['file_path']); ?>"
                                        download class="btn btn-secondary btn-sm"><i class="uil uil-cloud-download"></i> Unduh</a>
                                    <button onclick="openPreview('<?php echo BASE_URL . '/uploads/' . htmlspecialchars($existing_submission['file_path']); ?>', 'Jawaban Saya')" class="btn btn-primary btn-sm">
                                        <i class="uil uil-eye"></i> Lihat Jawaban
                                    </button>
                                </div>

                                <?php if ($existing_submission['grade'] !== null): ?>
                                    <div
                                        style="margin-top:2rem; background:rgba(0,0,0,0.3); padding:1.5rem; border-radius:var(--radius-sm); border:1px solid var(--border); width:100%;">
                                        <h4 style="margin-bottom:1rem; color:var(--text-main);">Hasil Penilaian Guru</h4>
                                        <div style="font-size:2rem; font-weight:800; color:var(--primary); margin-bottom:0.5rem;">
                                            <?php echo $existing_submission['grade']; ?> / 100
                                        </div>
                                        <?php if (!empty($existing_submission['feedback'])): ?>
                                            <p style="color:var(--text-muted); font-style:italic;">
                                                "<?php echo htmlspecialchars($existing_submission['feedback']); ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form action="../actions/submit_upload.php" method="POST" enctype="multipart/form-data"
                                class="glass-card" style="padding:2rem;">
                                <input type="hidden" name="assignment_id" value="<?php echo $current_assignment['id']; ?>">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <div class="form-group">
                                    <label class="form-label">Upload Tugas</label>
                                    <input type="file" name="assignment_file" class="form-control"
                                        style="background:var(--background);" required>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="uil uil-cloud-upload"></i> Kirim
                                    Tugas</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; // End check if locked ?>
            </div>

        <?php elseif ($current_lesson): ?>
            <!-- LESSON SECTION -->
            <?php if ($lesson_locked): ?>
                <div
                    style="display:flex; height:100%; align-items:center; justify-content:center; flex-direction:column; padding:2rem; text-align:center;">
                    <div
                        style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.3); padding:3rem; border-radius:var(--radius); max-width:600px;">
                        <i class="uil uil-lock"
                            style="font-size:4rem; color:var(--warning); margin-bottom:1rem; display:block;"></i>
                        <h2 style="margin-bottom:1rem;">Pelajaran Terkunci</h2>
                        <p style="color:var(--text-muted); margin-bottom:1.5rem;">Pelajaran ini memuat restriksi Syarat. Anda
                            harus menyelesaikan materi sebelumnya (Prasyarat) terlebih dahulu.</p>
                        <p
                            style="background:var(--background); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border); font-weight:bold;">
                            <i class="uil uil-info-circle" style="color:var(--warning);"></i> Wajib Diselesaikan:
                            <?php echo htmlspecialchars($missing_prereq_title); ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div style="max-width:900px; margin:0 auto; padding-top:1rem;">
                    <h1 style="font-size:2rem; margin-bottom:1.5rem;"><?php echo htmlspecialchars($current_lesson['title']); ?>
                    </h1>
                    <?php if (!empty($current_lesson['description'])): ?>
                        <div
                            style="font-size:1.1rem; color:var(--text-main); line-height:1.8; margin-bottom:2rem; background:var(--surface); padding:1.5rem; border-radius:var(--radius-sm);">
                            <?php echo nl2br(htmlspecialchars($current_lesson['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <script>
                        // Global logic to track if the current viewing session fulfills media requirements
                        var mediaConditionMet = <?php echo $is_current_completed ? 'true' : 'false'; ?>;

                        function checkUnlockMarkBtn() {
                            if (mediaConditionMet) {
                                var btn = document.getElementById('btnMarkComplete');
                                if (btn) {
                                    <?php
                                    $next_lesson_id_js = 0;
                                    $idx_js = array_search($current_lesson['id'], $ordered_lesson_ids);
                                    if ($idx_js !== false && isset($ordered_lesson_ids[$idx_js + 1])) {
                                        $next_lesson_id_js = $ordered_lesson_ids[$idx_js + 1];
                                    }
                                    ?>
                                    var hasNext = <?php echo ($next_lesson_id_js > 0) ? 'true' : 'false'; ?>;
                                    btn.disabled = false;
                                    btn.style.opacity = '1';
                                    btn.style.background = 'var(--primary)';
                                    btn.style.borderColor = 'var(--primary)';
                                    btn.style.color = 'white';
                                    if (hasNext) {
                                        btn.innerHTML = 'Lanjut Materi <i class="uil uil-arrow-right"></i>';
                                    } else {
                                        btn.innerHTML = '<i class="uil uil-check-circle"></i> Selesai';
                                    }
                                }
                            }
                        }
                    </script>

                    <?php if ($current_lesson['content_type'] === 'video_embed' && !empty($current_lesson['url_embed'])): ?>
                        <style>
                            #video-container:fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #video-container:-webkit-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #video-container:-moz-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #video-container:-ms-fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #fullscreen-btn:hover {
                                background: rgba(0, 0, 0, 0.8) !important;
                                transform: scale(1.05);
                            }
                        </style>
                        <div id="video-container"
                            style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:var(--radius); box-shadow:0 10px 30px rgba(0,0,0,0.5); margin-bottom:2rem; background:#000;">
                            <!-- pointer-events:none prevents interaction with share button, title, and "more videos" -->
                            <div id="youtube-player"
                                style="position:absolute; top:0; left:0; width:100%; height:100%; border:none; pointer-events:none;">
                            </div>

                            <!-- Custom User Controls Overlay -->
                            <div id="video-overlay" onclick="togglePlayPause()"
                                style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; cursor:pointer; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.2); transition:all 0.3s;">
                                <!-- Sisa Waktu -->
                                <div id="time-display"
                                    style="position:absolute; top:15px; left:15px; background:rgba(0,0,0,0.5); border-radius:var(--radius-sm); padding:6px 12px; color:white; z-index:20; font-size:0.85rem; font-weight:600; font-family:monospace; transition:all 0.2s; display:flex; align-items:center; gap:5px; backdrop-filter:blur(4px);">
                                    <i class="uil uil-clock"></i> <span id="time-text">-00:00</span>
                                </div>

                                <div id="play-pause-icon"
                                    style="background:rgba(0,0,0,0.6); width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:all 0.2s; backdrop-filter:blur(4px);">
                                    <i id="control-icon" class="uil uil-play"
                                        style="font-size:3rem; color:white; margin-left:5px;"></i>
                                </div>

                                <!-- Tombol Fullscreen -->
                                <div id="fullscreen-btn" onclick="toggleFullScreen(event)"
                                    style="position:absolute; bottom:15px; right:15px; background:rgba(0,0,0,0.5); border-radius:var(--radius-sm); padding:8px 12px; color:white; z-index:20; transition:all 0.2s; display:flex; align-items:center; gap:5px;">
                                    <i id="fs-icon" class="uil uil-expand-arrows" style="font-size:1.2rem;"></i> <span id="fs-text"
                                        style="font-size:0.8rem; font-weight:600;">Layar Penuh</span>
                                </div>
                            </div>
                        </div>

                        <!-- YouTube Logic -->
                        <script src="https://www.youtube.com/iframe_api"></script>
                        <script>
                            var player;
                            var timeInterval;

                            function formatTime(seconds) {
                                if (!seconds || isNaN(seconds)) return "00:00";
                                seconds = Math.floor(seconds);
                                var m = Math.floor(seconds / 60);
                                var s = seconds % 60;
                                return (m < 10 ? "0" + m : m) + ":" + (s < 10 ? "0" + s : s);
                            }

                            function updateTimeDisplay() {
                                if (player && player.getCurrentTime && player.getDuration) {
                                    var current = player.getCurrentTime();
                                    var duration = player.getDuration();
                                    var remaining = duration - current;
                                    if (remaining < 0) remaining = 0;
                                    document.getElementById('time-text').innerText = "-" + formatTime(remaining);
                                }
                            }

                            function onYouTubeIframeAPIReady() {
                                var url = "<?php echo htmlspecialchars($current_lesson['url_embed']); ?>";
                                var videoId = "INVALID";

                                // Parse various YouTube URL formats
                                var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#\&\?]*).*/;
                                var match = url.match(regExp);
                                if (match && match[2].length == 11) {
                                    videoId = match[2];
                                }

                                player = new YT.Player('youtube-player', {
                                    videoId: videoId,
                                    playerVars: {
                                        'controls': 0,      // Sembunyikan timeline/seekbar agar tidak bisa di-skip
                                        'disablekb': 1,     // Matikan jalan pintas keyboard (panah kanan untuk skip)
                                        'rel': 0,           // Jangan tampilkan video terkait di akhir
                                        'modestbranding': 1,// Kurangi logo YouTube
                                        'showinfo': 0,      // Sembunyikan judul
                                        'iv_load_policy': 3,// Sembunyikan anotasi
                                        'fs': 0,            // Matikan fullscreen native
                                        'playsinline': 1
                                    },
                                    events: {
                                        'onStateChange': onPlayerStateChange
                                    }
                                });
                            }

                            function togglePlayPause() {
                                if (player && player.getPlayerState) {
                                    var state = player.getPlayerState();
                                    if (state === YT.PlayerState.PLAYING) {
                                        player.pauseVideo();
                                    } else {
                                        player.playVideo();
                                    }
                                }
                            }

                            function onPlayerStateChange(event) {
                                var iconWrapper = document.getElementById('play-pause-icon');
                                var icon = document.getElementById('control-icon');
                                var overlay = document.getElementById('video-overlay');
                                var fsBtn = document.getElementById('fullscreen-btn');
                                var timeDisp = document.getElementById('time-display');

                                if (event.data == YT.PlayerState.PLAYING) {
                                    icon.className = 'uil uil-pause';
                                    icon.style.marginLeft = '0';
                                    iconWrapper.style.opacity = '0';
                                    overlay.style.background = 'transparent';
                                    fsBtn.style.opacity = '0'; // Sembunyikan tombol FS saat play biasa
                                    timeDisp.style.opacity = '0'; // Sembunyikan timer saat play biasa

                                    clearInterval(timeInterval);
                                    timeInterval = setInterval(updateTimeDisplay, 1000);
                                    updateTimeDisplay();
                                } else if (event.data == YT.PlayerState.PAUSED || event.data == YT.PlayerState.UNSTARTED) {
                                    icon.className = 'uil uil-play';
                                    icon.style.marginLeft = '5px';
                                    iconWrapper.style.opacity = '1';
                                    overlay.style.background = 'rgba(0,0,0,0.3)';
                                    fsBtn.style.opacity = '1';
                                    timeDisp.style.opacity = '1';

                                    clearInterval(timeInterval);
                                    updateTimeDisplay();
                                } else if (event.data == YT.PlayerState.ENDED) {
                                    icon.className = 'uil uil-redo'; // Ikon replay 
                                    icon.style.marginLeft = '0';
                                    iconWrapper.style.opacity = '1';
                                    overlay.style.background = 'rgba(0,0,0,0.6)';
                                    fsBtn.style.opacity = '1';
                                    timeDisp.style.opacity = '1';

                                    clearInterval(timeInterval);
                                    updateTimeDisplay();

                                    mediaConditionMet = true;
                                    checkUnlockMarkBtn();
                                }
                            }

                            document.getElementById('video-overlay').addEventListener('mouseenter', function () {
                                if (player && player.getPlayerState && player.getPlayerState() === YT.PlayerState.PLAYING) {
                                    document.getElementById('play-pause-icon').style.opacity = '0.5';
                                    document.getElementById('video-overlay').style.background = 'rgba(0,0,0,0.1)';
                                    document.getElementById('fullscreen-btn').style.opacity = '1';
                                    document.getElementById('time-display').style.opacity = '1';
                                }
                            });
                            document.getElementById('video-overlay').addEventListener('mouseleave', function () {
                                if (player && player.getPlayerState && player.getPlayerState() === YT.PlayerState.PLAYING) {
                                    document.getElementById('play-pause-icon').style.opacity = '0';
                                    document.getElementById('video-overlay').style.background = 'transparent';
                                    document.getElementById('fullscreen-btn').style.opacity = '0';
                                    document.getElementById('time-display').style.opacity = '0';
                                }
                            });

                            // --- FULLSCREEN LOGIC ---
                            function toggleFullScreen(e) {
                                e.stopPropagation(); // Mencegah klik play/pause saat klik tombol fullscreen
                                var container = document.getElementById('video-container');

                                if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                                    if (container.requestFullscreen) {
                                        container.requestFullscreen();
                                    } else if (container.mozRequestFullScreen) { /* Firefox */
                                        container.mozRequestFullScreen();
                                    } else if (container.webkitRequestFullscreen) { /* Chrome, Safari and Opera */
                                        container.webkitRequestFullscreen();
                                    } else if (container.msRequestFullscreen) { /* IE/Edge */
                                        container.msRequestFullscreen();
                                    }
                                } else {
                                    if (document.exitFullscreen) {
                                        document.exitFullscreen();
                                    } else if (document.mozCancelFullScreen) { /* Firefox */
                                        document.mozCancelFullScreen();
                                    } else if (document.webkitExitFullscreen) { /* Chrome, Safari and Opera */
                                        document.webkitExitFullscreen();
                                    } else if (document.msExitFullscreen) { /* IE/Edge */
                                        document.msExitFullscreen();
                                    }
                                }
                            }

                            // Update icon and text based on actual fullscreen state (handles ESC key too)
                            function updateFullscreenUI() {
                                var fsIcon = document.getElementById('fs-icon');
                                var fsText = document.getElementById('fs-text');
                                if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                                    fsIcon.className = 'uil uil-expand-arrows';
                                    fsText.innerText = 'Layar Penuh';
                                } else {
                                    fsIcon.className = 'uil uil-compress-arrows';
                                    fsText.innerText = 'Tutup Layar';
                                }
                            }

                            document.addEventListener('fullscreenchange', updateFullscreenUI);
                            document.addEventListener('mozfullscreenchange', updateFullscreenUI);
                            document.addEventListener('webkitfullscreenchange', updateFullscreenUI);
                            document.addEventListener('MSFullscreenChange', updateFullscreenUI);
                        </script>

                    <?php elseif ($current_lesson['content_type'] === 'document_upload' && !empty($current_lesson['document_path'])): ?>
                        <?php
                        $docExt = strtolower(pathinfo($current_lesson['document_path'], PATHINFO_EXTENSION));
                        ?>
                        <div class="glass-card" style="text-align:center; padding:3rem; margin-bottom:2rem;">
                            <?php if (in_array($docExt, ['pdf'])): ?>
                                <!-- Attempting PDF embed. We require them to be here for at least 5 seconds, assuming they scroll through -->
                                <object
                                    data="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_lesson['document_path']); ?>"
                                    type="application/pdf" width="100%" height="600px">
                                    <p>PDF tidak dapat ditampilkan langsung. <a
                                            href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_lesson['document_path']); ?>">Unduh
                                            file PDF</a></p>
                                </object>
                                <script>
                                    setTimeout(function () {
                                        mediaConditionMet = true; checkUnlockMarkBtn();
                                    }, 30000); // 30 seconds
                                </script>
                            <?php elseif (in_array($docExt, ['jpg', 'jpeg', 'png'])): ?>
                                <!-- Images -->
                                <img src="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_lesson['document_path']); ?>"
                                    style="max-width:100%; border-radius:var(--radius-sm);">
                                <script>
                                    setTimeout(function () {
                                        mediaConditionMet = true; checkUnlockMarkBtn();
                                    }, 2000);
                                </script>
                            <?php else: ?>
                                <!-- Require Download -->
                                <i class="uil uil-file-download-alt"
                                    style="font-size:4rem; color:var(--primary); margin-bottom:1rem; display:block;"></i>
                                <h3>Lampiran Dokumen</h3>
                                <p style="color:var(--text-muted); margin-bottom:1.5rem;">Sistem mengharuskan Anda untuk menekan tombol
                                    pengunduhan sebelum dianggap selesai.</p>
                                <a href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($current_lesson['document_path']); ?>"
                                    download class="btn btn-primary" onclick="mediaConditionMet=true; checkUnlockMarkBtn();">
                                    <i class="uil uil-download-alt"></i> Unduh File Terlampir
                                </a>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($current_lesson['content_type'] === 'slideshow' && !empty($current_lesson['url_embed'])): ?>
                        <style>
                            #slide-container:fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #slide-container:-webkit-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #slide-container:-moz-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #slide-container:-ms-fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }
                        </style>

                        <div id="slide-container"
                            style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:var(--radius); box-shadow:0 10px 30px rgba(0,0,0,0.5); margin-bottom:2rem; background:#000;">

                            <iframe src="<?php echo htmlspecialchars($current_lesson['url_embed']); ?>" frameborder="0" width="100%"
                                height="100%" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"
                                style="position:absolute; top:0; left:0; width:100%; height:100%;">
                            </iframe>

                            <div id="slide-overlay"
                                style="position:absolute; bottom:0; left:0; width:100%; height:80px; z-index:10; pointer-events:none; display:flex; align-items:flex-end; justify-content:flex-end; padding:15px;">
                                <div id="fullscreen-btn-slide" onclick="toggleFullScreenSlide(event)"
                                    style="pointer-events:auto; background:rgba(0,0,0,0.5); border-radius:var(--radius-sm); padding:8px 12px; color:white; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:5px; backdrop-filter:blur(4px);">
                                    <i id="fs-icon-slide" class="uil uil-expand-arrows" style="font-size:1.2rem;"></i> <span
                                        id="fs-text" style="font-size:0.8rem; font-weight:600;">Layar Penuh</span>
                                </div>
                            </div>
                        </div>

                        <script>
                            setTimeout(function () {
                                mediaConditionMet = true;
                                checkUnlockMarkBtn();
                            }, 300000); // minimal 5 menit menonton slides

                            function toggleFullScreenSlide(e) {
                                e.stopPropagation();
                                var container = document.getElementById('slide-container');

                                if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                                    if (container.requestFullscreen) {
                                        container.requestFullscreen();
                                    } else if (container.msRequestFullscreen) {
                                        container.msRequestFullscreen();
                                    } else if (container.mozRequestFullScreen) {
                                        container.mozRequestFullScreen();
                                    } else if (container.webkitRequestFullscreen) {
                                        container.webkitRequestFullscreen();
                                    }
                                } else {
                                    if (document.exitFullscreen) {
                                        document.exitFullscreen();
                                    } else if (document.msExitFullscreen) {
                                        document.msExitFullscreen();
                                    } else if (document.mozCancelFullScreen) {
                                        document.mozCancelFullScreen();
                                    } else if (document.webkitExitFullscreen) {
                                        document.webkitExitFullscreen();
                                    }
                                }
                            }

                            function updateSlideFullscreenUI() {
                                var isFS = document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
                                var icon = document.getElementById('fs-icon-slide');
                                if (isFS) {
                                    icon.className = 'uil uil-compress-arrows';
                                } else {
                                    icon.className = 'uil uil-expand-arrows';
                                }
                            }
                            document.addEventListener('fullscreenchange', updateSlideFullscreenUI);
                            document.addEventListener('mozfullscreenchange', updateSlideFullscreenUI);
                            document.addEventListener('webkitfullscreenchange', updateSlideFullscreenUI);
                            document.addEventListener('MSFullscreenChange', updateSlideFullscreenUI);
                        </script>
                    <?php elseif ($current_lesson['content_type'] === 'pdf_embed' && !empty($current_lesson['url_embed'])): ?>
                        <style>
                            #pdf-container:fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #pdf-container:-webkit-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #pdf-container:-moz-full-screen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }

                            #pdf-container:-ms-fullscreen {
                                padding-bottom: 0 !important;
                                height: 100vh !important;
                                border-radius: 0 !important;
                            }
                        </style>

                        <div id="pdf-container"
                            style="position:relative; padding-bottom:75%; height:0; overflow:hidden; border-radius:var(--radius); box-shadow:0 10px 30px rgba(0,0,0,0.5); margin-bottom:2rem; background:#fff;">
                            <iframe src="<?php echo htmlspecialchars($current_lesson['url_embed']); ?>" frameborder="0" width="100%"
                                height="100%" allowfullscreen="true"
                                style="position:absolute; top:0; left:0; width:100%; height:100%;">
                            </iframe>

                            <div
                                style="position:absolute; bottom:0; left:0; width:100%; height:60px; z-index:10; pointer-events:none; display:flex; align-items:flex-end; justify-content:flex-end; padding:12px;">
                                <div onclick="toggleFullScreenPdf(event)"
                                    style="pointer-events:auto; background:rgba(0,0,0,0.55); border-radius:var(--radius-sm); padding:8px 12px; color:white; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:5px; backdrop-filter:blur(4px);">
                                    <i id="fs-icon-pdf" class="uil uil-expand-arrows" style="font-size:1.2rem;"></i>
                                    <span style="font-size:0.8rem; font-weight:600;">Layar Penuh</span>
                                </div>
                            </div>
                        </div>

                        <script>
                            setTimeout(function () {
                                mediaConditionMet = true;
                                checkUnlockMarkBtn();
                            }, 300000); // minimal 5 menit membaca PDF

                            function toggleFullScreenPdf(e) {
                                e.stopPropagation();
                                var container = document.getElementById('pdf-container');
                                if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                                    if (container.requestFullscreen) container.requestFullscreen();
                                    else if (container.msRequestFullscreen) container.msRequestFullscreen();
                                    else if (container.mozRequestFullScreen) container.mozRequestFullScreen();
                                    else if (container.webkitRequestFullscreen) container.webkitRequestFullscreen();
                                } else {
                                    if (document.exitFullscreen) document.exitFullscreen();
                                    else if (document.msExitFullscreen) document.msExitFullscreen();
                                    else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
                                    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                                }
                            }
                            function updatePdfFullscreenUI() {
                                var isFS = document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
                                document.getElementById('fs-icon-pdf').className = isFS ? 'uil uil-compress-arrows' : 'uil uil-expand-arrows';
                            }
                            document.addEventListener('fullscreenchange', updatePdfFullscreenUI);
                            document.addEventListener('mozfullscreenchange', updatePdfFullscreenUI);
                            document.addEventListener('webkitfullscreenchange', updatePdfFullscreenUI);
                            document.addEventListener('MSFullscreenChange', updatePdfFullscreenUI);
                        </script>
                    <?php elseif ($current_lesson['content_type'] === 'document_upload' || $current_lesson['content_type'] === 'ppt_slideshow'): ?>
                        <div class="glass-card" style="text-align:center; padding:3rem; margin-bottom:2rem; border-color:var(--primary);">
                            <?php 
                            $doc_file = '';
                            if ($current_lesson['content_type'] === 'document_upload') {
                                $doc_file = $current_lesson['url_embed']; // for local uploads, url_embed stores filename
                            } else {
                                $ppt_data = json_decode($current_lesson['url_embed'], true);
                                $doc_file = $ppt_data['file'] ?? '';
                            }
                            ?>
                            
                            <?php if ($current_lesson['content_type'] === 'ppt_slideshow'): ?>
                                <i class="uil uil-exclamation-octagon" style="font-size:4rem; color:var(--warning); display:block; margin-bottom:1.5rem;"></i>
                                <h3 style="color:var(--text-main); margin-bottom:1rem;">Materi PowerPoint (Legacy)</h3>
                                <p style="color:var(--text-muted); max-width:500px; margin:0 auto 2rem;">
                                    Fitur peragaan PowerPoint langsung telah dinonaktifkan. Anda dapat melihat file ini 
                                    secara langsung atau mengunduhnya untuk dibuka di perangkat Anda.
                                </p>
                            <?php else: ?>
                                <i class="uil uil-file-alt" style="font-size:4rem; color:var(--primary); display:block; margin-bottom:1.5rem;"></i>
                                <h3>Materi Berkas Dokumen</h3>
                                <p style="color:var(--text-muted); margin-bottom:2rem;">Pengajar menyematkan dokumen untuk dipelajari pada topik ini.</p>
                            <?php endif; ?>

                            <div style="display:flex; gap:1rem; justify-content:center;">
                                <?php if (!empty($doc_file)): ?>
                                    <button onclick="openPreview('<?php echo BASE_URL . '/uploads/' . $doc_file; ?>', 'Materi: <?php echo addslashes($current_lesson['title']); ?>')" class="btn btn-primary">
                                        <i class="uil uil-eye"></i> Lihat Materi
                                    </button>
                                    <a href="<?php echo BASE_URL . '/uploads/' . $doc_file; ?>" download class="btn btn-secondary">
                                        <i class="uil uil-download-alt"></i> Unduh File
                                    </a>
                                <?php else: ?>
                                    <p style="color:var(--danger);">File tidak ditemukan atau belum diunggah.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <script>
                            // local files mark as complete after 10 seconds of 'viewing'
                            setTimeout(function () {
                                mediaConditionMet = true;
                                checkUnlockMarkBtn();
                            }, 10000); 
                        </script>
                    <?php elseif ($current_lesson['content_type'] === 'quiz'): ?>
                        <div class="glass-card"
                            style="text-align:center; padding:3rem; margin-bottom:2rem; border-color:var(--primary);">
                            <i class="uil uil-clipboard-blank"
                                style="font-size:4rem; color:var(--primary); margin-bottom:1rem; display:block;"></i>
                            <h3>Sesi Kuis / Evaluasi Konsep</h3>
                            <p style="color:var(--text-muted); margin-bottom:1.5rem;">Modul topik ini mencakup evaluasi pengetahuan.
                                Selesaikan kuis untuk membuka ceklis.</p>
                            <!-- Kuis tidak ada 'Mark Complete' melainkan ditandai otomatis setelah kerjakan kuis via backend! -->
                            <a href="quiz_take.php?lesson_id=<?php echo $current_lesson['id']; ?>" class="btn btn-primary"
                                style="background:#8b5cf6;">
                                <i class="uil uil-pen"></i> Kerjakan Ujian Sekarang
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Completion Form area -->
                    <?php if ($current_lesson['content_type'] !== 'quiz'): ?>
                        <?php
                        $next_lesson_id = 0;
                        $idx = array_search($current_lesson['id'], $ordered_lesson_ids);
                        if ($idx !== false && isset($ordered_lesson_ids[$idx + 1])) {
                            $next_lesson_id = $ordered_lesson_ids[$idx + 1];
                        }
                        ?>
                        <div
                            style="margin-top:4rem; padding-top:2rem; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                            <?php if ($is_current_completed): ?>
                                <?php if ($next_lesson_id == 0): ?>
                                    <div
                                        style="color:var(--secondary); font-weight:bold; display:flex; align-items:center; gap:0.5rem; justify-content:center; width:100%;">
                                        <i class="uil uil-check-circle" style="font-size:1.5rem;"></i> Materi telah Anda pelajari.
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <form action="../actions/mark_completed.php" method="POST"
                                    style="width:100%; display:flex; justify-content:<?php echo ($next_lesson_id > 0) ? 'flex-end' : 'center'; ?>;">
                                    <input type="hidden" name="lesson_id" value="<?php echo $current_lesson['id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <input type="hidden" name="next_lesson_id" value="<?php echo $next_lesson_id; ?>">
                                    <button type="submit" id="btnMarkComplete" class="btn btn-secondary"
                                        style="border-color:var(--secondary); color:var(--secondary); transition:all 0.3s;" <?php echo $is_current_completed ? '' : 'disabled style="opacity:0.4; cursor:not-allowed;"'; ?>>
                                        <i class="uil uil-lock"></i> Materi belum selesai!
                                    </button>
                                </form>
                                <script>
                                    // re-check just in case page reloads or logic fast-fires
                                    checkUnlockMarkBtn();
                                </script>
                            <?php endif; ?>

                            <?php if ($next_lesson_id > 0 && $is_current_completed): ?>
                                <a href="?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $next_lesson_id; ?>"
                                    class="btn btn-primary">
                                    Lanjut Materi <i class="uil uil-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; // not quiz ?>
                </div>
            <?php endif; ?>
        <?php elseif (!$current_lesson && !$current_assignment): ?>
            <div
                style="display:flex; height:100%; align-items:center; justify-content:center; color:var(--text-muted); flex-direction:column;">
                <i class="uil uil-books" style="font-size:5rem;"></i>
                <h2 style="color:var(--text-main); margin-top:1rem;">Selamat Datang di Portal Kelas!</h2>
                <p>Telusuri pohon mata pelajaran di menu sebelah kiri Anda.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('viewerSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
}
</script>

<?php require_once '../components/footer.php'; ?>