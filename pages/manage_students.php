<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = (int)$_SESSION['user_id'];
$classes = $conn->query("SELECT id, name FROM classes WHERE teacher_id = $teacher_id ORDER BY name ASC");

// Pagination, search & filter
$allowed_limits = [10, 25, 50, 100];
$limit = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowed_limits, true) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$filter_class_id = isset($_GET['rombel']) && is_numeric($_GET['rombel']) ? (int)$_GET['rombel'] : 0;
$search_q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where = "WHERE c.teacher_id = $teacher_id";
if ($filter_class_id > 0) {
    $where .= " AND c.id = $filter_class_id";
}
if ($search_q !== '') {
    $safe_q = $conn->real_escape_string($search_q);
    $where .= " AND (u.username LIKE '%$safe_q%' OR u.name LIKE '%$safe_q%')";
}

$count_query = "
    SELECT COUNT(u.id) as total
    FROM users u
    JOIN class_students cs ON u.id = cs.student_id
    JOIN classes c ON cs.class_id = c.id
    $where
";
$total_res = $conn->query($count_query);
$total_rows = $total_res ? (int)$total_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, (int)ceil($total_rows / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

$query = "
    SELECT u.id as student_id, u.name, u.username as nisn, u.temp_password, c.id as class_id, c.name as class_name
    FROM users u
    JOIN class_students cs ON u.id = cs.student_id
    JOIN classes c ON cs.class_id = c.id
    $where
    ORDER BY c.name ASC, u.name ASC
    LIMIT $limit OFFSET $offset
";
$students = $conn->query($query);

function students_qs(array $overrides = []): string {
    $params = [
        'rombel' => $overrides['rombel'] ?? ($_GET['rombel'] ?? 0),
        'q' => $overrides['q'] ?? ($_GET['q'] ?? ''),
        'per_page' => $overrides['per_page'] ?? ($_GET['per_page'] ?? 10),
        'page' => $overrides['page'] ?? ($_GET['page'] ?? 1),
    ];
    if ((int)$params['rombel'] <= 0) unset($params['rombel']);
    if (trim((string)$params['q']) === '') unset($params['q']);
    if ((int)$params['per_page'] === 10) unset($params['per_page']);
    if ((int)$params['page'] <= 1) unset($params['page']);
    return $params ? ('?' . http_build_query($params)) : '?';
}

$page_title = 'Manajemen Siswa';
require_once '../components/header.php';
?>
<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-users-alt"></i> Direktori Siswa</h2>
            <p class="text-muted" style="margin:0;">Awasi, perbarui, dan kontrol akun berdasar Rombel aktif.</p>
        </div>
        <div class="page-actions">
            <button type="button" onclick="document.getElementById('modalImportExcel').classList.add('active')" class="btn btn-secondary" style="border-color:var(--secondary); color:var(--secondary); background:rgba(13, 148, 136, 0.1);">
                <i class="uil uil-file-upload"></i> Impor Excel
            </button>
            <button type="button" onclick="document.getElementById('modalAddStudent').classList.add('active')" class="btn btn-primary">
                <i class="uil uil-user-plus"></i> Registrasi Siswa
            </button>
        </div>
    </div>

    <!-- Filter / Search Bar -->
    <div class="glass-card students-toolbar">
        <form action="" method="GET" class="students-toolbar-form">
            <div class="students-toolbar-field">
                <label class="form-label" for="filterRombel"><i class="uil uil-filter"></i> Rombel</label>
                <select id="filterRombel" name="rombel" class="form-control" onchange="this.form.submit()">
                    <option value="0">Semua Rombel</option>
                    <?php
                    if ($classes) {
                        $classes->data_seek(0);
                        while ($cl = $classes->fetch_assoc()) {
                            $sel = ($filter_class_id == $cl['id']) ? 'selected' : '';
                            echo "<option value=\"{$cl['id']}\" $sel>" . htmlspecialchars($cl['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="students-toolbar-field students-toolbar-search">
                <label class="form-label" for="filterSearch"><i class="uil uil-search"></i> Cari NISN / Nama</label>
                <div class="students-search-row">
                    <input id="filterSearch" type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Ketik NISN atau nama siswa..." autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm" title="Cari"><i class="uil uil-search"></i></button>
                    <?php if ($search_q !== '' || $filter_class_id > 0): ?>
                        <a href="manage_students.php" class="btn btn-secondary btn-sm" title="Reset"><i class="uil uil-times"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="students-toolbar-field">
                <label class="form-label" for="filterPerPage"><i class="uil uil-list-ul"></i> Baris / halaman</label>
                <select id="filterPerPage" name="per_page" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($allowed_limits as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $limit === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> baris</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="page" value="1">
        </form>
        <div class="students-toolbar-meta">
            Menampilkan <strong><?php echo $total_rows > 0 ? ($offset + 1) : 0; ?>–<?php echo min($offset + $limit, $total_rows); ?></strong>
            dari <strong><?php echo $total_rows; ?></strong> siswa
            <?php if ($search_q !== ''): ?>
                untuk pencarian “<?php echo htmlspecialchars($search_q); ?>”
            <?php endif; ?>
        </div>
    </div>

    <div class="glass-card" style="padding:1rem;">
        <div class="table-responsive">
        <table class="table" style="min-width:880px;">
            <thead>
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
                        $plain_pass = !empty($s['temp_password']) ? (string)$s['temp_password'] : '';
                        $row_key = $s_id . '_' . $c_id;
                    ?>
                        <tr>
                            <td style="text-align:center; color:var(--text-muted);"><?php echo $no++; ?></td>
                            <td>
                                <div class="cell-with-actions">
                                    <b class="nisn-text"><?php echo htmlspecialchars($s['nisn']); ?></b>
                                    <button type="button" class="icon-btn" title="Salin NISN" data-copy="<?php echo htmlspecialchars($s['nisn'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="uil uil-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td style="color:var(--text-main); font-weight:600;"><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><span class="badge-rombel"><?php echo htmlspecialchars($s['class_name']); ?></span></td>
                            <td>
                                <div class="cell-with-actions">
                                    <?php if ($plain_pass !== ''): ?>
                                        <code class="pwd-mask" data-pwd="<?php echo htmlspecialchars($plain_pass, ENT_QUOTES, 'UTF-8'); ?>">••••••</code>
                                        <button type="button" class="icon-btn pwd-toggle" title="Tampilkan sandi" aria-pressed="false">
                                            <i class="uil uil-eye-slash"></i>
                                        </button>
                                        <button type="button" class="icon-btn" title="Salin sandi" data-copy="<?php echo htmlspecialchars($plain_pass, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="uil uil-copy"></i>
                                        </button>
                                    <?php else: ?>
                                        <code class="pwd-mask">••••••</code>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="text-align:right;">
                                <div class="list-row-actions" style="justify-content:flex-end;">
                                <button type="button" onclick="document.getElementById('modalEditStudent<?php echo $row_key; ?>').classList.add('active')" class="btn btn-secondary btn-sm" title="Edit Siswa"><i class="uil uil-pen"></i> Edit</button>

                                <form action="../actions/delete_student.php" method="POST" data-confirm="PERINGATAN! Ini akan menghapus akun Siswa secara PERMANEN dari seluruh sistem, bukan hanya mengeluarkannya dari rombel. Lanjutkan?" style="margin:0;">
                                    <input type="hidden" name="student_id" value="<?php echo $s_id; ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $c_id; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus Permanen Akun"><i class="uil uil-trash-alt"></i> Hapus</button>
                                </form>
                                </div>
                            </td>
                        </tr>

                        <div id="modalEditStudent<?php echo $row_key; ?>" class="modal-overlay">
                            <div class="modal-box">
                                <div class="modal-header">
                                    <h3><i class="uil uil-user-check"></i> Modifikasi Data Siswa</h3>
                                    <button type="button" onclick="document.getElementById('modalEditStudent<?php echo $row_key; ?>').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
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
                                        <select name="new_class_id" class="form-control" required>
                                            <?php
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
                    <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);"><i class="uil uil-users-alt" style="font-size:2.5rem; display:block; margin-bottom:0.5rem;"></i>Tidak ada siswa yang cocok dengan filter/pencarian ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_rows > 0): ?>
            <div class="pagination-bar">
                <div class="pagination-info">
                    Halaman <strong><?php echo $page; ?></strong> dari <strong><?php echo $total_pages; ?></strong>
                </div>
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination-nav" aria-label="Navigasi halaman">
                        <?php
                        $window = 2;
                        $start = max(1, $page - $window);
                        $end = min($total_pages, $page + $window);
                        ?>
                        <a class="page-btn <?php echo $page <= 1 ? 'is-disabled' : ''; ?>"
                           href="<?php echo $page <= 1 ? '#' : htmlspecialchars(students_qs(['page' => $page - 1])); ?>"
                           <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                            <i class="uil uil-angle-left"></i>
                        </a>

                        <?php if ($start > 1): ?>
                            <a class="page-btn" href="<?php echo htmlspecialchars(students_qs(['page' => 1])); ?>">1</a>
                            <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a class="page-btn <?php echo $i === $page ? 'is-active' : ''; ?>"
                               href="<?php echo htmlspecialchars(students_qs(['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                            <a class="page-btn" href="<?php echo htmlspecialchars(students_qs(['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <a class="page-btn <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>"
                           href="<?php echo $page >= $total_pages ? '#' : htmlspecialchars(students_qs(['page' => $page + 1])); ?>"
                           <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                            <i class="uil uil-angle-right"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalAddStudent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="uil uil-user-plus"></i> Pendaftaran Akun Siswa</h3>
            <button type="button" onclick="document.getElementById('modalAddStudent').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>

        <?php if($classes && $classes->num_rows > 0): ?>
            <form action="../actions/add_student_to_class.php" method="POST">
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(BASE_URL . '/pages/manage_students.php'); ?>">

                <div class="form-group">
                    <label class="form-label">Tujuan Rombongan Belajar</label>
                    <select name="class_id" class="form-control" required>
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

                <button type="submit" class="btn btn-primary btn-block"><i class="uil uil-arrow-right"></i> Proses Pembuatan Akun</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning"><i class="uil uil-exclamation-triangle"></i> Anda harus memiliki minimal satu <b>Rombel</b> di "Manajemen Rombel" sebelum meregistrasi Siswa!</div>
        <?php endif; ?>
    </div>
</div>

<div id="modalImportExcel" class="modal-overlay">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <h3><i class="uil uil-file-upload"></i> Impor Siswa Massal (Excel)</h3>
            <button type="button" onclick="document.getElementById('modalImportExcel').classList.remove('active')" class="btn-close"><i class="uil uil-times"></i></button>
        </div>

        <div style="background:rgba(13, 148, 136, 0.1); border:1px solid rgba(13, 148, 136, 0.3); padding:1rem; border-radius:var(--radius-sm); margin-bottom:1.5rem; text-align:center;">
            <i class="uil uil-file-download-alt" style="font-size:3rem; color:var(--secondary); display:block; margin-bottom:0.5rem;"></i>
            <h4 style="color:var(--secondary);">1. Unduh Template Dasar (.xlsx)</h4>
            <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1rem;">Unduh format lajur standar BitLearn, isikan data murid dari sekolah Anda secara mandiri di perangkat, lalu Simpan (*Save*).</p>
            <button type="button" onclick="downloadStudentTemplate()" class="btn btn-secondary btn-sm" style="border-color:var(--secondary); color:var(--secondary);"><i class="uil uil-arrow-to-bottom"></i> Tarik Format Excel Template</button>
        </div>

        <div style="padding:1.5rem; border:2px dashed var(--border); border-radius:var(--radius); text-align:center; position:relative; overflow:hidden;" id="dropzoneExcel">
            <h4 style="margin-bottom:0.5rem;">2. Unggah File yang Sudah Terisi</h4>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Ketuk atau jatuhkan berkas **.xlsx** pendaftaran Anda kemari.</p>
            <input type="file" id="excelStudentUpload" accept=".xlsx, .xls" style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;" onchange="handleExcelUpload(this)">
            <div id="excelLoadingState" style="display:none; color:var(--warning);"><i class="uil uil-spinner-alt" style="animation: spin 1s linear infinite; display:inline-block;"></i> Mesin memproses pendaftaran...</div>
        </div>
    </div>
</div>

<style>
@keyframes spin { 100% { transform:rotate(360deg); } }

.students-toolbar {
    padding: 1.1rem 1.25rem;
    margin-bottom: 1.25rem;
}
.students-toolbar-form {
    display: grid;
    grid-template-columns: minmax(160px, 220px) minmax(220px, 1fr) minmax(140px, 170px);
    gap: 0.85rem 1rem;
    align-items: end;
}
.students-toolbar-field .form-label {
    margin-bottom: 0.35rem;
}
.students-search-row {
    display: flex;
    gap: 0.4rem;
    align-items: stretch;
}
.students-search-row .form-control { flex: 1; min-width: 0; }
.students-toolbar-meta {
    margin-top: 0.85rem;
    color: var(--text-muted);
    font-size: 0.85rem;
}
.badge-rombel {
    background: rgba(13, 148, 136, 0.12);
    color: var(--secondary);
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}
.cell-with-actions {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 100%;
}
.nisn-text {
    color: var(--primary);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.92rem;
}
.pwd-mask {
    background: #1C1917;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    color: #FDBA74;
    font-size: 0.85rem;
    letter-spacing: 0.04em;
    min-width: 4.5rem;
    display: inline-block;
    text-align: center;
}
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text-muted);
    cursor: pointer;
    transition: 0.15s ease;
    padding: 0;
    flex: 0 0 auto;
}
.icon-btn:hover {
    color: var(--primary-hover);
    border-color: #FDBA74;
    background: var(--primary-soft);
}
.icon-btn.is-copied {
    color: var(--secondary);
    border-color: rgba(13,148,136,0.35);
    background: rgba(13,148,136,0.1);
}
.pagination-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
    max-width: 100%;
    overflow: hidden;
}
.pagination-info {
    color: var(--text-muted);
    font-size: 0.88rem;
}
.pagination-nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    max-width: 100%;
}
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.15rem;
    height: 2.15rem;
    padding: 0 0.55rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text-main);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.88rem;
}
.page-btn:hover { background: var(--primary-soft); border-color: #FDBA74; color: var(--primary-hover); }
.page-btn.is-active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.page-btn.is-disabled {
    opacity: 0.4;
    pointer-events: none;
}
.page-ellipsis {
    color: var(--text-muted);
    padding: 0 0.2rem;
    font-weight: 700;
}
.table thead {
    background: var(--primary-soft);
}
.table thead th {
    color: var(--text-main);
    border-bottom: 1px solid var(--border);
}
@media (max-width: 900px) {
    .students-toolbar-form {
        grid-template-columns: 1fr;
    }
    .pagination-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .pagination-nav { justify-content: center; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
<script>
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.classList.remove('active');
    }
};

function copyText(value, btn) {
    if (!value) return;
    const done = () => {
        if (!btn) return;
        btn.classList.add('is-copied');
        const icon = btn.querySelector('i');
        const prev = icon ? icon.className : '';
        if (icon) icon.className = 'uil uil-check';
        setTimeout(() => {
            btn.classList.remove('is-copied');
            if (icon) icon.className = prev;
        }, 1200);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done).catch(() => fallbackCopy(value, done));
    } else {
        fallbackCopy(value, done);
    }
}

function fallbackCopy(value, done) {
    const ta = document.createElement('textarea');
    ta.value = value;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
}

document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', function () {
        copyText(this.getAttribute('data-copy') || '', this);
    });
});

document.querySelectorAll('.pwd-toggle').forEach((btn) => {
    btn.addEventListener('click', function () {
        const code = this.parentElement.querySelector('.pwd-mask');
        if (!code) return;
        const plain = code.getAttribute('data-pwd') || '';
        const shown = this.getAttribute('aria-pressed') === 'true';
        if (shown) {
            code.textContent = '••••••';
            this.setAttribute('aria-pressed', 'false');
            this.title = 'Tampilkan sandi';
            const icon = this.querySelector('i');
            if (icon) icon.className = 'uil uil-eye-slash';
        } else {
            code.textContent = plain;
            this.setAttribute('aria-pressed', 'true');
            this.title = 'Sembunyikan sandi';
            const icon = this.querySelector('i');
            if (icon) icon.className = 'uil uil-eye';
        }
    });
});

function downloadStudentTemplate() {
    const headers = ["Nama_Lengkap", "NISN_Username", "Password_Opsional", "Nama_Rombel_Tujuan"];
    const contohRow = ["Andi Darmawan", "102930129", "", "Kelas 7A"];
    const ws_data = [headers, contohRow];
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    ws['!cols'] = [{wpx: 150}, {wpx: 120}, {wpx: 120}, {wpx: 150}];
    XLSX.utils.book_append_sheet(wb, ws, "Daftar_Pendaftaran_Muda");
    XLSX.writeFile(wb, "Template_Pendaftaran_Siswa_BitLearn.xlsx");
}

function handleExcelUpload(fileInput) {
    const file = fileInput.files[0];
    if(!file) return;

    document.getElementById('excelLoadingState').style.display = 'block';
    fileInput.style.display = 'none';

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const raw_json = XLSX.utils.sheet_to_json(worksheet, {raw: true, defval: ""});

        if(raw_json.length === 0) {
            Swal.fire({icon: 'error', title: 'Kosong', text: 'Tabel Excel tersebut tidak punya data!'});
            resetExcelUploader(fileInput);
            return;
        }

        fetch('../actions/import_students_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
                        <div style="font-size:0.9rem; text-align:left; background:var(--primary-soft); padding:1rem; border-radius:5px;">
                            <b style="color:var(--secondary)">✔ Siswa Terarsip:</b> ${data.details.success} data<br>
                            <b style="color:var(--danger)">✘ NISN Duplikat/Gagal (Terlewati):</b> ${data.details.skipped_username_duplicate} data<br>
                            <b style="color:var(--warning)">✘ Rombel Tidak Valid:</b> ${data.details.skipped_rombel_not_found} data
                        </div>
                    `,
                    background: 'var(--surface)',
                    color: 'var(--text-main)'
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
    fileInput.value = '';
}
</script>

<?php require_once '../components/footer.php'; ?>
