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
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jumlah tidak valid']);
        exit();
    }
    
    // Check stock
    $stmt = $pdo->prepare("SELECT stock, name FROM parts WHERE id = ?");
    $stmt->execute([$part_id]);
    $part = $stmt->fetch();
    
    if (!$part) {
        echo json_encode(['success' => false, 'message' => 'Part tidak ditemukan']);
        exit();
    }
    
    $current_cart_qty = isset($_SESSION['cart'][$part_id]) ? $_SESSION['cart'][$part_id] : 0;
    $new_qty = $current_cart_qty + $quantity;
    
    if ($new_qty > $part['stock']) {
        echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi! Stok tersisa ' . $part['stock'] . ' unit']);
        exit();
    }
    
    $_SESSION['cart'][$part_id] = $new_qty;
    
    echo json_encode([
        'success' => true, 
        'message' => $part['name'] . ' berhasil ditambahkan ke keranjang',
        'cart_count' => array_sum($_SESSION['cart'])
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>