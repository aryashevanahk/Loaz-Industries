<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Get date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate dates
if ($start_date > $end_date) {
    $start_date = $end_date;
}

// Get counts for badges
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

try {
    // Get revenue by period
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(total_amount) as daily_revenue,
            COUNT(*) as transaction_count
        FROM transactions 
        WHERE payment_status = 'paid' 
            AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $daily_revenue = $stmt->fetchAll();

    // Get total revenue
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(*) as total_transactions
        FROM transactions 
        WHERE payment_status = 'paid' 
            AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $totals = $stmt->fetch();

    // Get service statistics
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM services 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$start_date, $end_date]);
    $service_stats = $stmt->fetchAll();

    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            COALESCE(SUM(total_price), 0) as total
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$start_date, $end_date]);
    $order_stats = $stmt->fetchAll();

    // Get top parts
    $stmt = $pdo->prepare("
        SELECT p.name, SUM(oi.quantity) as total_sold
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_parts = $stmt->fetchAll();

    // Get technician performance
    $stmt = $pdo->prepare("
        SELECT 
            u.name as technician_name,
            COUNT(s.id) as total_services,
            COUNT(CASE WHEN s.status = 'done' THEN 1 END) as completed_services,
            COALESCE(SUM(s.estimated_cost), 0) as total_earnings,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM technicians t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN services s ON t.id = s.technician_id AND DATE(s.created_at) BETWEEN ? AND ?
        LEFT JOIN reviews r ON t.id = r.technician_id
        GROUP BY t.id, u.name
        ORDER BY completed_services DESC
        LIMIT 5
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_technicians = $stmt->fetchAll();

    // Get low stock parts (stok <= 5)
    $stmt = $pdo->prepare("
        SELECT name, stock, brand, price
        FROM parts 
        WHERE stock <= 5
        ORDER BY stock ASC
        LIMIT 10
    ");
    $stmt->execute();
    $low_stock_parts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Report error: " . $e->getMessage());
    $daily_revenue = [];
    $totals = ['total_revenue' => 0, 'total_transactions' => 0];
    $service_stats = [];
    $order_stats = [];
    $top_parts = [];
    $top_technicians = [];
    $low_stock_parts = [];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .filter-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid rgba(192, 133, 82, 0.25);
            border-radius: 12px;
            font-size: 0.85rem;
            transition: var(--transition);
            background: white;
        }
        
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
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-export:hover {
            background: var(--gold-brown);
            color: white;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--dark-brown) 0%, #5C3A36 100%);
            border-radius: 16px;
            padding: 1.2rem;
            color: white;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card h3 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            font-size: 0.7rem;
            opacity: 0.8;
            margin: 0;
            margin-top: 0.25rem;
        }
        
        /* Section Card */
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
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
        
        /* Chart Container */
        .chart-container {
            padding: 0.5rem 0;
        }
        
        .chart-box {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 0.8rem 0.5rem;
            color: var(--medium-brown);
            font-weight: 600;
            font-size: 0.7rem;
            background: #fafafa;
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .data-table td {
            padding: 0.8rem 0.5rem;
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
            padding: 0.25rem 0.85rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-pending { background: rgba(255, 193, 7, 0.12); color: #ffc107; }
        .status-accepted { background: rgba(23, 162, 184, 0.12); color: #17a2b8; }
        .status-repairing { background: rgba(192, 133, 82, 0.12); color: var(--gold-brown); }
        .status-done { background: rgba(40, 167, 69, 0.12); color: #28a745; }
        
        .status-paid { background: rgba(40, 167, 69, 0.12); color: #28a745; }
        .status-shipped { background: rgba(23, 162, 184, 0.12); color: #17a2b8; }
        .status-completed { background: rgba(40, 167, 69, 0.12); color: #28a745; }
        
        /* Low Stock */
        .low-stock {
            color: #dc3545;
            font-weight: bold;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.6rem 0.4rem;
                font-size: 0.7rem;
            }
        }
        
        @media print {
            .sidebar, .top-header, .filter-bar, .btn-export, .section-header a {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .section-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            
            .stat-card {
                break-inside: avoid;
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
                <h1>Reports & Analytics</h1>
                <p>Laporan keuangan dan statistik bisnis</p>
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
        
        <!-- Filter Form -->
        <div class="filter-bar">
            <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn-filter w-100"><i class="fas fa-chart-line me-1"></i> Filter Reports</button>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="button" class="btn-export" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="button" class="btn-export" onclick="exportToCSV()"><i class="fas fa-file-excel me-1"></i> Export CSV</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo formatCurrency($totals['total_revenue'] ?? 0); ?></h3>
                <p>Total Revenue</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($totals['total_transactions'] ?? 0); ?></h3>
                <p>Total Transactions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></h3>
                <p>Period</p>
            </div>
        </div>
        
        <!-- Revenue Chart -->
        <?php if (count($daily_revenue) > 0): ?>
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-chart-line me-2"></i> Revenue Trend</h2>
            </div>
            <div class="chart-container">
                <div class="chart-box">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Daily Revenue -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-table me-2"></i> Daily Revenue</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($daily_revenue) > 0): ?>
                            <?php foreach ($daily_revenue as $day): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                <td><?php echo number_format($day['transaction_count']); ?> transactions</a>
                                <td><?php echo formatCurrency($day['daily_revenue']); ?></a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">No data available</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Service Statistics -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-tools me-2"></i> Service Statistics</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($service_stats) > 0): ?>
                            <?php foreach ($service_stats as $stat): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo $stat['status']; ?>">
                                        <?php echo ucfirst($stat['status']); ?>
                                    </span>
                                </a>
                                <td><?php echo number_format($stat['count']); ?> services</a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-center py-4">No data available</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </tr>
            </div>
        </div>
        
        <!-- Order Statistics -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-shopping-cart me-2"></i> Order Statistics</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($order_stats) > 0): ?>
                            <?php foreach ($order_stats as $stat): ?>
                            <tr>
                                <td>
                                    <span class="status-badge status-<?php echo $stat['status']; ?>">
                                        <?php echo ucfirst($stat['status']); ?>
                                    </span>
                                </a>
                                <td><?php echo number_format($stat['count']); ?> orders</a>
                                <td><?php echo formatCurrency($stat['total']); ?></a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4">No data available</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Selling Parts -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-microchip me-2"></i> Top Selling Parts</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part Name</th>
                            <th>Total Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_parts) > 0): ?>
                            <?php foreach ($top_parts as $part): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($part['name']); ?></a>
                                <td><?php echo number_format($part['total_sold']); ?> units</a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-center py-4">No data available</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Technician Performance -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-trophy me-2"></i> Top Technicians</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th>Total Services</th>
                            <th>Completed</th>
                            <th>Earnings</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_technicians) > 0): ?>
                            <?php foreach ($top_technicians as $tech): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tech['technician_name']); ?></a>
                                <td><?php echo number_format($tech['total_services']); ?></a>
                                <td><?php echo number_format($tech['completed_services']); ?></a>
                                <td><?php echo formatCurrency($tech['total_earnings']); ?></a>
                                <td>
                                    <div class="rating-stars">
                                        <?php 
                                            $rating = round($tech['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++):
                                        ?>
                                            <?php if ($i <= $rating): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-muted"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span class="ms-1 small">(<?php echo number_format($tech['avg_rating'], 1); ?>)</span>
                                    </div>
                                </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No data available</a>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Low Stock Alert -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle me-2" style="color: #dc3545;"></i> Low Stock Alert</h2>
                <a href="parts.php" class="btn-export">Manage Parts →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part Name</th>
                            <th>Brand</th>
                            <th>Stock</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($low_stock_parts) > 0): ?>
                            <?php foreach ($low_stock_parts as $part): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($part['name']); ?></a>
                                <td><?php echo htmlspecialchars($part['brand'] ?? '-'); ?></a>
                                <td><span class="low-stock"><?php echo $part['stock']; ?> units ⚠️</span></a>
                                <td><?php echo formatCurrency($part['price']); ?></a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">All stocks are healthy ✅</a>
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
        <?php if (count($daily_revenue) > 0): ?>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const dates = <?php echo json_encode(array_reverse(array_column($daily_revenue, 'date'))); ?>;
        const revenues = <?php echo json_encode(array_reverse(array_column($daily_revenue, 'daily_revenue'))); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates.map(d => new Date(d).toLocaleDateString('id-ID')),
                datasets: [{
                    label: 'Daily Revenue (Rp)',
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
        
        // Export to CSV
        function exportToCSV() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            window.location.href = 'export_report.php?start_date=' + startDate + '&end_date=' + endDate;
        }
    </script>
</body>
</html>