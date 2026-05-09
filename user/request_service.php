<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if (!isUser()) {
    if (isAdmin()) header('Location: /loaz_industries/admin/dashboard.php');
    elseif (isTechnician()) header('Location: /loaz_industries/technician/dashboard.php');
    exit();
}

$error = '';
$success = '';

// [FIX #6] Pastikan $pdo tersedia sebelum digunakan
if (!isset($pdo) || $pdo === null) {
    die('Koneksi database tidak tersedia. Silakan coba lagi.');
}

// Get available technicians untuk dipilih manual
try {
    $stmt = $pdo->query("
        SELECT t.id, u.name, t.specialty 
        FROM technicians t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.status = 'available' AND t.is_active = 1
        ORDER BY u.name ASC
    ");
    $technicians = $stmt->fetchAll();
} catch (PDOException $e) {
    $technicians = [];
}

// Get user's devices (riwayat device) - max 10
try {
    $stmt = $pdo->prepare("
        SELECT device FROM (
            SELECT device, MAX(created_at) AS last_used
            FROM services
            WHERE user_id = ?
            GROUP BY device
            ORDER BY last_used DESC
            LIMIT 10
        ) AS recent_devices
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $previous_devices = $stmt->fetchAll();
} catch (PDOException $e) {
    $previous_devices = [];
}

// Daftar nilai service_type yang valid
$valid_service_types = ['onsite', 'pickup'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $device       = trim($_POST['device'] ?? '');
    $problem      = trim($_POST['problem'] ?? '');

    // Validasi service_type ke whitelist, default 'onsite'
    $raw_service_type = $_POST['service_type'] ?? '';
    $service_type = in_array($raw_service_type, $valid_service_types, true) ? $raw_service_type : 'onsite';

    $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;

    // [FIX] Validasi - Hanya cek tidak boleh kosong, tanpa minimal karakter
    if (empty($device)) {
        $error = 'Jenis device harus diisi!';
    } elseif (strlen($device) > 100) {
        $error = 'Nama device maksimal 100 karakter!';
    } elseif (empty($problem)) {
        $error = 'Deskripsi masalah harus diisi!';
    } elseif (strlen($problem) > 2000) {
        $error = 'Deskripsi masalah maksimal 2000 karakter!';
    } else {
        // Gunakan transaction untuk menghindari race condition
        try {
            $pdo->beginTransaction();

            // Validate technician_id jika diberikan, dengan SELECT FOR UPDATE (lock baris)
            if ($technician_id) {
                $stmt = $pdo->prepare("
                    SELECT id FROM technicians 
                    WHERE id = ? AND status = 'available' AND is_active = 1
                    FOR UPDATE
                ");
                $stmt->execute([$technician_id]);
                if (!$stmt->fetch()) {
                    // Teknisi tidak tersedia, fallback ke auto-assign
                    $technician_id = null;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO services (user_id, technician_id, device, problem, service_type, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$_SESSION['user_id'], $technician_id, $device, $problem, $service_type]);
            $service_id = $pdo->lastInsertId();

            // Add initial service update
            $stmt = $pdo->prepare("
                INSERT INTO service_updates (service_id, status, note) 
                VALUES (?, 'pending', 'Servis baru telah dibuat dan menunggu konfirmasi')
            ");
            $stmt->execute([$service_id]);

            $pdo->commit();

            // Redirect ke my_services.php
            header('Location: /loaz_industries/user/my_services.php?status=success');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Service request error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan database. Silakan coba lagi.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">

            <!-- Page Title -->
            <div class="text-center mb-5">
                <div class="title-icon mb-3">
                    <i class="fas fa-tools fa-3x" style="color: var(--gold-brown);"></i>
                </div>
                <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Request Servis Elektronik</h1>
                <p class="text-muted">Isi form di bawah untuk meminta servis elektronik Anda</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                        <div>
                            <strong>Terjadi Kesalahan!</strong><br>
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Request Form Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4">
                    <h5 class="mb-0">
                        <i class="fas fa-pen-alt me-2" style="color: var(--gold-brown);"></i>
                        Form Request Servis
                    </h5>
                    <p class="text-muted small mt-1">Isi data dengan lengkap agar teknisi dapat membantu dengan cepat</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <form method="POST" id="requestForm">
                        <input type="hidden" name="submit_request" value="1">

                        <!-- Device Input -->
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-microchip me-2" style="color: var(--gold-brown);"></i>
                                Jenis Device <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-laptop" style="color: var(--gold-brown);"></i>
                                </span>
                                <input type="text" name="device" id="device" class="form-control border-start-0"
                                       placeholder="Contoh: Laptop Asus ROG, iPhone 14 Pro, TV Samsung 43 inch"
                                       value="<?php echo isset($_POST['device']) ? htmlspecialchars(trim($_POST['device']), ENT_QUOTES, 'UTF-8') : ''; ?>"
                                       list="previous-devices" required>
                                <datalist id="previous-devices">
                                    <?php foreach ($previous_devices as $pd): ?>
                                        <option value="<?php echo htmlspecialchars($pd['device'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Masukkan merk, tipe, dan model device Anda</small>
                                <small class="text-muted" id="deviceCharCount">0 / 100</small>
                            </div>
                            <div class="invalid-feedback" id="deviceError">Device harus diisi!</div>
                        </div>

                        <!-- Problem Description -->
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-exclamation-triangle me-2" style="color: var(--gold-brown);"></i>
                                Deskripsi Masalah <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 align-items-start pt-3">
                                    <i class="fas fa-tools" style="color: var(--gold-brown);"></i>
                                </span>
                                <textarea name="problem" id="problem" class="form-control border-start-0"
                                          rows="5" placeholder="Jelaskan masalah yang dialami secara detail...&#10;&#10;Contoh:&#10;- Layar tidak menyala&#10;- Baterai cepat habis&#10;- Suara tidak keluar" required><?php echo isset($_POST['problem']) ? htmlspecialchars(trim($_POST['problem']), ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Semakin detail deskripsi, semakin cepat teknisi memahami masalah</small>
                                <small class="text-muted" id="charCount">0 / 2000</small>
                            </div>
                            <div class="invalid-feedback" id="problemError">Deskripsi masalah harus diisi!</div>
                        </div>

                        <!-- Service Type -->
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-truck me-2" style="color: var(--gold-brown);"></i>
                                Tipe Servis
                            </label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="service-option">
                                        <input type="radio" name="service_type" value="onsite" id="onsite"
                                               <?php echo (!isset($_POST['service_type']) || $_POST['service_type'] === 'onsite') ? 'checked' : ''; ?>>
                                        <label for="onsite" class="w-100">
                                            <div class="d-flex align-items-center">
                                                <div class="service-icon me-3">
                                                    <i class="fas fa-home"></i>
                                                </div>
                                                <div>
                                                    <strong>On-site Service</strong>
                                                    <p class="small text-muted mb-0">Teknisi datang ke lokasi Anda</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="service-option">
                                        <input type="radio" name="service_type" value="pickup" id="pickup"
                                               <?php echo (isset($_POST['service_type']) && $_POST['service_type'] === 'pickup') ? 'checked' : ''; ?>>
                                        <label for="pickup" class="w-100">
                                            <div class="d-flex align-items-center">
                                                <div class="service-icon me-3">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div>
                                                    <strong>Pick-up Service</strong>
                                                    <p class="small text-muted mb-0">Device dijemput kurir</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technician Selection -->
                        <div class="form-group mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user-cog me-2" style="color: var(--gold-brown);"></i>
                                Pilih Teknisi (Opsional)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0">
                                    <i class="fas fa-users" style="color: var(--gold-brown);"></i>
                                </span>
                                <select name="technician_id" class="form-select border-start-0" id="technicianSelect">
                                    <option value="">🎯 Sistem akan memilihkan teknisi terbaik</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?php echo (int)$tech['id']; ?>"
                                            <?php echo (isset($_POST['technician_id']) && (int)$_POST['technician_id'] === (int)$tech['id']) ? 'selected' : ''; ?>>
                                            🔧 <?php echo htmlspecialchars($tech['name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($tech['specialty'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Kosongkan jika ingin servis Anda diambil oleh teknisi yang tersedia (lebih cepat diproses)
                            </small>
                        </div>

                        <!-- Loading Indicator -->
                        <div id="loadingIndicator" class="text-center mb-3" style="display: none;">
                            <div class="spinner-border text-gold" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Mengirim request...</p>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submitBtn" class="btn btn-submit w-100 py-3 rounded-4 fw-semibold">
                            <i class="fas fa-paper-plane me-2"></i> Kirim Request Servis
                            <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Box -->
            <div class="row mt-4 g-3">
                <div class="col-md-4">
                    <div class="info-card text-center p-3 rounded-4">
                        <div class="info-icon mx-auto mb-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h6 class="fw-semibold">Respon Cepat</h6>
                        <small class="text-muted">Teknisi akan menghubungi dalam 1x24 jam</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card text-center p-3 rounded-4">
                        <div class="info-icon mx-auto mb-3">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h6 class="fw-semibold">Garansi Servis</h6>
                        <small class="text-muted">Garansi 1 bulan untuk setiap servis</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card text-center p-3 rounded-4">
                        <div class="info-icon mx-auto mb-3">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h6 class="fw-semibold">Teknisi Profesional</h6>
                        <small class="text-muted">Berpengalaman dan bersertifikat</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --cream: #FFF8F0;
        --gold-brown: #C08552;
        --medium-brown: #8C5A3C;
        --dark-brown: #4B2E2B;
        --shadow-sm: 0 2px 8px rgba(75, 46, 43, 0.05);
        --shadow-md: 0 4px 16px rgba(75, 46, 43, 0.08);
        --transition: all 0.3s ease;
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
    }

    .title-icon {
        width: 70px;
        height: 70px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        transition: var(--transition);
    }

    .title-icon:hover {
        transform: scale(1.05);
    }

    .service-option {
        border: 2px solid rgba(192, 133, 82, 0.2);
        border-radius: 16px;
        transition: var(--transition);
        cursor: pointer;
    }

    .service-option:hover {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.03);
    }

    .service-option input {
        display: none;
    }

    .service-option label {
        padding: 1rem;
        margin: 0;
        cursor: pointer;
        display: block;
    }

    .service-option:has(input:checked) {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.08);
    }

    .service-icon {
        width: 45px;
        height: 45px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }

    .service-option:hover .service-icon {
        background: rgba(192, 133, 82, 0.2);
        transform: scale(1.05);
    }

    .service-icon i {
        font-size: 1.2rem;
        color: var(--gold-brown);
    }

    .info-card {
        background: white;
        border: 1px solid rgba(192, 133, 82, 0.1);
        border-radius: 20px;
        transition: var(--transition);
        height: 100%;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(75, 46, 43, 0.1);
    }

    .info-icon {
        width: 50px;
        height: 50px;
        background: rgba(192, 133, 82, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        transition: var(--transition);
    }

    .info-card:hover .info-icon {
        background: rgba(192, 133, 82, 0.2);
        transform: scale(1.1);
    }

    .info-icon i {
        font-size: 1.3rem;
        color: var(--gold-brown);
    }

    .btn-submit {
        background: var(--gold-brown);
        color: white;
        border: none;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .btn-submit:hover {
        background: var(--medium-brown);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(192, 133, 82, 0.3);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .form-control, .form-select {
        border: 1.5px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: var(--transition);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--gold-brown);
        box-shadow: 0 0 0 3px rgba(192, 133, 82, 0.1);
        outline: none;
    }

    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }

    .invalid-feedback {
        font-size: 0.7rem;
        color: #dc3545;
        display: none;
    }

    .form-control.is-invalid ~ .invalid-feedback {
        display: block;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: none;
        color: #dc3545;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .service-option label {
            padding: 0.75rem;
        }

        .service-icon {
            width: 35px;
            height: 35px;
        }

        .service-icon i {
            font-size: 1rem;
        }

        .info-card {
            margin-bottom: 1rem;
        }
    }
</style>

<script>
// Character counter untuk device input (tanpa validasi minimal)
const deviceInput      = document.getElementById('device');
const deviceCharCount  = document.getElementById('deviceCharCount');
const problemTextarea  = document.getElementById('problem');
const charCountSpan    = document.getElementById('charCount');
const form             = document.getElementById('requestForm');
const submitBtn        = document.getElementById('submitBtn');
const loadingIndicator = document.getElementById('loadingIndicator');

// --- Device character counter (hanya counter, tanpa validasi minimal)---
function updateDeviceCharCount() {
    if (!deviceInput || !deviceCharCount) return;
    const length = deviceInput.value.trim().length;
    deviceCharCount.textContent = length + ' / 100';
    if (length > 100) {
        deviceCharCount.style.color = '#dc3545';
        deviceInput.classList.add('is-invalid');
    } else if (length > 0) {
        deviceCharCount.style.color = 'var(--gold-brown)';
        deviceInput.classList.remove('is-invalid');
    } else {
        deviceCharCount.style.color = 'var(--medium-brown)';
    }
}

if (deviceInput) {
    deviceInput.addEventListener('input', updateDeviceCharCount);
    updateDeviceCharCount();
}

// --- Problem character counter (hanya counter, tanpa validasi minimal)---
function updateCharCount() {
    if (!problemTextarea || !charCountSpan) return;
    const length = problemTextarea.value.trim().length;
    charCountSpan.textContent = length + ' / 2000';
    if (length > 2000) {
        charCountSpan.style.color = '#dc3545';
        problemTextarea.classList.add('is-invalid');
    } else if (length > 0) {
        charCountSpan.style.color = 'var(--gold-brown)';
        problemTextarea.classList.remove('is-invalid');
    } else {
        charCountSpan.style.color = 'var(--medium-brown)';
    }
}

if (problemTextarea) {
    problemTextarea.addEventListener('input', updateCharCount);
    updateCharCount();
}

// --- Form validation (hanya cek tidak kosong dan tidak melebihi max)---
function validateForm() {
    let isValid = true;

    // Validate device: tidak boleh kosong dan tidak melebihi 100 karakter
    if (deviceInput) {
        const val = deviceInput.value.trim();
        if (val.length === 0) {
            deviceInput.classList.add('is-invalid');
            isValid = false;
        } else if (val.length > 100) {
            deviceInput.classList.add('is-invalid');
            isValid = false;
        } else {
            deviceInput.classList.remove('is-invalid');
        }
    }

    // Validate problem: tidak boleh kosong dan tidak melebihi 2000 karakter
    if (problemTextarea) {
        const val = problemTextarea.value.trim();
        if (val.length === 0) {
            problemTextarea.classList.add('is-invalid');
            isValid = false;
        } else if (val.length > 2000) {
            problemTextarea.classList.add('is-invalid');
            isValid = false;
        } else {
            problemTextarea.classList.remove('is-invalid');
        }
    }

    return isValid;
}

// Real-time validation (hanya untuk max length)
if (deviceInput) {
    deviceInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (val.length > 0 && val.length <= 100) {
            this.classList.remove('is-invalid');
        } else if (val.length === 0) {
            this.classList.add('is-invalid');
        } else if (val.length > 100) {
            this.classList.add('is-invalid');
        }
    });
}

if (problemTextarea) {
    problemTextarea.addEventListener('input', function () {
        const val = this.value.trim();
        if (val.length > 0 && val.length <= 2000) {
            this.classList.remove('is-invalid');
        } else if (val.length === 0) {
            this.classList.add('is-invalid');
        } else if (val.length > 2000) {
            this.classList.add('is-invalid');
        }
    });
}

// Form submit
if (form) {
    form.addEventListener('submit', function (e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }

        if (submitBtn && loadingIndicator) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Mengirim...';
            loadingIndicator.style.display = 'block';
        }

        return true;
    });
}
</script>

<?php include '../includes/footer.php'; ?>