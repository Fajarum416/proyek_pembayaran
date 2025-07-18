<?php
// api/db.php

// --- Ganti detail ini dengan kredensial database hosting Anda ---
$host = 'localhost';
$dbname = 'pembayaran_db';
$user = 'root';
$pass = ''; // Pastikan password ini 100% benar
// ----------------------------------------------------------------

// Atur header default untuk semua respons API
header("Content-Type: application/json");

try {
    // Membuat objek koneksi database menggunakan PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);

    // Mengatur atribut PDO untuk penanganan error yang lebih baik
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mengatur mode pengambilan data default menjadi array asosiatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika koneksi gagal, kirim respons error dalam format JSON yang jelas
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Koneksi ke database gagal. Silakan periksa kredensial di db.php.',
        // 'error_detail' => $e->getMessage() // Hapus komentar ini untuk melihat detail error teknis saat debugging
    ]);
    // Hentikan eksekusi skrip agar tidak ada error lain yang muncul
    die();
}

// Catatan: Variabel $pdo sekarang tersedia untuk file PHP lain yang menyertakan file ini.