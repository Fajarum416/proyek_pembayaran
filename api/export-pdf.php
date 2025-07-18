<?php
// api/export-pdf.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/fpdf/fpdf.php'; // Pastikan path ini benar

// Validasi token dari URL
$user = validate_jwt_from_request();

class PDF extends FPDF
{
    // Header halaman
    function Header()
    {
        // Logo atau Judul
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Laporan Pembayaran Siswa', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'LPK YAMAGUCHI INDONESIA', 0, 1, 'C');
        $this->Ln(10);
    }

    // Footer halaman
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Tabel data
    function CreateTable($header, $data)
    {
        // Lebar kolom
        $w = array(40, 35, 35, 35, 35);
        $this->SetFont('Arial', 'B', 10);
        // Header
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        }
        $this->Ln();
        // Data
        $this->SetFont('Arial', '', 10);
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row['name'], 'LR');
            $this->Cell($w[1], 6, 'Rp ' . number_format($row['totalBill']), 'LR', 0, 'R');
            $this->Cell($w[2], 6, 'Rp ' . number_format($row['totalPaid']), 'LR', 0, 'R');
            $sisa = $row['totalBill'] - $row['totalPaid'];
            $this->Cell($w[3], 6, 'Rp ' . number_format($sisa > 0 ? $sisa : 0), 'LR', 0, 'R');
            $this->Cell($w[4], 6, $row['status'], 'LR', 0, 'C');
            $this->Ln();
        }
        // Garis penutup
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

try {
    $stmt = $pdo->query("SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id GROUP BY s.id ORDER BY s.name");
    $studentsData = $stmt->fetchAll();

    $dataForPdf = [];
    foreach ($studentsData as $student) {
        $status = 'Belum Bayar';
        if ($student['totalPaid'] >= $student['totalBill']) $status = 'Lunas';
        elseif ($student['totalPaid'] > 0) $status = 'Bayar Sebagian';

        $dataForPdf[] = [
            'name' => $student['name'],
            'totalBill' => $student['totalBill'],
            'totalPaid' => $student['totalPaid'],
            'status' => $status
        ];
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $header = ['Nama Siswa', 'Total Tagihan', 'Total Terbayar', 'Sisa Tagihan', 'Status'];
    $pdf->CreateTable($header, $dataForPdf);
    $pdf->Output('D', 'laporan_pembayaran.pdf'); // 'D' untuk download
    exit();
} catch (Exception $e) {
    die("Gagal membuat PDF: " . $e->getMessage());
}
