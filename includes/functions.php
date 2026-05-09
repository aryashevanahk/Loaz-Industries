<?php
// ============================================
// LOAZ INDUSTRIES - FUNCTIONS LIBRARY
// ============================================

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format currency to Indonesian Rupiah
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    if (!$amount) return 'Rp 0';
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Get status badge HTML
 * @param string $status
 * @return string
 */
function getStatusBadge($status) {
    // [FIX] Handle null or empty status
    if (empty($status) || $status === null) {
        $status = 'pending';
    }
    
    $badges = [
        'pending' => ['class' => 'badge-pending', 'text' => 'Menunggu'],
        'accepted' => ['class' => 'badge-accepted', 'text' => 'Diterima'],
        'repairing' => ['class' => 'badge-repairing', 'text' => 'Diperbaiki'],
        'done' => ['class' => 'badge-done', 'text' => 'Selesai'],
        'visit' => ['class' => 'badge-visit', 'text' => 'Kunjungan'],
        'paid' => ['class' => 'badge-paid', 'text' => 'Dibayar'],
        'shipped' => ['class' => 'badge-shipped', 'text' => 'Dikirim'],
        'completed' => ['class' => 'badge-completed', 'text' => 'Selesai'],
        'cancelled' => ['class' => 'badge-cancelled', 'text' => 'Dibatalkan'],
        'pending_confirmation' => ['class' => 'badge-pending', 'text' => 'Menunggu Konfirmasi']
    ];
    
    $badge = $badges[$status] ?? ['class' => 'badge-secondary', 'text' => ucfirst($status)];
    
    return "<span class='badge-custom {$badge['class']}'>{$badge['text']}</span>";
}

// ============================================
// AUTHENTICATION & AUTHORIZATION
// ============================================

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is technician
 * @return bool
 */
function isTechnician() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'technician';
}

/**
 * Check if user is regular user
 * @return bool
 */
function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

/**
 * Redirect if not logged in
 */
function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: /loaz_industries/auth/login.php');
        exit();
    }
}

/**
 * Redirect if not admin
 */
function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header('Location: /loaz_industries/index.php');
        exit();
    }
}

/**
 * Redirect if not technician
 */
function redirectIfNotTechnician() {
    if (!isTechnician()) {
        header('Location: /loaz_industries/index.php');
        exit();
    }
}

/**
 * Redirect if not user
 */
function redirectIfNotUser() {
    if (!isUser()) {
        header('Location: /loaz_industries/index.php');
        exit();
    }
}

// ============================================
// CSRF PROTECTION
// ============================================

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token field HTML
 * @return string
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Upload file with validation
 * @param array $file $_FILES array
 * @param string $targetDir Target directory
 * @param array $allowedTypes Allowed file extensions
 * @param int $maxSize Max file size in bytes
 * @return string|false Filename or false on error
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], $maxSize = 2097152) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Check file type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return false;
    }
    
    // Create directory if not exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Delete file
 * @param string $filePath
 * @return bool
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// ============================================
// STRING & DATA FUNCTIONS
// ============================================

/**
 * Generate random token
 * @param int $length Token length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Truncate string with ellipsis
 * @param string $string
 * @param int $length
 * @return string
 */
function truncateString($string, $length = 100) {
    if (strlen($string) <= $length) {
        return $string;
    }
    return substr($string, 0, $length) . '...';
}

/**
 * Sanitize input string
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (Indonesian format)
 * @param string $phone
 * @return bool
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,13}$/', $phone);
}

// ============================================
// DATE FUNCTIONS
// ============================================

/**
 * Format date to Indonesian format
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' detik yang lalu';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' menit yang lalu';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' jam yang lalu';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' hari yang lalu';
    } else {
        return date('d/m/Y', $time);
    }
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Send notification (placeholder - implement with actual service)
 * @param int $userId User ID
 * @param string $message Notification message
 * @param string $type Notification type (email, whatsapp, database)
 * @return bool
 */
function sendNotification($userId, $message, $type = 'database') {
    // Database notification
    if ($type == 'database') {
        global $pdo;
        // Uncomment if notifications table exists
        // $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        // return $stmt->execute([$userId, $message]);
        return true;
    }
    
    // Email notification (implement with PHPMailer)
    if ($type == 'email') {
        // TODO: Implement email sending
        return true;
    }
    
    // WhatsApp notification (implement with WhatsApp API)
    if ($type == 'whatsapp') {
        // TODO: Implement WhatsApp API
        return true;
    }
    
    return false;
}

// ============================================
// DATABASE HELPER FUNCTIONS
// ============================================

/**
 * Get single record by ID
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param int $id Record ID
 * @return array|false
 */
function getById($pdo, $table, $id) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get all records from table
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param string $orderBy Order by clause
 * @return array
 */
function getAll($pdo, $table, $orderBy = 'id DESC') {
    $stmt = $pdo->query("SELECT * FROM $table ORDER BY $orderBy");
    return $stmt->fetchAll();
}

/**
 * Get count of records
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param string $where Where clause
 * @param array $params Parameters for prepared statement
 * @return int
 */
function getCount($pdo, $table, $where = '', $params = []) {
    $sql = "SELECT COUNT(*) as total FROM $table";
    if ($where) {
        $sql .= " WHERE $where";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch()['total'];
}

// ============================================
// CART FUNCTIONS
// ============================================

/**
 * Get cart total items count
 * @return int
 */
function getCartCount() {
    if (!isset($_SESSION['cart'])) {
        return 0;
    }
    $count = 0;
    foreach ($_SESSION['cart'] as $qty) {
        $count += $qty;
    }
    return $count;
}

/**
 * Get cart total amount
 * @param PDO $pdo Database connection
 * @return float
 */
function getCartTotal($pdo) {
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return 0;
    }
    
    $total = 0;
    foreach ($_SESSION['cart'] as $id => $qty) {
        $stmt = $pdo->prepare("SELECT price FROM parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch();
        if ($part) {
            $total += $part['price'] * $qty;
        }
    }
    return $total;
}

// ============================================
// PAYMENT & FEE CALCULATION FUNCTIONS
// ============================================

/**
 * Calculate sliding fee percentage based on transaction amount
 * Sliding scale: 4% for ≤ Rp 1M, 15% for ≥ Rp 2M, linear interpolation between
 * @param float $amount Total transaction amount
 * @return float Fee percentage (e.g., 4.5)
 */
function calculateFeePercentage($amount) {
    $min_amount = 1000000;  // Rp 1 juta
    $max_amount = 2000000;  // Rp 2 juta
    $min_fee = 4;           // 4%
    $max_fee = 15;          // 15%
    
    if ($amount <= $min_amount) {
        return (float)$min_fee;
    } elseif ($amount >= $max_amount) {
        return (float)$max_fee;
    } else {
        // Linear interpolation
        $percentage = $min_fee + (($amount - $min_amount) / ($max_amount - $min_amount)) * ($max_fee - $min_fee);
        return round($percentage, 2);
    }
}

/**
 * Calculate fee amount and technician earning
 * @param float $amount Total transaction amount
 * @return array [fee_amount, technician_earning, fee_percentage]
 */
function calculateFeeBreakdown($amount) {
    $fee_percentage = calculateFeePercentage($amount);
    $fee_amount = $amount * ($fee_percentage / 100);
    $technician_earning = $amount - $fee_amount;
    
    return [
        'fee_percentage' => $fee_percentage,
        'fee_amount' => round($fee_amount, 2),
        'technician_earning' => round($technician_earning, 2)
    ];
}

/**
 * Format fee percentage for display
 * @param float $percentage Fee percentage
 * @return string Formatted percentage (e.g., "4.50%")
 */
function formatFeePercentage($percentage) {
    return number_format($percentage, 2) . '%';
}

/**
 * Create technician earning record
 * @param PDO $pdo Database connection
 * @param int $technician_id Technician database ID
 * @param int $transaction_id Transaction ID
 * @param float $amount Total amount
 * @param float $fee_percentage Fee percentage
 * @param float $fee_amount Fee amount
 * @param float $net_amount Net earning amount
 * @return bool Success status
 */
function createTechnicianEarning($pdo, $technician_id, $transaction_id, $amount, $fee_percentage, $fee_amount, $net_amount) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO technician_earnings (technician_id, transaction_id, amount, fee_percentage, fee_amount, net_amount, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$technician_id, $transaction_id, $amount, $fee_percentage, $fee_amount, $net_amount]);
    } catch (Exception $e) {
        error_log("Error creating technician earning: " . $e->getMessage());
        return false;
    }
}

/**
 * Get technician's total earnings with breakdown
 * @param PDO $pdo Database connection
 * @param int $technician_id Technician database ID
 * @param string $start_date Start date (YYYY-MM-DD)
 * @param string $end_date End date (YYYY-MM-DD)
 * @return array Earnings summary
 */
function getTechnicianEarningsSummary($pdo, $technician_id, $start_date = null, $end_date = null) {
    $query = "
        SELECT 
            SUM(amount) as total_amount,
            SUM(fee_amount) as total_fees,
            SUM(net_amount) as total_earnings,
            COUNT(*) as transaction_count,
            AVG(fee_percentage) as avg_fee_percentage
        FROM technician_earnings
        WHERE technician_id = ?
    ";
    
    $params = [$technician_id];
    
    if ($start_date && $end_date) {
        $query .= " AND DATE(created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return [
        'total_amount' => (float)($result['total_amount'] ?? 0),
        'total_fees' => (float)($result['total_fees'] ?? 0),
        'total_earnings' => (float)($result['total_earnings'] ?? 0),
        'transaction_count' => (int)($result['transaction_count'] ?? 0),
        'avg_fee_percentage' => (float)($result['avg_fee_percentage'] ?? 0)
    ];
}

// ============================================
// LOGGING FUNCTIONS
// ============================================

/**
 * Log activity to file
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 */
function logActivity($message, $level = 'info') {
    $logFile = __DIR__ . '/../logs/activity.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Log error
 * @param Exception $e Exception object
 */
function logError($e) {
    logActivity($e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine(), 'error');
}
?>