<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$technician_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($technician_id <= 0) {
    echo json_encode(['error' => 'Invalid technician ID']);
    exit();
}

try {
    // Get reviews for this technician
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as customer_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.technician_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$technician_id]);
    $reviews = $stmt->fetchAll();
    
    // Get average rating
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews
        WHERE technician_id = ?
    ");
    $stmt->execute([$technician_id]);
    $stats = $stmt->fetch();
    
    echo json_encode([
        'reviews' => $reviews,
        'avg_rating' => round($stats['avg_rating'] ?? 0, 1),
        'total_reviews' => (int)($stats['total_reviews'] ?? 0)
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_technician_reviews.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error', 'reviews' => [], 'avg_rating' => 0, 'total_reviews' => 0]);
}
?>