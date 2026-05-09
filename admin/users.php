<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle delete user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$id]);
    header('Location: users.php?msg=deleted');
    exit();
}

// Handle change role
if (isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $user_id]);
    header('Location: users.php?msg=updated');
    exit();
}

// Handle add admin
if (isset($_POST['add_admin'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $error = '';
    
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
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, address) VALUES (?, ?, ?, 'admin', ?, ?)");
        if ($stmt->execute([$name, $email, $hashed_password, $phone, $address])) {
            header('Location: users.php?msg=admin_added');
        } else {
            header('Location: users.php?msg=error');
        }
    } else {
        header('Location: users.php?msg=error&error_msg=' . urlencode($error));
    }
    exit();
}

// Handle update profile (admin edit user)
if (isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = trim($_POST['postal_code']);
    $gender = $_POST['gender'];
    $birth_date = $_POST['birth_date'];
    
    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ?, city = ?, province = ?, postal_code = ?, gender = ?, birth_date = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $address, $city, $province, $postal_code, $gender, $birth_date, $user_id]);
    header('Location: users.php?msg=profile_updated');
    exit();
}

// Build where clause for filters
$where_conditions = [];
$params = [];

if ($filter_role && $filter_role != 'all') {
    $where_conditions[] = "role = ?";
    $params[] = $filter_role;
}

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get all users with additional info
$sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT $offset, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$user_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'technician'");
$technician_count = $stmt->fetch()['count'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_count = $stmt->fetch()['count'] ?? 0;

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

// Get single user details for modal
if (isset($_GET['get_user'])) {
    $id = (int)$_GET['get_user'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user_detail = $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode($user_detail);
    exit();
}

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>User berhasil dihapus!</div>';
    if ($_GET['msg'] == 'updated') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Role user berhasil diupdate!</div>';
    if ($_GET['msg'] == 'admin_added') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Admin baru berhasil ditambahkan!</div>';
    if ($_GET['msg'] == 'profile_updated') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Profil user berhasil diupdate!</div>';
    if ($_GET['msg'] == 'error') {
        $error_msg = isset($_GET['error_msg']) ? urldecode($_GET['error_msg']) : 'Gagal menambahkan admin!';
        $message = '<div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($error_msg) . '</div>';
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Loaz Industries</title>
    
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
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
        }
        
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
        
        /* Role Badges - Konsisten dengan tema */
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .role-admin {
            background: rgba(192, 133, 82, 0.15);
            color: var(--gold-brown);
        }
        
        .role-technician {
            background: rgba(23, 162, 184, 0.12);
            color: #17a2b8;
        }
        
        .role-user {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        /* Status Badge untuk filter */
        .stat-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .stat-badge-user {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .stat-badge-technician {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }
        
        .stat-badge-admin {
            background: rgba(192, 133, 82, 0.12);
            color: var(--gold-brown);
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
        
        /* Profile Photo Modal */
        .profile-photo {
            width: 100px;
            height: 100px;
            background: rgba(192, 133, 82, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            overflow: hidden;
        }
        
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-photo i {
            font-size: 3rem;
            color: var(--gold-brown);
        }
        
        .detail-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(192, 133, 82, 0.08);
        }
        
        .detail-label {
            width: 120px;
            font-weight: 500;
            color: var(--medium-brown);
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--dark-brown);
            font-size: 0.85rem;
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
                <h1>Users Management</h1>
                <p>Kelola semua pengguna sistem</p>
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
                <h2><i class="fas fa-users me-2"></i> All Users</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="stat-badge stat-badge-user"><i class="fas fa-user me-1"></i> User: <?php echo $user_count; ?></span>
                    <span class="stat-badge stat-badge-technician"><i class="fas fa-user-cog me-1"></i> Technician: <?php echo $technician_count; ?></span>
                    <span class="stat-badge stat-badge-admin"><i class="fas fa-user-shield me-1"></i> Admin: <?php echo $admin_count; ?></span>
                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                        <i class="fas fa-plus me-1"></i> Add Admin
                    </button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                    <div class="filter-group">
                        <label><i class="fas fa-filter me-1"></i> Role</label>
                        <select name="role" class="form-select">
                            <option value="all" <?php echo $filter_role == 'all' || !$filter_role ? 'selected' : ''; ?>>Semua Role</option>
                            <option value="user" <?php echo $filter_role == 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="technician" <?php echo $filter_role == 'technician' ? 'selected' : ''; ?>>Technician</option>
                            <option value="admin" <?php echo $filter_role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Cari</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama / Email / Telepon" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter w-100"><i class="fas fa-search me-1"></i> Filter</button>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="users.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
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
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div class="user-photo">
                                        <?php if ($user['profile_photo'] && file_exists('../assets/images/users/' . $user['profile_photo'])): ?>
                                            <img src="../assets/images/users/<?php echo $user['profile_photo']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['phone'] ?? '-'; ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn-action" onclick="viewUser(<?php echo $user['id']; ?>)" data-bs-toggle="modal" data-bs-target="#viewUserModal" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-action" onclick="editUserFull(<?php echo $user['id']; ?>)" data-bs-toggle="modal" data-bs-target="#editUserFullModal" title="Edit Profile">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['role']; ?>')" data-bs-toggle="modal" data-bs-target="#editUserModal" title="Change Role">
                                        <i class="fas fa-tag"></i>
                                    </button>
                                    <?php if ($user['role'] != 'admin'): ?>
                                        <button class="btn-action" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" style="color: #dc3545;" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                 </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">Tidak ada data user</a>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&role=<?php echo urlencode($filter_role); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo urlencode($filter_role); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&role=<?php echo urlencode($filter_role); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i> Tambah Admin Baru</h5>
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
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_admin" class="btn btn-modal-save">Tambah Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Role Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Role User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="name" id="edit_name" class="form-control" readonly disabled style="background: #f5f5f5;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="user">User</option>
                                <option value="technician">Technician</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="change_role" class="btn btn-modal-save">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View User Details Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i> Detail User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewUserContent">
                    <div class="text-center">
                        <div class="spinner-border text-gold" role="status"></div>
                        <p>Loading...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Full Profile Modal -->
    <div class="modal fade" id="editUserFullModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Profil User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserFullForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="full_user_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" name="name" id="full_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Kelamin</label>
                                    <select name="gender" id="full_gender" class="form-select">
                                        <option value="">Pilih</option>
                                        <option value="male">Laki-laki</option>
                                        <option value="female">Perempuan</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="text" name="phone" id="full_phone" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Lahir</label>
                                    <input type="date" name="birth_date" id="full_birth_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" id="full_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Kota</label>
                                    <input type="text" name="city" id="full_city" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Provinsi</label>
                                    <input type="text" name="province" id="full_province" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Kode Pos</label>
                                    <input type="text" name="postal_code" id="full_postal_code" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_user" class="btn btn-modal-save">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function editUser(id, name, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_role').value = role;
        }
        
        function confirmDelete(id, name) {
            if (confirm('Apakah Anda yakin ingin menghapus user "' + name + '"?')) {
                window.location.href = 'users.php?delete=' + id;
            }
        }
        
        function viewUser(id) {
            $('#viewUserContent').html('<div class="text-center"><div class="spinner-border text-gold" role="status"></div><p>Loading...</p></div>');
            $.get('users.php?get_user=' + id, function(data) {
                var user = data;
                var photoHtml = '';
                if (user.profile_photo && user.profile_photo !== 'null') {
                    photoHtml = `<img src="../assets/images/users/${escapeHtml(user.profile_photo)}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">`;
                } else {
                    photoHtml = `<i class="fas fa-user-circle"></i>`;
                }
                var html = `
                    <div class="text-center mb-4">
                        <div class="profile-photo">
                            ${photoHtml}
                        </div>
                        <h4>${escapeHtml(user.name)}</h4>
                        <span class="role-badge role-${user.role}">${user.role.toUpperCase()}</span>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">${escapeHtml(user.email)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">No. Telepon</div>
                        <div class="detail-value">${user.phone || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Jenis Kelamin</div>
                        <div class="detail-value">${user.gender === 'male' ? 'Laki-laki' : (user.gender === 'female' ? 'Perempuan' : (user.gender || '-'))}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Tanggal Lahir</div>
                        <div class="detail-value">${user.birth_date ? new Date(user.birth_date).toLocaleDateString('id-ID') : '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Alamat</div>
                        <div class="detail-value">${user.address || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Kota</div>
                        <div class="detail-value">${user.city || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Provinsi</div>
                        <div class="detail-value">${user.province || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Kode Pos</div>
                        <div class="detail-value">${user.postal_code || '-'}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Terdaftar</div>
                        <div class="detail-value">${new Date(user.created_at).toLocaleDateString('id-ID')}</div>
                    </div>
                `;
                $('#viewUserContent').html(html);
            }, 'json');
        }
        
        function editUserFull(id) {
            $.get('users.php?get_user=' + id, function(user) {
                document.getElementById('full_user_id').value = user.id;
                document.getElementById('full_name').value = user.name;
                document.getElementById('full_gender').value = user.gender || '';
                document.getElementById('full_phone').value = user.phone || '';
                document.getElementById('full_birth_date').value = user.birth_date || '';
                document.getElementById('full_address').value = user.address || '';
                document.getElementById('full_city').value = user.city || '';
                document.getElementById('full_province').value = user.province || '';
                document.getElementById('full_postal_code').value = user.postal_code || '';
            }, 'json');
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