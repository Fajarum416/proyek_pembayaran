<?php
// api/delete-history.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($transactionId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID transaksi tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT proof_image_url FROM payment_history WHERE transaction_id = :id");
    $stmt->execute([':id' => $transactionId]);
    $history = $stmt->fetch();

    if ($history && !empty($history['proof_image_url'])) {
        $filePath = __DIR__ . '/..' . $history['proof_image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM payment_history WHERE transaction_id = :id");
    $stmt->execute([':id' => $transactionId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Riwayat pembayaran berhasil dihapus.']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Riwayat pembayaran tidak ditemukan.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus riwayat pembayaran.']);
}
