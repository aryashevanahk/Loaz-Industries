<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if ($_SESSION['role'] !== 'technician') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /loaz_industries/admin/dashboard.php');
    } else {
        header('Location: /loaz_industries/user/dashboard.php');
    }
    exit();
}

$technician_id = $_SESSION['user_id'];

// Get technician's database id from technicians table
$stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
$stmt->execute([$technician_id]);
$tech = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tech) {
    $stmt = $pdo->prepare("SELECT DISTINCT technician_id FROM services WHERE technician_id = ?");
    $stmt->execute([$technician_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $tech_db_id = $technician_id;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO technicians (user_id, specialty, status, created_at) 
            VALUES (?, 'Laptop & PC', 'available', NOW())
        ");
        $stmt->execute([$technician_id]);
        
        $stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
        $stmt->execute([$technician_id]);
        $tech = $stmt->fetch(PDO::FETCH_ASSOC);
        $tech_db_id = $tech['id'];
    }
} else {
    $tech_db_id = $tech['id'];
}

// Handle cancel service - untuk status 'visit'
if (isset($_GET['cancel'])) {
    $service_id = (int)$_GET['cancel'];
    
    $stmt = $pdo->prepare("SELECT status, technician_id FROM services WHERE id = ? AND technician_id = ?");
    $stmt->execute([$service_id, $tech_db_id]);
    $service = $stmt->fetch();
    
    if ($service && $service['status'] == 'visit') {
        $stmt = $pdo->prepare("UPDATE services SET status = 'pending', technician_id = NULL WHERE id = ?");
        $stmt->execute([$service_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO service_updates (service_id, status, note) 
            VALUES (?, 'pending', 'Teknisi membatalkan servis saat tahap kunjungan')
        ");
        $stmt->execute([$service_id]);
        
        $cancel_message = '<div class="alert alert-info rounded-4"><i class="fas fa-info-circle me-2"></i>Servis berhasil dibatalkan. Servis akan dikembalikan ke antrian.</div>';
        header('Location: my_services.php?msg=' . urlencode($cancel_message));
        exit();
    } else {
        $cancel_message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>Servis tidak dapat dibatalkan karena sudah dalam proses pengerjaan.</div>';
        header('Location: my_services.php?msg=' . urlencode($cancel_message));
        exit();
    }
}

// Handle take service - Ubah menjadi status 'visit' (Kunjungan)
if (isset($_GET['take'])) {
    $service_id = (int)$_GET['take'];
    
    $stmt = $pdo->prepare("
        UPDATE services 
        SET technician_id = ?, status = 'visit' 
        WHERE id = ? AND (technician_id IS NULL OR technician_id = 0) AND status = 'pending'
    ");
    if ($stmt->execute([$tech_db_id, $service_id])) {
        $stmt = $pdo->prepare("
            INSERT INTO service_updates (service_id, status, note) 
            VALUES (?, 'visit', 'Teknisi melakukan kunjungan dan siap mengambil servis')
        ");
        $stmt->execute([$service_id]);
        
        $success_message = '<div class="alert alert-success rounded-4"><i class="fas fa-check-circle me-2"></i>Servis berhasil diambil! Silakan update status ke "Diterima" setelah selesai kunjungan.</div>';
        header('Location: my_services.php?msg=' . urlencode($success_message));
        exit();
    } else {
        $error_message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>Gagal mengambil servis!</div>';
        header('Location: my_services.php?msg=' . urlencode($error_message));
        exit();
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// [FIX] Build query for services assigned to this technician dengan COALESCE untuk handle NULL status
$query = "
    SELECT s.*, 
           COALESCE(s.status, 'pending') as safe_status,
           u.name as customer_name, 
           u.phone as customer_phone, 
           u.address as customer_address
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.technician_id = ? OR s.technician_id = ?
";
$params = [$tech_db_id, $technician_id];
$params = array_unique($params);

// Apply filter - menggunakan safe_status untuk filter
if ($filter == 'pending') {
    $query .= " AND COALESCE(s.status, 'pending') = 'pending'";
} elseif ($filter == 'visit') {
    $query .= " AND s.status = 'visit'";
} elseif ($filter == 'accepted') {
    $query .= " AND s.status = 'accepted'";
} elseif ($filter == 'repairing') {
    $query .= " AND s.status = 'repairing'";
} elseif ($filter == 'done') {
    $query .= " AND s.status = 'done'";
}

$query .= " ORDER BY FIELD(COALESCE(s.status, 'pending'), 'pending', 'visit', 'accepted', 'repairing', 'done'), s.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// [FIX] Get available services - servis yang belum ada teknisi
$stmt = $pdo->prepare("
    SELECT s.*, 
           COALESCE(s.status, 'pending') as safe_status,
           u.name as customer_name, 
           u.phone as customer_phone
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    WHERE (s.technician_id IS NULL OR s.technician_id = 0) AND COALESCE(s.status, 'pending') = 'pending'
    ORDER BY s.created_at ASC
");
$stmt->execute([]);
$available_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// [FIX] Count by status for this technician - menggunakan COALESCE
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN COALESCE(status, 'pending') = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'visit' THEN 1 END) as visit,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted,
        COUNT(CASE WHEN status = 'repairing' THEN 1 END) as repairing,
        COUNT(CASE WHEN status = 'done' THEN 1 END) as done
    FROM services 
    WHERE technician_id = ? OR technician_id = ?
");
$stmt->execute([$tech_db_id, $technician_id]);
$counts = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$counts) {
    $counts = ['pending' => 0, 'visit' => 0, 'accepted' => 0, 'repairing' => 0, 'done' => 0];
}

// Get message from URL
$message_display = '';
if (isset($_GET['msg'])) {
    $message_display = urldecode($_GET['msg']);
}

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php" style="color: var(--gold-brown); text-decoration: none;">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Servis Saya
                    </li>
                </ol>
            </nav>
            <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Servis Saya</h1>
            <p class="text-muted">Kelola semua servis yang ditugaskan kepada Anda</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>

    <?php echo $message_display; ?>

    <!-- Available Services Section (Take New Order) -->
    <?php if (count($available_services) > 0): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-4">
            <h5 class="mb-0">
                <i class="fas fa-clock me-2" style="color: var(--gold-brown);"></i>
                Servis Tersedia
                <span class="badge bg-gold ms-2 rounded-pill"><?php echo count($available_services); ?></span>
            </h5>
            <p class="text-muted small mt-1">Tekan tombol "Ambil" untuk mulai mengerjakan servis</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Device</th>
                            <th>Customer</th>
                            <th>Masalah</th>
                            <th>Tanggal</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_services as $service): ?>
                        <tr>
                            <td class="ps-4">#<?php echo $service['id']; ?></a>
                            <td><strong><?php echo htmlspecialchars($service['device'] ?? '-'); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?>
                                <br><small class="text-muted">📞 <?php echo htmlspecialchars($service['customer_phone'] ?? '-'); ?></small>
                            </a>
                            <td><?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 40)); ?>...</a>
                            <td><?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?></a>
                            <td class="text-end pe-4">
                                <a href="?take=<?php echo $service['id']; ?>" class="btn btn-sm btn-gold rounded-4" 
                                   onclick="return confirm('Ambil servis ini? Anda akan melakukan kunjungan ke customer.')">
                                    <i class="fas fa-hand-peace me-1"></i> Ambil
                                </a>
                            </a>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-tabs mb-4">
        <div class="d-flex flex-wrap gap-2">
            <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list me-1"></i> Semua
                <span class="badge ms-1"><?php echo array_sum($counts); ?></span>
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock me-1"></i> Menunggu
                <span class="badge ms-1"><?php echo $counts['pending']; ?></span>
            </a>
            <a href="?filter=visit" class="filter-tab <?php echo $filter == 'visit' ? 'active' : ''; ?>">
                <i class="fas fa-hand-peace me-1"></i> Kunjungan
                <span class="badge ms-1"><?php echo $counts['visit']; ?></span>
            </a>
            <a href="?filter=accepted" class="filter-tab <?php echo $filter == 'accepted' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle me-1"></i> Diterima
                <span class="badge ms-1"><?php echo $counts['accepted']; ?></span>
            </a>
            <a href="?filter=repairing" class="filter-tab <?php echo $filter == 'repairing' ? 'active' : ''; ?>">
                <i class="fas fa-tools me-1"></i> Diperbaiki
                <span class="badge ms-1"><?php echo $counts['repairing']; ?></span>
            </a>
            <a href="?filter=done" class="filter-tab <?php echo $filter == 'done' ? 'active' : ''; ?>">
                <i class="fas fa-check-double me-1"></i> Selesai
                <span class="badge ms-1"><?php echo $counts['done']; ?></span>
            </a>
        </div>
    </div>

    <?php if (count($services) == 0): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="fas fa-tools fa-4x text-muted mb-3"></i>
                <h3>Tidak Ada Servis</h3>
                <p class="text-muted">
                    <?php if ($filter == 'pending'): ?>
                        Tidak ada servis dengan status "Menunggu" yang ditugaskan kepada Anda.
                    <?php elseif ($filter == 'visit'): ?>
                        Tidak ada servis dengan status "Kunjungan" yang ditugaskan kepada Anda.
                    <?php elseif ($filter == 'accepted'): ?>
                        Tidak ada servis dengan status "Diterima" yang ditugaskan kepada Anda.
                    <?php elseif ($filter == 'repairing'): ?>
                        Tidak ada servis dengan status "Diperbaiki" yang ditugaskan kepada Anda.
                    <?php elseif ($filter == 'done'): ?>
                        Tidak ada servis dengan status "Selesai" yang ditugaskan kepada Anda.
                    <?php else: ?>
                        Belum ada servis yang ditugaskan kepada Anda.
                    <?php endif; ?>
                </p>
                <?php if (count($available_services) > 0): ?>
                    <p class="text-muted">Ada <?php echo count($available_services); ?> servis baru yang tersedia!</p>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-gold rounded-4 mt-3">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($services as $service): ?>
                <?php 
                // [FIX] Ambil status dari database dengan aman
                $current_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                
                // [FIX] Status labels mapping
                $status_labels = [
                    'pending' => ['color' => 'secondary', 'text' => 'Menunggu', 'icon' => 'fa-clock'],
                    'visit' => ['color' => 'info', 'text' => 'Kunjungan', 'icon' => 'fa-hand-peace'],
                    'accepted' => ['color' => 'primary', 'text' => 'Diterima', 'icon' => 'fa-check-circle'],
                    'repairing' => ['color' => 'warning', 'text' => 'Diperbaiki', 'icon' => 'fa-tools'],
                    'done' => ['color' => 'success', 'text' => 'Selesai', 'icon' => 'fa-check-double']
                ];
                
                // Get status info or default
                $status = $status_labels[$current_status] ?? [
                    'color' => 'secondary', 
                    'text' => $current_status == 'pending' ? 'Menunggu' : ucfirst($current_status), 
                    'icon' => 'fa-info-circle'
                ];
                
                // Determine button visibility
                $show_cancel = ($current_status == 'visit');
                $show_chat = in_array($current_status, ['visit', 'accepted', 'repairing']);
                // Tombol Update Status hanya ditampilkan jika status BUKAN 'done'
                $show_update_status = ($current_status != 'done');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="service-card rounded-4">
                        <div class="service-card-header p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-<?php echo $status['color']; ?> px-3 py-2 rounded-pill">
                                        <i class="fas <?php echo $status['icon']; ?> me-1"></i>
                                        <?php echo $status['text']; ?>
                                    </span>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <small class="text-muted">#<?php echo $service['id']; ?></small>
                                    <?php if ($show_chat): ?>
                                        <a href="chat.php?service_id=<?php echo $service['id']; ?>" class="btn-chat" title="Chat dengan Customer">
                                            <i class="fas fa-comment-dots"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="service-card-body p-3">
                            <h5 class="fw-semibold mb-2">
                                <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
                                <?php echo htmlspecialchars($service['device'] ?? '-'); ?>
                            </h5>
                            <div class="mb-2">
                                <i class="fas fa-user me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?></small>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo htmlspecialchars($service['customer_phone'] ?? '-'); ?></small>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-calendar me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo date('d/m/Y H:i', strtotime($service['created_at'] ?? 'now')); ?></small>
                            </div>
                            <div class="problem-box p-2 rounded-3 mb-3">
                                <small class="text-muted">Masalah:</small>
                                <p class="small mb-0"><?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 60)); ?>...</p>
                            </div>
                            
                            <!-- Part List - hanya untuk status repairing, accepted, done -->
                            <?php 
                            $service_parts = [];
                            if (!empty($service['used_parts'])) {
                                $service_parts = json_decode($service['used_parts'], true);
                            }
                            if (count($service_parts) > 0 && in_array($current_status, ['repairing', 'accepted', 'done'])): 
                            ?>
                                <div class="parts-used-box p-2 rounded-3 mb-3">
                                    <small class="text-muted"><i class="fas fa-microchip me-1"></i> Part digunakan:</small>
                                    <div class="small mt-1">
                                        <?php foreach ($service_parts as $idx => $part): ?>
                                            <span class="part-tag"><?php echo htmlspecialchars($part['name'] ?? ''); ?> (<?php echo $part['quantity'] ?? 0; ?>x)</span>
                                            <?php if ($idx < count($service_parts) - 1): ?>, <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($service['estimated_cost']) && $service['estimated_cost'] > 0): ?>
                                <div class="cost-box p-2 rounded-3 mb-3">
                                    <small class="text-muted">Estimasi Biaya:</small>
                                    <p class="small fw-bold mb-0" style="color: var(--gold-brown);">
                                        <?php echo formatCurrency($service['estimated_cost']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <?php if ($show_update_status): ?>
                                    <a href="update_status.php?id=<?php echo $service['id']; ?>" class="btn btn-gold rounded-4 flex-grow-1">
                                        <i class="fas fa-sync-alt me-2"></i> Update Status
                                    </a>
                                <?php else: ?>
                                    <!-- [FIX] Tombol informasi diperpanjang menggantikan update status -->
                                    <a href="update_status.php?id=<?php echo $service['id']; ?>" class="btn btn-gold rounded-4 flex-grow-1">
                                        <i class="fas fa-info-circle me-2"></i> Lihat Detail
                                    </a>
                                <?php endif; ?>
                                    
                                <?php if ($show_cancel): ?>
                                    <a href="?cancel=<?php echo $service['id']; ?>" class="btn btn-outline-danger rounded-4" 
                                       onclick="return confirm('Yakin ingin membatalkan servis ini? Servis akan dikembalikan ke antrian.')">
                                        <i class="fas fa-ban me-2"></i> Batal
                                    </a>
                                <?php else: ?>
                                    <a href="update_status.php?id=<?php echo $service['id']; ?>" class="btn btn-outline-gold rounded-4">
                                        <i class="fas fa-info-circle"></i>
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

<style>
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
    
    .filter-tabs {
        background: white;
        padding: 0.5rem;
        border-radius: 16px;
        border: 1px solid rgba(192, 133, 82, 0.15);
    }
    
    .filter-tab {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 12px;
        text-decoration: none;
        color: var(--medium-brown);
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    
    .filter-tab:hover {
        background: rgba(192, 133, 82, 0.05);
        color: var(--gold-brown);
    }
    
    .filter-tab.active {
        background: var(--gold-brown);
        color: white;
    }
    
    .filter-tab.active .badge {
        background: white !important;
        color: var(--gold-brown);
    }
    
    .filter-tab .badge {
        background: rgba(192, 133, 82, 0.15);
        color: var(--gold-brown);
        font-size: 0.7rem;
    }
    
    .service-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(75, 46, 43, 0.1);
    }
    
    .problem-box {
        background: rgba(192, 133, 82, 0.05);
    }
    
    .cost-box {
        background: rgba(192, 133, 82, 0.08);
    }
    
    .parts-used-box {
        background: rgba(192, 133, 82, 0.08);
        border-radius: 10px;
    }
    
    .part-tag {
        display: inline-block;
        background: rgba(192, 133, 82, 0.15);
        padding: 0.2rem 0.5rem;
        border-radius: 15px;
        font-size: 0.7rem;
        margin: 0.1rem;
        color: var(--gold-brown);
    }
    
    .btn-chat {
        background: rgba(192, 133, 82, 0.1);
        color: var(--gold-brown);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-chat:hover {
        background: var(--gold-brown);
        color: white;
        transform: scale(1.1);
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
    
    .bg-gold {
        background: var(--gold-brown);
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
    
    .alert-info {
        background: rgba(23, 162, 184, 0.1);
        border: none;
        color: #17a2b8;
    }
    
    .table-hover tbody tr:hover {
        background: rgba(192, 133, 82, 0.03);
    }
    
    /* Status badge colors */
    .bg-secondary { background-color: #6c757d !important; color: white; }
    .bg-info { background-color: #17a2b8 !important; color: white; }
    .bg-primary { background-color: #0d6efd !important; color: white; }
    .bg-warning { background-color: #ffc107 !important; color: #000; }
    .bg-success { background-color: #28a745 !important; color: white; }
</style>

<?php include '../includes/footer.php'; ?>