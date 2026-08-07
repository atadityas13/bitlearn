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
            <?php if($courses && $courses->num_rows > 0): ?>
                <?php while($c = $courses->fetch_assoc()): $c_id = $c['id']; ?>
                    <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); padding:1.5rem; padding-top:140px; display:flex; flex-direction:column; position:relative;">
                        <?php if(!empty($c['thumbnail_url'])): ?>
                            <div style="position:absolute; top:0; left:0; width:100%; height:140px; background:url('<?php echo htmlspecialchars(BASE_URL . '/uploads/thumbnails/' . $c['thumbnail_url']); ?>') center/cover; border-radius:var(--radius-sm) var(--radius-sm) 0 0;"></div>
                        <?php else: ?>
                            <div style="position:absolute; top:0; left:0; width:100%; height:140px; background:rgba(16, 185, 129, 0.2); border-radius:var(--radius-sm) var(--radius-sm) 0 0; display:flex; align-items:center; justify-content:center; color:var(--secondary);"><i class="uil uil-image-slash" style="font-size:2rem;"></i></div>
                        <?php endif; ?>
                        
                        <!-- Top Action Dots -->
                        <div style="position:absolute; top:1rem; right:1rem; display:flex; gap:0.5rem; z-index:10;">
                            <!-- Edit Button -->
                            <button onclick="document.getElementById('modalEditCourse<?php echo $c_id; ?>').classList.add('active')" class="btn btn-secondary btn-sm" style="padding:0.3rem 0.5rem; background:rgba(0,0,0,0.3); border:none;" title="Edit Detail Course"><i class="uil uil-pen"></i></button>
                            <!-- Delete Button -->
                            <form action="../actions/delete_course.php" method="POST" data-confirm="PERINGATAN! Menghapus Course ini akan menghancurkan SEKALI GUS seluruh Bab Materi, Tugas, Kuis, dan Nilai di dalamnya. Anda yakin ingin melanjutkannya?" style="margin:0;">
                                <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" style="padding:0.3rem 0.5rem; background:rgba(239, 68, 68, 0.2); border:none;" title="Hapus Course"><i class="uil uil-trash-alt"></i></button>
                            </form>
                        </div>

                        <h4 style="margin-top:1rem; margin-bottom:0.8rem; color:var(--text-main); font-size:1.15rem; line-height:1.4; padding-right:3rem;"><?php echo htmlspecialchars($c['title']); ?></h4>
                        
                        <div style="display:flex; gap:0.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap;">
                            <?php if(!empty($c['enrollment_code'])): ?>
                                <span style="background:rgba(16, 185, 129, 0.2); color:var(--secondary); padding:0.2rem 0.6rem; border-radius:12px; font-size:0.8rem; display:inline-block;">
                                    Kode Gabung: <b><?php echo htmlspecialchars($c['enrollment_code']); ?></b>
                                </span>
                            <?php endif; ?>
                            
                            <?php
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
                            <span style="background:rgba(245, 158, 11, 0.2); color:var(--warning); padding:0.2rem 0.6rem; border-radius:12px; font-size:0.8rem; display:inline-block;" title="Jumlah siswa yang dimasukkan ke Course ini">
                                <i class="uil uil-users-alt"></i> <?php echo $student_count; ?> Siswa Peserta
                            </span>
                            <?php if (!empty($linked_names)): ?>
                                <span style="background:var(--primary-muted); color:var(--primary-hover); padding:0.2rem 0.6rem; border-radius:12px; font-size:0.8rem; display:inline-block;" title="Rombel terhubung">
                                    <i class="uil uil-building"></i> <?php echo htmlspecialchars(implode(', ', $linked_names)); ?>
                                </span>
                            <?php else: ?>
                                <span style="background:rgba(255,255,255,0.06); color:var(--text-muted); padding:0.2rem 0.6rem; border-radius:12px; font-size:0.8rem; display:inline-block;">
                                    <i class="uil uil-building"></i> Belum ada rombel
                                </span>
                            <?php endif; ?>
                        </div>
                        <p style="color:var(--text-muted); font-size:0.9rem; flex:1; margin-bottom:1.5rem; line-height:1.6;"><?php echo htmlspecialchars(substr($c['description'], 0, 90)); ?>...</p>
                        
                        <a href="course_view.php?id=<?php echo $c_id; ?>" class="btn btn-primary btn-block" style="text-align:center;">Masuk ke Couse Panel <i class="uil uil-arrow-right"></i></a>
                    </div>

                    <!-- MODAL EDIT COURSE -->
                    <div id="modalEditCourse<?php echo $c_id; ?>" class="modal-overlay">
                        <div class="modal-box">
                            <div class="modal-header">
                                <h3><i class="uil uil-pen"></i> Edit Info Course</h3>
                                <button onclick="document.getElementById('modalEditCourse<?php echo $c_id; ?>').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
                            </div>
                            <form action="../actions/edit_course.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                <div class="form-group">
                                    <label class="form-label">Judul Pelajaran</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($c['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Deskripsi Singkat</label>
                                    <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($c['description']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Kode Gabung Mandiri (Opsional)</label>
                                    <input type="text" name="enrollment_code" class="form-control" value="<?php echo htmlspecialchars((string)$c['enrollment_code']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ubah Gambar Sampul (Opsional)</label>
                                    <input type="file" name="thumbnail_file" class="form-control" accept="image/*" style="background:var(--background);">
                                    <small style="color:var(--text-muted);">Abaikan jika Anda tidak ingin mengganti sampul saat ini.</small>
                                </div>
                                <div class="form-group" style="background:rgba(0,0,0,0.2); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border);">
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

                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column:span 3; text-align:center; padding:4rem; border:1px dashed var(--border); border-radius:var(--radius-sm);">
                    <i class="uil uil-books" style="font-size:4rem; color:var(--text-muted);"></i>
                    <p style="color:var(--text-muted); margin-top:1rem; font-size:1.1rem;">Belum ada Data Course. Silakan tekan tombol "Buat Course Baru" di sudut kanan atas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal ADD COURSE -->
<div id="modalAddCourse" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="uil uil-folder-plus"></i> Rakit Course Baru</h3>
            <button onclick="document.getElementById('modalAddCourse').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
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
            <div class="grid grid-cols-2">
                <div class="form-group">
                    <label class="form-label">Gambar Sampul (Opsional)</label>
                    <input type="file" name="thumbnail_file" class="form-control" style="background:var(--background);" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Kode Gabung Opsional</label>
                    <input type="text" name="enrollment_code" class="form-control" placeholder="KODE123">
                </div>
            </div>
            
            <div class="form-group" style="background:rgba(0,0,0,0.2); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--border);">
                <label class="form-label"><i class="uil uil-users-alt"></i> Daftarkan Otomatis Rombel Berikut:</label>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:1rem;">Centang kelas yang siswanya berhak mendapat *Akses Langsung* ke Course ini tanpa input kode manual.</div>
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
                    <p style="color:var(--warning); font-size:0.85rem;">Anda belum memiliki Rombel. Buat di Manajemen Rombel agar bisa mendaftarkan siswa massal.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-secondary btn-block" style="padding:1rem; font-size:1.1rem;"><i class="uil uil-rocket"></i> Luncurkan Course</button>
        </form>
    </div>
</div>

<script>
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
}
</script>
<?php require_once '../components/footer.php'; ?>
