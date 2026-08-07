<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$courses = $conn->query("SELECT * FROM courses WHERE teacher_id = $teacher_id ORDER BY id DESC");
// Also fetch classes for the course creation form
$classes = $conn->query("SELECT * FROM classes WHERE teacher_id = $teacher_id");

$page_title = 'Manajemen Course';
require_once '../components/header.php';
?>
<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-books"></i> Manajemen Course</h2>
            <p class="text-muted" style="margin:0;">Buat ruang Course, atur pendaftaran siswa, dan kelola materinya.</p>
        </div>
        <div class="page-actions">
            <button type="button" onclick="document.getElementById('modalAddCourse').classList.add('active')" class="btn btn-primary">
                <i class="uil uil-plus"></i> Buat Course Baru
            </button>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

    <div class="glass-card" style="margin-bottom:2rem;">
        <h3 style="margin-bottom:1.5rem;"><i class="uil uil-apps"></i> Daftar Course Aktif</h3>
        
        <div class="grid-auto">
            <?php
            $edit_course_modals = '';
            if($courses && $courses->num_rows > 0):
                while($c = $courses->fetch_assoc()):
                    $c_id = (int)$c['id'];
                    $linked_names = [];
                    $linked_ids = [];
                    $lq = $conn->query("
                        SELECT cl.id, cl.name
                        FROM course_classes cc
                        JOIN classes cl ON cl.id = cc.class_id
                        WHERE cc.course_id = $c_id
                        ORDER BY cl.name ASC
                    ");
                    if ($lq) {
                        while ($lr = $lq->fetch_assoc()) {
                            $linked_ids[(int)$lr['id']] = true;
                            $linked_names[] = $lr['name'];
                        }
                    }
                    $student_count_query = "
                        SELECT COUNT(DISTINCT student_id) as total_students
                        FROM (
                            SELECT student_id FROM enrollments WHERE course_id = $c_id
                            UNION
                            SELECT cs.student_id FROM course_classes cc 
                            JOIN class_students cs ON cc.class_id = cs.class_id 
                            WHERE cc.course_id = $c_id
                        ) AS combined_students
                    ";
                    $student_count = $conn->query($student_count_query)->fetch_assoc()['total_students'];
            ?>
                    <div class="course-card">
                        <?php if(!empty($c['thumbnail_url'])): ?>
                            <div class="course-card-cover" style="background-image:url('<?php echo htmlspecialchars(BASE_URL . '/uploads/thumbnails/' . $c['thumbnail_url']); ?>');"></div>
                        <?php else: ?>
                            <div class="course-card-cover is-empty"><i class="uil uil-image-slash"></i></div>
                        <?php endif; ?>
                        
                        <div class="course-card-actions">
                            <button type="button" onclick="document.getElementById('modalEditCourse<?php echo $c_id; ?>').classList.add('active')" class="btn btn-secondary btn-sm" title="Edit Detail Course"><i class="uil uil-pen"></i></button>
                            <form action="../actions/delete_course.php" method="POST" data-confirm="PERINGATAN! Menghapus Course ini akan menghancurkan SEKALI GUS seluruh Bab Materi, Tugas, Kuis, dan Nilai di dalamnya. Anda yakin ingin melanjutkannya?" style="margin:0;">
                                <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus Course"><i class="uil uil-trash-alt"></i></button>
                            </form>
                        </div>

                        <h4 class="course-card-title"><?php echo htmlspecialchars($c['title']); ?></h4>
                        
                        <div class="course-card-meta">
                            <?php if(!empty($c['enrollment_code'])): ?>
                                <span class="badge-rombel">Kode Gabung: <b><?php echo htmlspecialchars($c['enrollment_code']); ?></b></span>
                            <?php endif; ?>
                            <span class="badge-warn"><i class="uil uil-users-alt"></i> <?php echo (int)$student_count; ?> Siswa Peserta</span>
                            <?php if (!empty($linked_names)): ?>
                                <span class="rombel-chip"><i class="uil uil-building"></i> <?php echo htmlspecialchars(implode(', ', $linked_names)); ?></span>
                            <?php else: ?>
                                <span class="badge-muted"><i class="uil uil-building"></i> Belum ada rombel</span>
                            <?php endif; ?>
                        </div>
                        <p class="course-card-desc"><?php echo htmlspecialchars(substr($c['description'], 0, 90)); ?>...</p>
                        
                        <a href="course_view.php?id=<?php echo $c_id; ?>" class="btn btn-primary btn-block">Masuk ke Course Panel <i class="uil uil-arrow-right"></i></a>
                    </div>
            <?php
                    ob_start();
            ?>
                    <div id="modalEditCourse<?php echo $c_id; ?>" class="modal-overlay">
                        <div class="modal-box modal-box--lg">
                            <div class="modal-header">
                                <h3><i class="uil uil-pen"></i> Edit Info Course</h3>
                                <button type="button" onclick="document.getElementById('modalEditCourse<?php echo $c_id; ?>').classList.remove('active')" class="btn-close" aria-label="Tutup"><i class="uil uil-times"></i></button>
                            </div>
                            <form action="../actions/edit_course.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                <div class="form-group">
                                    <label class="form-label">Judul Course</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($c['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Deskripsi Singkat</label>
                                    <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($c['description']); ?></textarea>
                                </div>
                                <div class="grid grid-cols-2 modal-form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Ubah Gambar Sampul (Opsional)</label>
                                        <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
                                        <small style="color:var(--text-muted);">Kosongkan jika tidak ingin mengganti.</small>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Kode Gabung Mandiri (Opsional)</label>
                                        <input type="text" name="enrollment_code" class="form-control" value="<?php echo htmlspecialchars((string)$c['enrollment_code']); ?>">
                                    </div>
                                </div>
                                <div class="form-group modal-panel">
                                    <label class="form-label"><i class="uil uil-users-alt"></i> Rombel yang terhubung</label>
                                    <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.8rem;">
                                        Centang untuk menambah, hilangkan centang untuk mengeluarkan rombel dari course ini.
                                    </div>
                                    <?php if ($classes && $classes->num_rows > 0): ?>
                                        <div class="check-grid" style="max-height:160px;">
                                            <?php
                                            $classes->data_seek(0);
                                            while ($cl = $classes->fetch_assoc()):
                                                $cid = (int)$cl['id'];
                                                $checked = isset($linked_ids[$cid]) ? 'checked' : '';
                                            ?>
                                                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                                    <input type="checkbox" name="allowed_classes[]" value="<?php echo $cid; ?>" <?php echo $checked; ?>>
                                                    <span><?php echo htmlspecialchars($cl['name']); ?></span>
                                                </label>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="color:var(--warning); font-size:0.85rem; margin:0;">Belum ada rombel. Buat di Manajemen Rombel terlebih dahulu.</p>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="uil uil-save"></i> Terapkan Pembaruan</button>
                            </form>
                        </div>
                    </div>
            <?php
                    $edit_course_modals .= ob_get_clean();
                endwhile;
            else:
            ?>
                <div style="grid-column:1 / -1; text-align:center; padding:4rem; border:1px dashed var(--border); border-radius:var(--radius-sm);">
                    <i class="uil uil-books" style="font-size:4rem; color:var(--text-muted);"></i>
                    <p style="color:var(--text-muted); margin-top:1rem; font-size:1.1rem;">Belum ada Data Course. Silakan tekan tombol "Buat Course Baru" di sudut kanan atas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php echo $edit_course_modals ?? ''; ?>

<!-- Modal ADD COURSE -->
<div id="modalAddCourse" class="modal-overlay">
    <div class="modal-box modal-box--lg">
        <div class="modal-header">
            <h3><i class="uil uil-folder-plus"></i> Rakit Course Baru</h3>
            <button type="button" onclick="document.getElementById('modalAddCourse').classList.remove('active')" class="btn-close" aria-label="Tutup"><i class="uil uil-times"></i></button>
        </div>
        <form action="../actions/add_course.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Judul Course</label>
                <input type="text" name="title" class="form-control" placeholder="Informatika Kelas VII MTs" required>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi Singkat</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Jelaskan mengenai pelajaran ini..." required></textarea>
            </div>
            <div class="grid grid-cols-2 modal-form-grid">
                <div class="form-group">
                    <label class="form-label">Gambar Sampul (Opsional)</label>
                    <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Gabung Opsional</label>
                    <input type="text" name="enrollment_code" class="form-control" placeholder="KODE123">
                </div>
            </div>
            
            <div class="form-group modal-panel">
                <label class="form-label"><i class="uil uil-users-alt"></i> Daftarkan Otomatis Rombel Berikut:</label>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem;">Centang kelas yang siswanya berhak mendapat akses langsung ke Course ini tanpa input kode manual.</div>
                <?php if($classes && $classes->num_rows > 0): ?>
                    <div class="check-grid" style="max-height:150px;">
                        <?php 
                        $classes->data_seek(0);
                        while($cl = $classes->fetch_assoc()): ?>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" name="allowed_classes[]" value="<?php echo $cl['id']; ?>"> 
                                <span><?php echo htmlspecialchars($cl['name']); ?></span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--warning); font-size:0.85rem; margin:0;">Anda belum memiliki Rombel. Buat di Manajemen Rombel agar bisa mendaftarkan siswa massal.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><i class="uil uil-rocket"></i> Luncurkan Course</button>
        </form>
    </div>
</div>

<style>
.course-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 1.5rem;
    padding-top: 140px;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.course-card-cover {
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 140px;
    background-position: center;
    background-size: cover;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}
.course-card-cover.is-empty {
    background: rgba(13, 148, 136, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--secondary);
    font-size: 2rem;
}
.course-card-actions {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    gap: 0.5rem;
    z-index: 5;
}
.course-card-title {
    margin-top: 1rem;
    margin-bottom: 0.8rem;
    color: var(--text-main);
    font-size: 1.15rem;
    line-height: 1.4;
    padding-right: 3rem;
}
.course-card-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.course-card-desc {
    color: var(--text-muted);
    font-size: 0.9rem;
    flex: 1;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}
.badge-warn {
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    display: inline-block;
}
.badge-muted {
    background: var(--primary-soft);
    color: var(--text-muted);
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    display: inline-block;
}
</style>

<?php require_once '../components/footer.php'; ?>
