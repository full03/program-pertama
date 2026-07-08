<?php
include "../koneksi.php";

$nokartu = $_GET['nokartu'] ?? "";

// kosongkan buffer
mysqli_query($conek,"DELETE FROM tmprfid_siswa");

// simpan kartu
$simpan = mysqli_query($conek,
    "INSERT INTO tmprfid_siswa (nokartu) VALUES ('$nokartu')");

if($simpan){
    echo "SENT";
}else{
    echo "FAILED";
}
?>
