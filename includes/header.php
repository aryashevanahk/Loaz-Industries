<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user profile photo if logged in
$user_profile_photo = null;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    $stmt = $pdo->prepare("SELECT profile_photo, name, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $user_profile_photo = $user_data['profile_photo'];
        $_SESSION['user_name'] = $user_data['name'];
        $_SESSION['role'] = $user_data['role'];
    }
}

// Get current file name for active menu detection
$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

$page_name = str_replace('.php', '', $current_file);

// Handle homepage
if ($page_name == 'homepage' || $page_name == 'index') {
    $page_name = 'home';
}

$css_file = "/loaz_industries/assets/css/{$page_name}.css";
if (file_exists($_SERVER['DOCUMENT_ROOT'] . $css_file)) {
    echo "<link rel='stylesheet' href='{$css_file}'>";
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loaz Industries - Servis & Part Elektronik</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/loaz_industries/assets/css/style.css">
    
    <?php
    // Load page-specific CSS
    $page_name = str_replace('.php', '', $current_file);
    
    // Handle index page
    if ($page_name == 'index') {
        $page_name = 'home';
    }
    
    $css_file = "/loaz_industries/assets/css/{$page_name}.css";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $css_file)) {
        echo "<link rel='stylesheet' href='{$css_file}'>";
    }
    ?>
    
</head>
<body>
    <!-- Header / Navigation -->
    <header class="site-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="/loaz_industries/">
                    <div class="brand-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="brand-text">
                        <span class="brand-name">Loaz</span>
                        <span class="brand-sub">Industries</span>
                    </div>
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav mx-auto">
                        <!-- Beranda -->
                        <li class="nav-item">
                        <a class="nav-link <?php echo ($current_file == 'homepage.php' || $current_file == 'index.php') ? 'active' : ''; ?>" href="/loaz_industries/homepage.php">
                            <i class="fas fa-home"></i>
                            <span>Beranda</span>
                        </a>
                    </li>

                        <!-- Belanja Part - Sembunyikan untuk teknisi -->
                        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] != 'technician'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_file == 'order_part.php') ? 'active' : ''; ?>" href="/loaz_industries/user/order_part.php">
                                <i class="fas fa-microchip"></i>
                                <span>Belanja Part</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Layanan Servis - Sembunyikan untuk teknisi -->
                        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] != 'technician'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_file == 'request_service.php') ? 'active' : ''; ?>" href="/loaz_industries/user/request_service.php">
                                <i class="fas fa-tools"></i>
                                <span>Layanan Servis</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <!-- Tentang -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_file == 'about.php') ? 'active' : ''; ?>" href="/loaz_industries/about.php">
                                <i class="fas fa-info-circle"></i>
                                <span>Tentang</span>
                            </a>
                        </li>

                        <!-- Kontak -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_file == 'contact.php') ? 'active' : ''; ?>" href="/loaz_industries/contact.php">
                                <i class="fas fa-envelope"></i>
                                <span>Kontak</span>
                            </a>
                        </li>

                        <li class="nav-item">
    <a class="nav-link <?php echo ($current_file == 'karir.php') ? 'active' : ''; ?>" href="/loaz_industries/career/karir.php">
        <i class="fas fa-briefcase"></i>
        <span>Karir</span>
    </a>
</li>
                    </ul>

                    <div class="navbar-actions">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="user-menu dropdown">
                                <a href="#" class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar-circle">
                                        <?php 
                                        $photo_path = $_SERVER['DOCUMENT_ROOT'] . '/loaz_industries/assets/images/users/' . $user_profile_photo;
                                        if ($user_profile_photo && file_exists($photo_path)): 
                                        ?>
                                            <img src="/loaz_industries/assets/images/users/<?php echo $user_profile_photo; ?>" 
                                                 alt="Profile" 
                                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                        <?php else: ?>
                                            <span><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <li><a class="dropdown-item" href="/loaz_industries/admin/dashboard.php">
                                            <i class="fas fa-chart-line"></i> Dashboard Admin
                                        </a></li>
                                    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'technician'): ?>
                                        <li><a class="dropdown-item" href="/loaz_industries/technician/dashboard.php">
                                            <i class="fas fa-tachometer-alt"></i> Dashboard Teknisi
                                        </a></li>
                                        <li><a class="dropdown-item" href="/loaz_industries/technician/my_services.php">
                                            <i class="fas fa-tools"></i> Servis Saya
                                        </a></li>
                                        <li><a class="dropdown-item" href="/loaz_industries/technician/earnings.php">
                                            <i class="fas fa-money-bill-wave"></i> Pendapatan
                                        </a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="/loaz_industries/user/dashboard.php">
                                            <i class="fas fa-user"></i> Dashboard Saya
                                        </a></li>
                                        <li><a class="dropdown-item" href="/loaz_industries/user/my_services.php">
                                            <i class="fas fa-tools"></i> Servis Saya
                                        </a></li>
                                        <li><a class="dropdown-item" href="/loaz_industries/user/my_orders.php">
                                            <i class="fas fa-shopping-cart"></i> Pesanan Saya
                                        </a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/loaz_industries/<?php echo isset($_SESSION['role']) && $_SESSION['role'] === 'technician' ? 'technician' : 'user'; ?>/profile.php">
                                        <i class="fas fa-id-card"></i> Profil Saya
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/loaz_industries/auth/logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Keluar
                                    </a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="/loaz_industries/auth/login.php" class="btn btn-outline">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Masuk</span>
                            </a>
                            <a href="/loaz_industries/auth/register.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i>
                                <span>Daftar</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="site-main">