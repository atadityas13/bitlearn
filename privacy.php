<?php
require_once __DIR__ . '/core/config.php';

$page_title = 'Kebijakan Privasi';
$hide_navbar = true;
require_once __DIR__ . '/components/header.php';
?>

<div class="auth-wrapper" style="flex-direction:column; align-items:center; padding:2rem 1rem 3rem;">
    <div class="glass-card" style="max-width:820px; width:100%; padding:2rem 1.75rem;">
        <div style="text-align:center; margin-bottom:1.75rem;">
            <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn"
                style="height:auto; width:160px; max-width:100%; margin-bottom:1rem;">
            <h1 style="margin:0 0 0.4rem; font-size:1.6rem;">Kebijakan Privasi</h1>
            <p style="margin:0; color:var(--text-muted); font-size:0.95rem;">
                BitLearn MTsN 11 Majalengka
            </p>
            <p style="margin:0.5rem 0 0; color:var(--text-muted); font-size:0.85rem;">
                Terakhir diperbarui: 8 Agustus 2026
            </p>
        </div>

        <div style="color:var(--text-main); line-height:1.7; font-size:0.95rem;">
            <p>
                Kebijakan Privasi ini menjelaskan bagaimana <strong>BitLearn</strong>
                (aplikasi dan portal e-learning MTsN 11 Majalengka) mengumpulkan, menggunakan,
                menyimpan, dan melindungi data pengguna.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">1. Pengelola</h2>
            <p>
                BitLearn dikelola oleh <strong>MTsN 11 Majalengka</strong>
                untuk keperluan pembelajaran digital siswa dan guru.
                Kontak: situs
                <a href="<?php echo BASE_URL; ?>" style="color:var(--primary);"><?php echo htmlspecialchars(BASE_URL); ?></a>
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">2. Data yang dikumpulkan</h2>
            <ul style="padding-left:1.2rem; margin:0.4rem 0; color:var(--text-muted);">
                <li>Identitas akun sekolah: nama, NISN/NIP (username), dan peran (siswa/guru).</li>
                <li>Data pembelajaran: mata pelajaran, progres materi, jawaban kuis, dan pengumpulan tugas.</li>
                <li>Foto profil (opsional) jika pengguna mengunggahnya.</li>
                <li>Token sesi login pada aplikasi Android untuk menjaga status masuk.</li>
                <li>Data teknis dasar yang diperlukan agar layanan berfungsi (misalnya permintaan API melalui koneksi internet).</li>
            </ul>
            <p style="margin-top:0.75rem;">
                Kami <strong>tidak</strong> menjual data pengguna dan <strong>tidak</strong> menampilkan iklan pihak ketiga.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">3. Tujuan penggunaan data</h2>
            <ul style="padding-left:1.2rem; margin:0.4rem 0; color:var(--text-muted);">
                <li>Menyediakan akses login dan layanan e-learning.</li>
                <li>Menampilkan materi, kuis, tugas, dan progres belajar.</li>
                <li>Memungkinkan guru mengelola kelas, penilaian, dan peserta course.</li>
                <li>Menjaga keamanan akun dan mencegah akses yang tidak sah.</li>
            </ul>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">4. Penyimpanan dan keamanan</h2>
            <p>
                Data disimpan pada server sekolah yang digunakan BitLearn.
                Kata sandi disimpan dalam bentuk terenkripsi (hash).
                Komunikasi aplikasi produksi menggunakan HTTPS.
                Akses data dibatasi sesuai peran pengguna (siswa/guru/admin sekolah).
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">5. Berbagi data</h2>
            <p>
                Data pembelajaran dapat dilihat oleh guru/pembina terkait di lingkungan sekolah
                sesuai keperluan akademik. Data tidak dibagikan kepada pihak luar untuk pemasaran.
                Kami dapat mengungkapkan data jika diwajibkan oleh peraturan perundang-undangan
                yang berlaku.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">6. Hak pengguna</h2>
            <p>
                Siswa/orang tua/guru dapat meminta pembaruan atau penghapusan data akun melalui
                pihak sekolah (admin BitLearn / guru terkait), sepanjang tidak bertentangan
                dengan kebutuhan administrasi akademik sekolah.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">7. Anak di bawah umur</h2>
            <p>
                BitLearn ditujukan untuk siswa MTsN 11 Majalengka dalam konteks pendidikan formal.
                Penggunaan akun diatur dan diawasi oleh pihak sekolah.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">8. Perubahan kebijakan</h2>
            <p>
                Kebijakan ini dapat diperbarui. Versi terbaru akan dipublikasikan pada halaman ini
                beserta tanggal pembaruan.
            </p>

            <h2 style="font-size:1.15rem; margin:1.5rem 0 0.6rem;">9. Kontak</h2>
            <p>
                Untuk pertanyaan terkait privasi BitLearn, hubungi pihak MTsN 11 Majalengka
                melalui kanal resmi sekolah atau melalui portal BitLearn.
            </p>
        </div>

        <div style="margin-top:2rem; text-align:center;">
            <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:0.4rem;">
                <i class="uil uil-arrow-left"></i> Kembali ke portal
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
