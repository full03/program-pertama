<?php
include "../koneksi.php";

$id = $_GET['id']; // id riwayat

// ambil guru_id supaya bisa kembali ke profil guru
$get = mysqli_query($conek, "SELECT siswa_id FROM riwayat_siswa WHERE id='$id'");
$data = mysqli_fetch_assoc($get);
$siswa_id = $data['siswa_id'];

$sql = mysqli_query($conek, "DELETE FROM riwayat_siswa WHERE id='$id'");

if ($sql) {
    header("Location: profil_siswa.php?id=$siswa_id&msg=hapus_sukses");
} else {
    echo "Gagal menghapus riwayat.";
}
