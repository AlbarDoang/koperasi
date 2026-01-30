<?php

$db_name = "koperasi_gas";
$db_server = "localhost";
$db_user = "root";
$db_pass = "";

$db = new PDO("mysql:host={$db_server};dbname={$db_name};charset=utf8", $db_user, $db_pass);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = $_POST['username'];



$data = $db->prepare("SELECT * FROM transaksi WHERE id_tabungan='$username' ORDER BY id_transaksi DESC ");

$data->execute();
$result = $data->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);

