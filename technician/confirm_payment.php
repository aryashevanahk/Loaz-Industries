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

// Get technician's database id
$stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
$stmt->execute([$technician_id]);
$tech = $stmt->fetch();
$tech_db_id = $tech['id'] ?? null;

// Get pending payments for technician's services with fee calculation
$stmt = $pdo->prepare("
    SELECT t.*, 
           s.device, 
           s.problem, 
           u.name as customer_name, 
           u.phone as customer_phone,
           (SELECT SUM(total_amount) FROM transactions WHERE id = t.id) as total_amount
    FROM transactions t
    JOIN services s ON t.service_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE s.technician_id = ? AND t.payment_status = 'pending_confirmation'
    ORDER BY t.created_at DESC
");
$stmt->execute([$tech_db_id]);
$pending_payments = $stmt->fetchAll();

// Calculate fee for each payment
foreach ($pending_payments as &$payment) {
    $amount = $payment['total_amount'];
    $payment['fee_percentage'] = calculateFeePercentage($amount);
    $payment['fee_amount'] = $amount * ($payment['fee_percentage'] / 100);
    $payment['technician_earning'] = $amount - $payment['fee_amount'];
}

// Handle confirm payment by technician
if (isset($_POST['confirm_payment_technician'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Location: confirm_payment.php?msg=csrf_error');
        exit();
    }
    
    $transaction_id = (int)$_POST['transaction_id'];
    $total_amount = (float)$_POST['total_amount'];
    
    $pdo->beginTransaction();
    
    try {
        // Check if transaction still exists and has correct status (prevent race condition)
        $stmt = $pdo->prepare("
            SELECT payment_status FROM transactions WHERE id = ? FOR UPDATE
        ");
        $stmt->execute([$transaction_id]);
        $transaction_check = $stmt->fetch();
        
        if (!$transaction_check) {
            throw new Exception('Transaction not found');
        }
        
        if ($transaction_check['payment_status'] !== 'pending_confirmation') {
            throw new Exception('Transaction is no longer pending. It has been processed.');
        }
        
        // Calculate fee based on total amount using helper function
        $fee_breakdown = calculateFeeBreakdown($total_amount);
        $fee_percentage = $fee_breakdown['fee_percentage'];
        $fee_amount = $fee_breakdown['fee_amount'];
        $technician_earning = $fee_breakdown['technician_earning'];
        
        // Update transaction to paid with fee details
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET payment_status = 'paid', 
                paid_at = NOW(),
                confirmed_by = ?,
                confirmed_at = NOW(),
                fee_percentage = ?,
                fee_amount = ?,
                technician_earning = ?
            WHERE id = ?
        ");
        $stmt->execute([$technician_id, $fee_percentage, $fee_amount, $technician_earning, $transaction_id]);
        
        // Create earning record
        createTechnicianEarning($pdo, $tech_db_id, $transaction_id, $total_amount, $fee_percentage, $fee_amount, $technician_earning);
        
        $pdo->commit();
        
        // Redirect with success message including fee info
        header('Location: confirm_payment.php?msg=confirmed&fee=' . urlencode($fee_percentage) . '&earning=' . urlencode($technician_earning));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payment confirmation error: " . $e->getMessage());
        header('Location: confirm_payment.php?msg=error');
        exit();
    }
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'confirmed') {
        $fee = isset($_GET['fee']) ? (float)$_GET['fee'] : 0;
        $earning = isset($_GET['earning']) ? (float)$_GET['earning'] : 0;
        $message = '<div class="alert alert-success rounded-4">
            <i class="fas fa-check-circle me-2"></i>Pembayaran berhasil dikonfirmasi!
            <div class="mt-2 small">
                <strong>Detail Potongan:</strong><br>
                - Fee Admin: ' . number_format($fee, 2) . '%<br>
                - Pendapatan Anda: ' . formatCurrency($earning) . '
            </div>
        </div>';
    } elseif ($_GET['msg'] == 'error') {
        $message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>Gagal konfirmasi pembayaran!</div>';
    } elseif ($_GET['msg'] == 'csrf_error') {
        $message = '<div class="alert alert-danger rounded-4"><i class="fas fa-exclamation-circle me-2"></i>CSRF token tidak valid. Silakan coba lagi.</div>';
    }
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
                        Konfirmasi Pembayaran
                    </li>
                </ol>
            </nav>
            <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Konfirmasi Pembayaran</h1>
            <p class="text-muted">Konfirmasi pembayaran dari customer untuk servis yang telah selesai</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Fee Information Card -->
    <div class="fee-info-card rounded-4 mb-4">
        <div class="fee-info-header">
            <i class="fas fa-percent me-2"></i>
            Informasi Potongan Biaya (Sliding Fee)
        </div>
        <div class="fee-info-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="fee-tier">
                        <small class="text-muted">Pembayaran ≤ Rp 1.000.000</small>
                        <strong class="d-block fs-4" style="color: var(--gold-brown);">4%</strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fee-tier">
                        <small class="text-muted">Pembayaran Rp 1.000.000 - 2.000.000</small>
                        <strong class="d-block fs-4" style="color: var(--gold-brown);">4% - 15%</strong>
                        <small>(Progresif)</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="fee-tier">
                        <small class="text-muted">Pembayaran ≥ Rp 2.000.000</small>
                        <strong class="d-block fs-4" style="color: var(--gold-brown);">15%</strong>
                    </div>
                </div>
            </div>
            <div class="alert alert-info-custom mt-3 mb-0">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Perhitungan Potongan:</strong> Fee dihitung secara progresif. 
                Semakin besar nilai transaksi, semakin besar persentase fee (maksimal 15%).
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <?php if (count($pending_payments) == 0): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
                <h3>Tidak Ada Pembayaran Tertunda</h3>
                <p class="text-muted">Semua pembayaran sudah dikonfirmasi</p>
                <a href="dashboard.php" class="btn btn-gold rounded-4 mt-3">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($pending_payments as $payment): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="payment-card rounded-4">
                        <div class="payment-card-header p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-warning px-3 py-2 rounded-pill">
                                        <i class="fas fa-clock me-1"></i> Menunggu Konfirmasi
                                    </span>
                                </div>
                                <small class="text-muted">#<?php echo $payment['id']; ?></small>
                            </div>
                        </div>
                        <div class="payment-card-body p-3">
                            <h5 class="fw-semibold mb-2">
                                <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
                                <?php echo htmlspecialchars($payment['device']); ?>
                            </h5>
                            <div class="mb-2">
                                <i class="fas fa-user me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo htmlspecialchars($payment['customer_name']); ?></small>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo htmlspecialchars($payment['customer_phone'] ?? '-'); ?></small>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-calendar me-2" style="color: var(--gold-brown);"></i>
                                <small><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></small>
                            </div>
                            
                            <!-- Rincian Biaya dengan Fee -->
                            <div class="amount-box p-2 rounded-3 mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Total Pembayaran</small>
                                    <strong class="text-gold"><?php echo formatCurrency($payment['total_amount']); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Fee Admin (<?php echo formatFeePercentage($payment['fee_percentage']); ?>)</small>
                                    <small class="text-danger">- <?php echo formatCurrency($payment['fee_amount']); ?></small>
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between">
                                    <small class="fw-semibold">Pendapatan Anda</small>
                                    <strong class="text-success"><?php echo formatCurrency($payment['technician_earning']); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ($payment['payment_proof']): ?>
                                <div class="proof-box p-2 rounded-3 mb-3">
                                    <small class="text-muted"><i class="fas fa-image me-1"></i> Bukti Pembayaran:</small>
                                    <div class="mt-2">
                                        <a href="../uploads/payment_proofs/<?php echo $payment['payment_proof']; ?>" target="_blank" class="btn btn-sm btn-outline-gold w-100">
                                            <i class="fas fa-eye me-2"></i> Lihat Bukti Transfer
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <button class="btn btn-gold w-100 rounded-4 mt-2" 
                                    onclick="confirmPayment(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['device']); ?>', <?php echo $payment['total_amount']; ?>, <?php echo $payment['fee_percentage']; ?>, <?php echo $payment['fee_amount']; ?>, <?php echo $payment['technician_earning']; ?>)"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal">
                                <i class="fas fa-check-circle me-2"></i> Konfirmasi Pembayaran
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title">Konfirmasi Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="transaction_id" id="confirm_transaction_id">
                    <input type="hidden" name="total_amount" id="confirm_total_amount">
                    <div class="text-center mb-3">
                        <i class="fas fa-credit-card fa-3x mb-2" style="color: var(--gold-brown);"></i>
                        <h6 id="confirm_device_name"></h6>
                    </div>
                    
                    <!-- Ringkasan Biaya di Modal -->
                    <div class="fee-summary-box p-3 rounded-3 mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Pembayaran</span>
                            <strong id="modal_total_amount" class="text-gold"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Fee Admin</span>
                            <strong id="modal_fee_amount" class="text-danger"></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Pendapatan Anda</span>
                            <strong id="modal_technician_earning" class="text-success fs-5"></strong>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Pastikan bukti pembayaran sudah valid sebelum mengkonfirmasi.
                    </div>
                    <p class="mb-0">Apakah Anda yakin ingin mengkonfirmasi pembayaran ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="confirm_payment_technician" class="btn btn-gold">Ya, Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .payment-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .payment-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(75, 46, 43, 0.1);
    }
    
    .amount-box {
        background: rgba(192, 133, 82, 0.08);
    }
    
    .proof-box {
        background: rgba(192, 133, 82, 0.05);
    }
    
    .fee-info-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
    }
    
    .fee-info-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .fee-info-body {
        padding: 1.5rem;
    }
    
    .fee-tier {
        text-align: center;
        padding: 1rem;
        background: rgba(192, 133, 82, 0.03);
        border-radius: 12px;
    }
    
    .fee-summary-box {
        background: rgba(192, 133, 82, 0.05);
    }
    
    .alert-info-custom {
        background: rgba(23, 162, 184, 0.1);
        border: none;
        color: #17a2b8;
        border-radius: 12px;
        padding: 1rem;
    }
    
    .text-gold {
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
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
</style>

<script>
    function confirmPayment(id, deviceName, totalAmount, feePercentage, feeAmount, technicianEarning) {
        document.getElementById('confirm_transaction_id').value = id;
        document.getElementById('confirm_total_amount').value = totalAmount;
        document.getElementById('confirm_device_name').innerHTML = '<i class="fas fa-microchip me-1"></i> ' + deviceName;
        
        // Format currency untuk modal
        const formatRupiah = (value) => {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(value);
        };
        
        document.getElementById('modal_total_amount').innerHTML = formatRupiah(totalAmount);
        document.getElementById('modal_fee_amount').innerHTML = '-' + formatRupiah(feeAmount) + ' (' + feePercentage.toFixed(2) + '%)';
        document.getElementById('modal_technician_earning').innerHTML = formatRupiah(technicianEarning);
    }
</script>

<?php include '../includes/footer.php'; ?>