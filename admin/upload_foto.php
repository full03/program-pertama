<?php
session_start();
require '../koneksi.php';

if (isset($_FILES['profile'])) {

    $folder = "../profile/"; // samakan dengan yang dipakai di menu
    $namaFile = $_FILES['profile']['name'];
    $tmp      = $_FILES['profile']['tmp_name'];

    // Buat nama file unik
    $ext      = pathinfo($namaFile, PATHINFO_EXTENSION);
    $namaBaru = time() . "_" . $_SESSION['user_id'] . "." . $ext;

    // Pindahkan file
    move_uploaded_file($tmp, $folder . $namaBaru);

    // Simpan ke database
    $id = $_SESSION['user_id'];
    mysqli_query($conek, "UPDATE users SET profile='$namaBaru' WHERE id='$id'");

    // Update session supaya langsung berubah
    $_SESSION['admin_foto'] = $namaBaru;

    header("Location: admin_dashboard.php");
    exit;
}
?>