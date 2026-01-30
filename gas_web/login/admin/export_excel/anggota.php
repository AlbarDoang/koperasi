<?php
session_start();
if (!isset($_SESSION['saya_admin'])) {
    header('location:./../../' . $_SESSION['akses']);
    exit();
}

require __DIR__ . '/../../../vendor/autoload.php';
include "../../koneksi/config.php";
include "../../koneksi/fungsi_indotgl.php";
include "../../koneksi/pengaturan.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set judul
$sheet->setCellValue('A1', 'DATA ANGGOTA KOPERASI');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Koperasi ' . $nama_sekolah);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$sheet->setCellValue('A4', 'No');
$sheet->setCellValue('B4', 'ID Tabungan');
$sheet->setCellValue('C4', 'Nama');
$sheet->setCellValue('D4', 'Email');
$sheet->setCellValue('E4', 'No HP');
$sheet->setCellValue('F4', 'Saldo');
$sheet->setCellValue('G4', 'Tanggal Daftar');

// Style header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF6600']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

// Data
$query = mysqli_query($con, "SELECT * FROM pengguna WHERE role='siswa' ORDER BY id DESC");
$row = 5;
$no = 1;

while ($data = mysqli_fetch_array($query)) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $data['id_tabungan']);
    $sheet->setCellValue('C' . $row, $data['nama']);
    $sheet->setCellValue('D' . $row, $data['email']);
    $sheet->setCellValue('E' . $row, $data['no_wa']);
    $sheet->setCellValue('F' . $row, 'Rp ' . number_format($data['saldo'], 0, ',', '.'));
    $sheet->setCellValue('G' . $row, indonesian_date($data['tanggal_buat']));
    
    // Border untuk setiap baris
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    
    $row++;
}

// Auto size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Download
$filename = 'Data_Anggota_' . date('YmdHis') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
