<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="text-center py-4"><p class="text-muted">Silakan login</p></div>';
    exit();
}

$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
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
?>

<?php if (empty($cart_items)): ?>
    <div class="text-center py-4">
        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
        <p class="text-muted">Keranjang kosong</p>
        <a href="order_part.php" class="btn btn-sm btn-gold rounded-4 mt-2">
            <i class="fas fa-shop me-1"></i> Mulai Belanja
        </a>
    </div>
<?php else: ?>
    <div class="cart-items mb-3" style="max-height: 400px; overflow-y: auto;">
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="flex-grow-1">
                    <h6 class="mb-0"><?php echo htmlspecialchars($item['part']['name']); ?></h6>
                    <small class="text-muted">
                        <?php echo formatCurrency($item['part']['price']); ?> x <?php echo $item['quantity']; ?>
                    </small>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-gold"><?php echo formatCurrency($item['subtotal']); ?></div>
                    <button class="btn btn-sm btn-outline-danger remove-item mt-1" data-id="<?php echo $item['part']['id']; ?>">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="border-top pt-3">
        <div class="d-flex justify-content-between mb-3">
            <span class="fw-semibold">Total</span>
            <strong class="fs-5 text-gold"><?php echo formatCurrency($cart_total); ?></strong>
        </div>
        <div class="d-grid gap-2">
            <a href="cart.php" class="btn btn-outline-gold rounded-4">
                <i class="fas fa-edit me-2"></i> Kelola Keranjang
            </a>
            <a href="checkout.php" class="btn btn-gold rounded-4">
                <i class="fas fa-credit-card me-2"></i> Checkout
            </a>
        </div>
    </div>
<?php endif; ?>

<style>
    .text-gold { color: var(--gold-brown); }
    .btn-gold {
        background: var(--gold-brown);
        color: white;
        border: none;
    }
    .btn-gold:hover {
        background: var(--medium-brown);
    }
    .btn-outline-gold {
        border: 1px solid var(--gold-brown);
        color: var(--gold-brown);
        background: transparent;
    }
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
    }
    .cart-item {
        transition: all 0.3s ease;
    }
    .cart-item:hover {
        background: rgba(192, 133, 82, 0.05);
        padding-left: 5px;
    }
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.1) !important;
    }
</style>

<script>
$(document).ready(function() {
    $('.remove-item').on('click', function() {
        let btn = $(this);
        let partId = btn.data('id');
        
        $.ajax({
            url: 'remove_from_cart_ajax.php',
            method: 'POST',
            data: { part_id: partId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateCartSidebar();
                    updateCartBadge();
                    showNotification(response.message, 'success');
                }
            }
        });
    });
    
    function updateCartSidebar() {
        $.get('get_cart_ajax.php', function(data) {
            $('#cartContent').html(data);
            if ($('#cartContent').find('.cart-item').length > 0) {
                $('#cartSidebar').fadeIn();
            } else {
                $('#cartSidebar').fadeOut();
            }
        });
    }
    
    function updateCartBadge() {
        $.get('get_cart_count.php', function(data) {
            let badge = $('.cart-badge');
            if (data.count > 0) {
                if (badge.length) {
                    badge.text(data.count);
                } else {
                    $('.btn-outline-gold').append('<span class="cart-badge">' + data.count + '</span>');
                }
            } else {
                badge.remove();
            }
        }, 'json');
    }
    
    function showNotification(message, type) {
        // Reuse the same notification system
        let alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        
        let alert = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas ${icon} me-2"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('.container > .row').before(alert);
        
        setTimeout(function() {
            alert.alert('close');
        }, 3000);
    }
});
</script>