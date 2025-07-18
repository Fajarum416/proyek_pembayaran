<?php
// api/update-student.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID siswa tidak valid.']);
    exit;
}

$totalBill = isset($_POST['totalBill']) ? filter_var($_POST['totalBill'], FILTER_SANITIZE_NUMBER_INT) : null;
$amountToAdd = isset($_POST['amountToAdd']) ? filter_var($_POST['amountToAdd'], FILTER_SANITIZE_NUMBER_INT) : 0;

try {
    $pdo->beginTransaction();

    if ($totalBill !== null && $totalBill !== '') {
        $stmt = $pdo->prepare("UPDATE students SET total_bill = :total_bill WHERE id = :id");
        $stmt->execute([':total_bill' => $totalBill, ':id' => $studentId]);
    }

    if ($amountToAdd > 0) {
        $proofPath = null;
        if (isset($_FILES['proof']) && $_FILES['proof']['error'] == 0) {
            $uploadDir = __DIR__ . '/../uploads/';
            $fileName = time() . '-' . basename($_FILES['proof']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) {
                $proofPath = '/uploads/' . $fileName;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO payment_history (student_id, payment_date, amount, proof_image_url) VALUES (:sid, :pdate, :amount, :proof)");
        $stmt->execute([':sid' => $studentId, ':pdate' => date('Y-m-d'), ':amount' => $amountToAdd, ':proof' => $proofPath]);
    }

    $pdo->commit();
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Data siswa berhasil diupdate.']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate data siswa.']);
}
