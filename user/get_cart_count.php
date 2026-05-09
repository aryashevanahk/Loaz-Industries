<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$count = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $count += $qty;
    }
}

echo json_encode(['count' => $count]);
?>