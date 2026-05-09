<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Get statistics with error handling
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'] ?? 0;
    
    // Total technicians
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'technician'");
    $total_technicians = $stmt->fetch()['total'] ?? 0;
    
    // Total services
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services");
    $total_services = $stmt->fetch()['total'] ?? 0;
    
    // Total pending services
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE COALESCE(status, 'pending') = 'pending'");
    $pending_services = $stmt->fetch()['total'] ?? 0;
    
    // Total parts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM parts");
    $total_parts = $stmt->fetch()['total'] ?? 0;
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'] ?? 0;
    
    // Total revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM transactions WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Pending transactions count for notification badge
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_status = 'pending_confirmation'");
    $pending_transactions = $stmt->fetch()['count'] ?? 0;
    
    // Pending applications count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
    $pending_applications = $stmt->fetch()['count'] ?? 0;
    
    // Get monthly revenue for chart (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM transactions 
        WHERE payment_status = 'paid' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_revenue = $stmt->fetchAll();
    
    // [FIX] Get recent orders with proper status handling
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name,
               COALESCE(o.status, 'pending') as safe_status
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();
    
    // [FIX] Get recent services with COALESCE to handle NULL status
    $stmt = $pdo->query("
        SELECT s.*, u.name as customer_name,
               COALESCE(s.status, 'pending') as safe_status
        FROM services s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recent_services = $stmt->fetchAll();
    
    // Get recent users
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
    
    // Get user profile photo
    $user_profile_photo = null;
    $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    if ($user_data && $user_data['profile_photo']) {
        $user_profile_photo = $user_data['profile_photo'];
    }
    
} catch (PDOException $e) {
    error_log("Database error in dashboard: " . $e->getMessage());
    $total_users = $total_technicians = $total_services = $pending_services = 0;
    $total_parts = $total_orders = $total_revenue = $pending_transactions = 0;
    $recent_orders = $recent_services = $recent_users = [];
    $monthly_revenue = [];
    $user_profile_photo = null;
}

// Status text mapping for display
$order_status_text = [
    'pending' => 'Menunggu',
    'paid' => 'Dibayar',
    'shipped' => 'Dikirim',
    'completed' => 'Selesai'
];

$service_status_text = [
    'pending' => 'Menunggu',
    'visit' => 'Kunjungan',
    'accepted' => 'Diterima',
    'repairing' => 'Diperbaiki',
    'done' => 'Selesai'
];

// Status badge class mapping
$order_status_class = [
    'pending' => 'status-pending',
    'paid' => 'status-paid',
    'shipped' => 'status-shipped',
    'completed' => 'status-completed'
];

$service_status_class = [
    'pending' => 'status-pending',
    'visit' => 'status-shipped',
    'accepted' => 'status-info',
    'repairing' => 'status-warning',
    'done' => 'status-completed'
];

// Determine current page for sidebar active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* [Styles tetap sama seperti sebelumnya] */
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
            --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
            --shadow-lg: 0 8px 24px rgba(75, 46, 43, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cream);
            overflow-x: hidden;
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
        
        .brand-icon i {
            font-size: 1.3rem;
            color: white;
        }
        
        .brand-text {
            color: white;
        }
        
        .brand-name {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
        }
        
        .brand-sub {
            font-size: 0.7rem;
            opacity: 0.7;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
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
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(192, 133, 82, 0.2);
            color: var(--gold-brown);
        }
        
        .nav-link i {
            width: 22px;
            font-size: 1.1rem;
        }
        
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--gold-brown), var(--medium-brown));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gold-brown);
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-info p {
            color: var(--medium-brown);
            font-size: 0.75rem;
            margin: 0;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(192, 133, 82, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.05);
            background: rgba(192, 133, 82, 0.15);
        }
        
        .stat-icon i {
            font-size: 1.5rem;
            color: var(--gold-brown);
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            background: white;
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
            font-size: 1.1rem;
        }
        
        .btn-view {
            background: transparent;
            color: var(--gold-brown);
            border: 1px solid var(--gold-brown);
            padding: 0.35rem 1rem;
            border-radius: 30px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .btn-view:hover {
            background: var(--gold-brown);
            color: white;
        }
        
        /* Chart Container */
        .chart-container {
            padding: 1.25rem 1.5rem;
        }
        
        .chart-box {
            position: relative;
            height: 280px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            color: var(--medium-brown);
            font-weight: 500;
            font-size: 0.7rem;
            background: #fafafa;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .data-table td {
            padding: 1rem 1.5rem;
            color: var(--dark-brown);
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(192, 133, 82, 0.05);
        }
        
        .data-table tbody tr:hover {
            background: rgba(192, 133, 82, 0.02);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.12);
            color: #ffc107;
        }
        
        .status-paid,
        .status-completed {
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        
        .status-shipped {
            background: rgba(23, 162, 184, 0.12);
            color: #17a2b8;
        }
        
        .status-warning {
            background: rgba(255, 193, 7, 0.12);
            color: #ffc107;
        }
        
        .status-info {
            background: rgba(23, 162, 184, 0.12);
            color: #17a2b8;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-microchip"></i>
            </div>
            <div class="brand-text">
                <span class="brand-name">Loaz</span>
                <span class="brand-sub">Admin Panel</span>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="technician_applications.php" class="nav-link <?php echo $current_page == 'technician_applications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>
                    <span>Lamaran Teknisi</span>
                    <?php if ($pending_applications > 0): ?>
                        <span class="badge-notif"><?php echo $pending_applications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="technicians.php" class="nav-link <?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Technicians</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="services.php" class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tools"></i>
                    <span>Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="parts.php" class="nav-link <?php echo $current_page == 'parts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-microchip"></i>
                    <span>Parts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="transactions.php" class="nav-link <?php echo $current_page == 'transactions.php' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Transactions</span>
                    <?php if ($pending_transactions > 0): ?>
                        <span class="badge-notif"><?php echo $pending_transactions; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="support_chat.php" class="nav-link">
                    <i class="fas fa-headset"></i>
                    <span>Support Chat</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/loaz_industries/auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Selamat datang kembali, Admin</p>
            </div>
            <div class="user-info">
                <div class="user-detail">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
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
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($total_users); ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($total_technicians); ?></h3>
                    <p>Technicians</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($total_services); ?></h3>
                    <p>Total Services</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($pending_services); ?></h3>
                    <p>Pending Services</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($total_parts); ?></h3>
                    <p>Total Parts</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-microchip"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo number_format($total_orders); ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo formatCurrency($total_revenue); ?></h3>
                    <p>Total Revenue</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo date('d M Y'); ?></h3>
                    <p>Today's Date</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
            </div>
        </div>
        
        <!-- Revenue Chart -->
        <?php if (count($monthly_revenue) > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Revenue Trend (6 Months)</h2>
            </div>
            <div class="chart-container">
                <div class="chart-box">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Orders & Recent Services -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-shopping-cart"></i> Recent Orders</h2>
                        <a href="orders.php" class="btn-view">View All →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_orders) > 0): ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <?php 
                                    // [FIX] Get safe status with default
                                    $order_status = $order['safe_status'] ?? $order['status'] ?? 'pending';
                                    $order_status_display = $order_status_text[$order_status] ?? ucfirst($order_status);
                                    $order_status_class_name = $order_status_class[$order_status] ?? 'status-pending';
                                    ?>
                                    <tr>
                                        <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></td>
                                        <td><?php echo formatCurrency($order['total_price'] ?? 0); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $order_status_class_name; ?>">
                                                <?php echo $order_status_display; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'] ?? 'now')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No orders found</a>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h2><i class="fas fa-tools"></i> Recent Services</h2>
                        <a href="services.php" class="btn-view">View All →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_services) > 0): ?>
                                    <?php foreach ($recent_services as $service): ?>
                                    <?php 
                                    // [FIX] Get safe status with default
                                    $service_status = $service['safe_status'] ?? $service['status'] ?? 'pending';
                                    $service_status_display = $service_status_text[$service_status] ?? ucfirst($service_status);
                                    $service_status_class_name = $service_status_class[$service_status] ?? 'status-pending';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $service['id']; ?></td>
                                        <td><?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($service['device'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $service_status_class_name; ?>">
                                                <?php echo $service_status_display; ?>
                                            </span>
                                        </a>
                                        <td><?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?></a>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No services found</a>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="section-card mt-4">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Recent Users</h2>
                <a href="users.php" class="btn-view">View All →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_users) > 0): ?>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge" style="background: rgba(192, 133, 82, 0.12); color: var(--gold-brown);">
                                        <?php echo ucfirst($user['role'] ?? 'user'); ?>
                                    </span>
                                </a>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'now')); ?></a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">No users found</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Revenue Chart
        <?php if (count($monthly_revenue) > 0): ?>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const months = <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>;
        const revenues = <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>;
        
        // Format month names
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const formattedMonths = months.map(m => {
            const [year, month] = m.split('-');
            return monthNames[parseInt(month) - 1] + ' ' + year;
        });
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedMonths,
                datasets: [{
                    label: 'Revenue (Rp)',
                    data: revenues,
                    borderColor: '#C08552',
                    backgroundColor: 'rgba(192, 133, 82, 0.05)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#C08552',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(192, 133, 82, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>