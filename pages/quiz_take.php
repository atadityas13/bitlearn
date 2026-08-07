<?php
require_once '../core/config.php';
$r = isset($_SESSION['user_role']) ? trim(strtolower((string)$_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || $r !== 'student') { 
    if($r === 'teacher') header("Location: teacher_dashboard.php");
    else { session_destroy(); header("Location: ../index.php"); }
    exit; 
}

$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$student_id = $_SESSION['user_id'];

// Get lesson and course info
$les_q = $conn->query("SELECT lessons.title, lessons.content_type, lessons.is_published, courses.id as course_id, courses.title as course_title FROM lessons JOIN modules ON lessons.module_id = modules.id JOIN courses ON modules.course_id = courses.id WHERE lessons.id = $lesson_id AND lessons.is_published = 1");
if(!$les_q || $les_q->num_rows === 0) {
    header("Location: student_dashboard.php"); exit;
}
$lesson = $les_q->fetch_assoc();
$course_id = $lesson['course_id'];

// Check if already taken
$attempt_q = $conn->query("SELECT * FROM quiz_attempts WHERE student_id = $student_id AND lesson_id = $lesson_id");
$already_taken = ($attempt_q && $attempt_q->num_rows > 0);
$attempt = $already_taken ? $attempt_q->fetch_assoc() : null;

// Get Questions
$questions = $conn->query("SELECT * FROM quiz_questions WHERE lesson_id = $lesson_id ORDER BY id ASC");
$total_q = $questions->num_rows;

$page_title = 'Ujian Evaluasi: ' . htmlspecialchars($lesson['title']);
require_once '../components/header.php';
?>

<div class="container main-content" style="padding-top:2rem; max-width:800px;">
    
    <div style="margin-bottom:2rem;">
        <a href="lesson_viewer.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson_id; ?>" class="btn btn-secondary btn-sm" style="margin-bottom:1rem;">
            <i class="uil uil-arrow-left"></i> Kembali ke Modul Pembelajaran
        </a>
        <h2 style="font-size:2rem;"><i class="uil uil-clipboard-notes" style="color:var(--primary);"></i> <?php echo htmlspecialchars($lesson['title']); ?></h2>
        <p style="color:var(--text-muted); font-size:1.1rem; margin-top:0.3rem;">Mata Pelajaran: <?php echo htmlspecialchars($lesson['course_title']); ?></p>
    </div>

    <?php if($already_taken): ?>
        <div class="glass-card" style="text-align:center; padding:4rem; border-color:var(--primary);">
            <i class="uil uil-award" style="font-size:5rem; color:var(--warning); display:block; margin-bottom:1rem;"></i>
            <h3 style="font-size:1.8rem; margin-bottom:0.5rem;">Anda sudah menyelesaikan Evaluasi Ini!</h3>
            <p style="color:var(--text-muted); margin-bottom:2rem;">Tes ini hanya dapat dikerjakan satu kali. Nilai Anda telah terekam pada sistem.</p>
            
            <div style="background:rgba(0,0,0,0.3); padding:2rem; border-radius:var(--radius-sm); border:1px solid var(--border); display:inline-block; min-width:300px;">
                <div style="font-size:1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem;">Skor Akhir Anda</div>
                <div style="font-size:4rem; font-weight:800; color:var(--primary); line-height:1;"><?php echo $attempt['score']; ?></div>
            </div>
            
            <div style="margin-top:3rem;">
                <a href="lesson_viewer.php?course_id=<?php echo $course_id; ?>&lesson_id=<?php echo $lesson_id; ?>" class="btn btn-primary">
                    <i class="uil uil-check-circle"></i> Lanjutkan Pembelajaran
                </a>
            </div>
        </div>

    <?php else: ?>
        <?php if($total_q === 0): ?>
            <div class="alert alert-warning">
                <i class="uil uil-exclamation-triangle"></i> Pengajar belum menyusun soal untuk tes ini. Silakan kembali lagi nanti!
            </div>
        <?php else: ?>
            <div class="glass-card">
                <div style="background:var(--primary-soft); border:1px solid var(--primary-muted); padding:1.5rem; border-radius:var(--radius-sm); margin-bottom:2rem;">
                    <h4 style="color:var(--primary); margin-bottom:0.5rem;"><i class="uil uil-info-circle"></i> Petunjuk Pengerjaan</h4>
                    <ul style="color:var(--text-muted); padding-left:1.5rem; margin:0; line-height:1.6;">
                        <li>Terdapat <b><?php echo $total_q; ?> butir soal</b> bertipe Pilihan Ganda.</li>
                        <li>Pilihlah salah satu jawaban yang Anda anggap paling tepat.</li>
                        <li>Pastikan koneksi internet stabil; jika halaman dimuat ulang, jawaban Anda akan hilang.</li>
                        <li>Ujian ini mengikat kriteria kelulusan materi, nilainya akan dilaporkan ke Guru.</li>
                    </ul>
                </div>

                <form action="../actions/submit_quiz.php" method="POST">
                    <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <?php 
                    $num = 1;
                    $questions->data_seek(0);
                    while($q = $questions->fetch_assoc()): 
                        $qid = $q['id'];
                        $opts = $conn->query("SELECT * FROM quiz_options WHERE question_id = $qid ORDER BY RAND()"); // Randomize visually
                    ?>
                        <div style="margin-bottom:2.5rem; background:rgba(255,255,255,0.02); padding:2rem; border-radius:var(--radius-sm); border:1px solid var(--border);">
                            <h4 style="font-size:1.2rem; line-height:1.6; margin-bottom:1.5rem; color:var(--text-main);">
                                <span style="background:var(--primary); color:white; padding:0.2rem 0.6rem; border-radius:6px; font-size:0.9rem; margin-right:0.5rem; vertical-align:middle;"><?php echo $num; ?></span> 
                                <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                            </h4>
                            
                            <?php if($opts && $opts->num_rows > 0): ?>
                                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <?php while($opt = $opts->fetch_assoc()): ?>
                                        <label style="display:flex; align-items:center; gap:1rem; padding:1rem; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; transition:all 0.2s;">
                                            <input type="radio" name="answers[<?php echo $qid; ?>]" value="<?php echo $opt['id']; ?>" required style="width:20px; height:20px;">
                                            <span style="font-size:1.05rem;"><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php $num++; endwhile; ?>
                    
                    <div style="margin-top:3rem; padding-top:2rem; border-top:1px solid rgba(255,255,255,0.1); text-align:center;">
                        <button type="submit" class="btn btn-primary" style="font-size:1.2rem; padding:1rem 3rem; background:#10B981; border:none; box-shadow:0 4px 15px rgba(16, 185, 129, 0.4);">
                            <i class="uil uil-check-circle"></i> Selesai tes
                        </button>
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-top:1rem;">Tindakan ini tidak bisa dibatalkan setelah diserahkan.</p>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Highlight selected radio logic inline styling via vanilla JS to make it look premium -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            // Reset all in same group
            const group = e.target.name;
            document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
                r.parentElement.style.borderColor = 'var(--border)';
                r.parentElement.style.background = 'var(--surface)';
            });
            // highlight active
            if(e.target.checked) {
                e.target.parentElement.style.borderColor = 'var(--primary)';
                e.target.parentElement.style.background = 'rgba(249, 115, 22, 0.12)';
            }
        });
    });
});
</script>

<?php require_once '../components/footer.php'; ?>
