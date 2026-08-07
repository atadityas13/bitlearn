<?php
require_once '../core/config.php';
require_once '../core/LessonSettings.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$teacher_id = $_SESSION['user_id'];
LessonSettings::ensureDwellColumn($conn);

// Get Lesson & Verify
$les_query = $conn->query("
    SELECT l.*, m.course_id 
    FROM lessons l 
    JOIN modules m ON l.module_id = m.id 
    JOIN courses c ON m.course_id = c.id 
    WHERE l.id = $lesson_id AND c.teacher_id = $teacher_id
");
if (!$les_query || $les_query->num_rows === 0) {
    header("Location: manage_courses.php"); exit;
}
$lesson = $les_query->fetch_assoc();
$course_id = $lesson['course_id'];

// Get all existing lessons in this course to populate prerequisite dropdown (excluding itself)
$all_course_lessons = [];
$mod_query = $conn->query("SELECT id FROM modules WHERE course_id = $course_id");
if($mod_query && $mod_query->num_rows > 0) {
    $m_ids = [];
    while($m = $mod_query->fetch_assoc()) $m_ids[] = $m['id'];
    $m_list = implode(',', $m_ids);
    
    $c_query = $conn->query("SELECT id, title FROM lessons WHERE module_id IN ($m_list) AND id != $lesson_id ORDER BY id ASC");
    if($c_query) {
        while($l = $c_query->fetch_assoc()) $all_course_lessons[] = $l;
    }
}

$page_title = 'Edit Materi Pelajaran';
require_once '../components/header.php';

// Prepare slideshow array for textarea if applicable
$slides_text = "";
?>

<div class="container main-content" style="padding-top:2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h2><i class="uil uil-pen"></i> Edit Bab / Materi</h2>
        <a href="course_view.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
            <i class="uil uil-arrow-left"></i> Batal / Kembali
        </a>
    </div>

    <div class="glass-card" style="max-width:800px; margin:0 auto;">
        <form action="../actions/update_lesson.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
            <input type="hidden" name="existing_content_type" value="<?php echo $lesson['content_type']; ?>">
            
            <div class="form-group">
                <label class="form-label">Judul Pelajaran</label>
                <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($lesson['title']); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Teks Pemandu / Penjelasan</label>
                <textarea name="description" class="form-control" rows="5"><?php echo htmlspecialchars($lesson['description']); ?></textarea>
            </div>

            <div style="background:var(--surface); padding:1.5rem; border-radius:var(--radius-sm); margin-bottom:1.5rem; border:1px solid var(--border);">
                <h4 style="margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;"><i class="uil uil-paperclip"></i> Lampiran Media & Evaluasi</h4>
                
                <div class="form-group">
                    <label class="form-label">Tipe Konten Pembelajaran (Tipe Dasar Saat Ini)</label>
                    <select name="content_type" id="content_type" class="form-control" onchange="toggleMediaFields()">
                        <option value="video_embed" <?php echo ($lesson['content_type']=='video_embed')?'selected':''; ?>>Sematan Video (YouTube / G-Drive)</option>
                        <option value="slideshow" <?php echo ($lesson['content_type']=='slideshow')?'selected':''; ?>>Sematan Presentasi (Google Slides)</option>
                        <option value="pdf_embed" <?php echo ($lesson['content_type']=='pdf_embed')?'selected':''; ?>>Sematan Dokumen PDF (Google Drive)</option>
                        <option value="quiz" <?php echo ($lesson['content_type']=='quiz')?'selected':''; ?>>Kuis Evaluasi (Pretest / Posttest)</option>
                    </select>
                </div>

                <div id="video_field" class="form-group" style="<?php echo in_array($lesson['content_type'], ['video_embed','slideshow','pdf_embed'])?'':'display:none;'; ?>">
                    <label id="url_label" class="form-label">
                        <?php 
                            if($lesson['content_type']=='slideshow') echo 'Tautan Embed Google Slides';
                            elseif($lesson['content_type']=='pdf_embed') echo 'Tautan Embed PDF (Google Drive)';
                            else echo 'Tautan Video YouTube (Link Biasa / Iframe)';
                        ?>
                    </label>
                    <input type="text" id="url_embed_input" name="url_embed" class="form-control" 
                        placeholder="<?php 
                            if($lesson['content_type']=='slideshow') echo 'https://docs.google.com/presentation/d/.../embed?...';
                            elseif($lesson['content_type']=='pdf_embed') echo 'https://drive.google.com/file/d/.../preview';
                            else echo 'https://www.youtube.com/watch?v=...';
                        ?>"
                        value="<?php echo in_array($lesson['content_type'], ['video_embed','slideshow','pdf_embed']) ? htmlspecialchars($lesson['url_embed']) : ''; ?>">
                    <small id="url_hint" style="color:var(--text-muted); display:block; margin-top:0.3rem;">
                        <?php if($lesson['content_type']=='slideshow'): ?>
                            <b>Tips:</b> Anda cukup menyalin link "Bagikan" biasa dari Google Slides. Sistem akan otomatis mengubahnya.
                        <?php elseif($lesson['content_type']=='pdf_embed'): ?>
                            <b>Tips:</b> Cukup salin link "Bagikan" dari Google Drive. Sistem akan otomatis mengubahnya.
                        <?php else: ?>
                            Sistem kami akan otomatis mengonversi tautan YouTube agar bisa diputar di aplikasi.
                        <?php endif; ?>
                    </small>
                </div>
                <div id="doc_field" class="form-group" style="<?php echo ($lesson['content_type']=='document_upload')?'':'display:none;'; ?>">
                    <label class="form-label">Pilih File Dokumen Baru (PDF, DOCX, JPG, PNG)</label>
                    <?php if($lesson['content_type'] == 'document_upload' && !empty($lesson['document_path'])): ?>
                        <div style="margin-bottom:0.8rem; font-size:0.9rem; color:var(--secondary);"><i class="uil uil-file-check-alt"></i> File tersimpan: <?php echo htmlspecialchars($lesson['document_path']); ?></div>
                    <?php endif; ?>
                    <input type="file" name="document_file" class="form-control" style="background:transparent; border:none; padding-left:0;">
                    <small style="color:var(--text-muted);">Biarkan kosong jika tidak ingin merubah dokumen lama.</small>
                </div>

                <div id="quiz_field" class="form-group" style="<?php echo ($lesson['content_type']=='quiz')?'':'display:none;'; ?> color:var(--warning);">
                    <label class="form-label"><i class="uil uil-processor"></i> Mode Kuis</label>
                    <p style="font-size:0.9rem;">Catatan: Mengubah tipe materi ke Kuis akan meniadakan Dokumen Media yang sudah diinput. Edit butir soal bisa dilakukan dari panel course utama.</p>
                </div>

                <?php
                $dwellVal = isset($lesson['dwell_minutes']) ? (int)$lesson['dwell_minutes'] : 1;
                $showDwell = in_array($lesson['content_type'], ['pdf_embed', 'slideshow', 'document_upload'], true);
                ?>
                <div id="dwell_field" class="form-group" style="<?php echo $showDwell ? '' : 'display:none;'; ?>">
                    <label class="form-label"><i class="uil uil-clock"></i> Durasi baca minimal (menit)</label>
                    <input type="number" name="dwell_minutes" id="dwell_minutes" class="form-control"
                        min="0" max="180" step="1" value="<?php echo $dwellVal; ?>"
                        style="max-width:140px;">
                    <small style="color:var(--text-muted); display:block; margin-top:0.35rem;">
                        Waktu yang harus dilalui siswa sebelum tombol <b>Selesai</b> aktif (PDF / presentasi).
                        Isi <b>0</b> jika langsung bisa selesai.
                    </small>
                </div>
            </div>

            <div class="form-group" style="margin-top:2rem;">
                <label class="form-label"><i class="uil uil-lock"></i> Kunci Prasyarat (Logika Sekuensial)</label>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:0.8rem;">Pilih satu materi yang WAJIB diselesaikan/lulus sebelum siswa bisa membuka materi ini.</p>
                <select name="is_prerequisite_of" class="form-control" style="background:var(--surface);">
                    <option value="">-- Bebas Akses (Tidak Ada Syarat Materi Buka Terdahulu) --</option>
                    <?php foreach($all_course_lessons as $p_les): 
                        $sel = ($lesson['is_prerequisite_of'] == $p_les['id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $p_les['id']; ?>" <?php echo $sel; ?>>Wajib Terbuka/Lulus: <?php echo htmlspecialchars($p_les['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-top:2rem;">
                <label class="checkbox-container" style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_published" value="1" <?php echo ($lesson['is_published']) ? 'checked' : ''; ?> style="width:20px; height:20px;">
                    <strong>Publikasikan Materi ke Siswa (Aktifkan)</strong>
                </label>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-top:0.4rem;">Jika tidak dicentang, materi akan disimpan sebagai Draf dan tidak terlihat oleh siswa.</p>
            </div>

            <div style="margin-top:2rem;">
                <button type="submit" class="btn btn-primary btn-block" style="padding:1rem;"><i class="uil uil-save"></i> Simpan Perubahan Materi</button>
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

    // hide all
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
    } else if (type === 'document_upload') {
        document.getElementById('doc_field').style.display = 'block';
        dwell.style.display = 'block';
    } else if (type === 'quiz') {
        document.getElementById('quiz_field').style.display = 'block';
    }
}
</script>

<?php require_once '../components/footer.php'; ?>
