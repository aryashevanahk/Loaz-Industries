<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Cek token
if (empty($token)) {
    header('Location: forgot_password.php');
    exit();
}

// Validasi token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = "Token tidak valid atau sudah kadaluarsa. Silakan request reset password baru.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = 'Password harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama!';
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        
        if ($stmt->execute([$hashed_password, $reset['email']])) {
            // Hapus token yang sudah digunakan
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$reset['email']]);
            
            $success = "Password berhasil direset! Silakan login dengan password baru Anda.";
            // Redirect after 3 seconds
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
    <title>Reset Password - Loaz Industries</title>
    
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
        
        .reset-card {
            background: white;
            border: none;
            border-radius: 32px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        
        .reset-card .card-body {
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
        
        
        
        .input-group-custom .form-control {
            padding: 0.9rem 1rem 0.9rem 45px;
            border: 1.5px solid rgba(192, 133, 82, 0.2);
            border-radius: 16px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
        }
        
        .input-group-custom .form-control:focus {
            border-color: var(--gold-brown);
            box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
            outline: none;
        }
        
        .btn-reset {
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
        
        .btn-reset:hover {
            background: var(--medium-brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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
        
        .back-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .back-link:hover {
            color: var(--medium-brown);
            text-decoration: underline;
        }
        
        .password-strength {
            margin-top: -0.8rem;
            margin-bottom: 1rem;
            font-size: 0.7rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        @media (max-width: 576px) {
            .reset-card .card-body {
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
            <div class="col-md-5 col-lg-4">
                <div class="reset-card">
                    <div class="card-body">
                        
                        <div class="text-center mb-4">
                            <div class="brand-icon-large">
                                <i class="fas fa-microchip"></i>
                            </div>
                            <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Reset Password</h3>
                            <p style="color: var(--medium-brown); font-size: 0.85rem;">Buat password baru untuk akun Anda</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert-custom alert-danger-custom">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                                <?php if (strpos($error, 'Token') !== false): ?>
                                    <div class="mt-2">
                                        <a href="forgot_password.php" class="back-link">Klik di sini untuk request baru</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert-custom alert-success-custom">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$error && $reset): ?>
                            <form method="POST" id="resetForm">
                                <div class="input-group-custom">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Password Baru (min. 6 karakter)" required>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                                
                                <div class="input-group-custom">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Konfirmasi Password Baru" required>
                                </div>
                                
                                <button type="submit" class="btn-reset">
                                    <i class="fas fa-save me-2"></i> Reset Password
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="back-link">
                                <i class="fas fa-arrow-left me-1"></i> Kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker
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