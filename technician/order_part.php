<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if ($_SESSION['role'] !== 'technician') {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /loaz_industries/admin/dashboard.php');
    } else {
        header('Location: /loaz_industries/user/dashboard.php');
    }
    exit();
}

$technician_id = $_SESSION['user_id'];

// Get technician's database id
$stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
$stmt->execute([$technician_id]);
$tech = $stmt->fetch();
$tech_db_id = $tech['id'] ?? null;

if (!$tech_db_id) {
    // Create technician record if not exists
    $stmt = $pdo->prepare("
        INSERT INTO technicians (user_id, specialty, status, created_at) 
        VALUES (?, 'Laptop & PC', 'available', NOW())
    ");
    $stmt->execute([$technician_id]);
    
    $stmt = $pdo->prepare("SELECT id FROM technicians WHERE user_id = ?");
    $stmt->execute([$technician_id]);
    $tech = $stmt->fetch();
    $tech_db_id = $tech['id'];
}

$error = '';
$success = '';

// Handle take service
if (isset($_GET['take'])) {
    $service_id = $_GET['take'];
    
    $stmt = $pdo->prepare("
        UPDATE services 
        SET technician_id = ?, status = 'accepted' 
        WHERE id = ? AND technician_id IS NULL AND status = 'pending'
    ");
    if ($stmt->execute([$tech_db_id, $service_id])) {
        $stmt = $pdo->prepare("
            INSERT INTO service_updates (service_id, status, note) 
            VALUES (?, 'accepted', 'Teknisi telah mengambil servis ini')
        ");
        $stmt->execute([$service_id]);
        
        header('Location: update_status.php?id=' . $service_id . '&msg=taken');
    } else {
        header('Location: my_services.php?msg=error');
    }
    exit();
}

// Handle update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $service_id = $_POST['service_id'];
    $new_status = $_POST['status'];
    $note = trim($_POST['note']);
    $estimated_cost = !empty($_POST['estimated_cost']) ? $_POST['estimated_cost'] : null;
    
    // Validate status
    $allowed_status = ['accepted', 'repairing', 'done'];
    if (!in_array($new_status, $allowed_status)) {
        $error = 'Status tidak valid!';
    }
    
    // Handle photo upload
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0 && empty($error)) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $max_size = 2 * 1024 * 1024;
        
        if ($_FILES['photo']['size'] > $max_size) {
            $error = 'Ukuran file terlalu besar! Maksimal 2MB.';
        } elseif (!in_array($ext, $allowed)) {
            $error = 'Format file tidak didukung! Gunakan JPG, PNG, GIF, WEBP.';
        } else {
            if (!file_exists('../uploads/service_photos/')) {
                mkdir('../uploads/service_photos/', 0777, true);
            }
            $photo = 'service_' . $service_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/service_photos/' . $photo);
        }
    }
    
    if (empty($error)) {
        $pdo->beginTransaction();
        
        try {
            // Update service status
            $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ? AND technician_id = ?");
            $stmt->execute([$new_status, $service_id, $tech_db_id]);
            
            // Update estimated cost if provided and status is done
            if ($new_status == 'done' && $estimated_cost && $estimated_cost > 0) {
                $stmt = $pdo->prepare("UPDATE services SET estimated_cost = ? WHERE id = ?");
                $stmt->execute([$estimated_cost, $service_id]);
            }
            
            // Add to service updates
            $update_note = $note;
            if ($photo) {
                $update_note .= ($note ? "\n\n" : '') . "📸 Foto servis telah diupload.";
            }
            
            // Insert into service_updates
            $stmt = $pdo->prepare("
                INSERT INTO service_updates (service_id, status, note) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$service_id, $new_status, $update_note]);
            
            // If status is 'done', create transaction for payment
            if ($new_status == 'done' && $estimated_cost) {
                $stmt = $pdo->prepare("SELECT id FROM transactions WHERE service_id = ?");
                $stmt->execute([$service_id]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (service_id, total_amount, payment_status) 
                        VALUES (?, ?, 'pending')
                    ");
                    $stmt->execute([$service_id, $estimated_cost]);
                }
            }
            
            $pdo->commit();
            $success = 'Status servis berhasil diperbarui menjadi ' . ucfirst($new_status) . '!';
            
            // Refresh service data
            $stmt = $pdo->prepare("
                SELECT s.*, u.name as customer_name, u.phone as customer_phone, u.address as customer_address
                FROM services s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.technician_id = ?
            ");
            $stmt->execute([$service_id, $tech_db_id]);
            $service = $stmt->fetch();
            
            // Refresh service updates
            $stmt = $pdo->prepare("
                SELECT * FROM service_updates 
                WHERE service_id = ? 
                ORDER BY updated_at DESC
            ");
            $stmt->execute([$service_id]);
            $service_updates = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memperbarui status: ' . $e->getMessage();
        }
    }
}

// Get service details if ID is provided
$service = null;
$service_updates = [];
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($service_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as customer_name, u.phone as customer_phone, u.address as customer_address
        FROM services s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ? AND s.technician_id = ?
    ");
    $stmt->execute([$service_id, $tech_db_id]);
    $service = $stmt->fetch();
    
    if ($service) {
        // Get service updates
        $stmt = $pdo->prepare("
            SELECT * FROM service_updates 
            WHERE service_id = ? 
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$service_id]);
        $service_updates = $stmt->fetchAll();
    }
}

// Get all services for this technician (for dropdown)
$stmt = $pdo->prepare("
    SELECT s.id, s.device, s.status 
    FROM services s 
    WHERE s.technician_id = ?
    ORDER BY FIELD(s.status, 'pending', 'accepted', 'repairing', 'done'), s.created_at DESC
");
$stmt->execute([$tech_db_id]);
$my_services = $stmt->fetchAll();

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'taken') {
    $msg = '<div class="alert alert-success rounded-4"><i class="fas fa-check-circle me-2"></i>Servis berhasil diambil!</div>';
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-2">
                            <li class="breadcrumb-item">
                                <a href="dashboard.php" style="color: var(--gold-brown); text-decoration: none;">
                                    <i class="fas fa-home me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="my_services.php" style="color: var(--gold-brown); text-decoration: none;">
                                    Servis Saya
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                Update Status
                            </li>
                        </ol>
                    </nav>
                    <h1 class="display-5 fw-light" style="color: var(--dark-brown);">Update Status Servis</h1>
                    <p class="text-muted">Perbarui status pengerjaan servis elektronik</p>
                </div>
                <a href="my_services.php" class="btn btn-outline-gold rounded-4">
                    <i class="fas fa-arrow-left me-2"></i> Kembali
                </a>
            </div>

            <?php echo $msg; ?>
            
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

            <!-- Select Service Dropdown -->
            <?php if (!$service && count($my_services) > 0): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><i class="fas fa-search me-2" style="color: var(--gold-brown);"></i>Pilih Servis</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <select name="id" class="form-select" required>
                                    <option value="">-- Pilih Servis --</option>
                                    <?php foreach ($my_services as $svc): ?>
                                        <option value="<?php echo $svc['id']; ?>">
                                            #<?php echo $svc['id']; ?> - <?php echo htmlspecialchars($svc['device']); ?> 
                                            (<?php echo ucfirst($svc['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-gold w-100 rounded-4">
                                    <i class="fas fa-arrow-right me-2"></i> Pilih
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($service): ?>
                <!-- Service Details Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2" style="color: var(--gold-brown);"></i>Detail Servis</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Device</label>
                                    <p class="fw-semibold mb-0"><?php echo htmlspecialchars($service['device']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small">Customer</label>
                                    <p class="mb-0"><?php echo htmlspecialchars($service['customer_name']); ?></p>
                                    <small class="text-muted">📞 <?php echo htmlspecialchars($service['customer_phone'] ?? '-'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-muted small">Tipe Servis</label>
                                    <p class="mb-0"><?php echo $service['service_type'] == 'onsite' ? '🏠 On-site' : '🚚 Pick-up'; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted small">Status Saat Ini</label>
                                    <div>
                                        <?php
                                        $status_badge = [
                                            'pending' => 'warning',
                                            'accepted' => 'info',
                                            'repairing' => 'primary',
                                            'done' => 'success'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_badge[$service['status']] ?? 'secondary'; ?> px-3 py-2 rounded-pill">
                                            <?php echo ucfirst($service['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="text-muted small">Masalah</label>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($service['problem'])); ?></p>
                                </div>
                            </div>
                            <?php if ($service['customer_address']): ?>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="text-muted small">Alamat Customer</label>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($service['customer_address'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- PART LIST - TAMPILKAN PART YANG DIGUNAKAN -->
                            <?php 
                            $used_parts = [];
                            if ($service['used_parts']) {
                                $used_parts = json_decode($service['used_parts'], true);
                            }
                            if (count($used_parts) > 0): 
                            ?>
                            <div class="col-12 mt-3">
                                <div class="part-list-box p-3 rounded-3">
                                    <label class="text-muted small mb-2"><i class="fas fa-microchip me-1"></i> Part yang Digunakan:</label>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Part</th>
                                                    <th>Jumlah</th>
                                                    <th>Harga</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $parts_subtotal = 0; ?>
                                                <?php foreach ($used_parts as $part): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($part['name']); ?></a>
                                                        <td><?php echo $part['quantity']; ?> x</a>
                                                        <td><?php echo formatCurrency($part['price']); ?></a>
                                                        <td class="fw-bold"><?php echo formatCurrency($part['price'] * $part['quantity']); ?></a>
                                                    </tr>
                                                    <?php $parts_subtotal += $part['price'] * $part['quantity']; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Total Part:</td>
                                                    <td class="fw-bold" style="color: var(--gold-brown);"><?php echo formatCurrency($parts_subtotal); ?></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Update Status Form - PERBAIKAN -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-transparent border-0 pt-4">
                        <h5 class="mb-0"><i class="fas fa-sync-alt me-2" style="color: var(--gold-brown);"></i>Update Status</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" id="updateStatusForm">
                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                            <input type="hidden" name="update_status" value="1">
                            
                            <div class="mb-4">
                                <label class="form-label">Status Pengerjaan</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="status-option <?php echo $service['status'] == 'accepted' ? 'active' : ''; ?>">
                                            <input type="radio" name="status" value="accepted" id="status_accepted" 
                                                   <?php echo $service['status'] == 'accepted' ? 'checked' : ''; ?>>
                                            <label for="status_accepted" class="w-100" onclick="this.previousElementSibling.checked=true; updateCostSection();">
                                                <i class="fas fa-check-circle me-2" style="color: #17a2b8;"></i>
                                                <span>Diterima</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="status-option <?php echo $service['status'] == 'repairing' ? 'active' : ''; ?>">
                                            <input type="radio" name="status" value="repairing" id="status_repairing"
                                                   <?php echo $service['status'] == 'repairing' ? 'checked' : ''; ?>>
                                            <label for="status_repairing" class="w-100" onclick="this.previousElementSibling.checked=true; updateCostSection();">
                                                <i class="fas fa-tools me-2" style="color: var(--gold-brown);"></i>
                                                <span>Diperbaiki</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="status-option <?php echo $service['status'] == 'done' ? 'active' : ''; ?>">
                                            <input type="radio" name="status" value="done" id="status_done"
                                                   <?php echo $service['status'] == 'done' ? 'checked' : ''; ?>>
                                            <label for="status_done" class="w-100" onclick="this.previousElementSibling.checked=true; updateCostSection();">
                                                <i class="fas fa-check-double me-2" style="color: #28a745;"></i>
                                                <span>Selesai</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4" id="estimated_cost_section" style="display: none;">
                                <label class="form-label">Estimasi Biaya</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="estimated_cost" class="form-control" 
                                           placeholder="Masukkan estimasi biaya servis" 
                                           value="<?php echo $service['estimated_cost'] ?? ''; ?>">
                                </div>
                                <small class="text-muted">Isi jika status diubah menjadi "Selesai"</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Upload Foto Servis (Opsional)</label>
                                <input type="file" name="photo" class="form-control" accept="image/*">
                                <small class="text-muted">Upload foto bukti pengerjaan atau hasil servis</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea name="note" class="form-control" rows="3" 
                                          placeholder="Tambahkan catatan tentang pengerjaan servis..."></textarea>
                            </div>

                            <!-- Tombol Pesan Part (muncul jika status repairing) -->
                            <?php if ($service['status'] == 'repairing'): ?>
                                <div class="mb-4">
                                    <a href="order_part.php?service_id=<?php echo $service['id']; ?>" class="btn btn-outline-gold w-100 rounded-4 py-2">
                                        <i class="fas fa-boxes me-2"></i> Pesan Part untuk Servis Ini
                                    </a>
                                    <small class="text-muted d-block mt-2">Tambah part yang diperlukan untuk perbaikan</small>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-gold w-100 rounded-4 py-3">
                                <i class="fas fa-save me-2"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Service Updates Timeline -->
                <?php if (count($service_updates) > 0): ?>
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-transparent border-0 pt-4">
                            <h5 class="mb-0"><i class="fas fa-history me-2" style="color: var(--gold-brown);"></i>Riwayat Update</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="timeline">
                                <?php foreach ($service_updates as $update): ?>
                                    <div class="timeline-item d-flex mb-3">
                                        <div class="timeline-icon me-3">
                                            <i class="fas fa-check-circle" style="color: var(--gold-brown);"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold">
                                                <?php 
                                                    $status_labels = [
                                                        'pending' => 'Menunggu',
                                                        'accepted' => 'Diterima',
                                                        'repairing' => 'Diperbaiki',
                                                        'done' => 'Selesai'
                                                    ];
                                                    echo $status_labels[$update['status']] ?? ucfirst($update['status']);
                                                ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($update['updated_at'])); ?></small>
                                            <?php if ($update['note']): ?>
                                                <p class="small mb-0 mt-1"><?php echo nl2br(htmlspecialchars($update['note'])); ?></p>
                                            <?php endif; ?>
                                            <?php if (isset($update['photo']) && $update['photo']): ?>
                                                <a href="../uploads/service_photos/<?php echo $update['photo']; ?>" target="_blank" class="small text-gold">
                                                    <i class="fas fa-image me-1"></i> Lihat Foto
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif (count($my_services) == 0): ?>
                <div class="card border-0 shadow-sm rounded-4 text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-tools fa-4x text-muted mb-3"></i>
                        <h3>Belum Ada Servis</h3>
                        <p class="text-muted">Anda belum memiliki servis yang ditugaskan</p>
                        <a href="dashboard.php" class="btn btn-gold rounded-4 mt-3">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .status-option {
        border: 2px solid rgba(192, 133, 82, 0.2);
        border-radius: 12px;
        padding: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .status-option:hover {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.05);
    }
    
    .status-option.active {
        border-color: var(--gold-brown);
        background: rgba(192, 133, 82, 0.08);
    }
    
    .status-option input {
        display: none;
    }
    
    .status-option label {
        cursor: pointer;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .timeline-icon {
        width: 24px;
    }
    
    .part-list-box {
        background: rgba(192, 133, 82, 0.05);
        border-radius: 12px;
    }
    
    .part-list-box table {
        font-size: 0.85rem;
    }
    
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
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: var(--medium-brown);
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
    
    .text-gold {
        color: var(--gold-brown);
    }
</style>

<script>
    function updateCostSection() {
        const radios = document.querySelectorAll('input[name="status"]');
        const costSection = document.getElementById('estimated_cost_section');
        let selectedValue = null;
        
        for (let radio of radios) {
            if (radio.checked) {
                selectedValue = radio.value;
                break;
            }
        }
        
        if (selectedValue === 'done') {
            costSection.style.display = 'block';
        } else {
            costSection.style.display = 'none';
        }
    }
    
    // Add click handlers to status options
    document.querySelectorAll('.status-option').forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Update active class
                document.querySelectorAll('.status-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update cost section visibility
                updateCostSection();
            }
        });
    });
    
    // Check initial status on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCostSection();
        
        // Set active class based on checked radio
        const checkedRadio = document.querySelector('input[name="status"]:checked');
        if (checkedRadio) {
            const parentOption = checkedRadio.closest('.status-option');
            if (parentOption) {
                document.querySelectorAll('.status-option').forEach(opt => {
                    opt.classList.remove('active');
                });
                parentOption.classList.add('active');
            }
        }
    });
</script>

<?php include '../includes/footer.php'; ?>