<?php
// api/export-pdf.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/fpdf/fpdf.php'; // Pastikan path ini benar

// Validasi token dari URL
$user = validate_jwt_from_request();

// --- KELAS PDF KUSTOM DENGAN DESAIN BARU ---
class PDF_Report extends FPDF
{
    // Properti untuk menyimpan data ringkasan
    private $summaryData = [];

    public function setSummaryData($data)
    {
        $this->summaryData = $data;
    }

    // Header Halaman (Kop Surat)
    function Header()
    {
        // Cek jika file logo ada
        $logoPath = __DIR__ . '/../img/logo_lembaga.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 25);
        }

        // Set Font
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'LAPORAN PEMBAYARAN SISWA', 0, 0, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Ln(6);
        $this->Cell(80);
        $this->Cell(30, 10, 'LPK YAMAGUCHI INDONESIA', 0, 0, 'C');
        $this->Ln(5);
        $this->Cell(80);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(30, 10, 'Jl. Contoh Alamat No. 123, Kota Anda', 0, 0, 'C');

        // Garis bawah
        $this->Line(10, 40, 200, 40);

        // --- PERBAIKAN ---
        // Atur posisi kursor (Y) secara eksplisit ke 45mm dari atas halaman.
        // Ini memastikan konten utama dimulai di bawah garis header.
        $this->SetY(45);
    }

    // Footer Halaman
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Laporan ini dibuat secara otomatis oleh sistem.', 0, 0, 'L');
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    // Kotak Ringkasan Data
    function SummaryBox()
    {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, 'Ringkasan Keuangan', 0, 1);

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

    // Tabel Data yang Didesain
    function FancyTable($header, $data)
    {
        // Warna
        $this->SetFillColor(4, 42, 122); // Biru Tua
        $this->SetTextColor(255);
        $this->SetDrawColor(180, 180, 180);
        $this->SetFont('Arial', 'B', 9);

        // Lebar Kolom
        $w = array(10, 55, 35, 35, 35, 20);

        // Header
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        // Restore warna dan font
        $this->SetFillColor(245, 245, 245); // Abu-abu muda untuk zebra
        $this->SetTextColor(0);
        $this->SetFont('');

        // Data
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
        // Garis penutup
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

try {
    // Ambil data ringkasan
    $stmtSummary = $pdo->query("SELECT (SELECT SUM(total_bill) FROM students) as totalBill, (SELECT SUM(amount) FROM payment_history) as totalPaid");
    $summary = $stmtSummary->fetch();
    $summary['totalSisa'] = ($summary['totalBill'] ?? 0) - ($summary['totalPaid'] ?? 0);

    // Ambil data detail siswa
    $stmtStudents = $pdo->query("SELECT s.id, s.name, s.total_bill AS totalBill, SUM(IFNULL(ph.amount, 0)) as totalPaid FROM students s LEFT JOIN payment_history ph ON s.id = ph.student_id GROUP BY s.id ORDER BY s.name");
    $studentsData = $stmtStudents->fetchAll();

    $dataForPdf = [];
    foreach ($studentsData as $student) {
        $status = 'Belum';
        if ($student['totalPaid'] >= $student['totalBill']) $status = 'Lunas';
        elseif ($student['totalPaid'] > 0) $status = 'Sebagian';

        $dataForPdf[] = [
            'name' => $student['name'],
            'totalBill' => $student['totalBill'],
            'totalPaid' => $student['totalPaid'],
            'status' => $status
        ];
    }

    // --- PEMBUATAN PDF ---
    $pdf = new PDF_Report();
    $pdf->AliasNbPages();
    $pdf->setSummaryData($summary); // Kirim data ringkasan ke kelas PDF
    $pdf->AddPage();

    // Teks Tanggal Laporan (sekarang akan berada di posisi Y=45)
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Tanggal Laporan: ' . date('d F Y'), 0, 1);
    $pdf->Ln(5);

    // Tampilkan kotak ringkasan
    $pdf->SummaryBox();

    // --- PERBAIKAN ---
    // Tambahkan spasi vertikal antara kotak ringkasan dan judul tabel
    $pdf->Ln(5);

    // Tampilkan tabel detail
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 10, 'Detail Pembayaran', 0, 1);
    $header = ['No', 'Nama Siswa', 'Tagihan', 'Terbayar', 'Sisa', 'Status'];
    $pdf->FancyTable($header, $dataForPdf);

    $pdf->Output('D', 'Laporan_Pembayaran_Siswa_' . date('Y-m-d') . '.pdf');
    exit();
} catch (Exception $e) {
    die("Gagal membuat PDF: " . $e->getMessage());
}
