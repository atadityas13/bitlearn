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

$page_title = 'Beranda Guru';
require_once '../components/header.php';
?>
<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-estate"></i> Beranda Edukator</h2>
            <p class="text-muted" style="margin-bottom:0;">Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Pantau aktivitas belajar mengajar Anda di sini.</p>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="glass-card" style="text-align:center;">
            <i class="uil uil-users-alt" style="font-size:3rem; color:var(--primary);"></i>
            <h3><?php echo $classes_count; ?> Rombel</h3>
            <p style="color:var(--text-muted); font-size:0.9rem;">Total Rombongan Belajar</p>
        </div>
        <div class="glass-card" style="text-align:center;">
            <i class="uil uil-books" style="font-size:3rem; color:var(--secondary);"></i>
            <h3><?php echo $courses_count; ?> Mata Pelajaran</h3>
            <p style="color:var(--text-muted); font-size:0.9rem;">Total Modul Aktif</p>
        </div>
        <div class="glass-card" style="text-align:center;">
            <i class="uil uil-user" style="font-size:3rem; color:var(--warning);"></i>
            <h3><?php echo $students_count; ?> Siswa</h3>
            <p style="color:var(--text-muted); font-size:0.9rem;">Total Siswa Sistem Terdaftar</p>
        </div>
    </div>

    <!-- Stats Tugas -->
    <h3 style="margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid rgba(255,255,255,0.1);"><i class="uil uil-clipboard-notes"></i> Pantauan Penugasan</h3>
    <div class="grid grid-cols-2" style="margin-bottom:2rem;">
        <a href="teacher_grading.php" style="text-decoration:none; color:inherit;">
            <div class="glass-card flex-split" style="align-items:center; cursor:pointer; background:linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(0,0,0,0.4) 100%); border:1px solid rgba(239,68,68,0.3);">
                <div>
                    <h2 style="font-size:2.5rem; margin:0; color:var(--danger);"><?php echo $ungraded_submissions; ?></h2>
                    <h4 style="margin:0; font-size:1.1rem; color:var(--text-main);">Tugas Belum Dinilai</h4>
                    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.3rem;">Koreksi dan berikan nilai pada siswa Anda.</p>
                </div>
                <div style="background:rgba(239,68,68,0.2); padding:1.5rem; border-radius:50%;">
                    <i class="uil uil-file-times-alt" style="font-size:2.5rem; color:var(--danger);"></i>
                </div>
            </div>
        </a>

        <div class="glass-card flex-split" style="align-items:center; background:linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(0,0,0,0.4) 100%); border:1px solid rgba(16,185,129,0.3);">
            <div>
                <h2 style="font-size:2.5rem; margin:0; color:var(--secondary);"><?php echo $total_submissions; ?></h2>
                <h4 style="margin:0; font-size:1.1rem; color:var(--text-main);">Pekerjaan Terkumpul</h4>
                <p style="color:var(--text-muted); font-size:0.85rem; margin-top:0.3rem;">Dari <?php echo $unique_student_sum; ?> siswa yang telah berpartisipasi.</p>
            </div>
            <div style="background:rgba(16,185,129,0.2); padding:1.5rem; border-radius:50%;">
                <i class="uil uil-file-check-alt" style="font-size:2.5rem; color:var(--secondary);"></i>
            </div>
        </div>
    </div>
</div>
<?php require_once '../components/footer.php'; ?>
