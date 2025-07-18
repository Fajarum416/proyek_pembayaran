<?php
// api/export-pdf.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../fpdf/fpdf.php';

$user = validate_jwt_from_request();

try {
    $stmt = $pdo->query("SELECT s.id, s.name, s.total_bill, COALESCE(SUM(ph.amount), 0) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id GROUP BY s.id, s.name, s.total_bill ORDER BY s.id");
    $data = $stmt->fetchAll();

    class PDF extends FPDF
    {
        function Header()
        {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'LAPORAN PEMBAYARAN SISWA', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, 'Nama Institusi/Sekolah Anda', 0, 1, 'C');
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, 'Tanggal Cetak: ' . date('d F Y'), 0, 1, 'C');
            $this->Ln(10);
        }
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(10, 10, 'No', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Nama Siswa', 1, 0, 'C', true);
    $pdf->Cell(45, 10, 'Total Tagihan', 1, 0, 'C', true);
    $pdf->Cell(45, 10, 'Total Bayar', 1, 0, 'C', true);
    $pdf->Cell(45, 10, 'Sisa Tagihan', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Status', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $no = 1;
    foreach ($data as $row) {
        $totalPaid = (float)$row['totalPaid'];
        $totalBill = (float)$row['total_bill'];
        $sisaTagihan = $totalBill - $totalPaid;

        $status = 'Belum Bayar';
        $rowColor = [254, 242, 242];
        if ($totalPaid >= $totalBill) {
            $status = 'Lunas';
            $rowColor = [255, 255, 255];
        } elseif ($totalPaid > 0) {
            $status = 'Bayar Sebagian';
            $rowColor = [255, 251, 235];
        }
        $pdf->SetFillColor($rowColor[0], $rowColor[1], $rowColor[2]);

        $pdf->Cell(10, 10, $no++, 1, 0, 'C', true);
        $pdf->Cell(60, 10, $row['name'], 1, 0, 'L', true);
        $pdf->Cell(45, 10, 'Rp ' . number_format($totalBill, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(45, 10, 'Rp ' . number_format($totalPaid, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(45, 10, 'Rp ' . number_format($sisaTagihan, 0, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(30, 10, $status, 1, 1, 'C', true);
    }

    $pdf->Output('D', 'laporan-pembayaran.pdf');
    exit;
} catch (Exception $e) {
    http_response_code(500);
    die('Gagal membuat laporan PDF.');
}
