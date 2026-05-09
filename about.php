<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// Get statistics for about page
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$total_customers = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE status = 'done'");
$total_services_done = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM parts");
$total_parts = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians WHERE status = 'available'");
$total_technicians = $stmt->fetch()['total'] ?? 0;
?>

<!-- About Hero Section -->
<section class="about-hero">
    <div class="container">
        <div class="about-hero-content">
            <div class="about-hero-badge">
                <span class="badge-pill">Tentang Kami</span>
            </div>
            <h1 class="about-hero-title">Loaz <span class="highlight">Industries</span></h1>
            <p class="about-hero-subtitle">Solusi terpercaya untuk servis elektronik dan part berkualitas</p>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Visi & Misi Section -->
            <div class="vision-mission-section">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="vision-card">
                            <div class="vision-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h3>Visi</h3>
                            <p>Menjadi platform terkemuka di Indonesia untuk layanan servis elektronik dan jual beli part elektronik yang terpercaya, cepat, dan berkualitas.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mission-card">
                            <div class="mission-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h3>Misi</h3>
                            <ul class="mission-list">
                                <li>Layanan servis elektronik dengan teknisi profesional dan bersertifikat</li>
                                <li>Memudahkan pelanggan mencari part elektronik original dengan harga terbaik</li>
                                <li>Membangun ekosistem digital yang menghubungkan pelanggan, teknisi, dan supplier</li>
                                <li>Memberikan pengalaman transaksi yang aman dan nyaman</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Layanan Kami Section -->
            <div class="services-section">
                <div class="section-header-center">
                    <span class="section-tag">Layanan</span>
                    <h2 class="section-title">Yang Kami Tawarkan</h2>
                    <p class="section-desc">Solusi lengkap untuk kebutuhan elektronik Anda</p>
                </div>
                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h4>Servis Elektronik</h4>
                            <p>Servis untuk berbagai jenis elektronik: smartphone, laptop, TV, kulkas, AC, dan lainnya.</p>
                            <div class="service-features">
                                <span><i class="fas fa-check-circle"></i> Teknisi Ahli</span>
                                <span><i class="fas fa-check-circle"></i> Garansi 3 Bulan</span>
                                <span><i class="fas fa-check-circle"></i> Servis Cepat</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas fa-microchip"></i>
                            </div>
                            <h4>Jual Beli Part</h4>
                            <p>Part elektronik original dengan garansi resmi untuk berbagai merek ternama.</p>
                            <div class="service-features">
                                <span><i class="fas fa-check-circle"></i> Part Original</span>
                                <span><i class="fas fa-check-circle"></i> Garansi 1 Tahun</span>
                                <span><i class="fas fa-check-circle"></i> Harga Bersaing</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Section -->
            <div class="stats-section">
                <div class="row text-center">
                    <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-card-about">
                            <div class="stat-icon-about">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number-about"><?php echo number_format($total_customers); ?></div>
                            <div class="stat-label-about">Pelanggan Aktif</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-card-about">
                            <div class="stat-icon-about">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="stat-number-about"><?php echo number_format($total_services_done); ?></div>
                            <div class="stat-label-about">Servis Selesai</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-card-about">
                            <div class="stat-icon-about">
                                <i class="fas fa-microchip"></i>
                            </div>
                            <div class="stat-number-about"><?php echo number_format($total_parts); ?></div>
                            <div class="stat-label-about">Part Tersedia</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card-about">
                            <div class="stat-icon-about">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <div class="stat-number-about"><?php echo number_format($total_technicians); ?></div>
                            <div class="stat-label-about">Teknisi Profesional</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Values Section -->
            <div class="values-section">
                <div class="section-header-center">
                    <span class="section-tag">Nilai Kami</span>
                    <h2 class="section-title">Prinsip yang Kami Pegang</h2>
                </div>
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h5>Terpercaya</h5>
                            <p>Komitmen kami untuk memberikan pelayanan terbaik</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <h5>Cepat</h5>
                            <p>Respon dan pengerjaan yang tepat waktu</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <h5>Berkualitas</h5>
                            <p>Standar kualitas tinggi untuk setiap servis</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-smile"></i>
                            </div>
                            <h5>Ramah</h5>
                            <p>Pelayanan dengan senyuman dan keramahan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* About Hero Section */
    .about-hero {
        background: linear-gradient(135deg, var(--cream) 0%, #FFF5E8 100%);
        padding: 4rem 0 3rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .about-hero::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -10%;
        width: 60%;
        height: 140%;
        background: radial-gradient(circle, rgba(192, 133, 82, 0.06) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .about-hero::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 50%;
        height: 120%;
        background: radial-gradient(circle, rgba(140, 90, 60, 0.04) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .about-hero-content {
        position: relative;
        z-index: 2;
    }
    
    .about-hero-badge {
        margin-bottom: 1rem;
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

    .about-hero-title {
        font-size: 3rem;
        font-weight: 700;
        color: var(--dark-brown);
        margin-bottom: 1rem;
        letter-spacing: -0.02em;
    }
    
    .about-hero-title .highlight {
        color: var(--gold-brown);
        position: relative;
        display: inline-block;
    }
    
    .about-hero-title .highlight::after {
        content: '';
        position: absolute;
        bottom: 8px;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--gold-brown);
        opacity: 0.3;
    }
    
    .about-hero-subtitle {
        font-size: 1rem;
        color: var(--medium-brown);
        max-width: 500px;
        margin: 0 auto;
    }
    
    /* Vision & Mission Cards */
    .vision-mission-section {
        margin-bottom: 3rem;
    }
    
    .vision-card,
    .mission-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        height: 100%;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .vision-card:hover,
    .mission-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .vision-icon,
    .mission-icon {
        width: 65px;
        height: 65px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.2rem;
    }
    
    .vision-icon i,
    .mission-icon i {
        font-size: 1.8rem;
        color: var(--gold-brown);
    }
    
    .vision-card h3,
    .mission-card h3 {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin-bottom: 1rem;
    }
    
    .vision-card p {
        color: var(--medium-brown);
        line-height: 1.6;
        margin: 0;
    }
    
    .mission-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .mission-list li {
        color: var(--medium-brown);
        margin-bottom: 0.75rem;
        padding-left: 1.2rem;
        position: relative;
        line-height: 1.5;
    }
    
    .mission-list li::before {
        content: '▹';
        position: absolute;
        left: 0;
        color: var(--gold-brown);
    }
    
    /* Services Section */
    .services-section {
        margin-bottom: 3rem;
    }
    
    .service-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        height: 100%;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .service-icon {
        width: 60px;
        height: 60px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
    
    .service-icon i {
        font-size: 1.6rem;
        color: var(--gold-brown);
    }
    
    .service-card h4 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin-bottom: 0.8rem;
    }
    
    .service-card p {
        color: var(--medium-brown);
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }
    
    .service-features {
        display: flex;
        gap: 0.8rem;
        flex-wrap: wrap;
    }
    
    .service-features span {
        font-size: 0.7rem;
        color: var(--gold-brown);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .service-features i {
        font-size: 0.6rem;
    }
    
    /* Statistics Section */
    .stats-section {
        background: linear-gradient(135deg, var(--dark-brown) 0%, #5C3A36 100%);
        border-radius: 24px;
        padding: 3rem 2rem;
        margin-bottom: 3rem;
    }
    
    .stat-card-about {
        text-align: center;
        color: white;
    }
    
    .stat-icon-about {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }
    
    .stat-icon-about i {
        font-size: 1.3rem;
        color: var(--gold-brown);
    }
    
    .stat-number-about {
        font-size: 2rem;
        font-weight: 700;
        color: var(--gold-brown);
        margin-bottom: 0.3rem;
    }
    
    .stat-label-about {
        font-size: 0.8rem;
        opacity: 0.8;
    }
    
    /* Values Section */
    .values-section {
        margin-bottom: 2rem;
    }
    
    .value-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        height: 100%;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .value-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .value-icon {
        width: 55px;
        height: 55px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }
    
    .value-icon i {
        font-size: 1.3rem;
        color: var(--gold-brown);
    }
    
    .value-card h5 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin-bottom: 0.5rem;
    }
    
    .value-card p {
        font-size: 0.75rem;
        color: var(--medium-brown);
        margin: 0;
    }
    
    /* Section Headers */
    .section-header-center {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .section-tag {
        display: inline-block;
        padding: 0.3rem 1rem;
        background: rgba(192, 133, 82, 0.1);
        color: var(--gold-brown);
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 1px;
        margin-bottom: 0.8rem;
    }
    
    .section-title {
        font-size: 2rem;
        font-weight: 600;
        color: var(--dark-brown);
        margin-bottom: 0.5rem;
    }
    
    .section-desc {
        color: var(--medium-brown);
        font-size: 0.9rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .about-hero {
            padding: 2.5rem 0;
        }
        
        .about-hero-title {
            font-size: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
        }
        
        .vision-card,
        .mission-card,
        .service-card {
            padding: 1.5rem;
        }
        
        .stats-section {
            padding: 2rem 1rem;
        }
        
        .stat-number-about {
            font-size: 1.5rem;
        }
        
        .mission-list li {
            font-size: 0.85rem;
        }
    }
    
    @media (max-width: 576px) {
        .about-hero-title {
            font-size: 1.6rem;
        }
        
        .section-title {
            font-size: 1.3rem;
        }
        
        .service-features {
            justify-content: center;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>