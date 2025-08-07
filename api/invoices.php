<?php
// api/invoices.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->student_id) || !isset($data->description) || !isset($data->amount)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Student ID, deskripsi, dan jumlah tagihan wajib diisi.']);
    exit;
}

$studentId = (int)$data->student_id;
$description = htmlspecialchars(strip_tags($data->description));
$amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

if (empty($description) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Deskripsi dan jumlah tagihan tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO invoices (student_id, description, amount) VALUES (:student_id, :description, :amount)");
    $stmt->execute([
        ':student_id' => $studentId,
        ':description' => $description,
        ':amount' => $amount
    ]);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Tagihan baru berhasil ditambahkan.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan tagihan.', 'error_detail' => $e->getMessage()]);
}
