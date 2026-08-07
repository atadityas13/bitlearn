<?php
require_once '../core/config.php';
require_once '../core/CourseStudents.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];
CourseStudents::ensureExclusionsTable($conn);

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Daftar course milik guru
$courses = [];
$cq = $conn->query("SELECT id, title FROM courses WHERE teacher_id = $teacher_id ORDER BY title ASC");
if ($cq) {
    while ($row = $cq->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Validasi course
$selected_course = null;
if ($course_id > 0) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $course_id) {
            $selected_course = $c;
            break;
        }
    }
    if (!$selected_course) {
        $course_id = 0;
        $class_id = 0;
    }
}

// Kelas yang terhubung ke course terpilih
$classes = [];
if ($course_id > 0) {
    $clq = $conn->query("
        SELECT cl.id, cl.name
        FROM course_classes cc
        JOIN classes cl ON cl.id = cc.class_id
        WHERE cc.course_id = $course_id
          AND cl.teacher_id = $teacher_id
        ORDER BY cl.name ASC
    ");
    if ($clq) {
        while ($row = $clq->fetch_assoc()) {
            $classes[] = $row;
        }
    }
}

// Validasi kelas
$selected_class = null;
if ($course_id > 0 && $class_id > 0) {
    foreach ($classes as $cl) {
        if ((int)$cl['id'] === $class_id) {
            $selected_class = $cl;
            break;
        }
    }
    if (!$selected_class) {
        $class_id = 0;
    }
}

$quiz_columns = [];
$students = [];
$scores = []; // [student_id][lesson_id] => score
$ready = ($course_id > 0 && $class_id > 0 && $selected_course && $selected_class);

if ($ready) {
    // Kolom nilai = pelajaran kuis (Pretest/Posttest) di course, urut modul → lesson
    $qz = $conn->query("
        SELECT l.id, l.title, m.title AS module_title, m.order_num AS module_order, l.order_num AS lesson_order
        FROM lessons l
        JOIN modules m ON l.module_id = m.id
        WHERE m.course_id = $course_id
          AND l.content_type = 'quiz'
        ORDER BY m.order_num ASC, m.id ASC, l.order_num ASC, l.id ASC
    ");
    if ($qz) {
        $n = 1;
        while ($row = $qz->fetch_assoc()) {
            $row['label'] = 'Nilai ' . $n;
            $row['legend'] = trim((string)$row['title']);
            $quiz_columns[] = $row;
            $n++;
        }
    }

    // Siswa di rombel terpilih (kecuali yang di-exclude dari course)
    $has_excl = CourseStudents::hasExclusionsTable($conn);
    $excl_join = $has_excl
        ? "LEFT JOIN course_exclusions cx ON cx.course_id = $course_id AND cx.student_id = u.id"
        : '';
    $excl_where = $has_excl ? 'AND cx.id IS NULL' : '';

    $sq = $conn->query("
        SELECT DISTINCT u.id, u.name, u.username AS nisn
        FROM users u
        JOIN class_students cs ON cs.student_id = u.id AND cs.class_id = $class_id
        $excl_join
        WHERE u.role = 'student'
          $excl_where
        ORDER BY u.name ASC
    ");
    if ($sq) {
        while ($row = $sq->fetch_assoc()) {
            $students[] = $row;
        }
    }

    // Ambil skor (jika ada beberapa attempt, pakai yang terakhir)
    if (!empty($quiz_columns) && !empty($students)) {
        $lesson_ids = array_map(static function ($col) {
            return (int)$col['id'];
        }, $quiz_columns);
        $lesson_in = implode(',', $lesson_ids);
        $score_q = $conn->query("
            SELECT qa.student_id, qa.lesson_id, qa.score
            FROM quiz_attempts qa
            INNER JOIN (
                SELECT student_id, lesson_id, MAX(id) AS mid
                FROM quiz_attempts
                WHERE lesson_id IN ($lesson_in)
                GROUP BY student_id, lesson_id
            ) latest ON qa.id = latest.mid
        ");
        if ($score_q) {
            while ($row = $score_q->fetch_assoc()) {
                $sid = (int)$row['student_id'];
                $lid = (int)$row['lesson_id'];
                $scores[$sid][$lid] = (int)$row['score'];
            }
        }
    }
}

$page_title = 'Rekap Nilai';
require_once '../components/header.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-chart-line"></i> Rekap Nilai</h2>
            <p class="text-muted" style="margin:0;">Ringkasan nilai Pretest/Posttest siswa per course dan rombel.</p>
        </div>
    </div>

    <div class="glass-card recap-toolbar">
        <form method="GET" action="teacher_grade_recap.php" class="recap-filter-form" id="recapFilterForm">
            <div class="recap-filter-grid">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="filterCourse"><i class="uil uil-books"></i> Course</label>
                    <select id="filterCourse" name="course_id" class="form-control" required>
                        <option value="">— Pilih course —</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="filterClass"><i class="uil uil-building"></i> Kelas / Rombel</label>
                    <select id="filterClass" name="class_id" class="form-control" <?php echo $course_id > 0 ? '' : 'disabled'; ?>>
                        <option value="">— Pilih kelas —</option>
                        <?php foreach ($classes as $cl): ?>
                            <option value="<?php echo (int)$cl['id']; ?>" <?php echo $class_id === (int)$cl['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cl['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($course_id > 0 && empty($classes)): ?>
                        <small class="text-muted" style="display:block; margin-top:0.35rem;">Belum ada rombel terhubung ke course ini.</small>
                    <?php endif; ?>
                </div>
                <div class="recap-filter-actions">
                    <button type="submit" class="btn btn-primary" <?php echo ($course_id > 0 && !empty($classes)) ? '' : 'disabled'; ?>>
                        <i class="uil uil-search"></i> Tampilkan
                    </button>
                    <?php if ($course_id > 0 || $class_id > 0): ?>
                        <a href="teacher_grade_recap.php" class="btn btn-secondary"><i class="uil uil-times"></i> Reset</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if (!$ready): ?>
        <div class="glass-card" style="text-align:center; padding:3.5rem 1.5rem;">
            <i class="uil uil-chart-line" style="font-size:3.5rem; color:var(--text-muted);"></i>
            <h3 style="margin-top:1rem;">Pilih course dan kelas</h3>
            <p class="text-muted" style="margin:0; max-width:420px; margin-inline:auto;">
                Data nilai Pretest/Posttest akan ditampilkan setelah kedua filter dipilih.
            </p>
        </div>
    <?php elseif (empty($quiz_columns)): ?>
        <div class="glass-card" style="text-align:center; padding:3.5rem 1.5rem;">
            <i class="uil uil-clipboard-notes" style="font-size:3.5rem; color:var(--text-muted);"></i>
            <h3 style="margin-top:1rem;">Belum ada Pretest/Posttest</h3>
            <p class="text-muted" style="margin:0;">
                Course <b><?php echo htmlspecialchars($selected_course['title']); ?></b> belum memiliki pelajaran bertipe kuis.
            </p>
        </div>
    <?php elseif (empty($students)): ?>
        <div class="glass-card" style="text-align:center; padding:3.5rem 1.5rem;">
            <i class="uil uil-users-alt" style="font-size:3.5rem; color:var(--text-muted);"></i>
            <h3 style="margin-top:1rem;">Tidak ada siswa</h3>
            <p class="text-muted" style="margin:0;">
                Rombel <b><?php echo htmlspecialchars($selected_class['name']); ?></b> belum memiliki siswa aktif di course ini.
            </p>
        </div>
    <?php else: ?>
        <div class="glass-card" style="padding:1rem 1.15rem 0.5rem; margin-bottom:0.75rem;">
            <div style="font-size:0.95rem;">
                <b><?php echo htmlspecialchars($selected_course['title']); ?></b>
                <span class="text-muted"> · </span>
                <span><?php echo htmlspecialchars($selected_class['name']); ?></span>
                <span class="text-muted"> · <?php echo count($students); ?> siswa · <?php echo count($quiz_columns); ?> kolom nilai</span>
            </div>
        </div>

        <div class="table-responsive glass-card" style="padding:1rem;">
            <table class="table recap-table" style="min-width:<?php echo 280 + (count($quiz_columns) * 88); ?>px; margin-top:0;">
                <thead>
                    <tr>
                        <th style="width:52px; text-align:center;">No</th>
                        <th style="min-width:180px;">Nama Siswa</th>
                        <?php foreach ($quiz_columns as $col): ?>
                            <th style="text-align:center; white-space:nowrap;" title="<?php echo htmlspecialchars($col['legend']); ?>">
                                <?php echo htmlspecialchars($col['label']); ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($students as $st):
                        $sid = (int)$st['id'];
                    ?>
                        <tr>
                            <td style="text-align:center; color:var(--text-muted);"><?php echo $no++; ?></td>
                            <td>
                                <b><?php echo htmlspecialchars($st['name']); ?></b>
                                <?php if (!empty($st['nisn'])): ?>
                                    <div style="font-size:0.78rem; color:var(--text-muted);">NISN: <?php echo htmlspecialchars($st['nisn']); ?></div>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($quiz_columns as $col):
                                $lid = (int)$col['id'];
                                $has = isset($scores[$sid][$lid]);
                                $val = $has ? (int)$scores[$sid][$lid] : null;
                                $color = '';
                                if ($has) {
                                    $color = $val >= 75 ? 'var(--secondary)' : 'var(--danger)';
                                }
                            ?>
                                <td style="text-align:center;">
                                    <?php if ($has): ?>
                                        <span style="font-weight:700; color:<?php echo $color; ?>;"><?php echo $val; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="glass-card recap-legend">
            <h4 style="margin:0 0 0.75rem; font-size:1rem;"><i class="uil uil-info-circle"></i> Keterangan kolom</h4>
            <ul class="recap-legend-list">
                <?php foreach ($quiz_columns as $col): ?>
                    <li>
                        <span class="recap-legend-key"><?php echo htmlspecialchars($col['label']); ?></span>
                        <span class="recap-legend-sep">:</span>
                        <span class="recap-legend-val"><?php echo htmlspecialchars($col['legend']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>
.recap-toolbar { padding: 1.15rem 1.25rem; margin-bottom: 1.25rem; }
.recap-filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 0.85rem 1rem;
    align-items: end;
}
.recap-filter-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}
.recap-table th, .recap-table td { vertical-align: middle; }
.recap-legend { padding: 1.15rem 1.25rem; margin-top: 1rem; }
.recap-legend-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.45rem;
}
.recap-legend-list li {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.5rem;
    font-size: 0.92rem;
    line-height: 1.45;
}
.recap-legend-key { font-weight: 700; color: var(--primary); min-width: 4.5rem; }
.recap-legend-sep { color: var(--text-muted); }
.recap-legend-val { color: var(--text-main); }
@media (max-width: 768px) {
    .recap-filter-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    var course = document.getElementById('filterCourse');
    var kelas = document.getElementById('filterClass');
    var form = document.getElementById('recapFilterForm');
    if (!course || !form) return;

    course.addEventListener('change', function () {
        // Ganti course → reset kelas, muat ulang opsi kelas
        if (kelas) kelas.value = '';
        form.submit();
    });

    if (kelas) {
        kelas.addEventListener('change', function () {
            if (course.value && kelas.value) form.submit();
        });
    }
})();
</script>

<?php require_once '../components/footer.php'; ?>
