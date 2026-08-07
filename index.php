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
$hide_navbar = true; // hide navbar for the auth page to look cleaner
require_once 'components/header.php';
?>

<div class="auth-wrapper" style="flex-direction:column;">
    <div class="glass-card auth-card">
        <div class="auth-header">
            <div class="login-logo-container" style="display:flex; justify-content:center; margin-bottom:1.5rem; padding: 1rem;">
                <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn Logo" class="login-logo"
                    style="height:auto; width:220px; max-width:100%; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5)); transform: scale(1.1);">
            </div>
            <h1>Selamat Datang</h1>
            <p>Silakan masuk menggunakan identitas Anda</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="uil uil-exclamation-circle"></i>
                <?php
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="uil uil-check-circle"></i>
                <?php
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/actions/login.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Username (NIP / NISN)</label>
                <div style="position:relative;">
                    <i class="uil uil-user"
                        style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.2rem;"></i>
                    <input type="text" id="username" name="email" class="form-control" style="padding-left:3rem;"
                        placeholder="Masukkan NIP atau NISN Anda" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <div style="position:relative;">
                    <i class="uil uil-lock"
                        style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:1.2rem;"></i>
                    <input type="password" id="password" name="password" class="form-control" style="padding-left:3rem; padding-right:3rem;"
                        placeholder="••••••••" required>
                    <button type="button" id="togglePassword" aria-label="Tampilkan kata sandi"
                        style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-muted); padding:0.25rem; display:flex; align-items:center;">
                        <i class="uil uil-eye" id="togglePasswordIcon" style="font-size:1.25rem;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;">
                Masuk <i class="uil uil-arrow-right"></i>
            </button>
        </form>
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
    
    <div style="margin-top: 3rem; text-align:center; color:var(--text-muted); font-size:0.85rem;">
        <p style="margin-bottom:0.3rem;"><span style="color:var(--text-main); font-weight:500;">BitLearn E-Learning</span> &copy; 2026 MTsN 11 Majalengka</p>
        <p>Dikembangkan oleh <b style="color:var(--primary);">Dede Sudirman, S.Pd.</b></p>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>