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

// Handle view temporary password
if (isset($_GET['view_temp_password']) && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    
    // Check if user has temporary password
    $stmt = $pdo->prepare("SELECT id, name, email, temp_password, is_temp_password FROM users WHERE id = ? AND role = 'technician'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user && $user['is_temp_password'] == 1 && !empty($user['temp_password'])) {
        // Return JSON response with temporary password
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'name' => $user['name'],
            'email' => $user['email'],
            'temp_password' => $user['temp_password']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Password sementara tidak tersedia atau sudah diubah oleh user.'
        ]);
    }
    exit();
}

// Handle approve/reject
if (isset($_POST['process_application'])) {
    $id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $admin_note = $_POST['admin_note'];
    
    // Validate status
    if (!in_array($status, ['approved', 'rejected'])) {
        $error = "Status tidak valid!";
    } else {
        // Update application status
        $stmt = $pdo->prepare("UPDATE technician_applications SET status = ?, admin_note = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$status, $admin_note, $_SESSION['user_id'], $id]);
        
        // If approved, create technician account
        if ($status == 'approved') {
            $stmt = $pdo->prepare("SELECT * FROM technician_applications WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            
            // Generate random password (10 characters)
            $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$app['email']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing user to technician
                $stmt = $pdo->prepare("UPDATE users SET role = 'technician', is_temp_password = 1, temp_password = ? WHERE id = ?");
                $stmt->execute([$temp_password, $existing['id']]);
                $user_id = $existing['id'];
            } else {
                // Create new user with temporary password flag
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, is_temp_password, temp_password) VALUES (?, ?, ?, 'technician', ?, 1, ?)");
                $stmt->execute([$app['name'], $app['email'], $hashed_password, $app['phone'], $temp_password]);
                $user_id = $pdo->lastInsertId();
            }
            
            // Add to technicians table
            $stmt = $pdo->prepare("INSERT INTO technicians (user_id, specialty, status, application_id, experience_years) VALUES (?, ?, 'available', ?, ?)");
            $stmt->execute([$user_id, $app['specialty'], $id, $app['experience_years'] ?? 0]);
            
            // Store generated password in session to display in modal
            $_SESSION['temp_password'] = $temp_password;
            $_SESSION['temp_email'] = $app['email'];
            $_SESSION['temp_name'] = $app['name'];
            $_SESSION['temp_user_id'] = $user_id;
        }
        
        header('Location: technician_applications.php?msg=processed');
        exit();
    }
}

// Build where clause for filters
$where_conditions = [];
$params = [];

if ($filter_status && $filter_status != 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(a.name LIKE ? OR a.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM technician_applications a $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_applications = $stmt->fetch()['total'];
$total_pages = ceil($total_applications / $limit);

// Get all applications with user_id for approved applications
$sql = "
    SELECT a.*, u.name as reviewer_name, 
           (SELECT user_id FROM technicians WHERE application_id = a.id LIMIT 1) as user_id
    FROM technician_applications a 
    LEFT JOIN users u ON a.reviewed_by = u.id 
    $where_clause
    ORDER BY 
        CASE WHEN a.status = 'pending' THEN 0 ELSE 1 END,
        a.applied_at DESC
    LIMIT $offset, $limit
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
$pending_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'approved'");
$approved_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'rejected'");
$rejected_count = $stmt->fetch()['count'] ?? 0;

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

$message = '';
$show_password_modal = false;
$temp_password = '';
$temp_email = '';
$temp_name = '';
$temp_user_id = '';

if (isset($_GET['msg']) && $_GET['msg'] == 'processed') {
    $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Lamaran berhasil diproses!</div>';
    
    // Check if there's a generated password to show
    if (isset($_SESSION['temp_password'])) {
        $show_password_modal = true;
        $temp_password = $_SESSION['temp_password'];
        $temp_email = $_SESSION['temp_email'];
        $temp_name = $_SESSION['temp_name'];
        $temp_user_id = $_SESSION['temp_user_id'];
        
        // Clear session data after retrieving
        unset($_SESSION['temp_password']);
        unset($_SESSION['temp_email']);
        unset($_SESSION['temp_name']);
        unset($_SESSION['temp_user_id']);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Applications - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        /* CSS Variables and styles (same as before) */
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
        
        .section-header h2 i {
            color: var(--gold-brown);
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
        
        .stat-badge-approved {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        .stat-badge-rejected {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
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
        
        .status-approved {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        .status-rejected {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
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
        
        .btn-view-password {
            color: #17a2b8;
        }
        
        .btn-view-password:hover {
            background: rgba(23, 162, 184, 0.1);
            color: #138496;
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
        
        .pagination .page-item.disabled .page-link {
            color: #ccc;
            pointer-events: none;
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
        
        .alert-info-custom {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
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
        
        .detail-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .detail-label {
            width: 130px;
            font-weight: 500;
            color: var(--medium-brown);
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--dark-brown);
            font-size: 0.85rem;
        }
        
        .file-link {
            color: var(--gold-brown);
            text-decoration: none;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        .password-box {
            background: #f8f9fa;
            border: 1px solid rgba(192, 133, 82, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .password-text {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--gold-brown);
            background: white;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
            letter-spacing: 1px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }
            
            .sidebar-brand .brand-text,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .btn-action {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
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
                <?php if ($pending_count > 0): ?>
                    <span class="badge-notif"><?php echo $pending_count; ?></span>
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
                <h1>Technician Applications</h1>
                <p>Kelola pendaftaran teknisi baru</p>
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
                <h2><i class="fas fa-briefcase me-2"></i> All Applications</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="stat-badge stat-badge-pending"><i class="fas fa-clock me-1"></i> Pending: <?php echo $pending_count; ?></span>
                    <span class="stat-badge stat-badge-approved"><i class="fas fa-check-circle me-1"></i> Approved: <?php echo $approved_count; ?></span>
                    <span class="stat-badge stat-badge-rejected"><i class="fas fa-times-circle me-1"></i> Rejected: <?php echo $rejected_count; ?></span>
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
                            <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama / Email" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter w-100"><i class="fas fa-search me-1"></i> Filter</button>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="technician_applications.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Specialty</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Applied</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($applications) > 0): ?>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?php echo $app['id']; ?></a>
                                <td><?php echo htmlspecialchars($app['name']); ?></a>
                                <td><?php echo htmlspecialchars($app['email']); ?></a>
                                <td><?php echo htmlspecialchars($app['specialty']); ?></a>
                                <td><?php echo $app['experience_years']; ?> tahun</a>
                                <td>
                                    <span class="status-badge status-<?php echo $app['status']; ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </a>
                                <td><?php echo date('d/m/Y', strtotime($app['applied_at'])); ?></a>
                                <td>
                                    <button class="btn-action" onclick="viewApplication(<?php echo $app['id']; ?>)" data-bs-toggle="modal" data-bs-target="#viewApplicationModal" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($app['status'] == 'pending'): ?>
                                        <button class="btn-action" onclick="processApplication(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['name']); ?>')" data-bs-toggle="modal" data-bs-target="#processModal" title="Process Application" style="color: #28a745;">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php elseif ($app['status'] == 'approved' && !empty($app['user_id'])): ?>
                                        <button class="btn-action btn-view-password" onclick="viewTemporaryPassword(<?php echo $app['user_id']; ?>)" title="Lihat Password Sementara">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    <?php endif; ?>
                                </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">Tidak ada data lamaran</a>
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
    
    <!-- View Application Modal -->
    <div class="modal fade" id="viewApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i> Detail Lamaran Teknisi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewAppContent">
                    <div class="text-center">
                        <div class="spinner-border text-gold" role="status"></div>
                        <p class="mt-2">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Process Modal -->
    <div class="modal fade" id="processModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i> Proses Lamaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="process_app_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Pelamar</label>
                            <input type="text" id="process_app_name" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="process_status">
                                <option value="approved">✅ Setujui</option>
                                <option value="rejected">❌ Tolak</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (opsional)</label>
                            <textarea name="admin_note" class="form-control" rows="3" placeholder="Tulis catatan untuk pelamar..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="process_application" class="btn-modal-save">Proses</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Info Modal (SweetAlert2) -->
    <?php if ($show_password_modal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '✅ Teknisi Berhasil Dibuat!',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Nama:</strong> <?php echo htmlspecialchars($temp_name); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($temp_email); ?></p>
                        <div class="password-box">
                            <p><strong>🔑 Password Login:</strong></p>
                            <div class="password-text">
                                <code style="font-size: 1.1rem;"><?php echo $temp_password; ?></code>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle"></i> Simpan password ini dan berikan kepada pelamar.
                            </small>
                        </div>
                        <p class="mt-2 mb-0 text-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Catatan:</strong> Pelamar dapat login menggunakan email dan password di atas. 
                            Setelah login, pelamar dapat mengubah password dan password sementara ini akan tidak dapat dilihat lagi.
                        </p>
                    </div>
                `,
                icon: 'success',
                confirmButtonColor: '#C08552',
                confirmButtonText: '📋 Salin Password & Tutup',
                showCancelButton: true,
                cancelButtonText: 'Tutup',
                cancelButtonColor: '#6c757d',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Copy password to clipboard
                    const password = '<?php echo $temp_password; ?>';
                    navigator.clipboard.writeText(password).then(() => {
                        Swal.fire({
                            title: 'Berhasil Disalin!',
                            text: 'Password telah disalin ke clipboard.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }).catch(() => {
                        Swal.fire({
                            title: 'Gagal Menyalin',
                            text: 'Silakan salin password secara manual.',
                            icon: 'warning',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    });
                }
            });
        });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Function to view application details
        function viewApplication(id) {
            fetch(`get_application_detail.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const app = data.data;
                        const certificateHtml = app.certificate ? 
                            `<a href="../uploads/certificates/${app.certificate}" class="file-link" target="_blank">
                                <i class="fas fa-file-pdf"></i> Lihat Sertifikat
                            </a>` : '<span class="text-muted">Tidak ada</span>';
                        
                        const cvHtml = app.cv_file ? 
                            `<a href="../uploads/cvs/${app.cv_file}" class="file-link" target="_blank">
                                <i class="fas fa-file-alt"></i> Lihat CV
                            </a>` : '<span class="text-muted">Tidak ada</span>';
                        
                        document.getElementById('viewAppContent').innerHTML = `
                            <div class="detail-row">
                                <div class="detail-label">Nama Lengkap</div>
                                <div class="detail-value">${escapeHtml(app.name)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${escapeHtml(app.email)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">No. Telepon</div>
                                <div class="detail-value">${escapeHtml(app.phone)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Spesialisasi</div>
                                <div class="detail-value">${escapeHtml(app.specialty)}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Pengalaman</div>
                                <div class="detail-value">${app.experience_years} tahun</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Portofolio</div>
                                <div class="detail-value">${app.portfolio ? escapeHtml(app.portfolio) : '<span class="text-muted">Tidak ada</span>'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Sertifikat</div>
                                <div class="detail-value">${certificateHtml}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">CV/Resume</div>
                                <div class="detail-value">${cvHtml}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="status-badge status-${app.status}">
                                        ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Tanggal Daftar</div>
                                <div class="detail-value">${new Date(app.applied_at).toLocaleString('id-ID')}</div>
                            </div>
                            ${app.reviewed_at ? `
                            <div class="detail-row">
                                <div class="detail-label">Ditinjau Pada</div>
                                <div class="detail-value">${new Date(app.reviewed_at).toLocaleString('id-ID')}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Ditinjau Oleh</div>
                                <div class="detail-value">${escapeHtml(app.reviewer_name || '-')}</div>
                            </div>
                            ` : ''}
                            ${app.admin_note ? `
                            <div class="detail-row">
                                <div class="detail-label">Catatan Admin</div>
                                <div class="detail-value">${escapeHtml(app.admin_note)}</div>
                            </div>
                            ` : ''}
                        `;
                    } else {
                        document.getElementById('viewAppContent').innerHTML = `
                            <div class="alert alert-danger">Gagal mengambil data: ${data.message}</div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('viewAppContent').innerHTML = `
                        <div class="alert alert-danger">Terjadi kesalahan: ${error.message}</div>
                    `;
                });
        }
        
        // Function to view temporary password
        function viewTemporaryPassword(userId) {
            Swal.fire({
                title: 'Loading...',
                text: 'Mengambil data password sementara',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(`technician_applications.php?view_temp_password=1&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: '🔑 Password Sementara',
                            html: `
                                <div style="text-align: left;">
                                    <p><strong>Nama:</strong> ${escapeHtml(data.name)}</p>
                                    <p><strong>Email:</strong> ${escapeHtml(data.email)}</p>
                                    <div class="password-box">
                                        <p><strong>Password Login:</strong></p>
                                        <div class="password-text">
                                            <code style="font-size: 1.1rem;">${escapeHtml(data.temp_password)}</code>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-info-circle"></i> Password ini hanya tersedia sampai user mengubahnya.
                                        </small>
                                    </div>
                                </div>
                            `,
                            icon: 'info',
                            confirmButtonColor: '#C08552',
                            confirmButtonText: '📋 Salin Password',
                            showCancelButton: true,
                            cancelButtonText: 'Tutup',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Copy password to clipboard
                                navigator.clipboard.writeText(data.temp_password).then(() => {
                                    Swal.fire({
                                        title: 'Berhasil Disalin!',
                                        text: 'Password telah disalin ke clipboard.',
                                        icon: 'success',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                }).catch(() => {
                                    Swal.fire({
                                        title: 'Gagal Menyalin',
                                        text: 'Silakan salin password secara manual.',
                                        icon: 'warning',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                });
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Password Tidak Tersedia',
                            html: `
                                <div style="text-align: left;">
                                    <p>${escapeHtml(data.message)}</p>
                                    <hr>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Password sementara akan hilang setelah user mengubah password mereka.
                                    </small>
                                </div>
                            `,
                            icon: 'warning',
                            confirmButtonColor: '#C08552',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat mengambil data.',
                        icon: 'error',
                        confirmButtonColor: '#C08552'
                    });
                });
        }
        
        // Function to process application
        function processApplication(id, name) {
            document.getElementById('process_app_id').value = id;
            document.getElementById('process_app_name').value = name;
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 4000);
            });
        }, 100);
    </script>
</body>
</html>