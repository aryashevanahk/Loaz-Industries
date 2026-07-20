<?php
/**
 * About Page - Loaz Industries
 * Menampilkan informasi perusahaan, visi misi, layanan, dan statistik
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// ============================================
// DATA FETCHING - OPTIMASI
// ============================================

try {
    // Gunakan multiple queries untuk mendapatkan statistik
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_customers = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE status = 'done'");
    $total_services_done = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM parts");
    $total_parts = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians WHERE status = 'available' AND is_active = 1");
    $total_technicians = (int)($stmt->fetch()['total'] ?? 0);
    
} catch (PDOException $e) {
    // Fallback values jika terjadi error
    $total_customers = 0;
    $total_services_done = 0;
    $total_parts = 0;
    $total_technicians = 0;
    error_log("About page query error: " . $e->getMessage());
}

// [OPTIMASI] Pre-define stats array untuk reuse
$stats = [
    'customers' => [
        'value' => $total_customers,
        'label' => 'Pelanggan Aktif',
        'icon' => 'fa-users'
    ],
    'services' => [
        'value' => $total_services_done,
        'label' => 'Servis Selesai',
        'icon' => 'fa-tools'
    ],
    'parts' => [
        'value' => $total_parts,
        'label' => 'Part Tersedia',
        'icon' => 'fa-microchip'
    ],
    'technicians' => [
        'value' => $total_technicians,
        'label' => 'Teknisi Profesional',
        'icon' => 'fa-user-cog'
    ]
];

// [OPTIMASI] Values array untuk cards
$values = [
    [
        'icon' => 'fa-handshake',
        'title' => 'Terpercaya',
        'description' => 'Komitmen kami untuk memberikan pelayanan terbaik'
    ],
    [
        'icon' => 'fa-bolt',
        'title' => 'Cepat',
        'description' => 'Respon dan pengerjaan yang tepat waktu'
    ],
    [
        'icon' => 'fa-certificate',
        'title' => 'Berkualitas',
        'description' => 'Standar kualitas tinggi untuk setiap servis'
    ],
    [
        'icon' => 'fa-smile',
        'title' => 'Ramah',
        'description' => 'Pelayanan dengan senyuman dan keramahan'
    ]
];

// [OPTIMASI] Service features untuk reuse
$service_features = [
    'servis' => [
        'icon' => 'fa-tools',
        'title' => 'Servis Elektronik',
        'description' => 'Servis untuk berbagai jenis elektronik: smartphone, laptop, TV, kulkas, AC, dan lainnya.',
        'features' => ['Teknisi Ahli', 'Garansi 3 Bulan', 'Servis Cepat']
    ],
    'parts' => [
        'icon' => 'fa-microchip',
        'title' => 'Jual Beli Part',
        'description' => 'Part elektronik original dengan garansi resmi untuk berbagai merek ternama.',
        'features' => ['Part Original', 'Garansi 1 Tahun', 'Harga Bersaing']
    ]
];
?>

<style>
/* ============================================
   ABOUT PAGE STYLES - OPTIMASI
   ============================================ */

/* ===== HERO SECTION ===== */
.about-hero {
    background: linear-gradient(135deg, var(--cream) 0%, #FFF5E8 100%);
    padding: 4rem 0 3rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.about-hero::before,
.about-hero::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.about-hero::before {
    top: -30%;
    right: -10%;
    width: 60%;
    height: 140%;
    background: radial-gradient(circle, rgba(192, 133, 82, 0.06) 0%, transparent 70%);
}

.about-hero::after {
    bottom: -30%;
    left: -10%;
    width: 50%;
    height: 120%;
    background: radial-gradient(circle, rgba(140, 90, 60, 0.04) 0%, transparent 70%);
}

.about-hero-content { position: relative; z-index: 2; }
.about-hero-badge { margin-bottom: 1rem; }

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

/* ===== VISION & MISSION ===== */
.vision-mission-section { margin-bottom: 3rem; }

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

/* ===== SERVICES ===== */
.services-section { margin-bottom: 3rem; }

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

.service-features i { font-size: 0.6rem; }

/* ===== STATISTICS ===== */
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

/* ===== VALUES ===== */
.values-section { margin-bottom: 2rem; }

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

/* ===== SECTION HEADERS ===== */
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

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .about-hero { padding: 2.5rem 0; }
    .about-hero-title { font-size: 2rem; }
    .section-title { font-size: 1.5rem; }
    
    .vision-card,
    .mission-card,
    .service-card { padding: 1.5rem; }
    
    .stats-section { padding: 2rem 1rem; }
    .stat-number-about { font-size: 1.5rem; }
    .mission-list li { font-size: 0.85rem; }
}

@media (max-width: 576px) {
    .about-hero-title { font-size: 1.6rem; }
    .section-title { font-size: 1.3rem; }
    .service-features { justify-content: center; }
}
</style>

<!-- ============================================
     ABOUT HERO SECTION
     ============================================ -->
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
            
            <!-- ==========================================
            VISI & MISI SECTION
            ========================================== -->
            <section class="vision-mission-section">
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
            </section>
            
            <!-- ==========================================
            LAYANAN KAMI SECTION
            ========================================== -->
            <section class="services-section">
                <div class="section-header-center">
                    <span class="section-tag">Layanan</span>
                    <h2 class="section-title">Yang Kami Tawarkan</h2>
                    <p class="section-desc">Solusi lengkap untuk kebutuhan elektronik Anda</p>
                </div>
                <div class="row g-4 mt-2">
                    <?php foreach ($service_features as $service): ?>
                    <div class="col-md-6">
                        <div class="service-card">
                            <div class="service-icon">
                                <i class="fas <?php echo $service['icon']; ?>"></i>
                            </div>
                            <h4><?php echo $service['title']; ?></h4>
                            <p><?php echo $service['description']; ?></p>
                            <div class="service-features">
                                <?php foreach ($service['features'] as $feature): ?>
                                <span><i class="fas fa-check-circle"></i> <?php echo $feature; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- ==========================================
            STATISTICS SECTION
            ========================================== -->
            <section class="stats-section">
                <div class="row text-center">
                    <?php foreach ($stats as $stat): ?>
                    <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
                        <div class="stat-card-about">
                            <div class="stat-icon-about">
                                <i class="fas <?php echo $stat['icon']; ?>"></i>
                            </div>
                            <div class="stat-number-about"><?php echo number_format($stat['value']); ?></div>
                            <div class="stat-label-about"><?php echo $stat['label']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- ==========================================
            NILAI KAMI SECTION
            ========================================== -->
            <section class="values-section">
                <div class="section-header-center">
                    <span class="section-tag">Nilai Kami</span>
                    <h2 class="section-title">Prinsip yang Kami Pegang</h2>
                </div>
                <div class="row g-4">
                    <?php foreach ($values as $value): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas <?php echo $value['icon']; ?>"></i>
                            </div>
                            <h5><?php echo $value['title']; ?></h5>
                            <p><?php echo $value['description']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>