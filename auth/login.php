<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Jika sudah login, redirect ke dashboard sesuai role
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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        if ($user['role'] === 'admin') header('Location: /loaz_industries/admin/dashboard.php');
        elseif ($user['role'] === 'technician') header('Location: /loaz_industries/technician/dashboard.php');
        else header('Location: /loaz_industries/user/dashboard.php');
        exit();
    } else {
        $error = 'Email atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Loaz Industries</title>
    
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
        
        /* Background Decoration */
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
        .login-card {
            background: white;
            border: none;
            border-radius: 32px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }
        
        .login-card .card-body {
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
        .form-label {
            font-weight: 500;
            color: var(--dark-brown);
            font-size: 0.8rem;
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
        
        .input-group-custom .form-control::placeholder {
            color: #B8A99A;
        }
        
        /* Button */
        .btn-login {
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
        
        .btn-login:hover {
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
        
        /* Alert */
        .alert-custom {
            border: none;
            border-radius: 16px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }
        
        .alert-danger-custom {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        /* Links */
        .forgot-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--medium-brown);
            text-decoration: underline;
        }
        
        .register-link {
            color: var(--gold-brown);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .register-link:hover {
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
            .login-card .card-body {
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
                <div class="login-card">
                    <div class="card-body">
                        <!-- Back to Dashboard Button (Top Right) -->
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
                            <h3 style="color: var(--dark-brown); font-weight: 600; margin-bottom: 0.5rem;">Selamat Datang</h3>
                            <p style="color: var(--medium-brown); font-size: 0.85rem;">Login ke akun Loaz Industries Anda</p>
                        </div>
                        
                        <!-- Alert Error -->
                        <?php if ($error): ?>
                            <div class="alert-custom alert-danger-custom">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST">
                            <div class="input-group-custom">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                            
                            <div class="input-group-custom">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                            
                            <div class="text-end mb-3">
                                <a href="forgot_password.php" class="forgot-link">Lupa password?</a>
                            </div>
                            
                            <button type="submit" class="btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </form>
                        
                        <div class="divider">
                            <span>atau</span>
                        </div>
                        
                        <div class="text-center">
                            <p style="color: var(--medium-brown); font-size: 0.85rem; margin: 0;">
                                Belum punya akun? 
                                <a href="register.php" class="register-link">Daftar di sini</a>
                            </p>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>