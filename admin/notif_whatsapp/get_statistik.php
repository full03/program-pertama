<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../koneksi.php';

header('Content-Type: application/json');

$totalQuery = "SELECT COUNT(*) as total FROM siswa";
$totalResult = mysqli_query($conek, $totalQuery);
$totalSiswa = $totalResult ? mysqli_fetch_assoc($totalResult)['total'] : 0;

$aktifQuery = "SELECT COUNT(*) as total FROM siswa WHERE notifikasi_wa = 1";
$aktifResult = mysqli_query($conek, $aktifQuery);
$totalAktif = $aktifResult ? mysqli_fetch_assoc($aktifResult)['total'] : 0;

echo json_encode([
    'success' => true,
    'total_siswa' => $totalSiswa,
    'total_aktif' => $totalAktif
]);

mysqli_close($conek);
?>