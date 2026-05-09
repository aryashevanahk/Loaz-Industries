<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_services = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE user_id = ? AND status = 'done'");
$stmt->execute([$user_id]);
$completed_services = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_orders = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_orders = $stmt->fetch()['total'] ?? 0;

// Get user's services with COALESCE to handle NULL status
$stmt = $pdo->prepare("
    SELECT s.*, COALESCE(s.status, 'pending') as safe_status 
    FROM services s 
    WHERE s.user_id = ? 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_services = $stmt->fetchAll();

// Get user's orders
$stmt = $pdo->prepare("
    SELECT o.*, COUNT(oi.id) as items 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// Get pending service count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_services = $stmt->fetch()['total'] ?? 0;

// [FIX] Status text mapping for display
$status_text = [
    'pending' => 'Menunggu',
    'visit' => 'Kunjungan',
    'accepted' => 'Diterima',
    'repairing' => 'Diperbaiki',
    'done' => 'Selesai'
];
?>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card p-4 rounded-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h1 class="display-5 fw-light mb-2" style="color: var(--dark-brown);">
                            Halo, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!
                        </h1>
                        <p class="text-muted mb-0">Selamat datang kembali di Loaz Industries. Kelola servis dan pesanan Anda di sini.</p>
                    </div>
                    <div class="mt-3 mt-md-0">
                        <div class="welcome-date p-3 rounded-3 text-center" style="background: rgba(192, 133, 82, 0.1);">
                            <i class="fas fa-calendar-alt me-2" style="color: var(--gold-brown);"></i>
                            <span style="color: var(--dark-brown);"><?php echo date('d F Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $total_services; ?></h3>
                        <p class="text-muted small mb-0">Total Servis</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tools fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $pending_services; ?></h3>
                        <p class="text-muted small mb-0">Servis Pending</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $total_orders; ?></h3>
                        <p class="text-muted small mb-0">Total Pesanan</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $completed_orders; ?></h3>
                        <p class="text-muted small mb-0">Pesanan Selesai</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Services & Orders -->
    <div class="row g-4">
        <!-- Recent Services -->
        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>Servis Terbaru
                    </h5>
                    <a href="my_services.php" class="btn-link">Lihat Semua →</a>
                </div>
                <div class="section-body">
                    <?php if (count($recent_services) > 0): ?>
                        <?php foreach ($recent_services as $service): ?>
                            <?php 
                            // [FIX] Use safe_status from query or default to 'pending'
                            $service_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                            $status_display = $status_text[$service_status] ?? ucfirst($service_status);
                            $status_class = '';
                            switch($service_status) {
                                case 'pending': $status_class = 'bg-secondary'; break;
                                case 'visit': $status_class = 'bg-info'; break;
                                case 'accepted': $status_class = 'bg-primary'; break;
                                case 'repairing': $status_class = 'bg-warning'; break;
                                case 'done': $status_class = 'bg-success'; break;
                                default: $status_class = 'bg-secondary';
                            }
                            ?>
                            <div class="service-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" style="color: var(--dark-brown);">
                                        <?php echo htmlspecialchars($service['device'] ?? '-'); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 50)); ?>
                                        <?php if (strlen($service['problem'] ?? '') > 50) echo '...'; ?>
                                    </small>
                                </div>
                                <div class="ms-3">
                                    <span class="badge <?php echo $status_class; ?> px-3 py-2 rounded-pill">
                                        <?php echo $status_display; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada servis</p>
                            <a href="request_service.php" class="btn btn-sm btn-gold rounded-4">
                                <i class="fas fa-plus me-1"></i> Request Servis
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-shopping-cart me-2" style="color: var(--gold-brown);"></i>Pesanan Terbaru
                    </h5>
                    <a href="my_orders.php" class="btn-link">Lihat Semua →</a>
                </div>
                <div class="section-body">
                    <?php if (count($recent_orders) > 0): ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <?php 
                            $order_status = $order['status'] ?? 'pending';
                            $order_status_class = '';
                            switch($order_status) {
                                case 'pending': $order_status_class = 'bg-warning'; break;
                                case 'paid': $order_status_class = 'bg-info'; break;
                                case 'shipped': $order_status_class = 'bg-primary'; break;
                                case 'completed': $order_status_class = 'bg-success'; break;
                                default: $order_status_class = 'bg-secondary';
                            }
                            $order_status_text = [
                                'pending' => 'Menunggu',
                                'paid' => 'Dibayar',
                                'shipped' => 'Dikirim',
                                'completed' => 'Selesai'
                            ];
                            $order_display = $order_status_text[$order_status] ?? ucfirst($order_status);
                            ?>
                            <div class="order-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <div class="fw-semibold" style="color: var(--dark-brown);">
                                        Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $order['items'] ?? 0; ?> item(s) - <?php echo formatCurrency($order['total_price'] ?? 0); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge <?php echo $order_status_class; ?> px-3 py-2 rounded-pill">
                                        <?php echo $order_display; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada pesanan</p>
                            <a href="order_part.php" class="btn btn-sm btn-gold rounded-4">
                                <i class="fas fa-shop me-1"></i> Mulai Belanja
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="quick-actions-card rounded-4 p-4">
                <h5 class="mb-3 text-center fw-semibold" style="color: var(--dark-brown);">
                    <i class="fas fa-bolt me-2" style="color: var(--gold-brown);"></i>Akses Cepat
                </h5>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="request_service.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-tools me-2"></i> Request Servis
                    </a>
                    <a href="order_part.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-microchip me-2"></i> Belanja Part
                    </a>
                    <a href="my_services.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-list me-2"></i> Servis Saya
                    </a>
                    <a href="my_orders.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-receipt me-2"></i> Pesanan Saya
                    </a>
                    <a href="chat.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-comments me-2"></i> Chat Teknisi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .welcome-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .stat-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        transition: all 0.3s ease;
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(75, 46, 43, 0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .section-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        padding: 1.25rem;
        border: 1px solid rgba(192, 133, 82, 0.1);
        height: 100%;
    }
    
    .section-header {
        border-bottom: 1px solid rgba(192, 133, 82, 0.15);
    }
    
    .btn-link {
        color: var(--gold-brown);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }
    
    .btn-link:hover {
        color: var(--medium-brown);
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
        color: white;
        transform: translateY(-2px);
    }
    
    .btn-outline-gold {
        background: transparent;
        border: 1.5px solid var(--gold-brown);
        color: var(--gold-brown);
        transition: all 0.3s ease;
    }
    
    .btn-outline-gold:hover {
        background: var(--gold-brown);
        color: white;
        transform: translateY(-2px);
    }
    
    .quick-actions-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .service-item, .order-item {
        transition: all 0.3s ease;
    }
    
    .service-item:hover, .order-item:hover {
        background: rgba(192, 133, 82, 0.03);
        padding-left: 10px;
    }
    
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.1) !important;
    }
    
    /* Badge Colors */
    .bg-secondary { background-color: #6c757d !important; color: white; }
    .bg-info { background-color: #17a2b8 !important; color: white; }
    .bg-primary { background-color: #0d6efd !important; color: white; }
    .bg-warning { background-color: #ffc107 !important; color: #000; }
    .bg-success { background-color: #28a745 !important; color: white; }
</style>

<?php include '../includes/footer.php'; ?>