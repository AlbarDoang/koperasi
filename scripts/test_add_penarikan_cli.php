<?php
// simulate a POST request
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['id_anggota' => '97', 'jumlah' => 1000, 'id_petugas' => 1, 'keterangan' => 'test CLI pending'];
include __DIR__ . '/../gas_web/flutter_api/add_penarikan.php';
