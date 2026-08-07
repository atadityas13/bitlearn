<?php
// Prevent direct access to header
if(strpos($_SERVER['REQUEST_URI'], 'header.php') !== false) die('Akses langsung tidak diizinkan');

$is_teacher = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher');

// Intercept Alerts for SweetAlert Globally
$swal_success = '';
$swal_error = '';
if(isset($_SESSION['success'])) {
    $swal_success = $_SESSION['success'];
    unset($_SESSION['success']); // Prevent inline HTML alerts from rendering
}
if(isset($_SESSION['error'])) {
    $swal_error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - BitLearn' : 'BitLearn | E-Learning Modern'; ?></title>
    <!-- We prefer to use Vanilla CSS to match our premium design standard -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/style.css') ?: '1'; ?>">
    <!-- Unicons for beautiful modern icons -->
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup { font-family: 'Outfit', sans-serif !important; border-radius: var(--radius) !important; border: 1px solid rgba(255,255,255,0.08) !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.55) !important; }
        .swal2-title { color: var(--text-main) !important; font-weight: 700 !important; font-size: 1.35rem !important; }
        .swal2-html-container { color: var(--text-muted) !important; }
        .swal2-confirm { border-radius: var(--radius-sm) !important; padding: 0.7rem 1.4rem !important; font-weight: 600 !important; }
        .swal2-cancel { border-radius: var(--radius-sm) !important; padding: 0.7rem 1.4rem !important; }
        .swal2-icon.swal2-success { border-color: #10b981 !important; color: #10b981 !important; }
        .swal2-icon.swal2-success [class^=swal2-success-line] { background-color: #10b981 !important; }
        .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(16,185,129,0.3) !important; }
        .swal2-icon.swal2-error { border-color: #ef4444 !important; color: #ef4444 !important; }
        .swal2-icon.swal2-error .swal2-x-mark-line-left,
        .swal2-icon.swal2-error .swal2-x-mark-line-right { background-color: #ef4444 !important; }
    </style>
</head>
<body class="bg-gradient-mesh">

<?php if(isset($hide_navbar) && $hide_navbar): ?>
    <!-- Mode Tanpa Navigasi (Untuk Ujian / Viewer Imersif) -->
<?php else: ?>
    
    <?php if($is_teacher): ?>
        <!-- Teacher Sidebar Layout -->
        <div class="app-wrapper" id="appWrapper">
            <div class="admin-sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>
            <!-- Sidebar Kiri -->
            <aside class="app-sidebar" id="appSidebar">
                <div class="sidebar-header" style="justify-content:center; display:flex; padding:2rem 0 1rem 0;">
                    <a href="<?php echo BASE_URL; ?>" style="display:block;">
                        <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn Logo" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                    </a>
                </div>
                <nav class="sidebar-nav">
                    <?php $cur = $_SERVER['REQUEST_URI']; ?>
                    <a href="<?php echo BASE_URL; ?>/pages/teacher_dashboard.php" class="sidebar-link <?php echo strpos($cur, 'teacher_dashboard') ? 'active' : ''; ?>" title="Beranda">
                        <i class="uil uil-estate"></i> <span>Beranda</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/manage_classes.php" class="sidebar-link <?php echo strpos($cur, 'manage_classes') ? 'active' : ''; ?>" title="Manajemen Rombel">
                        <i class="uil uil-building"></i> <span>Manajemen Rombel</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/manage_students.php" class="sidebar-link <?php echo strpos($cur, 'manage_students') ? 'active' : ''; ?>" title="Manajemen Siswa">
                        <i class="uil uil-users-alt"></i> <span>Manajemen Siswa</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/manage_courses.php" class="sidebar-link <?php echo strpos($cur, 'manage_courses') || strpos($cur, 'course_view') || strpos($cur, 'add_') ? 'active' : ''; ?>" title="Manajemen Course">
                        <i class="uil uil-books"></i> <span>Manajemen Course</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/teacher_grading.php" class="sidebar-link <?php echo strpos($cur, 'teacher_grading') ? 'active' : ''; ?>" title="Penilaian">
                        <i class="uil uil-award"></i> <span>Penilaian</span>
                    </a>
                </nav>
                <div class="sidebar-footer">
                    <div class="profile-row">
                        <?php $prof_pic = isset($_SESSION['profile_pic']) && !empty($_SESSION['profile_pic']) ? BASE_URL . '/uploads/' . $_SESSION['profile_pic'] : null; ?>
                        <?php if($prof_pic): ?>
                            <img src="<?php echo htmlspecialchars($prof_pic); ?>" alt="Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid var(--primary); flex-shrink:0;">
                        <?php else: ?>
                            <div style="width:42px; height:42px; border-radius:50%; background:var(--surface); display:flex; align-items:center; justify-content:center; border:2px solid var(--primary); font-size:1.35rem; color:var(--text-muted); flex-shrink:0;">
                                <i class="uil uil-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="user-meta" style="flex:1; overflow:hidden; min-width:0;">
                            <div style="font-size:0.9rem; font-weight:600; color:var(--text-main); white-space:nowrap; text-overflow:ellipsis; overflow:hidden;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                            <div style="font-size:0.72rem; color:var(--text-muted);">Akun Guru</div>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <a href="<?php echo BASE_URL; ?>/pages/edit_profile.php" class="btn btn-secondary btn-profile" title="Pengaturan Profil"><i class="uil uil-user-circle"></i> <span class="btn-label">Profil</span></a>
                        <a href="<?php echo BASE_URL; ?>/actions/logout.php" class="btn btn-danger btn-logout" title="Keluar"><i class="uil uil-sign-out-alt"></i></a>
                    </div>
                </div>
            </aside>
            
            <!-- Konten Utama Kanan -->
            <main class="app-main">
                <header class="top-nav">
                    <div class="top-nav-left">
                        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu navigasi" aria-controls="appSidebar" aria-expanded="true">
                            <i class="uil uil-bars"></i>
                        </button>
                        <span style="color:var(--text-muted); font-size:0.9rem;">
                            <i class="uil uil-calender"></i> <?php echo date('d M Y'); ?>
                        </span>
                    </div>
                    <div class="top-nav-right">
                        <div class="top-nav-user">
                            <a href="<?php echo BASE_URL; ?>/pages/edit_profile.php" class="btn btn-secondary" title="Profil"><i class="uil uil-user-circle"></i></a>
                            <a href="<?php echo BASE_URL; ?>/actions/logout.php" class="btn btn-danger" title="Keluar"><i class="uil uil-sign-out-alt"></i></a>
                        </div>
                    </div>
                </header>
    <?php else: ?>
        <!-- Student & Guest Navbar -->
        <nav class="navbar" style="align-items:center;">
            <div class="navbar-header-mobile" style="display:flex; justify-content:space-between; align-items:center; width: 100%;">
                <a href="<?php echo BASE_URL; ?>" class="navbar-brand" style="line-height:1.2; font-size:1.4rem;">
                    Bit<span style="color:var(--text-main);">Learn</span><br>
                    <span style="font-size:0.8rem; font-weight:normal; color:var(--text-muted); letter-spacing:0.5px;">MTsN 11 Majalengka</span>
                </a>
                <button class="student-menu-toggle" id="studentMenuToggle" style="display:none;" title="Toggle Menu">
                    <i class="uil uil-bars"></i>
                </button>
            </div>
            <div class="navbar-links" id="studentNavbarLinks">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo BASE_URL; ?>/pages/student_dashboard.php" class="btn btn-primary" style="margin-left:0; font-weight:500;"><i class="uil uil-book-reader"></i> Area Belajar</a>
                    <a href="<?php echo BASE_URL; ?>/actions/logout.php" class="btn btn-danger" style="margin-left:1.5rem;"><i class="uil uil-sign-out-alt"></i> Keluar</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary">Masuk Portal</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>
