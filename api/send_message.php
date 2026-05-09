<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$from_user_id = $_SESSION['user_id'];
$to_user_id = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit();
}

if ($to_user_id <= 0 || $service_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (from_user_id, to_user_id, service_id, message, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$from_user_id, $to_user_id, $service_id, $message]);
    
    echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>