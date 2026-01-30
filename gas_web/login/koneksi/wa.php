<?php 
/**
 * WhatsApp Blast Configuration
 * Menggunakan database terpusat dari /config/database.php
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Variabel $con dan $koneksi sudah tersedia dari config
if (!$con){
	echo 'Tidak dapat terhubung ke database';
}
	
	$sql = $con->query("SELECT * FROM wa_blast");
        while($row = $sql->fetch_assoc()){

    $no 		= $row['no'];
    $link 	    = $row['link'];
    $api_key	= $row['api_key'];
    $no_server	= $row['no_server'];
    $linkweb	= $row['linkweb'];
 } ?>