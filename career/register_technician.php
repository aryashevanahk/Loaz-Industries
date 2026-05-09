<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Specialties list
$specialties = [
    'Laptop & PC' => 'Laptop & PC',
    'Smartphone' => 'Smartphone (iPhone, Android)',
    'TV & Audio' => 'TV, LED, LCD, Audio System',
    'AC & Kulkas' => 'AC, Kulkas, Freezer',
    'Mesin Cuci' => 'Mesin Cuci Front Load & Top Load',
    'Alat Rumah Tangga' => 'Microwave, Rice Cooker, Blender',
    'Kamera' => 'DSLR, Mirrorless, Camcorder',
    'Game Console' => 'PlayStation, Xbox, Nintendo',
    'All Round' => 'Semua jenis elektronik'
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
            // Handle certificate upload
            if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $filename = $_FILES['certificate']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $certificate = time() . '_cert_' . preg_replace('/[^a-zA-Z0-9]/', '_', $filename);
                    move_uploaded_file($_FILES['certificate']['tmp_name'], '../uploads/certificates/' . $certificate);
                }
            }
            
            // Handle CV upload
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $allowed = ['pdf', 'doc', 'docx'];
                $filename = $_FILES['cv_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $cv_file = time() . '_cv_' . preg_replace('/[^a-zA-Z0-9]/', '_', $filename);
                    move_uploaded_file($_FILES['cv_file']['tmp_name'], '../uploads/cvs/' . $cv_file);
                }
            }
            
            // Insert application
            $stmt = $pdo->prepare("
                INSERT INTO technician_applications 
                (name, email, phone, specialty, experience_years, portfolio, certificate, cv_file, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            if ($stmt->execute([$name, $email, $phone, $specialty, $experience_years, $portfolio, $certificate, $cv_file])) {
                $success = '
                    <div class="text-center">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--gold-brown); margin-bottom: 1rem;"></i>
                        <h4>Pendaftaran Berhasil!</h4>
                        <p>Terima kasih telah mendaftar sebagai teknisi. Lamaran Anda akan kami proses dalam 1x24 jam.<br>
                        Status pendaftaran dapat dilihat di halaman <a href="application_status.php?email=' . urlencode($email) . '" style="color: var(--gold-brown);">Cek Status</a>.</p>
                    </div>
                ';
            } else {
                $error = 'Pendaftaran gagal. Silakan coba lagi.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karir Teknisi - Loaz Industries</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 4px 12px rgba(75, 46, 43, 0.06);
            --shadow-md: 0 8px 24px rgba(75, 46, 43, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            min-height: 100vh;
        }
        
        /* Header */
        .job-header {
            background: linear-gradient(135deg, var(--dark-brown) 0%, #5C3A36 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        
        .job-header h1 { font-size: 2rem; font-weight: 600; margin-bottom: 1rem; }
        .job-header p { opacity: 0.9; max-width: 600px; margin: 0 auto; }
        
        /* Card */
        .job-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .job-card .card-body {
            padding: 2rem;
        }
        
        /* Form */
        .form-label {
            font-weight: 500;
            color: var(--dark-brown);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .input-group-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }
        
        .input-group-custom i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold-brown);
            font-size: 1rem;
            z-index: 2;
        }
        
        .input-group-custom input,
        .input-group-custom select,
        .input-group-custom textarea {
            padding: 0.9rem 1rem 0.9rem 45px;
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 16px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
            width: 100%;
        }
        
        .input-group-custom textarea {
            padding-top: 0.9rem;
        }
        
        .input-group-custom input:focus,
        .input-group-custom select:focus,
        .input-group-custom textarea:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        .btn-submit {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.9rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            width: 100%;
        }
        
        .btn-submit:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: transparent;
            color: var(--medium-brown);
            border: 1.5px solid rgba(192, 133, 82, 0.3);
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back:hover {
            background: rgba(192, 133, 82, 0.1);
            border-color: var(--gold-brown);
            color: var(--gold-brown);
        }
        
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
        
        .benefit-list {
            list-style: none;
            padding: 0;
        }
        
        .benefit-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .benefit-list li i {
            color: var(--gold-brown);
            width: 20px;
        }
        
        @media (max-width: 768px) {
            .job-card .card-body { padding: 1.5rem; }
            .job-header { padding: 2rem 0; }
            .job-header h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <section class="job-header">
        <div class="container">
            <a href="/loaz_industries/index.php" class="btn-back mb-4" style="display: inline-flex;">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>
            <h1><i class="fas fa-briefcase me-2"></i> Bergabung sebagai Teknisi</h1>
            <p>Loaz Industries mencari teknisi profesional yang berpengalaman dan bersertifikat</p>
        </div>
    </section>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if ($success): ?>
                    <div class="job-card">
                        <div class="card-body">
                            <?php echo $success; ?>
                            <div class="text-center mt-4">
                                <a href="/loaz_industries/index.php" class="btn-submit" style="display: inline-block; width: auto; padding: 0.8rem 2rem;">
                                    <i class="fas fa-home me-2"></i> Kembali ke Beranda
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-7">
                            <div class="job-card">
                                <div class="card-body">
                                    <h3 class="mb-4" style="color: var(--dark-brown);">Form Pendaftaran Teknisi</h3>
                                    
                                    <?php if ($error): ?>
                                        <div class="alert-custom alert-danger-custom">
                                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="input-group-custom">
                                            <i class="fas fa-user"></i>
                                            <input type="text" name="name" placeholder="Nama Lengkap" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-phone"></i>
                                            <input type="tel" name="phone" placeholder="Nomor Telepon (WhatsApp)" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-microchip"></i>
                                            <select name="specialty" required>
                                                <option value="">Pilih Spesialisasi</option>
                                                <?php foreach ($specialties as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" <?php echo (isset($_POST['specialty']) && $_POST['specialty'] == $value) ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-calendar-alt"></i>
                                            <select name="experience_years">
                                                <option value="0">Pengalaman (tahun)</option>
                                                <option value="1">1 tahun</option>
                                                <option value="2">2 tahun</option>
                                                <option value="3">3 tahun</option>
                                                <option value="4">4 tahun</option>
                                                <option value="5">5+ tahun</option>
                                            </select>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-link"></i>
                                            <textarea name="portfolio" rows="3" placeholder="Portofolio / Link karya / Pengalaman kerja..."></textarea>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-certificate"></i>
                                            <input type="file" name="certificate" accept=".jpg,.jpeg,.png,.pdf" style="padding-left: 45px;">
                                            <small class="text-muted d-block mt-1">Upload sertifikat (opsional, max 2MB)</small>
                                        </div>
                                        
                                        <div class="input-group-custom">
                                            <i class="fas fa-file-alt"></i>
                                            <input type="file" name="cv_file" accept=".pdf,.doc,.docx" style="padding-left: 45px;">
                                            <small class="text-muted d-block mt-1">Upload CV (opsional, max 2MB)</small>
                                        </div>
                                        
                                        <button type="submit" class="btn-submit">
                                            <i class="fas fa-paper-plane me-2"></i> Kirim Lamaran
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="job-card">
                                <div class="card-body">
                                    <h4 class="mb-3" style="color: var(--dark-brown);"><i class="fas fa-gem me-2"></i>Benefit Bergabung</h4>
                                    <ul class="benefit-list">
                                        <li><i class="fas fa-check-circle"></i> Gaji kompetitif + bonus</li>
                                        <li><i class="fas fa-calendar-alt"></i> Jadwal fleksibel</li>
                                        <li><i class="fas fa-chart-line"></i> Peluang karir</li>
                                        <li><i class="fas fa-certificate"></i> Sertifikasi berkala</li>
                                        <li><i class="fas fa-tools"></i> Peralatan servis disediakan</li>
                                        <li><i class="fas fa-truck"></i> Transportasi/akomodasi</li>
                                    </ul>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-3" style="color: var(--dark-brown);">Kualifikasi:</h5>
                                    <ul class="benefit-list">
                                        <li><i class="fas fa-graduation-cap"></i> Minimal SMA/SMK</li>
                                        <li><i class="fas fa-microchip"></i> Menguasai bidang elektronik</li>
                                        <li><i class="fas fa-clock"></i> Siap bekerja shift</li>
                                        <li><i class="fas fa-map-marker-alt"></i> Domisili Jabodetabek</li>
                                    </ul>
                                    
                                    <div class="mt-4 p-3" style="background: rgba(192, 133, 82, 0.08); border-radius: 16px;">
                                        <small style="color: var(--medium-brown);">
                                            <i class="fas fa-info-circle me-1"></i> 
                                            Pendaftaran gratis. Lamaran akan diproses dalam 1x24 jam.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>