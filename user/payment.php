<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, t.payment_method, t.id as transaction_id, t.payment_status, t.payment_proof, t.payment_note
    FROM orders o 
    JOIN transactions t ON o.id = t.order_id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: order_part.php');
    exit();
}

// Daftar metode pembayaran yang auto confirm
$auto_confirm_methods = ['bank_transfer', 'qris', 'e_wallet'];

// Handle payment confirmation
$confirm_error = '';
$confirm_success = '';
$payment_note = ''; // Initialize variable
$payment_method = $order['payment_method'];
$payment_proof = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $confirm_error = 'CSRF token tidak valid. Silakan coba lagi.';
    }
    
    if (empty($confirm_error)) {
        $payment_note = trim($_POST['payment_note'] ?? '');
        
        // Handle payment proof upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
            $file = $_FILES['payment_proof'];
            
            // Validasi file
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if ($file['size'] > $max_size) {
                $confirm_error = 'Ukuran file terlalu besar! Maksimal 2MB.';
            } elseif (!in_array($file_ext, $allowed_ext)) {
                $confirm_error = 'Format file tidak didukung! Gunakan JPG, PNG, PDF.';
            } else {
                // Create directory if not exists
                if (!file_exists('../uploads/payment_proofs/')) {
                    mkdir('../uploads/payment_proofs/', 0777, true);
                }
                
                // Generate unique filename
                $new_filename = 'payment_' . $order['id'] . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                $upload_path = '../uploads/payment_proofs/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $payment_proof = $new_filename;
                } else {
                    $confirm_error = 'Gagal mengupload bukti pembayaran. Silakan coba lagi.';
                }
            }
        } else {
            $confirm_error = 'Silakan upload bukti pembayaran!';
        }
    }
    
    // Process payment if no upload error
    if (empty($confirm_error) && !empty($payment_proof)) {
        // Calculate total amount for transaction
        $total_amount = $order['total_price'];
        
        // Logika berbeda berdasarkan metode pembayaran
        if (in_array($payment_method, $auto_confirm_methods)) {
            // Auto confirm: langsung ubah status menjadi 'paid' dengan fee calculation
            $new_payment_status = 'paid';
            $status_message = 'Pembayaran berhasil dikonfirmasi! Pesanan Anda akan segera diproses.';
            
            // Calculate fee breakdown for auto-confirmed payments
            $fee_breakdown = calculateFeeBreakdown($total_amount);
        } else {
            // Butuh konfirmasi admin (COD) - tanpa fee calculation dulu
            $new_payment_status = 'pending_confirmation';
            $status_message = 'Bukti pembayaran berhasil diupload! Admin akan melakukan konfirmasi dalam 1x24 jam.';
            $fee_breakdown = null;
        }
        
        // Update transaction with payment proof and new status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET payment_proof = ?, 
                payment_note = ?, 
                payment_status = ?,
                fee_percentage = ?,
                fee_amount = ?,
                technician_earning = ?,
                paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE NULL END
            WHERE id = ?
        ");
        
        $fee_pct = $fee_breakdown ? $fee_breakdown['fee_percentage'] : null;
        $fee_amt = $fee_breakdown ? $fee_breakdown['fee_amount'] : null;
        $tech_earn = $fee_breakdown ? $fee_breakdown['technician_earning'] : null;
        
        if ($stmt->execute([$payment_proof, $payment_note, $new_payment_status, $fee_pct, $fee_amt, $tech_earn, $new_payment_status, $order['transaction_id']])) {
            // Jika status paid, update juga status order
            if ($new_payment_status == 'paid') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
                $stmt->execute([$order['id']]);
            }
            
            $confirm_success = $status_message;
            
            // Refresh order data
            $stmt = $pdo->prepare("
                SELECT o.*, t.payment_method, t.id as transaction_id, t.payment_status, t.payment_proof, t.payment_note, t.fee_percentage, t.fee_amount
                FROM orders o 
                JOIN transactions t ON o.id = t.order_id 
                WHERE o.id = ? AND o.user_id = ?
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            $order = $stmt->fetch();
        } else {
            $confirm_error = 'Gagal menyimpan bukti pembayaran. Silakan coba lagi.';
        }
    }
}


// Generate virtual account number based on payment method
$va_number = '';
if ($order['payment_method'] == 'bank_transfer') {
    $va_number = '888' . str_pad($order['id'], 12, '0', STR_PAD_LEFT);
} elseif ($order['payment_method'] == 'e_wallet') {
    $va_number = '882' . str_pad($order['id'], 12, '0', STR_PAD_LEFT);
}

// QR Code dengan link ke YouTube
$qr_data = 'https://quickchart.io/qr?text=' . urlencode('https://www.youtube.com/watch?v=dQw4w9WgXcQ') . '&size=200' . $order['id'] . '-' . time();

// Status badge mapping untuk payment_status
$payment_status_badge = [
    'pending' => '<span class="badge bg-warning">Menunggu Pembayaran</span>',
    'pending_confirmation' => '<span class="badge bg-info">Menunggu Konfirmasi Admin</span>',
    'paid' => '<span class="badge bg-success">Sudah Dibayar</span>'
];

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="my_orders.php" class="btn btn-outline-gold rounded-4">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Pesanan Saya
                </a>
            </div>
            
            <!-- Payment Header -->
            <div class="text-center mb-4">
                <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Selesaikan Pembayaran</h1>
                <p class="text-muted">Konfirmasi pembayaran untuk menyelesaikan pesanan Anda</p>
            </div>
            
            <!-- Order Info -->
            <div class="payment-card rounded-4 mb-4">
                <div class="payment-card-body p-4">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <small class="text-muted">Nomor Pesanan</small>
                            <p class="fw-bold fs-5 mb-0">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <small class="text-muted">Total Pembayaran</small>
                            <p class="fw-bold fs-4 mb-0" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Status</small>
                            <p class="mb-0"><?php echo $payment_status_badge[$order['payment_status']] ?? '<span class="badge bg-secondary">' . ucfirst($order['payment_status']) . '</span>'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rincian Biaya -->
            <?php 
            // Hitung total part jika ada
            $used_parts_data = [];
            $parts_total = 0;
            if (isset($order['used_parts']) && !empty($order['used_parts'])) {
                $used_parts_data = json_decode($order['used_parts'], true);
                if (is_array($used_parts_data)) {
                    foreach ($used_parts_data as $part) {
                        $parts_total += $part['price'] * $part['quantity'];
                    }
                }
            }
            $service_cost = $order['total_price'] - $parts_total;
            ?>
            
            <div class="payment-card rounded-4 mb-4">
                <div class="payment-card-header">
                    <i class="fas fa-receipt me-2" style="color: var(--gold-brown);"></i>
                    Rincian Pembayaran
                </div>
                <div class="payment-card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <span class="text-muted">Biaya Jasa Servis</span>
                                <span class="fw-bold"><?php echo formatCurrency($service_cost); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                                <span class="text-muted">Biaya Part</span>
                                <span class="fw-bold"><?php echo formatCurrency($parts_total); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($used_parts_data) > 0 && is_array($used_parts_data)): ?>
                        <div class="mt-3">
                            <label class="text-muted small mb-2">Detail Part:</label>
                            <div class="bg-light rounded-4 p-3">
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
                                        <?php foreach ($used_parts_data as $part): ?>
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
                                            <td colspan="3" class="text-end fw-bold">Total Part:</a>
                                            <td class="fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency($parts_total); ?></a>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <hr class="my-3">
                    
                    <div class="d-flex justify-content-between">
                        <span class="fs-5 fw-bold">Total Yang Harus Dibayar</span>
                        <span class="fs-3 fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency($order['total_price']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Jika sudah dibayar -->
            <?php if ($order['payment_status'] == 'paid'): ?>
                <div class="payment-card rounded-4 mb-4 text-center">
                    <div class="payment-card-body p-5">
                        <i class="fas fa-check-circle fa-4x mb-3" style="color: #28a745;"></i>
                        <h3>Pembayaran Sudah Dikonfirmasi!</h3>
                        <p class="text-muted">Terima kasih, pesanan Anda akan segera diproses.</p>
                        <a href="my_orders.php" class="btn btn-gold rounded-4 mt-3">
                            <i class="fas fa-list me-2"></i> Lihat Pesanan Saya
                        </a>
                    </div>
                </div>
            <?php else: ?>
            
            <!-- Payment Instructions Based on Method -->
            <?php if ($order['payment_method'] == 'bank_transfer'): ?>
                <!-- Bank Transfer Payment -->
                <div class="payment-card rounded-4 mb-4">
                    <div class="payment-card-header">
                        <i class="fas fa-university me-2" style="color: var(--gold-brown);"></i>
                        Transfer Bank
                    </div>
                    <div class="payment-card-body p-4">
                        <div class="alert alert-info rounded-4 mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Silakan transfer sesuai dengan total pembayaran ke salah satu rekening berikut
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="bank-card p-3 rounded-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <i class="fas fa-university fa-2x" style="color: #0055a4;"></i>
                                        <div>
                                            <strong>Bank BCA</strong>
                                            <p class="small text-muted mb-0">a.n Loaz Industries</p>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="va-number"><?php echo $va_number; ?></div>
                                        <button class="btn btn-sm btn-outline-gold mt-2 copy-va" data-va="<?php echo $va_number; ?>">
                                            <i class="fas fa-copy me-1"></i> Salin Nomor VA
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bank-card p-3 rounded-4">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <i class="fas fa-university fa-2x" style="color: #dd0000;"></i>
                                        <div>
                                            <strong>Bank Mandiri</strong>
                                            <p class="small text-muted mb-0">a.n Loaz Industries</p>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="va-number">1234567890</div>
                                        <button class="btn btn-sm btn-outline-gold mt-2 copy-va" data-va="1234567890">
                                            <i class="fas fa-copy me-1"></i> Salin Nomor Rekening
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($order['payment_method'] == 'qris'): ?>
                <!-- QRIS Payment -->
                <div class="payment-card rounded-4 mb-4">
                    <div class="payment-card-header">
                        <i class="fas fa-qrcode me-2" style="color: var(--gold-brown);"></i>
                        QRIS - Scan to Pay
                    </div>
                    <div class="payment-card-body p-4 text-center">
                        <div class="qris-container mb-4">
                            <img src="<?php echo $qr_data; ?>" alt="QR Code" class="qris-code" style="cursor: pointer;" onclick="window.open('<?php echo $qr_data; ?>', '_blank')">
                            <p class="small text-muted mt-2">Klik QR Code untuk membuka pembayaran</p>
                        </div>
                        <p class="mb-2">Scan QR Code di atas menggunakan aplikasi:</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap mb-4">
                            <span class="badge-app"><i class="fab fa-google-play"></i> GoPay</span>
                            <span class="badge-app"><i class="fas fa-wallet"></i> OVO</span>
                            <span class="badge-app"><i class="fas fa-wallet"></i> Dana</span>
                            <span class="badge-app"><i class="fas fa-university"></i> LinkAja</span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($order['payment_method'] == 'e_wallet'): ?>
                <!-- E-Wallet Payment -->
                <div class="payment-card rounded-4 mb-4">
                    <div class="payment-card-header">
                        <i class="fas fa-wallet me-2" style="color: var(--gold-brown);"></i>
                        E-Wallet
                    </div>
                    <div class="payment-card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="ewallet-card p-3 rounded-4 text-center">
                                    <i class="fab fa-google-play fa-3x mb-2" style="color: #00AA5E;"></i>
                                    <h6>GoPay</h6>
                                    <div class="va-number">0888<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <button class="btn btn-sm btn-outline-gold mt-2 copy-va" data-va="0888<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>">
                                        <i class="fas fa-copy me-1"></i> Salin ID
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="ewallet-card p-3 rounded-4 text-center">
                                    <i class="fas fa-wallet fa-3x mb-2" style="color: #660099;"></i>
                                    <h6>OVO</h6>
                                    <div class="va-number">0887<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <button class="btn btn-sm btn-outline-gold mt-2 copy-va" data-va="0887<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>">
                                        <i class="fas fa-copy me-1"></i> Salin ID
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($order['payment_method'] == 'cod'): ?>
                <!-- COD Payment -->
                <div class="payment-card rounded-4 mb-4">
                    <div class="payment-card-header">
                        <i class="fas fa-hand-holding-usd me-2" style="color: var(--gold-brown);"></i>
                        COD (Bayar di Tempat)
                    </div>
                    <div class="payment-card-body p-4 text-center">
                        <i class="fas fa-check-circle fa-4x mb-3" style="color: #28a745;"></i>
                        <h4>Pesanan akan dikirim dengan COD</h4>
                        <p class="text-muted mb-4">Anda dapat membayar langsung saat barang diterima</p>
                        <div class="alert alert-info rounded-4 text-start">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informasi COD:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                <li>Bayar langsung kepada kurir saat barang diterima</li>
                                <li>Siapkan uang pas untuk memudahkan transaksi</li>
                                <li>COD hanya tersedia untuk area Jabodetabek</li>
                            </ul>
                        </div>
                        <a href="my_orders.php" class="btn btn-gold rounded-4 mt-3">
                            <i class="fas fa-list me-2"></i> Kembali ke Pesanan
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Konfirmasi Pembayaran Section (for non-COD) -->
            <?php if ($order['payment_method'] != 'cod'): ?>
                <div class="payment-card rounded-4 mb-4">
                    <div class="payment-card-header">
                        <i class="fas fa-upload me-2" style="color: var(--gold-brown);"></i>
                        Konfirmasi Pembayaran
                    </div>
                    <div class="payment-card-body p-4">
                        <?php if ($order['payment_status'] == 'pending_confirmation'): ?>
                            <div class="alert alert-info rounded-4 text-center">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Bukti pembayaran Anda sedang diverifikasi oleh admin.</strong>
                                <p class="mb-0 mt-2 small">Proses verifikasi maksimal 1x24 jam.</p>
                            </div>
                            <?php if ($order['payment_proof']): ?>
                                <div class="text-center mt-3">
                                    <a href="../uploads/payment_proofs/<?php echo $order['payment_proof']; ?>" target="_blank" class="btn btn-outline-gold rounded-4">
                                        <i class="fas fa-eye me-2"></i> Lihat Bukti Pembayaran
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($order['payment_status'] == 'paid'): ?>
                            <div class="alert alert-success rounded-4 text-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Pembayaran Anda telah dikonfirmasi!</strong>
                                <p class="mb-0 mt-2 small">Terima kasih, pesanan Anda akan segera diproses.</p>
                            </div>
                        <?php else: ?>
                            <?php if ($confirm_success): ?>
                                <div class="alert alert-success rounded-4">
                                    <i class="fas fa-check-circle me-2"></i> <?php echo $confirm_success; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($confirm_error): ?>
                                <div class="alert alert-danger rounded-4">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $confirm_error; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-warning rounded-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Setelah melakukan pembayaran, silakan upload bukti transfer di sini.</strong>
                                <?php if (in_array($order['payment_method'], $auto_confirm_methods)): ?>
                                    <p class="mb-0 mt-2 small text-success">
                                        <i class="fas fa-check-circle me-1"></i> 
                                        Pembayaran akan langsung dikonfirmasi secara otomatis setelah upload bukti.
                                    </p>
                                <?php else: ?>
                                    <p class="mb-0 mt-2 small">
                                        <i class="fas fa-clock me-1"></i> 
                                        Pembayaran akan dikonfirmasi oleh admin dalam 1x24 jam.
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo csrfField(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Upload Bukti Pembayaran <span class="text-danger">*</span></label>
                                    <input type="file" name="payment_proof" class="form-control" accept="image/*,application/pdf" required>
                                    <small class="text-muted">Format: JPG, PNG, PDF. Maksimal 2MB</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Catatan (Opsional)</label>
                                    <textarea name="payment_note" class="form-control" rows="3" placeholder="Contoh: Transfer via BCA a.n. John Doe"></textarea>
                                </div>
                                <button type="submit" name="confirm_payment" class="btn btn-gold w-100 rounded-4 py-3">
                                    <i class="fas fa-paper-plane me-2"></i> Konfirmasi Pembayaran
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Buat folder uploads/payment_proofs jika belum ada -->
<?php
if (!file_exists('../uploads/payment_proofs/')) {
    mkdir('../uploads/payment_proofs/', 0777, true);
}
?>

<style>
    .payment-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .payment-card-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .payment-card-body {
        background: white;
    }
    
    .bank-card, .ewallet-card {
        background: rgba(192, 133, 82, 0.03);
        border: 1px solid rgba(192, 133, 82, 0.1);
        transition: all 0.3s ease;
    }
    
    .bank-card:hover, .ewallet-card:hover {
        border-color: var(--gold-brown);
    }
    
    .va-number {
        font-family: monospace;
        font-size: 1.2rem;
        font-weight: bold;
        letter-spacing: 2px;
        background: white;
        padding: 0.5rem;
        border-radius: 8px;
        border: 1px dashed var(--gold-brown);
    }
    
    .qris-code {
        width: 200px;
        height: 200px;
        border: 1px solid rgba(192, 133, 82, 0.2);
        border-radius: 20px;
        padding: 10px;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .qris-code:hover {
        transform: scale(1.05);
    }
    
    .badge-app {
        background: rgba(192, 133, 82, 0.1);
        padding: 0.4rem 1rem;
        border-radius: 50px;
        font-size: 0.8rem;
        color: var(--gold-brown);
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
    
    .alert-info {
        background: rgba(23, 162, 184, 0.1);
        border: none;
        color: #17a2b8;
    }
    
    .alert-warning {
        background: rgba(255, 193, 7, 0.1);
        border: none;
        color: #856404;
    }
    
    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: none;
        color: #28a745;
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }
    
    .bg-warning { background: #ffc107 !important; color: #000; }
    .bg-info { background: #17a2b8 !important; color: #fff; }
    .bg-success { background: #28a745 !important; color: #fff; }
    
    .border-bottom {
        border-bottom: 1px solid rgba(192, 133, 82, 0.15) !important;
    }
</style>

<script>
// Copy VA number functionality
document.querySelectorAll('.copy-va').forEach(btn => {
    btn.addEventListener('click', function() {
        let vaNumber = this.getAttribute('data-va');
        navigator.clipboard.writeText(vaNumber).then(() => {
            let originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check me-1"></i> Tersalin!';
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        }).catch(() => {
            alert('Gagal menyalin, silakan salin manual');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>