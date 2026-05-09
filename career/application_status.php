<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /loaz_industries/admin/dashboard.php');
    } elseif (isTechnician()) {
        header('Location: /loaz_industries/technician/dashboard.php');
    } else {
        header('Location: /loaz_industries/user/dashboard.php');
    }
    exit();
}

// Rate limiting - simple session-based
session_start();
$rate_limit_key = 'status_check_' . $_SERVER['REMOTE_ADDR'];
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key] : 0;

if ($rate_limit_count >= 10) {
    $error = 'Terlalu banyak percobaan. Silakan coba lagi setelah 1 jam.';
}

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$application = null;
$error = isset($error) ? $error : '';

if ($email && !$error) {
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        // Increment rate limit counter
        $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
        if ($rate_limit_count == 0) {
            $_SESSION[$rate_limit_key . '_time'] = time();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM technician_applications WHERE email = ? ORDER BY applied_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $application = $stmt->fetch();
        
        if (!$application) {
            $error = 'Email tidak ditemukan dalam database lamaran!';
        }
    }
}

// Function to get status badge (moved to separate function for reusability)
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Menunggu Verifikasi</span>',
        'approved' => '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Disetujui</span>',
        'rejected' => '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Ditolak</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

// Include header with proper navigation
include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="/loaz_industries/index.php" style="color: var(--gold-brown); text-decoration: none;">
                            <i class="fas fa-home me-1"></i> Beranda
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Cek Status Lamaran
                    </li>
                </ol>
            </nav>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($application): ?>
                <!-- Status Card -->
                <div class="status-card rounded-4">
                    <div class="text-center mb-4">
                        <div class="status-icon mx-auto mb-3">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="fw-light" style="color: var(--dark-brown);">Status Lamaran</h3>
                        <p class="text-muted small"><?php echo htmlspecialchars($application['email']); ?></p>
                    </div>
                    
                    <div class="text-center my-4">
                        <?php echo getStatusBadge($application['status']); ?>
                    </div>
                    
                    <div class="info-container mt-4">
                        <div class="info-row">
                            <span class="info-label">Nama Lengkap</span>
                            <span class="info-value fw-semibold"><?php echo htmlspecialchars($application['name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Spesialisasi</span>
                            <span class="info-value"><?php echo htmlspecialchars($application['specialty']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Pengalaman</span>
                            <span class="info-value"><?php echo $application['experience_years']; ?> tahun</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Daftar</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($application['applied_at'])); ?></span>
                        </div>
                        <?php if ($application['reviewed_at']): ?>
                        <div class="info-row">
                            <span class="info-label">Direview</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($application['reviewed_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($application['admin_note']): ?>
                    <div class="admin-note mt-4 p-3 rounded-3">
                        <i class="fas fa-comment-dots me-2" style="color: var(--gold-brown);"></i>
                        <small><strong>Catatan Admin:</strong></small>
                        <p class="small mb-0 mt-1"><?php echo nl2br(htmlspecialchars($application['admin_note'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['status'] == 'approved'): ?>
                    <div class="alert alert-success-custom mt-4">
                        <i class="fas fa-check-circle me-2"></i>
                        Selamat! Lamaran Anda telah disetujui. Silakan login menggunakan email dan password yang telah dikirim.
                    </div>
                    <?php elseif ($application['status'] == 'rejected'): ?>
                    <div class="alert alert-danger-custom mt-4">
                        <i class="fas fa-frown me-2"></i>
                        Mohon maaf, lamaran Anda belum dapat kami terima. Silakan coba lagi di lain kesempatan.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info-custom mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Lamaran Anda sedang dalam proses verifikasi. Kami akan menghubungi Anda melalui email.
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-3 justify-content-center mt-4">
                        <a href="/loaz_industries/index.php" class="btn btn-outline-gold rounded-4 px-4">
                            <i class="fas fa-home me-2"></i> Ke Beranda
                        </a>
                        <a href="register_technician.php" class="btn btn-gold rounded-4 px-4">
                            <i class="fas fa-pen me-2"></i> Daftar Lagi
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Search Box -->
                <div class="search-card rounded-4">
                    <div class="text-center mb-4">
                        <div class="search-icon mx-auto mb-3">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="fw-light" style="color: var(--dark-brown);">Cek Status Lamaran</h3>
                        <p class="text-muted">Masukkan email yang digunakan saat mendaftar</p>
                    </div>
                    
                    <form method="GET" class="mt-4">
                        <div class="mb-4">
                            <div class="input-group-custom">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="Email address" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 py-3 rounded-4">
                            <i class="fas fa-paper-plane me-2"></i> Cek Status
                        </button>
                    </form>
                    
                    <div class="text-center mt-4 pt-3">
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            Belum mendaftar? 
                            <a href="register_technician.php" style="color: var(--gold-brown); text-decoration: none;">
                                Daftar sekarang →
                            </a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .status-card,
    .search-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        padding: 2rem;
    }
    
    .status-icon,
    .search-icon {
        width: 70px;
        height: 70px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .status-icon i,
    .search-icon i {
        font-size: 2rem;
        color: var(--gold-brown);
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.5rem 1.2rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .status-pending {
        background: rgba(255, 193, 7, 0.15);
        color: #ffc107;
    }
    
    .status-approved {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    
    .status-rejected {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }
    
    .info-container {
        background: rgba(192, 133, 82, 0.03);
        border-radius: 16px;
        padding: 0.5rem 1rem;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 0;
        border-bottom: 1px solid rgba(192, 133, 82, 0.08);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 0.8rem;
        color: var(--medium-brown);
    }
    
    .info-value {
        font-size: 0.85rem;
        color: var(--dark-brown);
        text-align: right;
    }
    
    .admin-note {
        background: rgba(192, 133, 82, 0.05);
    }
    
    .input-group-custom {
        position: relative;
    }
    
    .input-group-custom i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gold-brown);
        font-size: 1rem;
        z-index: 2;
    }
    
    .input-group-custom .form-control {
        padding-left: 45px;
        border: 1.5px solid rgba(192, 133, 82, 0.2);
        border-radius: 16px;
        height: 52px;
    }
    
    .input-group-custom .form-control:focus {
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
    }
    
    .btn-gold {
        background: var(--gold-brown);
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    
    .btn-gold:hover {
        background: var(--medium-brown);
        transform: translateY(-2px);
    }
    
    .btn-outline-gold {
        background: transparent;
        border: 1.5px solid var(--gold-brown);
        color: var(--gold-brown);
        transition: all 0.3s ease;
    }
    
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
    }
    
    .alert-success-custom {
        background: rgba(40, 167, 69, 0.1);
        border: none;
        color: #28a745;
    }
    
    .alert-danger-custom {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }
    
    .alert-info-custom {
        background: rgba(23, 162, 184, 0.1);
        border: none;
        color: #17a2b8;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
</style>

<?php include '../includes/footer.php'; ?>