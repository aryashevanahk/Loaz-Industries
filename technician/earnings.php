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

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Build query for earnings FROM technician_earnings table
$query = "
    SELECT te.*, 
           t.payment_status,
           t.confirmed_at,
           s.device,
           u.name as customer_name
    FROM technician_earnings te
    JOIN transactions t ON te.transaction_id = t.id
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE te.technician_id = ?
";

$params = [$tech_db_id];

if ($filter == 'month' && $start_date && $end_date) {
    $query .= " AND DATE(te.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$query .= " ORDER BY te.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$earnings = $stmt->fetchAll();

// Calculate totals from technician_earnings
$total_gross = 0;
$total_fees = 0;
$total_net = 0;
$transaction_count = 0;

foreach ($earnings as $earning) {
    $total_gross += $earning['amount'];
    $total_fees += $earning['fee_amount'];
    $total_net += $earning['net_amount'];
    $transaction_count++;
}

// ============================================
// HAPUS FUNGSI-FUNGSI DI BAWAH INI!
// Fungsi sudah ada di includes/functions.php
// Jangan deklarasikan ulang di sini!
// ============================================
// function calculateFeePercentage($amount) { ... }   -> HAPUS
// function calculateFeeBreakdown($amount) { ... }    -> HAPUS
// function formatFeePercentage($percentage) { ... }  -> HAPUS

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
                        Riwayat Pendapatan
                    </li>
                </ol>
            </nav>
            <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Riwayat Pendapatan</h1>
            <p class="text-muted">Lihat riwayat pendapatan dari servis yang telah selesai</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-gold rounded-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="summary-card p-3 rounded-4 text-center">
                <i class="fas fa-coins fa-2x mb-2" style="color: #28a745;"></i>
                <h3 class="mb-0 fw-bold text-success"><?php echo formatCurrency($total_net); ?></h3>
                <p class="text-muted small mb-0">Pendapatan Bersih</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card p-3 rounded-4 text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2" style="color: var(--gold-brown);"></i>
                <h3 class="mb-0 fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency($total_gross); ?></h3>
                <p class="text-muted small mb-0">Total Bruto</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card p-3 rounded-4 text-center">
                <i class="fas fa-percent fa-2x mb-2" style="color: #dc3545;"></i>
                <h3 class="mb-0 fw-bold text-danger"><?php echo formatCurrency($total_fees); ?></h3>
                <p class="text-muted small mb-0">Total Fee Admin</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card p-3 rounded-4 text-center">
                <i class="fas fa-receipt fa-2x mb-2" style="color: #17a2b8;"></i>
                <h3 class="mb-0 fw-bold" style="color: #17a2b8;"><?php echo $transaction_count; ?></h3>
                <p class="text-muted small mb-0">Jumlah Transaksi</p>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Filter</label>
                    <select name="filter" class="form-select">
                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>
                        <option value="month" <?php echo $filter == 'month' ? 'selected' : ''; ?>>Periode Tertentu</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-gold w-100 rounded-4">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (count($earnings) == 0): ?>
        <!-- Empty State -->
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="fas fa-money-bill-wave fa-4x text-muted mb-3"></i>
                <h3>Belum Ada Data Pendapatan</h3>
                <p class="text-muted">Belum ada servis selesai dengan estimasi biaya</p>
                <a href="my_services.php" class="btn btn-gold rounded-4 mt-3">
                    <i class="fas fa-list me-2"></i> Lihat Servis Saya
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Earnings Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">No</th>
                                <th>Tanggal</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Total Bruto</th>
                                <th>Fee Admin</th>
                                <th>Pendapatan Bersih</th>
                                <th>Status</th>
                                <th>Tanggal Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php foreach ($earnings as $earning): ?>
                                <tr>
                                    <td class="ps-4"><?php echo $no++; ?></td>
                                    <td><?php echo formatDate($earning['created_at'], 'd/m/Y'); ?></td>
                                    <td><?php echo htmlspecialchars($earning['customer_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($earning['device'] ?? '-'); ?></td>
                                    <td class="fw-bold" style="color: var(--gold-brown);">
                                        <?php echo formatCurrency($earning['amount']); ?>
                                    </td>
                                    <td class="text-danger">
                                        <small><?php echo formatCurrency($earning['fee_amount']); ?></small>
                                        <br>
                                        <small>(<?php echo formatFeePercentage($earning['fee_percentage']); ?>)</small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <?php echo formatCurrency($earning['net_amount']); ?>
                                    </td>
                                    <td>
                                        <?php if ($earning['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success px-3 py-2 rounded-pill">
                                                <i class="fas fa-check-circle me-1"></i> Dibayar
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning px-3 py-2 rounded-pill">
                                                <i class="fas fa-clock me-1"></i> Menunggu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $earning['confirmed_at'] ? formatDate($earning['confirmed_at'], 'd/m/Y') : '-'; ?>
                                    </td>
                                 </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end ps-4">TOTAL</td>
                                <td style="color: var(--gold-brown);"><?php echo formatCurrency($total_gross); ?></td>
                                <td class="text-danger"><?php echo formatCurrency($total_fees); ?></td>
                                <td class="text-success"><?php echo formatCurrency($total_net); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .summary-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        transition: all 0.3s ease;
        border-radius: 20px;
    }
    
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(75, 46, 43, 0.1);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th,
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .data-table th {
        text-align: left;
        font-weight: 600;
        color: var(--medium-brown);
        font-size: 0.85rem;
    }
    
    .data-table td {
        color: var(--dark-brown);
        font-size: 0.85rem;
    }
    
    .data-table tbody tr:hover {
        background: rgba(192, 133, 82, 0.02);
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
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
    
    @media (max-width: 768px) {
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            font-size: 0.75rem;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>