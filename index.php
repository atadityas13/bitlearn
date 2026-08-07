<?php
require_once 'core/config.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    $role = trim(strtolower((string) $_SESSION['user_role']));
    if ($role === 'teacher') {
        header("Location: pages/teacher_dashboard.php");
    } else {
        header("Location: pages/student_dashboard.php");
    }
    exit;
}

$page_title = 'Masuk ke Portal';
$hide_navbar = true;
$auth_page = true;
require_once 'components/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card-decor" aria-hidden="true"></div>

        <div class="auth-header">
            <div class="login-logo-container">
                <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn" class="login-logo">
            </div>
            <h1>Selamat Datang</h1>
            <p>Silakan masuk menggunakan identitas Anda</p>
        </div>

        <?php if (!empty($swal_error)): ?>
            <div class="alert alert-danger auth-alert">
                <i class="uil uil-exclamation-circle"></i>
                <?php echo htmlspecialchars($swal_error); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger auth-alert">
                <i class="uil uil-exclamation-circle"></i>
                <?php
                echo htmlspecialchars((string)$_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($swal_success)): ?>
            <div class="alert alert-success auth-alert">
                <i class="uil uil-check-circle"></i>
                <?php echo htmlspecialchars($swal_success); ?>
            </div>
        <?php elseif (isset($_SESSION['success'])): ?>
            <div class="alert alert-success auth-alert">
                <i class="uil uil-check-circle"></i>
                <?php
                echo htmlspecialchars((string)$_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/actions/login.php" method="POST" class="auth-form">
            <div class="form-group">
                <label class="form-label" for="username">Username (NIP / NISN)</label>
                <div class="auth-input-wrap">
                    <i class="uil uil-user" aria-hidden="true"></i>
                    <input type="text" id="username" name="email" class="form-control"
                        placeholder="Masukkan NIP atau NISN Anda" required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <div class="auth-input-wrap">
                    <i class="uil uil-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control auth-input-password"
                        placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" id="togglePassword" class="auth-eye" aria-label="Tampilkan kata sandi">
                        <i class="uil uil-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-block auth-submit">
                Masuk <i class="uil uil-arrow-right"></i>
            </button>
        </form>
    </div>

    <div class="auth-footer">
        <p><span>BitLearn E-Learning</span> &copy; 2026 MTsN 11 Majalengka</p>
        <p>Dikembangkan oleh <b>Dede Sudirman, S.Pd.</b></p>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('togglePassword');
    var input = document.getElementById('password');
    var icon = document.getElementById('togglePasswordIcon');
    if (!btn || !input || !icon) return;
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'uil uil-eye-slash' : 'uil uil-eye';
        btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
})();
</script>

<?php
// Hindari SweetAlert ganda: pesan sudah ditampilkan di kartu login
$swal_success = '';
$swal_error = '';
require_once 'components/footer.php';
?>
