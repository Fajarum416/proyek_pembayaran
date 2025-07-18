<?php
// api/export-csv.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

// Validasi token yang dikirim melalui URL
$user = validate_jwt_from_request();

// Set header agar browser mengunduh file, bukan menampilkannya
header('Content-Type: text/csv; charset=utf-g');
header('Content-Disposition: attachment; filename="laporan_pembayaran_siswa.csv"');

// Buka output stream PHP untuk menulis file CSV
$output = fopen('php://output', 'w');

// Tulis baris header untuk CSV
fputcsv($output, ['ID Siswa', 'Nama', 'Total Tagihan', 'Total Terbayar', 'Sisa Tagihan', 'Status']);

try {
    // Query untuk mengambil semua data siswa beserta total pembayarannya
    $query = "SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid 
              FROM students s 
              LEFT JOIN payment_history ph ON s.id = ph.student_id 
              GROUP BY s.id, s.name, s.total_bill 
              ORDER BY s.id";
    $stmt = $pdo->query($query);
    $students = $stmt->fetchAll();

    foreach ($students as $student) {
        $totalBill = (float)$student['totalBill'];
        $totalPaid = (float)$student['totalPaid'];
        $sisaTagihan = $totalBill - $totalPaid;

        // Menentukan status pembayaran
        $status = 'Belum Bayar';
        if ($totalPaid >= $totalBill) {
            $status = 'Lunas';
        } elseif ($totalPaid > 0) {
            $status = 'Bayar Sebagian';
        }

        // Tulis baris data ke file CSV
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
    // Jika terjadi error, kita bisa menulis pesan error di file CSV
    fputcsv($output, ['Error mengambil data: ' . $e->getMessage()]);
}

fclose($output);
exit();
