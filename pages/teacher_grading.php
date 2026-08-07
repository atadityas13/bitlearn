<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = (int)$_SESSION['user_id'];

$status = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
if (!in_array($status, ['all', 'ungraded', 'graded'], true)) {
    $status = 'all';
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$search_q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (strlen($search_q) > 100) {
    $search_q = substr($search_q, 0, 100);
}

// Daftar course milik guru
$courses = [];
$cq = $conn->query("SELECT id, title FROM courses WHERE teacher_id = $teacher_id ORDER BY title ASC");
if ($cq) {
    while ($row = $cq->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Validasi course
$course_ok = false;
if ($course_id > 0) {
    foreach ($courses as $c) {
        if ((int)$c['id'] === $course_id) {
            $course_ok = true;
            break;
        }
    }
    if (!$course_ok) {
        $course_id = 0;
        $class_id = 0;
    }
}

// Kelas: jika course dipilih → rombel terhubung; jika tidak → semua rombel guru
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
} else {
    $clq = $conn->query("SELECT id, name FROM classes WHERE teacher_id = $teacher_id ORDER BY name ASC");
}
if ($clq) {
    while ($row = $clq->fetch_assoc()) {
        $classes[] = $row;
    }
}

$class_ok = false;
if ($class_id > 0) {
    foreach ($classes as $cl) {
        if ((int)$cl['id'] === $class_id) {
            $class_ok = true;
            break;
        }
    }
    if (!$class_ok) {
        $class_id = 0;
    }
}

function grading_qs(array $overrides = []): string
{
    $params = [
        'status' => $overrides['status'] ?? ($_GET['status'] ?? 'all'),
        'course_id' => array_key_exists('course_id', $overrides) ? (int)$overrides['course_id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0),
        'class_id' => array_key_exists('class_id', $overrides) ? (int)$overrides['class_id'] : (isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0),
        'q' => array_key_exists('q', $overrides) ? trim((string)$overrides['q']) : (isset($_GET['q']) ? trim((string)$_GET['q']) : ''),
    ];
    if (!in_array($params['status'], ['all', 'ungraded', 'graded'], true)) {
        $params['status'] = 'all';
    }
    if ($params['status'] === 'all') {
        unset($params['status']);
    }
    if ((int)$params['course_id'] <= 0) {
        unset($params['course_id']);
    }
    if ((int)$params['class_id'] <= 0) {
        unset($params['class_id']);
    }
    if ($params['q'] === '') {
        unset($params['q']);
    }
    return $params ? ('?' . http_build_query($params)) : '';
}

// Base filter (course/kelas/nama) untuk query & hitungan
$filter_sql = "WHERE c.teacher_id = $teacher_id";
$join_class = '';
$join_user = " JOIN users u ON s.student_id = u.id ";
if ($course_id > 0) {
    $filter_sql .= " AND c.id = $course_id";
}
if ($class_id > 0) {
    $join_class = " JOIN class_students cs ON cs.student_id = s.student_id AND cs.class_id = $class_id ";
}
if ($search_q !== '') {
    $safe_q = $conn->real_escape_string($search_q);
    $filter_sql .= " AND (u.name LIKE '%$safe_q%' OR u.username LIKE '%$safe_q%')";
}

$status_sql = '';
if ($status === 'ungraded') {
    $status_sql = " AND s.grade IS NULL";
} elseif ($status === 'graded') {
    $status_sql = " AND s.grade IS NOT NULL";
}

$count_base = "
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    $join_user
    $join_class
    $filter_sql
";

$count_all = (int)$conn->query("SELECT COUNT(s.id) AS n $count_base")->fetch_assoc()['n'];
$count_ungraded = (int)$conn->query("SELECT COUNT(s.id) AS n $count_base AND s.grade IS NULL")->fetch_assoc()['n'];
$count_graded = (int)$conn->query("SELECT COUNT(s.id) AS n $count_base AND s.grade IS NOT NULL")->fetch_assoc()['n'];

$query = "SELECT s.id as sub_id, s.file_path, s.grade, s.created_at as submitted_at, s.feedback,
                 u.name as st_name, a.title as assign_title, c.title as course_title
          FROM submissions s
          JOIN assignments a ON s.assignment_id = a.id
          JOIN courses c ON a.course_id = c.id
          $join_user
          $join_class
          $filter_sql
          $status_sql
          ORDER BY s.created_at DESC";
$subs = $conn->query($query);

$has_extra_filter = ($course_id > 0 || $class_id > 0 || $search_q !== '');

$page_title = 'Portal Nilai';
require_once '../components/header.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-award"></i> Evaluasi & Penilaian</h2>
            <p class="text-muted" style="margin:0;">Periksa dokumen kiriman tugas siswa, lalu berikan skor kelulusan.</p>
        </div>
    </div>

    <div class="glass-card grading-toolbar">
        <form method="GET" action="teacher_grading.php" class="grading-filter-form" id="gradingFilterForm">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
            <div class="grading-select-grid">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="filterCourse"><i class="uil uil-books"></i> Course</label>
                    <select id="filterCourse" name="course_id" class="form-control">
                        <option value="0">Semua course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $course_id === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="filterClass"><i class="uil uil-building"></i> Kelas / Rombel</label>
                    <select id="filterClass" name="class_id" class="form-control">
                        <option value="0">Semua kelas</option>
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
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="filterSearch"><i class="uil uil-search"></i> Cari siswa</label>
                    <div class="grading-search-row">
                        <input type="text" id="filterSearch" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Nama atau NISN..." autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-sm" title="Cari"><i class="uil uil-search"></i></button>
                    </div>
                </div>
                <div class="grading-filter-actions">
                    <?php if ($has_extra_filter): ?>
                        <a href="teacher_grading.php<?php echo grading_qs(['course_id' => 0, 'class_id' => 0, 'q' => '', 'status' => $status]); ?>" class="btn btn-secondary btn-sm">
                            <i class="uil uil-times"></i> Reset filter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="grading-filter-label" style="margin-top:1rem;"><i class="uil uil-filter"></i> Filter status</div>
        <div class="grading-filter-chips">
            <a href="teacher_grading.php<?php echo grading_qs(['status' => 'all', 'course_id' => $course_id, 'class_id' => $class_id, 'q' => $search_q]); ?>" class="filter-chip <?php echo $status === 'all' ? 'is-active' : ''; ?>">Semua <span class="chip-count"><?php echo $count_all; ?></span></a>
            <a href="teacher_grading.php<?php echo grading_qs(['status' => 'ungraded', 'course_id' => $course_id, 'class_id' => $class_id, 'q' => $search_q]); ?>" class="filter-chip <?php echo $status === 'ungraded' ? 'is-active' : ''; ?>">Belum dinilai <span class="chip-count"><?php echo $count_ungraded; ?></span></a>
            <a href="teacher_grading.php<?php echo grading_qs(['status' => 'graded', 'course_id' => $course_id, 'class_id' => $class_id, 'q' => $search_q]); ?>" class="filter-chip <?php echo $status === 'graded' ? 'is-active' : ''; ?>">Sudah dinilai <span class="chip-count"><?php echo $count_graded; ?></span></a>
        </div>
    </div>

    <?php if($subs && $subs->num_rows > 0): ?>
        <div class="table-responsive glass-card" style="padding:1rem;">
            <table class="table" style="min-width:820px; margin-top:0;">
                <thead>
                    <tr>
                        <th>Nama Siswa / Course</th>
                        <th>Penugasan</th>
                        <th>File Lampiran</th>
                        <th>Status / Skor</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sub = $subs->fetch_assoc()):
                        $is_graded = ($sub['grade'] !== null && $sub['grade'] !== '');
                        $sub_id = (int)$sub['sub_id'];
                    ?>
                    <tr>
                        <td>
                            <b><?php echo htmlspecialchars($sub['st_name']); ?></b><br>
                            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($sub['course_title']); ?></small><br>
                            <span style="font-size:0.8rem; color:var(--text-muted);"><i class="uil uil-clock"></i> <?php echo date('d M, H:i', strtotime($sub['submitted_at'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($sub['assign_title']); ?></td>
                        <td>
                            <div class="action-row" style="width:auto; flex-wrap:nowrap;">
                                <a href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($sub['file_path']); ?>" download class="btn btn-secondary btn-sm" title="Unduh"><i class="uil uil-cloud-download"></i></a>
                                <button type="button" onclick="openPreview('<?php echo BASE_URL . '/uploads/' . htmlspecialchars($sub['file_path']); ?>', 'Submisi: <?php echo htmlspecialchars(addslashes($sub['st_name'])); ?>')" class="btn btn-primary btn-sm">
                                    <i class="uil uil-eye"></i> Lihat
                                </button>
                            </div>
                        </td>
                        <td>
                            <?php if($is_graded): ?>
                                <span class="badge-rombel"><?php echo (int)$sub['grade']; ?> / 100</span>
                                <?php if (trim((string)$sub['feedback']) !== ''): ?>
                                    <div style="margin-top:0.35rem; font-size:0.8rem; color:var(--text-muted); max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($sub['feedback']); ?>">
                                        <i class="uil uil-comment-alt-message"></i> <?php echo htmlspecialchars($sub['feedback']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge-warn">Menunggu Penilaian</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <?php if ($is_graded): ?>
                                <button type="button"
                                    class="btn btn-secondary btn-sm btn-open-grade"
                                    data-sub-id="<?php echo $sub_id; ?>"
                                    data-student="<?php echo htmlspecialchars($sub['st_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-assignment="<?php echo htmlspecialchars($sub['assign_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-course="<?php echo htmlspecialchars($sub['course_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-grade="<?php echo (int)$sub['grade']; ?>"
                                    data-feedback="<?php echo htmlspecialchars((string)$sub['feedback'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-mode="edit">
                                    <i class="uil uil-pen"></i> Perbaiki
                                </button>
                            <?php else: ?>
                                <button type="button"
                                    class="btn btn-primary btn-sm btn-open-grade"
                                    data-sub-id="<?php echo $sub_id; ?>"
                                    data-student="<?php echo htmlspecialchars($sub['st_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-assignment="<?php echo htmlspecialchars($sub['assign_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-course="<?php echo htmlspecialchars($sub['course_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-grade=""
                                    data-feedback=""
                                    data-mode="new">
                                    <i class="uil uil-edit"></i> Nilai
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="glass-card" style="text-align:center; padding:4rem;">
            <i class="uil uil-inbox" style="font-size:4rem; color:var(--text-muted);"></i>
            <h3 style="margin-top:1rem;">
                <?php
                if ($status === 'ungraded') echo 'Tidak ada tugas yang menunggu penilaian.';
                elseif ($status === 'graded') echo 'Belum ada tugas yang sudah dinilai.';
                else echo 'Tidak ada penugasan yang masuk.';
                ?>
            </h3>
            <p style="color:var(--text-muted);">
                <?php
                if ($has_extra_filter) echo 'Coba ubah filter course, kelas, pencarian, atau status.';
                elseif ($status === 'all') echo 'Belum ada siswa yang mengumpulkan tugas.';
                else echo 'Coba ubah filter status di atas.';
                ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<div id="modalGradeSubmission" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="gradeModalTitle"><i class="uil uil-award"></i> Beri Nilai</h3>
            <button type="button" class="btn-close" onclick="document.getElementById('modalGradeSubmission').classList.remove('active')" aria-label="Tutup"><i class="uil uil-times"></i></button>
        </div>
        <form action="../actions/grade_submission.php" method="POST" id="formGradeSubmission">
            <input type="hidden" name="sub_id" id="gradeSubId" value="">
            <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($status); ?>">
            <input type="hidden" name="return_course_id" value="<?php echo (int)$course_id; ?>">
            <input type="hidden" name="return_class_id" value="<?php echo (int)$class_id; ?>">
            <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($search_q); ?>">

            <div class="modal-note" id="gradeMetaBox">
                <div><strong id="gradeStudentName">-</strong></div>
                <div style="font-size:0.88rem; color:var(--text-muted); margin-top:0.25rem;" id="gradeAssignmentMeta">-</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="gradeInput">Nilai (0–100)</label>
                <input type="number" name="grade" id="gradeInput" class="form-control" placeholder="Contoh: 85" min="0" max="100" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="feedbackInput">Feedback / catatan</label>
                <textarea name="feedback" id="feedbackInput" class="form-control" rows="4" placeholder="Tulis umpan balik untuk siswa (opsional)"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="gradeSubmitBtn">
                <i class="uil uil-save"></i> Simpan Nilai
            </button>
        </form>
    </div>
</div>

<style>
.grading-toolbar {
    padding: 1.1rem 1.25rem;
    margin-bottom: 1.25rem;
}
.grading-select-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.2fr auto;
    gap: 0.85rem 1rem;
    align-items: end;
}
.grading-search-row {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.grading-search-row .form-control { flex: 1; min-width: 0; }
.grading-filter-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}
.grading-filter-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 0.55rem;
}
.grading-filter-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
}
.filter-chip:hover {
    border-color: #FDBA74;
    color: var(--primary-hover);
    background: var(--primary-soft);
}
.filter-chip.is-active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.filter-chip .chip-count {
    font-size: 0.75rem;
    opacity: 0.85;
}
.filter-chip.is-active .chip-count {
    opacity: 1;
}
.badge-warn {
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}
@media (max-width: 768px) {
    .grading-select-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    var form = document.getElementById('gradingFilterForm');
    var course = document.getElementById('filterCourse');
    var kelas = document.getElementById('filterClass');
    if (!form) return;

    if (course) {
        course.addEventListener('change', function () {
            // Ganti course → reset kelas agar daftar rombel sesuai course
            if (kelas) kelas.value = '0';
            form.submit();
        });
    }
    if (kelas) {
        kelas.addEventListener('change', function () {
            form.submit();
        });
    }
})();

document.querySelectorAll('.btn-open-grade').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var mode = this.getAttribute('data-mode') || 'new';
        var modal = document.getElementById('modalGradeSubmission');
        document.getElementById('gradeSubId').value = this.getAttribute('data-sub-id') || '';
        document.getElementById('gradeStudentName').textContent = this.getAttribute('data-student') || '-';
        document.getElementById('gradeAssignmentMeta').textContent =
            (this.getAttribute('data-assignment') || '-') + ' · ' + (this.getAttribute('data-course') || '-');
        document.getElementById('gradeInput').value = this.getAttribute('data-grade') || '';
        document.getElementById('feedbackInput').value = this.getAttribute('data-feedback') || '';

        var title = document.getElementById('gradeModalTitle');
        var submitBtn = document.getElementById('gradeSubmitBtn');
        if (mode === 'edit') {
            title.innerHTML = '<i class="uil uil-pen"></i> Perbaiki Nilai';
            submitBtn.innerHTML = '<i class="uil uil-save"></i> Simpan Perbaikan';
        } else {
            title.innerHTML = '<i class="uil uil-award"></i> Beri Nilai';
            submitBtn.innerHTML = '<i class="uil uil-save"></i> Simpan Nilai';
        }
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        setTimeout(function () { document.getElementById('gradeInput').focus(); }, 50);
    });
});
</script>

<?php require_once '../components/footer.php'; ?>
