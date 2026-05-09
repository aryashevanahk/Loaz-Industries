<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="success-card rounded-4 text-center">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="mt-4 mb-2">Pembayaran Berhasil!</h2>
                <p class="text-muted mb-4">Terima kasih, pembayaran Anda telah dikonfirmasi.</p>
                
                <div class="alert alert-success-custom mb-4">
                    <i class="fas fa-check-circle me-2"></i>
                    Servis Anda telah selesai dan pembayaran lunas.
                </div>
                
                <div class="d-flex gap-3 justify-content-center">
                    <a href="my_services.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-list me-2"></i> Servis Saya
                    </a>
                    <a href="dashboard.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-home me-2"></i> Dashboard
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
        padding: 2.5rem;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: rgba(40, 167, 69, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .success-icon i {
        font-size: 3.5rem;
        color: #28a745;
    }
    
    .alert-success-custom {
        background: rgba(192, 133, 82, 0.12);
        border-radius: 12px;
        padding: 1rem;
        color: var(--gold-brown);
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