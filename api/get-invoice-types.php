<?php
// File: api/get-invoice-types.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

$user = validate_jwt_from_request();

try {
    $stmt = $pdo->query("SELECT DISTINCT description FROM invoices ORDER BY description ASC");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($types);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil jenis tagihan.']);
}