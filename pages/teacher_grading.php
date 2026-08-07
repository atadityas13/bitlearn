<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$query = "SELECT s.id as sub_id, s.file_path, s.grade, s.created_at as submitted_at, s.feedback, u.name as st_name, a.title as assign_title, c.title as course_title 
          FROM submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id JOIN users u ON s.student_id = u.id 
          WHERE c.teacher_id = $teacher_id ORDER BY s.created_at DESC";
$subs = $conn->query($query);

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

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>

    <!-- UI List of Subs -->
    <?php if($subs && $subs->num_rows > 0): ?>
        <div class="table-responsive glass-card" style="padding:1rem;">
            <table class="table" style="min-width:800px; margin-top:0;">
                <thead><tr><th>Nama Siswa / Rombel</th><th>Penugasan</th><th>File Lampiran</th><th>Status / Skor</th><th>Aksi Simpan</th></tr></thead>
                <tbody>
                    <?php while($sub = $subs->fetch_assoc()): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($sub['st_name']); ?></b><br><small style="color:var(--text-muted);"><?php echo htmlspecialchars($sub['course_title']); ?></small><br><span style="font-size:0.8rem; color:var(--text-muted);"><i class="uil uil-clock"></i> <?php echo date('d M, H:i', strtotime($sub['submitted_at'])); ?></span></td>
                        <td><?php echo htmlspecialchars($sub['assign_title']); ?></td>
                        <td>
                            <div class="action-row" style="width:auto; flex-wrap:nowrap;">
                            <a href="<?php echo BASE_URL . '/uploads/' . htmlspecialchars($sub['file_path']); ?>" download class="btn btn-secondary btn-sm"><i class="uil uil-cloud-download"></i></a>
                            <button type="button" onclick="openPreview('<?php echo BASE_URL . '/uploads/' . htmlspecialchars($sub['file_path']); ?>', 'Submisi: <?php echo addslashes($sub['st_name']); ?>')" class="btn btn-primary btn-sm">
                                <i class="uil uil-eye"></i> Lihat
                            </button>
                            </div>
                        </td>
                        <td>
                            <?php if($sub['grade'] !== null): ?>
                                <span style="background:rgba(16, 185, 129, 0.2); color:var(--secondary); padding:0.2rem 0.6rem; border-radius:12px; font-weight:bold;"><?php echo $sub['grade']; ?> / 100</span>
                            <?php else: ?>
                                <span style="background:rgba(245, 158, 11, 0.2); color:var(--warning); padding:0.2rem 0.6rem; border-radius:12px;">Menunggu Penilaian</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form action="../actions/grade_submission.php" method="POST" class="action-row" style="margin:0; width:auto;">
                                <input type="hidden" name="sub_id" value="<?php echo $sub['sub_id']; ?>">
                                <input type="number" name="grade" class="form-control" placeholder="0-100" style="width:80px; padding:0.4rem; flex:0 0 auto;" min="0" max="100" value="<?php echo $sub['grade']; ?>" required>
                                <input type="text" name="feedback" class="form-control" placeholder="Pesan Feedback..." style="width:150px; max-width:100%; padding:0.4rem; flex:1 1 120px;" value="<?php echo htmlspecialchars((string)$sub['feedback']); ?>">
                                <button type="submit" class="btn btn-primary" style="padding:0.4rem 1rem;"><i class="uil uil-save"></i> Input</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="glass-card" style="text-align:center; padding:4rem;">
            <i class="uil uil-inbox" style="font-size:4rem; color:var(--text-muted);"></i>
            <h3 style="margin-top:1rem;">Tidak ada Penugasan yang masuk.</h3>
            <p style="color:var(--text-muted);">Belum ada siswa yang mengumpulkan tugas.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../components/footer.php'; ?>
