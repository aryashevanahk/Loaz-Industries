<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Handle rating submission
$rating_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $service_id = (int)$_POST['service_id'];
    $technician_id = (int)$_POST['technician_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        $rating_message = '<div class="alert alert-danger rounded-4">Rating harus antara 1-5!</div>';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE service_id = ? AND user_id = ?");
        $stmt->execute([$service_id, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $rating_message = '<div class="alert alert-warning rounded-4">Anda sudah memberikan rating untuk servis ini!</div>';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reviews (service_id, user_id, technician_id, rating, comment, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            if ($stmt->execute([$service_id, $_SESSION['user_id'], $technician_id, $rating, $comment])) {
                $rating_message = '<div class="alert alert-success rounded-4"><i class="fas fa-check-circle me-2"></i>Terima kasih atas rating dan ulasannya!</div>';
            } else {
                $rating_message = '<div class="alert alert-danger rounded-4">Gagal menyimpan rating. Silakan coba lagi.</div>';
            }
        }
    }
}

// Handle cancel service - untuk status 'visit'
if (isset($_GET['cancel'])) {
    $service_id = (int)$_GET['cancel'];
    
    $stmt = $pdo->prepare("SELECT status, technician_id FROM services WHERE id = ? AND user_id = ?");
    $stmt->execute([$service_id, $_SESSION['user_id']]);
    $service = $stmt->fetch();
    
    if ($service && $service['status'] == 'visit') {
        $stmt = $pdo->prepare("UPDATE services SET status = 'pending', technician_id = NULL WHERE id = ?");
        $stmt->execute([$service_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO service_updates (service_id, status, note) 
            VALUES (?, 'pending', 'Customer membatalkan servis saat tahap kunjungan')
        ");
        $stmt->execute([$service_id]);
        
        $cancel_message = '<div class="alert alert-info rounded-4"><i class="fas fa-info-circle me-2"></i>Servis berhasil dibatalkan.</div>';
    } else {
        $cancel_message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>Servis tidak dapat dibatalkan karena sudah diproses teknisi.</div>';
    }
    
    header('Location: my_services.php?msg=' . urlencode($cancel_message));
    exit();
}

// Handle request for payment (when user wants to pay)
if (isset($_GET['pay'])) {
    $service_id = (int)$_GET['pay'];
    header('Location: payment_service.php?service_id=' . $service_id);
    exit();
}

// Get message from URL
$cancel_message_display = '';
if (isset($_GET['msg'])) {
    $cancel_message_display = urldecode($_GET['msg']);
}

// [FIX] Get user's services dengan display status yang benar
// Menangani NULL status dengan COALESCE
try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(s.status, 'pending') as safe_status,
               t.payment_status as transaction_status,
               t.id as transaction_id,
               t.total_amount as transaction_amount,
               tu.name as technician_name,
               tech.id as technician_db_id,
               (SELECT rating FROM reviews WHERE service_id = s.id AND user_id = ?) as user_rating,
               (SELECT comment FROM reviews WHERE service_id = s.id AND user_id = ?) as user_comment
        FROM services s 
        LEFT JOIN technicians tech ON s.technician_id = tech.id
        LEFT JOIN users tu ON tech.user_id = tu.id
        LEFT JOIN transactions t ON s.id = t.service_id
        WHERE s.user_id = ? 
        ORDER BY FIELD(COALESCE(s.status, 'pending'), 'pending', 'visit', 'accepted', 'repairing', 'done'), s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $services = [];
    error_log("Error in my_services.php: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Layanan Servis Saya</h1>
                    <p class="text-muted">Pantau status servis elektronik Anda</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                    </a>
                    <a href="request_service.php" class="btn btn-gold rounded-4">
                        <i class="fas fa-plus-circle me-2"></i> Request Baru
                    </a>
                </div>
            </div>
            
            <?php if ($rating_message): ?>
                <?php echo $rating_message; ?>
            <?php endif; ?>
            
            <?php if ($cancel_message_display): ?>
                <?php echo $cancel_message_display; ?>
            <?php endif; ?>
            
            <?php if (count($services) == 0): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-tools fa-4x text-muted mb-3"></i>
                        <h3>Belum ada layanan servis</h3>
                        <p class="text-muted">Silakan buat request servis pertama Anda</p>
                        <div class="mt-3">
                            <a href="request_service.php" class="btn btn-gold rounded-4">
                                <i class="fas fa-paper-plane me-2"></i> Request Servis
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-gold rounded-4 ms-2">
                                <i class="fas fa-home me-2"></i> Ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($services as $service): ?>
                        <?php 
                        // [FIX] Ambil status dengan aman, gunakan safe_status dari query atau fallback
                        $actual_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                        $has_technician = !empty($service['technician_name']);
                        
                        // Tentukan status yang akan ditampilkan
                        $display_status = $actual_status;
                        
                        // Jika status pending tapi punya teknisi, tampilkan sebagai visit (Kunjungan)
                        if ($actual_status == 'pending' && $has_technician) {
                            $display_status = 'visit';
                        }
                        
                        // Status badge mapping dengan nilai default
                        $status_badge = [
                            'pending' => ['color' => 'secondary', 'text' => 'Menunggu Teknisi', 'icon' => 'fa-clock'],
                            'visit' => ['color' => 'info', 'text' => 'Kunjungan Teknisi', 'icon' => 'fa-hand-peace'],
                            'accepted' => ['color' => 'primary', 'text' => 'Diterima', 'icon' => 'fa-check-circle'],
                            'repairing' => ['color' => 'warning', 'text' => 'Diperbaiki', 'icon' => 'fa-tools'],
                            'done' => ['color' => 'success', 'text' => 'Selesai', 'icon' => 'fa-check-double']
                        ];
                        
                        // Ambil status atau default ke pending
                        $status = $status_badge[$display_status] ?? ['color' => 'secondary', 'text' => 'Menunggu', 'icon' => 'fa-clock'];
                        
                        // Tentukan apakah tombol chat ditampilkan
                        $show_chat = $has_technician && in_array($actual_status, ['pending', 'visit', 'accepted', 'repairing']);
                        // Tentukan apakah tombol cancel ditampilkan (hanya untuk status visit)
                        $show_cancel = ($display_status == 'visit');
                        // Tentukan apakah tombol bayar ditampilkan
                        $show_pay = ($actual_status == 'done' && ($service['transaction_status'] ?? '') != 'paid');
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="service-card rounded-4 h-100">
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
                                            <!-- Tombol Chat - muncul jika sudah ada teknisi -->
                                            <?php if ($show_chat): ?>
                                                <a href="chat.php?service_id=<?php echo $service['id']; ?>" class="btn-chat" title="Chat dengan Teknisi">
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
                                        <i class="fas fa-user-cog me-2" style="color: var(--gold-brown);"></i>
                                        <small>
                                            Teknisi: 
                                            <?php 
                                                if ($has_technician) {
                                                    echo '<span class="fw-semibold" style="color: var(--gold-brown);">' . htmlspecialchars($service['technician_name']) . '</span>';
                                                } else {
                                                    echo '<span class="text-muted">Belum ditugaskan</span>';
                                                }
                                            ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <i class="fas fa-calendar me-2" style="color: var(--gold-brown);"></i>
                                        <small><?php echo date('d/m/Y H:i', strtotime($service['created_at'] ?? 'now')); ?></small>
                                    </div>
                                    
                                    <div class="problem-box p-2 rounded-3 mb-3">
                                        <small class="text-muted">Masalah:</small>
                                        <p class="small mb-0"><?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 60)); ?>...</p>
                                    </div>
                                    
                                    <!-- Part yang Digunakan (hanya untuk status repairing atau done) -->
                                    <?php 
                                    $service_parts = [];
                                    $parts_total = 0;
                                    if (!empty($service['used_parts'])) {
                                        $service_parts = json_decode($service['used_parts'], true);
                                        if (is_array($service_parts)) {
                                            foreach ($service_parts as $part) {
                                                $parts_total += $part['price'] * $part['quantity'];
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <?php if (count($service_parts) > 0 && in_array($actual_status, ['repairing', 'done'])): ?>
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
                                    
                                    <!-- Biaya (hanya untuk status accepted, repairing, done) -->
                                    <?php if (in_array($actual_status, ['accepted', 'repairing', 'done']) && (($service['estimated_cost'] ?? 0) > 0 || $parts_total > 0)): ?>
                                    <div class="cost-box p-2 rounded-3 mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="text-muted">Biaya Jasa:</small>
                                            <small><?php echo formatCurrency($service['estimated_cost'] ?? 0); ?></small>
                                        </div>
                                        <?php if ($parts_total > 0): ?>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Biaya Part:</small>
                                                <small><?php echo formatCurrency($parts_total); ?></small>
                                            </div>
                                            <hr class="my-1">
                                            <div class="d-flex justify-content-between">
                                                <small class="fw-bold">Total:</small>
                                                <small class="fw-bold" style="color: var(--gold-brown);">
                                                    <?php echo formatCurrency(($service['estimated_cost'] ?? 0) + $parts_total); ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between">
                                                <small class="fw-bold">Total:</small>
                                                <small class="fw-bold" style="color: var(--gold-brown);">
                                                    <?php echo formatCurrency($service['estimated_cost'] ?? 0); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Pembayaran (hanya untuk status done) -->
                                    <?php if ($actual_status == 'done'): ?>
                                        <div class="payment-status-box p-2 rounded-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-credit-card me-1" style="color: var(--gold-brown);"></i>
                                                    <small class="text-muted">Status Pembayaran:</small>
                                                </div>
                                                <div>
                                                    <?php if (($service['transaction_status'] ?? '') == 'paid'): ?>
                                                        <span class="badge bg-success">Lunas</span>
                                                    <?php elseif (($service['transaction_status'] ?? '') == 'pending_confirmation'): ?>
                                                        <span class="badge bg-info">Menunggu Konfirmasi</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Belum Dibayar</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Rating Section (hanya untuk servis selesai dengan teknisi) -->
                                    <?php if ($actual_status == 'done' && $has_technician): ?>
                                        <div class="rating-section p-2 rounded-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <i class="fas fa-star me-1" style="color: #ffc107;"></i>
                                                    <small class="fw-semibold">Rating Teknisi</small>
                                                </div>
                                                <?php if ($service['user_rating']): ?>
                                                    <span class="badge bg-success">Sudah Dinilai</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($service['user_rating']): ?>
                                                <div class="text-center">
                                                    <div class="existing-rating mb-2">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $service['user_rating'] ? 'text-warning' : 'text-muted'; ?>" style="font-size: 1.1rem;"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <?php if ($service['user_comment']): ?>
                                                        <p class="small text-muted mb-0">"<?php echo htmlspecialchars($service['user_comment']); ?>"</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-gold w-100" 
                                                        onclick="openRatingModal(<?php echo $service['id']; ?>, <?php echo $service['technician_db_id']; ?>, '<?php echo addslashes($service['technician_name'] ?? ''); ?>')"
                                                        data-bs-toggle="modal" data-bs-target="#ratingModal">
                                                    <i class="fas fa-star me-2"></i> Beri Rating & Ulasan
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-outline-gold rounded-4 flex-grow-1" 
                                                onclick="showServiceDetails(<?php echo $service['id']; ?>, '<?php echo addslashes($service['device'] ?? ''); ?>')"
                                                data-bs-toggle="modal" data-bs-target="#serviceModal">
                                            <i class="fas fa-info-circle me-2"></i> Detail
                                        </button>
                                        
                                        <!-- Tombol Batalkan Servis - Hanya untuk status 'visit' (display status) -->
                                        <?php if ($show_cancel): ?>
                                            <a href="?cancel=<?php echo $service['id']; ?>" class="btn btn-outline-danger rounded-4" 
                                               onclick="return confirm('Yakin ingin membatalkan servis ini? Servis akan dikembalikan ke antrian dan teknisi tidak akan datang.')">
                                                <i class="fas fa-ban me-2"></i> Batalkan
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Bayar - Hanya muncul jika status done dan belum dibayar -->
                                        <?php if ($show_pay): ?>
                                            <?php 
                                            $total_tagihan = ($service['estimated_cost'] ?? 0) + $parts_total;
                                            if ($total_tagihan > 0): 
                                            ?>
                                                <a href="payment_service.php?service_id=<?php echo $service['id']; ?>" class="btn btn-gold rounded-4">
                                                    <i class="fas fa-credit-card me-2"></i> Bayar
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rating Modal (sama seperti sebelumnya) -->
<div class="modal fade" id="ratingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-star me-2"></i> Beri Rating & Ulasan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="service_id" id="rating_service_id">
                    <input type="hidden" name="technician_id" id="rating_technician_id">
                    
                    <div class="text-center mb-3">
                        <p class="mb-1">Berikan rating untuk teknisi</p>
                        <h6 id="rating_technician_name" class="text-gold mb-3"></h6>
                        
                        <div class="rating-stars mb-3">
                            <div class="star-rating">
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                            </div>
                            <input type="hidden" name="rating" id="selected_rating" value="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ulasan (Opsional)</label>
                            <textarea name="comment" class="form-control" rows="3" placeholder="Tulis pengalaman Anda dengan teknisi..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_rating" class="btn btn-gold" id="submitRatingBtn" disabled>Kirim Rating</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Service Details Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title" id="modalTitle">Detail Servis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-gold" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS Styles (sama seperti sebelumnya) -->
<style>
    :root {
        --cream: #FFF8F0;
        --gold-brown: #C08552;
        --medium-brown: #8C5A3C;
        --dark-brown: #4B2E2B;
    }
    
    .service-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(75, 46, 43, 0.1);
    }
    
    .problem-box {
        background: rgba(192, 133, 82, 0.05);
        border-radius: 12px;
    }
    
    .cost-box {
        background: rgba(192, 133, 82, 0.08);
        border-radius: 12px;
    }
    
    .parts-used-box {
        background: rgba(192, 133, 82, 0.08);
        border-radius: 12px;
    }
    
    .payment-status-box {
        background: rgba(192, 133, 82, 0.05);
        border-radius: 12px;
    }
    
    .rating-section {
        background: rgba(255, 193, 7, 0.08);
        border-radius: 12px;
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
    
    .star-rating {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        cursor: pointer;
    }
    
    .star-rating i {
        font-size: 2rem;
        transition: all 0.2s ease;
        color: #ddd;
    }
    
    .star-rating i:hover,
    .star-rating i.hover {
        transform: scale(1.1);
    }
    
    .star-rating i.selected {
        color: #ffc107;
    }
    
    .text-gold {
        color: var(--gold-brown);
    }
    
    .bg-secondary { background-color: #6c757d !important; }
    .bg-info { background-color: #17a2b8 !important; }
    .bg-primary { background-color: #0d6efd !important; }
    .bg-warning { background-color: #ffc107 !important; color: #000; }
    .bg-success { background-color: #28a745 !important; }
    
    @media (max-width: 768px) {
        .service-card-header {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .star-rating i {
            font-size: 1.5rem;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        let selectedRating = 0;
        
        $('.star-rating i').on('click', function() {
            selectedRating = $(this).data('rating');
            $('#selected_rating').val(selectedRating);
            
            $('.star-rating i').each(function() {
                const rating = $(this).data('rating');
                if (rating <= selectedRating) {
                    $(this).removeClass('far').addClass('fas selected');
                } else {
                    $(this).removeClass('fas selected').addClass('far');
                }
            });
            
            $('#submitRatingBtn').prop('disabled', false);
        });
        
        $('.star-rating i').on('mouseenter', function() {
            const hoverRating = $(this).data('rating');
            $('.star-rating i').each(function() {
                const rating = $(this).data('rating');
                if (rating <= hoverRating) {
                    $(this).addClass('hover');
                }
            });
        });
        
        $('.star-rating').on('mouseleave', function() {
            $('.star-rating i').removeClass('hover');
        });
    });
    
    function showServiceDetails(id, device) {
        $('#modalTitle').text('Detail Servis - ' + device);
        $('#modalBody').html('<div class="text-center py-4"><div class="spinner-border text-gold" role="status"></div></div>');
        
        $.get('get_service_detail.php?id=' + id, function(data) {
            $('#modalBody').html(data);
        }).fail(function() {
            $('#modalBody').html('<div class="text-center py-4 text-danger">Gagal memuat detail servis</div>');
        });
    }
    
    function openRatingModal(serviceId, technicianId, technicianName) {
        document.getElementById('rating_service_id').value = serviceId;
        document.getElementById('rating_technician_id').value = technicianId;
        document.getElementById('rating_technician_name').innerHTML = technicianName;
        
        $('#selected_rating').val(0);
        $('.star-rating i').removeClass('fas selected').addClass('far');
        $('#submitRatingBtn').prop('disabled', true);
        $('textarea[name="comment"]').val('');
    }
</script>

<?php include '../includes/footer.php'; ?>