<?php
// api/dashboard.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

try {
    $query = "SELECT (SELECT SUM(total_bill) FROM students) as totalBill, (SELECT SUM(amount) FROM payment_history) as totalPaid";
    $stmt = $pdo->query($query);
    $data = $stmt->fetch();
    $totalTagihan = (float)($data['totalBill'] ?? 0);
    $totalTerbayar = (float)($data['totalPaid'] ?? 0);

    http_response_code(200);
    echo json_encode([
        'totalTagihan' => $totalTagihan,
        'totalTerbayar' => $totalTerbayar,
        'totalSisa' => $totalTagihan - $totalTerbayar
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data dashboard.']);
}
