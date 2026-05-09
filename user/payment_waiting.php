<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Get service details
$stmt = $pdo->prepare("
    SELECT s.*, t.payment_status, t.payment_proof, u.name as technician_name
    FROM services s
    LEFT JOIN users u ON s.technician_id = u.id
    LEFT JOIN transactions t ON s.id = t.service_id
    WHERE s.id = ? AND s.user_id = ?
");
$stmt->execute([$service_id, $_SESSION['user_id']]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: my_services.php');
    exit();
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="waiting-card rounded-4 text-center">
                <div class="waiting-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <h2 class="mt-4 mb-2">Menunggu Konfirmasi Admin</h2>
                <p class="text-muted mb-3">Bukti pembayaran Anda sedang diverifikasi oleh admin.</p>
                
                <div class="alert alert-info-custom mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Proses verifikasi maksimal 1x24 jam
                </div>
                
                <div class="service-info mb-4">
                    <div class="info-row">
                        <span class="info-label">Servis</span>
                        <span class="info-value"><?php echo htmlspecialchars($service['device']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Teknisi</span>
                        <span class="info-value"><?php echo htmlspecialchars($service['technician_name'] ?? '-'); ?></span>
                    </div>
                    <?php if ($service['payment_proof']): ?>
                        <div class="info-row">
                            <span class="info-label">Bukti Bayar</span>
                            <span class="info-value">
                                <a href="../uploads/payment_proofs/<?php echo $service['payment_proof']; ?>" target="_blank" class="text-gold">
                                    <i class="fas fa-eye me-1"></i> Lihat Bukti
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-3 justify-content-center">
                    <a href="my_services.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-list me-2"></i> Servis Saya
                    </a>
                    <a href="dashboard.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                </div>
                
                <div class="mt-4 pt-3">
                    <small class="text-muted">
                        <i class="fas fa-sync-alt me-1"></i> Halaman akan otomatis refresh setiap 10 detik
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .waiting-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        padding: 2.5rem;
    }
    
    .waiting-icon {
        width: 100px;
        height: 100px;
        background: rgba(255, 193, 7, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .waiting-icon i {
        font-size: 3.5rem;
        color: #ffc107;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 0.6; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.1); }
        100% { opacity: 0.6; transform: scale(1); }
    }
    
    .alert-info-custom {
        background: rgba(23, 162, 184, 0.1);
        border-radius: 12px;
        padding: 0.75rem;
        color: #17a2b8;
    }
    
    .service-info {
        background: rgba(192, 133, 82, 0.05);
        border-radius: 16px;
        padding: 1rem;
        text-align: left;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        color: var(--medium-brown);
        font-size: 0.85rem;
    }
    
    .info-value {
        font-weight: 500;
        color: var(--dark-brown);
    }
    
    .text-gold {
        color: var(--gold-brown);
        text-decoration: none;
    }
    
    .text-gold:hover {
        text-decoration: underline;
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

<script>
    // Auto refresh setiap 10 detik
    setTimeout(function() {
        location.reload();
    }, 10000);
    
    // Cek status pembayaran via AJAX
    function checkPaymentStatus() {
        fetch('check_payment_status.php?service_id=<?php echo $service_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.payment_status === 'paid') {
                    window.location.href = 'payment_success.php?service_id=<?php echo $service_id; ?>';
                }
            })
            .catch(error => console.log('Error:', error));
    }
    
    // Cek setiap 5 detik
    setInterval(checkPaymentStatus, 5000);
</script>

<?php include '../includes/footer.php'; ?>