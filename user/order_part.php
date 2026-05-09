<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// [FIX] Handle add to cart via GET (fallback jika JS tidak jalan)
if (isset($_GET['add']) && !isset($_POST['add_to_cart_ajax'])) {
    $part_id = (int)$_GET['add'];
    $quantity = isset($_GET['qty']) ? max(1, (int)$_GET['qty']) : 1;
    
    try {
        $stmt = $pdo->prepare("SELECT stock, name FROM parts WHERE id = ?");
        $stmt->execute([$part_id]);
        $part = $stmt->fetch();
        
        if ($part) {
            $current_cart_qty = isset($_SESSION['cart'][$part_id]) ? (int)$_SESSION['cart'][$part_id] : 0;
            $new_qty = $current_cart_qty + $quantity;
            
            if ($new_qty <= $part['stock']) {
                $_SESSION['cart'][$part_id] = $new_qty;
                $message = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($part['name']) . ' berhasil ditambahkan ke keranjang!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } else {
                $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>Stok ' . htmlspecialchars($part['name']) . ' tidak mencukupi! Tersisa ' . $part['stock'] . ' unit.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>Part tidak ditemukan.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    } catch (PDOException $e) {
        error_log('Add to cart error: ' . $e->getMessage());
        $message = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>Terjadi kesalahan, silakan coba lagi.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    
    // Redirect with message
    $_SESSION['cart_message'] = $message;
    header('Location: order_part.php?page=' . $page . ($category_filter ? '&category=' . urlencode($category_filter) : '') . ($search ? '&search=' . urlencode($search) : ''));
    exit();
}

// Get message from session or query param
$message = '';
if (isset($_SESSION['cart_message'])) {
    $message = $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
} elseif (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// [FIX] Validasi category filter
$valid_categories = ['smartphone', 'laptop', 'tablet', 'tv', 'ac', 'washer', 'audio', 'camera', 'gaming', 'other'];
if ($category_filter && !in_array($category_filter, $valid_categories)) {
    $category_filter = '';
}

// Build query for counting
$count_query = "SELECT COUNT(*) as total FROM parts WHERE stock > 0";
$params = [];

if ($category_filter && $category_filter != 'all') {
    $count_query .= " AND category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $count_query .= " AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Get total count for pagination
$total_parts = 0;
$total_pages = 0;
try {
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_parts = $result ? (int)$result['total'] : 0;
    $total_pages = $total_parts > 0 ? ceil($total_parts / $limit) : 0;
} catch (PDOException $e) {
    error_log('Count query error: ' . $e->getMessage());
}

// [FIX] Validasi page tidak melebihi total pages
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Build query for parts
$query = "SELECT * FROM parts WHERE stock > 0";
$params = [];

if ($category_filter && $category_filter != 'all') {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if ($search) {
    $query .= " AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY id DESC LIMIT $offset, $limit";

$parts = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $parts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Parts query error: ' . $e->getMessage());
}

// Get max stock for progress bar
$max_stock = 100;
foreach ($parts as $part) {
    if ($part['stock'] > $max_stock) $max_stock = $part['stock'];
}

// Get categories for filter
$categories_count = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category, COUNT(*) as count FROM parts WHERE stock > 0 GROUP BY category");
    $categories_count = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Categories query error: ' . $e->getMessage());
}

// Category labels with icons
$category_labels = [
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

// Get cart items count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cart_count += (int)$qty;
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar Filter -->
        <div class="col-lg-3 mb-4 mb-lg-0">
            <div class="filter-card rounded-4 p-4 sticky-top" style="top: 80px;">
                <h5 class="mb-3 fw-semibold" style="color: var(--dark-brown);">
                    <i class="fas fa-filter me-2" style="color: var(--gold-brown);"></i>Kategori
                </h5>
                
                <div class="mb-4">
                    <a href="order_part.php?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-link <?php echo !$category_filter ? 'active' : ''; ?>">
                        <i class="fas fa-microchip me-2"></i> Semua Part
                    </a>
                    <?php foreach ($categories_count as $cat): ?>
                        <?php $cat_name = htmlspecialchars($cat['category']); ?>
                        <a href="?category=<?php echo urlencode($cat_name); ?>&page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-link <?php echo $category_filter == $cat_name ? 'active' : ''; ?>">
                            <?php echo isset($category_labels[$cat_name]) ? $category_labels[$cat_name] : htmlspecialchars($cat_name); ?>
                            <span class="badge rounded-pill float-end"><?php echo (int)$cat['count']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <hr class="my-3" style="border-color: rgba(192, 133, 82, 0.2);">
                
                <div class="mt-3">
                    <a href="cart.php" class="btn btn-outline-gold w-100 rounded-4 position-relative">
                        <i class="fas fa-shopping-cart me-2"></i> Lihat Keranjang
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Belanja Part Elektronik</h1>
                    <p class="text-muted">Part original dengan garansi resmi</p>
                </div>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Search Bar -->
            <div class="mb-4">
                <form method="GET" class="input-group">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="fas fa-search" style="color: var(--gold-brown);"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari part elektronik..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if ($category_filter): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="btn btn-gold rounded-4 px-4">
                        <i class="fas fa-search me-2"></i> Cari
                    </button>
                    <?php if ($search || $category_filter): ?>
                        <a href="order_part.php" class="btn btn-outline-secondary rounded-4 ms-2">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Parts Grid -->
            <div class="row g-4" id="partsGrid">
                <?php if (count($parts) > 0): ?>
                    <?php foreach ($parts as $part): ?>
                        <div class="col-md-6 col-lg-4 part-item">
                            <div class="part-card rounded-4 h-100">
                                <!-- Image -->
                                <div class="part-image-wrapper">
                                    <?php if (!empty($part['image']) && file_exists('../uploads/parts/' . $part['image'])): ?>
                                        <img src="../uploads/parts/<?php echo htmlspecialchars($part['image']); ?>" alt="<?php echo htmlspecialchars($part['name']); ?>" class="part-image">
                                    <?php else: ?>
                                        <div class="part-image-placeholder">
                                            <i class="fas fa-microchip fa-3x" style="color: var(--gold-brown);"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Category Badge -->
                                    <span class="part-category-badge">
                                        <?php echo isset($category_labels[$part['category']]) ? $category_labels[$part['category']] : '🔧 Part'; ?>
                                    </span>
                                </div>
                                
                                <div class="part-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="part-name mb-1"><?php echo htmlspecialchars($part['name']); ?></h5>
                                            <?php if (!empty($part['brand'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($part['brand']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="part-price"><?php echo formatCurrency($part['price']); ?></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="text-muted">Stok</small>
                                            <small class="stock-count"><?php echo (int)$part['stock']; ?> unit</small>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar" style="width: <?php echo $max_stock > 0 ? min(100, ($part['stock'] / $max_stock) * 100) : 0; ?>%; background: var(--gold-brown);"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt me-1"></i> Garansi <?php echo (int)$part['warranty_months']; ?> bulan
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <div class="input-group">
                                            <button class="btn btn-outline-secondary qty-minus" type="button">-</button>
                                            <input type="number" class="form-control text-center qty-input" value="1" min="1" max="<?php echo (int)$part['stock']; ?>">
                                            <button class="btn btn-outline-secondary qty-plus" type="button">+</button>
                                        </div>
                                        <button class="btn btn-gold rounded-4 add-to-cart" data-id="<?php echo (int)$part['id']; ?>" data-stock="<?php echo (int)$part['stock']; ?>" data-name="<?php echo htmlspecialchars($part['name']); ?>">
                                            <i class="fas fa-cart-plus me-2"></i> Tambah ke Keranjang
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-microchip fa-4x text-muted mb-3"></i>
                        <h3>Tidak Ada Part Ditemukan</h3>
                        <p class="text-muted">Coba cari dengan kata kunci lain atau lihat kategori lain</p>
                        <a href="order_part.php" class="btn btn-gold rounded-4 mt-3">
                            <i class="fas fa-refresh me-2"></i> Reset Filter
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">1</a></li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a></li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cart Sidebar -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-sidebar-header">
        <h5><i class="fas fa-shopping-cart me-2"></i> Keranjang Belanja</h5>
        <button class="btn-close-cart" id="closeCart">&times;</button>
    </div>
    <div class="cart-sidebar-body" id="cartContent">
        <div class="text-center py-4">
            <div class="spinner-border text-gold" role="status"></div>
            <p class="text-muted mt-2">Memuat keranjang...</p>
        </div>
    </div>
</div>
<div class="cart-overlay" id="cartOverlay"></div>

<style>
    /* [Style tetap sama seperti sebelumnya] */
    :root {
        --cream: #FFF8F0;
        --gold-brown: #C08552;
        --medium-brown: #8C5A3C;
        --dark-brown: #4B2E2B;
    }
    
    .text-gold { color: var(--gold-brown); }
    
    .filter-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
    }
    
    .category-link {
        display: block;
        padding: 0.5rem 0;
        color: var(--medium-brown);
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .category-link:hover, .category-link.active {
        color: var(--gold-brown);
        padding-left: 5px;
    }
    
    .category-link .badge {
        background: rgba(192, 133, 82, 0.1);
        color: var(--gold-brown);
    }
    
    .part-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .part-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(75, 46, 43, 0.1);
    }
    
    .part-image-wrapper {
        position: relative;
        height: 180px;
        background: rgba(192, 133, 82, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .part-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .part-image-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    .part-category-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: white;
        padding: 0.2rem 0.7rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--gold-brown);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .part-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin: 0;
    }
    
    .part-price {
        font-weight: 700;
        color: var(--gold-brown);
    }
    
    .progress {
        background: rgba(192, 133, 82, 0.1);
        border-radius: 4px;
    }
    
    .progress-bar {
        border-radius: 4px;
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
    
    .qty-input {
        max-width: 60px;
        text-align: center;
    }
    
    .cart-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--gold-brown);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Cart Sidebar */
    .cart-sidebar {
        position: fixed;
        top: 0;
        right: -400px;
        width: 380px;
        height: 100vh;
        background: white;
        box-shadow: -5px 0 20px rgba(0,0,0,0.1);
        z-index: 1050;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }
    
    .cart-sidebar.open {
        right: 0;
    }
    
    .cart-sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .btn-close-cart {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--medium-brown);
    }
    
    .cart-sidebar-body {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    
    .cart-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
        display: none;
    }
    
    .cart-overlay.show {
        display: block;
    }
    
    .alert-success {
        background: rgba(192, 133, 82, 0.12);
        border: none;
        color: var(--gold-brown);
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }
    
    /* Pagination */
    .pagination .page-link {
        color: var(--gold-brown);
        border: 1px solid rgba(192, 133, 82, 0.2);
        background: white;
        padding: 0.5rem 0.9rem;
        margin: 0 3px;
        border-radius: 10px;
        transition: all 0.3s ease;
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
    
    .sticky-top {
        position: sticky;
        top: 80px;
    }
    
    @media (max-width: 768px) {
        .cart-sidebar {
            width: 320px;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let isLoading = false;

// Quantity buttons
$('.qty-minus').on('click', function() {
    let input = $(this).closest('.input-group').find('.qty-input');
    let val = parseInt(input.val()) || 1;
    let min = parseInt(input.attr('min')) || 1;
    if (val > min) input.val(val - 1);
});

$('.qty-plus').on('click', function() {
    let input = $(this).closest('.input-group').find('.qty-input');
    let max = parseInt(input.attr('max')) || 999;
    let val = parseInt(input.val()) || 1;
    if (val < max) input.val(val + 1);
});

// Add to cart with AJAX
$('.add-to-cart').on('click', function() {
    if (isLoading) return;
    
    let btn = $(this);
    let partId = btn.data('id');
    let qty = btn.closest('.part-body').find('.qty-input').val();
    let stock = btn.data('stock');
    let partName = btn.data('name');
    
    // Validate quantity
    qty = parseInt(qty) || 1;
    if (qty < 1) {
        showNotification('Jumlah minimal 1', 'danger');
        return;
    }
    
    if (qty > stock) {
        showNotification('Stok tidak mencukupi! Stok tersisa ' + stock + ' unit', 'danger');
        return;
    }
    
    isLoading = true;
    let originalHtml = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin me-2"></i> Menambahkan...').prop('disabled', true);
    
    $.ajax({
        url: 'add_to_cart_ajax.php',
        method: 'POST',
        data: { part_id: partId, quantity: qty },
        dataType: 'json',
        success: function(response) {
            if (response && response.success) {
                showNotification(partName + ' ' + (response.message || 'berhasil ditambahkan ke keranjang!'), 'success');
                updateCartSidebar();
                updateCartBadge();
                openCartSidebar();
            } else {
                showNotification((response && response.message) || 'Gagal menambahkan ke keranjang', 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showNotification('Gagal menambahkan ke keranjang. Silakan coba lagi.', 'danger');
        },
        complete: function() {
            isLoading = false;
            btn.html(originalHtml).prop('disabled', false);
        }
    });
});

// Cart sidebar functions
function openCartSidebar() {
    $('#cartSidebar').addClass('open');
    $('#cartOverlay').addClass('show');
    $('body').css('overflow', 'hidden');
}

function closeCartSidebar() {
    $('#cartSidebar').removeClass('open');
    $('#cartOverlay').removeClass('show');
    $('body').css('overflow', '');
}

function updateCartSidebar() {
    $.get('get_cart_ajax.php', function(data) {
        $('#cartContent').html(data);
        // Reattach event handlers for remove buttons
        $('.remove-item').off('click').on('click', function() {
            let partId = $(this).data('id');
            $.post('remove_from_cart_ajax.php', { part_id: partId }, function() {
                updateCartSidebar();
                updateCartBadge();
                showNotification('Item berhasil dihapus dari keranjang', 'success');
            }).fail(function() {
                showNotification('Gagal menghapus item', 'danger');
            });
        });
    }).fail(function() {
        $('#cartContent').html('<div class="text-center py-4"><p class="text-danger">Gagal memuat keranjang</p></div>');
    });
}

function updateCartBadge() {
    $.get('get_cart_count.php', function(data) {
        let badge = $('.cart-badge');
        if (data && data.count > 0) {
            if (badge.length) {
                badge.text(data.count);
            } else {
                $('.btn-outline-gold').append('<span class="cart-badge">' + data.count + '</span>');
            }
        } else {
            badge.remove();
        }
    }, 'json').fail(function() {
        console.error('Failed to update cart badge');
    });
}

function showNotification(message, type) {
    let alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    let alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index: 1060; min-width: 300px; border-radius: 16px;" role="alert">
            <i class="fas ${icon} me-2"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(alert);
    
    setTimeout(function() {
        alert.fadeOut('slow', function() {
            $(this).remove();
        });
    }, 3000);
}

$(document).ready(function() {
    updateCartSidebar();
    updateCartBadge();
    
    $('#closeCart, #cartOverlay').on('click', function() {
        closeCartSidebar();
    });
    
    // Close sidebar on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#cartSidebar').hasClass('open')) {
            closeCartSidebar();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>