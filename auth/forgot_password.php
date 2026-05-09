<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Cek jika sudah login
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

// Daftar pertanyaan keamanan yang tersedia
$security_questions = [
    'Apa nama hewan peliharaan pertama Anda?',
    'Apa nama ibu kandung Anda?',
    'Apa nama kota kelahiran Anda?',
    'Apa nama sekolah dasar Anda?',
    'Apa warna favorit Anda?',
    'Apa nama pahlawan favorit Anda?',
    'Apa merek mobil pertama Anda?',
    'Apa makanan favorit Anda?'
];

$step = 1; // 1: input email, 2: verifikasi/setujawaban, 3: reset password
$error = '';
$success = '';
$email = '';
$security_question = '';
$user_id = '';
$needs_setup = false; // Flag untuk user yang belum punya security question

// Step 1: Cek email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email tidak valid!';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, security_question, security_answer FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $user_id = $user['id'];
            $step = 2;
            
            // Cek apakah user memiliki security question
            if (!empty($user['security_question']) && !empty($user['security_answer'])) {
                $needs_setup = false;
                $security_question = $user['security_question'];
            } else {
                $needs_setup = true;
                // Pilih pertanyaan default pertama
                $security_question = $security_questions[0];
            }
        } else {
            $error = "Email tidak ditemukan!";
        }
    }
}

// Step 2: Verifikasi jawaban atau setup pertanyaan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_answer'])) {
    $email = trim($_POST['email']);
    $security_answer = isset($_POST['security_answer']) ? trim(strtolower($_POST['security_answer'])) : '';
    $selected_question = isset($_POST['security_question']) ? $_POST['security_question'] : '';
    $is_setup = isset($_POST['is_setup']) ? (bool)$_POST['is_setup'] : false;
    
    $stmt = $pdo->prepare("SELECT id, name, security_question, security_answer FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $user_id = $user['id'];
        
        if ($is_setup) {
            // Mode setup: user belum punya security question, buat baru
            if (empty($selected_question)) {
                $error = "Silakan pilih pertanyaan keamanan!";
            } elseif (empty($security_answer)) {
                $error = "Silakan isi jawaban keamanan!";
            } elseif (strlen($security_answer) < 2) {
                $error = "Jawaban keamanan minimal 2 karakter!";
            } else {
                // Simpan pertanyaan dan jawaban keamanan
                $hashed_answer = password_hash($security_answer, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET security_question = ?, security_answer = ? WHERE id = ?");
                if ($stmt->execute([$selected_question, $hashed_answer, $user_id])) {
                    $step = 3;
                    $success = "Pertanyaan keamanan berhasil diatur! Silakan lanjutkan reset password.";
                } else {
                    $error = "Gagal menyimpan pertanyaan keamanan. Silakan coba lagi.";
                }
            }
        } else {
            // Mode verifikasi: user sudah punya security question
            if ($user && password_verify($security_answer, $user['security_answer'])) {
                $step = 3;
                $user_id = $user['id'];
            } else {
                $error = "Jawaban keamanan salah!";
                $step = 2;
                $security_question = $user['security_question'];
                $needs_setup = false;
            }
        }
    } else {
        $error = "Email tidak ditemukan!";
        $step = 1;
    }
}

// Step 3: Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = 'Password harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password dan reset flag is_temp_password
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_temp_password = 0, temp_password = NULL WHERE id = ?");
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = "Password berhasil direset! Silakan login dengan password baru Anda.";
            echo '<meta http-equiv="refresh" content="3;url=login.php">';
        } else {
            $error = "Gagal mereset password. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Loaz Industries</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
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
        
        .forgot-card {
            background: white;
            border: none;
            border-radius: 32px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        
        .forgot-card .card-body {
            padding: 2.5rem;
        }
        
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
        
        .input-group-custom {
            position: relative;
            margin-bottom: 1.5rem;
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
            padding: 0.9rem 1rem 0.9rem 45px;
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 16px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
            width: 100%;
        }
        
        .input-group-custom .form-control:focus,
        .input-group-custom .form-select:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        .input-group-custom .form-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23C08552' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
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
            box-shadow: var(--shadow-md);
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
        
        .alert-info-custom {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
        .back-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .back-link:hover {
            color: var(--medium-brown);
            text-decoration: underline;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(192, 133, 82, 0.2);
            color: var(--medium-brown);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            margin: 0 0.5rem;
        }
        
        .step.active {
            background: var(--gold-brown);
            color: white;
        }
        
        .step-line {
            width: 40px;
            height: 2px;
            background: rgba(192, 133, 82, 0.2);
            margin: auto 0;
        }
        
        .password-strength {
            margin-top: -0.8rem;
            margin-bottom: 1rem;
            font-size: 0.7rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .info-note {
            background: rgba(23, 162, 184, 0.08);
            border-radius: 12px;
            padding: 0.75rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #17a2b8;
            text-align: center;
        }
        
        @media (max-width: 576px) {
            .forgot-card .card-body {
                padding: 1.8rem;
            }
            
            .brand-icon-large {
                width: 55px;
                height: 55px;
            }
            
            .brand-icon-large i {
                font-size: 1.5rem;
            }
            
            .step-line {
                width: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="forgot-card">
                    <div class="card-body">
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                            <div class="step-line"></div>
                            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2</div>
                            <div class="step-line"></div>
                            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                        </div>
                        
                        <div class="text-center mb-4">
                            <div class="brand-icon-large">
                                <i class="fas fa-microchip"></i>
                            </div>
                            
                            <?php if ($step == 1): ?>
                                <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Lupa Password?</h3>
                                <p style="color: var(--medium-brown); font-size: 0.85rem;">Masukkan email terdaftar Anda</p>
                            <?php elseif ($step == 2 && $needs_setup): ?>
                                <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Atur Keamanan Akun</h3>
                                <p style="color: var(--medium-brown); font-size: 0.85rem;">Buat pertanyaan keamanan untuk akun Anda</p>
                            <?php elseif ($step == 2): ?>
                                <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Verifikasi Keamanan</h3>
                                <p style="color: var(--medium-brown); font-size: 0.85rem;">Jawab pertanyaan keamanan Anda</p>
                            <?php else: ?>
                                <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Reset Password</h3>
                                <p style="color: var(--medium-brown); font-size: 0.85rem;">Buat password baru untuk akun Anda</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert-custom alert-danger-custom">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert-custom alert-success-custom">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step 1: Form Email -->
                        <?php if ($step == 1): ?>
                            <form method="POST">
                                <div class="input-group-custom">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>
                                <button type="submit" name="check_email" class="btn-submit">
                                    <i class="fas fa-arrow-right me-2"></i> Lanjutkan
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Step 2: Form Security Question (Setup untuk user yang belum punya) -->
                        <?php if ($step == 2 && $needs_setup): ?>
                            <form method="POST">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <input type="hidden" name="is_setup" value="1">
                                
                                <div class="alert-info-custom mb-3">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <small>Akun Anda belum memiliki pertanyaan keamanan. Silakan atur pertanyaan keamanan terlebih dahulu.</small>
                                </div>
                                
                                <div class="input-group-custom">
                                    <i class="fas fa-question-circle"></i>
                                    <select name="security_question" class="form-select" required>
                                        <option value="">Pilih pertanyaan keamanan</option>
                                        <?php foreach ($security_questions as $question): ?>
                                            <option value="<?php echo htmlspecialchars($question); ?>" <?php echo $question == $security_question ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($question); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="input-group-custom">
                                    <i class="fas fa-paw"></i>
                                    <input type="text" name="security_answer" class="form-control" placeholder="Jawaban Anda" required>
                                </div>
                                <small class="text-muted d-block mb-3">* Jawaban akan dienkripsi dan aman</small>
                                
                                <button type="submit" name="verify_answer" class="btn-submit">
                                    <i class="fas fa-save me-2"></i> Simpan & Lanjutkan
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Step 2: Form Security Question (Verifikasi untuk user yang sudah punya) -->
                        <?php if ($step == 2 && !$needs_setup): ?>
                            <form method="POST">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                <div class="input-group-custom">
                                    <i class="fas fa-question-circle"></i>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($security_question); ?>" disabled style="background: #f5f5f5;">
                                </div>
                                <div class="input-group-custom">
                                    <i class="fas fa-paw"></i>
                                    <input type="text" name="security_answer" class="form-control" placeholder="Jawaban Anda" required>
                                </div>
                                <button type="submit" name="verify_answer" class="btn-submit">
                                    <i class="fas fa-shield-alt me-2"></i> Verifikasi
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Step 3: Form Reset Password -->
                        <?php if ($step == 3): ?>
                            <form method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <div class="input-group-custom">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Password Baru (min. 6 karakter)" required>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                
                                <div class="input-group-custom">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Konfirmasi Password Baru" required>
                                </div>
                                
                                <!-- Informasi tambahan tentang password sementara -->
                                <div class="info-note">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Dengan mereset password, akses admin untuk melihat password sementara Anda akan dinonaktifkan.
                                </div>
                                
                                <button type="submit" name="reset_password" class="btn-submit mt-3">
                                    <i class="fas fa-save me-2"></i> Reset Password
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Back to Login Link -->
                        <div class="text-center mt-4">
                            <a href="login.php" class="back-link">
                                <i class="fas fa-arrow-left"></i> Kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker untuk step 3
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('passwordStrength');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let message = '';
                let className = '';
                
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                if (password.length === 0) {
                    message = '';
                } else if (strength <= 2) {
                    message = '⚠️ Password lemah';
                    className = 'strength-weak';
                } else if (strength <= 4) {
                    message = '⚠️ Password sedang';
                    className = 'strength-medium';
                } else {
                    message = '✓ Password kuat';
                    className = 'strength-strong';
                }
                
                strengthDiv.innerHTML = message;
                strengthDiv.className = 'password-strength ' + className;
            });
        }
        
        // Confirm password validation
        const confirmInput = document.getElementById('confirm_password');
        if (confirmInput) {
            confirmInput.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirm = this.value;
                
                if (confirm.length > 0 && password !== confirm) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = 'rgba(192, 133, 82, 0.2)';
                }
            });
        }
    </script>
</body>
</html>