<?php
$host = 'localhost';
$dbname = 'loaz_industries';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus fungsi-fungsi di bawah ini karena sudah ada di functions.php
// Cukup panggil functions.php di file yang membutuhkan

?>