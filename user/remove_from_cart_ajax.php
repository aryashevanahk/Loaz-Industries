<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_id = $_POST['part_id'];
    
    if (isset($_SESSION['cart'][$part_id])) {
        unset($_SESSION['cart'][$part_id]);
        echo json_encode([
            'success' => true, 
            'message' => 'Item berhasil dihapus dari keranjang',
            'cart_count' => array_sum($_SESSION['cart'])
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan di keranjang']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>