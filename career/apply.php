<?php
// Hapus session_start() karena sudah di header
// Career page - open for public, no login required

require_once '../config/database.php';
require_once '../includes/functions.php';

// Jangan panggil redirectIfNotLoggedIn() karena halaman ini untuk publik

$error = '';
$success = '';

// Specialties list
$specialties = [
    'Laptop & PC' => '💻 Laptop & PC',
    'Smartphone' => '📱 Smartphone (iPhone, Android)',
    'TV & Audio' => '📺 TV, LED, LCD, Audio System',
    'AC & Kulkas' => '❄️ AC, Kulkas, Freezer',
    'Mesin Cuci' => '🧺 Mesin Cuci Front Load & Top Load',
    'Alat Rumah Tangga' => '🏠 Microwave, Rice Cooker, Blender',
    'Kamera' => '📷 DSLR, Mirrorless, Camcorder',
    'Game Console' => '🎮 PlayStation, Xbox, Nintendo',
    'All Round' => '🔧 Semua jenis elektronik'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialty = $_POST['specialty'];
    $experience_years = (int)$_POST['experience_years'];
    $portfolio = trim($_POST['portfolio']);
    $certificate = '';
    $cv_file = '';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($specialty)) {
        $error = 'Semua field wajib harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } elseif (!preg_match('/^[0-9]{10,13}$/', $phone)) {
        $error = 'Nomor telepon tidak valid! (min 10 digit)';
    } else {
        // Check if email already applied
        $stmt = $pdo->prepare("SELECT id FROM technician_applications WHERE email = ? AND status != 'rejected'");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar dalam pendaftaran. Silakan gunakan email lain.';
        } else {
            // Create upload directories if not exists
            $upload_cert_dir = '../uploads/certificates/';
            $upload_cv_dir = '../uploads/cvs/';
            
            if (!file_exists($upload_cert_dir)) {
                mkdir($upload_cert_dir, 0777, true);
            }
            if (!file_exists($upload_cv_dir)) {
                mkdir($upload_cv_dir, 0777, true);
            }
            
            // Handle certificate upload
            if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $filename = $_FILES['certificate']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $max_size = 2 * 1024 * 1024;
                
                if ($_FILES['certificate']['size'] <= $max_size && in_array($ext, $allowed)) {
                    $certificate = time() . '_cert_' . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($filename, PATHINFO_FILENAME)) . '.' . $ext;
                    move_uploaded_file($_FILES['certificate']['tmp_name'], $upload_cert_dir . $certificate);
                }
            }
            
            // Handle CV upload
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $allowed = ['pdf', 'doc', 'docx'];
                $filename = $_FILES['cv_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $max_size = 2 * 1024 * 1024;
                
                if ($_FILES['cv_file']['size'] <= $max_size && in_array($ext, $allowed)) {
                    $cv_file = time() . '_cv_' . preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($filename, PATHINFO_FILENAME)) . '.' . $ext;
                    move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_cv_dir . $cv_file);
                }
            }
            
            // Insert application
            $stmt = $pdo->prepare("
                INSERT INTO technician_applications 
                (name, email, phone, specialty, experience_years, portfolio, certificate, cv_file, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            if ($stmt->execute([$name, $email, $phone, $specialty, $experience_years, $portfolio, $certificate, $cv_file])) {
                // Redirect ke thank-you.php dengan email
                header('Location: thank_you.php?email=' . urlencode($email));
                exit();
            } else {
                $error = 'Pendaftaran gagal. Silakan coba lagi.';
            }
        }
    }
}

// Gunakan header khusus untuk halaman karir (tidak ada redirect login)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karir - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS Variables - Konsisten dengan sistem utama */
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
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--dark-brown);
        }
        
        /* Career Navigation - Styling konsisten dengan header utama */
        .career-nav {
            background: white;
            padding: 1rem 0;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid rgba(192, 133, 82, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .career-nav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .nav-brand:hover {
            transform: translateY(-2px);
        }
        
        .nav-brand-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .nav-brand-icon i {
            font-size: 1.3rem;
            color: white;
        }
        
        .nav-brand-text {
            font-weight: 700;
            color: var(--dark-brown);
            font-size: 1.1rem;
            line-height: 1.2;
        }
        
        .nav-brand-text small {
            font-weight: 400;
            font-size: 0.7rem;
            color: var(--gold-brown);
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--medium-brown);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 0;
            position: relative;
        }
        
        .nav-links a:hover {
            color: var(--gold-brown);
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold-brown);
            transition: var(--transition);
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        /* Main Content Container */
        .career-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        
        /* Title Icon */
        .title-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(192, 133, 82, 0.1), rgba(140, 90, 60, 0.05));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            transition: var(--transition);
        }
        
        .title-icon:hover {
            transform: scale(1.05);
            background: linear-gradient(135deg, rgba(192, 133, 82, 0.15), rgba(140, 90, 60, 0.08));
        }
        
        /* Typography */
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-brown);
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: var(--medium-brown);
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid rgba(192, 133, 82, 0.1);
        }
        
        .form-card:hover {
            box-shadow: 0 8px 32px rgba(75, 46, 43, 0.12);
            transform: translateY(-4px);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #ffffff, #fef9f4);
            padding: 1.5rem 2rem;
            border-bottom: 2px solid rgba(192, 133, 82, 0.1);
        }
        
        .card-header-custom h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-brown);
            margin: 0;
        }
        
        .card-header-custom p {
            color: var(--medium-brown);
            font-size: 0.85rem;
            margin: 0.5rem 0 0;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-brown);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-label i {
            color: var(--gold-brown);
            margin-right: 8px;
        }
        
        .form-control, .form-select {
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Alert Styles */
        .alert-custom {
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            font-size: 0.9rem;
        }
        
        .alert-danger-custom {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .alert-info-custom {
            background: linear-gradient(135deg, #e3f2fd, #bbdef5);
            color: #1565c0;
            border-left: 4px solid #1565c0;
        }
        
        /* Button Styles */
        .btn-submit {
            background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
            color: white;
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(192, 133, 82, 0.4);
            background: linear-gradient(135deg, #d4945a, #9c6a46);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        /* Breadcrumb */
        .breadcrumb-custom {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
        }
        
        .breadcrumb-custom .breadcrumb-item {
            color: var(--medium-brown);
            font-size: 0.85rem;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: var(--gold-brown);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb-custom .breadcrumb-item a:hover {
            color: var(--medium-brown);
        }
        
        .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--medium-brown);
            font-size: 1.1rem;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--dark-brown);
            font-weight: 500;
        }
        
        /* Info List */
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
        }
        
        .info-list li {
            padding: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-list li i {
            color: var(--gold-brown);
            font-size: 0.8rem;
        }
        
        /* Footer */
        .footer-career {
            text-align: center;
            padding: 2rem;
            color: var(--medium-brown);
            font-size: 0.8rem;
            border-top: 1px solid rgba(192, 133, 82, 0.1);
            margin-top: 3rem;
            background: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .career-nav .container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .career-container {
                padding: 1.5rem 1rem;
            }
            
            h1 {
                font-size: 1.75rem;
            }
            
            .card-header-custom {
                padding: 1.25rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Required field indicator */
        .text-danger {
            color: #dc3545 !important;
            font-size: 0.8rem;
        }
        
        /* File input styling */
        input[type="file"] {
            padding: 0.5rem;
            background: #fafafa;
        }
        
        input[type="file"]::file-selector-button {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        input[type="file"]::file-selector-button:hover {
            background: var(--medium-brown);
        }
        
        /* Small text styling */
        .text-muted {
            color: var(--medium-brown) !important;
            opacity: 0.7;
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }
        
        hr {
            border-color: rgba(192, 133, 82, 0.1);
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
    <!-- Simple Navigation for Career Pages (tanpa mengubah header utama) -->
    <header class="career-nav">
        <div class="container">
            <a href="index.php" class="nav-brand">
                <div class="nav-brand-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <div class="nav-brand-text">
                    Loaz Industries<br>
                    <small>Karir & Rekrutmen</small>
                </div>
            </a>
            <div class="nav-links">
                <a href="karir.php"><i class="fas fa-home me-1"></i> Beranda</a>
                <a href="apply.php"><i class="fas fa-paper-plane me-1"></i> Lamar</a>
                <a href="check.php"><i class="fas fa-search me-1"></i> Cek Status</a>
                <a href="/loaz_industries/index.php"><i class="fas fa-globe me-1"></i> Website Utama</a>
            </div>
        </div>
    </header>

    <div class="career-container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
        
                <!-- Page Title -->
                <div class="text-center mb-4 animate-fade-in">
                    <div class="title-icon">
                        <i class="fas fa-user-plus fa-3x" style="color: var(--gold-brown);"></i>
                    </div>
                    <h1>Form Lamaran Teknisi</h1>
                    <p class="subtitle">Isi form di bawah untuk bergabung menjadi teknisi Loaz Industries</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($error): ?>
                    <div class="alert-custom alert-danger-custom animate-fade-in" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                            <div>
                                <strong>Terjadi Kesalahan!</strong><br>
                                <?php echo $error; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Application Form -->
                <div class="form-card animate-fade-in">
                    <div class="card-header-custom">
                        <h5>
                            <i class="fas fa-file-alt me-2" style="color: var(--gold-brown);"></i>
                            Form Pendaftaran Teknisi
                        </h5>
                        <p>Isi data dengan lengkap untuk mempercepat proses seleksi</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-user"></i>
                                            Nama Lengkap <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="name" class="form-control" placeholder="Contoh: John Doe" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-envelope"></i>
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-phone"></i>
                                            Nomor Telepon <span class="text-danger">*</span>
                                        </label>
                                        <input type="tel" name="phone" class="form-control" placeholder="081234567890" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-microchip"></i>
                                            Spesialisasi <span class="text-danger">*</span>
                                        </label>
                                        <select name="specialty" class="form-select" required>
                                            <option value="">Pilih Spesialisasi</option>
                                            <?php foreach ($specialties as $value => $label): ?>
                                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-calendar-alt"></i>
                                            Pengalaman (tahun)
                                        </label>
                                        <select name="experience_years" class="form-select">
                                            <option value="0">Fresh Graduate / Kurang dari 1 tahun</option>
                                            <option value="1">1 tahun</option>
                                            <option value="2">2 tahun</option>
                                            <option value="3">3 tahun</option>
                                            <option value="4">4 tahun</option>
                                            <option value="5">5+ tahun</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-link"></i>
                                            Portofolio / Link Karya (Opsional)
                                        </label>
                                        <textarea name="portfolio" class="form-control" rows="3" placeholder="Masukkan link portofolio, GitHub, atau pengalaman kerja..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-certificate"></i>
                                            Upload Sertifikat (Opsional)
                                        </label>
                                        <input type="file" name="certificate" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted">Format: JPG, PNG, PDF. Max 2MB</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-file-alt"></i>
                                            Upload CV / Resume (Opsional)
                                        </label>
                                        <input type="file" name="cv_file" class="form-control" accept=".pdf,.doc,.docx">
                                        <small class="text-muted">Format: PDF, DOC, DOCX. Max 2MB</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="alert-custom alert-info-custom">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Informasi Penting:</strong>
                                <ul class="info-list">
                                    <li><i class="fas fa-check-circle"></i> Data Anda akan kami jaga kerahasiaannya</li>
                                    <li><i class="fas fa-clock"></i> Proses seleksi membutuhkan waktu 1-3 hari kerja</li>
                                    <li><i class="fas fa-search"></i> Status lamaran dapat dicek melalui halaman <a href="check.php" style="color: var(--gold-brown); font-weight: 500;">Cek Status Lamaran</a></li>
                                </ul>
                            </div>
                            
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane me-2"></i> Kirim Lamaran
                                <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer-career">
        <p>&copy; 2024 Loaz Industries. All rights reserved.</p>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Simple animation on scroll -->
    <script>
        // Add animation class to elements when they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Observe form card
        const formCard = document.querySelector('.form-card');
        if (formCard) {
            formCard.style.opacity = '0';
            formCard.style.transform = 'translateY(30px)';
            formCard.style.transition = 'all 0.6s ease-out';
            observer.observe(formCard);
        }
    </script>
</body>
</html>