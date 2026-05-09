<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION)) {
    session_start();
}

$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';

if (empty($session_id)) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM support_chat 
        WHERE session_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$session_id]);
    $messages = $stmt->fetchAll();
    
    echo json_encode($messages);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>