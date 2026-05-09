<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';
$success = '';
$photo_error = '';

// Handle profile photo upload
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $_FILES['profile_photo']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if ($_FILES['profile_photo']['size'] > $max_size) {
        $photo_error = 'Ukuran file terlalu besar! Maksimal 2MB.';
    } elseif (!in_array($ext, $allowed)) {
        $photo_error = 'Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.';
    } else {
        // Delete old photo if exists
        if ($user['profile_photo'] && file_exists('../assets/images/users/' . $user['profile_photo'])) {
            unlink('../assets/images/users/' . $user['profile_photo']);
        }
        
        // Create directory if not exists
        if (!file_exists('../assets/images/users/')) {
            mkdir('../assets/images/users/', 0777, true);
        }
        
        // Generate unique filename
        $new_filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
        $upload_path = '../assets/images/users/' . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt->execute([$new_filename, $_SESSION['user_id']]);
            $user['profile_photo'] = $new_filename;
            $success = 'Foto profil berhasil diupdate!';
        } else {
            $photo_error = 'Gagal mengupload foto. Silakan coba lagi.';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = trim($_POST['postal_code']);
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null; // Fix: convert empty to NULL
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null; // Fix: convert empty to NULL
    
    // Validate name
    if (empty($name)) {
        $error = 'Nama lengkap harus diisi!';
    }
    
    // Validate phone format (optional)
    if (!empty($phone) && !preg_match('/^[0-9]{10,13}$/', $phone)) {
        $error = 'Nomor telepon harus terdiri dari 10-13 digit angka!';
    }
    
    // Handle password change
    $update_password = '';
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        if (password_verify($current_password, $user['password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (strlen($new_password) < 6) {
                $error = 'Password baru minimal 6 karakter!';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Konfirmasi password baru tidak sama!';
            } else {
                $update_password = password_hash($new_password, PASSWORD_DEFAULT);
            }
        } else {
            $error = 'Password saat ini salah!';
        }
    }
    
    if (empty($error)) {
        try {
            if ($update_password) {
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        name = ?, phone = ?, address = ?, city = ?, province = ?, 
                        postal_code = ?, gender = ?, birth_date = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $phone, $address, $city, $province, $postal_code, $gender, $birth_date, $update_password, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        name = ?, phone = ?, address = ?, city = ?, province = ?, 
                        postal_code = ?, gender = ?, birth_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $phone, $address, $city, $province, $postal_code, $gender, $birth_date, $_SESSION['user_id']]);
            }
            
            $_SESSION['user_name'] = $name;
            $success = 'Profil berhasil diperbarui!';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            // Handle specific database errors
            if (strpos($e->getMessage(), 'gender') !== false) {
                $error = 'Format jenis kelamin tidak valid!';
            } elseif (strpos($e->getMessage(), 'birth_date') !== false) {
                $error = 'Format tanggal lahir tidak valid!';
            } else {
                $error = 'Terjadi kesalahan database: ' . $e->getMessage();
            }
        }
    }
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM services WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_services = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_orders = $stmt->fetch()['total'];

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-4 mb-4 mb-lg-0">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm rounded-4 text-center">
                <div class="card-body p-4">
                    <!-- Profile Photo -->
                    <div class="profile-photo-container mb-3">
                        <?php if ($user['profile_photo'] && file_exists('../assets/images/users/' . $user['profile_photo'])): ?>
                            <img src="../assets/images/users/<?php echo $user['profile_photo']; ?>" 
                                 alt="Profile Photo" 
                                 class="profile-photo-img"
                                 id="profilePhoto">
                        <?php else: ?>
                            <div class="profile-photo-placeholder" id="profilePhotoPlaceholder">
                                <i class="fas fa-user-circle fa-5x" style="color: var(--gold-brown);"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload Button -->
                        <button class="btn btn-sm btn-outline-gold mt-2" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                            <i class="fas fa-camera me-1"></i> Ganti Foto
                        </button>
                    </div>
                    
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="row g-3 mt-2">
                        <div class="col-6">
                            <div class="bg-light rounded-4 p-2">
                                <div class="small text-muted">Servis</div>
                                <div class="fw-bold fs-4" style="color: var(--gold-brown);"><?php echo $total_services; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded-4 p-2">
                                <div class="small text-muted">Pesanan</div>
                                <div class="fw-bold fs-4" style="color: var(--gold-brown);"><?php echo $total_orders; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Member Sejak</span>
                            <span><?php echo date('d F Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Status</span>
                            <span class="badge bg-success rounded-pill">Aktif</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Page Header with Back Button -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="dashboard.php" style="color: var(--gold-brown); text-decoration: none;">
                                    <i class="fas fa-home me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                Profil Saya
                            </li>
                        </ol>
                    </nav>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Profil Saya</h1>
                    <p class="text-muted">Kelola informasi akun Anda</p>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Edit Profile Form -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2" style="color: var(--gold-brown);"></i>Edit Profil</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="text-muted">Email tidak dapat diubah</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. Telepon</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="081234567890">
                                <small class="text-muted">10-13 digit angka</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jenis Kelamin</label>
                                <select name="gender" class="form-select">
                                    <option value="">Pilih</option>
                                    <option value="male" <?php echo ($user['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="female" <?php echo ($user['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Alamat</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kota</label>
                                <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Provinsi</label>
                                <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kode Pos</label>
                                <input type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h6 class="fw-semibold mb-3"><i class="fas fa-lock me-2" style="color: var(--gold-brown);"></i>Ubah Password</h6>
                        <div class="alert alert-info rounded-4 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Password Saat Ini</label>
                                <input type="password" name="current_password" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-control">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="update_profile" class="btn btn-gold rounded-4 px-4">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content rounded-4">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i> Upload Foto Profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body text-center">
                    <?php if ($photo_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $photo_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="profile-photo-preview mb-3">
                            <?php if ($user['profile_photo'] && file_exists('../assets/images/users/' . $user['profile_photo'])): ?>
                                <img src="../assets/images/users/<?php echo $user['profile_photo']; ?>" 
                                     alt="Preview" 
                                     id="photoPreview"
                                     style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <div id="photoPreview" class="photo-preview-placeholder">
                                    <i class="fas fa-user-circle fa-5x" style="color: var(--gold-brown);"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="profile_photo" id="profilePhotoInput" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-2">Format: JPG, PNG, GIF, WEBP. Max 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-gold">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .text-gold { color: var(--gold-brown); }
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
    .profile-photo-container {
        position: relative;
    }
    .profile-photo-img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid var(--gold-brown);
        padding: 3px;
    }
    .profile-photo-placeholder {
        width: 120px;
        height: 120px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    .photo-preview-placeholder {
        width: 120px;
        height: 120px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
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
    .alert-info {
        background: rgba(23, 162, 184, 0.1);
        border: none;
        color: #17a2b8;
    }
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
    }
</style>

<script>
// Preview image before upload
document.getElementById('profilePhotoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.getElementById('photoPreview');
            if (preview && preview.tagName === 'IMG') {
                preview.src = event.target.result;
            } else {
                // Replace placeholder with image
                const img = document.createElement('img');
                img.id = 'photoPreview';
                img.src = event.target.result;
                img.style.width = '120px';
                img.style.height = '120px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                const container = document.querySelector('.profile-photo-preview');
                if (container) {
                    container.innerHTML = '';
                    container.appendChild(img);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include '../includes/footer.php'; ?>