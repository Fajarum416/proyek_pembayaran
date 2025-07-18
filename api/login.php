<?php
// api/login.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    // Tambahkan metode yang diterima ke dalam pesan error untuk debugging
    $received_method = $_SERVER['REQUEST_METHOD'];
    echo json_encode([
        'status' => 'error',
        'message' => 'Metode request tidak diizinkan. Seharusnya POST, tetapi diterima: ' . $received_method
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->username) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username dan password diperlukan.']);
    exit;
}

$username = htmlspecialchars(strip_tags($data->username));
$password = $data->password;

try {
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Username atau password salah.']);
        exit;
    }

    $jwt = generate_jwt(['id' => $user['id'], 'username' => $user['username']]);
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Login berhasil.', 'token' => $jwt]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.']);
}
