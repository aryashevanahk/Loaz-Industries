<?php
// Career landing page - open for public, no login required
require_once '../config/database.php';
require_once '../includes/functions.php';

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM technician_applications");
$total_applicants = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM technician_applications WHERE status = 'pending'");
$pending_count = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM technicians");
$total_technicians = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<title>Karir - Loaz Industries</title>
<div class="container py-5">
    <!-- Hero Section -->
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
                <div class="stat-card text-center p-4 rounded-4 flex-fill">
                    <i class="fas fa-users fa-3x mb-3" style="color: var(--gold-brown);"></i>
                    <h3 class="mb-1"><?php echo $total_applicants; ?></h3>
                    <p class="text-muted mb-0">Total Pelamar</p>
                </div>
                <div class="stat-card text-center p-4 rounded-4 flex-fill">
                    <i class="fas fa-clock fa-3x mb-3" style="color: var(--gold-brown);"></i>
                    <h3 class="mb-1"><?php echo $pending_count; ?></h3>
                    <p class="text-muted mb-0">Diproses</p>
                </div>
                <div class="stat-card text-center p-4 rounded-4 flex-fill">
                    <i class="fas fa-check-circle fa-3x mb-3" style="color: var(--gold-brown);"></i>
                    <h3 class="mb-1"><?php echo $total_technicians; ?></h3>
                    <p class="text-muted mb-0">Teknisi Aktif</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Positions -->
    <div id="jobs" class="mt-5 pt-4">
        <div class="text-center mb-5">
            <h2 style="color: var(--dark-brown);">Posisi yang Tersedia</h2>
            <p class="text-muted">Pilih spesialisasi yang sesuai dengan keahlian Anda</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-laptop-code fa-2x"></i>
                    </div>
                    <h4>Teknisi Laptop & PC</h4>
                    <p class="text-muted small">Spesialis dalam perbaikan laptop, PC, dan komputer</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-mobile-alt fa-2x"></i>
                    </div>
                    <h4>Teknisi Smartphone</h4>
                    <p class="text-muted small">Ahli perbaikan iPhone, Android, dan tablet</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-tv fa-2x"></i>
                    </div>
                    <h4>Teknisi TV & Audio</h4>
                    <p class="text-muted small">Spesialis TV, LED, LCD, dan sistem audio</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-snowflake fa-2x"></i>
                    </div>
                    <h4>Teknisi AC & Kulkas</h4>
                    <p class="text-muted small">Ahli perbaikan AC, kulkas, dan freezer</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-tshirt fa-2x"></i>
                    </div>
                    <h4>Teknisi Mesin Cuci</h4>
                    <p class="text-muted small">Spesialis mesin cuci front load & top load</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4">
                <div class="job-card p-4 rounded-4 h-100">
                    <div class="job-icon mb-3">
                        <i class="fas fa-tools fa-2x"></i>
                    </div>
                    <h4>All Round Teknisi</h4>
                    <p class="text-muted small">Menguasai semua jenis perbaikan elektronik</p>
                    <div class="job-details mt-3">
                        <span class="badge bg-light text-dark me-2">Full Time</span>
                        <span class="badge bg-light text-dark">On-site</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Why Join Us -->
    <div class="mt-5 pt-4">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="../assets/images/default/img1.png" alt="Team" class="img-fluid rounded-4" style="width: 100%;">
            </div>
            <div class="col-lg-6">
                <h2 style="color: var(--dark-brown);">Mengapa Bergabung dengan Kami?</h2>
                <div class="benefit-list mt-4">
                    <div class="benefit-item d-flex mb-3">
                        <i class="fas fa-chart-line fa-2x me-3" style="color: var(--gold-brown);"></i>
                        <div>
                            <h5>Pengembangan Karir</h5>
                            <p class="text-muted">Program pelatihan dan sertifikasi untuk meningkatkan skill</p>
                        </div>
                    </div>
                    <div class="benefit-item d-flex mb-3">
                        <i class="fas fa-money-bill-wave fa-2x me-3" style="color: var(--gold-brown);"></i>
                        <div>
                            <h5>Gaji Kompetitif</h5>
                            <p class="text-muted">Gaji pokok + bonus berdasarkan performa</p>
                        </div>
                    </div>
                    <div class="benefit-item d-flex mb-3">
                        <i class="fas fa-shield-alt fa-2x me-3" style="color: var(--gold-brown);"></i>
                        <div>
                            <h5>Asuransi Kesehatan</h5>
                            <p class="text-muted">Jaminan kesehatan untuk karyawan tetap</p>
                        </div>
                    </div>
                    <div class="benefit-item d-flex mb-3">
                        <i class="fas fa-calendar-alt fa-2x me-3" style="color: var(--gold-brown);"></i>
                        <div>
                            <h5>Work-Life Balance</h5>
                            <p class="text-muted">Jam kerja fleksibel dan cuti tahunan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="cta-section text-center p-5 rounded-4 mt-5" style="background: linear-gradient(135deg, rgba(192, 133, 82, 0.1), rgba(140, 90, 60, 0.05));">
        <h3 style="color: var(--dark-brown);">Siap Memulai Karir Anda?</h3>
        <p class="text-muted mb-4">Kirim lamaran Anda sekarang dan bergabunglah dengan tim profesional kami</p>
        <a href="apply.php" class="btn btn-gold rounded-4 px-5 py-2">
            <i class="fas fa-paper-plane me-2"></i> Lamar Sekarang
        </a>
    </div>
</div>

<style>
    .stat-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 16px rgba(75, 46, 43, 0.08);
    }
    
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
    
    .benefit-item {
        transition: all 0.3s ease;
        padding: 0.5rem;
        border-radius: 12px;
    }
    
    .benefit-item:hover {
        background: rgba(192, 133, 82, 0.05);
        transform: translateX(5px);
    }
    
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
    
    .badge.bg-light {
        background: rgba(192, 133, 82, 0.1) !important;
        color: var(--medium-brown) !important;
    }
    
    @media (max-width: 768px) {
        .hero-stats {
            flex-direction: column;
        }
        
        .display-4 {
            font-size: 2rem;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>