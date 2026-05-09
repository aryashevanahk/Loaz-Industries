<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$session_id = isset($_POST['session_id']) ? $_POST['session_id'] : '';
$message = trim($_POST['message']);
$admin_id = $_SESSION['user_id'];

if (empty($session_id)) {
    echo json_encode(['error' => 'Invalid session']);
    exit();
}

if (empty($message)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO support_chat (session_id, admin_id, message, sender_type, created_at) 
        VALUES (?, ?, ?, 'admin', NOW())
    ");
    $result = $stmt->execute([$session_id, $admin_id, $message]);
    
    echo json_encode(['success' => $result]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>