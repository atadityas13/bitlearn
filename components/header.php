<?php
// Prevent direct access to header
if(strpos($_SERVER['REQUEST_URI'], 'header.php') !== false) die('Akses langsung tidak diizinkan');

$is_teacher = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher');

// Intercept Alerts for SweetAlert Globally
$swal_success = '';
$swal_error = '';
if(isset($_SESSION['success'])) {
    $swal_success = $_SESSION['success'];
    unset($_SESSION['success']);
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
    <?php if ($is_teacher): ?>
    <!-- Bootstrap 5 (admin shell) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/style.css') ?: '1'; ?>">
    <?php if ($is_teacher): ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/admin-shell.css?v=<?php echo @filemtime(__DIR__ . '/../assets/admin-shell.css') ?: '1'; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-popup { font-family: 'Outfit', sans-serif !important; border-radius: var(--radius) !important; border: 1px solid var(--border) !important; box-shadow: 0 25px 50px -12px rgba(28,25,23,0.2) !important; background: var(--surface) !important; }
        .swal2-title { color: var(--text-main) !important; font-weight: 700 !important; font-size: 1.35rem !important; }
        .swal2-html-container { color: var(--text-muted) !important; }
        .swal2-confirm { border-radius: var(--radius-sm) !important; padding: 0.7rem 1.4rem !important; font-weight: 600 !important; }
        .swal2-cancel { border-radius: var(--radius-sm) !important; padding: 0.7rem 1.4rem !important; }
        .swal2-icon.swal2-success { border-color: #0D9488 !important; color: #0D9488 !important; }
        .swal2-icon.swal2-success [class^=swal2-success-line] { background-color: #0D9488 !important; }
        .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(13,148,136,0.3) !important; }
        .swal2-icon.swal2-error { border-color: #DC2626 !important; color: #DC2626 !important; }
        .swal2-icon.swal2-error .swal2-x-mark-line-left,
        .swal2-icon.swal2-error .swal2-x-mark-line-right { background-color: #DC2626 !important; }
    </style>
</head>
<body class="bg-gradient-mesh<?php echo $is_teacher ? ' admin-body' : ''; ?>">

<?php if(isset($hide_navbar) && $hide_navbar): ?>
    <!-- Mode Tanpa Navigasi (Untuk Ujian / Viewer Imersif) -->
<?php else: ?>
    
    <?php if($is_teacher): ?>
        <?php $cur = $_SERVER['REQUEST_URI']; ?>
        <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop" aria-hidden="true"></div>

        <aside id="blSidebar" aria-label="Menu navigasi guru">
            <div class="sidebar-header">
                <a href="<?php echo BASE_URL; ?>">
                    <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="BitLearn Logo">
                </a>
            </div>
            <nav class="sidebar-nav">
                <a href="<?php echo BASE_URL; ?>/pages/teacher_dashboard.php" class="sidebar-link <?php echo strpos($cur, 'teacher_dashboard') !== false ? 'active' : ''; ?>" title="Beranda">
                    <i class="uil uil-estate"></i> <span>Beranda</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/manage_classes.php" class="sidebar-link <?php echo strpos($cur, 'manage_classes') !== false ? 'active' : ''; ?>" title="Manajemen Rombel">
                    <i class="uil uil-building"></i> <span>Manajemen Rombel</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/manage_students.php" class="sidebar-link <?php echo strpos($cur, 'manage_students') !== false ? 'active' : ''; ?>" title="Manajemen Siswa">
                    <i class="uil uil-users-alt"></i> <span>Manajemen Siswa</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/manage_courses.php" class="sidebar-link <?php echo (strpos($cur, 'manage_courses') !== false || strpos($cur, 'course_view') !== false || strpos($cur, 'add_') !== false) ? 'active' : ''; ?>" title="Manajemen Course">
                    <i class="uil uil-books"></i> <span>Manajemen Course</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/pages/teacher_grading.php" class="sidebar-link <?php echo strpos($cur, 'teacher_grading') !== false ? 'active' : ''; ?>" title="Penilaian">
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

        <div id="blMain">
            <header class="top-nav">
                <div class="top-nav-left">
                    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu navigasi" aria-controls="blSidebar" aria-expanded="true">
                        <i class="uil uil-bars"></i>
                    </button>
                    <span style="color:var(--text-muted); font-size:0.9rem;">
                        <i class="uil uil-calender"></i> <?php echo date('d M Y'); ?>
                    </span>
                </div>
                <div class="top-nav-right">
                    <div class="top-nav-user" style="display:none;">
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
