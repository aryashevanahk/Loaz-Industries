<?php
/**
 * Career Landing Page - Loaz Industries
 * Menampilkan informasi karir dan posisi yang tersedia untuk teknisi
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// ============================================
// DATA FETCHING - OPTIMASI
// ============================================

try {
    // [OPTIMASI] Gunakan prepared statements untuk keamanan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM technician_applications");
    $total_applicants = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM technician_applications WHERE status = 'pending'");
    $pending_count = (int)($stmt->fetch()['total'] ?? 0);
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians");
    $total_technicians = (int)($stmt->fetch()['total'] ?? 0);
    
} catch (PDOException $e) {
    // Fallback jika error
    $total_applicants = 0;
    $pending_count = 0;
    $total_technicians = 0;
    error_log("Career page query error: " . $e->getMessage());
}

// [OPTIMASI] Pre-define job positions untuk loop
$job_positions = [
    [
        'icon' => 'fa-laptop-code',
        'title' => 'Teknisi Laptop & PC',
        'description' => 'Spesialis dalam perbaikan laptop, PC, dan komputer',
        'badges' => ['Full Time', 'On-site']
    ],
    [
        'icon' => 'fa-mobile-alt',
        'title' => 'Teknisi Smartphone',
        'description' => 'Ahli perbaikan iPhone, Android, dan tablet',
        'badges' => ['Full Time', 'On-site']
    ],
    [
        'icon' => 'fa-tv',
        'title' => 'Teknisi TV & Audio',
        'description' => 'Spesialis TV, LED, LCD, dan sistem audio',
        'badges' => ['Full Time', 'On-site']
    ],
    [
        'icon' => 'fa-snowflake',
        'title' => 'Teknisi AC & Kulkas',
        'description' => 'Ahli perbaikan AC, kulkas, dan freezer',
        'badges' => ['Full Time', 'On-site']
    ],
    [
        'icon' => 'fa-tshirt',
        'title' => 'Teknisi Mesin Cuci',
        'description' => 'Spesialis mesin cuci front load & top load',
        'badges' => ['Full Time', 'On-site']
    ],
    [
        'icon' => 'fa-tools',
        'title' => 'All Round Teknisi',
        'description' => 'Menguasai semua jenis perbaikan elektronik',
        'badges' => ['Full Time', 'On-site']
    ]
];

// [OPTIMASI] Benefits data
$benefits = [
    [
        'icon' => 'fa-chart-line',
        'title' => 'Pengembangan Karir',
        'description' => 'Program pelatihan dan sertifikasi untuk meningkatkan skill'
    ],
    [
        'icon' => 'fa-money-bill-wave',
        'title' => 'Gaji Kompetitif',
        'description' => 'Gaji pokok + bonus berdasarkan performa'
    ],
    [
        'icon' => 'fa-shield-alt',
        'title' => 'Asuransi Kesehatan',
        'description' => 'Jaminan kesehatan untuk karyawan tetap'
    ],
    [
        'icon' => 'fa-calendar-alt',
        'title' => 'Work-Life Balance',
        'description' => 'Jam kerja fleksibel dan cuti tahunan'
    ]
];

// [OPTIMASI] Stats untuk hero
$stats = [
    [
        'icon' => 'fa-users',
        'value' => $total_applicants,
        'label' => 'Total Pelamar'
    ],
    [
        'icon' => 'fa-clock',
        'value' => $pending_count,
        'label' => 'Diproses'
    ],
    [
        'icon' => 'fa-check-circle',
        'value' => $total_technicians,
        'label' => 'Teknisi Aktif'
    ]
];

include '../includes/header.php';
?>

<!-- ============================================
     HERO SECTION
     ============================================ -->
<div class="container py-5">
    <div class="row align-items-center mb-5">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <h1 class="display-4 fw-bold mb-3" style="color: var(--dark-brown);">
                Bergabung Menjadi<br>
                <span style="color: var(--gold-brown);">Teknisi Loaz Industries</span>
            </h1>
            <p class="lead mb-4" style="color: var(--medium-brown);">
                Jadi bagian dari tim teknisi profesional kami dan kembangkan karir Anda di bidang elektronik.
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <a href="apply.php" class="btn btn-gold rounded-4 px-4 py-2">
                    <i class="fas fa-paper-plane me-2"></i> Lamar Sekarang
                </a>
                <a href="#jobs" class="btn btn-outline-gold rounded-4 px-4 py-2">
                    <i class="fas fa-briefcase me-2"></i> Lihat Posisi
                </a>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="hero-stats d-flex gap-3">
                <?php foreach ($stats as $stat): ?>
                <div class="stat-card text-center p-4 rounded-4 flex-fill">
                    <i class="fas <?php echo $stat['icon']; ?> fa-3x mb-3" style="color: var(--gold-brown);"></i>
                    <h3 class="mb-1"><?php echo number_format($stat['value']); ?></h3>
                    <p class="text-muted mb-0"><?php echo $stat['label']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ============================================
    JOB POSITIONS SECTION
    ============================================ -->
    <div id="jobs" class="mt-5 pt-4">
        <div class="text-center mb-5">
            <h2 style="color: var(--dark-brown);">Posisi yang Tersedia</h2>
            <p class="text-muted">Pilih spesialisasi yang sesuai dengan keahlian Anda</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($job_positions as $job): ?>
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas <?php echo $job['icon']; ?> fa-2x"></i>
                    </div>
                    <h4><?php echo $job['title']; ?></h4>
                    <p class="text-muted small"><?php echo $job['description']; ?></p>
                    <div class="job-details mt-3">
                        <?php foreach ($job['badges'] as $badge): ?>
                        <span class="badge bg-light text-dark me-2"><?php echo $badge; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================
    WHY JOIN US SECTION
    ============================================ -->
    <div class="mt-5 pt-4">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="../assets/images/default/img1.png" alt="Team" class="img-fluid rounded-4" style="width: 100%;" loading="lazy">
            </div>
            <div class="col-lg-6">
                <h2 style="color: var(--dark-brown);">Mengapa Bergabung dengan Kami?</h2>
                <div class="benefit-list mt-4">
                    <?php foreach ($benefits as $benefit): ?>
                    <div class="benefit-item d-flex mb-3">
                        <i class="fas <?php echo $benefit['icon']; ?> fa-2x me-3" style="color: var(--gold-brown);"></i>
                        <div>
                            <h5><?php echo $benefit['title']; ?></h5>
                            <p class="text-muted"><?php echo $benefit['description']; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
    CTA SECTION
    ============================================ -->
    <div class="cta-section text-center p-5 rounded-4 mt-5" style="background: linear-gradient(135deg, rgba(192, 133, 82, 0.1), rgba(140, 90, 60, 0.05));">
        <h3 style="color: var(--dark-brown);">Siap Memulai Karir Anda?</h3>
        <p class="text-muted mb-4">Kirim lamaran Anda sekarang dan bergabunglah dengan tim profesional kami</p>
        <a href="apply.php" class="btn btn-gold rounded-4 px-5 py-2">
            <i class="fas fa-paper-plane me-2"></i> Lamar Sekarang
        </a>
    </div>
</div>

<style>
/* ============================================
   CAREER PAGE STYLES - OPTIMASI
   ============================================ */

/* ===== STAT CARDS ===== */
.stat-card {
    background: white;
    border: 1px solid rgba(192, 133, 82, 0.15);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(75, 46, 43, 0.08);
}

/* ===== JOB CARDS ===== */
.job-card {
    background: white;
    border: 1px solid rgba(192, 133, 82, 0.15);
    transition: all 0.3s ease;
    cursor: pointer;
}

.job-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 16px rgba(75, 46, 43, 0.08);
    border-color: var(--gold-brown);
}

.job-icon {
    width: 60px;
    height: 60px;
    background: rgba(192, 133, 82, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold-brown);
}

/* ===== BENEFIT ITEMS ===== */
.benefit-item {
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 12px;
}

.benefit-item:hover {
    background: rgba(192, 133, 82, 0.05);
    transform: translateX(5px);
}

/* ===== BUTTONS ===== */
.btn-gold {
    background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
    color: white;
    border: none;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-gold:hover {
    background: linear-gradient(135deg, #d4945a, #9c6a46);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(192, 133, 82, 0.3);
    color: white;
}

.btn-outline-gold {
    background: transparent;
    color: var(--gold-brown);
    border: 2px solid var(--gold-brown);
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-outline-gold:hover {
    background: var(--gold-brown);
    color: white;
    transform: translateY(-2px);
}

/* ===== BADGES ===== */
.badge.bg-light {
    background: rgba(192, 133, 82, 0.1) !important;
    color: var(--medium-brown) !important;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .hero-stats {
        flex-direction: column;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}

/* ===== CTA SECTION ===== */
.cta-section {
    border: 1px solid rgba(192, 133, 82, 0.1);
}
</style>

<?php include '../includes/footer.php'; ?>