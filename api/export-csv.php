<?php
// api/export-csv.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan-pembayaran.csv"');

$user = validate_jwt_from_request();

try {
    $stmt = $pdo->query("SELECT s.id, s.name, s.total_bill, COALESCE(SUM(ph.amount), 0) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id GROUP BY s.id, s.name, s.total_bill ORDER BY s.id");
    $students = $stmt->fetchAll();

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID Siswa', 'Nama', 'Total Tagihan', 'Total Terbayar', 'Sisa Tagihan', 'Status']);

    foreach ($students as $student) {
        $totalPaid = (float)$student['totalPaid'];
        $totalBill = (float)$student['total_bill'];
        $sisaTagihan = $totalBill - $totalPaid;
        $status = 'Belum Bayar';
        if ($totalPaid >= $totalBill) $status = 'Lunas';
        elseif ($totalPaid > 0) $status = 'Bayar Sebagian';
        fputcsv($output, [$student['id'], $student['name'], $totalBill, $totalPaid, $sisaTagihan, $status]);
    }
    fclose($output);
    exit;
} catch (Exception $e) {
    die('Gagal membuat laporan CSV.');
}
