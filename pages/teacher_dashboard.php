<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$courses_count = $conn->query("SELECT COUNT(*) as sum FROM courses WHERE teacher_id = $teacher_id")->fetch_assoc()['sum'];
$students_count = $conn->query("SELECT COUNT(*) as sum FROM users WHERE role = 'student'")->fetch_assoc()['sum'];
$classes_count = $conn->query("SELECT COUNT(*) as sum FROM classes WHERE teacher_id = $teacher_id")->fetch_assoc()['sum'];

// Assignment subqueries
$ungraded_submissions = $conn->query("SELECT COUNT(s.id) as sum FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = $teacher_id AND s.grade IS NULL")->fetch_assoc()['sum'];
$total_submissions = $conn->query("SELECT COUNT(s.id) as sum FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['sum'];
$unique_student_sum = $conn->query("SELECT COUNT(DISTINCT s.student_id) as sum FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['sum'];

$user_name = rtrim((string)$_SESSION['user_name'], " \t\n\r\0\x0B.");
$page_title = 'Beranda Guru';
require_once '../components/header.php';
?>
<div class="container main-content dash-home">
    <div class="page-header dash-home-header">
        <div>
            <h2><i class="uil uil-estate"></i> Beranda Edukator</h2>
            <p class="text-muted dash-home-sub">Selamat datang, <?php echo htmlspecialchars($user_name); ?>. Pantau aktivitas belajar mengajar Anda di sini.</p>
        </div>
    </div>

    <div class="stats-grid dash-stats">
        <div class="glass-card dash-stat">
            <div class="dash-stat-icon is-primary"><i class="uil uil-users-alt"></i></div>
            <div class="dash-stat-body">
                <div class="dash-stat-value"><?php echo (int)$classes_count; ?></div>
                <div class="dash-stat-label">Rombel</div>
                <div class="dash-stat-hint">Total rombongan belajar</div>
            </div>
        </div>
        <div class="glass-card dash-stat">
            <div class="dash-stat-icon is-success"><i class="uil uil-books"></i></div>
            <div class="dash-stat-body">
                <div class="dash-stat-value"><?php echo (int)$courses_count; ?></div>
                <div class="dash-stat-label">Mata Pelajaran</div>
                <div class="dash-stat-hint">Total modul aktif</div>
            </div>
        </div>
        <div class="glass-card dash-stat">
            <div class="dash-stat-icon is-warning"><i class="uil uil-user"></i></div>
            <div class="dash-stat-body">
                <div class="dash-stat-value"><?php echo (int)$students_count; ?></div>
                <div class="dash-stat-label">Siswa</div>
                <div class="dash-stat-hint">Total siswa terdaftar</div>
            </div>
        </div>
    </div>

    <div class="dash-section">
        <h3 class="dash-section-title"><i class="uil uil-clipboard-notes"></i> Pantauan Penugasan</h3>
        <div class="grid grid-cols-2 dash-monitor-grid">
            <a href="teacher_grading.php?status=ungraded" class="glass-card dash-monitor is-danger">
                <div class="dash-monitor-body">
                    <div class="dash-monitor-value"><?php echo (int)$ungraded_submissions; ?></div>
                    <div class="dash-monitor-label">Tugas Belum Dinilai</div>
                    <p class="dash-monitor-hint">Koreksi dan berikan nilai pada siswa Anda.</p>
                </div>
                <div class="dash-monitor-icon"><i class="uil uil-file-times-alt"></i></div>
            </a>

            <div class="glass-card dash-monitor is-success">
                <div class="dash-monitor-body">
                    <div class="dash-monitor-value"><?php echo (int)$total_submissions; ?></div>
                    <div class="dash-monitor-label">Pekerjaan Terkumpul</div>
                    <p class="dash-monitor-hint">Dari <?php echo (int)$unique_student_sum; ?> siswa yang telah berpartisipasi.</p>
                </div>
                <div class="dash-monitor-icon"><i class="uil uil-file-check-alt"></i></div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../components/footer.php'; ?>
