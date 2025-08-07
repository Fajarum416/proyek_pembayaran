<?php
// File: api/export-report.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/fpdf/fpdf.php';

// Validasi token dari URL
$user = validate_jwt_from_request();

// Ambil parameter dari URL
$reportType = $_GET['report_type'] ?? 'rekap';
$invoiceDescriptions = $_GET['invoice_descriptions'] ?? []; // Sekarang menjadi array

// Fungsi helper untuk format mata uang
function format_currency($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

class PDF_Report extends FPDF
{
    // Header halaman
    function Header()
    {
        // Logo (jika ada, pastikan path benar)
        // $this->Image('../img/logo.png', 10, 6, 30);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Laporan Pembayaran Siswa', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'LPK YAMAGUCHI INDONESIA', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 6, 'Dicetak pada: ' . date('d F Y'), 0, 1, 'C');
        $this->Ln(10);
    }

    // Footer halaman
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    // Ambil semua data yang relevan dari database
    $students_stmt = $pdo->query("SELECT id, name FROM students ORDER BY name ASC");
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

    $invoices_stmt = $pdo->query("SELECT id, student_id, description, amount FROM invoices");
    $invoices_data = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

    $payments_stmt = $pdo->query("SELECT invoice_id, amount FROM payment_history");
    $payments_data = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Olah data menjadi struktur yang mudah digunakan
    $paymentsByInvoice = [];
    foreach ($payments_data as $p) {
        $invoice_id = $p['invoice_id'];
        if (!isset($paymentsByInvoice[$invoice_id])) {
            $paymentsByInvoice[$invoice_id] = 0;
        }
        $paymentsByInvoice[$invoice_id] += $p['amount'];
    }

    $invoicesByStudent = [];
    foreach ($invoices_data as $inv) {
        $inv['paid'] = $paymentsByInvoice[$inv['id']] ?? 0;
        $invoicesByStudent[$inv['student_id']][] = $inv;
    }

    // Inisialisasi PDF
    $pdf = new PDF_Report('L', 'mm', 'A4'); // L untuk Lanskap
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // --- LOGIKA PEMBUATAN LAPORAN ---

    if ($reportType === 'rekap') {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Rekapitulasi Global per Siswa', 0, 1);

        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(10, 7, 'No', 1, 0, 'C', true);
        $pdf->Cell(80, 7, 'Nama Siswa', 1, 0, 'L', true);
        $pdf->Cell(45, 7, 'Total Tagihan', 1, 0, 'R', true);
        $pdf->Cell(45, 7, 'Total Terbayar', 1, 0, 'R', true);
        $pdf->Cell(45, 7, 'Sisa Tagihan', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 10);
        $no = 1;
        foreach ($students as $studentId => $studentArray) {
            $student = $studentArray[0];
            $totalBill = 0;
            $totalPaid = 0;
            if (isset($invoicesByStudent[$studentId])) {
                foreach ($invoicesByStudent[$studentId] as $inv) {
                    $totalBill += $inv['amount'];
                    $totalPaid += $inv['paid'];
                }
            }
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(80, 7, $student['name'], 1, 0, 'L');
            $pdf->Cell(45, 7, format_currency($totalBill), 1, 0, 'R');
            $pdf->Cell(45, 7, format_currency($totalPaid), 1, 0, 'R');
            $pdf->Cell(45, 7, format_currency($totalBill - $totalPaid), 1, 1, 'R');
        }
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $title = 'Laporan Detail - ';
        if (count($invoiceDescriptions) === 1) {
            $title .= $invoiceDescriptions[0];
        } else {
            $title .= 'Tagihan Terpilih';
        }
        $pdf->Cell(0, 10, $title, 0, 1);

        $invoiceTypesToDisplay = $invoiceDescriptions;

        // Kalkulasi lebar dinamis
        $pageWidth = $pdf->GetPageWidth();
        $margin = 10;
        $fixedColsWidth = 10 + 50; // Lebar kolom No + Nama Siswa
        $availableWidth = $pageWidth - (2 * $margin) - $fixedColsWidth;

        $numInvoiceTypes = count($invoiceTypesToDisplay);
        if ($numInvoiceTypes > 0) {
            $numDynamicCols = ($numInvoiceTypes * 3) + 3;
            $colWidth = $availableWidth / $numDynamicCols;
        } else {
            $colWidth = $availableWidth / 3; // Fallback for Grand Total only
        }

        // --- PERBAIKAN LOGIKA PEMBUATAN HEADER ---
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('Arial', 'B', 8);

        // Simpan posisi Y awal
        $yPos1 = $pdf->GetY();

        // Header baris pertama (No dan Nama)
        $pdf->Cell(10, 14, 'No', 1, 0, 'C', true);
        $pdf->Cell(50, 14, 'Nama Siswa', 1, 0, 'C', true);

        // Simpan posisi X setelah kolom tetap
        $xPos = $pdf->GetX();

        // Header baris pertama (Jenis Tagihan dan Grand Total)
        foreach ($invoiceTypesToDisplay as $type) {
            $pdf->SetXY($xPos, $yPos1);
            $pdf->MultiCell($colWidth * 3, 7, $type, 1, 'C', true);
            $xPos += ($colWidth * 3);
        }
        $pdf->SetXY($xPos, $yPos1);
        $pdf->MultiCell($colWidth * 3, 7, 'Grand Total', 1, 'C', true);

        // Set posisi untuk baris kedua header
        $pdf->SetXY(20 + 50, $yPos1 + 7);

        // Header baris kedua (Sub-kolom)
        foreach ($invoiceTypesToDisplay as $type) {
            $pdf->Cell($colWidth, 7, 'Tagihan', 1, 0, 'C', true);
            $pdf->Cell($colWidth, 7, 'Terbayar', 1, 0, 'C', true);
            $pdf->Cell($colWidth, 7, 'Sisa', 1, 0, 'C', true);
        }
        $pdf->Cell($colWidth, 7, 'Tagihan', 1, 0, 'C', true);
        $pdf->Cell($colWidth, 7, 'Terbayar', 1, 0, 'C', true);
        $pdf->Cell($colWidth, 7, 'Sisa', 1, 1, 'C', true); // Pindah baris di akhir

        // Body Tabel
        $pdf->SetFont('Arial', '', 7);
        $no = 1;
        foreach ($students as $studentId => $studentArray) {
            $student = $studentArray[0];
            $pdf->Cell(10, 7, $no++, 1, 0, 'C');
            $pdf->Cell(50, 7, $student['name'], 1, 0, 'L');

            $grandTotalBill = 0;
            $grandTotalPaid = 0;

            $studentInvoices = [];
            if (isset($invoicesByStudent[$studentId])) {
                foreach ($invoicesByStudent[$studentId] as $inv) {
                    $studentInvoices[$inv['description']] = $inv;
                }
            }

            foreach ($invoiceTypesToDisplay as $type) {
                $bill = $studentInvoices[$type]['amount'] ?? 0;
                $paid = $studentInvoices[$type]['paid'] ?? 0;
                $grandTotalBill += $bill;
                $grandTotalPaid += $paid;

                $pdf->Cell($colWidth, 7, $bill > 0 ? format_currency($bill) : '-', 1, 0, 'R');
                $pdf->Cell($colWidth, 7, $paid > 0 ? format_currency($paid) : '-', 1, 0, 'R');
                $pdf->Cell($colWidth, 7, $bill > 0 ? format_currency($bill - $paid) : '-', 1, 0, 'R');
            }

            $pdf->Cell($colWidth, 7, format_currency($grandTotalBill), 1, 0, 'R');
            $pdf->Cell($colWidth, 7, format_currency($grandTotalPaid), 1, 0, 'R');
            $pdf->Cell($colWidth, 7, format_currency($grandTotalBill - $grandTotalPaid), 1, 1, 'R');
        }
    }

    $pdf->Output('D', 'Laporan_Pembayaran_' . date('Y-m-d') . '.pdf');
} catch (Exception $e) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Error', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 10, 'Gagal membuat laporan: ' . $e->getMessage());
    $pdf->Output('D', 'Error_Laporan.pdf');
}