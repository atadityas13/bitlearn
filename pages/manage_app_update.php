<?php
require_once '../core/config.php';
require_once '../core/AppVersion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: ../index.php');
    exit;
}

AppVersion::ensureTable($conn);
$config = AppVersion::getConfig($conn, 'android');
if (!$config) {
  $config = [
    'latest_version_code' => 3,
    'latest_version_name' => '1.2',
    'min_version_code' => 1,
    'force_update' => 0,
    'update_url' => AppVersion::DEFAULT_PLAY_URL,
    'release_notes' => '',
    'updated_at' => null,
  ];
}

$page_title = 'Manajemen Update App';
require_once '../components/header.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h2><i class="uil uil-mobile-android"></i> Manajemen Update Aplikasi</h2>
            <p class="text-muted" style="margin:0;">Atur versi terbaru aplikasi Android siswa. Aplikasi akan mengecek saat dibuka.</p>
        </div>
    </div>

    <div class="grid course-layout-grid" style="grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);">
        <div class="glass-card" style="padding:1.25rem;">
            <form action="../actions/save_app_update.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="latestVersionName">Versi terbaru (nama)</label>
                    <input type="text" id="latestVersionName" name="latest_version_name" class="form-control"
                        value="<?php echo htmlspecialchars((string)$config['latest_version_name']); ?>" required
                        placeholder="Contoh: 1.2">
                    <small class="text-muted">Sesuaikan dengan <code>versionName</code> di Android Studio.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="latestVersionCode">Kode versi terbaru (versionCode)</label>
                    <input type="number" id="latestVersionCode" name="latest_version_code" class="form-control" min="1" required
                        value="<?php echo (int)$config['latest_version_code']; ?>">
                    <small class="text-muted">Harus sama dengan angka di <code>build.gradle</code> saat upload ke Play Store.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="minVersionCode">Kode versi minimum wajib</label>
                    <input type="number" id="minVersionCode" name="min_version_code" class="form-control" min="1" required
                        value="<?php echo (int)$config['min_version_code']; ?>">
                    <small class="text-muted">Siswa dengan versi di bawah angka ini <b>wajib</b> update sebelum bisa lanjut.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="updateUrl">URL Play Store</label>
                    <input type="url" id="updateUrl" name="update_url" class="form-control"
                        value="<?php echo htmlspecialchars((string)($config['update_url'] ?? AppVersion::DEFAULT_PLAY_URL)); ?>"
                        placeholder="https://play.google.com/store/apps/details?id=...">
                </div>

                <div class="form-group">
                    <label class="form-label" for="releaseNotes">Catatan rilis</label>
                    <textarea id="releaseNotes" name="release_notes" class="form-control" rows="5"
                        placeholder="Perbaikan bug, peningkatan performa, dll."><?php echo htmlspecialchars((string)($config['release_notes'] ?? '')); ?></textarea>
                </div>

                <label class="form-check" style="display:flex; align-items:flex-start; gap:0.65rem; margin-bottom:1.25rem; cursor:pointer;">
                    <input type="checkbox" name="force_update" value="1" <?php echo !empty($config['force_update']) ? 'checked' : ''; ?>
                        style="width:18px; height:18px; margin-top:0.2rem;">
                    <span>
                        <b>Paksa update</b> untuk semua versi di bawah versi terbaru
                        <small style="display:block; color:var(--text-muted); margin-top:0.2rem;">
                            Jika dicentang, siswa yang belum ke versi terbaru tidak bisa melewati dialog update (selain opsi buka Play Store).
                        </small>
                    </span>
                </label>

                <button type="submit" class="btn btn-primary">
                    <i class="uil uil-save"></i> Simpan Pengaturan
                </button>
            </form>
        </div>

        <div class="glass-card" style="padding:1.15rem;">
            <h3 style="margin:0 0 0.75rem; font-size:1rem;"><i class="uil uil-info-circle"></i> Panduan</h3>
            <ul style="margin:0; padding-left:1.1rem; color:var(--text-muted); font-size:0.9rem; line-height:1.55;">
                <li>Upload versi baru ke Play Console terlebih dahulu.</li>
                <li>Isi <b>versionCode</b> dan <b>versionName</b> sesuai rilis terbaru.</li>
                <li>Naikkan <b>minimum wajib</b> jika versi lama tidak boleh dipakai lagi.</li>
                <li>Aplikasi <b>baru</b> mengecek update di splash (header <code>X-App-Update-Managed</code>).</li>
                <li>Aplikasi <b>lama</b> tanpa fitur itu akan diblokir di beranda jika versinya di bawah versi terbaru admin.</li>
            </ul>
            <?php if (!empty($config['updated_at'])): ?>
                <p class="text-muted" style="margin:1rem 0 0; font-size:0.82rem;">
                    Terakhir diubah: <?php echo date('d M Y, H:i', strtotime((string)$config['updated_at'])); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../components/footer.php'; ?>
