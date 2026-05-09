<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set typing status
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $is_typing = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;
    
    if ($service_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE services 
            SET is_typing = ?, last_typing_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$is_typing, $service_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid service ID']);
    }
} else {
    // Get typing status
    $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    
    if ($service_id > 0) {
        $stmt = $pdo->prepare("
            SELECT is_typing, last_typing_at 
            FROM services 
            WHERE id = ?
        ");
        $stmt->execute([$service_id]);
        $result = $stmt->fetch();
        
        $is_typing = false;
        if ($result && $result['is_typing'] == 1) {
            // Check if typing status is still valid (within 3 seconds)
            $last_typing = strtotime($result['last_typing_at']);
            if (time() - $last_typing < 3) {
                $is_typing = true;
            } else {
                // Auto reset if expired
                $stmt = $pdo->prepare("UPDATE services SET is_typing = 0 WHERE id = ?");
                $stmt->execute([$service_id]);
            }
        }
        
        echo json_encode(['is_typing' => $is_typing]);
    } else {
        echo json_encode(['is_typing' => false]);
    }
}
?>