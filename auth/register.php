<?php
/**
 * Register Page - Loaz Industries
 * Pendaftaran akun baru untuk user
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// ============================================
// REDIRECT IF ALREADY LOGGED IN
// ============================================

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /loaz_industries/admin/dashboard.php');
    } elseif (isTechnician()) {
        header('Location: /loaz_industries/technician/dashboard.php');
    } else {
        header('Location: /loaz_industries/user/dashboard.php');
    }
    exit();
}

// ============================================
// CONFIGURATION
// ============================================

$security_questions = [
    'Apa nama hewan peliharaan pertama Anda?',
    'Siapa nama ibu kandung Anda?',
    'Di kota mana Anda lahir?',
    'Apa nama sekolah dasar Anda?',
    'Apa makanan favorit Anda?',
    'Apa warna favorit Anda?',
    'Siapa nama sahabat masa kecil Anda?',
    'Apa merek mobil impian Anda?'
];

$error = '';
$success = '';
$form_data = [
    'name' => '',
    'email' => '',
    'security_question' => '',
    'security_answer' => ''
];

// ============================================
// FORM HANDLING - OPTIMASI
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [OPTIMASI] Sanitasi data
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['security_question'] = $_POST['security_question'] ?? '';
    $form_data['security_answer'] = trim(strtolower($_POST['security_answer'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // [OPTIMASI] Validasi dengan array errors
    $validation_errors = [];
    
    if (empty($form_data['name'])) {
        $validation_errors[] = 'Nama lengkap harus diisi!';
    } elseif (strlen($form_data['name']) < 2) {
        $validation_errors[] = 'Nama lengkap minimal 2 karakter!';
    }
    
    if (empty($form_data['email'])) {
        $validation_errors[] = 'Email harus diisi!';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = 'Email tidak valid!';
    }
    
    if (empty($password)) {
        $validation_errors[] = 'Password harus diisi!';
    } elseif (strlen($password) < 6) {
        $validation_errors[] = 'Password minimal 6 karakter!';
    }
    
    if ($password !== $confirm_password) {
        $validation_errors[] = 'Password dan konfirmasi password tidak sama!';
    }
    
    if (empty($form_data['security_question'])) {
        $validation_errors[] = 'Pertanyaan keamanan harus dipilih!';
    }
    
    if (empty($form_data['security_answer'])) {
        $validation_errors[] = 'Jawaban keamanan harus diisi!';
    } elseif (strlen($form_data['security_answer']) < 2) {
        $validation_errors[] = 'Jawaban keamanan minimal 2 karakter!';
    }
    
    // Jika ada error, gabungkan
    if (!empty($validation_errors)) {
        $error = implode(' ', $validation_errors);
    } else {
        try {
            // [OPTIMASI] Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            
            if ($stmt->fetch()) {
                $error = 'Email sudah terdaftar! Silakan gunakan email lain atau login.';
            } else {
                // [OPTIMASI] Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $hashed_answer = password_hash($form_data['security_answer'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, security_question, security_answer) 
                    VALUES (?, ?, ?, 'user', ?, ?)
                ");
                
                if ($stmt->execute([$form_data['name'], $form_data['email'], $hashed_password, 
                                    $form_data['security_question'], $hashed_answer])) {
                    $success = 'Registrasi berhasil! Silakan login untuk melanjutkan.';
                    
                    // [OPTIMASI] Log registrasi berhasil
                    error_log("New user registered: {$form_data['email']} at " . date('Y-m-d H:i:s'));
                    
                    // Reset form data setelah sukses
                    $form_data = ['name' => '', 'email' => '', 'security_question' => '', 'security_answer' => ''];
                    
                    // Redirect after 2 seconds
                    echo '<meta http-equiv="refresh" content="2;url=login.php">';
                } else {
                    $error = 'Registrasi gagal. Silakan coba lagi.';
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Loaz Industries</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================
           REGISTER PAGE STYLES - OPTIMASI
           ============================================ */
        
        :root {
            --cream: #FFF8F0;
            --gold-brown: #C08552;
            --medium-brown: #8C5A3C;
            --dark-brown: #4B2E2B;
            --shadow-sm: 0 4px 12px rgba(75, 46, 43, 0.06);
            --shadow-md: 0 8px 24px rgba(75, 46, 43, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #FFF5E8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Background Decoration - Optimasi */
        body::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 60%;
            height: 140%;
            background: radial-gradient(circle, rgba(192, 133, 82, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        body::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 50%;
            height: 120%;
            background: radial-gradient(circle, rgba(140, 90, 60, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        /* Card Style */
        .register-card {
            background: white;
            border: none;
            border-radius: 32px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        
        .register-card .card-body {
            padding: 2.5rem;
        }
        
        /* Brand Icon */
        .brand-icon-large {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--gold-brown), var(--medium-brown));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .brand-icon-large i {
            font-size: 2rem;
            color: white;
        }
        
        /* Form Styles */
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
        
        .input-group-custom .form-control,
        .input-group-custom select {
            padding: 0.9rem 1rem 0.9rem 45px;
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 16px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
            width: 100%;
            appearance: none;
        }
        
        .input-group-custom select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23C08552' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
        }
        
        .input-group-custom .form-control:focus,
        .input-group-custom select:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        /* Button */
        .btn-register {
            background: var(--gold-brown);
            color: white;
            border: none;
            padding: 0.9rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            width: 100%;
            margin-top: 0.5rem;
        }
        
        .btn-register:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        /* Back Button */
        .btn-back {
            background: transparent;
            color: var(--medium-brown);
            border: 1.5px solid rgba(192, 133, 82, 0.3);
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: rgba(192, 133, 82, 0.1);
            border-color: var(--gold-brown);
            color: var(--gold-brown);
            transform: translateY(-2px);
        }
        
        /* Alerts */
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
        
        /* Links */
        .login-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .login-link:hover {
            color: var(--medium-brown);
            text-decoration: underline;
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(192, 133, 82, 0.2);
        }
        
        .divider span {
            padding: 0 1rem;
            color: var(--medium-brown);
            font-size: 0.75rem;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .register-card .card-body {
                padding: 1.8rem;
            }
            
            .brand-icon-large {
                width: 55px;
                height: 55px;
            }
            
            .brand-icon-large i {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="register-card">
                    <div class="card-body">
                        
                        <!-- Back Button -->
                        <div class="d-flex justify-content-end mb-3">
                            <a href="/loaz_industries/index.php" class="btn-back">
                                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                            </a>
                        </div>
                        
                        <!-- Brand -->
                        <div class="text-center mb-4">
                            <div class="brand-icon-large">
                                <i class="fas fa-microchip"></i>
                            </div>
                            <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Daftar Akun</h3>
                            <p style="color: var(--medium-brown); font-size: 0.85rem;">Bergabung dengan Loaz Industries</p>
                        </div>
                        
                        <!-- Alert Messages -->
                        <?php if ($error): ?>
                            <div class="alert-custom alert-danger-custom">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert-custom alert-success-custom">
                                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Registration Form -->
                        <form method="POST" autocomplete="on">
                            <div class="input-group-custom">
                                <i class="fas fa-user"></i>
                                <input type="text" name="name" class="form-control" 
                                       placeholder="Nama Lengkap" 
                                       value="<?php echo htmlspecialchars($form_data['name']); ?>" 
                                       required autofocus>
                            </div>
                            
                            <div class="input-group-custom">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="Email Address" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                       required>
                            </div>
                            
                            <div class="input-group-custom">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Password (min. 6 karakter)" 
                                       required minlength="6">
                            </div>
                            
                            <div class="input-group-custom">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Konfirmasi Password" 
                                       required>
                            </div>
                            
                            <!-- Security Question -->
                            <div class="input-group-custom">
                                <i class="fas fa-question-circle"></i>
                                <select name="security_question" class="form-control" required>
                                    <option value="">Pilih pertanyaan keamanan</option>
                                    <?php foreach ($security_questions as $q): ?>
                                        <option value="<?php echo htmlspecialchars($q); ?>" 
                                            <?php echo ($form_data['security_question'] == $q) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($q); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="input-group-custom">
                                <i class="fas fa-paw"></i>
                                <input type="text" name="security_answer" class="form-control" 
                                       placeholder="Jawaban (huruf kecil semua)" 
                                       value="<?php echo htmlspecialchars($form_data['security_answer']); ?>" 
                                       required>
                            </div>
                            
                            <button type="submit" class="btn-register">
                                <i class="fas fa-user-plus me-2"></i> Daftar Sekarang
                            </button>
                        </form>
                        
                        <div class="divider">
                            <span>atau</span>
                        </div>
                        
                        <div class="text-center">
                            <p style="color: var(--medium-brown); font-size: 0.85rem; margin: 0;">
                                Sudah punya akun? 
                                <a href="login.php" class="login-link">Login di sini</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Security Note -->
                <div class="text-center mt-4">
                    <p style="color: var(--medium-brown); font-size: 0.75rem;">
                        <i class="fas fa-shield-alt me-1"></i> Data Anda aman dan terenkripsi
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>