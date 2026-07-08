<?php
include "../koneksi.php";

$id  = $_GET['id'];
$ket = $_GET['ket'];

mysqli_query($conek, "
	UPDATE absensi_siswa 
	SET keterangan='$ket' 
	WHERE id='$id'
");

header("Location: rekab_absensi_siswa.php");
?>