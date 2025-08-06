<?php
// api/export-csv.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

// Validasi token yang dikirim melalui URL
$user = validate_jwt_from_request();

// Ambil status filter dari URL
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';
// Ambil nilai ambang batas dari parameter GET
$tahap1_threshold = isset($_GET['tahap1']) ? (float)$_GET['tahap1'] : 0;
$tahap2_threshold = isset($_GET['tahap2']) ? (float)$_GET['tahap2'] : 0;

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
    $queryParams = [];

    switch ($statusFilter) {
        case 'lunas':
            $havingClause = 'HAVING totalPaid >= totalBill';
            break;
        case 'lunas_tahap_2':
            if ($tahap2_threshold > 0) {
                $havingClause = 'HAVING totalPaid >= :tahap2 AND totalPaid < totalBill';
                $queryParams[':tahap2'] = $tahap2_threshold;
            }
            break;
        case 'lunas_tahap_1':
            if ($tahap1_threshold > 0) {
                $limitAtas = ($tahap2_threshold > $tahap1_threshold) ? ':tahap2_limit' : 'totalBill';
                $havingClause = 'HAVING totalPaid >= :tahap1 AND totalPaid < ' . $limitAtas;
                $queryParams[':tahap1'] = $tahap1_threshold;
                if ($limitAtas == ':tahap2_limit') {
                    $queryParams[':tahap2_limit'] = $tahap2_threshold;
                }
            }
            break;
        case 'sebagian':
            $limitBawah = ($tahap1_threshold > 0) ? ':tahap1_limit' : '0';
            if ($limitBawah != '0') {
                $havingClause = 'HAVING totalPaid > 0 AND totalPaid < ' . $limitBawah;
                $queryParams[':tahap1_limit'] = $tahap1_threshold;
            } else {
                $havingClause = 'HAVING totalPaid > 0';
            }
            break;
        case 'belum':
            $havingClause = 'HAVING totalPaid = 0 OR totalPaid IS NULL';
            break;
    }

    $finalQuery = $baseQuery . ' ' . $havingClause . ' ORDER BY s.id';

    $stmt = $pdo->prepare($finalQuery);
    $stmt->execute($queryParams);
    $students = $stmt->fetchAll();

    foreach ($students as $student) {
        $totalBill = (float)$student['totalBill'];
        $totalPaid = (float)$student['totalPaid'];
        $sisaTagihan = $totalBill - $totalPaid;

        // --- Logika Status Baru ---
        $statusText = 'Belum Bayar';
        if ($totalPaid >= $totalBill && $totalBill > 0) {
            $statusText = 'Lunas Penuh';
        } elseif ($tahap2_threshold > 0 && $totalPaid >= $tahap2_threshold) {
            $statusText = 'Lunas Tahap 2';
        } elseif ($tahap1_threshold > 0 && $totalPaid >= $tahap1_threshold) {
            $statusText = 'Lunas Tahap 1';
        } elseif ($totalPaid > 0) {
            $statusText = 'Bayar Sebagian';
        }

        fputcsv($output, [
            $student['id'],
            $student['name'],
            $totalBill,
            $totalPaid,
            $sisaTagihan > 0 ? $sisaTagihan : 0,
            $statusText
        ]);
    }
} catch (Exception $e) {
    fputcsv($output, ['Error mengambil data: ' . $e->getMessage()]);
}

fclose($output);
exit();
