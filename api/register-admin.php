<?php
// api/register-admin.php

// Jalankan file ini sekali saja dari browser untuk membuat admin pertama
// Contoh: http://localhost/proyek_pembayaran/api/register-admin.php

require_once __DIR__ . '/db.php';

// --- UBAH DETAIL DI BAWAH INI ---
$username = 'admin';
$password = 'ymiid123';
// --------------------------------

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        die("<h1>Error: Pengguna '{$username}' sudah ada.</h1>");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
    $stmt->execute([':username' => $username, ':password' => $hashedPassword]);

    echo "<h1>Sukses!</h1>";
    echo "<p>Pengguna admin berhasil dibuat.</p>";
    echo "<p><b>Username:</b> {$username}</p>";
    echo "<p><b>Password:</b> {$password}</p>";
    echo "<p style='color:red;'><b>PENTING:</b> Hapus file 'register-admin.php' ini dari server Anda sekarang!</p>";
} catch (Exception $e) {
    http_response_code(500);
    die("<h1>Error!</h1><p>Gagal membuat pengguna admin: " . $e->getMessage() . "</p>");
}
