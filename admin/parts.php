<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();
redirectIfNotAdmin();

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle delete part
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Get image to delete
    $stmt = $pdo->prepare("SELECT image FROM parts WHERE id = ?");
    $stmt->execute([$id]);
    $part = $stmt->fetch();
    if ($part && $part['image']) {
        $image_path = '../uploads/parts/' . $part['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $stmt = $pdo->prepare("DELETE FROM parts WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: parts.php?msg=deleted');
    exit();
}

// Handle add/edit part
$edit_part = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
    $stmt->execute([$id]);
    $edit_part = $stmt->fetch();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category = $_POST['category'];
    $brand = trim($_POST['brand']);
    $warranty_months = (int)$_POST['warranty_months'];
    $description = trim($_POST['description']);
    $image = $edit_part['image'] ?? null;
    
    // Validate
    if (empty($name)) {
        $error = 'Nama part harus diisi!';
    } elseif ($price <= 0) {
        $error = 'Harga harus lebih dari 0!';
    } elseif ($stock < 0) {
        $error = 'Stok tidak boleh negatif!';
    }
    
    // Handle image upload
    if (empty($error) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if ($_FILES['image']['size'] > $max_size) {
            $error = 'Ukuran file terlalu besar! Maksimal 2MB.';
        } elseif (!in_array($ext, $allowed)) {
            $error = 'Format file tidak didukung! Gunakan JPG, PNG, WEBP, GIF.';
        } else {
            // Delete old image
            if ($image && file_exists('../uploads/parts/' . $image)) {
                unlink('../uploads/parts/' . $image);
            }
            $image = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($filename, PATHINFO_FILENAME)) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/parts/' . $image);
        }
    }
    
    if (empty($error)) {
        if (isset($_POST['edit_id']) && $_POST['edit_id']) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE parts SET name = ?, price = ?, stock = ?, category = ?, brand = ?, 
                warranty_months = ?, description = ?, image = ? WHERE id = ?
            ");
            $stmt->execute([$name, $price, $stock, $category, $brand, $warranty_months, $description, $image, $_POST['edit_id']]);
            header('Location: parts.php?msg=updated');
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO parts (name, price, stock, category, brand, warranty_months, description, image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $stock, $category, $brand, $warranty_months, $description, $image]);
            header('Location: parts.php?msg=added');
        }
        exit();
    }
}

// Build where clause with search
$where_clause = "";
$params = [];

if ($search) {
    $where_clause = "WHERE name LIKE ? OR brand LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM parts $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_parts = $stmt->fetch()['total'];
$total_pages = ceil($total_parts / $limit);

// Get parts with pagination
$sql = "SELECT * FROM parts $where_clause ORDER BY id DESC LIMIT $offset, $limit";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Category list
$categories = [
    'smartphone' => '📱 Smartphone',
    'laptop' => '💻 Laptop',
    'tablet' => '📟 Tablet',
    'tv' => '📺 TV & LED',
    'ac' => '❄️ AC & Kulkas',
    'washer' => '🧺 Mesin Cuci',
    'audio' => '🎵 Audio & Speaker',
    'camera' => '📷 Kamera',
    'gaming' => '🎮 Gaming',
    'other' => '🔧 Lainnya'
];

$message = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Part berhasil ditambahkan!</div>';
    if ($_GET['msg'] == 'updated') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Part berhasil diupdate!</div>';
    if ($_GET['msg'] == 'deleted') $message = '<div class="alert-custom alert-success-custom"><i class="fas fa-check-circle me-2"></i>Part berhasil dihapus!</div>';
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

// Get low stock count
$stmt = $pdo->query("SELECT COUNT(*) as count FROM parts WHERE stock <= 5");
$low_stock_count = $stmt->fetch()['count'] ?? 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parts Management - Loaz Industries</title>
    
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
            min-width: 200px;
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
        
        .stat-badge-low-stock {
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
        
        /* Category Badges */
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .cat-smartphone { background: rgba(192, 133, 82, 0.12); color: var(--gold-brown); }
        .cat-laptop { background: rgba(23, 162, 184, 0.12); color: #17a2b8; }
        .cat-tv { background: rgba(40, 167, 69, 0.12); color: #28a745; }
        .cat-default { background: rgba(108, 117, 125, 0.12); color: #6c757d; }
        
        /* Part Image */
        .part-image {
            width: 40px;
            height: 40px;
            background: rgba(192, 133, 82, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .part-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .low-stock {
            color: #dc3545;
            font-weight: bold;
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
        
        /* Image Preview */
        .image-preview {
            margin-top: 0.5rem;
            display: none;
        }
        
        .image-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid rgba(192, 133, 82, 0.2);
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
            <li class="nav-item"><a href="parts.php" class="nav-link <?php echo $current_page == 'parts.php' ? 'active' : ''; ?>"><i class="fas fa-microchip"></i><span>Parts</span>
            </a></li>
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
                <h1>Parts Management</h1>
                <p>Kelola semua part elektronik</p>
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
        <?php if ($error): ?>
            <div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-microchip me-2"></i> All Parts</h2>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($low_stock_count > 0): ?>
                        <span class="stat-badge stat-badge-low-stock"><i class="fas fa-exclamation-triangle me-1"></i> Low Stock: <?php echo $low_stock_count; ?></span>
                    <?php endif; ?>
                    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addPartModal">
                        <i class="fas fa-plus me-1"></i> Add Part
                    </button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="d-flex gap-3 flex-wrap w-100 align-items-end">
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i> Cari Part</label>
                        <input type="text" name="search" class="form-control" placeholder="Nama / Brand" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter w-100"><i class="fas fa-search me-1"></i> Cari</button>
                    </div>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="parts.php" class="btn btn-outline-secondary w-100" style="border-radius: 12px; padding: 0.6rem 1.2rem;">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Warranty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($parts) > 0): ?>
                            <?php foreach ($parts as $part): ?>
                            <tr>
                                <td>
                                    <div class="part-image">
                                        <?php if ($part['image'] && file_exists('../uploads/parts/' . $part['image'])): ?>
                                            <img src="../uploads/parts/<?php echo $part['image']; ?>" alt="<?php echo htmlspecialchars($part['name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-microchip fa-2x" style="color: var(--gold-brown);"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($part['name']); ?></td>
                                <td>
                                    <span class="category-badge cat-<?php 
                                        echo in_array($part['category'], ['smartphone', 'laptop', 'tv']) ? $part['category'] : 'default'; 
                                    ?>">
                                        <?php echo $categories[$part['category']] ?? $part['category']; ?>
                                    </span>
                                 </a>
                                <td><?php echo htmlspecialchars($part['brand'] ?? '-'); ?></a>
                                <td><?php echo formatCurrency($part['price']); ?></a>
                                <td>
                                    <?php if ($part['stock'] <= 5): ?>
                                        <span class="low-stock"><?php echo $part['stock']; ?> ⚠️</span>
                                    <?php else: ?>
                                        <?php echo $part['stock']; ?>
                                    <?php endif; ?>
                                 </a>
                                <td><?php echo $part['warranty_months']; ?> bulan</a>
                                <td>
                                    <a href="?edit=<?php echo $part['id']; ?>" class="btn-action" data-bs-toggle="modal" data-bs-target="#editPartModal" onclick="editPart(<?php echo htmlspecialchars(json_encode($part)); ?>)" title="Edit Part">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $part['id']; ?>" class="btn-action" onclick="return confirm('Yakin ingin menghapus part <?php echo htmlspecialchars($part['name']); ?>?')" style="color: #dc3545;" title="Delete Part">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                 </a>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">Tidak ada part ditemukan</a>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add Part Modal -->
    <div class="modal fade" id="addPartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Tambah Part Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="addPartForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Part <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Merek/Brand</label>
                                    <input type="text" name="brand" class="form-control" placeholder="Contoh: Samsung, ASUS, LG">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kategori</label>
                                    <select name="category" class="form-select" required>
                                        <?php foreach ($categories as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Harga <span class="text-danger">*</span></label>
                                    <input type="number" name="price" class="form-control" required min="0" step="1000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stok <span class="text-danger">*</span></label>
                                    <input type="number" name="stock" class="form-control" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Garansi (bulan)</label>
                                    <input type="number" name="warranty_months" class="form-control" value="12" min="0">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Gambar Part</label>
                                    <input type="file" name="image" id="addImageInput" class="form-control" accept="image/*">
                                    <small class="text-muted">Format: JPG, PNG, WEBP. Max 2MB</small>
                                    <div id="addImagePreview" class="image-preview">
                                        <img id="addPreviewImg" src="#" alt="Preview">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-modal-save">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Part Modal -->
    <div class="modal fade" id="editPartModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Part</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editPartForm">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Part</label>
                                    <input type="text" name="name" id="edit_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Merek/Brand</label>
                                    <input type="text" name="brand" id="edit_brand" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kategori</label>
                                    <select name="category" id="edit_category" class="form-select" required>
                                        <?php foreach ($categories as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Harga</label>
                                    <input type="number" name="price" id="edit_price" class="form-control" required min="0" step="1000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stok</label>
                                    <input type="number" name="stock" id="edit_stock" class="form-control" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Garansi (bulan)</label>
                                    <input type="number" name="warranty_months" id="edit_warranty" class="form-control" min="0">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Gambar Part</label>
                                    <input type="file" name="image" id="editImageInput" class="form-control" accept="image/*">
                                    <small class="text-muted">Kosongkan jika tidak ingin mengubah gambar</small>
                                    <div id="editImagePreview" class="image-preview">
                                        <img id="editPreviewImg" src="#" alt="Preview">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-modal-save">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPart(part) {
            document.getElementById('edit_id').value = part.id;
            document.getElementById('edit_name').value = part.name;
            document.getElementById('edit_brand').value = part.brand || '';
            document.getElementById('edit_category').value = part.category || 'other';
            document.getElementById('edit_price').value = part.price;
            document.getElementById('edit_stock').value = part.stock;
            document.getElementById('edit_warranty').value = part.warranty_months || 12;
            document.getElementById('edit_description').value = part.description || '';
            
            // Reset image preview
            const previewImg = document.getElementById('editPreviewImg');
            if (part.image && part.image !== 'null') {
                previewImg.src = '../uploads/parts/' + part.image;
                previewImg.parentElement.style.display = 'block';
            } else {
                previewImg.parentElement.style.display = 'none';
            }
        }
        
        // Image preview for add modal
        document.getElementById('addImageInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewDiv = document.getElementById('addImagePreview');
            const previewImg = document.getElementById('addPreviewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    previewDiv.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.style.display = 'none';
            }
        });
        
        // Image preview for edit modal
        document.getElementById('editImageInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewDiv = document.getElementById('editImagePreview');
            const previewImg = document.getElementById('editPreviewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    previewDiv.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>