<?php
session_start();
if (!isset($_SESSION['saya_admin'])) {
    header('location:./../../' . $_SESSION['akses']);
    exit();
}

require __DIR__ . '/../../../vendor/autoload.php';
include "../../koneksi/config.php";
include "../../koneksi/fungsi_indotgl.php";
include "../../koneksi/fungsi_waktu.php";
include "../../koneksi/pengaturan.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set judul
$sheet->setCellValue('A1', 'DATA HISTORI TRANSAKSI');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Koperasi ' . $nama_sekolah);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$sheet->setCellValue('A4', 'No');
$sheet->setCellValue('B4', 'Tanggal');
$sheet->setCellValue('C4', 'Nama');
$sheet->setCellValue('D4', 'Kelas');
$sheet->setCellValue('E4', 'Jenis');
$sheet->setCellValue('F4', 'Nominal');
$sheet->setCellValue('G4', 'Saldo');
$sheet->setCellValue('H4', 'Keterangan');

// Style header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF6600']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A4:H4')->applyFromArray($headerStyle);

// Data
$query = mysqli_query($con, "SELECT t.*, s.nama, s.kelas FROM transaksi t LEFT JOIN pengguna s ON t.id_anggota = s.id ORDER BY t.id_transaksi DESC");
$row = 5;
$no = 1;

while ($data = mysqli_fetch_array($query)) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, indonesian_date($data['tanggal']));
    $sheet->setCellValue('C' . $row, $data['nama']);
    $sheet->setCellValue('D' . $row, $data['kelas']);
    $sheet->setCellValue('E' . $row, $data['jenis']);
    $sheet->setCellValue('F' . $row, 'Rp ' . number_format($data['nominal'], 0, ',', '.'));
    $sheet->setCellValue('G' . $row, 'Rp ' . number_format($data['saldo'], 0, ',', '.'));
    $sheet->setCellValue('H' . $row, $data['keterangan']);
    
    // Border untuk setiap baris
    $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);
    
    $row++;
}

// Auto size columns
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Download
$filename = 'Transaksi_' . date('YmdHis') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
