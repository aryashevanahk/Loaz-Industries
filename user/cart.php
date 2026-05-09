<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$error = '';
$success = '';

// Update cart quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $has_error = false;
    foreach ($_POST['quantity'] as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;
        
        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            // Validate stock
            $stmt = $pdo->prepare("SELECT stock, name FROM parts WHERE id = ?");
            $stmt->execute([$id]);
            $part = $stmt->fetch();
            if ($part && $qty <= $part['stock']) {
                $_SESSION['cart'][$id] = $qty;
            } else {
                $has_error = true;
                $error = "Stok untuk " . htmlspecialchars($part['name'] ?? 'item') . " tidak mencukupi! Maksimal " . ($part['stock'] ?? 0) . " unit.";
            }
        }
    }
    if (!$has_error) {
        $success = "Keranjang berhasil diperbarui!";
    }
}

// Remove item
if (isset($_GET['remove'])) {
    $part_id = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$part_id])) {
        unset($_SESSION['cart'][$part_id]);
        $success = "Item berhasil dihapus dari keranjang!";
    }
    header('Location: cart.php');
    exit();
}

// Clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit();
}

// Get cart items
$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    try {
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
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan database. Silakan coba lagi.';
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Keranjang Belanja</h1>
                    <p class="text-muted">Review pesanan Anda sebelum checkout</p>
                </div>
                <?php if (!empty($cart_items)): ?>
                    <a href="?clear=1" class="btn btn-outline-danger rounded-4" onclick="return confirm('Hapus semua item dari keranjang?')">
                        <i class="fas fa-trash-alt me-2"></i> Kosongkan Keranjang
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($cart_items)): ?>
                <!-- Empty Cart -->
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                        <h3>Keranjang Belanja Kosong</h3>
                        <p class="text-muted">Yuk, mulai belanja part elektronik sekarang!</p>
                        <a href="order_part.php" class="btn btn-gold rounded-4 mt-3">
                            <i class="fas fa-shop me-2"></i> Mulai Belanja
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Cart Items -->
                <form method="POST" id="cartForm">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-cart mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4" style="width: 40%;">Produk</th>
                                            <th style="width: 15%;">Harga</th>
                                            <th style="width: 20%;">Jumlah</th>
                                            <th style="width: 15%;">Subtotal</th>
                                            <th style="width: 10%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <?php $part = $item['part']; ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <!-- [FIX] Tampilkan gambar part -->
                                                        <div class="cart-item-image me-3">
                                                            <?php if (!empty($part['image']) && file_exists('../uploads/parts/' . $part['image'])): ?>
                                                                <img src="../uploads/parts/<?php echo htmlspecialchars($part['image']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($part['name']); ?>"
                                                                     class="cart-img">
                                                            <?php else: ?>
                                                                <div class="cart-item-icon">
                                                                    <i class="fas fa-microchip fa-2x" style="color: var(--gold-brown);"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($part['name']); ?></h6>
                                                            <?php if (!empty($part['brand'])): ?>
                                                                <small class="text-muted"><?php echo htmlspecialchars($part['brand']); ?></small>
                                                            <?php endif; ?>
                                                            <div class="stock-info">
                                                                <small class="text-muted">Stok: <?php echo $part['stock']; ?> unit</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                 </a>
                                                <td>
                                                    <strong><?php echo formatCurrency($part['price']); ?></strong>
                                                 </a>
                                                <td>
                                                    <div class="input-group input-group-sm" style="width: 120px;">
                                                        <button class="btn btn-outline-secondary qty-minus" type="button" data-id="<?php echo $part['id']; ?>">-</button>
                                                        <input type="number" name="quantity[<?php echo $part['id']; ?>]" 
                                                               value="<?php echo $item['quantity']; ?>" 
                                                               min="0" 
                                                               max="<?php echo $part['stock']; ?>" 
                                                               class="form-control text-center qty-input" 
                                                               data-id="<?php echo $part['id']; ?>">
                                                        <button class="btn btn-outline-secondary qty-plus" type="button" data-id="<?php echo $part['id']; ?>">+</button>
                                                    </div>
                                                 </a>
                                                <td class="subtotal-col fw-bold" style="color: var(--gold-brown);">
                                                    <?php echo formatCurrency($item['subtotal']); ?>
                                                 </a>
                                                <td>
                                                    <a href="?remove=<?php echo $part['id']; ?>" class="text-danger" onclick="return confirm('Hapus item ini?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                 </a>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold ps-4">Total:</a>
                                            <td colspan="2" class="fw-bold fs-4" style="color: var(--gold-brown);">
                                                <?php echo formatCurrency($cart_total); ?>
                                             </a>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0 p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <a href="order_part.php" class="btn btn-outline-gold rounded-4">
                                    <i class="fas fa-arrow-left me-2"></i> Lanjutkan Belanja
                                </a>
                                <div>
                                    <button type="submit" name="update_cart" class="btn btn-outline-secondary rounded-4 me-2">
                                        <i class="fas fa-sync-alt me-2"></i> Update Keranjang
                                    </button>
                                    <a href="checkout.php" class="btn btn-gold rounded-4">
                                        <i class="fas fa-credit-card me-2"></i> Checkout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- Cart Summary Sidebar -->
        <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 80px;">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2" style="color: var(--gold-brown);"></i>Ringkasan Belanja</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Jumlah Item</span>
                            <strong><?php echo count($cart_items); ?> produk</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Item</span>
                            <strong><?php echo array_sum($_SESSION['cart']); ?> unit</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <strong><?php echo formatCurrency($cart_total); ?></strong>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <i class="fas fa-truck me-2" style="color: var(--gold-brown);"></i>
                        <strong>Pengiriman</strong>
                        <p class="small text-muted mb-0 mt-1">Estimasi pengiriman 2-3 hari setelah pembayaran</p>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-shield-alt me-2" style="color: var(--gold-brown);"></i>
                        <strong>Garansi</strong>
                        <p class="small text-muted mb-0 mt-1">Garansi 1 tahun untuk semua part original</p>
                    </div>
                    <div class="mb-3">
                        <i class="fas fa-credit-card me-2" style="color: var(--gold-brown);"></i>
                        <strong>Pembayaran</strong>
                        <p class="small text-muted mb-0 mt-1">Transfer Bank, QRIS, atau COD (tersedia untuk area tertentu)</p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-0">
                        <span class="fw-bold">Total Estimasi</span>
                        <strong class="fs-4" style="color: var(--gold-brown);"><?php echo formatCurrency($cart_total); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .cart-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid rgba(192, 133, 82, 0.15);
    }
    
    .cart-item-image {
        width: 60px;
        height: 60px;
        margin-right: 1rem;
        flex-shrink: 0;
    }
    
    .cart-item-icon {
        width: 60px;
        height: 60px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .cart-item-icon i {
        font-size: 1.8rem;
        color: var(--gold-brown);
    }
    
    .stock-info {
        font-size: 0.7rem;
        margin-top: 2px;
    }
    
    .table-cart th, .table-cart td {
        vertical-align: middle;
        padding: 1rem;
    }
    
    .qty-input {
        max-width: 60px;
        text-align: center;
    }
    
    .qty-input:focus {
        outline: none;
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 2px rgba(192, 133, 82, 0.2);
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
        color: white;
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
    
    .btn-outline-danger {
        border: 1.5px solid #dc3545;
        color: #dc3545;
        background: transparent;
        transition: all 0.3s ease;
    }
    
    .btn-outline-danger:hover {
        background: #dc3545;
        color: white;
    }
    
    .sticky-top {
        position: sticky;
        top: 20px;
    }
    
    .alert-success {
        background: rgba(192, 133, 82, 0.12);
        border: none;
        color: var(--gold-brown);
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }
    
    .price-col, .subtotal-col {
        white-space: nowrap;
    }
    
    @media (max-width: 768px) {
        .table-cart th, .table-cart td {
            padding: 0.75rem;
        }
        
        .cart-img, .cart-item-icon {
            width: 45px;
            height: 45px;
        }
        
        .cart-item-icon i {
            font-size: 1.2rem;
        }
        
        .qty-col .input-group {
            width: 100px !important;
        }
        
        .qty-input {
            max-width: 45px;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Quantity minus button
    $('.qty-minus').on('click', function() {
        let id = $(this).data('id');
        let input = $(`.qty-input[data-id="${id}"]`);
        let val = parseInt(input.val());
        let min = parseInt(input.attr('min')) || 1;
        if (val > min) {
            input.val(val - 1);
        }
    });
    
    // Quantity plus button
    $('.qty-plus').on('click', function() {
        let id = $(this).data('id');
        let input = $(`.qty-input[data-id="${id}"]`);
        let max = parseInt(input.attr('max'));
        let val = parseInt(input.val());
        if (val < max) {
            input.val(val + 1);
        } else {
            // Show warning if trying to exceed stock
            alert('Stok maksimal ' + max + ' unit!');
        }
    });
    
    // Validate quantity input
    $('.qty-input').on('change', function() {
        let max = parseInt($(this).attr('max'));
        let val = parseInt($(this).val());
        let min = parseInt($(this).attr('min')) || 1;
        
        if (isNaN(val) || val < min) {
            $(this).val(min);
        } else if (val > max) {
            $(this).val(max);
            alert('Stok maksimal ' + max + ' unit!');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>