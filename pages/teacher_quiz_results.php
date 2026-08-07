<?php
require_once '../core/config.php';

$role = isset($_SESSION['user_role']) ? trim(strtolower((string) $_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $role !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

$lesson_id = isset($_GET['lesson_id']) ? (int) $_GET['lesson_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$teacher_id = (int) $_SESSION['user_id'];

// Verifikasi lesson kuis milik guru
$l_query = $conn->query("
    SELECT l.*
    FROM lessons l
    JOIN modules m ON l.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE l.id = $lesson_id
      AND c.teacher_id = $teacher_id
      AND l.content_type = 'quiz'
    LIMIT 1
");
if (!$l_query || $l_query->num_rows === 0) {
    header("Location: course_view.php?id=$course_id");
    exit;
}
$lesson = $l_query->fetch_assoc();

// Deteksi kolom waktu submit (skema lama/baru berbeda)
$timeColumn = 'id'; // fallback aman
$cols = $conn->query('SHOW COLUMNS FROM quiz_attempts');
if ($cols) {
    $names = [];
    while ($c = $cols->fetch_assoc()) {
        $names[strtolower($c['Field'])] = true;
    }
    if (isset($names['attempted_at'])) {
        $timeColumn = 'attempted_at';
    } elseif (isset($names['created_at'])) {
        $timeColumn = 'created_at';
    } elseif (isset($names['submitted_at'])) {
        $timeColumn = 'submitted_at';
    }
}

$orderTime = $timeColumn === 'id' ? 'qa.id ASC' : "qa.`$timeColumn` ASC";

$results = $conn->query("
    SELECT qa.*, u.name, u.username AS nisn
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    WHERE qa.lesson_id = $lesson_id
    ORDER BY qa.score DESC, $orderTime
");

$page_title = 'Rekap Nilai Kuis';
require_once '../components/header.php';
?>

<div class="container main-content" style="padding-top:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; gap:1rem; flex-wrap:wrap;">
        <div>
            <h2><i class="uil uil-chart-bar"></i> Buku Nilai Kuis</h2>
            <p class="text-muted">Bab: <span style="font-weight:bold; color:var(--primary);"><?php echo htmlspecialchars($lesson['title']); ?></span></p>
        </div>
        <a href="course_view.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
            <i class="uil uil-arrow-left"></i> Kembali ke Course
        </a>
    </div>

    <div class="glass-card" style="padding:1rem; overflow-x:auto;">
        <table class="table" style="min-width:600px;">
            <thead style="background:rgba(0,0,0,0.2);">
                <tr>
                    <th style="width:50px; text-align:center;">No</th>
                    <th>Nama Lengkap</th>
                    <th>NISN Siswa</th>
                    <th>Skor Perolehan</th>
                    <th>Waktu Submit</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results && $results->num_rows > 0): ?>
                    <?php $no = 1; while ($r = $results->fetch_assoc()): ?>
                        <?php
                        $score = (int) $r['score'];
                        $color = $score >= 75 ? 'var(--secondary)' : 'var(--danger)';
                        $rawTime = $r[$timeColumn] ?? ($r['created_at'] ?? ($r['attempted_at'] ?? ($r['submitted_at'] ?? null)));
                        $timeLabel = '-';
                        if (!empty($rawTime) && $timeColumn !== 'id') {
                            $ts = strtotime((string) $rawTime);
                            if ($ts) {
                                $timeLabel = date('d M Y, H:i', $ts);
                            }
                        }
                        ?>
                        <tr>
                            <td style="text-align:center; color:var(--text-muted);"><?php echo $no++; ?></td>
                            <td><b style="color:var(--text-main);"><?php echo htmlspecialchars($r['name']); ?></b></td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($r['nisn']); ?></td>
                            <td>
                                <span style="font-size:1.5rem; font-weight:800; color:<?php echo $color; ?>;"><?php echo $score; ?></span>
                                <small> / 100</small>
                            </td>
                            <td style="color:var(--text-muted); font-size:0.9rem;">
                                <?php echo htmlspecialchars($timeLabel); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                            <i class="uil uil-clipboard-blank" style="font-size:3rem;"></i><br>
                            Belum ada siswa yang merampungkan Kuis ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../components/footer.php'; ?>
