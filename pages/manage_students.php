<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$classes = $conn->query("SELECT id, name FROM classes WHERE teacher_id = $teacher_id ORDER BY name ASC");

// Pagination & Filter Logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$filter_query = "";
$filter_class_id = isset($_GET['rombel']) && is_numeric($_GET['rombel']) ? (int)$_GET['rombel'] : 0;
if ($filter_class_id > 0) {
    $filter_query = " AND c.id = $filter_class_id ";
}

// Get Total Rows
$count_query = "
    SELECT COUNT(u.id) as total
    FROM users u
    JOIN class_students cs ON u.id = cs.student_id
    JOIN classes c ON cs.class_id = c.id
    WHERE c.teacher_id = $teacher_id $filter_query
";
$total_res = $conn->query($count_query);
$total_rows = $total_res ? $total_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_rows / $limit);

// Get Paginated Data
$query = "
    SELECT u.id as student_id, u.name, u.username as nisn, u.temp_password, c.id as class_id, c.name as class_name
    FROM users u
    JOIN class_students cs ON u.id = cs.student_id
    JOIN classes c ON cs.class_id = c.id
    WHERE c.teacher_id = $teacher_id $filter_query
    ORDER BY c.name ASC, u.name ASC
    LIMIT $limit OFFSET $offset
";
$students = $conn->query($query);

$page_title = 'Manajemen Siswa V6';
require_once '../components/header.php';
?>
<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-users-alt"></i> Direktori Siswa</h2>
            <p class="text-muted" style="margin:0;">Awasi, perbarui, dan kontrol akun berdasar Rombel aktif.</p>
        </div>
        <div class="page-actions">
            <button type="button" onclick="document.getElementById('modalImportExcel').classList.add('active')" class="btn btn-secondary" style="border-color:var(--secondary); color:var(--secondary); background:rgba(16, 185, 129, 0.1);">
                <i class="uil uil-file-upload"></i> Impor Excel
            </button>
            <button type="button" onclick="document.getElementById('modalAddStudent').classList.add('active')" class="btn btn-primary" style="background:var(--warning); color:#fff; border:none;">
                <i class="uil uil-user-plus"></i> Registrasi Siswa
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="glass-card" style="padding:1rem 1.5rem; margin-bottom:1.5rem;">
        <form action="" method="GET" class="filter-row" style="margin:0;">
            <strong style="white-space:nowrap;"><i class="uil uil-filter"></i> Filter Rombel:</strong>
            <select name="rombel" class="form-control" style="background:var(--background);" onchange="this.form.submit()">
                <option value="0">Tampilkan Semua Rombel</option>
                <?php 
                if($classes) {
                    $classes->data_seek(0);
                    while($cl = $classes->fetch_assoc()) {
                        $sel = ($filter_class_id == $cl['id']) ? 'selected' : '';
                        echo "<option value='{$cl['id']}' $sel>" . htmlspecialchars($cl['name']) . "</option>";
                    }
                }
                ?>
            </select>
            <?php if($filter_class_id > 0): ?>
                <a href="manage_students.php" class="btn btn-secondary btn-sm"><i class="uil uil-times"></i> Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>

    <div class="glass-card" style="padding:1rem;">
        <div class="table-responsive">
        <table class="table" style="min-width:800px;">
            <thead style="background:rgba(0,0,0,0.2);">
                <tr>
                    <th style="width:50px; text-align:center;">No</th>
                    <th>NISN (Username)</th>
                    <th>Nama Lengkap</th>
                    <th>Rombel Aktif</th>
                    <th>Sandi Akun</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if($students && $students->num_rows > 0): ?>
                    <?php 
                    $no = $offset + 1;
                    while($s = $students->fetch_assoc()): 
                        $s_id = $s['student_id']; $c_id = $s['class_id']; 
                    ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="text-align:center; color:var(--text-muted);"><?php echo $no++; ?></td>
                            <td><b style="color:var(--primary); font-family:monospace;"><?php echo htmlspecialchars($s['nisn']); ?></b></td>
                            <td style="color:var(--text-main); font-weight:600;"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><span style="background:rgba(16, 185, 129, 0.2); color:var(--secondary); padding:0.2rem 0.6rem; border-radius:12px; font-weight:bold; font-size:0.8rem;"><?php echo htmlspecialchars($s['class_name']); ?></span></td>
                            <td><code style="background:#2d3748; padding:0.2rem 0.6rem; border-radius:4px; color:var(--warning);"><?php echo !empty($s['temp_password']) ? htmlspecialchars($s['temp_password']) : '******'; ?></code></td>
                            
                            <td style="text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
                                <button onclick="document.getElementById('modalEditStudent<?php echo $s_id . '_' . $c_id; ?>').classList.add('active')" class="btn btn-secondary btn-sm" style="padding:0.4rem 0.6rem;" title="Edit Siswa"><i class="uil uil-pen"></i> Edit</button>
                                
                                <form action="../actions/delete_student.php" method="POST" data-confirm="PERINGATAN! Ini akan menghapus akun Siswa secara PERMANEN dari seluruh sistem, bukan hanya mengeluarkannya dari rombel. Lanjutkan?" style="margin:0;">
                                    <input type="hidden" name="student_id" value="<?php echo $s_id; ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $c_id; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding:0.4rem 0.6rem;" title="Hapus Permanen Akun"><i class="uil uil-trash-alt"></i> Hapus</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal EDIT Student -->
                        <div id="modalEditStudent<?php echo $s_id . '_' . $c_id; ?>" class="modal-overlay">
                            <div class="modal-box">
                                <div class="modal-header">
                                    <h3><i class="uil uil-user-check"></i> Modifikasi Data Siswa</h3>
                                    <button onclick="document.getElementById('modalEditStudent<?php echo $s_id . '_' . $c_id; ?>').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
                                </div>
                                <form action="../actions/edit_student.php" method="POST">
                                    <input type="hidden" name="student_id" value="<?php echo $s_id; ?>">
                                    <input type="hidden" name="old_class_id" value="<?php echo $c_id; ?>">
                                    
                                    <div class="form-group">
                                        <label class="form-label">Nama Lengkap</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($s['name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Nomor Induk Siswa Nasional (NISN)</label>
                                        <input type="text" name="email" class="form-control" value="<?php echo htmlspecialchars($s['nisn']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Sandi Otentikasi Baru (Kosongkan bila sama)</label>
                                        <input type="text" name="password" class="form-control" placeholder="Acak secara manual jika diisi">
                                        <small style="color:var(--warning);">Hanya isi ini untuk me-reset sandi siswa yang lupa sandinya.</small>
                                    </div>
                                    <div class="form-group" style="margin-top:1rem;">
                                        <label class="form-label">Rotasi Rombel Domisili</label>
                                        <select name="new_class_id" class="form-control" required style="background:var(--surface);">
                                            <?php 
                                            // Reset pointer and re-iterate for dropdown
                                            if($classes) {
                                                $classes->data_seek(0);
                                                while($cl = $classes->fetch_assoc()): 
                                            ?>
                                                <option value="<?php echo $cl['id']; ?>" <?php if($cl['id'] == $c_id) echo 'selected'; ?>><?php echo htmlspecialchars($cl['name']); ?></option>
                                            <?php endwhile; } ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:2rem;"><i class="uil uil-save"></i> Perbarui Arsip</button>
                                </form>
                            </div>
                        </div>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:4rem; color:var(--text-muted);"><i class="uil uil-users-alt" style="font-size:3rem;"></i><br>Tidak ada satupun arsip murid pada saringan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        
        <!-- Pagination Controls -->
        <?php if($total_pages > 1): ?>
            <div style="display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-top:2rem;">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&rombel=<?php echo $filter_class_id; ?>" class="btn <?php echo ($i == $page) ? 'btn-primary' : 'btn-secondary'; ?>" style="padding:0.4rem 0.8rem;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; margin-top:1rem;">Menampilkan Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal ADD STUDENT -->
<div id="modalAddStudent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="uil uil-user-plus"></i> Pendaftaran Akun Siswa</h3>
            <button onclick="document.getElementById('modalAddStudent').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>
        
        <?php if($classes && $classes->num_rows > 0): ?>
            <form action="../actions/add_student_to_class.php" method="POST">
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(BASE_URL . '/pages/manage_students.php'); ?>">
                
                <div class="form-group">
                    <label class="form-label">Tujuan Rombongan Belajar</label>
                    <select name="class_id" class="form-control" required style="background:var(--surface);">
                        <option value="">-- Letakkan di Kelas... --</option>
                        <?php 
                        $classes->data_seek(0);
                        while($cl = $classes->fetch_assoc()): 
                            $sel_cl = ($filter_class_id == $cl['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $cl['id']; ?>" <?php echo $sel_cl; ?>><?php echo htmlspecialchars($cl['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Siswa Baru</label>
                    <input type="text" name="name" class="form-control" placeholder="Sesuai Akta Kelahiran" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nomor Induk / Username</label>
                    <input type="text" name="username" class="form-control" placeholder="NIP / NISN" required>
                </div>
                
                <p style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.3); padding:1rem; border-radius:var(--radius-sm); color:var(--text-main); font-size:0.85rem; margin-bottom:1.5rem;">
                    <i class="uil uil-lock-access" style="color:var(--warning);"></i> <b>Kriptografi Otomatis:</b> Sandi sepanjang 6 digit acak akan dibuatkan oleh sistem dan diletakkan pada tabel setelah berhasil registrasi.
                </p>
                
                <button type="submit" class="btn btn-primary btn-block" style="background:var(--warning); color:#fff; border:none;"><i class="uil uil-arrow-right"></i> Proses Pembuatan Akun</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning"><i class="uil uil-exclamation-triangle"></i> Anda harus memiliki minimal satu <b>Rombel</b> di "Manajemen Rombel" sebelum meregistrasi Siswa!</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal IMPORT EXCEL -->
<div id="modalImportExcel" class="modal-overlay">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="uil uil-file-upload"></i> Impor Siswa Massal (Excel)</h3>
            <button onclick="document.getElementById('modalImportExcel').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>
        
        <div style="background:rgba(16, 185, 129, 0.1); border:1px solid rgba(16, 185, 129, 0.3); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem; text-align:center;">
            <i class="uil uil-file-download-alt" style="font-size:3rem; color:var(--secondary); display:block; margin-bottom:0.5rem;"></i>
            <h4 style="color:var(--secondary);">1. Unduh Template Dasar (.xlsx)</h4>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">Unduh format lajur standar BitLearn, isikan data murid dari sekolah Anda secara mandiri di perangkat, lalu Simpan (*Save*).</p>
            <button onclick="downloadStudentTemplate()" class="btn btn-secondary btn-sm" style="border-color:var(--secondary); color:var(--secondary);"><i class="uil uil-arrow-to-bottom"></i> Tarik Format Excel Template</button>
        </div>
        
        <div style="padding:1.5rem; border:2px dashed var(--border); border-radius:var(--radius-md); text-align:center; position:relative; overflow:hidden;" id="dropzoneExcel">
            <h4 style="margin-bottom:0.5rem;">2. Unggah File yang Sudah Terisi</h4>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Ketuk atau jatuhkan berkas **.xlsx** pendaftaran Anda kemari.</p>
            <input type="file" id="excelStudentUpload" accept=".xlsx, .xls" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;" onchange="handleExcelUpload(this)">
            <div id="excelLoadingState" style="display:none; color:var(--warning);"><i class="uil uil-spinner-alt" style="animation: spin 1s linear infinite; display:inline-block;"></i> Mesin memproses pendaftaran...</div>
        </div>
    </div>
</div>

<style>
@keyframes spin { 100% { transform:rotate(360deg); } }
</style>

<!-- Load Library Online (CDN SheetJS) Sesuai Permintaan! -->
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
}

// Fitur Merender Secara Mulus (.xlsx) Template!
function downloadStudentTemplate() {
    // 1. Definisikan Header Data
    const headers = [
        "Nama_Lengkap",
        "NISN_Username",
        "Password_Opsional",
        "Nama_Rombel_Tujuan"
    ];
    
    // 2. Beri Satu Baris Contoh (Dummy)
    const contohRow = [
        "Andi Darmawan", 
        "102930129", 
        "", 
        "Kelas 7A"
    ];
    
    const ws_data = [headers, contohRow];
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    
    // Set lebar kolom yang indah
    const wscols = [ {wpx: 150}, {wpx: 120}, {wpx: 120}, {wpx: 150} ];
    ws['!cols'] = wscols;
    
    XLSX.utils.book_append_sheet(wb, ws, "Daftar_Pendaftaran_Muda");
    
    // 3. Picu Trigger Download
    XLSX.writeFile(wb, "Template_Pendaftaran_Siswa_BitLearn.xlsx");
}

// Fitur Membaca Formats (.xlsx) Menggunakan Mesin Browser lalu Mengerahkannya ke Peladen
function handleExcelUpload(fileInput) {
    const file = fileInput.files[0];
    if(!file) return;
    
    // Tampilkan Indikator Processing
    document.getElementById('excelLoadingState').style.display = 'block';
    fileInput.style.display = 'none';

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        
        // Ambil Sheet Pertama Saja
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        
        // Konversi Raw XML Ke Javascript Object / JSON
        const raw_json = XLSX.utils.sheet_to_json(worksheet, {raw: true, defval: ""});
        
        if(raw_json.length === 0) {
            Swal.fire({icon: 'error', title: 'Kosong', text: 'Tabel Excel tersebut tidak punya data!'});
            resetExcelUploader(fileInput);
            return;
        }

        // Tembak Data JSON Massive ini Pake AJAX POST ke peladen PHP 
        fetch('../actions/import_students_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ students: raw_json })
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Pendaftaran Bulk Sukses!',
                    html: `
                        <p style="color:var(--text-main); margin-bottom:1rem;">${data.message}</p>
                        <div style="font-size:0.9rem; text-align:left; background:rgba(0,0,0,0.2); padding:1rem; border-radius:5px;">
                            <b style="color:var(--secondary)">✔ Siswa Terarsip:</b> ${data.details.success} data<br>
                            <b style="color:var(--danger)">✘ NISN Duplikat/Gagal (Terlewati):</b> ${data.details.skipped_username_duplicate} data<br>
                            <b style="color:var(--warning)">✘ Rombel Tidak Valid:</b> ${data.details.skipped_rombel_not_found} data
                        </div>
                    `,
                    background: 'var(--surface)', color: '#fff'
                }).then(() => { window.location.reload(); });
            } else {
                Swal.fire({icon: 'error', title: 'Terjadi Masalah', text: data.message});
            }
        })
        .catch(error => {
            Swal.fire({icon: 'error', title: 'Server Error', text: 'Gagal menghubungi Endpoint Peladen!'});
            console.error('Error:', error);
        })
        .finally(() => {
            resetExcelUploader(fileInput);
        });
    };
    reader.readAsArrayBuffer(file);
}

function resetExcelUploader(fileInput) {
    document.getElementById('excelLoadingState').style.display = 'none';
    fileInput.style.display = 'block';
    fileInput.value = ''; // Reset Form Valuenya biar bisa nyedot file yg sama 2x kalau error
}
</script>

<?php require_once '../components/footer.php'; ?>
