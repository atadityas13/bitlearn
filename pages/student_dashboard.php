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

$student_id = $_SESSION['user_id'];

require_once '../core/CourseStudents.php';
CourseStudents::ensureExclusionsTable($conn);

// Get ALL accessible courses (Manual code enrollments + Implicit Rombel assignments)
$exJoin = '';
$exWhere = '';
if (CourseStudents::hasExclusionsTable($conn)) {
    $exJoin = "LEFT JOIN course_exclusions cx ON cx.course_id = c.id AND cx.student_id = $student_id";
    $exWhere = 'AND cx.id IS NULL';
}
$query = "
    SELECT DISTINCT c.* 
    FROM courses c 
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $student_id
    LEFT JOIN course_classes cc ON c.id = cc.course_id
    LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $student_id
    $exJoin
    WHERE (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
      $exWhere
";
$enrolled_query = $conn->query($query);

// Get pending assignments (unsubmitted & future deadline)
$pending_assignments_query = $conn->query("
    SELECT a.*, c.title as course_name 
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN enrollments e ON c.id = e.course_id AND e.student_id = $student_id
    LEFT JOIN course_classes cc ON c.id = cc.course_id
    LEFT JOIN class_students cs ON cc.class_id = cs.class_id AND cs.student_id = $student_id
    $exJoin
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = $student_id
    WHERE (e.id IS NOT NULL OR cs.student_id IS NOT NULL)
      $exWhere
      AND s.id IS NULL
      AND a.due_date > NOW()
      AND a.is_published = 1
    ORDER BY a.due_date ASC
    LIMIT 5
");

$page_title = 'Area Belajar Saya';
require_once '../components/header.php';
?>

<div class="container main-content" style="padding-top:2rem;">
    <!-- Bagian Header Logo -->
    <div
        style="text-align:center; padding-bottom: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08);">
        <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn Logo"
            style="height:auto; width:140px; max-width:100%; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.4));">
    </div>

    <div class="flex-split" style="margin-bottom:2rem;">
        <div>
            <h2><i class="uil uil-graduation-cap"></i> Area Belajar Saya</h2>
            <p style="color:var(--text-muted);">Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                Siap untuk belajar hari ini?</p>
        </div>

        <!-- Form Pendaftaran Mandiri dengan Kode -->
        <div
            style="background:var(--surface); padding:1rem 1.5rem; border-radius:var(--radius-sm); border:1px solid var(--border); display:flex; flex-wrap:wrap; gap:1rem; align-items:center; justify-content:center;">
            <div style="text-align:left;">
                <b style="font-size:0.9rem; display:block;">Punya Kode Pelajaran?</b>
                <small style="color:var(--text-muted);">Gabung ke kelas dengan kode dari guru</small>
            </div>
            <form action="../actions/enroll_code.php" method="POST" style="display:flex; flex-wrap:wrap; justify-content:center; gap:0.5rem; margin:0; width:100%; max-width:260px;">
                <input type="text" name="enrollment_code" class="form-control" placeholder="Contoh: KODE123" required
                    style="flex:1; min-width:120px; padding:0.4rem 0.8rem;">
                <button type="submit" class="btn btn-secondary" style="padding:0.4rem 1rem;"><i
                        class="uil uil-enter"></i> Gabung</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><i class="uil uil-check-circle"></i>
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
        </div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><i class="uil uil-exclamation-circle"></i>
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div><?php endif; ?>

    <!-- Upcoming Assignments Notification -->
    <?php if ($pending_assignments_query && $pending_assignments_query->num_rows > 0): ?>
        <div style="margin-bottom:2.5rem; animation: slideDown 0.5s ease-out;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                <i class="uil uil-bell" style="color:var(--warning); font-size:1.4rem;"></i>
                <h3 style="margin:0; font-size:1.2rem;">Ada tugas belum dikerjakan!</h3>
                <span
                    style="background:var(--warning); color:white; font-size:0.7rem; padding:2px 8px; border-radius:50px; margin-left:0.5rem; font-weight:800;">
                    <?php echo $pending_assignments_query->num_rows; ?> Belum Dikerjakan
                </span>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.8rem;">
                <?php while ($asn = $pending_assignments_query->fetch_assoc()): ?>
                    <div class="glass-card"
                        style="padding:1rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-left:4px solid var(--warning); background:rgba(245, 158, 11, 0.05);">
                        <div style="display:flex; align-items:center; gap:1.2rem;">
                            <div
                                style="background:rgba(245, 158, 11, 0.15); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:var(--warning);">
                                <i class="uil uil-clipboard-notes" style="font-size:1.5rem;"></i>
                            </div>
                            <div>
                                <h5 style="margin:0; font-size:1rem; color:var(--text-main);">
                                    <?php echo htmlspecialchars($asn['title']); ?>
                                </h5>
                                <small
                                    style="color:var(--text-muted);"><?php echo htmlspecialchars($asn['course_name']); ?></small>
                            </div>
                        </div>

                        <div style="text-align:right; display:flex; align-items:center; gap:2rem;">
                            <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                <div id="timer-<?php echo $asn['id']; ?>"
                                    style="font-family:monospace; font-weight:700; color:var(--warning); font-size:1.05rem;">
                                    --:--:--</div>
                                <small style="color:rgba(255,255,255,0.4); font-size:0.7rem;">SISA WAKTU</small>
                            </div>
                            <div style="display:flex; gap:0.5rem;">
                                <?php if (!empty($asn['file_path'])): ?>
                                    <button onclick="openPreview('<?php echo BASE_URL . '/uploads/' . htmlspecialchars($asn['file_path']); ?>', 'Soal: <?php echo addslashes($asn['title']); ?>')" class="btn btn-secondary btn-sm" style="padding:0.5rem 1rem;">
                                        <i class="uil uil-eye"></i> Lihat Soal
                                    </button>
                                <?php endif; ?>
                                <a href="lesson_viewer.php?course_id=<?php echo $asn['course_id']; ?>&assignment_id=<?php echo $asn['id']; ?>"
                                    class="btn btn-primary btn-sm" style="padding:0.5rem 1rem; background:var(--warning); border:none;">
                                    Kerjakan <i class="uil uil-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <script>
                        (function () {
                            const deadline = new Date("<?php echo $asn['due_date']; ?>").getTime();
                            const timerId = "timer-<?php echo $asn['id']; ?>";

                            function update() {
                                const now = new Date().getTime();
                                const diff = deadline - now;

                                if (diff <= 0) {
                                    document.getElementById(timerId).innerHTML = "WAKTU HABIS";
                                    document.getElementById(timerId).style.color = "var(--danger)";
                                    return;
                                }

                                const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                                const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                const s = Math.floor((diff % (1000 * 60)) / 1000);

                                let display = "";
                                if (d > 0) display += d + "h ";
                                display += (h < 10 ? "0" + h : h) + "j " + (m < 10 ? "0" + m : m) + "m " + (s < 10 ? "0" + s : s) + "d";

                                document.getElementById(timerId).innerHTML = display;
                            }
                            update();
                            setInterval(update, 1000);
                        })();
                    </script>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <h3 style="margin-bottom:1.5rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:0.5rem;"><i
            class="uil uil-books"></i> Mata Pelajaran Anda</h3>

    <?php if ($enrolled_query && $enrolled_query->num_rows > 0): ?>
        <div class="grid grid-cols-3">
            <?php while ($c = $enrolled_query->fetch_assoc()): ?>
                <div class="glass-card" style="padding:1.5rem; display:flex; flex-direction:column;">
                    <?php if (!empty($c['thumbnail_url'])): ?>
                        <img src="<?php echo htmlspecialchars(BASE_URL . '/uploads/thumbnails/' . $c['thumbnail_url']); ?>"
                            alt="Thumbnail"
                            style="width:100%; height:140px; object-fit:cover; border-radius:var(--radius-sm); margin-bottom:1rem; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div
                            style="width:100%; height:140px; background:rgba(16, 185, 129, 0.2); border-radius:var(--radius-sm); margin-bottom:1rem; display:flex; align-items:center; justify-content:center; color:var(--secondary);">
                            <i class="uil uil-book-reader" style="font-size:2.5rem;"></i>
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size:1.25rem; margin-bottom:0.5rem; color:var(--text-main);">
                        <?php echo htmlspecialchars($c['title']); ?>
                    </h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem; flex-grow:1;">
                        <?php echo htmlspecialchars(substr($c['description'], 0, 90)); ?>...
                    </p>

                    <a href="lesson_viewer.php?course_id=<?php echo $c['id']; ?>" class="btn btn-primary btn-block"
                        style="text-align:center;">
                        Mulai Belajar <i class="uil uil-arrow-right"></i>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="glass-card"
            style="text-align:center; padding:4rem; margin-bottom:3rem; border:1px dashed var(--border);">
            <i class="uil uil-book-open" style="font-size:4rem; color:var(--text-muted);"></i>
            <h4 style="margin-top:1rem; font-size:1.2rem;">Belum ada Mata Pelajaran</h4>
            <p style="color:var(--text-muted); margin-top:0.5rem; max-width:400px; margin-left:auto; margin-right:auto;">
                Anda belum dimasukkan ke Rombel atau belum mendaftar mata pelajaran mana pun. Gunakan Kode Gabung di atas
                jika Anda memilikinya.</p>
        </div>
    <?php endif; ?>

</div>

<?php require_once '../components/footer.php'; ?>