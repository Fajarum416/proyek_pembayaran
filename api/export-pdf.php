<?php
// api/export-pdf.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/fpdf/fpdf.php'; // Pastikan path ini benar

// Validasi token dari URL
$user = validate_jwt_from_request();

// Ambil status filter dan nilai ambang batas dari URL
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';
$tahap1_threshold = isset($_GET['tahap1']) ? (float)$_GET['tahap1'] : 0;
$tahap2_threshold = isset($_GET['tahap2']) ? (float)$_GET['tahap2'] : 0;

class PDF_Report extends FPDF
{
    private $summaryData = [];

    public function setSummaryData($data)
    {
        $this->summaryData = $data;
    }

    function Header()
    {
        $logoPath = __DIR__ . '/../img/logo_lembaga.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 25);
        }

        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'Laporan Pembayaran Siswa', 0, 0, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Ln(6);
        $this->Cell(80);
        $this->Cell(30, 10, 'LPK YAMAGUCHI INDONESIA', 0, 0, 'C');
        $this->Ln(5);
        $this->Cell(80);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(30, 10, 'Jl. Contoh Alamat No. 123, Kota Anda', 0, 0, 'C');

        $this->Line(10, 40, 200, 40);
        $this->SetY(45);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Laporan ini dibuat secara otomatis oleh sistem.', 0, 0, 'L');
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    function SummaryBox()
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, 'Ringkasan Keuangan (Berdasarkan Filter)', 0, 1);

        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0);
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.3);

        $this->Cell(63, 10, 'Total Akumulasi Tagihan', 1, 0, 'L', true);
        $this->Cell(0, 10, 'Rp ' . number_format($this->summaryData['totalBill'], 0, ',', '.'), 1, 1, 'R');
        $this->Cell(63, 10, 'Total Pembayaran Diterima', 1, 0, 'L', true);
        $this->Cell(0, 10, 'Rp ' . number_format($this->summaryData['totalPaid'], 0, ',', '.'), 1, 1, 'R');
        $this->Cell(63, 10, 'Total Sisa Tagihan', 1, 0, 'L', true);
        $this->Cell(0, 10, 'Rp ' . number_format($this->summaryData['totalSisa'], 0, ',', '.'), 1, 1, 'R');
    }

    function FancyTable($header, $data)
    {
        $this->SetFillColor(4, 42, 122);
        $this->SetTextColor(255);
        $this->SetDrawColor(180, 180, 180);
        $this->SetFont('Arial', 'B', 9);
        $w = array(10, 55, 35, 35, 35, 20);
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0);
        $this->SetFont('');
        $fill = false;
        $no = 1;
        foreach ($data as $row) {
            $sisa = $row['totalBill'] - $row['totalPaid'];
            $this->Cell($w[0], 7, $no++, 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 7, $row['name'], 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 7, 'Rp ' . number_format($row['totalBill']), 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 7, 'Rp ' . number_format($row['totalPaid']), 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 7, 'Rp ' . number_format($sisa > 0 ? $sisa : 0), 'LR', 0, 'R', $fill);
            $this->Cell($w[5], 7, $row['status'], 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

try {
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

    $finalQuery = $baseQuery . ' ' . $havingClause . ' ORDER BY s.name';

    $stmtStudents = $pdo->prepare($finalQuery);
    $stmtStudents->execute($queryParams);
    $studentsData = $stmtStudents->fetchAll();

    $summaryTotalBill = 0;
    $summaryTotalPaid = 0;
    $dataForPdf = [];

    foreach ($studentsData as $student) {
        $totalPaid = (float)$student['totalPaid'];
        $totalBill = (float)$student['totalBill'];
        $summaryTotalBill += $totalBill;
        $summaryTotalPaid += $totalPaid;

        // --- Logika Status Baru ---
        $statusText = 'Belum Bayar';
        if ($totalPaid >= $totalBill && $totalBill > 0) {
            $statusText = 'Lunas Penuh';
        } elseif ($tahap2_threshold > 0 && $totalPaid >= $tahap2_threshold) {
            $statusText = 'Tahap 2';
        } elseif ($tahap1_threshold > 0 && $totalPaid >= $tahap1_threshold) {
            $statusText = 'Tahap 1';
        } elseif ($totalPaid > 0) {
            $statusText = 'Sebagian';
        }

        $dataForPdf[] = [
            'name' => $student['name'],
            'totalBill' => $totalBill,
            'totalPaid' => $totalPaid,
            'status' => $statusText
        ];
    }

    $summary = [
        'totalBill' => $summaryTotalBill,
        'totalPaid' => $summaryTotalPaid,
        'totalSisa' => $summaryTotalBill - $summaryTotalPaid
    ];

    // --- PEMBUATAN PDF ---
    $pdf = new PDF_Report();
    $pdf->AliasNbPages();
    $pdf->setSummaryData($summary);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Tanggal Laporan: ' . date('d F Y'), 0, 1);
    $pdf->Ln(5);

    $pdf->SummaryBox();
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Detail Pembayaran per Siswa (Filter: ' . ucfirst(str_replace('_', ' ', $statusFilter)) . ')', 0, 1);
    $header = ['No', 'Nama Siswa', 'Tagihan', 'Terbayar', 'Sisa', 'Status'];
    $pdf->FancyTable($header, $dataForPdf);

    $pdf->Output('D', 'Laporan_Pembayaran_' . ucfirst($statusFilter) . '_' . date('Y-m-d') . '.pdf');
    exit();
} catch (Exception $e) {
    die("Gagal membuat PDF: " . $e->getMessage());
}
