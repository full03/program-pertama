<?php
include "../koneksi.php";

// Ambil ID siswa dari form
$siswa_id = $_POST['siswa_id'] ?? $_GET['id'] ?? null;

if (!$siswa_id) {
    die("ID Siswa tidak ditemukan.");
}

// Pastikan folder ada
$folder = "foto_siswa/";
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

// Cek file dikirim
if (!isset($_FILES['foto'])) {
    die("Foto tidak dikirim.");
}

$foto = $_FILES['foto'];
$ext  = pathinfo($foto['name'], PATHINFO_EXTENSION);

// Nama file yang BENAR
$nama_file = "siswa_" . $siswa_id . "_" . time() . "." . $ext;
$target_path = $folder . $nama_file;

// Upload file
if (move_uploaded_file($foto['tmp_name'], $target_path)) {

    // Simpan ke database
    mysqli_query($conek, "UPDATE siswa SET foto='$nama_file' WHERE id='$siswa_id'");

    header("Location: profil_siswa.php?id=$siswa_id&msg=foto_sukses");
    exit;

} else {
    echo "Gagal mengupload foto.";
}
?>
