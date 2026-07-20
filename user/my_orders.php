<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Handle confirm order received (user confirms receipt)
if (isset($_GET['confirm'])) {
    $order_id = (int)$_GET['confirm'];
    
    // Check if order belongs to user and status is 'shipped'
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if ($order && $order['status'] == 'shipped') {
        // Update order status to 'completed'
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $confirm_message = '<div class="alert alert-success rounded-4"><i class="fas fa-check-circle me-2"></i>Pesanan berhasil dikonfirmasi! Terima kasih telah berbelanja di Loaz Industries.</div>';
        header('Location: my_orders.php?msg=' . urlencode($confirm_message));
        exit();
    } else {
        $confirm_message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>Pesanan tidak dapat dikonfirmasi.</div>';
        header('Location: my_orders.php?msg=' . urlencode($confirm_message));
        exit();
    }
}

// Get message from URL
$message_display = '';
if (isset($_GET['msg'])) {
    $message_display = urldecode($_GET['msg']);
}

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
           t.payment_method, 
           t.payment_status as transaction_status
    FROM orders o 
    LEFT JOIN transactions t ON o.id = t.order_id
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Pesanan Saya</h1>
                    <p class="text-muted">Lihat riwayat pesanan part elektronik Anda</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                    </a>
                    <a href="order_part.php" class="btn btn-gold rounded-4">
                        <i class="fas fa-shop me-2"></i> Belanja Lagi
                    </a>
                </div>
            </div>
            
            <?php if ($message_display): ?>
                <?php echo $message_display; ?>
            <?php endif; ?>
            
            <?php if (count($orders) == 0): ?>
                <!-- Empty Orders -->
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                        <h3>Belum Ada Pesanan</h3>
                        <p class="text-muted">Yuk, mulai belanja part elektronik sekarang!</p>
                        <div class="mt-3">
                            <a href="order_part.php" class="btn btn-gold rounded-4">
                                <i class="fas fa-shop me-2"></i> Mulai Belanja
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-gold rounded-4 ms-2">
                                <i class="fas fa-home me-2"></i> Ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Orders List -->
                <div class="row g-4">
                    <?php foreach ($orders as $order): ?>
                        <?php 
                        // [FIX] Status badge mapping
                        $status_badge = [
                            'pending' => 'warning',
                            'paid' => 'info',
                            'shipped' => 'primary',
                            'completed' => 'success'
                        ];
                        $status_text = [
                            'pending' => 'Menunggu Pembayaran',
                            'paid' => 'Dibayar',
                            'shipped' => 'Dikirim',
                            'completed' => 'Selesai'
                        ];
                        $status_color = $status_badge[$order['status']] ?? 'secondary';
                        $status_display = $status_text[$order['status']] ?? ucfirst($order['status']);
                        
                        // [FIX] Tentukan apakah tombol konfirmasi ditampilkan (hanya untuk status 'shipped')
                        $show_confirm = ($order['status'] == 'shipped');
                        ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-<?php echo $status_color; ?> px-3 py-2 rounded-pill">
                                                <?php echo $status_display; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Tanggal</span>
                                            <span><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Jumlah Item</span>
                                            <span><?php echo $order['item_count']; ?> produk</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Metode Pembayaran</span>
                                            <span>
                                                <?php 
                                                    $methods = [
                                                        'bank_transfer' => '🏦 Transfer Bank',
                                                        'qris' => '📱 QRIS',
                                                        'e_wallet' => '👛 E-Wallet',
                                                        'cod' => '💵 COD'
                                                    ];
                                                    echo $methods[$order['payment_method']] ?? '-';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Total</span>
                                            <strong class="fs-5" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price']); ?></strong>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-outline-gold rounded-4 flex-grow-1" 
                                                onclick="viewOrder(<?php echo $order['id']; ?>)" 
                                                data-bs-toggle="modal" data-bs-target="#orderModal">
                                            <i class="fas fa-eye me-2"></i> Detail
                                        </button>
                                        
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <a href="payment.php?order_id=<?php echo $order['id']; ?>" class="btn btn-gold rounded-4 flex-grow-1">
                                                <i class="fas fa-credit-card me-2"></i> Bayar
                                            </a>
                                        <?php elseif ($show_confirm): ?>
                                            <!-- [FIX] Tombol Konfirmasi Pesanan Diterima -->
                                            <a href="?confirm=<?php echo $order['id']; ?>" class="btn btn-success rounded-4 flex-grow-1" 
                                               onclick="return confirm('Konfirmasi bahwa Anda telah menerima pesanan ini?')">
                                                <i class="fas fa-check-double me-2"></i> Konfirmasi
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title">Detail Pesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-gold" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .text-gold { color: var(--gold-brown); }
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
    .btn-success {
        background: #28a745;
        color: white;
        border: none;
        transition: all 0.3s ease;
    }
    .btn-success:hover {
        background: #1e7e34;
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
    .bg-warning { background: #ffc107 !important; color: #000; }
    .bg-info { background: #17a2b8 !important; color: #fff; }
    .bg-primary { background: var(--gold-brown) !important; color: #fff; }
    .bg-success { background: #28a745 !important; color: #fff; }
    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(75, 46, 43, 0.1) !important;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function viewOrder(id) {
        $('#orderModalBody').html('<div class="text-center py-4"><div class="spinner-border text-gold" role="status"></div></div>');
        $.get('get_order_detail.php?id=' + id, function(data) {
            $('#orderModalBody').html(data);
        });
    }
</script>

<?php include '../includes/footer.php'; ?>