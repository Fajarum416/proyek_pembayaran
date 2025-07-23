<?php
// Header wajib untuk memberitahu bahwa outputnya adalah JSON
header('Content-Type: application/json');

// --- EDIT BAGIAN INI SESUAI DENGAN KONFIGURASI DATABASE ANDA ---
$db_host = 'localhost';      // Biasanya 'localhost'
$db_name = 'u500054717_pembayaran_ymi';   // Ganti dengan nama database Anda
$db_user = 'u500054717_Yamaguchidata';  // Ganti dengan username database Anda
$db_pass = 'Ymiid123';  // Ganti dengan password database Anda
// -------------------------------------------------------------

// Membuat koneksi ke database menggunakan PDO (cara yang lebih aman)
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    // Set error mode ke exception agar kita tahu jika ada masalah
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Jika koneksi gagal, kirim pesan error dalam format JSON
    http_response_code(500); // Server Error
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $e->getMessage()]);
    die(); // Hentikan eksekusi skrip
}

// Contoh query: mengambil semua data dari tabel 'users'.
// Ganti 'users' dengan nama tabel yang ingin Anda ambil datanya.
$sql = "SELECT * FROM users LIMIT 10"; // Kita batasi 10 data untuk awal

try {
    $statement = $pdo->prepare($sql);
    $statement->execute();

    // Mengambil semua baris data sebagai array asosiatif
    $results = $statement->fetchAll(PDO::FETCH_ASSOC);

    // Mengembalikan data yang berhasil diambil dalam format JSON
    echo json_encode([
        'status' => 'sukses',
        'data' => $results
    ]);
} catch (PDOException $e) {
    // Jika query gagal, kirim pesan error
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Query gagal: ' . $e->getMessage()]);
}
