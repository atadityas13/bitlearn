<?php
if (strpos($_SERVER['REQUEST_URI'], 'footer.php') !== false)
    die('Direct access not permitted');

$is_teacher = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher');
$hide_nav = (isset($hide_navbar) && $hide_navbar);
?>
<?php if (!$hide_nav && $is_teacher): ?>
    <footer class="footer"
        style="margin-top:auto; padding-top:2rem; padding-bottom:2rem; border-top:1px solid rgba(255,255,255,0.05); text-align:center;">
        <div style="font-size:0.85rem; line-height:1.6; color:var(--text-muted);">
            <p style="margin-bottom:0.4rem; font-weight:500;"><span style="color:var(--text-main);">BitLearn
                    E-Learning</span> &copy; 2026 <b style="color:var(--text-main);">MTsN 11 Majalengka</b></p>
            <p>Dikembangkan secara khusus oleh <b style="color:var(--primary);">Dede Sudirman, S.Pd.</b> (Guru
                Informatika)<br>
        </div>
    </footer>
    </main> <!-- end .app-main -->
    </div> <!-- end .app-wrapper -->
<?php else: ?>
    <?php if (!$hide_nav): ?>
        <footer class="footer"
            style="padding-top:2rem; padding-bottom:2rem; border-top:1px solid rgba(255,255,255,0.05); text-align:center;">
            <div class="container" style="font-size:0.85rem; line-height:1.6; color:var(--text-muted);">
                <p style="margin-bottom:0.4rem; font-weight:500;"><span style="color:var(--text-main);">BitLearn
                        E-Learning</span> &copy; 2026 <b style="color:var(--text-main);">MTsN 11 Majalengka</b></p>
                <p>Dikembangkan secara khusus oleh <b style="color:var(--primary);">Dede Sudirman, S.Pd.</b> (Guru
                    Informatika)<br>
            </div>
        </footer>
    <?php endif; ?>
<?php endif; ?>

<?php if(isset($swal_success) && $swal_success !== ''): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        html: '<p style="margin:0;line-height:1.55;font-size:1rem;"><?php echo htmlspecialchars($swal_success, ENT_QUOTES, 'UTF-8'); ?></p>',
        confirmButtonText: 'Mengerti',
        confirmButtonColor: '#10b981',
        background: 'var(--surface)',
        color: 'var(--text-main)',
        width: 420,
        padding: '1.75rem',
        customClass: {
            popup: 'bitlearn-swal',
            title: 'bitlearn-swal-title',
            confirmButton: 'bitlearn-swal-btn'
        }
    });
</script>
<?php endif; ?>

<?php if(isset($swal_error) && $swal_error !== ''): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        html: '<p style="margin:0;line-height:1.55;font-size:1rem;"><?php echo htmlspecialchars($swal_error, ENT_QUOTES, 'UTF-8'); ?></p>',
        confirmButtonText: 'Tutup',
        confirmButtonColor: '#ef4444',
        background: 'var(--surface)',
        color: 'var(--text-main)',
        width: 420,
        padding: '1.75rem',
        customClass: {
            popup: 'bitlearn-swal',
            title: 'bitlearn-swal-title',
            confirmButton: 'bitlearn-swal-btn'
        }
    });
</script>
<?php endif; ?>

<script>
// Intercept forms requiring confirmation
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = this.getAttribute('data-confirm');
        Swal.fire({
            title: 'Konfirmasi',
            html: '<p style="margin:0;line-height:1.5;">' + msg + '</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--danger)',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            background: 'var(--surface)',
            color: 'var(--text-main)',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
});

// Unenroll: konfirmasi + checkbox cegah masuk kembali
document.querySelectorAll('form[data-confirm-unenroll]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const studentName = this.getAttribute('data-student-name') || 'siswa ini';
        Swal.fire({
            title: 'Keluarkan siswa?',
            html: `
                <p style="margin:0 0 1rem; line-height:1.5; color:var(--text-muted);">
                    Keluarkan <b style="color:var(--text-main);">${studentName}</b> dari course ini?
                </p>
                <label style="display:flex; align-items:flex-start; gap:0.65rem; text-align:left; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:10px; padding:0.85rem 1rem; cursor:pointer;">
                    <input type="checkbox" id="swalPreventRejoin" style="margin-top:0.2rem; width:1rem; height:1rem; accent-color:#ef4444;">
                    <span style="font-size:0.9rem; line-height:1.4; color:var(--text-main);">
                        Cegah siswa masuk course ini kembali
                        <small style="display:block; margin-top:0.25rem; color:var(--text-muted); font-weight:400;">
                            Jika dicentang, siswa tidak bisa gabung lagi lewat kode kelas.
                        </small>
                    </span>
                </label>
            `,
            icon: 'warning',
            showCancelButton: true,
            focusConfirm: false,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Keluarkan',
            cancelButtonText: 'Batal',
            background: 'var(--surface)',
            color: 'var(--text-main)',
            reverseButtons: true,
            preConfirm: () => {
                const el = document.getElementById('swalPreventRejoin');
                return !!(el && el.checked);
            }
        }).then((result) => {
            if (!result.isConfirmed) return;
            let input = this.querySelector('input[name="prevent_rejoin"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'prevent_rejoin';
                this.appendChild(input);
            }
            input.value = result.value ? '1' : '0';
            this.submit();
        });
    });
});
</script>
<!-- File Preview Modal (Global) -->
<div id="filePreviewModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); backdrop-filter:blur(15px); color:white; animation: fadeIn 0.3s ease-out;">
    <div style="position:relative; width:95%; height:92%; margin:1.5% auto; background:var(--surface); border-radius:var(--radius); border:1px solid var(--border); overflow:hidden; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="padding:1rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); background:rgba(255,255,255,0.03);">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="background:var(--primary); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="uil uil-eye" style="font-size:1.2rem;"></i>
                </div>
                <h4 id="previewTitle" style="margin:0; font-size:1.1rem; font-weight:600;">Pratinjau Berkas</h4>
            </div>
            <button onclick="closePreview()" style="background:rgba(255,255,255,0.05); border:none; color:var(--text-main); cursor:pointer; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:all 0.2s;">
                <i class="uil uil-multiply" style="font-size:1.4rem;"></i>
            </button>
        </div>
        <div id="previewContent" style="flex-grow:1; background:#0c0c0c; display:flex; align-items:center; justify-content:center; overflow:auto;">
             <!-- Content injected here -->
        </div>
        <div style="padding:0.8rem 1.5rem; background:rgba(0,0,0,0.2); border-top:1px solid var(--border); text-align:right;">
             <small style="color:var(--text-muted);">Tekan <kbd style="background:#444; padding:2px 5px; border-radius:4px; color:white;">ESC</kbd> untuk menutup</small>
        </div>
    </div>
</div>

<script>
const studentMenuToggle = document.getElementById('studentMenuToggle');
if (studentMenuToggle) {
    studentMenuToggle.addEventListener('click', () => {
        document.getElementById('studentNavbarLinks').classList.toggle('active');
        studentMenuToggle.classList.toggle('active');
        // ganti icon silang atau hamburger jika bisa, biarkan saja sbg bars tapi active style
        if(document.getElementById('studentNavbarLinks').classList.contains('active')){
           studentMenuToggle.innerHTML = '<i class="uil uil-multiply"></i>';
        } else {
           studentMenuToggle.innerHTML = '<i class="uil uil-bars"></i>';
        }
    });
}

function openPreview(url, title) {
    const modal = document.getElementById('filePreviewModal');
    const content = document.getElementById('previewContent');
    document.getElementById('previewTitle').innerText = title || "Pratinjau Berkas";
    
    content.innerHTML = '<div style="color:var(--text-muted); display:flex; flex-direction:column; align-items:center; gap:15px;"><div class="spinner"></div><span>Sedang memuat dokumen...</span></div>';
    modal.style.display = 'block';
    
    // Auto-detect extension
    const ext = url.split('.').pop().toLowerCase();
    
    setTimeout(() => {
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            content.innerHTML = `<img src="${url}" style="max-width:98%; max-height:98%; object-fit:contain; border-radius:var(--radius-sm); box-shadow:0 20px 40px rgba(0,0,0,0.8);">`;
        } else if (ext === 'pdf') {
            content.innerHTML = `<iframe src="${url}#toolbar=0" style="width:100%; height:100%; border:none;"></iframe>`;
        } else {
            content.innerHTML = `<div style="text-align:center; padding:3rem; max-width:400px; background:var(--surface); border-radius:var(--radius); border:1px solid var(--border);">
                <i class="uil uil-file-info-alt" style="font-size:5rem; color:var(--warning); margin-bottom:1.5rem; display:block;"></i>
                <h3 style="margin-bottom:1rem;">Tipe file tidak didukung untuk pratinjau</h3>
                <p style="color:var(--text-muted); margin-bottom:2rem; font-size:0.9rem;">Pratinjau hanya tersedia untuk format PDF dan Gambar. Silakan unduh file secara manual.</p>
                <div style="display:flex; gap:1rem; justify-content:center;">
                    <button onclick="closePreview()" class="btn btn-secondary btn-sm">Tutup</button>
                    <a href="${url}" download class="btn btn-primary btn-sm"><i class="uil uil-cloud-download"></i> Unduh File</a>
                </div>
            </div>`;
        }
    }, 400);
}

function closePreview() {
    document.getElementById('filePreviewModal').style.display = 'none';
    document.getElementById('previewContent').innerHTML = '';
}

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreview();
});

// Teacher admin sidebar toggle (collapse only — never overlay content)
(function () {
    const wrapper = document.getElementById('appWrapper');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('adminSidebarOverlay');
    if (!wrapper || !toggle) return;

    const storageKey = 'bitlearn_sidebar_collapsed';

    function syncAria() {
        const open = !wrapper.classList.contains('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (overlay) {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
        }
    }

    function applyInitial() {
        wrapper.classList.remove('sidebar-open');
        if (localStorage.getItem(storageKey) === '1') {
            wrapper.classList.add('sidebar-collapsed');
        } else {
            wrapper.classList.remove('sidebar-collapsed');
        }
        syncAria();
    }

    toggle.addEventListener('click', function () {
        wrapper.classList.toggle('sidebar-collapsed');
        localStorage.setItem(
            storageKey,
            wrapper.classList.contains('sidebar-collapsed') ? '1' : '0'
        );
        syncAria();
    });

    if (overlay) {
        overlay.addEventListener('click', function () {
            wrapper.classList.remove('sidebar-open');
            syncAria();
        });
    }

    applyInitial();
})();
</script>

<style>
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-left-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>

</html>