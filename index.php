<?php
/**
 * Index Router - Entry Point of Loaz Industries
 * 
 * @package LoazIndustries
 * @version 1.0
 */

// ============================================
// KONFIGURASI & KONSTANTA
// ============================================

/**
 * Redirect ke homepage
 * Menggunakan header() dengan status 302 Found (temporary redirect)
 * 
 * CATATAN: Untuk production, pertimbangkan:
 * - Menggunakan status 301 (permanent redirect) jika homepage tidak berubah
 * - Menambahkan base URL dari konfigurasi untuk fleksibilitas
 */

// ============================================
// REDIRECT - OPTIMASI
// ============================================

// [OPTIMASI] Tambahkan pengecekan environment dan logging
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_log("Redirecting to homepage from index.php");
}

// [OPTIMASI] Gunakan absolute path untuk menghindari redirect issues
// Dan tambahkan status code yang sesuai
$redirect_url = 'homepage.php';

// [OPTIMASI] Cek apakah file tujuan ada sebelum redirect (opsional)
if (!file_exists(__DIR__ . '/' . $redirect_url)) {
    // Fallback jika homepage tidak ditemukan
    $redirect_url = 'homepage.php';
    // Log error untuk debugging
    error_log("Warning: homepage.php not found, using default redirect");
}

// Eksekusi redirect dengan status 302 (Found)
// Gunakan 301 (Moved Permanently) untuk production jika sudah stabil
header('Location: ' . $redirect_url, true, 302);
exit();

// ============================================
// ALTERNATIF: ROUTER MODE (jika diperlukan di masa depan)
// ============================================

/*
 * Jika di masa depan ingin mengimplementasikan router,
 * bisa menggunakan struktur seperti ini:
 * 
 * $routes = [
 *     '/' => 'homepage.php',
 *     '/about' => 'about.php',
 *     '/contact' => 'contact.php',
 *     // ... dll
 * ];
 * 
 * $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
 * $path = str_replace('/loaz_industries/', '', $path);
 * 
 * if (isset($routes[$path])) {
 *     require_once $routes[$path];
 * } else {
 *     require_once 'homepage.php';
 * }
 * 
 * exit();
 */
?>