<?php
// api/update-invoice.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));
$invoiceId = isset($data->id) ? (int)$data->id : 0;

if ($invoiceId <= 0 || !isset($data->description) || !isset($data->amount)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tagihan tidak lengkap.']);
    exit;
}

$description = htmlspecialchars(strip_tags($data->description));
$amount = filter_var($data->amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

try {
    $stmt = $pdo->prepare("UPDATE invoices SET description = :description, amount = :amount WHERE id = :id");
    $stmt->execute([
        ':description' => $description,
        ':amount' => $amount,
        ':id' => $invoiceId
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Tagihan berhasil diperbarui.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui tagihan.', 'error_detail' => $e->getMessage()]);
}
