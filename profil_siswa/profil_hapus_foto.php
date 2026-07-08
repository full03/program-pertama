<?php
include "../koneksi.php";

$siswa_id = $_GET['id'] ?? null;

if (!$siswa_id) {
    die("ID siswa tidak ditemukan.");
}

// Ambil foto lama
$q = mysqli_query($conek, "SELECT foto FROM siswa WHERE id='$siswa_id'");
$data = mysqli_fetch_assoc($q);

$foto_lama = $data['foto'];

$folder = "foto_siswa/";

// Hapus foto di folder (jika ada dan bukan kosong)
if ($foto_lama && file_exists($folder . $foto_lama)) {
    unlink($folder . $foto_lama);
}

// Update database → jadikan foto kosong
mysqli_query($conek, "UPDATE siswa SET foto='' WHERE id='$siswa_id'");

// Redirect kembali ke profil
header("Location: profil_siswa.php?id=$siswa_id&msg=foto_dihapus");
exit;
?>
