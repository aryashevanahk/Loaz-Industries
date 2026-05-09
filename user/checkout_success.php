<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, t.payment_method 
    FROM orders o 
    JOIN transactions t ON o.id = t.order_id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: order_part.php');
    exit();
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="success-card rounded-4 text-center">
                <div class="success-card-body p-5">
                    <!-- Success Icon -->
                    <div class="success-icon mx-auto mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    
                    <h2 class="fw-light mb-2" style="color: var(--dark-brown);">Pesanan Berhasil!</h2>
                    <p class="text-muted mb-4">Terima kasih telah berbelanja di Loaz Industries</p>
                    
                    <!-- Order Info -->
                    <div class="order-info p-4 rounded-4 mb-4 text-start">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Nomor Pesanan</small>
                                <p class="fw-bold mb-2">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Tanggal</small>
                                <p class="fw-bold mb-2"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total</small>
                                <p class="fw-bold mb-0" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price']); ?></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Metode Pembayaran</small>
                                <p class="fw-bold mb-0">
                                    <?php 
                                        $methods = [
                                            'bank_transfer' => 'Transfer Bank',
                                            'qris' => 'QRIS',
                                            'e_wallet' => 'E-Wallet',
                                            'cod' => 'COD'
                                        ];
                                        echo $methods[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="next-steps mb-4">
                        <h6 class="mb-3">Langkah Selanjutnya:</h6>
                        <?php if ($order['payment_method'] == 'cod'): ?>
                            <p class="small text-muted">✓ Pesanan akan diproses dan dikirim dalam 1x24 jam</p>
                            <p class="small text-muted">✓ Siapkan uang tunai untuk pembayaran COD</p>
                        <?php else: ?>
                            <p class="small text-muted">✓ Segera lakukan pembayaran sebelum batas waktu</p>
                            <p class="small text-muted">✓ Upload bukti pembayaran untuk mempercepat proses</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="payment.php?order_id=<?php echo $order['id']; ?>" class="btn btn-gold rounded-4 px-4">
                            <i class="fas fa-credit-card me-2"></i> Lanjutkan Pembayaran
                        </a>
                        <a href="my_orders.php" class="btn btn-outline-gold rounded-4 px-4">
                            <i class="fas fa-list me-2"></i> Lihat Pesanan Saya
                        </a>
                    </div>
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
    
    .order-info {
        background: rgba(192, 133, 82, 0.05);
        border: 1px solid rgba(192, 133, 82, 0.1);
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