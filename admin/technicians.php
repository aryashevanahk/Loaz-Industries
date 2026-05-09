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

// Handle delete technician
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Get user_id from technicians
    $stmt = $pdo->prepare("SELECT user_id FROM technicians WHERE id = ?");
    $stmt->execute([$id]);
    $tech = $stmt->fetch();
    if ($tech) {
        $stmt = $pdo->prepare("DELETE FROM technicians WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'technician'");
        $stmt->execute([$tech['user_id']]);
    }
    header('Location: technicians.php?msg=deleted');
    exit();
}

// Handle add technician
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_technician'])) {
    $error = '';
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $specialty = $_POST['specialty'];
    $status = $_POST['status'];
    
    // Validate
    if (empty($name)) {
        $error = 'Nama harus diisi!';
    } elseif (empty($email)) {
        $error = 'Email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar!';
        }
    }
    
    if (empty($error)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into users
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'technician')");
        $stmt->execute([$name, $email, $hashed_password]);
        $user_id = $pdo->lastInsertId();
        
        // Insert into technicians
        $stmt = $pdo->prepare("INSERT INTO technicians (user_id, specialty, status) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $specialty, $status]);
        
        header('Location: technicians.php?msg=added');
        exit();
    } else {
        $message = '<div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle me-2"></i>' . $error . '</div>';
    }
}

// Handle update status
if (isset($_POST['update_status'])) {
    $id = (int)$_POST['technician_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE technicians SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header('Location: technicians.php?msg=updated');
    exit();
}

// Build where clause for filters
$where_conditions = [];
$params = [];

if ($filter_status && $filter_status != 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR t.specialty LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_technicians = $stmt->fetch()['total'];
$total_pages = ceil($total_technicians / $limit);

// Get all technicians with ratings
$sql = "
    SELECT t.*, 
           u.name, u.email, u.phone, u.profile_photo,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(r.id) as total_reviews
    FROM technicians t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN reviews r ON t.id = r.technician_id
    $where_clause
    GROUP BY t.id, u.id
    ORDER BY avg_rating DESC, t.id DESC
    LIMIT $offset, $limit
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$technicians = $stmt->fetchAll();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) as count FROM technicians WHERE status = 'available'");
$available_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM technicians WHERE status = 'busy'");
$busy_count = $stmt->fetch()['count'] ?? 0;

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

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Technician berhasil dihapus!</div>';
    if ($_GET['msg'] == 'added') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Technician berhasil ditambahkan!</div>';
    if ($_GET['msg'] == 'updated') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Status technician berhasil diupdate!</div>';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians Management - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
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
        
        /* Notification Badge */
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
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 1.5rem 2rem;
            min-height: 100vh;
        }
        
        /* Header */
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
        
        /* Card */
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
        
        /* Filter Bar */
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
        
        .btn-add {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-add:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
        }
        
        /* Stat Badges */
        .stat-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .stat-badge-available {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        .stat-badge-busy {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }
        
        /* Tables */
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
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-available {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        .status-busy {
            background: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }
        
        /* Rating Stars */
        .rating-stars {
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        
        .rating-stars i {
            font-size: 0.7rem;
        }
        
        .rating-text {
            margin-left: 5px;
            font-size: 0.7rem;
            color: var(--medium-brown);
        }
        
        /* User Photo */
        .user-photo {
            width: 36px;
            height: 36px;
            background: rgba(192, 133, 82, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .user-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-photo i {
            font-size: 1rem;
            color: var(--gold-brown);
        }
        
        /* Button Actions */
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
        
        /* Pagination */
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
        
        /* Alerts */
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
        
        /* Modals */
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
        
        /* Review Item */
        .review-item {
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
            padding: 1rem 0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-rating {
            margin-bottom: 0.5rem;
        }
        
        .review-comment {
            font-size: 0.85rem;
            color: var(--dark-brown);
            margin-bottom: 0.3rem;
        }
        
        .review-customer {
            font-size: 0.7rem;
            color: var(--medium-brown);
        }
        
        /* Responsive */
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
                <h1>Technicians Management</h1>
                <p>Kelola semua teknisi servis</p>
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
                <h2><i class="fas fa-user-cog me-2"></i> All Technicians</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="stat-badge stat-badge-available"><i class="fas fa-circle me-1" style="color: #28a745;"></i> Available: <?php echo $available_count; ?></span>
                    <span class="stat-badge stat-badge-busy"><i class="fas fa-circle me-1" style="color: #dc3545;"></i> Busy: <?php echo $busy_count; ?></span>
                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addTechnicianModal">
                        <i class="fas fa-plus me-1"></i> Add Technician
                    </button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                    <div class="filter-group">
                        <label><i class="fas fa-filter me-1"></i> Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status == 'all' || !$filter_status ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="busy" <?php echo $filter_status == 'busy' ? 'selected' : ''; ?>>Busy</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama / Email / Spesialisasi" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter w-100"><i class="fas fa-search me-1"></i> Filter</button>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="technicians.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Specialty</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($technicians) > 0): ?>
                            <?php foreach ($technicians as $tech): ?>
                            <tr>
                                <td><?php echo $tech['id']; ?></td>
                                <td>
                                    <div class="user-photo">
                                        <?php if ($tech['profile_photo'] && file_exists('../assets/images/users/' . $tech['profile_photo'])): ?>
                                            <img src="../assets/images/users/<?php echo $tech['profile_photo']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($tech['name']); ?></td>
                                <td><?php echo htmlspecialchars($tech['email']); ?></td>
                                <td><?php echo htmlspecialchars($tech['specialty']); ?></td>
                                <td>
                                    <div class="rating-stars">
                                        <?php 
                                            $avg_rating = round($tech['avg_rating'], 1);
                                            $full_stars = floor($avg_rating);
                                            $half_star = ($avg_rating - $full_stars) >= 0.5;
                                            $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                                        ?>
                                        <?php for ($i = 1; $i <= $full_stars; $i++): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php endfor; ?>
                                        <?php if ($half_star): ?>
                                            <i class="fas fa-star-half-alt text-warning"></i>
                                        <?php endif; ?>
                                        <?php for ($i = 1; $i <= $empty_stars; $i++): ?>
                                            <i class="far fa-star text-muted"></i>
                                        <?php endfor; ?>
                                        <span class="rating-text">(<?php echo number_format($avg_rating, 1); ?> dari <?php echo $tech['total_reviews']; ?> ulasan)</span>
                                    </div>
                                 </a>
                                <td>
                                    <span class="status-badge status-<?php echo $tech['status']; ?>">
                                        <?php echo ucfirst($tech['status']); ?>
                                    </span>
                                 </a>
                                <td>
                                    <button class="btn-action" onclick="viewReviews(<?php echo $tech['id']; ?>, '<?php echo htmlspecialchars($tech['name']); ?>')" data-bs-toggle="modal" data-bs-target="#reviewsModal" title="Lihat Ulasan">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button class="btn-action" onclick="editTechnician(<?php echo $tech['id']; ?>, '<?php echo $tech['status']; ?>')" data-bs-toggle="modal" data-bs-target="#editTechnicianModal" title="Edit Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action" onclick="confirmDelete(<?php echo $tech['id']; ?>, '<?php echo htmlspecialchars($tech['name']); ?>')" style="color: #dc3545;" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                 </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">Tidak ada data teknisi</a>
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
    
    <!-- Add Technician Modal -->
    <div class="modal fade" id="addTechnicianModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Tambah Technician Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimal 6 karakter</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesialisasi</label>
                            <select name="specialty" class="form-select" required>
                                <option value="Laptop & PC">💻 Laptop & PC</option>
                                <option value="Smartphone">📱 Smartphone</option>
                                <option value="TV & Audio">📺 TV & Audio</option>
                                <option value="AC & Kulkas">❄️ AC & Kulkas</option>
                                <option value="Mesin Cuci">🧺 Mesin Cuci</option>
                                <option value="All Round">🔧 All Round</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="available">✅ Available</option>
                                <option value="busy">⛔ Busy</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_technician" class="btn btn-modal-save">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Technician Modal -->
    <div class="modal fade" id="editTechnicianModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Technician Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="technician_id" id="edit_tech_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_tech_status" class="form-select">
                                <option value="available">✅ Available</option>
                                <option value="busy">⛔ Busy</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_status" class="btn btn-modal-save">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reviews Modal -->
    <div class="modal fade" id="reviewsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i> Ulasan Teknisi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reviewsContent">
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
        function editTechnician(id, status) {
            document.getElementById('edit_tech_id').value = id;
            document.getElementById('edit_tech_status').value = status;
        }
        
        function confirmDelete(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus technician "' + name + '"?')) {
                window.location.href = 'technicians.php?delete=' + id;
            }
        }
        
        function viewReviews(technicianId, technicianName) {
            $('#reviewsContent').html('<div class="text-center py-4"><div class="spinner-border text-gold" role="status"></div><p class="mt-2">Loading...</p></div>');
            
            $.get('get_technician_reviews.php?id=' + technicianId, function(data) {
                if (data.reviews && data.reviews.length > 0) {
                    var html = `
                        <div class="text-center mb-4">
                            <div class="d-inline-block p-3 bg-light rounded-4">
                                <h4 class="mb-1">${escapeHtml(technicianName)}</h4>
                                <div class="rating-stats">
                                    <div class="rating-stars-large mb-2">
                        `;
                    
                    // Rating stars for average
                    var avgRating = parseFloat(data.avg_rating) || 0;
                    var fullStars = Math.floor(avgRating);
                    var hasHalfStar = (avgRating - fullStars) >= 0.5;
                    
                    for (var i = 1; i <= fullStars; i++) {
                        html += '<i class="fas fa-star text-warning"></i>';
                    }
                    if (hasHalfStar) {
                        html += '<i class="fas fa-star-half-alt text-warning"></i>';
                    }
                    for (var i = fullStars + (hasHalfStar ? 1 : 0); i < 5; i++) {
                        html += '<i class="far fa-star text-muted"></i>';
                    }
                    
                    html += `
                                    </div>
                                    <p class="mb-0"><strong>${avgRating.toFixed(1)}</strong> dari 5 (${data.total_reviews} ulasan)</p>
                                </div>
                            </div>
                        </div>
                        <div class="reviews-list">
                    `;
                    
                    data.reviews.forEach(function(review) {
                        var reviewStars = '';
                        for (var i = 1; i <= 5; i++) {
                            if (i <= review.rating) {
                                reviewStars += '<i class="fas fa-star text-warning"></i>';
                            } else {
                                reviewStars += '<i class="far fa-star text-muted"></i>';
                            }
                        }
                        
                        html += `
                            <div class="review-item">
                                <div class="review-rating">
                                    ${reviewStars}
                                    <span class="review-date ms-2 small text-muted">${new Date(review.created_at).toLocaleDateString('id-ID')}</span>
                                </div>
                                <div class="review-customer">
                                    <i class="fas fa-user me-1"></i> ${escapeHtml(review.customer_name)}
                                </div>
                                <div class="review-comment mt-1">
                                    "${escapeHtml(review.comment || 'Tidak ada komentar')}"
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `</div>`;
                    $('#reviewsContent').html(html);
                } else {
                    $('#reviewsContent').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5>Belum Ada Ulasan</h5>
                            <p class="text-muted">Teknisi ini belum menerima ulasan dari pelanggan.</p>
                        </div>
                    `);
                }
            }, 'json').fail(function() {
                $('#reviewsContent').html(`
                    <div class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                        <h5>Gagal Memuat Data</h5>
                        <p>Silakan coba lagi nanti.</p>
                    </div>
                `);
            });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    </script>
</body>
</html>