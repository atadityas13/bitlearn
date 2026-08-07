<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../index.php");
    exit;
}

$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$teacher_id = $_SESSION['user_id'];
CourseStudents::ensureExclusionsTable($conn);

// Get course data
$course_result = $conn->query("SELECT * FROM courses WHERE id = $course_id AND teacher_id = $teacher_id");
if (!$course_result || $course_result->num_rows === 0) {
    header("Location: manage_courses.php");
    exit;
}
$course = $course_result->fetch_assoc();

// Get modules and lessons
$modules_result = $conn->query("SELECT * FROM modules WHERE course_id = $course_id ORDER BY order_num ASC, id ASC");
$modules = [];
while ($row = $modules_result->fetch_assoc()) {
    $row['lessons'] = [];
    $modules[$row['id']] = $row;
}
if (!empty($modules)) {
    $module_ids = implode(',', array_keys($modules));
    $lessons_result = $conn->query("SELECT * FROM lessons WHERE module_id IN ($module_ids) ORDER BY order_num ASC, id ASC");
    if ($lessons_result) {
        while ($lesson = $lessons_result->fetch_assoc()) {
            $modules[$lesson['module_id']]['lessons'][] = $lesson;
        }
    }
}

// Get Assignments
$assign_qs = $conn->query("SELECT * FROM assignments WHERE course_id = $course_id ORDER BY id DESC");

// Analytics: Total lessons visible to students (published module + published lesson)
$total_lessons_query = $conn->query("
    SELECT COUNT(l.id) as sum 
    FROM lessons l 
    JOIN modules m ON l.module_id = m.id 
    WHERE m.course_id = $course_id
      AND m.is_published = 1
      AND l.is_published = 1
");
$all_lesson_count = $total_lessons_query ? (int)$total_lessons_query->fetch_assoc()['sum'] : 0;

// Analytics: Student Progress Tracker Pagination + Search
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page_num < 1) $page_num = 1;
$search_q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$search_sql = '';
if ($search_q !== '') {
    $safe = $conn->real_escape_string($search_q);
    $search_sql = " AND (u.name LIKE '%$safe%' OR u.username LIKE '%$safe%') ";
}
$limit = 10;
$offset = ($page_num - 1) * $limit;

// Get Total Students Enrolled (exclude yang di-unenroll)
$total_students_qs = $conn->query("
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    JOIN (
        SELECT student_id FROM enrollments WHERE course_id = $course_id
        UNION
        SELECT cs.student_id FROM course_classes cc 
        JOIN class_students cs ON cc.class_id = cs.class_id 
        WHERE cc.course_id = $course_id
    ) AS enrolled ON u.id = enrolled.student_id
    LEFT JOIN course_exclusions cx ON cx.course_id = $course_id AND cx.student_id = u.id
    WHERE u.role = 'student'
      AND cx.id IS NULL
      $search_sql
");
$total_students_val = $total_students_qs ? (int)$total_students_qs->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_students_val / $limit));
if ($page_num > $total_pages) $page_num = $total_pages;
$offset = ($page_num - 1) * $limit;

$students_progress = [];
$tracker_query = $conn->query("
    SELECT DISTINCT
        u.id, 
        u.name, 
        u.username, 
        u.profile_pic,
        (
            SELECT COUNT(p.id) 
            FROM user_progress p 
            JOIN lessons l ON p.lesson_id = l.id 
            JOIN modules m ON l.module_id = m.id 
            WHERE m.course_id = $course_id AND p.student_id = u.id
              AND m.is_published = 1
              AND l.is_published = 1
        ) as completed_count,
        (
            SELECT MAX(p.id)
            FROM user_progress p 
            JOIN lessons l ON p.lesson_id = l.id 
            JOIN modules m ON l.module_id = m.id 
            WHERE m.course_id = $course_id AND p.student_id = u.id
              AND m.is_published = 1
              AND l.is_published = 1
        ) as last_completed
    FROM users u
    JOIN (
        SELECT student_id FROM enrollments WHERE course_id = $course_id
        UNION
        SELECT cs.student_id FROM course_classes cc 
        JOIN class_students cs ON cc.class_id = cs.class_id 
        WHERE cc.course_id = $course_id
    ) AS enrolled ON u.id = enrolled.student_id
    LEFT JOIN course_exclusions cx ON cx.course_id = $course_id AND cx.student_id = u.id
    WHERE u.role = 'student'
      AND cx.id IS NULL
      $search_sql
    ORDER BY completed_count DESC, last_completed DESC, u.name ASC
    LIMIT $limit OFFSET $offset
");
if ($tracker_query) {
    while($s = $tracker_query->fetch_assoc()){
        $students_progress[] = $s;
    }
}

$page_title = 'Panel Course: ' . $course['title'];
require_once '../components/header.php';
?>

<div class="container main-content" style="padding-top:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:2rem;">
        <div>
            <h2><i class="uil uil-book-open"></i> <?php echo htmlspecialchars($course['title']); ?></h2>
            <p style="color:var(--text-muted); margin-top:0.5rem; max-width:800px;">
                <?php echo nl2br(htmlspecialchars($course['description'])); ?>
            </p>
        </div>
        <div style="display:flex; gap:1rem;">
            <button onclick="document.getElementById('modalAddModule').classList.add('active')" class="btn btn-primary" style="box-shadow:0 4px 15px rgba(79, 70, 229, 0.4);">
                <i class="uil uil-layer-group"></i> Buat Modul Baru
            </button>
            <a href="manage_courses.php" class="btn btn-secondary">
                <i class="uil uil-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>
    </div>

    <?php /* Notifikasi sukses/gagal ditampilkan via SweetAlert di footer */ ?>

    <div class="grid course-layout-grid">
        <!-- Area Kurikulum (Modul & Materi) -->
        <div>
            <!-- Assignments List First -->
            <div class="glass-card" style="margin-bottom:2rem;">
                <div
                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:1rem;">
                    <h3 style="margin:0;"><i class="uil uil-clipboard-notes"
                            style="color:var(--warning); margin-right:0.5rem;"></i> Penugasan / Ulangan</h3>
                    <a href="add_assignment.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary"
                        style="padding:0.4rem 1rem; font-size:0.9rem; background:var(--warning); border:none; box-shadow:0 4px 15px rgba(245, 158, 11, 0.4);">
                        <i class="uil uil-plus"></i> Buat Tugas
                    </a>
                </div>

                <?php if ($assign_qs && $assign_qs->num_rows > 0): ?>
                    <ul style="list-style:none; padding:0;">
                        <?php while ($a = $assign_qs->fetch_assoc()): ?>
                            <li
                                style="padding:1rem; background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.2); border-radius:var(--radius-sm); margin-bottom:0.8rem; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong style="display:block; margin-bottom:0.2rem; color:var(--text-main); <?php echo !$a['is_published'] ? 'opacity:0.5;' : ''; ?>">
                                        <?php echo htmlspecialchars($a['title']); ?>
                                        <?php if(!$a['is_published']): ?>
                                            <span style="font-size:0.7rem; background:var(--border); color:var(--text-muted); padding:2px 6px; border-radius:4px; margin-left:5px; vertical-align:middle;">DRAFT</span>
                                        <?php endif; ?>
                                    </strong>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><i class="uil uil-calender"></i>
                                        Tenggat: <?php echo date('d M Y, H:i', strtotime($a['due_date'])); ?></span>
                                </div>
                                <div style="display:flex; gap:0.5rem; margin:0;">
                                    <button type="button" onclick="toggleStatus('assignment', <?php echo $a['id']; ?>)" class="btn btn-secondary btn-sm"
                                        style="padding:0.3rem 0.6rem; border-color:var(--border); color:<?php echo $a['is_published'] ? 'var(--secondary)' : 'var(--text-muted)'; ?>;" title="<?php echo $a['is_published'] ? 'Sembunyikan dari Siswa' : 'Tampilkan ke Siswa'; ?>">
                                        <i class="uil <?php echo $a['is_published'] ? 'uil-eye' : 'uil-eye-slash'; ?>"></i>
                                    </button>
                                    <a href="edit_assignment.php?id=<?php echo $a['id']; ?>" class="btn btn-secondary btn-sm"
                                        style="padding:0.3rem 0.6rem; border-color:var(--warning); color:var(--warning);"><i
                                            class="uil uil-pen"></i></a>
                                    <form action="../actions/delete_assignment.php" method="POST"
                                        data-confirm="Hapus penugasan ini secara permanen?" style="margin:0;">
                                        <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:0.3rem 0.6rem;"><i
                                                class="uil uil-trash-alt"></i></button>
                                    </form>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:var(--text-muted); font-size:0.9rem; font-style:italic;">Belum ada Ulangan / Tugas yang
                        dirancang.</p>
                <?php endif; ?>
            </div>

            <!-- Modules List -->
            <?php if (empty($modules)): ?>
                <div class="glass-card" style="text-align:center; padding:3rem; border:1px dashed var(--border);">
                    <i class="uil uil-layer-group" style="font-size:3rem; color:var(--text-muted);"></i>
                    <h3>Belum ada Bab Konsep</h3>
                    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Buat "Modul Baru" di panel sisi kanan untuk
                        menampung RPP materi Anda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($modules as $mod): ?>
                    <div class="glass-card" style="margin-bottom:1.5rem; padding:1.5rem;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:1rem;">
                            <h3 style="margin:0;"><i class="uil uil-layer-group"
                                    style="color:var(--primary); margin-right:0.5rem;"></i>
                                <span id="mod_title_<?php echo $mod['id']; ?>" style="<?php echo !$mod['is_published'] ? 'opacity:0.5;' : ''; ?>">
                                    <?php echo htmlspecialchars($mod['title']); ?>
                                    <?php if(!$mod['is_published']): ?>
                                        <span style="font-size:0.75rem; background:var(--border); color:var(--text-muted); padding:2px 8px; border-radius:4px; margin-left:10px; vertical-align:middle; font-weight:normal;">DRAFT</span>
                                    <?php endif; ?>
                                </span>
                            </h3>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <!-- Toggle Status -->
                                <button type="button" onclick="toggleStatus('module', <?php echo $mod['id']; ?>)" class="btn btn-secondary btn-sm" style="padding:0.4rem 0.6rem; color:<?php echo $mod['is_published'] ? 'var(--secondary)' : 'var(--text-muted)'; ?>; border-color:var(--border);" title="<?php echo $mod['is_published'] ? 'Sembunyikan Seluruh Bab' : 'Tampilkan Bab'; ?>">
                                    <i class="uil <?php echo $mod['is_published'] ? 'uil-eye' : 'uil-eye-slash'; ?>"></i>
                                </button>
                                <!-- Edit Tombol -->
                                <button type="button" onclick="editModule('<?php echo $mod['id']; ?>', '<?php echo addslashes(htmlspecialchars($mod['title'])); ?>', <?php echo $mod['is_published'] ? 'true' : 'false'; ?>)" class="btn btn-secondary btn-sm" style="padding:0.4rem 0.6rem; color:var(--warning); border-color:rgba(245, 158, 11, 0.3);" title="Ubah Nama Modul">
                                    <i class="uil uil-pen"></i>
                                </button>
                                
                                <!-- Hapus Tombol -->
                                <form action="../actions/delete_module.php" method="POST" data-confirm="Hapus Modul ini beserta SELURUH MATERI yang menempel di dalamnya secara permanen?" style="margin:0;">
                                    <input type="hidden" name="module_id" value="<?php echo $mod['id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding:0.4rem 0.6rem;" title="Hapus Modul"><i class="uil uil-trash-alt"></i></button>
                                </form>

                                <a href="add_lesson.php?module_id=<?php echo $mod['id']; ?>&course_id=<?php echo $course_id; ?>"
                                    class="btn btn-primary btn-sm" style="padding:0.4rem 1rem; margin-left:1rem;">
                                    <i class="uil uil-plus"></i> Tambah Materi Bab
                                </a>
                            </div>
                        </div>

                        <?php if (empty($mod['lessons'])): ?>
                            <p style="color:var(--text-muted); font-size:0.9rem; font-style:italic;">Belum ada topik materi di dalam
                                modul ini.</p>
                        <?php else: ?>
                            <ul style="list-style:none; padding:0;">
                                <?php foreach ($mod['lessons'] as $les): ?>
                                    <li
                                        style="padding:1rem; background:rgba(0,0,0,0.2); border-radius:var(--radius-sm); margin-bottom:0.8rem; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
                                        <div>
                                            <strong style="display:block; margin-bottom:0.4rem; color:var(--text-main);">
                                                <span style="<?php echo !$les['is_published'] ? 'opacity:0.5;' : ''; ?>">
                                                    <?php if ($les['content_type'] === 'video_embed'): ?>
                                                        <i class="uil uil-play-circle" style="color:var(--secondary); margin-right:0.5rem;"></i>
                                                    <?php elseif ($les['content_type'] === 'document_upload'): ?>
                                                        <i class="uil uil-file-alt" style="color:var(--primary); margin-right:0.5rem;"></i>
                                                    <?php else: ?>
                                                        <i class="uil uil-processor" style="color:var(--warning); margin-right:0.5rem;"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($les['title']); ?>
                                                </span>
                                                <?php if(!$les['is_published']): ?>
                                                    <span style="font-size:0.65rem; background:var(--border); color:var(--text-muted); padding:1px 5px; border-radius:3px; margin-left:5px; vertical-align:middle;">DRAFT</span>
                                                <?php endif; ?>
                                            </strong>

                                            <div style="display:flex; gap:0.5rem;">
                                                <a href="edit_lesson.php?id=<?php echo $les['id']; ?>"
                                                    style="font-size:0.75rem; background:rgba(245, 158, 11, 0.2); color:var(--warning); padding:0.1rem 0.5rem; border-radius:10px; text-decoration:none;"><i
                                                        class="uil uil-pen"></i> Edit Materi</a>
                                                <?php if ($les['content_type'] === 'quiz'): ?>
                                                    <a href="builder_quiz.php?lesson_id=<?php echo $les['id']; ?>&course_id=<?php echo $course_id; ?>"
                                                        style="font-size:0.75rem; background:rgba(79,70,229,0.2); color:var(--primary); padding:0.1rem 0.5rem; border-radius:10px; text-decoration:none;"><i
                                                            class="uil uil-puzzle-piece"></i> Edit Soal</a>
                                                    <a href="teacher_quiz_results.php?lesson_id=<?php echo $les['id']; ?>&course_id=<?php echo $course_id; ?>"
                                                        style="font-size:0.75rem; background:rgba(16, 185, 129, 0.2); color:var(--secondary); padding:0.1rem 0.5rem; border-radius:10px; text-decoration:none;"><i
                                                            class="uil uil-chart-bar"></i> Laporan Nilai</a>
                                                <?php endif; ?>
                                                <?php if ($les['is_prerequisite_of']): ?>
                                                    <span
                                                        style="font-size:0.75rem; background:rgba(239,68,68,0.2); color:var(--danger); padding:0.1rem 0.5rem; border-radius:10px;">
                                                        <i class="uil uil-lock"></i> Ada Syarat Kunci
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div style="display:flex; align-items:center; gap:0.5rem;">
                                            <!-- Toggle Status Lesson -->
                                            <button type="button" onclick="toggleStatus('lesson', <?php echo $les['id']; ?>)" class="btn btn-secondary btn-sm" style="padding:0.3rem 0.6rem; border-color:var(--border); color:<?php echo $les['is_published'] ? 'var(--secondary)' : 'var(--text-muted)'; ?>;" title="<?php echo $les['is_published'] ? 'Sembunyikan Materi' : 'Tampilkan Materi'; ?>">
                                                <i class="uil <?php echo $les['is_published'] ? 'uil-eye' : 'uil-eye-slash'; ?>"></i>
                                            </button>
                                            
                                            <!-- Hapus Lesson -->
                                            <form action="../actions/delete_lesson.php" method="POST"
                                            data-confirm="Hapus materi bab ini beserta rekam persentase siswanya?" style="margin:0;">
                                            <input type="hidden" name="lesson_id" value="<?php echo $les['id']; ?>">
                                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding:0.3rem 0.6rem;"><i
                                                    class="uil uil-trash-alt"></i></button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar Kanan untuk Class LEADERBOARD -->
        <div>
            <div class="glass-card" style="position:sticky; top:100px; max-height:calc(100vh - 120px); display:flex; flex-direction:column; padding:1.5rem;">
                <h4 style="margin-bottom:0.8rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.8rem;"><i class="uil uil-analytics"></i> Papan Progres Siswa</h4>

                <form method="GET" action="course_view.php" style="margin-bottom:0.75rem;">
                    <input type="hidden" name="id" value="<?php echo $course_id; ?>">
                    <div style="display:flex; gap:0.4rem;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>"
                            placeholder="Cari nama / NISN..."
                            class="form-control"
                            style="flex:1; padding:0.45rem 0.7rem; font-size:0.85rem;">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Cari siswa" style="padding:0.45rem 0.7rem;">
                            <i class="uil uil-search"></i>
                        </button>
                        <?php if ($search_q !== ''): ?>
                            <a href="course_view.php?id=<?php echo $course_id; ?>" class="btn btn-secondary btn-sm" title="Reset pencarian" style="padding:0.45rem 0.7rem;">
                                <i class="uil uil-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <small style="color:var(--text-muted); font-size:0.75rem;">
                        <?php echo (int)$total_students_val; ?> siswa
                        <?php echo $search_q !== '' ? ' ditemukan' : ' terdaftar'; ?>
                    </small>
                </form>

                <form action="../actions/add_student_to_course.php" method="POST" style="margin-bottom:0.9rem; padding:0.7rem; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:var(--radius-sm);">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    <input type="hidden" name="page" value="<?php echo $page_num; ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_q); ?>">
                    <label style="display:block; font-size:0.78rem; color:var(--text-muted); margin-bottom:0.35rem;">
                        <i class="uil uil-user-plus"></i> Tambah siswa (NISN)
                    </label>
                    <div style="display:flex; gap:0.4rem;">
                        <input type="text" name="nisn" class="form-control" placeholder="Masukkan NISN"
                            required inputmode="numeric" autocomplete="off"
                            style="flex:1; padding:0.45rem 0.7rem; font-size:0.85rem;">
                        <button type="submit" class="btn btn-primary btn-sm" title="Tambahkan siswa ke course"
                            style="padding:0.45rem 0.7rem; background:#10b981; border:none;">
                            <i class="uil uil-plus"></i>
                        </button>
                    </div>
                </form>
                
                <div style="overflow-y:auto; flex:1; padding-right:0.5rem;" class="custom-scrollbar">
                    <?php if(empty($students_progress)): ?>
                        <div style="text-align:center; padding:2rem 0; color:var(--text-muted);">
                            <i class="uil uil-users-alt" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
                            <p style="font-size:0.9rem;"><?php echo $search_q !== '' ? 'Tidak ada siswa yang cocok dengan pencarian.' : 'Belum ada siswa terdaftar pada kelas ini.'; ?></p>
                        </div>
                    <?php else: ?>
                        <?php $qsKeep = $search_q !== '' ? '&q=' . urlencode($search_q) : ''; ?>
                        <div style="display:flex; flex-direction:column; gap:0.8rem;">
                            <?php foreach($students_progress as $sp): 
                                $completed = (int)$sp['completed_count'];
                                $percent = $all_lesson_count > 0 ? round(($completed / $all_lesson_count) * 100) : 0;
                                if($percent > 100) $percent = 100;
                                
                                $bar_color = 'var(--danger)';
                                if($percent >= 40) $bar_color = 'var(--warning)';
                                if($percent >= 80) $bar_color = 'var(--secondary)';
                                if($percent == 100) $bar_color = 'var(--primary)';
                                
                                $pic_file = $sp['profile_pic'];
                                $pic_url = !empty($pic_file) ? BASE_URL . '/uploads/' . $pic_file : 'https://ui-avatars.com/api/?name='.urlencode($sp['name']).'&background=312e81&color=fff';
                            ?>
                            <div class="student-progress-row"
                                role="button"
                                tabindex="0"
                                title="Lihat detail progres materi"
                                data-student-id="<?php echo (int)$sp['id']; ?>"
                                style="display:flex; align-items:center; gap:0.6rem; background:rgba(0,0,0,0.2); padding:0.7rem; border-radius:var(--radius-sm); border:1px solid rgba(255,255,255,0.05); transition:background 0.3s; cursor:pointer;"
                                onmouseover="this.style.background='rgba(0,0,0,0.4)';"
                                onmouseout="this.style.background='rgba(0,0,0,0.2)';">
                                <img src="<?php echo htmlspecialchars($pic_url); ?>" alt="Avatar" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid <?php echo $bar_color; ?>;">
                                
                                <div style="flex:1; min-width:0;" title="<?php echo $completed; ?> / <?php echo $all_lesson_count; ?> Topik Diselesaikan">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem; gap:0.4rem;">
                                        <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <strong style="color:var(--text-main); font-size:0.9rem;"><?php echo htmlspecialchars($sp['name']); ?></strong>
                                            <div style="color:var(--text-muted); font-size:0.72rem;"><?php echo htmlspecialchars($sp['username']); ?></div>
                                        </div>
                                        <div style="font-size:0.85rem; font-weight:700; color:<?php echo $bar_color; ?>; padding-left:0.3rem;">
                                            <?php echo $percent; ?>%
                                        </div>
                                    </div>
                                    
                                    <div style="width:100%; height:6px; background:rgba(255,255,255,0.15); border-radius:10px; overflow:hidden; box-shadow:inset 0 1px 2px rgba(0,0,0,0.3);">
                                        <div style="width:<?php echo $percent; ?>%; height:100%; background:<?php echo $bar_color; ?>; border-radius:10px; transition:width 1s cubic-bezier(0.4, 0, 0.2, 1); box-shadow:0 0 8px <?php echo $bar_color; ?>88;"></div>
                                    </div>
                                </div>

                                <div class="student-row-actions" style="display:flex; flex-direction:column; gap:0.25rem;">
                                    <form action="../actions/reset_student_progress.php" method="POST" style="margin:0;"
                                        data-confirm="Reset semua progres materi, kuis, dan pengumpulan tugas siswa ini pada course ini?">
                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$sp['id']; ?>">
                                        <input type="hidden" name="page" value="<?php echo $page_num; ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_q); ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm"
                                            title="Reset progres siswa"
                                            style="padding:0.25rem 0.4rem; line-height:1; border-color:rgba(245,158,11,0.45); color:var(--warning);">
                                            <i class="uil uil-history"></i>
                                        </button>
                                    </form>
                                    <form action="../actions/unenroll_student.php" method="POST" style="margin:0;"
                                        data-confirm-unenroll="1"
                                        data-student-name="<?php echo htmlspecialchars($sp['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$sp['id']; ?>">
                                        <input type="hidden" name="page" value="<?php echo $page_num; ?>">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_q); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            title="Keluarkan dari course"
                                            style="padding:0.25rem 0.4rem; line-height:1;">
                                            <i class="uil uil-user-minus"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if($total_pages > 1): ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid rgba(255,255,255,0.05);">
                                    <?php if($page_num > 1): ?>
                                        <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $page_num-1; ?><?php echo $qsKeep; ?>" class="btn btn-secondary btn-sm" style="padding:0.2rem 0.5rem; display:flex; align-items:center;"><i class="uil uil-angle-left"></i> Mundur</a>
                                    <?php else: ?>
                                        <span style="opacity:0.3; padding:0.2rem 0.5rem; display:inline-block;"><i class="uil uil-angle-left"></i> Mundur</span>
                                    <?php endif; ?>
                                    
                                    <span style="color:var(--text-muted); font-size:0.85rem; font-weight:bold;">Hal <?php echo $page_num; ?> / <?php echo $total_pages; ?></span>
                                    
                                    <?php if($page_num < $total_pages): ?>
                                        <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $page_num+1; ?><?php echo $qsKeep; ?>" class="btn btn-secondary btn-sm" style="padding:0.2rem 0.5rem; display:flex; align-items:center;">Maju <i class="uil uil-angle-right"></i></a>
                                    <?php else: ?>
                                        <span style="opacity:0.3; padding:0.2rem 0.5rem; display:inline-block;">Maju <i class="uil uil-angle-right"></i></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>

<!-- Modal ADD MODULE -->
<div id="modalAddModule" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="uil uil-layer-group"></i> Rancang Modul Baru</h3>
            <button onclick="document.getElementById('modalAddModule').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>
        <form action="../actions/add_module.php" method="POST">
            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
            <div class="form-group">
                <label class="form-label">Nama Tajuk / Bab Pelajaran</label>
                <input type="text" name="title" class="form-control" placeholder="Contoh: Bab 1 Pengenalan Akar Semesta" required>
            </div>
            <div class="form-group">
                <label class="checkbox-container" style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_published" value="1" checked style="width:20px; height:20px;">
                    <span>Langsung Publikasikan ke Siswa</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="padding:1rem; font-size:1.1rem;"><i class="uil uil-save"></i> Konfirmasi Perancangan Modul</button>
        </form>
    </div>
</div>
</div>
<!-- Modal DETAIL PROGRES SISWA -->
<div id="modalStudentProgress" class="modal-overlay">
    <div class="modal-box" style="max-width:560px; max-height:85vh; display:flex; flex-direction:column;">
        <div class="modal-header">
            <h3 style="margin:0;"><i class="uil uil-clipboard-notes"></i> Detail Progres Siswa</h3>
            <button type="button" onclick="document.getElementById('modalStudentProgress').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>
        <div id="studentProgressBody" style="overflow-y:auto; flex:1; padding:0.25rem 0.15rem 0.5rem;">
            <div style="text-align:center; padding:2rem; color:var(--text-muted);">Memuat...</div>
        </div>
    </div>
</div>

<!-- Hidden Form & JS Handler for Edit Module -->
<form id="formEditModule" action="../actions/edit_module.php" method="POST" style="display:none;">
    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
    <input type="hidden" name="module_id" id="edit_module_id" value="">
    <input type="hidden" name="new_title" id="edit_module_title" value="">
    <input type="hidden" name="is_published" id="edit_module_published" value="">
</form>

<form id="formToggleStatus" action="../actions/toggle_status.php" method="POST" style="display:none;">
    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
    <input type="hidden" name="type" id="toggle_type" value="">
    <input type="hidden" name="id" id="toggle_id" value="">
</form>

<script>
const COURSE_ID_PROGRESS = <?php echo (int)$course_id; ?>;

document.querySelectorAll('.student-progress-row').forEach(function(row) {
    row.addEventListener('click', function(e) {
        if (e.target.closest('.student-row-actions')) return;
        const sid = parseInt(row.getAttribute('data-student-id'), 10);
        if (sid) openStudentProgress(sid);
    });
    row.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const sid = parseInt(row.getAttribute('data-student-id'), 10);
            if (sid) openStudentProgress(sid);
        }
    });
});

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function lessonTypeIcon(type) {
    if (type === 'quiz') return 'uil-question-circle';
    if (type === 'video_embed') return 'uil-play-circle';
    if (type === 'document_upload') return 'uil-file-alt';
    return 'uil-book-open';
}

async function openStudentProgress(studentId) {
    const modal = document.getElementById('modalStudentProgress');
    const body = document.getElementById('studentProgressBody');
    modal.classList.add('active');
    body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--text-muted);"><i class="uil uil-spinner-alt" style="font-size:1.6rem;"></i><div style="margin-top:0.6rem;">Memuat detail progres...</div></div>';

    try {
        const res = await fetch('../actions/student_lesson_progress.php?course_id=' + COURSE_ID_PROGRESS + '&student_id=' + studentId, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json();
        if (!json.success || !json.data) {
            body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--danger);">' + escapeHtml(json.message || 'Gagal memuat data.') + '</div>';
            return;
        }

        const d = json.data;
        const pct = d.percent || 0;
        let barColor = 'var(--danger)';
        if (pct >= 40) barColor = 'var(--warning)';
        if (pct >= 80) barColor = 'var(--secondary)';
        if (pct === 100) barColor = 'var(--primary)';

        let html = `
            <div style="margin-bottom:1rem; padding:0.9rem 1rem; background:rgba(0,0,0,0.2); border-radius:var(--radius-sm); border:1px solid rgba(255,255,255,0.06);">
                <div style="display:flex; justify-content:space-between; gap:0.8rem; align-items:flex-start;">
                    <div>
                        <strong style="font-size:1.05rem; color:var(--text-main);">${escapeHtml(d.student.name)}</strong>
                        <div style="color:var(--text-muted); font-size:0.8rem; margin-top:0.15rem;">NISN: ${escapeHtml(d.student.username)}</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.25rem; font-weight:800; color:${barColor};">${pct}%</div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">${d.completed} / ${d.total} materi</div>
                    </div>
                </div>
                <div style="margin-top:0.7rem; width:100%; height:8px; background:rgba(255,255,255,0.12); border-radius:10px; overflow:hidden;">
                    <div style="width:${pct}%; height:100%; background:${barColor}; border-radius:10px;"></div>
                </div>
                <div style="display:flex; gap:1rem; margin-top:0.7rem; font-size:0.78rem; color:var(--text-muted);">
                    <span><i class="uil uil-check-circle" style="color:#10b981;"></i> Selesai</span>
                    <span><i class="uil uil-times-circle" style="color:#ef4444;"></i> Belum</span>
                </div>
            </div>
        `;

        if (!d.modules || d.modules.length === 0) {
            html += '<div style="text-align:center; padding:1.5rem; color:var(--text-muted);">Belum ada materi pada course ini.</div>';
        } else {
            d.modules.forEach(mod => {
                const lessons = mod.lessons || [];
                const modDone = lessons.filter(l => l.completed).length;
                html += `
                    <div style="margin-bottom:0.9rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.45rem; padding:0 0.15rem;">
                            <strong style="font-size:0.88rem; color:var(--text-main);"><i class="uil uil-folder" style="color:var(--primary);"></i> ${escapeHtml(mod.title)}</strong>
                            <span style="font-size:0.75rem; color:var(--text-muted);">${modDone}/${lessons.length}</span>
                        </div>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.35rem;">
                `;
                if (lessons.length === 0) {
                    html += '<li style="padding:0.55rem 0.75rem; color:var(--text-muted); font-size:0.85rem; background:rgba(0,0,0,0.15); border-radius:8px;">Belum ada materi di bab ini.</li>';
                } else {
                    lessons.forEach(les => {
                        const ok = !!les.completed;
                        const icon = ok ? 'uil-check-circle' : 'uil-times-circle';
                        const color = ok ? '#10b981' : '#ef4444';
                        const bg = ok ? 'rgba(16,185,129,0.08)' : 'rgba(239,68,68,0.06)';
                        const border = ok ? 'rgba(16,185,129,0.22)' : 'rgba(239,68,68,0.18)';
                        html += `
                            <li style="display:flex; align-items:center; gap:0.65rem; padding:0.55rem 0.75rem; background:${bg}; border:1px solid ${border}; border-radius:8px;">
                                <i class="uil ${icon}" style="color:${color}; font-size:1.25rem; flex-shrink:0;"></i>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-size:0.88rem; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <i class="uil ${lessonTypeIcon(les.content_type)}" style="opacity:0.7; margin-right:0.2rem;"></i>${escapeHtml(les.title)}
                                    </div>
                                    <div style="font-size:0.72rem; color:var(--text-muted);">${ok ? 'Sudah diselesaikan' : 'Belum diselesaikan'}</div>
                                </div>
                            </li>
                        `;
                    });
                }
                html += '</ul></div>';
            });
        }

        body.innerHTML = html;
    } catch (err) {
        body.innerHTML = '<div style="text-align:center; padding:2rem; color:var(--danger);">Gagal memuat detail progres.</div>';
    }
}

function editModule(moduleId, currentTitle, isPublished) {
    Swal.fire({
        title: 'Ubah Bab Konsep',
        html: `
            <input id="swal-input1" class="swal2-input" style="width:80%;" placeholder="Nama bab baru..." value="${currentTitle}">
            <div style="margin-top:20px; display:flex; align-items:center; justify-content:center; gap:10px;">
                <input type="checkbox" id="swal-input2" ${isPublished ? 'checked' : ''} style="width:20px; height:20px;">
                <label for="swal-input2" style="color:var(--text-main);">Publikasikan ke Siswa</label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: 'var(--primary)',
        cancelButtonColor: 'var(--border)',
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        background: 'var(--surface)',
        color: 'var(--text-main)',
        preConfirm: () => {
            return [
                document.getElementById('swal-input1').value,
                document.getElementById('swal-input2').checked
            ]
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const [title, published] = result.value;
            if(!title || title.trim().length === 0) {
                Swal.fire('Error', 'Judul tidak boleh kosong', 'error');
                return;
            }
            document.getElementById('edit_module_id').value = moduleId;
            document.getElementById('edit_module_title').value = title.trim();
            document.getElementById('edit_module_published').value = published ? '1' : '0';
            document.getElementById('formEditModule').submit();
        }
    });
}

function toggleStatus(type, id) {
    document.getElementById('toggle_type').value = type;
    document.getElementById('toggle_id').value = id;
    document.getElementById('formToggleStatus').submit();
}
</script>

<?php require_once '../components/footer.php'; ?>