<?php  
    //urutan = server, userdb, passdb, namadb
    $conek = mysqli_connect("localhost", "root", "", "absensi");

    if (mysqli_connect_errno()) {
    echo "Koneksi database gagal: " . mysqli_connect_error();
    }
?>