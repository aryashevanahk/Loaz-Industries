<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get order info
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email, u.phone, u.address
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    echo '<p class="text-center">Order tidak ditemukan</p>';
    exit();
}

// Get order items (parts)
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as part_name 
    FROM order_items oi 
    JOIN parts p ON oi.part_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Get service info if this order is from a service
$stmt = $pdo->prepare("
    SELECT s.*, t.name as technician_name 
    FROM services s 
    LEFT JOIN users t ON s.technician_id = t.id 
    WHERE s.order_id = ?
");
$stmt->execute([$id]);
$service = $stmt->fetch();

// Calculate totals
$parts_total = 0;
foreach ($items as $item) {
    $parts_total += $item['quantity'] * $item['price'];
}
$service_cost = $order['total_price'] - $parts_total;
?>

<div class="container-fluid">
    <!-- Order Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="detail-row">
                <div class="detail-label">Nomor Pesanan</div>
                <div class="detail-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tanggal Pesanan</div>
                <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Customer</div>
                <div class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value">
                    <span class="badge bg-<?php 
                        echo $order['status'] == 'pending' ? 'warning' : 
                            ($order['status'] == 'paid' ? 'info' : 
                            ($order['status'] == 'shipped' ? 'primary' : 'success')); 
                    ?> px-3 py-2 rounded-pill">
                        <?php 
                            $status_text = [
                                'pending' => 'Menunggu Pembayaran',
                                'paid' => 'Dibayar',
                                'shipped' => 'Dikirim',
                                'completed' => 'Selesai'
                            ];
                            echo $status_text[$order['status']] ?? ucfirst($order['status']);
                        ?>
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Total Pembayaran</div>
                <div class="detail-value fs-4 fw-bold" style="color: var(--gold-brown);">
                    <?php echo formatCurrency($order['total_price']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rincian Biaya -->
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-3"><i class="fas fa-receipt me-2" style="color: var(--gold-brown);"></i>Rincian Biaya</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                    <span>Biaya Jasa Servis</span>
                    <span class="fw-bold"><?php echo formatCurrency($service_cost); ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                    <span>Biaya Part</span>
                    <span class="fw-bold"><?php echo formatCurrency($parts_total); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Service Info -->
    <?php if ($service): ?>
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-2"><i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>Informasi Servis</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-muted">Device</small>
                    <p class="mb-0"><?php echo htmlspecialchars($service['device']); ?></p>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Teknisi</small>
                    <p class="mb-0"><?php echo htmlspecialchars($service['technician_name'] ?? '-'); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-2">
                    <small class="text-muted">Masalah</small>
                    <p class="mb-0"><?php echo htmlspecialchars(substr($service['problem'], 0, 100)); ?>...</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Shipping Info -->
    <div class="bg-light rounded-4 p-3 mb-4">
        <h6 class="fw-semibold mb-2"><i class="fas fa-truck me-2" style="color: var(--gold-brown);"></i>Informasi Pengiriman</h6>
        <p class="mb-1 small"><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '-')); ?></p>
        <p class="mb-0 small"><?php echo htmlspecialchars($order['shipping_city'] ?? '-'); ?> - <?php echo htmlspecialchars($order['shipping_postal'] ?? '-'); ?></p>
        <?php if ($order['shipping_cost'] > 0): ?>
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
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                    <td><?php echo formatCurrency($item['price']); ?></td>
                    <td><?php echo $item['quantity']; ?> x</a>
                    <td><?php echo formatCurrency($item['quantity'] * $item['price']); ?></a>
                </tr>
                <?php endforeach; ?>
                <?php if (count($items) == 0 && $service_cost > 0): ?>
                <tr>
                    <td colspan="3">Biaya Jasa Servis</a>
                    <td class="fw-bold"><?php echo formatCurrency($service_cost); ?></a>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end fw-bold">Total</a>
                    <td class="fw-bold fs-5" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price']); ?></a>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php if ($order['notes']): ?>
        <div class="mt-3 p-3 bg-light rounded-4">
            <small class="text-muted">Catatan:</small>
            <p class="small mb-0"><?php echo htmlspecialchars($order['notes']); ?></p>
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
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.2) !important;
    }
</style>