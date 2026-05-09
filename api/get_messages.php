<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

if ($service_id <= 0) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT cm.*, u.name as sender_name
        FROM chat_messages cm
        JOIN users u ON cm.from_user_id = u.id
        WHERE cm.service_id = ?
        ORDER BY cm.created_at ASC
    ");
    $stmt->execute([$service_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read for current user
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE service_id = ? AND to_user_id = ? AND is_read = 0
    ");
    $stmt->execute([$service_id, $user_id]);
    
    echo json_encode($messages);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>