<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Only admin can access this (called from admin panel)
// No need to add redirectIfNotAdmin() because it's called via AJAX from admin page

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<p class="text-center text-danger">Invalid order ID</p>';
    exit();
}

try {
    // Get order info
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email, u.phone, u.address,
               t.payment_method, t.payment_status, t.paid_at,
               s.id as service_id, s.device as service_device, s.status as service_status,
               s.estimated_cost as service_cost, s.used_parts
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN transactions t ON o.id = t.order_id
        LEFT JOIN services s ON o.id = s.order_id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo '<p class="text-center text-danger">Order tidak ditemukan</p>';
        exit();
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as part_name 
        FROM order_items oi 
        JOIN parts p ON oi.part_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    
    // Get service parts if exists
    $service_parts = [];
    $service_parts_total = 0;
    if ($order['used_parts']) {
        $service_parts = json_decode($order['used_parts'], true);
        foreach ($service_parts as $part) {
            $service_parts_total += $part['price'] * $part['quantity'];
        }
    }
    
    // Payment method labels
    $methods = [
        'bank_transfer' => '🏦 Transfer Bank',
        'qris' => '📱 QRIS',
        'e_wallet' => '👛 E-Wallet',
        'cod' => '💵 COD'
    ];
    
    // Status badges
    $status_badges = [
        'pending' => 'status-pending',
        'paid' => 'status-paid',
        'shipped' => 'status-shipped',
        'completed' => 'status-completed'
    ];
    
} catch (PDOException $e) {
    error_log("Error in get_order_details: " . $e->getMessage());
    echo '<p class="text-center text-danger">Terjadi kesalahan database</p>';
    exit();
}
?>

<div class="container-fluid">
    <!-- Order Header with Print Button -->
    <div class="d-flex justify-content-end mb-3">
        <button onclick="window.print()" class="btn btn-sm btn-outline-gold">
            <i class="fas fa-print me-1"></i> Print
        </button>
    </div>
    
    <!-- Customer Info -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="detail-row">
                <div class="detail-label">Order ID</div>
                <div class="detail-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Customer</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['email'] ?? '-'); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Phone</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['phone'] ?? '-'); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="detail-row">
                <div class="detail-label">Status Order</div>
                <div class="detail-value">
                    <span class="status-badge <?php echo $status_badges[$order['status']] ?? 'status-pending'; ?>">
                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Status</div>
                <div class="detail-value">
                    <span class="status-badge <?php echo ($order['payment_status'] == 'paid') ? 'status-paid' : 'status-pending'; ?>">
                        <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Method</div>
                <div class="detail-value"><?php echo $methods[$order['payment_method']] ?? '-'; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Order Date</div>
                <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'] ?? 'now')); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Service Info (if this order is from a service) -->
    <?php if ($order['service_id']): ?>
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-2"><i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>Informasi Servis</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-muted">Device</small>
                    <p class="mb-0"><?php echo htmlspecialchars($order['service_device'] ?? '-'); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-muted">Status Servis</small>
                    <p class="mb-0"><?php echo ucfirst($order['service_status'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
        
        <?php if ($order['service_cost']): ?>
        <div class="mt-2">
            <small class="text-muted">Biaya Jasa Servis</small>
            <p class="mb-0 fw-bold"><?php echo formatCurrency($order['service_cost']); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Service Parts (if any) -->
    <?php if (count($service_parts) > 0): ?>
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-2"><i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>Part dari Servis</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>Jumlah</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_parts as $part): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($part['name']); ?></td>
                        <td><?php echo $part['quantity']; ?> x</a>
                        <td><?php echo formatCurrency($part['price']); ?></a>
                        <td class="fw-bold"><?php echo formatCurrency($part['price'] * $part['quantity']); ?></a>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Total Part Servis:</a>
                        <td class="fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency($service_parts_total); ?></a>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Shipping Info -->
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-2"><i class="fas fa-truck me-2" style="color: var(--gold-brown);"></i>Informasi Pengiriman</h6>
        <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '-')); ?></p>
        <p class="mb-0 small"><?php echo htmlspecialchars($order['shipping_city'] ?? '-'); ?> - <?php echo htmlspecialchars($order['shipping_postal'] ?? '-'); ?></p>
        <?php if (($order['shipping_cost'] ?? 0) > 0): ?>
            <p class="mb-0 small mt-2"><strong>Ongkos Kirim:</strong> <?php echo formatCurrency($order['shipping_cost']); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Order Items -->
    <h6 class="fw-semibold mb-3"><i class="fas fa-box me-2" style="color: var(--gold-brown);"></i>Item Pesanan</h6>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead class="bg-light">
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                        <td><?php echo formatCurrency($item['price']); ?></td>
                        <td><?php echo $item['quantity']; ?> x</a>
                        <td><?php echo formatCurrency($item['quantity'] * $item['price']); ?></a>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">Tidak ada item</a>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Total</a>
                    <td class="fw-bold fs-5" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price'] ?? 0); ?></a>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php if ($order['notes']): ?>
        <div class="mt-3 p-3 bg-light rounded-4">
            <small class="text-muted">Catatan:</small>
            <p class="small mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
    .detail-row {
        display: flex;
        padding: 0.4rem 0;
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    .detail-label {
        width: 130px;
        font-weight: 500;
        color: var(--medium-brown);
        font-size: 0.8rem;
    }
    .detail-value {
        flex: 1;
        color: var(--dark-brown);
        font-size: 0.85rem;
    }
    .status-badge {
        display: inline-block;
        padding: 0.2rem 0.7rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .status-pending { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
    .status-paid { background: rgba(40, 167, 69, 0.15); color: #28a745; }
    .status-shipped { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }
    .status-completed { background: rgba(40, 167, 69, 0.15); color: #28a745; }
    .btn-outline-gold {
        background: transparent;
        border: 1px solid var(--gold-brown);
        color: var(--gold-brown);
        border-radius: 30px;
        padding: 0.3rem 0.8rem;
        font-size: 0.75rem;
        transition: all 0.3s ease;
    }
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
    }
    @media print {
        .btn-outline-gold {
            display: none;
        }
        body {
            background: white;
        }
        .bg-light {
            background: #f8f9fa !important;
            print-color-adjust: exact;
        }
    }
</style>