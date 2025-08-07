<?php
// api/delete-invoice.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID tagihan tidak valid.']);
    exit;
}

try {
    // Juga hapus riwayat pembayaran yang terkait dengan tagihan ini
    $pdo->beginTransaction();
    $stmt_payments = $pdo->prepare("DELETE FROM payment_history WHERE invoice_id = :id");
    $stmt_payments->execute([':id' => $invoiceId]);

    $stmt_invoice = $pdo->prepare("DELETE FROM invoices WHERE id = :id");
    $stmt_invoice->execute([':id' => $invoiceId]);
    $pdo->commit();

    if ($stmt_invoice->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Tagihan berhasil dihapus.']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Tagihan tidak ditemukan.']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus tagihan.', 'error_detail' => $e->getMessage()]);
}
