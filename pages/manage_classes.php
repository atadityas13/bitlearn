<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$classes = $conn->query("SELECT * FROM classes WHERE teacher_id = $teacher_id ORDER BY created_at DESC");

$page_title = 'Manajemen Rombel';
require_once '../components/header.php';
?>
<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-building"></i> Manajemen Rombongan Belajar</h2>
            <p class="text-muted" style="margin:0;">Kelengkapan Grup Kelas untuk mengontrol pendaftaran siswa secara terpusat.</p>
        </div>
        <div class="page-actions">
            <button type="button" onclick="document.getElementById('modalAddClass').classList.add('active')" class="btn btn-primary">
                <i class="uil uil-plus"></i> Tambah Rombel
            </button>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

    <!-- Daftar Rombel List -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.5rem;"><i class="uil uil-list-ul"></i> Daftar Rombel Aktif</h3>
        <?php
        $classes->data_seek(0);
        if($classes->num_rows > 0):
            while($cl = $classes->fetch_assoc()):
                $c_id = $cl['id'];
                $st = $conn->query("SELECT student_id FROM class_students WHERE class_id = $c_id");
        ?>
            <div class="list-row">
                <div>
                    <h4 style="color:var(--primary); font-size:1.2rem; margin-bottom:0.2rem;"><?php echo htmlspecialchars($cl['name']); ?></h4>
                    <p style="color:var(--text-muted); font-size:0.9rem; margin:0;"><i class="uil uil-users-alt"></i> <?php echo $st->num_rows; ?> Anggota Siswa Didaftarkan</p>
                </div>
                <div class="list-row-actions">
                    <a href="manage_students.php?rombel=<?php echo $c_id; ?>" class="btn btn-primary btn-sm" style="padding:0.4rem 0.8rem; background:var(--warning); color:#fff; border:none;">
                        <i class="uil uil-users-alt"></i> Kelola Anggota
                    </a>
                    <button type="button" onclick="document.getElementById('modalEditClass<?php echo $c_id; ?>').classList.add('active')" class="btn btn-secondary btn-sm" style="padding:0.4rem 0.8rem;">
                        <i class="uil uil-pen"></i> Edit
                    </button>
                    <form action="../actions/delete_class.php" method="POST" data-confirm="Apakah Anda yakin ingin menghapus rombel ini secara permanen? Seluruh siswa akan kehilangan akses kelasnya." style="margin:0;">
                         <input type="hidden" name="class_id" value="<?php echo $c_id; ?>">
                         <button type="submit" class="btn btn-danger btn-sm" style="padding:0.4rem 0.8rem;"><i class="uil uil-trash-alt"></i> Hapus</button>
                    </form>
                </div>
            </div>

            <!-- Modal EDIT untuk class spesifik -->
            <div id="modalEditClass<?php echo $c_id; ?>" class="modal-overlay">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>Edit Nama Rombel</h3>
                        <button onclick="document.getElementById('modalEditClass<?php echo $c_id; ?>').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
                    </div>
                    <form action="../actions/edit_class.php" method="POST">
                        <input type="hidden" name="class_id" value="<?php echo $c_id; ?>">
                        <div class="form-group">
                            <label class="form-label">Nama Rombel</label>
                            <input type="text" name="class_name" class="form-control" value="<?php echo htmlspecialchars($cl['name']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="uil uil-save"></i> Simpan Perubahan</button>
                    </form>
                </div>
            </div>

        <?php 
            endwhile;
        else:
        ?>
            <div style="text-align:center; padding:3rem; border:1px dashed var(--border); border-radius:var(--radius-sm);">
                <i class="uil uil-building" style="font-size:3rem; color:var(--text-muted);"></i>
                <p style="color:var(--text-muted); margin-top:1rem;">Anda belum mendaftarkan Grup Rombel apa pun.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal ADD CLASS -->
<div id="modalAddClass" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3 style="color:var(--text-main);"><i class="uil uil-plus-circle"></i> Buat Rombel Baru</h3>
            <button onclick="document.getElementById('modalAddClass').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>
        <form action="../actions/add_class.php" method="POST">
            <div class="form-group">
                <label class="form-label">Format Penamaan Cth: VII.1 atau X-IPA</label>
                <input type="text" name="class_name" class="form-control" placeholder="VII.1" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="uil uil-check"></i> Simpan Daftar</button>
        </form>
    </div>
</div>

<!-- Click Outside to Close Modals Component Script -->
<script>
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
}
</script>

<?php require_once '../components/footer.php'; ?>
