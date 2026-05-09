<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Hanya ambil email dari URL, jangan ada redirect
$email = isset($_GET['email']) ? $_GET['email'] : '';

// Jika tidak ada email, redirect ke halaman apply
if (empty($email)) {
    header('Location: apply.php');
    exit();
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="success-card rounded-4 text-center p-5">
                <div class="success-icon mx-auto mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h2 class="mb-3" style="color: var(--dark-brown);">Lamaran Terkirim!</h2>
                <p class="text-muted mb-4">
                    Terima kasih telah melamar menjadi teknisi di Loaz Industries.
                </p>
                
                <div class="alert alert-info-custom rounded-4 mb-4 text-start">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Apa yang perlu Anda lakukan?</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <li>Tim HRD kami akan mereview lamaran Anda</li>
                        <li>Proses seleksi memakan waktu 1-3 hari kerja</li>
                        <li>Anda akan dihubungi via email jika lolos seleksi administrasi</li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <p class="mb-1"><strong>Email yang digunakan:</strong></p>
                    <p class="text-gold"><?php echo htmlspecialchars($email); ?></p>
                </div>
                
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="status.php?email=<?php echo urlencode($email); ?>" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-search me-2"></i> Cek Status Lamaran
                    </a>
                    <a href="karir.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-home me-2"></i> Kembali ke Karir
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .success-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
    }
    
    .success-icon {
        width: 80px;
        height: 80px;
        background: rgba(40, 167, 69, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .success-icon i {
        font-size: 3rem;
        color: #28a745;
    }
    
    .alert-info-custom {
        background: rgba(23, 162, 184, 0.08);
        border: 1px solid rgba(23, 162, 184, 0.2);
        border-radius: 16px;
        padding: 1rem;
        color: #17a2b8;
    }
    
    .text-gold {
        color: var(--gold-brown);
        font-weight: 500;
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
        border: 1.5px solid var(--gold-brown);
        color: var(--gold-brown);
        background: transparent;
        transition: all 0.3s ease;
    }
    
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
    }
</style>

<?php include '../includes/footer.php'; ?>