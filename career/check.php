<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Career page - open for public, no login required
// Jangan panggil redirectIfNotLoggedIn() karena halaman ini untuk publik

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$application = null;
$error_message = '';

if ($email) {
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM technician_applications WHERE email = ? ORDER BY applied_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $application = $stmt->fetch();
        
        if (!$application) {
            $error_message = 'Email tidak ditemukan dalam database kami. Pastikan email yang Anda masukkan benar atau <a href="apply.php" style="color: var(--gold-brown); text-decoration: underline;">daftar sekarang</a>.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Lamaran - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS Variables - Konsisten dengan sistem utama */
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
            --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--dark-brown);
        }
        
        /* Career Navigation - Sama persis dengan apply.php */
        .career-nav {
            background: white;
            padding: 1rem 0;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .career-nav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .nav-brand:hover {
            transform: translateY(-2px);
        }
        
        .nav-brand-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .nav-brand-icon i {
            font-size: 1.3rem;
            color: white;
        }
        
        .nav-brand-text {
            font-weight: 700;
            color: var(--dark-brown);
            font-size: 1.1rem;
            line-height: 1.2;
        }
        
        .nav-brand-text small {
            font-weight: 400;
            font-size: 0.7rem;
            color: var(--gold-brown);
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--medium-brown);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 0;
            position: relative;
        }
        
        .nav-links a:hover {
            color: var(--gold-brown);
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold-brown);
            transition: var(--transition);
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        .nav-links a.active {
            color: var(--gold-brown);
        }
        
        .nav-links a.active::after {
            width: 100%;
        }
        
        /* Main Content Container */
        .career-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Breadcrumb */
        .breadcrumb-custom {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
        }
        
        .breadcrumb-custom .breadcrumb {
            background: transparent;
            padding: 0;
        }
        
        .breadcrumb-custom .breadcrumb-item {
            color: var(--medium-brown);
            font-size: 0.85rem;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: var(--gold-brown);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb-custom .breadcrumb-item a:hover {
            color: var(--medium-brown);
        }
        
        .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--medium-brown);
            font-size: 1.1rem;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        /* Search & Status Cards */
        .search-card, .status-card {
            background: white;
            border: 1px solid rgba(192, 133, 82, 0.15);
            border-radius: 24px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .search-card:hover, .status-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }
        
        .search-icon, .status-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(192, 133, 82, 0.1), rgba(140, 90, 60, 0.05));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            margin: 0 auto;
        }
        
        .search-icon:hover, .status-icon:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, rgba(192, 133, 82, 0.15), rgba(140, 90, 60, 0.08));
        }
        
        .search-icon i, .status-icon i {
            font-size: 2.5rem;
            color: var(--gold-brown);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.6rem 1.8rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.12), rgba(255, 193, 7, 0.05));
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .status-approved {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.12), rgba(40, 167, 69, 0.05));
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .status-rejected {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.12), rgba(220, 53, 69, 0.05));
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        /* Application Details */
        .application-details {
            background: linear-gradient(135deg, rgba(192, 133, 82, 0.04), rgba(140, 90, 60, 0.02));
            border-radius: 20px;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        
        .detail-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            width: 130px;
            font-weight: 600;
            color: var(--medium-brown);
            font-size: 0.85rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--dark-brown);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Form Elements */
        .form-control {
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        .form-control:hover {
            border-color: rgba(192, 133, 82, 0.4);
        }
        
        /* Buttons */
        .btn-gold {
            background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
            color: white;
            border: none;
            transition: var(--transition);
            font-weight: 600;
            letter-spacing: 0.3px;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
        }
        
        .btn-gold:hover {
            background: linear-gradient(135deg, #d4945a, #9c6a46);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(192, 133, 82, 0.3);
            color: white;
        }
        
        .btn-outline-gold {
            background: transparent;
            color: var(--gold-brown);
            border: 2px solid var(--gold-brown);
            transition: var(--transition);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
        }
        
        .btn-outline-gold:hover {
            background: var(--gold-brown);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Alert */
        .alert-warning {
            border: none;
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.08), rgba(255, 193, 7, 0.03));
            border-left: 4px solid #ffc107;
            border-radius: 16px;
            padding: 1rem 1.25rem;
        }
        
        /* Footer */
        .footer-career {
            text-align: center;
            padding: 2rem;
            color: var(--medium-brown);
            font-size: 0.8rem;
            border-top: 1px solid rgba(192, 133, 82, 0.1);
            margin-top: 3rem;
            background: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .career-nav .container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .career-container {
                padding: 1.5rem 1rem;
            }
            
            .detail-label {
                width: 100px;
                font-size: 0.75rem;
            }
            
            .detail-value {
                font-size: 0.75rem;
            }
            
            .status-badge {
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .search-icon, .status-icon {
                width: 60px;
                height: 60px;
            }
            
            .search-icon i, .status-icon i {
                font-size: 1.8rem;
            }
        }
        
        /* Print styles */
        @media print {
            .career-nav, .btn, .alert, .breadcrumb-custom, .btn-outline-gold, .footer-career {
                display: none !important;
            }
            
            .status-card {
                box-shadow: none;
                border: 1px solid #ddd;
                margin: 0;
                padding: 20px;
            }
            
            body {
                background: white;
            }
            
            .career-container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Simple Navigation for Career Pages - Sama persis dengan apply.php -->
    <header class="career-nav">
        <div class="container">
            <a href="index.php" class="nav-brand">
                <div class="nav-brand-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="nav-brand-text">
                    Loaz Industries<br>
                    <small>Karir & Rekrutmen</small>
                </div>
            </a>
            <div class="nav-links">
                <a href="karir.php"><i class="fas fa-home me-1"></i> Beranda</a>
                <a href="apply.php"><i class="fas fa-paper-plane me-1"></i> Lamar</a>
                <a href="check.php" class="active"><i class="fas fa-search me-1"></i> Cek Status</a>
                <a href="/loaz_industries/index.php"><i class="fas fa-globe me-1"></i> Website Utama</a>
            </div>
        </div>
    </header>

    <div class="career-container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="breadcrumb-custom">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="karir.php">Karir</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Cek Status Lamaran</li>
                    </ol>
                </nav>
                
                <?php if ($error_message): ?>
                    <!-- Error Alert -->
                    <div class="alert alert-warning alert-dismissible fade show animate-fade-in" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3" style="color: #ffc107;"></i>
                            <div>
                                <strong>Email Tidak Ditemukan!</strong><br>
                                <?php echo $error_message; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <!-- Show search form again -->
                    <div class="search-card p-4 text-center animate-fade-in">
                        <div class="search-icon mx-auto mb-3">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 style="color: var(--dark-brown);">Cek Status Lamaran</h3>
                        <p class="text-muted mb-4">Masukkan email yang digunakan saat mendaftar</p>
                        
                        <form method="GET" class="mb-4">
                            <div class="mb-3">
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       placeholder="Email address" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-gold w-100">
                                <i class="fas fa-search me-2"></i> Cek Status
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <p class="small text-muted mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Belum mendaftar? <a href="apply.php" style="color: var(--gold-brown); font-weight: 500;">Daftar sekarang →</a>
                        </p>
                    </div>
                    
                <?php elseif ($application): ?>
                    <!-- Status Result -->
                    <div class="status-card p-4 animate-fade-in">
                        <div class="text-center mb-4">
                            <div class="status-icon mx-auto mb-3">
                                <?php if ($application['status'] == 'pending'): ?>
                                    <i class="fas fa-clock"></i>
                                <?php elseif ($application['status'] == 'approved'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i>
                                <?php endif; ?>
                            </div>
                            <h3 style="color: var(--dark-brown);">Status Lamaran</h3>
                            <p class="text-muted"><?php echo htmlspecialchars($application['email']); ?></p>
                        </div>
                        
                        <div class="status-badge-container text-center mb-4">
                            <?php if ($application['status'] == 'pending'): ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-spinner fa-spin me-1"></i> Sedang Diproses
                                </span>
                                <p class="small text-muted mt-2">Lamaran Anda sedang kami review. Harap tunggu 1-3 hari kerja.</p>
                            <?php elseif ($application['status'] == 'approved'): ?>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle me-1"></i> Diterima
                                </span>
                                <p class="small text-success mt-2">Selamat! Anda lolos seleksi. Tim HRD akan menghubungi Anda melalui email/telepon dalam waktu 2x24 jam.</p>
                            <?php else: ?>
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle me-1"></i> Tidak Diterima
                                </span>
                                <p class="small text-muted mt-2">Mohon maaf, lamaran Anda belum berhasil. Jangan menyerah, coba lagi lain waktu! Tetap semangat!</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="application-details">
                            <h6 class="mb-3" style="color: var(--dark-brown); font-weight: 600;">
                                <i class="fas fa-info-circle me-2" style="color: var(--gold-brown);"></i>
                                Detail Lamaran:
                            </h6>
                            <div class="detail-row">
                                <span class="detail-label">Nama Lengkap</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Spesialisasi</span>
                                <span class="detail-value"><?php echo htmlspecialchars($application['specialty']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Pengalaman</span>
                                <span class="detail-value"><?php echo $application['experience_years']; ?> tahun</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Tanggal Daftar</span>
                                <span class="detail-value"><?php echo date('d F Y H:i', strtotime($application['applied_at'])); ?></span>
                            </div>
                            <?php if ($application['reviewed_at']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Direview Pada</span>
                                    <span class="detail-value"><?php echo date('d F Y H:i', strtotime($application['reviewed_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($application['admin_note']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Catatan Admin</span>
                                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($application['admin_note'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="check.php" class="btn btn-gold px-4">
                                <i class="fas fa-arrow-left me-2"></i> Kembali ke Karir
                            </a>
                            <?php if ($application['status'] == 'approved'): ?>
                                <button class="btn btn-outline-gold px-4 ms-2" onclick="window.print();">
                                    <i class="fas fa-print me-2"></i> Cetak
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Search Form (initial state) -->
                    <div class="search-card p-4 text-center animate-fade-in">
                        <div class="search-icon mx-auto mb-3">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 style="color: var(--dark-brown);">Cek Status Lamaran</h3>
                        <p class="text-muted mb-4">Masukkan email yang digunakan saat mendaftar untuk mengetahui status lamaran Anda</p>
                        
                        <form method="GET" class="mb-4">
                            <div class="mb-3">
                                <input type="email" name="email" class="form-control form-control-lg" 
                                       placeholder="example@email.com" required>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-envelope me-1"></i> Masukkan email yang Anda gunakan saat mendaftar
                                </small>
                            </div>
                            <button type="submit" class="btn btn-gold w-100">
                                <i class="fas fa-search me-2"></i> Cek Status
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-6">
                                <a href="apply.php" class="text-decoration-none">
                                    <i class="fas fa-paper-plane me-1" style="color: var(--gold-brown);"></i>
                                    <span style="color: var(--gold-brown); font-weight: 500;">Daftar Sekarang</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="karir.php" class="text-decoration-none">
                                    <i class="fas fa-home me-1" style="color: var(--gold-brown);"></i>
                                    <span style="color: var(--gold-brown); font-weight: 500;">Kembali</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer-career">
        <p>&copy; 2024 Loaz Industries. All rights reserved.</p>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-dismiss alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>