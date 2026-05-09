<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// Get featured parts (limit 8)
$stmt = $pdo->query("SELECT * FROM parts WHERE stock > 0 ORDER BY id DESC LIMIT 8");
$featured_parts = $stmt->fetchAll();

// Get recent services (limit 5) - FIX: Handle NULL status
$stmt = $pdo->query("
    SELECT s.*, u.name as customer_name 
    FROM services s 
    JOIN users u ON s.user_id = u.id 
    WHERE COALESCE(s.status, 'pending') != 'done' 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$recent_services = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$total_customers = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE status = 'done'");
$total_services_done = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM parts WHERE stock > 0");
$total_parts = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians WHERE status = 'available' AND is_active = 1");
$total_technicians = $stmt->fetch()['total'] ?? 0;
?>

<style>
/* CSS Variables */
:root {
    --cream: #FFF8F0;
    --gold-brown: #C08552;
    --medium-brown: #8C5A3C;
    --dark-brown: #4B2E2B;
    --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
    --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Hero Section */
.hero {
    background: linear-gradient(135deg, var(--cream) 0%, #FFF5E8 100%);
    padding: 4rem 0 3rem;
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -10%;
    width: 60%;
    height: 140%;
    background: radial-gradient(circle, rgba(192, 133, 82, 0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.hero-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.hero-badge {
    margin-bottom: 1.5rem;
}

.badge-pill {
    display: inline-block;
    padding: 0.4rem 1rem;
    background: rgba(192, 133, 82, 0.12);
    color: var(--gold-brown);
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    color: var(--dark-brown);
    margin-bottom: 1rem;
    line-height: 1.2;
}

.hero-title .highlight {
    color: var(--gold-brown);
    position: relative;
    display: inline-block;
}

.hero-desc {
    font-size: 1rem;
    color: var(--medium-brown);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.btn-primary {
    background: var(--gold-brown);
    color: white;
    border: none;
    padding: 0.8rem 1.8rem;
    border-radius: 40px;
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-primary:hover {
    background: var(--medium-brown);
    transform: translateY(-2px);
    color: white;
}

.btn-outline {
    background: transparent;
    border: 1.5px solid var(--gold-brown);
    color: var(--gold-brown);
    padding: 0.8rem 1.8rem;
    border-radius: 40px;
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-outline:hover {
    background: var(--gold-brown);
    color: white;
}

.hero-stats {
    display: flex;
    gap: 2rem;
    padding-top: 1rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gold-brown);
}

.stat-label {
    font-size: 0.8rem;
    color: var(--medium-brown);
}

.stat-divider {
    width: 1px;
    background: rgba(192, 133, 82, 0.2);
}

.hero-visual {
    position: relative;
}

.hero-image-wrapper {
    position: relative;
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-image {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: float 3s ease-in-out infinite;
}

.hero-image i {
    font-size: 8rem;
    color: white;
}

.floating-card {
    position: absolute;
    background: white;
    padding: 0.8rem 1.2rem;
    border-radius: 50px;
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--dark-brown);
}

.floating-card i {
    color: var(--gold-brown);
    font-size: 1rem;
}

.card-1 {
    top: 10%;
    left: 0;
    animation: float 2s ease-in-out infinite;
}

.card-2 {
    top: 50%;
    right: 0;
    animation: float 2.5s ease-in-out infinite;
}

.card-3 {
    bottom: 10%;
    left: 20%;
    animation: float 1.8s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* Parts Section */
.section-parts {
    padding: 5rem 0;
    background: white;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-tag {
    display: inline-block;
    padding: 0.3rem 1rem;
    background: rgba(192, 133, 82, 0.1);
    color: var(--gold-brown);
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--dark-brown);
    margin-bottom: 0.5rem;
}

.section-desc {
    color: var(--medium-brown);
    max-width: 600px;
    margin: 0 auto;
}

.parts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.part-card {
    background: white;
    border: 1px solid rgba(192, 133, 82, 0.15);
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    transition: var(--transition);
}

.part-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.part-image {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.part-image-placeholder {
    width: 120px;
    height: 120px;
    background: rgba(192, 133, 82, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.part-image-placeholder i {
    font-size: 3rem;
    color: var(--gold-brown);
}

.part-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-brown);
    margin-bottom: 0.5rem;
}

.part-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gold-brown);
    margin-bottom: 0.5rem;
}

.part-stock {
    font-size: 0.75rem;
    color: var(--medium-brown);
    margin-bottom: 1rem;
}

.btn-buy {
    background: var(--gold-brown);
    color: white;
    border: none;
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    font-weight: 500;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-buy:hover {
    background: var(--medium-brown);
    transform: translateY(-2px);
    color: white;
}

.section-action {
    text-align: center;
    margin-top: 2rem;
}

/* Services Section */
.section-services {
    padding: 5rem 0;
    background: var(--cream);
}

.services-table-wrapper {
    background: white;
    border-radius: 20px;
    overflow-x: auto;
    box-shadow: var(--shadow-sm);
}

.services-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.services-table th {
    padding: 1rem;
    text-align: left;
    background: rgba(192, 133, 82, 0.05);
    color: var(--dark-brown);
    font-weight: 600;
    font-size: 0.85rem;
}

.services-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    color: var(--medium-brown);
    font-size: 0.85rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-pending { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
.status-accepted { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }
.status-repairing { background: rgba(192, 133, 82, 0.15); color: var(--gold-brown); }
.status-done { background: rgba(40, 167, 69, 0.15); color: #28a745; }
.status-visit { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }

/* Features Section */
.section-features {
    padding: 5rem 0;
    background: white;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 2rem;
}

.feature-card {
    text-align: center;
    padding: 2rem;
    background: white;
    border: 1px solid rgba(192, 133, 82, 0.1);
    border-radius: 20px;
    transition: var(--transition);
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: rgba(192, 133, 82, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.feature-icon i {
    font-size: 2rem;
    color: var(--gold-brown);
}

.feature-card h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-brown);
    margin-bottom: 0.5rem;
}

.feature-card p {
    font-size: 0.85rem;
    color: var(--medium-brown);
}

/* CTA Section - Minimalis dan Proporsional */
.section-cta {
    padding: 2rem 0;
}

.cta-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 20px;
    border: 1px solid rgba(192, 133, 82, 0.12);
    box-shadow: 0 2px 12px rgba(75, 46, 43, 0.04);
}

.cta-content h3 {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--dark-brown);
    margin-bottom: 0.25rem;
}

.cta-content p {
    font-size: 0.85rem;
    color: var(--medium-brown);
    margin: 0;
}

.btn-cta {
    background: var(--gold-brown);
    color: white;
    border: none;
    padding: 0.7rem 1.6rem;
    border-radius: 40px;
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 0.85rem;
}

.btn-cta:hover {
    background: var(--medium-brown);
    transform: translateY(-2px);
    color: white;
    box-shadow: 0 4px 12px rgba(192, 133, 82, 0.25);
}

/* Responsive */
@media (max-width: 768px) {
    .hero-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-buttons {
        justify-content: center;
    }
    
    .hero-stats {
        justify-content: center;
    }
    
    .hero-image {
        width: 200px;
        height: 200px;
    }
    
    .hero-image i {
        font-size: 5rem;
    }
    
    .section-title {
        font-size: 1.6rem;
    }
    
    .parts-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .cta-wrapper {
        flex-direction: column;
        text-align: center;
        padding: 1.2rem;
    }
    
    .cta-content h3 {
        font-size: 1.1rem;
    }
    
    .floating-card {
        display: none;
    }
}
</style>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="badge-pill">#SolusiElektronikTerpercaya</span>
                </div>
                <h1 class="hero-title">
                    Servis Elektronik & 
                    <span class="highlight">Part Berkualitas</span>
                </h1>
                <p class="hero-desc">
                    Layanan servis profesional dengan teknisi berpengalaman dan part elektronik original dengan garansi resmi. Cepat, tepat, dan terpercaya.
                </p>
                <div class="hero-buttons">
                    <a href="user/request_service.php" class="btn-primary">
                        <i class="fas fa-tools"></i>
                        <span>Request Servis</span>
                    </a>
                    <a href="user/order_part.php" class="btn-outline">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Beli Part</span>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number" data-target="<?php echo $total_customers; ?>">0</span>
                        <span class="stat-label">Pelanggan Aktif</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-number" data-target="<?php echo $total_services_done; ?>">0</span>
                        <span class="stat-label">Servis Selesai</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-number" data-target="<?php echo $total_technicians; ?>">0</span>
                        <span class="stat-label">Teknisi Profesional</span>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-image-wrapper">
                    <div class="hero-image">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="floating-card card-1">
                        <i class="fas fa-tools"></i>
                        <span>Servis 24/7</span>
                    </div>
                    <div class="floating-card card-2">
                        <i class="fas fa-shield-alt"></i>
                        <span>Garansi Resmi</span>
                    </div>
                    <div class="floating-card card-3">
                        <i class="fas fa-truck"></i>
                        <span>Gratis Ongkir</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Parts Section -->
<section class="section-parts">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Belanja Part</span>
            <h2 class="section-title">Part Elektronik Terbaru</h2>
            <p class="section-desc">Komponen elektronik original dengan harga terbaik dan garansi resmi</p>
        </div>
        <div class="parts-grid">
            <?php if (count($featured_parts) > 0): ?>
                <?php foreach ($featured_parts as $part): ?>
                <div class="part-card">
                    <?php if (!empty($part['image']) && file_exists('uploads/parts/' . $part['image'])): ?>
                        <img src="uploads/parts/<?php echo htmlspecialchars($part['image']); ?>" 
                             alt="<?php echo htmlspecialchars($part['name']); ?>" 
                             class="part-image">
                    <?php else: ?>
                        <div class="part-image-placeholder">
                            <i class="fas fa-microchip"></i>
                        </div>
                    <?php endif; ?>
                    <h3 class="part-name"><?php echo htmlspecialchars($part['name']); ?></h3>
                    <div class="part-price"><?php echo formatCurrency($part['price']); ?></div>
                    <div class="part-stock">
                        <i class="fas fa-boxes"></i>
                        <span>Stok: <?php echo $part['stock']; ?> unit</span>
                    </div>
                    <a href="user/order_part.php?add=<?php echo $part['id']; ?>" class="btn-buy">
                        <i class="fas fa-cart-plus"></i>
                        <span>Beli Sekarang</span>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">Belum ada part tersedia</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="section-action">
            <a href="user/order_part.php" class="btn-outline">
                <span>Lihat Semua Part</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="section-services">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Layanan Servis</span>
            <h2 class="section-title">Servis Terbaru</h2>
            <p class="section-desc">Pantau status servis elektronik Anda</p>
        </div>
        <div class="services-table-wrapper">
            <table class="services-table">
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Device</th>
                        <th>Problem</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_services) > 0): ?>
                        <?php foreach ($recent_services as $service): ?>
                        <tr>
                            <td data-label="Pelanggan"><?php echo htmlspecialchars($service['customer_name'] ?? '-'); ?></a>
                            <td data-label="Device"><strong><?php echo htmlspecialchars($service['device'] ?? '-'); ?></strong></a>
                            <td data-label="Problem"><?php echo htmlspecialchars(substr($service['problem'] ?? '', 0, 40)) . ((strlen($service['problem'] ?? '') > 40) ? '...' : ''); ?></a>
                            <td data-label="Status">
                                <?php
                                // [FIX] Handle NULL status dengan default 'pending'
                                $status_value = $service['status'] ?? 'pending';
                                $statusClass = '';
                                switch($status_value) {
                                    case 'pending': $statusClass = 'status-pending'; break;
                                    case 'visit': $statusClass = 'status-visit'; break;
                                    case 'accepted': $statusClass = 'status-accepted'; break;
                                    case 'repairing': $statusClass = 'status-repairing'; break;
                                    case 'done': $statusClass = 'status-done'; break;
                                    default: $statusClass = 'status-pending';
                                }
                                
                                // Status text mapping
                                $status_text = [
                                    'pending' => 'Menunggu',
                                    'visit' => 'Kunjungan',
                                    'accepted' => 'Diterima',
                                    'repairing' => 'Diperbaiki',
                                    'done' => 'Selesai'
                                ];
                                $display_text = $status_text[$status_value] ?? ucfirst($status_value);
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo $display_text; ?>
                                </span>
                            </a>
                            <td data-label="Tanggal"><?php echo date('d/m/Y', strtotime($service['created_at'] ?? 'now')); ?></a>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada layanan servis</a>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="section-action">
            <a href="user/request_service.php" class="btn-primary">
                <i class="fas fa-plus-circle"></i>
                <span>Request Servis Baru</span>
            </a>
        </div>
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section-features">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Keunggulan</span>
            <h2 class="section-title">Mengapa Memilih Kami?</h2>
            <p class="section-desc">Layanan terbaik untuk kepuasan Anda</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3>Teknisi Profesional</h3>
                <p>Berpengalaman dan bersertifikat di bidangnya</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <h3>Part Original</h3>
                <p>Garansi resmi 1 tahun untuk semua part</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <h3>Servis Cepat</h3>
                <p>Pengerjaan tepat waktu dengan kualitas terbaik</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Garansi Servis</h3>
                <p>Garansi 3 bulan untuk setiap layanan servis</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section - Minimalis dan Proporsional -->
<section class="section-cta">
    <div class="container">
        <div class="cta-wrapper">
            <div class="cta-content">
                <h3>Siap untuk servis elektronik Anda?</h3>
                <p>Dapatkan penanganan cepat dari teknisi profesional kami</p>
            </div>
            <div class="cta-button">
                <a href="user/request_service.php" class="btn-cta">
                    <i class="fas fa-tools"></i>
                    <span>Mulai Sekarang</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<script>
// Counter animation
document.addEventListener('DOMContentLoaded', function() {
    const animateNumber = (element, target) => {
        if (target === 0) {
            element.textContent = '0';
            return;
        }
        let current = 0;
        const increment = target / 50;
        const updateCounter = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = target.toLocaleString();
                clearInterval(updateCounter);
            } else {
                element.textContent = Math.floor(current).toLocaleString();
            }
        }, 20);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const numbers = entry.target.querySelectorAll('.stat-number');
                numbers.forEach(num => {
                    const target = parseInt(num.getAttribute('data-target')) || 0;
                    animateNumber(num, target);
                });
                observer.unobserve(entry.target);
            }
        });
    });

    const heroStats = document.querySelector('.hero-stats');
    if (heroStats) {
        observer.observe(heroStats);
    }
});
</script>

<?php include 'includes/footer.php'; ?>