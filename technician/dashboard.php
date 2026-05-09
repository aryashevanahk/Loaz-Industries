<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

// Cek role technician
if ($_SESSION['role'] !== 'technician') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /loaz_industries/admin/dashboard.php');
    } else {
        header('Location: /loaz_industries/user/dashboard.php');
    }
    exit();
}

// Handle AJAX request for reviews (diletakkan di awal sebelum output HTML)
if (isset($_GET['ajax_get_reviews'])) {
    header('Content-Type: application/json');
    
    $technician_user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
    $stmt->execute([$technician_user_id]);
    $tech = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tech) {
        echo json_encode(['error' => 'Technician not found', 'reviews' => [], 'avg_rating' => 0, 'total_reviews' => 0]);
        exit();
    }
    
    $technician_id = $tech['id'];
    
    try {
        // Get all reviews for this technician
        $stmt = $pdo->prepare("
            SELECT r.*, u.name as customer_name, s.device
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN services s ON r.service_id = s.id
            WHERE r.technician_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$technician_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get average rating
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(AVG(rating), 0) as avg_rating, 
                COUNT(*) as total_reviews
            FROM reviews
            WHERE technician_id = ?
        ");
        $stmt->execute([$technician_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'reviews' => $reviews,
            'avg_rating' => round($stats['avg_rating'] ?? 0, 1),
            'total_reviews' => (int)($stats['total_reviews'] ?? 0),
            'success' => true
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'reviews' => [], 'avg_rating' => 0, 'total_reviews' => 0]);
    }
    exit();
}

$technician_user_id = $_SESSION['user_id'];

// Get technician data from both tables
$stmt = $pdo->prepare("
    SELECT t.*, u.name, u.email, u.phone, u.address, u.city, u.province, 
           u.postal_code, u.profile_photo, u.created_at as member_since
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.user_id = ?
");
$stmt->execute([$technician_user_id]);
$technician = $stmt->fetch();

if (!$technician) {
    // If no technician record, create one
    $stmt = $pdo->prepare("
        INSERT INTO technicians (user_id, specialty, status, created_at) 
        VALUES (?, 'Laptop & PC', 'available', NOW())
    ");
    $stmt->execute([$technician_user_id]);
    
    // Refresh data
    $stmt = $pdo->prepare("
        SELECT t.*, u.name, u.email, u.phone, u.address, u.city, u.province, 
               u.postal_code, u.profile_photo, u.created_at as member_since
        FROM technicians t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$technician_user_id]);
    $technician = $stmt->fetch();
}

$tech_db_id = $technician['id'];

// ==================== PENDING CONFIRMATION COUNT ====================
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_confirm 
    FROM transactions t
    JOIN services s ON t.service_id = s.id
    WHERE s.technician_id = ? AND t.payment_status = 'pending_confirmation'
");
$stmt->execute([$tech_db_id]);
$pending_confirm = $stmt->fetch()['pending_confirm'] ?? 0;

// ==================== STATISTICS ====================
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE technician_id = ?");
$stmt->execute([$tech_db_id]);
$total_services = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE technician_id = ? AND status = 'done'");
$stmt->execute([$tech_db_id]);
$completed_services = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE technician_id = ? AND status IN ('accepted', 'repairing')");
$stmt->execute([$tech_db_id]);
$ongoing_services = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE technician_id IS NULL AND status = 'pending'");
$stmt->execute([]);
$pending_services = $stmt->fetch()['total'] ?? 0;

// ==================== EARNINGS WITH FEE CALCULATION ====================
// Get total earnings from transactions (paid) - lebih akurat dengan fee
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(te.net_amount), 0) as total_net_earnings,
        COALESCE(SUM(te.amount), 0) as total_gross_earnings,
        COALESCE(SUM(te.fee_amount), 0) as total_fees,
        COUNT(*) as total_paid_transactions
    FROM technician_earnings te
    WHERE te.technician_id = ?
");
$stmt->execute([$tech_db_id]);
$earnings_stats = $stmt->fetch();

$total_net_earnings = $earnings_stats['total_net_earnings'] ?? 0;
$total_gross_earnings = $earnings_stats['total_gross_earnings'] ?? 0;
$total_fees = $earnings_stats['total_fees'] ?? 0;
$total_paid_transactions = $earnings_stats['total_paid_transactions'] ?? 0;

// Alternative: Get estimated total from services (untuk servis yang belum dibayar)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(estimated_cost), 0) as estimated_total 
    FROM services 
    WHERE technician_id = ? AND status = 'done' AND estimated_cost IS NOT NULL
");
$stmt->execute([$tech_db_id]);
$estimated_total = $stmt->fetch()['estimated_total'] ?? 0;

// ==================== RATINGS ====================
$stmt = $pdo->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE technician_id = ?
");
$stmt->execute([$tech_db_id]);
$ratings = $stmt->fetch();
$avg_rating = $ratings['avg_rating'] ?? 0;
$total_reviews = $ratings['total_reviews'] ?? 0;

// Get recent reviews for this technician
$stmt = $pdo->prepare("
    SELECT r.*, u.name as customer_name, s.device
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN services s ON r.service_id = s.id
    WHERE r.technician_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$tech_db_id]);
$recent_reviews = $stmt->fetchAll();

// ==================== RECENT SERVICES ====================
$stmt = $pdo->prepare("
    SELECT s.*, COALESCE(s.status, 'pending') as safe_status, u.name as customer_name, u.phone as customer_phone
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.technician_id = ? 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$stmt->execute([$tech_db_id]);
$recent_services = $stmt->fetchAll();

// ==================== AVAILABLE SERVICES ====================
$stmt = $pdo->prepare("
    SELECT s.*, u.name as customer_name, u.phone as customer_phone, u.address as customer_address
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.technician_id IS NULL AND s.status = 'pending'
    ORDER BY s.created_at ASC
    LIMIT 5
");
$stmt->execute([]);
$available_services = $stmt->fetchAll();

// ==================== MONTHLY EARNINGS CHART (with actual earnings from technician_earnings) ====================
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(te.created_at, '%Y-%m') as month,
        COALESCE(SUM(te.net_amount), 0) as net_earnings,
        COALESCE(SUM(te.amount), 0) as gross_earnings,
        COALESCE(SUM(te.fee_amount), 0) as total_fees,
        COUNT(*) as services_count
    FROM technician_earnings te
    WHERE te.technician_id = ?
    GROUP BY DATE_FORMAT(te.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$tech_db_id]);
$monthly_earnings = $stmt->fetchAll();

// If no earnings yet, show estimated from services
if (count($monthly_earnings) == 0) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COALESCE(SUM(estimated_cost), 0) as gross_earnings,
            0 as net_earnings,
            0 as total_fees,
            COUNT(*) as services_count
        FROM services 
        WHERE technician_id = ? AND status = 'done' AND estimated_cost IS NOT NULL
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$tech_db_id]);
    $monthly_earnings = $stmt->fetchAll();
}

// ==================== RECENT UPDATES ====================
$stmt = $pdo->prepare("
    SELECT su.*, s.device, u.name as customer_name
    FROM service_updates su
    JOIN services s ON su.service_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE s.technician_id = ?
    ORDER BY su.updated_at DESC
    LIMIT 5
");
$stmt->execute([$tech_db_id]);
$recent_updates = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card p-4 rounded-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h1 class="display-5 fw-light mb-2" style="color: var(--dark-brown);">
                            Halo, <?php echo htmlspecialchars($technician['name'] ?? 'Teknisi'); ?>!
                        </h1>
                        <p class="text-muted mb-0">Selamat datang di dashboard teknisi. Kelola servis dan jadwal Anda di sini.</p>
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
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $ongoing_services; ?></h3>
                        <p class="text-muted small mb-0">Servis Berjalan</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-spinner fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo $completed_services; ?></h3>
                        <p class="text-muted small mb-0">Servis Selesai</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card p-3 rounded-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold text-success"><?php echo formatCurrency($total_net_earnings); ?></h3>
                        <p class="text-muted small mb-0">Pendapatan Bersih</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave fa-2x" style="color: var(--gold-brown);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Rating & Status Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="rating-card p-3 rounded-4 h-100">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center">
                        <div class="text-center me-4">
                            <i class="fas fa-star fa-3x" style="color: #ffc107;"></i>
                            <div class="fs-1 fw-bold" style="color: var(--dark-brown);"><?php echo number_format($avg_rating, 1); ?></div>
                            <small class="text-muted">/ 5.0</small>
                        </div>
                        <div>
                            <p class="mb-0">Rating dari <?php echo $total_reviews; ?> ulasan</p>
                            <div class="mt-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($avg_rating) ? 'text-warning' : 'text-muted'; ?>" style="opacity: <?php echo $i <= round($avg_rating) ? '1' : '0.3'; ?>;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="status-card p-3 rounded-4 h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-circle me-2" style="color: <?php echo ($technician['status'] ?? 'available') == 'available' ? '#28a745' : '#dc3545'; ?>;"></i>
                        <span class="fw-semibold">Status: <?php echo ($technician['status'] ?? 'available') == 'available' ? 'Tersedia' : 'Sedang Sibuk'; ?></span>
                    </div>
                    <div>
                        <span class="badge bg-<?php echo ($technician['status'] ?? 'available') == 'available' ? 'success' : 'danger'; ?> px-3 py-2">
                            <?php echo ($technician['status'] ?? 'available') == 'available' ? 'Online' : 'Offline'; ?>
                        </span>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-microchip me-1"></i> Spesialisasi: <?php echo htmlspecialchars($technician['specialty'] ?? 'Laptop & PC'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reviews Section -->
    <?php if (count($recent_reviews) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-star me-2" style="color: #ffc107;"></i>Ulasan Terbaru
                    </h5>
                    <button class="btn-link view-reviews-btn">Lihat Semua →</button>
                </div>
                <div class="section-body">
                    <?php foreach ($recent_reviews as $review): ?>
                        <div class="review-item d-flex py-3 border-bottom">
                            <div class="review-avatar me-3">
                                <div class="avatar-circle-sm">
                                    <span><?php echo strtoupper(substr($review['customer_name'], 0, 1)); ?></span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($review['customer_name']); ?></div>
                                        <small class="text-muted">Servis: <?php echo htmlspecialchars($review['device']); ?></small>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>" style="font-size: 0.8rem;"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted ms-1"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                </div>
                                <?php if ($review['comment']): ?>
                                    <p class="small mb-0 mt-1 text-muted">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Available Services & Recent Services -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-clock me-2" style="color: var(--gold-brown);"></i>Servis Tersedia
                        <?php if ($pending_services > 0): ?>
                            <span class="badge bg-gold ms-2 rounded-pill"><?php echo $pending_services; ?></span>
                        <?php endif; ?>
                    </h5>
                    <a href="my_services.php?filter=pending" class="btn-link">Lihat Semua →</a>
                </div>
                <div class="section-body">
                    <?php if (count($available_services) > 0): ?>
                        <?php foreach ($available_services as $service): ?>
                            <div class="service-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold" style="color: var(--dark-brown);">
                                        <?php echo htmlspecialchars($service['device'] ?? ''); ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($service['customer_name'] ?? ''); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?>
                                    </small>
                                </div>
                                <div class="ms-3">
                                    <a href="update_status.php?take=<?php echo $service['id']; ?>" 
                                       class="btn btn-sm btn-gold rounded-4"
                                       onclick="return confirm('Ambil servis ini?')">
                                        <i class="fas fa-hand-paper me-1"></i> Ambil
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada servis tersedia</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-history me-2" style="color: var(--gold-brown);"></i>Servis Terbaru
                    </h5>
                    <a href="my_services.php" class="btn-link">Lihat Semua →</a>
                </div>
                <div class="section-body">
                    <?php if (count($recent_services) > 0): ?>
                        <?php foreach ($recent_services as $service): ?>
                            <?php 
                            // [FIX] Handle NULL status dengan default 'pending'
                            $service_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                            
                            $status_labels = [
                                'pending' => ['color' => 'warning', 'text' => 'Menunggu'],
                                'visit' => ['color' => 'info', 'text' => 'Kunjungan'],
                                'accepted' => ['color' => 'primary', 'text' => 'Diterima'],
                                'repairing' => ['color' => 'warning', 'text' => 'Diperbaiki'],
                                'done' => ['color' => 'success', 'text' => 'Selesai']
                            ];
                            $status = $status_labels[$service_status] ?? ['color' => 'secondary', 'text' => 'Pending'];
                            ?>
                            <div class="service-item d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <div class="fw-semibold" style="color: var(--dark-brown);">
                                        <?php echo htmlspecialchars($service['device'] ?? ''); ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($service['customer_name'] ?? ''); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i> <?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $status['color']; ?> px-3 py-2 rounded-pill">
                                        <?php echo $status['text']; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada servis yang ditugaskan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Updates & Monthly Earnings -->
    <div class="row g-4 mt-2">
        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-bell me-2" style="color: var(--gold-brown);"></i>Aktivitas Terbaru
                    </h5>
                </div>
                <div class="section-body">
                    <?php if (count($recent_updates) > 0): ?>
                        <?php foreach ($recent_updates as $update): ?>
                            <?php 
                            // [FIX] Handle NULL status di update
                            $update_status = $update['status'] ?? 'pending';
                            $update_status_text = [
                                'pending' => 'Menunggu',
                                'visit' => 'Kunjungan',
                                'accepted' => 'Diterima',
                                'repairing' => 'Diperbaiki',
                                'done' => 'Selesai'
                            ];
                            $status_display = $update_status_text[$update_status] ?? ucfirst($update_status);
                            ?>
                            <div class="update-item d-flex py-2 border-bottom">
                                <div class="update-icon me-3">
                                    <i class="fas fa-check-circle" style="color: var(--gold-brown);"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($update['device'] ?? '-'); ?></div>
                                    <small class="text-muted">
                                        Status berubah menjadi: 
                                        <strong><?php echo $status_display; ?></strong>
                                    </small>
                                    <br>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($update['updated_at'] ?? 'now')); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-card rounded-4">
                <div class="section-header d-flex justify-content-between align-items-center mb-3 pb-2">
                    <h5 class="mb-0 fw-semibold" style="color: var(--dark-brown);">
                        <i class="fas fa-chart-line me-2" style="color: var(--gold-brown);"></i>Pendapatan per Bulan
                    </h5>
                    <a href="earnings.php" class="btn-link">Detail →</a>
                </div>
                <div class="section-body">
                    <?php if (count($monthly_earnings) > 0): ?>
                        <?php foreach ($monthly_earnings as $month): ?>
                            <div class="earning-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="fw-semibold">
                                        <?php 
                                            $date = DateTime::createFromFormat('Y-m', $month['month']);
                                            echo $date ? $date->format('F Y') : $month['month'];
                                        ?>
                                    </small>
                                    <div>
                                        <small class="text-muted">Gross: <?php echo formatCurrency($month['gross_earnings']); ?></small>
                                        <small class="text-success ms-2">Net: <?php echo formatCurrency($month['net_earnings'] > 0 ? $month['net_earnings'] : $month['gross_earnings']); ?></small>
                                    </div>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                        $max_earning = 0;
                                        foreach ($monthly_earnings as $me) {
                                            $earning_value = $me['net_earnings'] > 0 ? $me['net_earnings'] : $me['gross_earnings'];
                                            if ($earning_value > $max_earning) $max_earning = $earning_value;
                                        }
                                        $current_value = $month['net_earnings'] > 0 ? $month['net_earnings'] : $month['gross_earnings'];
                                        $percentage = $max_earning > 0 ? ($current_value / $max_earning) * 100 : 0;
                                    ?>
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%; background: var(--gold-brown);"
                                         aria-valuenow="<?php echo $current_value; ?>" 
                                         aria-valuemin="0" aria-valuemax="<?php echo $max_earning; ?>">
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo $month['services_count']; ?> servis selesai</small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-simple fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada data pendapatan</p>
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
                    <a href="my_services.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-list me-2"></i> Servis Saya
                    </a>
                    <a href="update_status.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-sync-alt me-2"></i> Update Status
                    </a>
                    <a href="earnings.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-money-bill-wave me-2"></i> Riwayat Pendapatan
                    </a>
                    <a href="confirm_payment.php" class="btn btn-outline-gold rounded-4 px-4 position-relative">
                        <i class="fas fa-check-double me-2"></i> Konfirmasi Pembayaran
                        <?php if ($pending_confirm > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $pending_confirm; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="chat.php" class="btn btn-outline-gold rounded-4 px-4">
                        <i class="fas fa-comments me-2"></i> Chat Client
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reviews Modal -->
<div class="modal fade" id="reviewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-star me-2"></i> Semua Ulasan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="reviewsModalContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-gold" role="status"></div>
                    <p class="mt-2">Memuat ulasan...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Styles tetap sama seperti sebelumnya */
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
    
    .fee-info-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .rating-card, .status-card {
        background: white;
        box-shadow: 0 4px 12px rgba(75, 46, 43, 0.06);
        border: 1px solid rgba(192, 133, 82, 0.1);
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
        cursor: pointer;
        background: none;
        border: none;
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
    
    .service-item, .update-item, .earning-item, .review-item {
        transition: all 0.3s ease;
    }
    
    .service-item:hover, .update-item:hover, .review-item:hover {
        background: rgba(192, 133, 82, 0.03);
        padding-left: 10px;
    }
    
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.1) !important;
    }
    
    .progress {
        background: rgba(192, 133, 82, 0.1);
        border-radius: 4px;
    }
    
    .progress-bar {
        border-radius: 4px;
    }
    
    .bg-gold {
        background: var(--gold-brown);
    }
    
    .avatar-circle-sm {
        width: 40px;
        height: 40px;
        background: var(--gold-brown);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-circle-sm span {
        color: white;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .review-rating i {
        font-size: 0.7rem;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // View all reviews - menggunakan AJAX internal
    $(document).ready(function() {
        $('.view-reviews-btn').on('click', function() {
            // Reset modal content
            $('#reviewsModalContent').html('<div class="text-center py-4"><div class="spinner-border text-gold" role="status"></div><p class="mt-2">Memuat ulasan...</p></div>');
            
            // Load data via AJAX ke halaman yang sama
            $.ajax({
                url: window.location.href + '?ajax_get_reviews=1',
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(data) {
                    if (data.error) {
                        $('#reviewsModalContent').html(`
                            <div class="text-center py-5 text-danger">
                                <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                                <h5>Gagal Memuat Data</h5>
                                <p>${escapeHtml(data.error)}</p>
                                <button class="btn btn-sm btn-outline-gold mt-3" onclick="location.reload()">Refresh Halaman</button>
                            </div>
                        `);
                    } else if (data.reviews && data.reviews.length > 0) {
                        var html = `
                            <div class="text-center mb-4">
                                <div class="d-inline-block p-3 bg-light rounded-4">
                                    <h4 class="mb-1">Rating Anda</h4>
                                    <div class="rating-stats">
                                        <div class="rating-stars-large mb-2">
                        `;
                        
                        var avgRating = parseFloat(data.avg_rating) || 0;
                        var fullStars = Math.floor(avgRating);
                        var hasHalfStar = (avgRating - fullStars) >= 0.5;
                        
                        for (var i = 1; i <= fullStars; i++) {
                            html += '<i class="fas fa-star text-warning"></i>';
                        }
                        if (hasHalfStar) {
                            html += '<i class="fas fa-star-half-alt text-warning"></i>';
                        }
                        for (var i = fullStars + (hasHalfStar ? 1 : 0); i < 5; i++) {
                            html += '<i class="far fa-star text-muted"></i>';
                        }
                        
                        html += `
                                        </div>
                                        <p class="mb-0"><strong>${avgRating.toFixed(1)}</strong> dari 5 (${data.total_reviews} ulasan)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="reviews-list">
                        `;
                        
                        data.reviews.forEach(function(review) {
                            var reviewStars = '';
                            for (var i = 1; i <= 5; i++) {
                                if (i <= review.rating) {
                                    reviewStars += '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    reviewStars += '<i class="far fa-star text-muted"></i>';
                                }
                            }
                            
                            html += `
                                <div class="review-item d-flex py-3 border-bottom">
                                    <div class="review-avatar me-3">
                                        <div class="avatar-circle-sm">
                                            <span>${escapeHtml(review.customer_name.charAt(0))}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                                            <div>
                                                <div class="fw-semibold">${escapeHtml(review.customer_name)}</div>
                                                <small class="text-muted">Servis: ${escapeHtml(review.device)}</small>
                                            </div>
                                            <div class="review-rating">
                                                ${reviewStars}
                                                <small class="text-muted ms-1">${new Date(review.created_at).toLocaleDateString('id-ID')}</small>
                                            </div>
                                        </div>
                                        ${review.comment ? `<p class="small mb-0 mt-1 text-muted">"${escapeHtml(review.comment)}"</p>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += `</div>`;
                        $('#reviewsModalContent').html(html);
                    } else {
                        $('#reviewsModalContent').html(`
                            <div class="text-center py-5">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <h5>Belum Ada Ulasan</h5>
                                <p class="text-muted">Anda belum menerima ulasan dari pelanggan.</p>
                                <small class="text-muted">Ulasan akan muncul setelah pelanggan memberikan rating pada servis yang selesai.</small>
                            </div>
                        `);
                    }
                    $('#reviewsModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $('#reviewsModalContent').html(`
                        <div class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                            <h5>Gagal Memuat Data</h5>
                            <p>Terjadi kesalahan saat memuat data. Silakan coba lagi.</p>
                            <p class="small text-muted">Status: ${status}</p>
                            <button class="btn btn-sm btn-outline-gold mt-3" onclick="location.reload()">Refresh Halaman</button>
                        </div>
                    `);
                    $('#reviewsModal').modal('show');
                }
            });
        });
    });
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
</script>

<?php include '../includes/footer.php'; ?>