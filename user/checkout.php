<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: order_part.php');
    exit();
}

// Get cart items
$cart_items = [];
$cart_total = 0;
$ids = array_keys($_SESSION['cart']);
if (!empty($ids)) {
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM parts WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $cart_parts = $stmt->fetchAll();
    
    foreach ($cart_parts as $part) {
        $qty = $_SESSION['cart'][$part['id']];
        $subtotal = $part['price'] * $qty;
        $cart_items[] = [
            'part' => $part,
            'quantity' => $qty,
            'subtotal' => $subtotal
        ];
        $cart_total += $subtotal;
    }
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = trim($_POST['shipping_address']);
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_postal = trim($_POST['shipping_postal']);
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    $shipping_option = $_POST['shipping_option'];
    
    // Calculate shipping cost
    if ($shipping_option == 'regular') {
        $shipping_cost = 15000;
    } elseif ($shipping_option == 'express') {
        $shipping_cost = 30000;
    } else {
        $shipping_cost = 0;
    }
    $total_with_shipping = $cart_total + $shipping_cost;
    
    if (empty($shipping_address) || empty($shipping_city) || empty($shipping_postal)) {
        $error = 'Alamat pengiriman harus diisi lengkap!';
    } else {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_price, status, shipping_address, shipping_city, shipping_postal, shipping_cost, notes, payment_method, created_at) 
                VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $total_with_shipping, 
                $shipping_address, 
                $shipping_city, 
                $shipping_postal, 
                $shipping_cost, 
                $notes, 
                $payment_method
            ]);
            $order_id = $pdo->lastInsertId();
            
            // Create order items
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, part_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$order_id, $item['part']['id'], $item['quantity'], $item['part']['price']]);
                
                // Update stock
                $stmt = $pdo->prepare("UPDATE parts SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['part']['id']]);
            }
            
            // Create transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (order_id, total_amount, payment_status, payment_method, created_at) 
                VALUES (?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([$order_id, $total_with_shipping, $payment_method]);
            $transaction_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Store order info in session for payment page
            $_SESSION['last_order'] = [
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
                'total' => $total_with_shipping,
                'payment_method' => $payment_method
            ];
            
            // Redirect to payment page
            header('Location: payment.php?order_id=' . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memproses pesanan: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-7">
            <!-- Page Header -->
            <h1 class="display-5 fw-light mb-4" style="color: var(--dark-brown);">Checkout</h1>
            
            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Checkout Form -->
            <form method="POST" id="checkoutForm">
                <!-- Shipping Information -->
                <div class="checkout-card rounded-4 mb-4">
                    <div class="checkout-card-header">
                        <i class="fas fa-truck me-2" style="color: var(--gold-brown);"></i>
                        Informasi Pengiriman
                    </div>
                    <div class="checkout-card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nama Penerima</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="shipping_address" class="form-control" rows="3" required placeholder="Jalan, RT/RW, Kelurahan, Kecamatan"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kota <span class="text-danger">*</span></label>
                                <input type="text" name="shipping_city" class="form-control" required value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kode Pos <span class="text-danger">*</span></label>
                                <input type="text" name="shipping_postal" class="form-control" required value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Method -->
                <div class="checkout-card rounded-4 mb-4">
                    <div class="checkout-card-header">
                        <i class="fas fa-shipping-fast me-2" style="color: var(--gold-brown);"></i>
                        Metode Pengiriman
                    </div>
                    <div class="checkout-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="shipping-option" data-cost="15000">
                                    <input type="radio" name="shipping_option" value="regular" id="regular" checked>
                                    <label for="regular" class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Regular</strong>
                                                <p class="small text-muted mb-0">Estimasi 3-5 hari</p>
                                            </div>
                                            <span class="fw-bold" style="color: var(--gold-brown);">Rp 15.000</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="shipping-option" data-cost="30000">
                                    <input type="radio" name="shipping_option" value="express" id="express">
                                    <label for="express" class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Express</strong>
                                                <p class="small text-muted mb-0">Estimasi 1-2 hari</p>
                                            </div>
                                            <span class="fw-bold" style="color: var(--gold-brown);">Rp 30.000</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="checkout-card rounded-4 mb-4">
                    <div class="checkout-card-header">
                        <i class="fas fa-credit-card me-2" style="color: var(--gold-brown);"></i>
                        Metode Pembayaran
                    </div>
                    <div class="checkout-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" checked>
                                    <label for="bank_transfer" class="w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-university fa-2x" style="color: var(--gold-brown);"></i>
                                            <div>
                                                <strong>Transfer Bank</strong>
                                                <p class="small text-muted mb-0">BCA, Mandiri, BRI, BNI</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="qris" id="qris">
                                    <label for="qris" class="w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-qrcode fa-2x" style="color: var(--gold-brown);"></i>
                                            <div>
                                                <strong>QRIS</strong>
                                                <p class="small text-muted mb-0">Scan menggunakan aplikasi bank</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="e_wallet" id="e_wallet">
                                    <label for="e_wallet" class="w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-wallet fa-2x" style="color: var(--gold-brown);"></i>
                                            <div>
                                                <strong>E-Wallet</strong>
                                                <p class="small text-muted mb-0">GoPay, OVO, Dana, LinkAja</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="cod" id="cod">
                                    <label for="cod" class="w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-hand-holding-usd fa-2x" style="color: var(--gold-brown);"></i>
                                            <div>
                                                <strong>COD (Bayar di Tempat)</strong>
                                                <p class="small text-muted mb-0">Tersedia untuk area tertentu</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notes -->
                <div class="checkout-card rounded-4 mb-4">
                    <div class="checkout-card-header">
                        <i class="fas fa-pen me-2" style="color: var(--gold-brown);"></i>
                        Catatan Pesanan
                    </div>
                    <div class="checkout-card-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan untuk pesanan Anda (opsional)..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="col-lg-5 mt-4 mt-lg-0">
            <div class="summary-card rounded-4 sticky-top" style="top: 80px;">
                <div class="summary-header">
                    <i class="fas fa-receipt me-2" style="color: var(--gold-brown);"></i>
                    Ringkasan Pesanan
                </div>
                <div class="summary-body">
                    <!-- Order Items -->
                    <div class="order-items mb-3" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <div>
                                    <span class="fw-medium"><?php echo htmlspecialchars($item['part']['name']); ?></span>
                                    <small class="text-muted d-block">x<?php echo $item['quantity']; ?></small>
                                </div>
                                <span class="fw-semibold"><?php echo formatCurrency($item['subtotal']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price Details -->
                    <div class="price-details">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal</span>
                            <span id="subtotal"><?php echo formatCurrency($cart_total); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ongkos Kirim</span>
                            <span id="shipping-cost"><?php echo formatCurrency(15000); ?></span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 mt-2 border-top">
                            <span class="fw-bold fs-5">Total</span>
                            <strong class="fs-4" style="color: var(--gold-brown);" id="total-amount"><?php echo formatCurrency($cart_total + 15000); ?></strong>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-3 mt-4">
                        <button type="submit" form="checkoutForm" class="btn btn-gold rounded-4 py-3">
                            <i class="fas fa-credit-card me-2"></i> Lanjut ke Pembayaran
                        </button>
                        <a href="cart.php" class="btn btn-outline-gold rounded-4 py-2">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Keranjang
                        </a>
                    </div>
                    
                    <!-- Secure Info -->
                    <div class="secure-info text-center mt-4 pt-3 border-top">
                        <i class="fas fa-shield-alt me-1" style="color: var(--gold-brown);"></i>
                        <small class="text-muted">Transaksi Anda aman dan terenkripsi</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .checkout-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .checkout-card-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .checkout-card-body {
        padding: 1.5rem;
    }
    
    .summary-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .summary-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .summary-body {
        padding: 1.5rem;
    }
    
    .shipping-option, .payment-option {
        border: 1.5px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .shipping-option:hover, .payment-option:hover {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.03);
    }
    
    .shipping-option input, .payment-option input {
        display: none;
    }
    
    .shipping-option label, .payment-option label {
        padding: 1rem;
        margin: 0;
        cursor: pointer;
    }
    
    .shipping-option:has(input:checked), .payment-option:has(input:checked) {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.08);
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
    
    .form-control, .form-select {
        border: 1.5px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
    }
    
    .order-items {
        scrollbar-width: thin;
    }
    
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.1) !important;
    }
    
    .border-top {
        border-top-color: rgba(192, 133, 82, 0.1) !important;
    }
    
    .sticky-top {
        position: sticky;
        top: 20px;
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }
</style>

<script>
// Update shipping cost and total when shipping option changes
document.querySelectorAll('input[name="shipping_option"]').forEach(radio => {
    radio.addEventListener('change', function() {
        let shippingCost = parseInt(this.closest('.shipping-option').dataset.cost);
        let subtotal = <?php echo $cart_total; ?>;
        let total = subtotal + shippingCost;
        
        document.getElementById('shipping-cost').innerHTML = formatRupiah(shippingCost);
        document.getElementById('total-amount').innerHTML = formatRupiah(total);
    });
});

function formatRupiah(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}
</script>

<?php include '../includes/footer.php'; ?>