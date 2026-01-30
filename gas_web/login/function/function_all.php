<?php

function get_total_all_records_masuk()
{
	include('../koneksi/db_auto.php');
	$statement = $connection->prepare("SELECT * FROM tabungan WHERE jenis = 'masuk'");
	$statement->execute();
	$result = $statement->fetchAll();
	return $statement->rowCount();
}

function get_total_all_records_keluar()
{
	include('../koneksi/db_auto.php');
	$statement = $connection->prepare("SELECT * FROM tabungan WHERE jenis = 'keluar'");
	$statement->execute();
	$result = $statement->fetchAll();
	return $statement->rowCount();
}

function get_total_all_records_pengguna()
{
	include('../koneksi/db_auto.php');
	$statement = $connection->prepare("SELECT * FROM pengguna WHERE status = 'aktif'");
	$statement->execute();
	$result = $statement->fetchAll();
	return $statement->rowCount();
}

function get_total_all_records_transaksi()
{
	include('../koneksi/db_auto.php');
	$statement = $connection->prepare("SELECT * FROM tabungan");
	$statement->execute();
	$result = $statement->fetchAll();
	return $statement->rowCount();
}

function get_total_all_records_transfer()
{
	include('../koneksi/db_auto.php');
	$table = null;
	$candidates = ['transfer', 't_transfer'];
	foreach ($candidates as $candidate) {
		$check = $connection->prepare('SHOW TABLES LIKE ?');
		$check->execute([$candidate]);
		if ($check->fetchColumn()) {
			$table = $candidate;
			break;
		}
	}
	if ($table === null) {
		return 0;
	}
	$statement = $connection->query("SELECT COUNT(*) FROM `{$table}`");
	return (int)$statement->fetchColumn();
}
