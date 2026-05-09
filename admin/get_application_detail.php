<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

$id = (int)$_GET['id'];

try {
    // Get application details with reviewer name
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as reviewer_name 
        FROM technician_applications a 
        LEFT JOIN users u ON a.reviewed_by = u.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $application
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>