<?php
// api/delete-payment.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paymentId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID pembayaran tidak valid.']);
    exit;
}

try {
    // Ambil path gambar bukti untuk dihapus dari server
    $stmt = $pdo->prepare("SELECT proof_image_url FROM payment_history WHERE transaction_id = :id");
    $stmt->execute([':id' => $paymentId]);
    $payment = $stmt->fetch();

    if ($payment && !empty($payment['proof_image_url'])) {
        $filePath = __DIR__ . '/..' . $payment['proof_image_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Hapus record dari database
    $stmt = $pdo->prepare("DELETE FROM payment_history WHERE transaction_id = :id");
    $stmt->execute([':id' => $paymentId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Riwayat pembayaran berhasil dihapus.']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Riwayat pembayaran tidak ditemukan.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus riwayat pembayaran.', 'error_detail' => $e->getMessage()]);
}