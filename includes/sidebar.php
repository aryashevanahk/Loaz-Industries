<?php
// Sidebar component untuk dashboard
function renderSidebar($activePage = '') {
    $role = $_SESSION['role'] ?? 'user';
    
    // Get counts for badges
    global $pdo;
    
    // Pending applications count (for admin)
    $pending_applications = 0;
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM technician_applications WHERE status = 'pending'");
        $pending_applications = $stmt->fetch()['count'] ?? 0;
    }
    
    // Pending transactions count (for admin)
    $pending_transactions = 0;
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE payment_status = 'pending_confirmation'");
        $pending_transactions = $stmt->fetch()['count'] ?? 0;
    }
    ?>
    <div class="col-md-3 mb-4">
        <div class="sidebar-menu">
            <?php if ($role === 'admin'): ?>
                <!-- Admin Menu -->
                <div class="sidebar-section">
                    <h5 class="sidebar-title">Main</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/admin/dashboard.php" class="list-group-item list-group-item-action <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="/loaz_industries/admin/users.php" class="list-group-item list-group-item-action <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> Users
                        </a>
                        <a href="/loaz_industries/admin/technician_applications.php" class="list-group-item list-group-item-action <?php echo $activePage === 'applications' ? 'active' : ''; ?>">
                            <i class="fas fa-briefcase"></i> Lamaran Teknisi
                            <?php if ($pending_applications > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $pending_applications; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Management</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/admin/technicians.php" class="list-group-item list-group-item-action <?php echo $activePage === 'technicians' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i> Technicians
                        </a>
                        <a href="/loaz_industries/admin/services.php" class="list-group-item list-group-item-action <?php echo $activePage === 'services' ? 'active' : ''; ?>">
                            <i class="fas fa-tools"></i> Services
                        </a>
                        <a href="/loaz_industries/admin/parts.php" class="list-group-item list-group-item-action <?php echo $activePage === 'parts' ? 'active' : ''; ?>">
                            <i class="fas fa-microchip"></i> Parts
                        </a>
                        <a href="/loaz_industries/admin/orders.php" class="list-group-item list-group-item-action <?php echo $activePage === 'orders' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i> Orders
                        </a>
                        <a href="/loaz_industries/admin/transactions.php" class="list-group-item list-group-item-action <?php echo $activePage === 'transactions' ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill-wave"></i> Transactions
                            <?php if ($pending_transactions > 0): ?>
                                <span class="badge bg-warning ms-2"><?php echo $pending_transactions; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Reports & Support</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/admin/reports.php" class="list-group-item list-group-item-action <?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <a href="/loaz_industries/admin/support_chat.php" class="list-group-item list-group-item-action <?php echo $activePage === 'support_chat' ? 'active' : ''; ?>">
                            <i class="fas fa-headset"></i> Support Chat
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Account</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/admin/profile.php" class="list-group-item list-group-item-action <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </div>
                </div>
                
            <?php elseif ($role === 'technician'): ?>
                <!-- Technician Menu -->
                <div class="sidebar-section">
                    <h5 class="sidebar-title">Main</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/technician/dashboard.php" class="list-group-item list-group-item-action <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="/loaz_industries/technician/my_services.php" class="list-group-item list-group-item-action <?php echo $activePage === 'services' ? 'active' : ''; ?>">
                            <i class="fas fa-tasks"></i> My Services
                        </a>
                        <a href="/loaz_industries/technician/update_status.php" class="list-group-item list-group-item-action <?php echo $activePage === 'update_status' ? 'active' : ''; ?>">
                            <i class="fas fa-sync-alt"></i> Update Status
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Parts & Earnings</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/technician/order_part.php" class="list-group-item list-group-item-action <?php echo $activePage === 'order_part' ? 'active' : ''; ?>">
                            <i class="fas fa-microchip"></i> Order Parts
                        </a>
                        <a href="/loaz_industries/technician/earnings.php" class="list-group-item list-group-item-action <?php echo $activePage === 'earnings' ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill-wave"></i> Earnings
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Account</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/technician/profile.php" class="list-group-item list-group-item-action <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- User Menu -->
                <div class="sidebar-section">
                    <h5 class="sidebar-title">Main</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/user/dashboard.php" class="list-group-item list-group-item-action <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="/loaz_industries/user/request_service.php" class="list-group-item list-group-item-action <?php echo $activePage === 'request' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i> Request Service
                        </a>
                        <a href="/loaz_industries/user/my_services.php" class="list-group-item list-group-item-action <?php echo $activePage === 'my_services' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> My Services
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Shopping</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/user/order_part.php" class="list-group-item list-group-item-action <?php echo $activePage === 'order_part' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i> Order Parts
                        </a>
                        <a href="/loaz_industries/user/cart.php" class="list-group-item list-group-item-action <?php echo $activePage === 'cart' ? 'active' : ''; ?>">
                            <i class="fas fa-cart-shopping"></i> Cart
                        </a>
                        <a href="/loaz_industries/user/my_orders.php" class="list-group-item list-group-item-action <?php echo $activePage === 'my_orders' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i> My Orders
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Support</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/user/chat.php" class="list-group-item list-group-item-action <?php echo $activePage === 'chat' ? 'active' : ''; ?>">
                            <i class="fas fa-comments"></i> Chat Support
                        </a>
                        <a href="/loaz_industries/user/support_chat.php" class="list-group-item list-group-item-action <?php echo $activePage === 'support_chat' ? 'active' : ''; ?>">
                            <i class="fas fa-headset"></i> Live Chat
                        </a>
                    </div>
                </div>
                
                <div class="sidebar-section mt-3">
                    <h5 class="sidebar-title">Account</h5>
                    <div class="list-group">
                        <a href="/loaz_industries/user/profile.php" class="list-group-item list-group-item-action <?php echo $activePage === 'profile' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .sidebar-menu {
            background: white;
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .sidebar-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--medium-brown);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0.75rem;
        }
        
        .list-group-item {
            border: none;
            background: transparent;
            padding: 0.6rem 0.75rem;
            margin-bottom: 0.25rem;
            border-radius: 12px;
            color: var(--dark-brown);
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .list-group-item i {
            width: 22px;
            margin-right: 10px;
            color: var(--gold-brown);
            font-size: 0.9rem;
        }
        
        .list-group-item:hover {
            background: rgba(192, 133, 82, 0.08);
            color: var(--gold-brown);
            transform: translateX(3px);
        }
        
        .list-group-item.active {
            background: rgba(192, 133, 82, 0.12);
            color: var(--gold-brown);
            font-weight: 500;
        }
        
        .list-group-item.active i {
            color: var(--gold-brown);
        }
        
        .badge {
            font-size: 0.6rem;
            padding: 0.2rem 0.5rem;
            border-radius: 30px;
        }
        
        @media (max-width: 768px) {
            .sidebar-menu {
                margin-bottom: 1.5rem;
            }
            
            .list-group-item {
                padding: 0.5rem 0.6rem;
                font-size: 0.8rem;
            }
        }
    </style>
    <?php
}
?>