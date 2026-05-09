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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle update service status
if (isset($_POST['update_service'])) {
    $id = (int)$_POST['service_id'];
    $status = $_POST['status'];
    
    // Validate status - include 'visit'
    $valid_statuses = ['pending', 'visit', 'accepted', 'repairing', 'done'];
    if (!in_array($status, $valid_statuses)) {
        $error = "Status tidak valid!";
    } else {
        $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        // Add to service updates
        $note = $_POST['note'] ?? 'Status updated by admin';
        $stmt = $pdo->prepare("INSERT INTO service_updates (service_id, status, note) VALUES (?, ?, ?)");
        $stmt->execute([$id, $status, $note]);
        
        header('Location: services.php?msg=updated');
        exit();
    }
}

// Handle assign technician
if (isset($_POST['assign_technician'])) {
    $service_id = (int)$_POST['service_id'];
    $technician_id = (int)$_POST['technician_id'];
    
    $stmt = $pdo->prepare("UPDATE services SET technician_id = ?, status = 'visit' WHERE id = ?");
    $stmt->execute([$technician_id, $service_id]);
    
    $stmt = $pdo->prepare("
        INSERT INTO service_updates (service_id, status, note) 
        VALUES (?, 'visit', 'Teknisi telah ditugaskan oleh admin dan akan melakukan kunjungan')
    ");
    $stmt->execute([$service_id]);
    
    header('Location: services.php?msg=assigned');
    exit();
}

// Handle delete service
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: services.php?msg=deleted');
    exit();
}

// Build where clause for filters
$where_conditions = [];
$params = [];

if ($filter_status && $filter_status != 'all') {
    $where_conditions[] = "COALESCE(s.status, 'pending') = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(u.name LIKE ? OR s.device LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_services = $stmt->fetch()['total'] ?? 0;
$total_pages = $total_services > 0 ? ceil($total_services / $limit) : 0;

// [FIX] Get all services with user and technician info - dengan COALESCE untuk status
$sql = "
    SELECT s.*, 
           COALESCE(s.status, 'pending') as safe_status,
           u.name as customer_name, 
           tech_user.name as technician_name,
           tech.id as technician_db_id
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN technicians tech ON s.technician_id = tech.id
    LEFT JOIN users tech_user ON tech.user_id = tech_user.id
    $where_clause
    ORDER BY FIELD(COALESCE(s.status, 'pending'), 'pending', 'visit', 'accepted', 'repairing', 'done'), s.created_at DESC
    LIMIT $offset, $limit
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Get technicians for dropdown
$stmt = $pdo->query("
    SELECT t.id, u.name, t.specialty 
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'available' AND t.is_active = 1
");
$technicians = $stmt->fetchAll();

// Get counts for badges - dengan COALESCE untuk handle NULL
$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE COALESCE(status, 'pending') = 'pending'");
$pending_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE status = 'visit'");
$visit_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE status = 'accepted'");
$accepted_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE status = 'repairing'");
$repairing_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE status = 'done'");
$done_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
$pending_applications = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_status = 'pending_confirmation'");
$pending_transactions = $stmt->fetch()['count'] ?? 0;

// Get user profile photo for current admin
$user_profile_photo = null;
$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if ($user_data && $user_data['profile_photo']) {
    $user_profile_photo = $user_data['profile_photo'];
}

// Status text mapping
$status_text_map = [
    'pending' => 'Menunggu',
    'visit' => 'Kunjungan',
    'accepted' => 'Diterima',
    'repairing' => 'Diperbaiki',
    'done' => 'Selesai'
];

// Status class mapping
$status_class_map = [
    'pending' => 'status-pending',
    'visit' => 'status-accepted',
    'accepted' => 'status-accepted',
    'repairing' => 'status-repairing',
    'done' => 'status-done'
];

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Service status berhasil diupdate!</div>';
    if ($_GET['msg'] == 'deleted') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Service berhasil dihapus!</div>';
    if ($_GET['msg'] == 'assigned') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Teknisi berhasil ditugaskan!</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Loaz Industries</title>
    
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
        
        /* Sidebar */
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
            min-width: 150px;
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
        
        .stat-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .stat-badge-pending {
            background: rgba(255, 193, 7, 0.12);
            color: #ffc107;
        }
        
        .stat-badge-accepted {
            background: rgba(23, 162, 184, 0.12);
            color: #17a2b8;
        }
        
        .stat-badge-repairing {
            background: rgba(192, 133, 82, 0.12);
            color: var(--gold-brown);
        }
        
        .stat-badge-done {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
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
        
        .alert-success-custom {
            background: rgba(192, 133, 82, 0.12);
            color: var(--gold-brown);
        }
        
        .alert-danger-custom {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
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
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .top-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filter-bar { flex-direction: column; }
            .filter-group { width: 100%; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .data-table th, .data-table td { padding: 0.75rem 0.5rem; font-size: 0.75rem; }
            .btn-action { width: 28px; height: 28px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
                <?php if ($pending_transactions > 0): ?>
                    <span class="badge-notif"><?php echo $pending_transactions; ?></span>
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
                <h1>Services Management</h1>
                <p>Kelola semua layanan servis</p>
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
        
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-tools me-2"></i> All Service Requests</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="stat-badge stat-badge-pending"><i class="fas fa-clock me-1"></i> Pending: <?php echo $pending_count; ?></span>
                    <span class="stat-badge stat-badge-accepted"><i class="fas fa-check-circle me-1"></i> Accepted: <?php echo $accepted_count; ?></span>
                    <span class="stat-badge stat-badge-repairing"><i class="fas fa-tools me-1"></i> Repairing: <?php echo $repairing_count; ?></span>
                    <span class="stat-badge stat-badge-done"><i class="fas fa-check-double me-1"></i> Done: <?php echo $done_count; ?></span>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                    <div class="filter-group">
                        <label><i class="fas fa-filter me-1"></i> Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status == 'all' || !$filter_status ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="accepted" <?php echo $filter_status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="repairing" <?php echo $filter_status == 'repairing' ? 'selected' : ''; ?>>Repairing</option>
                            <option value="done" <?php echo $filter_status == 'done' ? 'selected' : ''; ?>>Done</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Customer / Device" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter w-100"><i class="fas fa-search me-1"></i> Filter</button>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="services.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Problem</th>
                            <th>Technician</th>
                            <th>Status</th>
                            <th>Est. Cost</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($services) > 0): ?>
                            <?php foreach ($services as $service): ?>
                            <?php 
                            // [FIX] Get safe status with default
                            $safe_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                            $status_display = $status_text_map[$safe_status] ?? ucfirst($safe_status);
                            $status_class = $status_class_map[$safe_status] ?? 'status-pending';
                            ?>
                            <tr>
                                <td>#<?php echo $service['id']; ?></td>
                                <td><?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($service['device'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 30)); ?>...</a>
                                <td>
                                    <?php if (!empty($service['technician_name'])): ?>
                                        <?php echo htmlspecialchars($service['technician_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Belum ditugaskan</span>
                                    <?php endif; ?>
                                </a>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_display; ?>
                                    </span>
                                </a>
                                <td>
                                    <?php if (!empty($service['estimated_cost'])): ?>
                                        <?php echo formatCurrency($service['estimated_cost']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </a>
                                <td><?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?></a>
                                <td>
                                    <button class="btn-action" onclick="viewServiceDetail(<?php echo $service['id']; ?>)" data-bs-toggle="modal" data-bs-target="#viewServiceModal" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-action" onclick="editService(<?php echo $service['id']; ?>, '<?php echo $safe_status; ?>')" data-bs-toggle="modal" data-bs-target="#editServiceModal" title="Edit Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (empty($service['technician_db_id'])): ?>
                                        <button class="btn-action" onclick="assignTechnician(<?php echo $service['id']; ?>)" data-bs-toggle="modal" data-bs-target="#assignModal" title="Assign Teknisi" style="color: #28a745;">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-action" onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['device'] ?? ''); ?>')" style="color: #dc3545;" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">Tidak ada data servis</a>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Edit Service Modal -->
    <div class="modal fade" id="editServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Update Service Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="edit_service_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_service_status" class="form-select">
                                <option value="pending">Pending (Menunggu)</option>
                                <option value="visit">Visit (Kunjungan)</option>
                                <option value="accepted">Accepted (Diterima)</option>
                                <option value="repairing">Repairing (Diperbaiki)</option>
                                <option value="done">Done (Selesai)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (Optional)</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Tambahkan catatan untuk update status..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_service" class="btn btn-modal-save">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Technician Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Assign Teknisi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="service_id" id="assign_service_id">
                        <div class="mb-3">
                            <label class="form-label">Pilih Teknisi</label>
                            <select name="technician_id" class="form-select" required>
                                <option value="">-- Pilih Teknisi --</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?php echo $tech['id']; ?>">
                                        <?php echo htmlspecialchars($tech['name']); ?> - <?php echo htmlspecialchars($tech['specialty']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="assign_technician" class="btn btn-modal-save">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Service Detail Modal -->
    <div class="modal fade" id="viewServiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Detail Servis</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewServiceContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-gold" role="status"></div>
                        <p class="mt-2">Loading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function editService(id, status) {
            document.getElementById('edit_service_id').value = id;
            document.getElementById('edit_service_status').value = status || 'pending';
        }
        
        function assignTechnician(serviceId) {
            document.getElementById('assign_service_id').value = serviceId;
        }
        
        function confirmDelete(id, deviceName) {
            if (confirm('Apakah Anda yakin ingin menghapus service untuk device "' + deviceName + '"?')) {
                window.location.href = 'services.php?delete=' + id;
            }
        }
        
        function viewServiceDetail(id) {
            $('#viewServiceContent').html('<div class="text-center py-4"><div class="spinner-border text-gold" role="status"></div><p class="mt-2">Loading...</p></div>');
            $.get('get_service_detail.php?id=' + id, function(data) {
                $('#viewServiceContent').html(data);
            }).fail(function() {
                $('#viewServiceContent').html('<div class="text-center py-4 text-danger">Gagal memuat detail servis</div>');
            });
        }
    </script>
</body>
</html>