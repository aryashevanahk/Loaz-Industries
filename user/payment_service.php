<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// [FIX] Perbaiki query untuk mendapatkan nama teknisi dari tabel technicians dan users
$stmt = $pdo->prepare("
    SELECT s.*, 
           t.id as transaction_id,
           t.payment_status,
           t.payment_proof,
           tu.name as technician_name,
           tu.phone as technician_phone,
           tech.id as technician_db_id
    FROM services s
    LEFT JOIN technicians tech ON s.technician_id = tech.id
    LEFT JOIN users tu ON tech.user_id = tu.id
    LEFT JOIN transactions t ON s.id = t.service_id
    WHERE s.id = ? AND s.user_id = ?
");
$stmt->execute([$service_id, $_SESSION['user_id']]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: my_services.php');
    exit();
}

// Calculate total cost
$used_parts = [];
$parts_total = 0;
if ($service['used_parts']) {
    $used_parts = json_decode($service['used_parts'], true);
    if (is_array($used_parts)) {
        foreach ($used_parts as $part) {
            $parts_total += $part['price'] * $part['quantity'];
        }
    }
}
$service_cost = $service['estimated_cost'] ?? 0;
$total_cost = $service_cost + $parts_total;

// Generate order ID for payment reference
$order_ref = 'INV-' . str_pad($service['id'], 6, '0', STR_PAD_LEFT);

// Handle payment confirmation
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_note = trim($_POST['payment_note']);
    $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
    
    // Handle payment proof upload
    $payment_proof = null;
    $upload_error = null;
    
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $filename = $_FILES['payment_proof']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024;
        
        if ($_FILES['payment_proof']['size'] > $max_size) {
            $upload_error = 'Ukuran file terlalu besar! Maksimal 2MB.';
        } elseif (!in_array($ext, $allowed)) {
            $upload_error = 'Format file tidak didukung! Gunakan JPG, PNG, PDF.';
        } else {
            if (!file_exists('../uploads/payment_proofs/')) {
                mkdir('../uploads/payment_proofs/', 0777, true);
            }
            $payment_proof = 'service_' . $service_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            move_uploaded_file($_FILES['payment_proof']['tmp_name'], '../uploads/payment_proofs/' . $payment_proof);
        }
    } else {
        if ($_FILES['payment_proof']['error'] == UPLOAD_ERR_NO_FILE) {
            $upload_error = 'Silakan upload bukti pembayaran!';
        } elseif ($_FILES['payment_proof']['error'] != 0) {
            $upload_error = 'Terjadi kesalahan saat upload file.';
        }
    }
    
    if ($upload_error) {
        $error = $upload_error;
    } elseif ($payment_proof) {
        try {
            if ($service['transaction_id']) {
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET payment_proof = ?, 
                        payment_note = ?, 
                        payment_method = ?,
                        payment_status = 'pending_confirmation'
                    WHERE id = ?
                ");
                $result = $stmt->execute([$payment_proof, $payment_note, $payment_method, $service['transaction_id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (service_id, total_amount, payment_proof, payment_note, payment_method, payment_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending_confirmation', NOW())
                ");
                $result = $stmt->execute([$service_id, $total_cost, $payment_proof, $payment_note, $payment_method]);
            }
            
            if ($result) {
                $success = 'Bukti pembayaran berhasil diupload! Admin akan melakukan konfirmasi dalam 1x24 jam.';
                
                // Refresh service data
                $stmt = $pdo->prepare("
                    SELECT s.*, 
                           t.id as transaction_id,
                           t.payment_status,
                           t.payment_proof,
                           tu.name as technician_name,
                           tu.phone as technician_phone,
                           tech.id as technician_db_id
                    FROM services s
                    LEFT JOIN technicians tech ON s.technician_id = tech.id
                    LEFT JOIN users tu ON tech.user_id = tu.id
                    LEFT JOIN transactions t ON s.id = t.service_id
                    WHERE s.id = ? AND s.user_id = ?
                ");
                $stmt->execute([$service_id, $_SESSION['user_id']]);
                $service = $stmt->fetch();
            } else {
                $error = 'Gagal menyimpan data pembayaran. Silakan coba lagi.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan database. Silakan coba lagi.';
        }
    }
}

// [FIX] QR Code dengan quickchart.io dan link YouTube
$qr_data = 'https://quickchart.io/qr?text=' . urlencode('https://www.youtube.com/watch?v=dQw4w9WgXcQ') . '&size=200';
$va_number = '';

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="my_services.php" class="btn-back">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Servis Saya
                </a>
            </div>
            
            <!-- Header -->
            <div class="text-center mb-5">
                <div class="payment-icon mb-3">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Pembayaran Servis</h1>
                <p class="text-muted">Selesaikan pembayaran untuk servis elektronik Anda</p>
            </div>
            
            <!-- Service Info Card -->
            <div class="service-info-card rounded-4 mb-4">
                <div class="service-info-header">
                    <i class="fas fa-microchip me-2"></i>
                    Informasi Servis
                </div>
                <div class="service-info-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label class="info-label">Device</label>
                                <p class="info-value fw-semibold mb-0"><?php echo htmlspecialchars($service['device']); ?></p>
                            </div>
                            <div class="info-item">
                                <label class="info-label">Teknisi</label>
                                <p class="info-value mb-0">
                                    <i class="fas fa-user-circle me-1" style="color: var(--gold-brown);"></i>
                                    <?php 
                                    if (!empty($service['technician_name'])) {
                                        echo '<strong>' . htmlspecialchars($service['technician_name']) . '</strong>';
                                        if (!empty($service['technician_phone'])) {
                                            echo '<br><small class="text-muted">📞 ' . htmlspecialchars($service['technician_phone']) . '</small>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">Sedang ditugaskan</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <label class="info-label">Status Servis</label>
                                <div>
                                    <span class="status-badge status-done">
                                        <i class="fas fa-check-circle me-1"></i> Selesai
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <label class="info-label">Tanggal Selesai</label>
                                <p class="info-value mb-0">
                                    <i class="fas fa-calendar-alt me-1" style="color: var(--gold-brown);"></i>
                                    <?php echo date('d F Y', strtotime($service['updated_at'] ?? $service['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Rincian Biaya Card -->
            <div class="cost-card rounded-4 mb-4">
                <div class="cost-card-header">
                    <i class="fas fa-receipt me-2"></i>
                    Rincian Biaya
                </div>
                <div class="cost-card-body">
                    <div class="cost-row">
                        <div class="cost-label">
                            <i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>
                            Biaya Jasa Servis
                        </div>
                        <div class="cost-value"><?php echo formatCurrency($service_cost); ?></div>
                    </div>
                    
                    <?php if ($parts_total > 0): ?>
                        <div class="cost-row">
                            <div class="cost-label">
                                <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
                                Biaya Part
                            </div>
                            <div class="cost-value"><?php echo formatCurrency($parts_total); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($used_parts) > 0 && is_array($used_parts)): ?>
                        <div class="parts-detail mt-3">
                            <div class="parts-detail-header">
                                <i class="fas fa-list me-1"></i> Detail Part
                            </div>
                            <div class="parts-list">
                                <?php foreach ($used_parts as $part): ?>
                                    <div class="part-item">
                                        <span class="part-name"><?php echo htmlspecialchars($part['name']); ?></span>
                                        <span class="part-qty"><?php echo $part['quantity']; ?>x</span>
                                        <span class="part-price"><?php echo formatCurrency($part['price']); ?></span>
                                        <span class="part-subtotal"><?php echo formatCurrency($part['price'] * $part['quantity']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="cost-divider"></div>
                    
                    <div class="cost-total">
                        <div class="total-label">Total Yang Harus Dibayar</div>
                        <div class="total-value"><?php echo formatCurrency($total_cost); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Form Konfirmasi Pembayaran -->
            <?php if ($service['payment_status'] == 'paid'): ?>
                <div class="success-card rounded-4 text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="mt-3 mb-2">Pembayaran Sudah Dikonfirmasi!</h3>
                    <p class="text-muted mb-4">Terima kasih, pembayaran Anda sudah kami terima.</p>
                    <a href="my_services.php" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-list me-2"></i> Kembali ke Servis Saya
                    </a>
                </div>
            <?php elseif ($service['payment_status'] == 'pending_confirmation'): ?>
                <div class="pending-card rounded-4 text-center">
                    <div class="pending-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h3 class="mt-3 mb-2">Menunggu Konfirmasi Admin</h3>
                    <p class="text-muted mb-3">Bukti pembayaran Anda sedang diverifikasi.</p>
                    <div class="alert alert-info-custom mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Proses verifikasi maksimal 1x24 jam
                    </div>
                    <?php if ($service['payment_proof']): ?>
                        <a href="../uploads/payment_proofs/<?php echo $service['payment_proof']; ?>" target="_blank" class="btn btn-outline-gold rounded-4 me-2">
                            <i class="fas fa-eye me-2"></i> Lihat Bukti
                        </a>
                    <?php endif; ?>
                    <a href="my_services.php" class="btn btn-gold rounded-4">
                        <i class="fas fa-list me-2"></i> Kembali ke Servis Saya
                    </a>
                </div>
            <?php else: ?>
                <div class="payment-form-card rounded-4">
                    <div class="payment-form-header">
                        <i class="fas fa-upload me-2"></i>
                        Konfirmasi Pembayaran
                    </div>
                    <div class="payment-form-body">
                        <?php if ($success): ?>
                            <div class="alert-custom alert-success-custom">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert-custom alert-danger-custom">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Dynamic Payment Instructions - Card berdasarkan metode -->
                        <div id="paymentInstructions" class="mb-4"></div>
                        
                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <div class="form-group mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-credit-card me-2" style="color: var(--gold-brown);"></i>
                                    Metode Pembayaran
                                </label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="payment-method-option">
                                            <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" checked>
                                            <label for="bank_transfer" class="w-100">
                                                <i class="fas fa-university fa-2x mb-2"></i>
                                                <div><strong>Transfer Bank</strong></div>
                                                <small>BCA/Mandiri/BRI/BNI</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="payment-method-option">
                                            <input type="radio" name="payment_method" value="qris" id="qris">
                                            <label for="qris" class="w-100">
                                                <i class="fas fa-qrcode fa-2x mb-2"></i>
                                                <div><strong>QRIS</strong></div>
                                                <small>Scan via e-wallet/bank</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="payment-method-option">
                                            <input type="radio" name="payment_method" value="e_wallet" id="e_wallet">
                                            <label for="e_wallet" class="w-100">
                                                <i class="fas fa-wallet fa-2x mb-2"></i>
                                                <div><strong>E-Wallet</strong></div>
                                                <small>GoPay/OVO/Dana</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bank Transfer Instructions Card -->
                            <div id="bankTransferInstructions" class="instruction-panel payment-card-small mb-4" style="display: none;">
                                <div class="instruction-header">
                                    <i class="fas fa-university me-2"></i>
                                    <strong>Instruksi Transfer Bank</strong>
                                </div>
                                <div class="instruction-body">
                                    <p>Silakan transfer sebesar <strong><?php echo formatCurrency($total_cost); ?></strong> ke salah satu rekening berikut:</p>
                                    <div class="bank-list">
                                        <div class="bank-item">
                                            <span class="bank-name">BCA</span>
                                            <span class="bank-number">1234567890</span>
                                            <span class="bank-owner">a.n Loaz Industries</span>
                                            <button type="button" class="btn-copy" data-copy="1234567890">📋 Salin</button>
                                        </div>
                                        <div class="bank-item">
                                            <span class="bank-name">Mandiri</span>
                                            <span class="bank-number">9876543210</span>
                                            <span class="bank-owner">a.n Loaz Industries</span>
                                            <button type="button" class="btn-copy" data-copy="9876543210">📋 Salin</button>
                                        </div>
                                    </div>
                                    <hr>
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-info-circle me-1"></i> 
                                        Setelah transfer, upload bukti pembayaran di bawah.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- QRIS Instructions Card -->
                            <div id="qrisInstructions" class="instruction-panel payment-card-small mb-4" style="display: none;">
                                <div class="instruction-header">
                                    <i class="fas fa-qrcode me-2"></i>
                                    <strong>Instruksi Pembayaran QRIS</strong>
                                </div>
                                <div class="instruction-body text-center">
                                    <p>Scan QR Code di bawah menggunakan aplikasi:</p>
                                    <div class="d-flex justify-content-center gap-3 flex-wrap mb-3">
                                        <span class="badge-app"><i class="fab fa-google-play"></i> GoPay</span>
                                        <span class="badge-app"><i class="fas fa-wallet"></i> OVO</span>
                                        <span class="badge-app"><i class="fas fa-wallet"></i> Dana</span>
                                        <span class="badge-app"><i class="fas fa-university"></i> LinkAja</span>
                                    </div>
                                    <div class="qris-container my-3">
                                        <img src="<?php echo $qr_data; ?>" alt="QR Code" class="qris-code" 
                                             onclick="window.open('https://www.youtube.com/watch?v=dQw4w9WgXcQ', '_blank')"
                                             title="Klik untuk demo pembayaran">
                                        <p class="small text-muted mt-2">Klik QR Code untuk demo simulasi pembayaran</p>
                                    </div>
                                    <hr>
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-info-circle me-1"></i> 
                                        Setelah scan dan pembayaran berhasil, upload bukti pembayaran di bawah.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- E-Wallet Instructions Card -->
                            <div id="ewalletInstructions" class="instruction-panel payment-card-small mb-4" style="display: none;">
                                <div class="instruction-header">
                                    <i class="fas fa-wallet me-2"></i>
                                    <strong>Instruksi Pembayaran E-Wallet</strong>
                                </div>
                                <div class="instruction-body">
                                    <p>Kirim pembayaran ke ID E-Wallet berikut sesuai total tagihan <strong><?php echo formatCurrency($total_cost); ?></strong>:</p>
                                    <div class="ewallet-list">
                                        <div class="ewallet-item">
                                            <span class="ewallet-name"><i class="fab fa-google-play"></i> GoPay</span>
                                            <span class="ewallet-number">0888<?php echo str_pad($service['id'], 8, '0', STR_PAD_LEFT); ?></span>
                                            <button type="button" class="btn-copy" data-copy="0888<?php echo str_pad($service['id'], 8, '0', STR_PAD_LEFT); ?>">📋 Salin</button>
                                        </div>
                                        <div class="ewallet-item">
                                            <span class="ewallet-name"><i class="fas fa-wallet"></i> OVO</span>
                                            <span class="ewallet-number">0887<?php echo str_pad($service['id'], 8, '0', STR_PAD_LEFT); ?></span>
                                            <button type="button" class="btn-copy" data-copy="0887<?php echo str_pad($service['id'], 8, '0', STR_PAD_LEFT); ?>">📋 Salin</button>
                                        </div>
                                    </div>
                                    <hr>
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-info-circle me-1"></i> 
                                        Setelah transfer, upload bukti pembayaran di bawah.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="form-group mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-image me-2" style="color: var(--gold-brown);"></i>
                                    Upload Bukti Pembayaran <span class="text-danger">*</span>
                                </label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-2" style="color: var(--gold-brown);"></i>
                                    <p class="mb-1">Klik atau drag & drop file di sini</p>
                                    <small class="text-muted">Format: JPG, PNG, PDF. Maksimal 2MB</small>
                                    <input type="file" name="payment_proof" id="paymentProof" class="d-none" accept="image/*,application/pdf" required>
                                </div>
                                <div id="fileNameDisplay" class="mt-2 text-center" style="display: none;">
                                    <i class="fas fa-file-alt me-1" style="color: var(--gold-brown);"></i>
                                    <span id="selectedFileName"></span>
                                    <button type="button" class="btn btn-sm btn-link text-danger" id="clearFileBtn">Hapus</button>
                                </div>
                            </div>
                            
                            <div class="form-group mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-pen me-2" style="color: var(--gold-brown);"></i>
                                    Catatan (Opsional)
                                </label>
                                <textarea name="payment_note" class="form-control" rows="3" placeholder="Contoh: Transfer via BCA a.n. John Doe, Rp <?php echo number_format($total_cost, 0, ',', '.'); ?>"></textarea>
                            </div>
                            
                            <button type="submit" name="confirm_payment" class="btn btn-submit w-100 py-3 rounded-4" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i> Konfirmasi Pembayaran
                                <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .btn-back {
        display: inline-flex;
        align-items: center;
        background: transparent;
        border: 1.5px solid var(--gold-brown);
        color: var(--gold-brown);
        padding: 0.6rem 1.2rem;
        border-radius: 40px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .btn-back:hover {
        background: var(--gold-brown);
        color: white;
        transform: translateX(-5px);
    }
    
    .payment-icon {
        width: 80px;
        height: 80px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .payment-icon i {
        font-size: 2.5rem;
        color: var(--gold-brown);
    }
    
    .service-info-card, .cost-card, .payment-form-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
    }
    
    .service-info-header, .cost-card-header, .payment-form-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .service-info-body, .cost-card-body, .payment-form-body {
        padding: 1.5rem;
    }
    
    .info-item {
        margin-bottom: 1rem;
    }
    
    .info-label {
        display: block;
        font-size: 0.7rem;
        color: var(--medium-brown);
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 0.9rem;
        color: var(--dark-brown);
    }
    
    .cost-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
    }
    
    .cost-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--gold-brown), transparent);
        margin: 1rem 0;
    }
    
    .cost-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 0.5rem;
    }
    
    .total-label {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--dark-brown);
    }
    
    .total-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--gold-brown);
    }
    
    .parts-detail {
        background: rgba(192, 133, 82, 0.05);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .parts-detail-header {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(192, 133, 82, 0.15);
    }
    
    .part-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.4rem 0;
        font-size: 0.8rem;
    }
    
    .part-name { flex: 2; color: var(--dark-brown); }
    .part-qty { flex: 1; text-align: center; color: var(--medium-brown); }
    .part-price { flex: 1; text-align: right; color: var(--medium-brown); }
    .part-subtotal { flex: 1; text-align: right; font-weight: 600; color: var(--gold-brown); }
    
    .payment-method-option {
        border: 2px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-align: center;
        padding: 0.75rem;
    }
    
    .payment-method-option:hover {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.03);
    }
    
    .payment-method-option input { display: none; }
    .payment-method-option label { cursor: pointer; margin: 0; display: block; }
    .payment-method-option:has(input:checked) {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.08);
    }
    
    /* Payment Instruction Cards */
    .payment-card-small {
        background: rgba(192, 133, 82, 0.03);
        border: 1px solid rgba(192, 133, 82, 0.12);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .instruction-header {
        background: rgba(192, 133, 82, 0.08);
        padding: 0.8rem 1.2rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .instruction-body {
        padding: 1.2rem;
    }
    
    .upload-area {
        border: 2px dashed rgba(192, 133, 82, 0.3);
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(192, 133, 82, 0.02);
    }
    
    .upload-area:hover {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.05);
    }
    
    .alert-custom {
        border: none;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .alert-success-custom { background: rgba(192, 133, 82, 0.12); color: var(--gold-brown); }
    .alert-danger-custom { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    .alert-info-custom { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
    
    .bank-list, .ewallet-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-top: 0.75rem;
    }
    
    .bank-item, .ewallet-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.6rem;
        background: #f8f9fa;
        border-radius: 12px;
        flex-wrap: wrap;
    }
    
    .bank-name, .ewallet-name {
        font-weight: 600;
        color: var(--gold-brown);
        min-width: 80px;
    }
    
    .bank-number, .ewallet-number {
        font-family: monospace;
        font-size: 1rem;
        font-weight: bold;
        color: var(--dark-brown);
        flex: 1;
    }
    
    .btn-copy {
        background: var(--gold-brown);
        color: white;
        border: none;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-copy:hover {
        background: var(--medium-brown);
    }
    
    .badge-app {
        background: rgba(192, 133, 82, 0.1);
        padding: 0.4rem 1rem;
        border-radius: 50px;
        font-size: 0.8rem;
        color: var(--gold-brown);
    }
    
    .qris-code {
        width: 180px;
        height: 180px;
        cursor: pointer;
        transition: transform 0.3s ease;
        border-radius: 20px;
        padding: 10px;
        background: white;
    }
    
    .qris-code:hover {
        transform: scale(1.05);
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.3rem 1rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-done {
        background: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }
    
    .btn-submit {
        background: var(--gold-brown);
        color: white;
        border: none;
        transition: all 0.3s ease;
        font-weight: 600;
    }
    
    .btn-submit:hover {
        background: var(--medium-brown);
        transform: translateY(-2px);
    }
    
    .success-card, .pending-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        padding: 2rem;
        border-radius: 20px;
        text-align: center;
    }
    
    .success-icon, .pending-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    .success-icon { background: rgba(40, 167, 69, 0.1); }
    .success-icon i { font-size: 3rem; color: #28a745; }
    .pending-icon { background: rgba(255, 193, 7, 0.1); }
    .pending-icon i { font-size: 3rem; color: #ffc107; }
    
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
    
    @media (max-width: 576px) {
        .bank-item, .ewallet-item {
            flex-direction: column;
            text-align: center;
        }
        
        .qris-code {
            width: 150px;
            height: 150px;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Toggle payment instructions based on selected method
    function updatePaymentInstructions() {
        const selectedMethod = $('input[name="payment_method"]:checked').val();
        
        // Hide all instruction panels
        $('.instruction-panel').hide();
        
        // Show selected instruction panel
        if (selectedMethod === 'bank_transfer') {
            $('#bankTransferInstructions').show();
        } else if (selectedMethod === 'qris') {
            $('#qrisInstructions').show();
        } else if (selectedMethod === 'e_wallet') {
            $('#ewalletInstructions').show();
        }
    }
    
    // Listen for payment method change
    $('input[name="payment_method"]').on('change', function() {
        updatePaymentInstructions();
    });
    
    // Copy button functionality
    $(document).on('click', '.btn-copy', function(e) {
        e.preventDefault();
        const textToCopy = $(this).data('copy');
        navigator.clipboard.writeText(textToCopy).then(() => {
            const originalText = $(this).html();
            $(this).html('✓ Tersalin!');
            setTimeout(() => {
                $(this).html(originalText);
            }, 2000);
        });
    });
    
    // Upload area handlers
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('paymentProof');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const selectedFileName = document.getElementById('selectedFileName');
    const clearFileBtn = document.getElementById('clearFileBtn');
    
    if (uploadArea) {
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--gold-brown)';
            uploadArea.style.background = 'rgba(192, 133, 82, 0.05)';
        });
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'rgba(192, 133, 82, 0.3)';
            uploadArea.style.background = 'rgba(192, 133, 82, 0.02)';
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                updateFileName(file.name);
            }
            uploadArea.style.borderColor = 'rgba(192, 133, 82, 0.3)';
            uploadArea.style.background = 'rgba(192, 133, 82, 0.02)';
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                updateFileName(this.files[0].name);
            } else {
                fileNameDisplay.style.display = 'none';
            }
        });
    }
    
    if (clearFileBtn) {
        clearFileBtn.addEventListener('click', () => {
            fileInput.value = '';
            fileNameDisplay.style.display = 'none';
        });
    }
    
    function updateFileName(name) {
        selectedFileName.textContent = name;
        fileNameDisplay.style.display = 'block';
    }
    
    // Initialize instructions on page load
    $(document).ready(function() {
        updatePaymentInstructions();
    });
</script>

<?php include '../includes/footer.php'; ?>