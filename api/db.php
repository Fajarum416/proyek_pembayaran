<?php
// api/db.php

// --- START CORS & PREFLIGHT HANDLING ---
// Baris ini mengizinkan skrip dari domain mana pun untuk mengakses API Anda.
// Untuk production, sebaiknya ganti '*' dengan domain frontend Anda, contoh: 'http://website-anda.com'
header("Access-Control-Allow-Origin: *");

// Baris ini memberitahu browser header apa saja yang diizinkan dalam permintaan.
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Baris ini memberitahu browser metode HTTP apa saja yang diizinkan.
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");

// Bagian ini khusus untuk menangani pre-flight request dari browser.
// Jika metode yang masuk adalah OPTIONS, kita kirim status OK dan hentikan skrip.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // 204 No Content
    exit();
}
// --- END CORS & PREFLIGHT HANDLING ---


$host = 'localhost';
$dbname = 'u500054717_pembayaran_ymi';
$user = 'u500054717_Yamaguchidata';
$pass = 'Ymiid123';

// Header ini tetap diperlukan untuk respons API utama Anda.
header("Content-Type: application/json");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi ke database gagal.']);
    die();
}
