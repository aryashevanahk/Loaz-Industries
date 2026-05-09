<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<div class="text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
            <p class="text-danger">Invalid service ID</p>
          </div>';
    exit();
}

try {
    // [FIX] Get service details with COALESCE untuk handle NULL status
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(s.status, 'pending') as safe_status,
               u.name as customer_name, 
               u.email as customer_email,
               u.phone as customer_phone,
               u.address as customer_address,
               tu.name as technician_name,
               tech.specialty as technician_specialty,
               tech.status as technician_status
        FROM services s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN technicians tech ON s.technician_id = tech.id
        LEFT JOIN users tu ON tech.user_id = tu.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        echo '<div class="text-center py-5">
                <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                <p class="text-muted">Service tidak ditemukan</p>
              </div>';
        exit();
    }
    
    // Get service updates dengan COALESCE
    $stmt = $pdo->prepare("
        SELECT *, COALESCE(status, 'pending') as safe_status 
        FROM service_updates 
        WHERE service_id = ? 
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$id]);
    $updates = $stmt->fetchAll();
    
    // Get used parts
    $used_parts = [];
    $parts_total = 0;
    if ($service['used_parts']) {
        $used_parts = json_decode($service['used_parts'], true);
        foreach ($used_parts as $part) {
            $parts_total += $part['price'] * $part['quantity'];
        }
    }
    
    // [FIX] Status badges mapping dengan nilai default
    $status_badges = [
        'pending' => 'status-pending',
        'visit' => 'status-accepted',
        'accepted' => 'status-accepted',
        'repairing' => 'status-repairing',
        'done' => 'status-done'
    ];
    
    // [FIX] Status labels mapping dengan nilai default
    $status_labels = [
        'pending' => 'Menunggu',
        'visit' => 'Kunjungan',
        'accepted' => 'Diterima',
        'repairing' => 'Diperbaiki',
        'done' => 'Selesai'
    ];
    
    // [FIX] Status icons mapping
    $status_icons = [
        'pending' => 'fa-clock',
        'visit' => 'fa-hand-peace',
        'accepted' => 'fa-check-circle',
        'repairing' => 'fa-tools',
        'done' => 'fa-check-double'
    ];
    
    $technician_status_labels = [
        'available' => 'Tersedia',
        'busy' => 'Sibuk'
    ];
    
    $technician_status_colors = [
        'available' => '#28a745',
        'busy' => '#dc3545'
    ];
    
    // [FIX] Get safe status value
    $current_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
    $status_display = $status_labels[$current_status] ?? ucfirst($current_status);
    $status_class = $status_badges[$current_status] ?? 'status-pending';
    $status_icon = $status_icons[$current_status] ?? 'fa-clock';
    
} catch (PDOException $e) {
    echo '<div class="text-center py-5">
            <i class="fas fa-database fa-3x text-danger mb-3"></i>
            <p class="text-danger">Terjadi kesalahan database: ' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
    exit();
}
?>

<div class="container-fluid p-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h5 class="mb-0" style="color: var(--dark-brown);">
                <i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>
                Detail Servis #<?php echo $service['id']; ?>
            </h5>
            <small class="text-muted">Informasi lengkap layanan servis</small>
        </div>
        <span class="status-badge <?php echo $status_class; ?>">
            <i class="fas <?php echo $status_icon; ?> me-1"></i>
            <?php echo $status_display; ?>
        </span>
    </div>
    
    <!-- Two Column Layout -->
    <div class="row">
        <!-- Left Column - Customer Info -->
        <div class="col-md-6">
            <div class="info-section mb-4">
                <div class="info-section-header">
                    <i class="fas fa-user me-2" style="color: var(--gold-brown);"></i>
                    Informasi Customer
                </div>
                <div class="info-section-body">
                    <div class="info-row">
                        <div class="info-label">Nama</div>
                        <div class="info-value"><?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($service['customer_email'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Telepon</div>
                        <div class="info-value">
                            <?php if (!empty($service['customer_phone'])): ?>
                                <i class="fas fa-phone-alt me-1" style="color: var(--gold-brown); font-size: 0.7rem;"></i>
                                <?php echo htmlspecialchars($service['customer_phone']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($service['customer_address'])): ?>
                        <div class="info-row">
                            <div class="info-label">Alamat</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($service['customer_address'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Service Info -->
        <div class="col-md-6">
            <div class="info-section mb-4">
                <div class="info-section-header">
                    <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
                    Informasi Servis
                </div>
                <div class="info-section-body">
                    <div class="info-row">
                        <div class="info-label">Device</div>
                        <div class="info-value fw-semibold"><?php echo htmlspecialchars($service['device'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipe Servis</div>
                        <div class="info-value">
                            <?php if (($service['service_type'] ?? '') == 'onsite'): ?>
                                <i class="fas fa-home me-1" style="color: var(--gold-brown);"></i> On-site Service
                            <?php else: ?>
                                <i class="fas fa-box me-1" style="color: var(--gold-brown);"></i> Pick-up Service
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tanggal Dibuat</div>
                        <div class="info-value">
                            <i class="fas fa-calendar-alt me-1" style="color: var(--gold-brown); font-size: 0.7rem;"></i>
                            <?php echo date('d/m/Y H:i', strtotime($service['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Technician Info -->
    <div class="info-section mb-4">
        <div class="info-section-header">
            <i class="fas fa-user-cog me-2" style="color: var(--gold-brown);"></i>
            Informasi Teknisi
        </div>
        <div class="info-section-body">
            <?php if (!empty($service['technician_name'])): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Nama Teknisi</div>
                            <div class="info-value">
                                <i class="fas fa-user-check me-1" style="color: var(--gold-brown);"></i>
                                <?php echo htmlspecialchars($service['technician_name']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Spesialisasi</div>
                            <div class="info-value"><?php echo htmlspecialchars($service['technician_specialty'] ?? '-'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="info-label">Status Teknisi</div>
                            <div class="info-value">
                                <span class="technician-status" style="color: <?php echo $technician_status_colors[$service['technician_status'] ?? 'available'] ?? '#6c757d'; ?>;">
                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                    <?php echo $technician_status_labels[$service['technician_status'] ?? 'available'] ?? ucfirst($service['technician_status'] ?? 'available'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">Belum ada teknisi yang ditugaskan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Problem Description -->
    <div class="info-section mb-4">
        <div class="info-section-header">
            <i class="fas fa-exclamation-triangle me-2" style="color: var(--gold-brown);"></i>
            Deskripsi Masalah
        </div>
        <div class="info-section-body">
            <div class="problem-box p-3 rounded-3">
                <i class="fas fa-quote-left me-2" style="color: var(--gold-brown); opacity: 0.5;"></i>
                <?php echo nl2br(htmlspecialchars($service['problem'] ?? '-')); ?>
            </div>
        </div>
    </div>
    
    <!-- Used Parts -->
    <?php if (count($used_parts) > 0): ?>
    <div class="info-section mb-4">
        <div class="info-section-header">
            <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
            Part yang Digunakan
        </div>
        <div class="info-section-body p-0">
            <div class="table-responsive">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>Jumlah</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($used_parts as $part): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($part['name'] ?? '-'); ?></td>
                            <td><?php echo $part['quantity'] ?? 0; ?> x</a>
                            <td><?php echo formatCurrency($part['price'] ?? 0); ?></a>
                            <td class="fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency(($part['price'] ?? 0) * ($part['quantity'] ?? 0)); ?></a>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total Part:</a>
                            <td class="fw-bold fs-5" style="color: var(--gold-brown);"><?php echo formatCurrency($parts_total); ?></a>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Cost Summary -->
    <div class="info-section mb-4">
        <div class="info-section-header">
            <i class="fas fa-receipt me-2" style="color: var(--gold-brown);"></i>
            Ringkasan Biaya
        </div>
        <div class="info-section-body">
            <div class="cost-summary p-3 rounded-3">
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Biaya Jasa Servis</span>
                            <span class="fw-semibold"><?php echo formatCurrency($service['estimated_cost'] ?? 0); ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Biaya Part</span>
                            <span class="fw-semibold"><?php echo formatCurrency($parts_total); ?></span>
                        </div>
                    </div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-5">Total Keseluruhan</span>
                    <span class="fw-bold fs-4" style="color: var(--gold-brown);">
                        <?php echo formatCurrency(($service['estimated_cost'] ?? 0) + $parts_total); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Service Updates Timeline -->
    <?php if (count($updates) > 0): ?>
    <div class="info-section">
        <div class="info-section-header">
            <i class="fas fa-history me-2" style="color: var(--gold-brown);"></i>
            Riwayat Update
        </div>
        <div class="info-section-body">
            <div class="timeline-container">
                <?php foreach ($updates as $update): ?>
                    <?php 
                    // [FIX] Get safe status for update
                    $update_status = $update['safe_status'] ?? $update['status'] ?? 'pending';
                    $update_status_display = $status_labels[$update_status] ?? ucfirst($update_status);
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-status <?php echo $update_status; ?>">
                                    <?php echo $update_status_display; ?>
                                </span>
                                <span class="timeline-date">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($update['updated_at'] ?? 'now')); ?>
                                </span>
                            </div>
                            <?php if (!empty($update['note'])): ?>
                                <div class="timeline-note">
                                    <i class="fas fa-comment me-1" style="color: var(--gold-brown); font-size: 0.7rem;"></i>
                                    <?php echo nl2br(htmlspecialchars($update['note'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($update['photo'])): ?>
                                <div class="timeline-photo mt-2">
                                    <a href="../uploads/service_photos/<?php echo $update['photo']; ?>" target="_blank" class="btn-proof">
                                        <i class="fas fa-image me-1"></i> Lihat Foto
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    :root {
        --cream: #FFF8F0;
        --gold-brown: #C08552;
        --medium-brown: #8C5A3C;
        --dark-brown: #4B2E2B;
    }
    
    .info-section {
        background: white;
        border-radius: 16px;
        border: 1px solid rgba(192, 133, 82, 0.1);
        overflow: hidden;
    }
    
    .info-section-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 0.8rem 1.2rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        font-size: 0.85rem;
    }
    
    .info-section-body {
        padding: 1rem 1.2rem;
    }
    
    .info-row {
        display: flex;
        padding: 0.4rem 0;
        border-bottom: 1px solid rgba(192, 133, 82, 0.05);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        width: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--medium-brown);
    }
    
    .info-value {
        flex: 1;
        font-size: 0.8rem;
        color: var(--dark-brown);
    }
    
    .problem-box {
        background: rgba(192, 133, 82, 0.05);
        border-left: 3px solid var(--gold-brown);
        font-size: 0.85rem;
        line-height: 1.5;
    }
    
    .cost-summary {
        background: rgba(192, 133, 82, 0.05);
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.85rem;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .status-pending {
        background: rgba(255, 193, 7, 0.12);
        color: #ffc107;
    }
    
    .status-accepted {
        background: rgba(23, 162, 184, 0.12);
        color: #17a2b8;
    }
    
    .status-repairing {
        background: rgba(192, 133, 82, 0.12);
        color: var(--gold-brown);
    }
    
    .status-done {
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
    }
    
    /* Detail Table */
    .detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .detail-table th {
        text-align: left;
        padding: 0.8rem 1rem;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--medium-brown);
        background: #fafafa;
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .detail-table td {
        padding: 0.7rem 1rem;
        font-size: 0.8rem;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.05);
    }
    
    .detail-table tbody tr:hover {
        background: rgba(192, 133, 82, 0.02);
    }
    
    /* Timeline */
    .timeline-container {
        position: relative;
        padding-left: 1.5rem;
    }
    
    .timeline-container::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: rgba(192, 133, 82, 0.2);
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }
    
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-dot {
        position: absolute;
        left: -1.5rem;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--gold-brown);
        border: 2px solid white;
        box-shadow: 0 0 0 2px rgba(192, 133, 82, 0.2);
    }
    
    .timeline-content {
        background: #fafafa;
        border-radius: 12px;
        padding: 0.8rem 1rem;
    }
    
    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    
    .timeline-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
    }
    
    .timeline-status.pending {
        background: rgba(255, 193, 7, 0.12);
        color: #ffc107;
    }
    
    .timeline-status.visit,
    .timeline-status.accepted {
        background: rgba(23, 162, 184, 0.12);
        color: #17a2b8;
    }
    
    .timeline-status.repairing {
        background: rgba(192, 133, 82, 0.12);
        color: var(--gold-brown);
    }
    
    .timeline-status.done {
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
    }
    
    .timeline-date {
        font-size: 0.65rem;
        color: var(--medium-brown);
    }
    
    .timeline-note {
        font-size: 0.75rem;
        color: var(--dark-brown);
        padding: 0.4rem 0;
        border-top: 1px solid rgba(192, 133, 82, 0.08);
        margin-top: 0.4rem;
    }
    
    .btn-proof {
        display: inline-block;
        background: transparent;
        border: 1px solid var(--gold-brown);
        color: var(--gold-brown);
        padding: 0.2rem 0.6rem;
        border-radius: 20px;
        font-size: 0.65rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .btn-proof:hover {
        background: var(--gold-brown);
        color: white;
    }
    
    .technician-status {
        display: inline-flex;
        align-items: center;
    }
    
    /* Border Bottom */
    .border-bottom {
        border-bottom-color: rgba(192, 133, 82, 0.15) !important;
    }
</style>