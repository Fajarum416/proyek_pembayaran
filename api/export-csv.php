<?php
// api/export-csv.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

// Validasi token yang dikirim melalui URL
$user = validate_jwt_from_request();

// Ambil status filter dari URL
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';

// Set header agar browser mengunduh file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan_pembayaran_' . $statusFilter . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID Siswa', 'Nama', 'Total Tagihan', 'Total Terbayar', 'Sisa Tagihan', 'Status']);

try {
    // --- Bangun Query Berdasarkan Filter ---
    $baseQuery = "SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid 
                  FROM students s 
                  LEFT JOIN payment_history ph ON s.id = ph.student_id 
                  GROUP BY s.id, s.name, s.total_bill";

    $havingClause = '';
    if ($statusFilter === 'lunas') {
        $havingClause = 'HAVING totalPaid >= totalBill';
    } elseif ($statusFilter === 'sebagian') {
        $havingClause = 'HAVING totalPaid > 0 AND totalPaid < totalBill';
    } elseif ($statusFilter === 'belum') {
        $havingClause = 'HAVING totalPaid = 0 OR totalPaid IS NULL';
    }

    $finalQuery = $baseQuery . ' ' . $havingClause . ' ORDER BY s.id';

    $stmt = $pdo->query($finalQuery);
    $students = $stmt->fetchAll();

    foreach ($students as $student) {
        $totalBill = (float)$student['totalBill'];
        $totalPaid = (float)$student['totalPaid'];
        $sisaTagihan = $totalBill - $totalPaid;

        $status = 'Belum Bayar';
        if ($totalPaid >= $totalBill) {
            $status = 'Lunas';
        } elseif ($totalPaid > 0) {
            $status = 'Bayar Sebagian';
        }

        fputcsv($output, [
            $student['id'],
            $student['name'],
            $totalBill,
            $totalPaid,
            $sisaTagihan > 0 ? $sisaTagihan : 0,
            $status
        ]);
    }
} catch (Exception $e) {
    fputcsv($output, ['Error mengambil data: ' . $e->getMessage()]);
}

fclose($output);
exit();
