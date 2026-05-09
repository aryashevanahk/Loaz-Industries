<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Validation
    if (empty($name)) {
        $error = 'Nama lengkap harus diisi!';
    } elseif (empty($email)) {
        $error = 'Email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } elseif (empty($subject)) {
        $error = 'Subjek harus dipilih!';
    } elseif (empty($message)) {
        $error = 'Pesan harus diisi!';
    } else {
        // Handle attachment (optional)
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if ($_FILES['attachment']['size'] > $max_size) {
                $error = 'Ukuran file terlalu besar! Maksimal 2MB.';
            } elseif (!in_array($ext, $allowed)) {
                $error = 'Format file tidak didukung!';
            } else {
                // Create directory if not exists
                if (!file_exists('uploads/attachments/')) {
                    mkdir('uploads/attachments/', 0777, true);
                }
                $attachment = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $filename);
                move_uploaded_file($_FILES['attachment']['tmp_name'], 'uploads/attachments/' . $attachment);
            }
        }
        
        // If no error, simulate successful submission
        if (empty($error)) {
            // Log the message to a file for admin reference (optional)
            $log_data = [
                'date' => date('Y-m-d H:i:s'),
                'name' => $name,
                'email' => $email,
                'subject' => $subject,
                'message' => $message,
                'attachment' => $attachment
            ];
            
            // Save to log file (optional)
            $log_file = 'uploads/contact_log_' . date('Y-m-d') . '.json';
            if (!file_exists('uploads/')) {
                mkdir('uploads/', 0777, true);
            }
            
            $logs = [];
            if (file_exists($log_file)) {
                $logs = json_decode(file_get_contents($log_file), true) ?: [];
            }
            $logs[] = $log_data;
            file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
            
            // Set success message
            $success = 'Pesan Anda telah terkirim! Admin akan membalas dalam 1x24 jam. Terima kasih telah menghubungi kami.';
            
            // Clear form data after successful submission (optional)
            // You can add JavaScript to reset form if needed
        }
    }
}

include 'includes/header.php';
?>

<!-- Contact Hero Section -->
<section class="contact-hero">
    <div class="container">
        <div class="contact-hero-content">
            <div class="contact-hero-badge">
                <span class="badge-pill">Hubungi Kami</span>
            </div>
            <h1 class="contact-hero-title">Kami Siap <span class="highlight">Membantu</span></h1>
            <p class="contact-hero-subtitle">Tim support kami siap melayani Anda 24/7</p>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Contact Form -->
                <div class="col-md-7">
                    <div class="contact-form-card">
                        <div class="contact-form-header">
                            <i class="fas fa-paper-plane me-2"></i>
                            Kirim Pesan
                        </div>
                        <div class="contact-form-body">
                            <form method="POST" enctype="multipart/form-data" id="contactForm">
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-user"></i>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : (isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''); ?>" 
                                               placeholder="Masukkan nama lengkap" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>" 
                                               placeholder="Masukkan email aktif" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Subjek <span class="text-danger">*</span></label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-tag"></i>
                                        <select name="subject" class="form-select" required>
                                            <option value="">Pilih Subjek</option>
                                            <option value="Pertanyaan Umum" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Pertanyaan Umum') ? 'selected' : ''; ?>>❓ Pertanyaan Umum</option>
                                            <option value="Servis Elektronik" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Servis Elektronik') ? 'selected' : ''; ?>>🔧 Servis Elektronik</option>
                                            <option value="Pesanan Part" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Pesanan Part') ? 'selected' : ''; ?>>📦 Pesanan Part</option>
                                            <option value="Keluhan" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Keluhan') ? 'selected' : ''; ?>>😟 Keluhan</option>
                                            <option value="Kerjasama" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Kerjasama') ? 'selected' : ''; ?>>🤝 Kerjasama</option>
                                            <option value="Lainnya" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Lainnya') ? 'selected' : ''; ?>>📝 Lainnya</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Pesan <span class="text-danger">*</span></label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-comment"></i>
                                        <textarea name="message" class="form-control" rows="5" 
                                                  placeholder="Tulis pesan Anda di sini..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Lampiran (Opsional)</label>
                                    <div class="input-group-custom">
                                        <i class="fas fa-paperclip"></i>
                                        <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                    </div>
                                    <small class="text-muted">Maksimal 2MB. Format: JPG, PNG, PDF, DOC</small>
                                </div>
                                
                                <button type="submit" class="btn btn-gold w-100 py-3 rounded-4">
                                    <i class="fas fa-paper-plane me-2"></i> Kirim Pesan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Info & Map -->
                <div class="col-md-5">
                    <!-- Contact Info Card -->
                    <div class="contact-info-card mb-4">
                        <div class="contact-info-header">
                            <i class="fas fa-address-card me-2"></i>
                            Informasi Kontak
                        </div>
                        <div class="contact-info-body">
                            <div class="contact-items">
                                <div class="contact-item">
                                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                                    <div class="contact-detail">
                                        <span class="contact-label">Telepon</span>
                                        <span class="contact-value">(021) 1234-5678</span>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                                    <div class="contact-detail">
                                        <span class="contact-label">WhatsApp</span>
                                        <span class="contact-value">0812-3456-7890</span>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                                    <div class="contact-detail">
                                        <span class="contact-label">Email</span>
                                        <span class="contact-value">support@loazindustries.com</span>
                                    </div>
                                </div>
                                <div class="contact-item">
                                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="contact-detail">
                                        <span class="contact-label">Alamat</span>
                                        <span class="contact-value">Jalan Raya Serpong No. 123, Kota Tangerang, Banten</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Hours Card -->
                    <div class="contact-info-card mb-4">
                        <div class="contact-info-header">
                            <i class="fas fa-clock me-2"></i>
                            Jam Operasional
                        </div>
                        <div class="contact-info-body">
                            <div class="hours-items">
                                <div class="hours-item">
                                    <div class="hours-day">
                                        <i class="fas fa-calendar-day"></i>
                                        <span>Senin - Sabtu</span>
                                    </div>
                                    <div class="hours-time">
                                        <i class="fas fa-clock"></i>
                                        <span>09:00 - 18:00</span>
                                    </div>
                                </div>
                                <div class="hours-item">
                                    <div class="hours-day">
                                        <i class="fas fa-calendar-week"></i>
                                        <span>Minggu</span>
                                    </div>
                                    <div class="hours-time closed">
                                        <i class="fas fa-clock"></i>
                                        <span>Tutup</span>
                                    </div>
                                </div>
                                <div class="hours-divider"></div>
                                <div class="hours-note">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Layanan darurat 24/7 melalui WhatsApp</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Live Chat Support Card -->
                    <div class="contact-info-card">
                        <div class="contact-info-header">
                            <i class="fas fa-comments me-2"></i>
                            Live Chat Support
                        </div>
                        <div class="contact-info-body text-center">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <i class="fas fa-headset fa-3x mb-3" style="color: var(--gold-brown);"></i>
                                <h5>Butuh bantuan cepat?</h5>
                                <p class="small text-muted mb-3">Chat langsung dengan customer service kami</p>
                                <a href="user/support_chat.php" class="btn btn-gold rounded-4 w-100">
                                    <i class="fas fa-comment-dots me-2"></i> Mulai Chat Sekarang
                                </a>
                            <?php else: ?>
                                <i class="fas fa-lock fa-3x mb-3" style="color: var(--medium-brown);"></i>
                                <h5>Login untuk Chat</h5>
                                <p class="small text-muted mb-3">Silakan login terlebih dahulu untuk menggunakan fitur live chat</p>
                                <a href="auth/login.php" class="btn btn-outline-gold rounded-4 w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login Sekarang
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Google Maps Section -->
            <div class="map-card mt-4">
                <div class="map-header">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Lokasi Kami
                </div>
                <div class="map-body">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d294.7123691083194!2d105.83548716364142!3d-6.3731802815895335!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sid!2sid!4v1775834286154!5m2!1sid!2sid" 
                        width="100%" 
                        height="300" 
                        style="border:0; border-radius: 16px;" 
                        allowfullscreen="" 
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                    <div class="map-note text-center mt-2">
                        <small class="text-muted">
                            <i class="fas fa-location-dot me-1" style="color: var(--gold-brown);"></i>
                            Jalan Raya Serpong No. 123, Kota Tangerang, Banten
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Contact Hero Section */
    .contact-hero {
        background: linear-gradient(135deg, var(--cream) 0%, #FFF5E8 100%);
        padding: 4rem 0 3rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .contact-hero::before {
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
    
    .contact-hero::after {
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
    
    .contact-hero-content {
        position: relative;
        z-index: 2;
    }
    
    .contact-hero-badge {
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
    
    .contact-hero-title {
        font-size: 3rem;
        font-weight: 700;
        color: var(--dark-brown);
        margin-bottom: 1rem;
        letter-spacing: -0.02em;
    }
    
    .contact-hero-title .highlight {
        color: var(--gold-brown);
        position: relative;
        display: inline-block;
    }
    
    .contact-hero-title .highlight::after {
        content: '';
        position: absolute;
        bottom: 8px;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--gold-brown);
        opacity: 0.3;
    }
    
    .contact-hero-subtitle {
        font-size: 1rem;
        color: var(--medium-brown);
        max-width: 500px;
        margin: 0 auto;
    }
    
    /* Contact Form Card */
    .contact-form-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
        height: 100%;
    }
    
    .contact-form-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .contact-form-body {
        padding: 1.5rem;
    }
    
    /* Contact Info Card */
    .contact-info-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
    }
    
    .contact-info-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .contact-info-body {
        padding: 1.5rem;
    }
    
    /* Input Group Custom */
    .input-group-custom {
        position: relative;
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
    
    .input-group-custom .form-control,
    .input-group-custom .form-select {
        padding-left: 45px;
        border: 1.5px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: var(--transition);
        background: white;
        width: 100%;
    }
    
    .input-group-custom textarea {
        padding-top: 12px;
    }
    
    .input-group-custom textarea ~ i {
        top: 20px;
        transform: none;
    }
    
    .input-group-custom .form-control:focus,
    .input-group-custom .form-select:focus {
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
        outline: none;
    }
    
    /* Contact Items */
    .contact-items {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .contact-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem;
        background: var(--cream);
        border-radius: 16px;
        transition: var(--transition);
    }
    
    .contact-item:hover {
        transform: translateX(5px);
        background: rgba(192, 133, 82, 0.05);
    }
    
    .contact-icon {
        width: 45px;
        height: 45px;
        background: rgba(192, 133, 82, 0.12);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .contact-icon i {
        font-size: 1.2rem;
        color: var(--gold-brown);
    }
    
    .contact-detail {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .contact-label {
        font-size: 0.7rem;
        color: var(--medium-brown);
        letter-spacing: 0.5px;
    }
    
    .contact-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--dark-brown);
    }
    
    /* Hours Items */
    .hours-items {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .hours-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem;
        background: var(--cream);
        border-radius: 16px;
        transition: var(--transition);
    }
    
    .hours-item:hover {
        background: rgba(192, 133, 82, 0.05);
    }
    
    .hours-day {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .hours-day i {
        color: var(--gold-brown);
        font-size: 1rem;
    }
    
    .hours-day span {
        font-weight: 500;
        color: var(--dark-brown);
    }
    
    .hours-time {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .hours-time i {
        color: var(--gold-brown);
        font-size: 0.9rem;
    }
    
    .hours-time span {
        color: var(--medium-brown);
    }
    
    .hours-time.closed span {
        color: #dc3545;
    }
    
    .hours-divider {
        height: 1px;
        background: rgba(192, 133, 82, 0.15);
        margin: 0.5rem 0;
    }
    
    .hours-note {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0.8rem;
        background: rgba(192, 133, 82, 0.08);
        border-radius: 16px;
    }
    
    .hours-note i {
        color: var(--gold-brown);
        font-size: 0.9rem;
    }
    
    .hours-note span {
        font-size: 0.75rem;
        color: var(--medium-brown);
    }
    
    /* Map Card */
    .map-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.15);
        border-radius: 20px;
        overflow: hidden;
    }
    
    .map-header {
        background: rgba(192, 133, 82, 0.05);
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: var(--dark-brown);
        border-bottom: 1px solid rgba(192, 133, 82, 0.1);
    }
    
    .map-body {
        padding: 0;
    }
    
    .map-note {
        padding: 0.8rem;
        background: var(--cream);
    }
    
    /* Buttons */
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
    
    /* Alerts */
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .contact-hero {
            padding: 2.5rem 0;
        }
        
        .contact-hero-title {
            font-size: 2rem;
        }
        
        .contact-hero-subtitle {
            font-size: 0.85rem;
        }
        
        .contact-item {
            flex-direction: column;
            text-align: center;
        }
        
        .hours-item {
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }
        
        .hours-note {
            text-align: center;
        }
    }
    
    @media (max-width: 576px) {
        .contact-hero-title {
            font-size: 1.6rem;
        }
        
        .contact-form-body,
        .contact-info-body {
            padding: 1rem;
        }
    }
</style>

<script>
    // Auto reset form after successful submission
    <?php if ($success && !$error): ?>
    setTimeout(function() {
        document.getElementById('contactForm').reset();
    }, 100);
    <?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>