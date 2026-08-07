<?php
require_once '../core/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../index.php");
    exit;
}

$module_id = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

// Verify teacher owns the course
$teacher_id = $_SESSION['user_id'];
$check = $conn->query("SELECT id FROM courses WHERE id = $course_id AND teacher_id = $teacher_id");
if (!$check || $check->num_rows === 0) {
    header("Location: manage_courses.php");
    exit;
}

// Get all existing lessons in this course to populate prerequisite dropdown
$all_course_lessons = [];
$mod_query = $conn->query("SELECT id FROM modules WHERE course_id = $course_id");
if ($mod_query && $mod_query->num_rows > 0) {
    $m_ids = [];
    while ($m = $mod_query->fetch_assoc())
        $m_ids[] = $m['id'];
    $m_list = implode(',', $m_ids);

    $les_query = $conn->query("SELECT id, title FROM lessons WHERE module_id IN ($m_list) ORDER BY id ASC");
    if ($les_query) {
        while ($l = $les_query->fetch_assoc())
            $all_course_lessons[] = $l;
    }
}

$page_title = 'Tambah Materi Pelajaran';
require_once '../components/header.php';
?>

<div class="container main-content" style="padding-top:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h2><i class="uil uil-plus-circle"></i> Tambah Bab / Materi Baru</h2>
        <a href="course_view.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
            <i class="uil uil-arrow-left"></i> Batal / Kembali
        </a>
    </div>

    <div class="glass-card" style="max-width:800px; margin:0 auto;">
        <form action="../actions/save_lesson.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">

            <div class="form-group">
                <label class="form-label">Judul Pelajaran</label>
                <input type="text" name="title" class="form-control" required
                    placeholder="Contoh: Pengantar Kerangka Kerja">
            </div>

            <div class="form-group">
                <label class="form-label">Teks Pemandu / Penjelasan</label>
                <textarea name="description" class="form-control" rows="5"
                    placeholder="Tuliskan teks atau narasi yang perlu dibaca siswa..."></textarea>
            </div>

            <div
                style="background:var(--surface); padding:1.5rem; border-radius:var(--radius-sm); margin-bottom:1.5rem; border:1px solid var(--border);">
                <h4 style="margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;">
                    <i class="uil uil-paperclip"></i> Lampiran Media & Evaluasi</h4>

                <div class="form-group">
                    <label class="form-label">Tipe Konten Pembelajaran</label>
                    <select name="content_type" id="content_type" class="form-control" onchange="toggleMediaFields()">
                        <option value="video_embed">Sematan Video (YouTube / G-Drive)</option>
                        <option value="slideshow">Sematan Presentasi (Google Slides)</option>
                        <option value="pdf_embed">Sematan Dokumen PDF (Google Drive)</option>
                        <option value="quiz">Kuis Evaluasi (Pretest / Posttest)</option>
                    </select>
                </div>

                <div id="video_field" class="form-group">
                    <label id="url_label" class="form-label">Tautan Video YouTube (Link Biasa / Iframe)</label>
                    <input type="text" id="url_embed_input" name="url_embed" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    <small id="url_hint" style="color:var(--text-muted); display:block; margin-top:0.3rem;">Sistem kami akan otomatis mengonversi tautan YouTube agar bisa diputar di aplikasi.</small>
                </div>

                <div id="doc_field" class="form-group" style="display:none;">
                    <label class="form-label">Pilih File Dokumen (PDF, DOCX, JPG, PNG)</label>
                    <input type="file" name="document_file" class="form-control"
                        style="background:transparent; border:none; padding-left:0;">
                </div>


                <div id="quiz_field" class="form-group" style="display:none; color:var(--warning);">
                    <label class="form-label"><i class="uil uil-processor"></i> Pembuat Kuis / Test</label>
                    <p style="font-size:0.9rem;">Anda akan dialihkan ke **halaman Perakit Soal Pilihan Ganda** setelah
                        menekan tombol "Terbitkan" di bawah ini.</p>
                </div>

                <div id="dwell_field" class="form-group" style="display:none;">
                    <label class="form-label"><i class="uil uil-clock"></i> Durasi baca minimal (menit)</label>
                    <input type="number" name="dwell_minutes" id="dwell_minutes" class="form-control"
                        min="0" max="180" step="1" value="1"
                        style="max-width:140px;">
                    <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                        Waktu yang harus dilalui siswa sebelum tombol <b>Selesai</b> aktif (PDF / presentasi).
                        Isi <b>0</b> jika langsung bisa selesai. Default: 1 menit (bukan 5 menit).
                    </small>
                </div>
            </div>

            <div class="form-group" style="margin-top:2rem;">
                <label class="form-label"><i class="uil uil-lock"></i> Kunci Prasyarat (Logika Sekuensial)</label>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.8rem;">Pilih satu materi yang
                    WAJIB diselesaikan/lulus sebelum siswa bisa membuka materi ini.</p>
                <select name="is_prerequisite_of" class="form-control" style="background:var(--surface);">
                    <option value="">-- Bebas Akses (Tidak Ada Syarat Materi Buka Terdahulu) --</option>
                    <?php foreach ($all_course_lessons as $p_les): ?>
                        <option value="<?php echo $p_les['id']; ?>">Wajib Terbuka/Lulus:
                            <?php echo htmlspecialchars($p_les['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-top:2rem;">
                <label class="checkbox-container" style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_published" value="1" checked style="width:20px; height:20px;">
                    <strong>Publikasikan Materi ke Siswa (Aktifkan)</strong>
                </label>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.4rem;">Jika tidak dicentang, materi akan disimpan sebagai Draf dan tidak terlihat oleh siswa.</p>
            </div>

            <div style="margin-top:2rem;">
                <button type="submit" class="btn btn-primary btn-block" style="padding:1rem;">Terbitkan Materi ke Kelas
                    <i class="uil uil-check-circle"></i></button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleMediaFields() {
        var type = document.getElementById('content_type').value;
        var label = document.getElementById('url_label');
        var input = document.getElementById('url_embed_input');
        var hint = document.getElementById('url_hint');
        var dwell = document.getElementById('dwell_field');
        // hide all first
        document.getElementById('video_field').style.display = 'none';
        document.getElementById('doc_field').style.display = 'none';
        document.getElementById('quiz_field').style.display = 'none';
        dwell.style.display = 'none';

        if (type === 'video_embed') {
            document.getElementById('video_field').style.display = 'block';
            label.innerText = 'Tautan Video YouTube (Link Biasa / Iframe)';
            input.placeholder = 'https://www.youtube.com/watch?v=...';
            hint.innerText = 'Sistem kami akan otomatis mengonversi tautan YouTube agar bisa diputar di aplikasi.';
        } else if (type === 'slideshow') {
            document.getElementById('video_field').style.display = 'block';
            dwell.style.display = 'block';
            label.innerText = 'Tautan Embed Google Slides';
            input.placeholder = 'https://docs.google.com/presentation/d/.../edit';
            hint.innerHTML = '<b>Tips:</b> Anda cukup menyalin link "Bagikan" biasa dari Google Slides. Sistem akan otomatis mengubahnya agar bisa tampil di aplikasi.';
        } else if (type === 'pdf_embed') {
            document.getElementById('video_field').style.display = 'block';
            dwell.style.display = 'block';
            label.innerText = 'Tautan Embed PDF (Google Drive)';
            input.placeholder = 'https://drive.google.com/file/d/.../view';
            hint.innerHTML = '<b>Tips:</b> Cukup salin link "Bagikan" dari Google Drive. Sistem akan otomatis mengubahnya agar bisa tampil di aplikasi.';
        } else if (type === 'quiz') {
            document.getElementById('quiz_field').style.display = 'block';
        }
    }
    document.addEventListener('DOMContentLoaded', toggleMediaFields);
</script>

<?php require_once '../components/footer.php'; ?>