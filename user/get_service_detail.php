<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="text-center py-4 text-danger">Silakan login terlebih dahulu</div>';
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<div class="text-center py-4 text-danger">ID servis tidak valid</div>';
    exit();
}

// [FIX] Get service details with JOIN to technician properly
$stmt = $pdo->prepare("
    SELECT s.*, 
           COALESCE(s.status, 'pending') as safe_status,
           tech.id as technician_db_id,
           tu.name as technician_name, 
           tu.phone as technician_phone,
           tu.email as technician_email
    FROM services s 
    LEFT JOIN technicians tech ON s.technician_id = tech.id
    LEFT JOIN users tu ON tech.user_id = tu.id
    WHERE s.id = ? AND s.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$service = $stmt->fetch();

if (!$service) {
    echo '<div class="text-center py-4 text-danger">Data tidak ditemukan atau Anda tidak memiliki akses</div>';
    exit();
}

// [FIX] Get updates with safe status
$stmt = $pdo->prepare("
    SELECT su.*, COALESCE(su.status, 'pending') as safe_status 
    FROM service_updates su 
    WHERE su.service_id = ? 
    ORDER BY su.updated_at DESC
");
$stmt->execute([$id]);
$updates = $stmt->fetchAll();

// [FIX] Status badge mapping with colors
$status_config = [
    'pending' => ['class' => 'warning', 'text' => 'Menunggu', 'icon' => 'fa-clock'],
    'visit' => ['class' => 'info', 'text' => 'Kunjungan', 'icon' => 'fa-hand-peace'],
    'accepted' => ['class' => 'primary', 'text' => 'Diterima', 'icon' => 'fa-check-circle'],
    'repairing' => ['class' => 'warning', 'text' => 'Diperbaiki', 'icon' => 'fa-tools'],
    'done' => ['class' => 'success', 'text' => 'Selesai', 'icon' => 'fa-check-double']
];

// Get safe status
$current_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
$status_info = $status_config[$current_status] ?? ['class' => 'secondary', 'text' => ucfirst($current_status), 'icon' => 'fa-info-circle'];

// [FIX] Status text mapping for updates
$status_text_map = [
    'pending' => 'Menunggu',
    'visit' => 'Kunjungan',
    'accepted' => 'Diterima',
    'repairing' => 'Diperbaiki',
    'done' => 'Selesai'
];

// Calculate parts total
$used_parts = [];
$parts_total = 0;
if (!empty($service['used_parts'])) {
    $used_parts = json_decode($service['used_parts'], true);
    if (is_array($used_parts)) {
        foreach ($used_parts as $part) {
            $parts_total += ($part['price'] ?? 0) * ($part['quantity'] ?? 0);
        }
    }
}

$service_cost = $service['estimated_cost'] ?? 0;
$total_cost = $service_cost + $parts_total;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Servis - Loaz Industries</title>
    <style>
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cream);
        }
        
        .detail-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Service Info Card */
        .info-card {
            background: white;
            border-radius: 20px;
            border: 1px solid rgba(192, 133, 82, 0.15);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(75, 46, 43, 0.05);
        }
        
        .info-header {
            background: rgba(192, 133, 82, 0.05);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .info-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 1.1rem;
        }
        
        .info-header h5 i {
            color: var(--gold-brown);
            margin-right: 8px;
        }
        
        .info-body {
            padding: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            width: 130px;
            font-size: 0.75rem;
            color: var(--medium-brown);
            font-weight: 500;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
            font-size: 0.85rem;
            color: var(--dark-brown);
        }
        
        .info-value strong {
            color: var(--gold-brown);
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .status-info {
            background: rgba(23, 162, 184, 0.15);
            color: #17a2b8;
        }
        
        .status-primary {
            background: rgba(13, 110, 253, 0.15);
            color: #0d6efd;
        }
        
        .status-success {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .status-secondary {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        /* Cost Box */
        .cost-box {
            background: rgba(192, 133, 82, 0.05);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
        }
        
        .cost-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .cost-total {
            border-top: 1px solid rgba(192, 133, 82, 0.15);
            margin-top: 8px;
            padding-top: 8px;
            font-weight: 700;
            color: var(--gold-brown);
        }
        
        /* Technician Card */
        .technician-card {
            background: rgba(192, 133, 82, 0.05);
            border-radius: 12px;
            padding: 12px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .technician-avatar {
            width: 45px;
            height: 45px;
            background: var(--gold-brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .technician-avatar i {
            font-size: 1.3rem;
            color: white;
        }
        
        .technician-info {
            flex: 1;
        }
        
        .technician-name {
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 0.9rem;
        }
        
        .technician-contact {
            font-size: 0.7rem;
            color: var(--medium-brown);
        }
        
        /* Parts List */
        .parts-list {
            margin-top: 10px;
        }
        
        .part-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
            font-size: 0.8rem;
        }
        
        .part-item:last-child {
            border-bottom: none;
        }
        
        .part-name {
            flex: 2;
            color: var(--dark-brown);
        }
        
        .part-qty {
            flex: 1;
            text-align: center;
            color: var(--medium-brown);
        }
        
        .part-price {
            flex: 1;
            text-align: right;
            color: var(--medium-brown);
        }
        
        .part-subtotal {
            flex: 1;
            text-align: right;
            font-weight: 600;
            color: var(--gold-brown);
        }
        
        /* Timeline */
        .timeline {
            margin-top: 15px;
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .timeline-item:last-child {
            border-bottom: none;
        }
        
        .timeline-icon {
            width: 28px;
            height: 28px;
            background: rgba(192, 133, 82, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .timeline-icon i {
            font-size: 0.8rem;
            color: var(--gold-brown);
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-status {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--dark-brown);
        }
        
        .timeline-date {
            font-size: 0.65rem;
            color: var(--medium-brown);
            display: block;
            margin-bottom: 4px;
        }
        
        .timeline-note {
            font-size: 0.75rem;
            color: var(--medium-brown);
            margin-top: 4px;
        }
        
        /* Tags */
        .part-tag {
            display: inline-block;
            background: rgba(192, 133, 82, 0.15);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: var(--gold-brown);
            margin: 2px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--medium-brown);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        @media (max-width: 576px) {
            .detail-container {
                padding: 10px;
            }
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .part-item {
                flex-wrap: wrap;
            }
            
            .technician-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="detail-container">
        <!-- Service Info Card -->
        <div class="info-card">
            <div class="info-header">
                <h5><i class="fas fa-microchip"></i> Informasi Servis #<?php echo str_pad($service['id'], 6, '0', STR_PAD_LEFT); ?></h5>
            </div>
            <div class="info-body">
                <div class="info-row">
                    <div class="info-label">Device</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($service['device'] ?? '-'); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Masalah</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($service['problem'] ?? '-')); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tipe Servis</div>
                    <div class="info-value">
                        <?php if (($service['service_type'] ?? '') == 'onsite'): ?>
                            🏠 On-site Service (Teknisi datang ke lokasi)
                        <?php else: ?>
                            🚚 Pick-up Service (Device dijemput kurir)
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Tanggal Request</div>
                    <div class="info-value"><?php echo date('d F Y H:i', strtotime($service['created_at'] ?? 'now')); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $status_info['class']; ?>">
                            <i class="fas <?php echo $status_info['icon']; ?>"></i>
                            <?php echo $status_info['text']; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Technician Info Card -->
        <div class="info-card">
            <div class="info-header">
                <h5><i class="fas fa-user-cog"></i> Informasi Teknisi</h5>
            </div>
            <div class="info-body">
                <?php if (!empty($service['technician_name'])): ?>
                    <div class="technician-card">
                        <div class="technician-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="technician-info">
                            <div class="technician-name"><?php echo htmlspecialchars($service['technician_name']); ?></div>
                            <?php if (!empty($service['technician_phone'])): ?>
                                <div class="technician-contact">
                                    <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($service['technician_phone']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($service['technician_email'])): ?>
                                <div class="technician-contact">
                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($service['technician_email']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-clock"></i>
                        <p>Belum ada teknisi yang ditugaskan</p>
                        <small>Teknisi akan segera ditugaskan untuk servis ini</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Cost & Parts Card -->
        <?php if ($service_cost > 0 || $parts_total > 0 || count($used_parts) > 0): ?>
        <div class="info-card">
            <div class="info-header">
                <h5><i class="fas fa-receipt"></i> Rincian Biaya</h5>
            </div>
            <div class="info-body">
                <?php if ($service_cost > 0): ?>
                <div class="cost-row">
                    <span>Biaya Jasa Servis</span>
                    <span><?php echo formatCurrency($service_cost); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (count($used_parts) > 0): ?>
                    <div class="parts-list">
                        <div class="part-item">
                            <span class="part-name"><strong>Part</strong></span>
                            <span class="part-qty"><strong>Jml</strong></span>
                            <span class="part-price"><strong>Harga</strong></span>
                            <span class="part-subtotal"><strong>Subtotal</strong></span>
                        </div>
                        <?php foreach ($used_parts as $part): ?>
                        <div class="part-item">
                            <span class="part-name"><?php echo htmlspecialchars($part['name'] ?? '-'); ?></span>
                            <span class="part-qty"><?php echo $part['quantity'] ?? 0; ?>x</span>
                            <span class="part-price"><?php echo formatCurrency($part['price'] ?? 0); ?></span>
                            <span class="part-subtotal"><?php echo formatCurrency(($part['price'] ?? 0) * ($part['quantity'] ?? 0)); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="cost-row cost-total">
                            <span>Total Biaya Part</span>
                            <span><?php echo formatCurrency($parts_total); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($total_cost > 0): ?>
                <div class="cost-box">
                    <div class="cost-row" style="font-size: 1rem;">
                        <span class="fw-bold">Total Yang Harus Dibayar</span>
                        <span class="fw-bold" style="color: var(--gold-brown); font-size: 1.2rem;"><?php echo formatCurrency($total_cost); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Timeline Updates Card -->
        <div class="info-card">
            <div class="info-header">
                <h5><i class="fas fa-history"></i> Riwayat Update</h5>
            </div>
            <div class="info-body">
                <?php if (count($updates) > 0): ?>
                    <div class="timeline">
                        <?php foreach ($updates as $update): ?>
                            <?php 
                            $update_status = $update['safe_status'] ?? $update['status'] ?? 'pending';
                            $update_text = $status_text_map[$update_status] ?? ucfirst($update_status);
                            $update_icon = $status_config[$update_status]['icon'] ?? 'fa-check-circle';
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas <?php echo $update_icon; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <span class="timeline-date"><?php echo date('d F Y H:i', strtotime($update['updated_at'] ?? 'now')); ?></span>
                                    <div class="timeline-status"><?php echo $update_text; ?></div>
                                    <?php if (!empty($update['note'])): ?>
                                        <div class="timeline-note"><?php echo nl2br(htmlspecialchars($update['note'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <p>Belum ada update untuk servis ini</p>
                        <small>Status akan muncul setelah teknisi mulai mengerjakan</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>