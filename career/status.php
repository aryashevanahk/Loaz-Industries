<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Career page - open for public, no login required
// Halaman ini hanya untuk menampilkan status berdasarkan email dari URL

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
            $error_message = 'Email tidak ditemukan dalam database kami.';
        }
    }
} else {
    // If no email parameter, redirect to check page
    header('Location: check.php');
    exit();
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="index.php" style="color: var(--gold-brown); text-decoration: none;">
                            <i class="fas fa-briefcase me-1"></i> Karir
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="check.php" style="color: var(--gold-brown); text-decoration: none;">
                            Cek Status
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Hasil Status</li>
                </ol>
            </nav>
            
            <?php if ($error_message): ?>
                <!-- Error Alert -->
                <div class="alert alert-warning alert-dismissible fade show rounded-4" role="alert" style="border-left: 4px solid #ffc107; background: rgba(255, 193, 7, 0.1);">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3" style="color: #ffc107;"></i>
                        <div>
                            <strong>Email Tidak Ditemukan!</strong><br>
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                
                <div class="text-center mt-4">
                    <a href="check.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Cek Status
                    </a>
                    <a href="apply.php" class="btn btn-outline-gold rounded-4 px-4 ms-2">
                        <i class="fas fa-paper-plane me-2"></i> Daftar Sekarang
                    </a>
                </div>
                
            <?php elseif ($application): ?>
                <!-- Status Result -->
                <div class="status-card rounded-4 p-4">
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
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($application['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Telepon</span>
                            <span class="detail-value"><?php echo htmlspecialchars($application['phone']); ?></span>
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
                        <a href="apply.php" class="btn btn-gold rounded-4 px-4">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Karir
                        </a>
                        <?php if ($application['status'] == 'approved'): ?>
                            <button class="btn btn-outline-gold rounded-4 px-4 ms-2" onclick="window.print();">
                                <i class="fas fa-print me-2"></i> Cetak
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .status-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 24px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(75, 46, 43, 0.05);
    }
    
    .status-card:hover {
        box-shadow: 0 4px 16px rgba(75, 46, 43, 0.08);
        transform: translateY(-4px);
    }
    
    .status-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, rgba(192, 133, 82, 0.1), rgba(140, 90, 60, 0.05));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .status-icon:hover {
        transform: scale(1.05);
        background: linear-gradient(135deg, rgba(192, 133, 82, 0.15), rgba(140, 90, 60, 0.08));
    }
    
    .status-icon i {
        font-size: 2.5rem;
        color: var(--gold-brown);
    }
    
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
    
    .btn-gold {
        background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
        color: white;
        border: none;
        transition: all 0.3s ease;
        font-weight: 600;
        letter-spacing: 0.3px;
        padding: 0.75rem 1.5rem;
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
        transition: all 0.3s ease;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
    }
    
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
        transform: translateY(-2px);
    }
    
    .alert-warning {
        border: none;
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.08), rgba(255, 193, 7, 0.03));
        border-left: 4px solid #ffc107;
        border-radius: 16px;
        padding: 1rem 1.25rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
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
        
        .status-icon {
            width: 60px;
            height: 60px;
        }
        
        .status-icon i {
            font-size: 1.8rem;
        }
    }
    
    /* Print styles */
    @media print {
        .navbar, .footer, .btn, .alert, .breadcrumb, .btn-outline-gold {
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
        
        .container {
            margin: 0;
            padding: 0;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>