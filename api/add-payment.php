<?php
// api/add-payment.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

// Ambil data dari form-data
$studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$amount = isset($_POST['amount']) ? filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;

if ($studentId <= 0 || $invoiceId <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap atau tidak valid.']);
    exit;
}

try {
    $proofPath = null;
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = time() . '-' . basename($_FILES['proof']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) {
            $proofPath = '/uploads/' . $fileName;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO payment_history (student_id, invoice_id, payment_date, amount, proof_image_url) VALUES (:sid, :iid, :pdate, :amount, :proof)");
    $stmt->execute([
        ':sid' => $studentId,
        ':iid' => $invoiceId,
        ':pdate' => date('Y-m-d'),
        ':amount' => $amount,
        ':proof' => $proofPath
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Pembayaran berhasil ditambahkan.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan pembayaran.', 'error_detail' => $e->getMessage()]);
}
