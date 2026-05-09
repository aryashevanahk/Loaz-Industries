<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_method = isset($_GET['method']) ? $_GET['method'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get pending count for badge
$stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_status = 'pending_confirmation'");
$pending_count = $stmt->fetch()['count'] ?? 0;

// Handle confirm payment from user (admin konfirmasi)
if (isset($_POST['confirm_payment'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Location: transactions.php?msg=csrf_error');
        exit();
    }
    
    $id = (int)$_POST['transaction_id'];
    $total_amount = (float)$_POST['total_amount'];
    
    $pdo->beginTransaction();
    
    try {
        // Check transaction status with lock
        $stmt = $pdo->prepare("SELECT payment_status, service_id, order_id FROM transactions WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $trans_check = $stmt->fetch();
        
        if (!$trans_check) {
            throw new Exception('Transaction not found');
        }
        
        if ($trans_check['payment_status'] !== 'pending_confirmation') {
            throw new Exception('Transaction is no longer pending');
        }
        
        // Calculate fee breakdown using function from includes/functions.php
        $fee_breakdown = calculateFeeBreakdown($total_amount);
        
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
        $stmt->execute([
            $_SESSION['user_id'], 
            $fee_breakdown['fee_percentage'],
            $fee_breakdown['fee_amount'],
            $fee_breakdown['technician_earning'],
            $id
        ]);
        
        // If this is a service payment, create technician earning record
        if ($trans_check['service_id']) {
            $stmt = $pdo->prepare("SELECT technician_id FROM services WHERE id = ?");
            $stmt->execute([$trans_check['service_id']]);
            $service = $stmt->fetch();
            
            if ($service && $service['technician_id']) {
                // Get technician database id
                $stmt = $pdo->prepare("SELECT id FROM technicians WHERE id = ?");
                $stmt->execute([$service['technician_id']]);
                $tech = $stmt->fetch();
                
                if ($tech) {
                    createTechnicianEarning($pdo, $tech['id'], $id, $total_amount, 
                        $fee_breakdown['fee_percentage'], $fee_breakdown['fee_amount'], 
                        $fee_breakdown['technician_earning']);
                }
            }
        }
        
        // Update order status if exists
        if ($trans_check['order_id']) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
            $stmt->execute([$trans_check['order_id']]);
        }
        
        $pdo->commit();
        header('Location: transactions.php?msg=confirmed');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Admin payment confirmation error: " . $e->getMessage());
        header('Location: transactions.php?msg=error');
        exit();
    }
}

// Handle update payment status (manual update)
if (isset($_POST['update_payment'])) {
    $id = (int)$_POST['transaction_id'];
    $payment_status = $_POST['payment_status'];
    
    $pdo->beginTransaction();
    
    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT total_amount, service_id FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $transaction = $stmt->fetch();
        
        // Update transaction
        $stmt = $pdo->prepare("UPDATE transactions SET payment_status = ?, paid_at = ? WHERE id = ?");
        $paid_at = ($payment_status == 'paid') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$payment_status, $paid_at, $id]);
        
        // If status changes to paid, calculate and store fee
        if ($payment_status == 'paid' && $transaction) {
            $fee_breakdown = calculateFeeBreakdown($transaction['total_amount']);
            
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET fee_percentage = ?, 
                    fee_amount = ?, 
                    technician_earning = ?,
                    confirmed_by = ?,
                    confirmed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $fee_breakdown['fee_percentage'],
                $fee_breakdown['fee_amount'],
                $fee_breakdown['technician_earning'],
                $_SESSION['user_id'],
                $id
            ]);
            
            // Create technician earning record if service exists
            if ($transaction['service_id']) {
                $stmt = $pdo->prepare("SELECT technician_id FROM services WHERE id = ?");
                $stmt->execute([$transaction['service_id']]);
                $service = $stmt->fetch();
                
                if ($service && $service['technician_id']) {
                    $stmt = $pdo->prepare("SELECT id FROM technicians WHERE id = ?");
                    $stmt->execute([$service['technician_id']]);
                    $tech = $stmt->fetch();
                    
                    if ($tech) {
                        createTechnicianEarning($pdo, $tech['id'], $id, $transaction['total_amount'], 
                            $fee_breakdown['fee_percentage'], $fee_breakdown['fee_amount'], 
                            $fee_breakdown['technician_earning']);
                    }
                }
            }
        }
        
        // Get order_id from transaction
        $stmt = $pdo->prepare("SELECT order_id FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $trans_order = $stmt->fetch();
        
        if ($trans_order && $trans_order['order_id']) {
            if ($payment_status == 'paid') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'paid' WHERE id = ? AND status = 'pending'");
                $stmt->execute([$trans_order['order_id']]);
            } elseif ($payment_status == 'pending') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND status = 'paid'");
                $stmt->execute([$trans_order['order_id']]);
            }
        }
        
        $pdo->commit();
        header('Location: transactions.php?msg=updated');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Admin payment update error: " . $e->getMessage());
        header('Location: transactions.php?msg=error');
        exit();
    }
}

// Build where clause for filters
$where_conditions = [];
$params = [];

if ($filter_status && $filter_status != 'all') {
    $where_conditions[] = "t.payment_status = ?";
    $params[] = $filter_status;
}

if ($filter_method && $filter_method != 'all') {
    $where_conditions[] = "t.payment_method = ?";
    $params[] = $filter_method;
}

if ($start_date) {
    $where_conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where_conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $end_date;
}

if ($search) {
    $where_conditions[] = "(customer_name LIKE ? OR order_display LIKE ? OR device_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// [FIX] Query yang lebih baik untuk mendapatkan data customer dan order dengan format nomor yang rapi
$sql = "
    SELECT 
        t.*,
        -- Order / Service Number display logic
        CASE 
            WHEN t.order_id IS NOT NULL AND t.order_id > 0 THEN CONCAT('#', LPAD(t.order_id, 6, '0'))
            WHEN t.service_id IS NOT NULL AND t.service_id > 0 THEN CONCAT('#SVC', LPAD(t.service_id, 6, '0'))
            ELSE '-'
        END as order_display,
        -- Customer name logic
        CASE 
            WHEN o.id IS NOT NULL AND o.user_id IS NOT NULL THEN uo.name
            WHEN s.id IS NOT NULL AND s.user_id IS NOT NULL THEN us.name
            ELSE '-'
        END as customer_name,
        -- Device name untuk jasa servis
        CASE 
            WHEN s.id IS NOT NULL AND s.device IS NOT NULL THEN s.device
            WHEN o.id IS NOT NULL THEN 'Pembelian Part'
            ELSE '-'
        END as device_name,
        -- Order ID as integer for link
        t.order_id as order_id_value,
        -- Service ID for link
        t.service_id as service_id_value,
        -- Type indicator
        CASE 
            WHEN t.order_id IS NOT NULL AND t.order_id > 0 THEN 'order'
            WHEN t.service_id IS NOT NULL AND t.service_id > 0 THEN 'service'
            ELSE 'unknown'
        END as transaction_type
    FROM transactions t
    LEFT JOIN orders o ON t.order_id = o.id
    LEFT JOIN users uo ON o.user_id = uo.id
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN users us ON s.user_id = us.id
    $where_clause
    ORDER BY t.created_at DESC
    LIMIT $offset, $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM (
        SELECT t.id
        FROM transactions t
        LEFT JOIN orders o ON t.order_id = o.id
        LEFT JOIN users uo ON o.user_id = uo.id
        LEFT JOIN services s ON t.service_id = s.id
        LEFT JOIN users us ON s.user_id = us.id
        $where_clause
    ) as filtered
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_transactions = $stmt->fetch()['total'];
$total_pages = ceil($total_transactions / $limit);

// Get summary stats including fee breakdown
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN payment_status = 'pending_confirmation' THEN total_amount ELSE 0 END), 0) as total_pending_confirmation,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN fee_amount ELSE 0 END), 0) as total_fees_collected,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN technician_earning ELSE 0 END), 0) as total_technician_earnings,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as count_paid,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as count_pending,
        COUNT(CASE WHEN payment_status = 'pending_confirmation' THEN 1 END) as count_pending_confirmation
    FROM transactions
");
$stmt->execute();
$summary = $stmt->fetch();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
$pending_applications = $stmt->fetch()['count'] ?? 0;

// Get user profile photo for current admin
$user_profile_photo = null;
$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if ($user_data && $user_data['profile_photo']) {
    $user_profile_photo = $user_data['profile_photo'];
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') {
        $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Status pembayaran berhasil diupdate!</div>';
    }
    if ($_GET['msg'] == 'confirmed') {
        $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Pembayaran berhasil dikonfirmasi!</div>';
    }
    if ($_GET['msg'] == 'error') {
        $message = '<div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle me-2"></i>Terjadi kesalahan, silakan coba lagi!</div>';
    }
    if ($_GET['msg'] == 'csrf_error') {
        $message = '<div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle me-2"></i>Token keamanan tidak valid, silakan refresh halaman!</div>';
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions Management - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        /* [Styles tetap sama seperti sebelumnya] */
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
            --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--cream); }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, var(--dark-brown) 0%, #5C3A36 100%);
            padding: 1.5rem;
            transition: var(--transition);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 248, 240, 0.1);
        }
        
        .brand-icon {
            width: 45px;
            height: 45px;
            background: var(--gold-brown);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-icon i { font-size: 1.3rem; color: white; }
        .brand-text { color: white; }
        .brand-name { font-size: 1.2rem; font-weight: 700; display: block; }
        .brand-sub { font-size: 0.7rem; opacity: 0.7; }
        
        .nav-menu { list-style: none; padding: 0; }
        .nav-item { margin-bottom: 0.5rem; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1rem;
            color: rgba(255, 248, 240, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(192, 133, 82, 0.2);
            color: var(--gold-brown);
        }
        .nav-link i { width: 22px; font-size: 1.1rem; }
        
        .badge-notif {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 1.5rem 2rem;
            min-height: 100vh;
        }
        
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.15);
        }
        
        .page-title h1 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--dark-brown);
            margin: 0;
        }
        
        .page-title p {
            color: var(--medium-brown);
            font-size: 0.85rem;
            margin: 0;
            margin-top: 0.25rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            box-shadow: var(--shadow-sm);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gold-brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar span {
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-brown);
            font-size: 0.9rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
        }
        
        .summary-card p {
            font-size: 0.7rem;
            color: var(--medium-brown);
            margin: 0;
        }
        
        .summary-card.total h3 { color: var(--dark-brown); }
        .summary-card.paid h3 { color: #28a745; }
        .summary-card.pending h3 { color: #ffc107; }
        .summary-card.waiting h3 { color: #17a2b8; }
        
        .fee-summary-card {
            background: linear-gradient(135deg, #fff8f0, #fff);
            border: 1px solid rgba(192, 133, 82, 0.2);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .fee-summary-card .fee-stats {
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .fee-summary-card .fee-item {
            text-align: center;
            flex: 1;
        }
        
        .fee-summary-card .fee-item h4 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }
        
        .fee-summary-card .fee-item p {
            font-size: 0.7rem;
            margin: 0;
            color: var(--medium-brown);
        }
        
        .fee-summary-card .divider {
            width: 1px;
            height: 40px;
            background: rgba(192, 133, 82, 0.2);
        }
        
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-brown);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .filter-group {
            flex: 1;
            min-width: 130px;
        }
        
        .filter-group label {
            font-size: 0.7rem;
            color: var(--medium-brown);
            margin-bottom: 0.3rem;
            display: block;
            font-weight: 500;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid rgba(192, 133, 82, 0.25);
            border-radius: 12px;
            font-size: 0.85rem;
            transition: var(--transition);
            background: white;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--gold-brown);
            outline: none;
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
        }
        
        .btn-filter {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .btn-filter:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
        }
        
        .btn-export {
            background: transparent;
            border: 1px solid var(--gold-brown);
            color: var(--gold-brown);
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-export:hover {
            background: var(--gold-brown);
            color: white;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 1rem 0.8rem;
            color: var(--medium-brown);
            font-weight: 600;
            font-size: 0.7rem;
            background: #fafafa;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .data-table td {
            padding: 1rem 0.8rem;
            color: var(--dark-brown);
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.05);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: rgba(192, 133, 82, 0.02);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-pending { background: rgba(255, 193, 7, 0.12); color: #ffc107; }
        .status-pending_confirmation { background: rgba(23, 162, 184, 0.12); color: #17a2b8; }
        .status-paid { background: rgba(40, 167, 69, 0.12); color: #28a745; }
        
        .payment-method-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 500;
            background: rgba(192, 133, 82, 0.1);
            color: var(--gold-brown);
        }
        
        .fee-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.6rem;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .service-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 500;
            background: rgba(192, 133, 82, 0.1);
            color: var(--gold-brown);
        }
        
        .order-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-weight: 500;
        }
        
        .order-link:hover {
            text-decoration: underline;
        }
        
        .btn-proof {
            background: transparent;
            border: 1px solid var(--gold-brown);
            color: var(--gold-brown);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-proof:hover {
            background: var(--gold-brown);
            color: white;
        }
        
        .btn-action {
            background: transparent;
            border: none;
            color: var(--medium-brown);
            cursor: pointer;
            margin: 0 3px;
            transition: var(--transition);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .btn-action:hover {
            background: rgba(192, 133, 82, 0.1);
            color: var(--gold-brown);
        }
        
        .pagination {
            margin-top: 1.5rem;
            justify-content: center;
        }
        
        .pagination .page-link {
            color: var(--gold-brown);
            border: 1px solid rgba(192, 133, 82, 0.2);
            background: white;
            padding: 0.5rem 0.9rem;
            margin: 0 3px;
            border-radius: 10px;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover {
            background: var(--gold-brown);
            color: white;
            border-color: var(--gold-brown);
        }
        
        .pagination .page-item.active .page-link {
            background: var(--gold-brown);
            border-color: var(--gold-brown);
            color: white;
        }
        
        .alert-custom {
            border: none;
            border-radius: 16px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
        .alert-success-custom { background: rgba(192, 133, 82, 0.12); color: var(--gold-brown); }
        .alert-danger-custom { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .alert-info-custom { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: var(--dark-brown);
            color: white;
            padding: 1.2rem 1.5rem;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .btn-modal-save {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            transition: var(--transition);
        }
        
        .btn-modal-save:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }
            .sidebar-brand .brand-text, .nav-link span { display: none; }
            .nav-link { justify-content: center; }
            .main-content { margin-left: 80px; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .top-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filter-bar { flex-direction: column; }
            .filter-group { width: 100%; }
            .summary-grid { grid-template-columns: 1fr; }
            .fee-summary-card .fee-stats { flex-direction: column; }
            .fee-summary-card .divider { display: none; }
        }
    </style>
</head>
<body>
    <!-- Sidebar (sama seperti sebelumnya) -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="fas fa-microchip"></i></div>
            <div class="brand-text">
                <span class="brand-name">Loaz</span>
                <span class="brand-sub">Admin Panel</span>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Users</span></a></li>
            <li class="nav-item"><a href="technician_applications.php" class="nav-link <?php echo $current_page == 'technician_applications.php' ? 'active' : ''; ?>"><i class="fas fa-briefcase"></i><span>Lamaran Teknisi</span>
                <?php if ($pending_applications > 0): ?>
                    <span class="badge-notif"><?php echo $pending_applications; ?></span>
                <?php endif; ?>
            </a></li>
            <li class="nav-item"><a href="technicians.php" class="nav-link <?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i><span>Technicians</span></a></li>
            <li class="nav-item"><a href="services.php" class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>"><i class="fas fa-tools"></i><span>Services</span></a></li>
            <li class="nav-item"><a href="parts.php" class="nav-link <?php echo $current_page == 'parts.php' ? 'active' : ''; ?>"><i class="fas fa-microchip"></i><span>Parts</span></a></li>
            <li class="nav-item"><a href="orders.php" class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i><span>Orders</span></a></li>
            <li class="nav-item"><a href="transactions.php" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i><span>Transactions</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge-notif"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a></li>
            <li class="nav-item"><a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
            <li class="nav-item"><a href="support_chat.php" class="nav-link"><i class="fas fa-headset"></i><span>Support Chat</span></a></li>
            <li class="nav-item"><a href="/loaz_industries/auth/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1>Transactions Management</h1>
                <p>Kelola semua transaksi pembayaran dan fee admin</p>
            </div>
            <div class="user-info">
                <div class="user-detail">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <small style="color: var(--medium-brown);">Administrator</small>
                </div>
                <div class="user-avatar">
                    <?php if ($user_profile_photo && file_exists('../assets/images/users/' . $user_profile_photo)): ?>
                        <img src="../assets/images/users/<?php echo $user_profile_photo; ?>" alt="Profile">
                    <?php else: ?>
                        <span><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card total">
                <h3><?php echo formatCurrency($summary['total_paid'] + $summary['total_pending'] + $summary['total_pending_confirmation']); ?></h3>
                <p>Total Transaksi</p>
            </div>
            <div class="summary-card paid">
                <h3><?php echo formatCurrency($summary['total_paid']); ?></h3>
                <p>Dibayar (<?php echo $summary['count_paid']; ?> transaksi)</p>
            </div>
            <div class="summary-card pending">
                <h3><?php echo formatCurrency($summary['total_pending']); ?></h3>
                <p>Menunggu (<?php echo $summary['count_pending']; ?> transaksi)</p>
            </div>
            <div class="summary-card waiting">
                <h3><?php echo formatCurrency($summary['total_pending_confirmation']); ?></h3>
                <p>Konfirmasi (<?php echo $summary['count_pending_confirmation']; ?> transaksi)</p>
            </div>
        </div>
        
        <!-- Fee Summary Card -->
        <div class="fee-summary-card">
            <div class="fee-stats">
                <div class="fee-item">
                    <h4><?php echo formatCurrency($summary['total_fees_collected']); ?></h4>
                    <p><i class="fas fa-percent"></i> Total Fee Admin Terkumpul</p>
                </div>
                <div class="divider"></div>
                <div class="fee-item">
                    <h4><?php echo formatCurrency($summary['total_technician_earnings']); ?></h4>
                    <p><i class="fas fa-user-cog"></i> Pendapatan Teknisi</p>
                </div>
                <div class="divider"></div>
                <div class="fee-item">
                    <h4><?php echo formatCurrency($summary['total_paid']); ?></h4>
                    <p><i class="fas fa-money-bill-wave"></i> Total Pendapatan</p>
                </div>
            </div>
            <div class="text-center mt-2">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Fee progresif: 4% (≤ Rp1.000.000) → 15% (≥ Rp2.000.000)
                </small>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                <div class="filter-group">
                    <label><i class="fas fa-filter me-1"></i> Status</label>
                    <select name="status">
                        <option value="all" <?php echo $filter_status == 'all' || !$filter_status ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="pending_confirmation" <?php echo $filter_status == 'pending_confirmation' ? 'selected' : ''; ?>>Pending Confirmation</option>
                        <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-credit-card me-1"></i> Metode</label>
                    <select name="method">
                        <option value="all" <?php echo $filter_method == 'all' || !$filter_method ? 'selected' : ''; ?>>Semua Metode</option>
                        <option value="bank_transfer" <?php echo $filter_method == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="qris" <?php echo $filter_method == 'qris' ? 'selected' : ''; ?>>QRIS</option>
                        <option value="e_wallet" <?php echo $filter_method == 'e_wallet' ? 'selected' : ''; ?>>E-Wallet</option>
                        <option value="cod" <?php echo $filter_method == 'cod' ? 'selected' : ''; ?>>COD</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> Dari Tanggal</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar me-1"></i> Sampai Tanggal</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search me-1"></i> Cari</label>
                    <input type="text" name="search" placeholder="Order # / Service # / Customer" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn-filter w-100"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <a href="transactions.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="button" class="btn-export" onclick="exportToCSV()"><i class="fas fa-file-excel me-1"></i> Export</button>
                </div>
            </form>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-money-bill-wave me-2"></i> All Transactions</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Order / Servis</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Amount</th>
                            <th>Fee Admin</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Paid At</th>
                            <th>Payment Proof</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td>#<?php echo $trans['id']; ?></td>
                                <td>
                                    <?php if ($trans['order_id_value'] > 0): ?>
                                        <a href="orders.php?view=<?php echo $trans['order_id_value']; ?>" class="order-link" title="Lihat Detail Order">
                                            <?php echo $trans['order_display']; ?>
                                            <i class="fas fa-box fa-xs ms-1" style="color: var(--gold-brown);"></i>
                                        </a>
                                    <?php elseif ($trans['service_id_value'] > 0): ?>
                                        <a href="services.php?view=<?php echo $trans['service_id_value']; ?>" class="order-link" title="Lihat Detail Servis">
                                            <?php echo $trans['order_display']; ?>
                                            <i class="fas fa-tools fa-xs ms-1" style="color: var(--gold-brown);"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo $trans['order_display']; ?></span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <?php if ($trans['customer_name'] != '-'): ?>
                                        <span><?php echo htmlspecialchars($trans['customer_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <?php if ($trans['service_id_value'] > 0): ?>
                                        <span class="service-badge">
                                            <i class="fas fa-microchip me-1"></i>
                                            <?php echo htmlspecialchars($trans['device_name'] ?? '-'); ?>
                                        </span>
                                    <?php elseif ($trans['order_id_value'] > 0): ?>
                                        <span class="text-muted">Pembelian Part</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td><?php echo formatCurrency($trans['total_amount']); ?></a>
                                <td>
                                    <?php if ($trans['fee_amount'] > 0): ?>
                                        <span class="fee-badge" title="Fee <?php echo formatFeePercentage($trans['fee_percentage'] ?? 0); ?>">
                                            <?php echo formatCurrency($trans['fee_amount']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <span class="payment-method-badge">
                                        <?php 
                                            $methods = [
                                                'bank_transfer' => '🏦 Transfer Bank',
                                                'qris' => '📱 QRIS',
                                                'e_wallet' => '👛 E-Wallet',
                                                'cod' => '💵 COD'
                                            ];
                                            echo $methods[$trans['payment_method']] ?? ucfirst($trans['payment_method'] ?? '-');
                                        ?>
                                    </span>
                                </a>
                                <td>
                                    <span class="status-badge status-<?php echo $trans['payment_status'] ?? 'pending'; ?>">
                                        <?php 
                                            $status_text = [
                                                'pending' => 'Menunggu',
                                                'pending_confirmation' => 'Konfirmasi',
                                                'paid' => 'Dibayar'
                                            ];
                                            echo $status_text[$trans['payment_status']] ?? ucfirst($trans['payment_status'] ?? 'pending');
                                        ?>
                                    </span>
                                </a>
                                <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></a>
                                <td>
                                    <?php if ($trans['paid_at']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($trans['paid_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <?php if (!empty($trans['payment_proof']) && $trans['payment_proof']): ?>
                                        <a href="../uploads/payment_proofs/<?php echo $trans['payment_proof']; ?>" target="_blank" class="btn-proof">
                                            <i class="fas fa-eye"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <?php if (!empty($trans['payment_proof']) && $trans['payment_proof'] && ($trans['payment_status'] ?? '') == 'pending_confirmation'): ?>
                                        <button class="btn-action" onclick="confirmPayment(<?php echo $trans['id']; ?>, '<?php echo htmlspecialchars($trans['payment_proof']); ?>', <?php echo $trans['total_amount']; ?>)" 
                                                data-bs-toggle="modal" data-bs-target="#confirmPaymentModal" title="Konfirmasi Pembayaran" style="color: #28a745;">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php elseif (($trans['payment_status'] ?? '') == 'paid'): ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Terkonfirmasi</span>
                                    <?php else: ?>
                                        <button class="btn-action" onclick="editTransaction(<?php echo $trans['id']; ?>, '<?php echo $trans['payment_status'] ?? 'pending'; ?>')" 
                                                data-bs-toggle="modal" data-bs-target="#editTransactionModal" title="Update Status">
                                            <i class="fas fa-credit-card"></i>
                                        </button>
                                    <?php endif; ?>
                                </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">Belum ada transaksi</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&method=<?php echo urlencode($filter_method); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i> Update Status Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="transaction_id" id="edit_transaction_id">
                        <div class="mb-3">
                            <label class="form-label">Status Pembayaran</label>
                            <select name="payment_status" id="edit_payment_status" class="form-select">
                                <option value="pending">Pending (Menunggu)</option>
                                <option value="paid">Paid (Dibayar)</option>
                            </select>
                        </div>
                        <div class="alert alert-info-custom">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Jika status diubah menjadi "Paid", fee admin akan dihitung otomatis (progresif 4%-15%).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_payment" class="btn btn-modal-save">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Confirm Payment Modal -->
    <div class="modal fade" id="confirmPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="transaction_id" id="confirm_transaction_id">
                        <input type="hidden" name="total_amount" id="confirm_total_amount">
                        <div class="text-center mb-3">
                            <i class="fas fa-credit-card fa-3x" style="color: var(--gold-brown); margin-bottom: 1rem;"></i>
                            <h5>Konfirmasi Pembayaran</h5>
                        </div>
                        
                        <div class="alert alert-info-custom mb-3" id="feePreview" style="display: none;">
                            <i class="fas fa-percent me-2"></i>
                            <strong>Informasi Fee:</strong><br>
                            <span id="feePercentageText"></span><br>
                            <span id="feeAmountText"></span><br>
                            <span id="technicianEarningText"></span>
                        </div>
                        
                        <div class="alert alert-warning-custom">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Pastikan bukti pembayaran sudah valid sebelum mengkonfirmasi. Fee akan dihitung otomatis.</small>
                        </div>
                        <div class="mb-3" id="proofPreview">
                            <label class="form-label">Bukti Pembayaran</label>
                            <div class="text-center">
                                <a href="#" id="proofLink" target="_blank" class="btn-proof">
                                    <i class="fas fa-eye me-2"></i> Lihat Bukti Transfer
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="confirm_payment" class="btn btn-modal-save">Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editTransaction(id, status) {
            document.getElementById('edit_transaction_id').value = id;
            document.getElementById('edit_payment_status').value = status || 'pending';
        }
        
        function confirmPayment(id, proof, totalAmount) {
            document.getElementById('confirm_transaction_id').value = id;
            document.getElementById('confirm_total_amount').value = totalAmount;
            document.getElementById('proofLink').href = '../uploads/payment_proofs/' + proof;
            
            calculateAndDisplayFee(totalAmount);
        }
        
        function calculateAndDisplayFee(amount) {
            let minAmount = 1000000;
            let maxAmount = 2000000;
            let minFee = 4;
            let maxFee = 15;
            let feePercentage;
            
            if (amount <= minAmount) {
                feePercentage = minFee;
            } else if (amount >= maxAmount) {
                feePercentage = maxFee;
            } else {
                feePercentage = minFee + ((amount - minAmount) / (maxAmount - minAmount)) * (maxFee - minFee);
                feePercentage = Math.round(feePercentage * 100) / 100;
            }
            
            let feeAmount = amount * (feePercentage / 100);
            let technicianEarning = amount - feeAmount;
            
            let formatRupiah = (value) => {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }).format(value);
            };
            
            document.getElementById('feePreview').style.display = 'block';
            document.getElementById('feePercentageText').innerHTML = `Fee Persentase: <strong>${feePercentage.toFixed(2)}%</strong>`;
            document.getElementById('feeAmountText').innerHTML = `Fee Admin: <strong>${formatRupiah(feeAmount)}</strong>`;
            document.getElementById('technicianEarningText').innerHTML = `Pendapatan Teknisi: <strong>${formatRupiah(technicianEarning)}</strong>`;
        }
        
        function exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export_transactions.php?' + params.toString();
        }
    </script>
</body>
</html>