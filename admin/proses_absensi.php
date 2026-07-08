<?php
// File: proses_absensi.php
// Digunakan untuk memproses absensi siswa

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal']);
    exit();
}

// Ambil data dari POST
$nokartu = isset($_POST['nokartu']) ? trim($_POST['nokartu']) : '';

if (empty($nokartu)) {
    echo json_encode(['status' => 'error', 'message' => 'Nomor kartu tidak ditemukan']);
    exit();
}

// Cek apakah kartu terdaftar
$query = "SELECT * FROM siswa WHERE nokartu = ?";
$stmt = $conek->prepare($query);
$stmt->bind_param("s", $nokartu);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Kartu tidak terdaftar']);
    exit();
}

$siswa = $result->fetch_assoc();
$stmt->close();

// Cek apakah sudah absen hari ini
$tanggal = date('Y-m-d');
$query = "SELECT * FROM absensi_siswa WHERE nokartu = ? AND tanggal = ?";
$stmt = $conek->prepare($query);
$stmt->bind_param("ss", $nokartu, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $absen = $result->fetch_assoc();
    $stmt->close();
    
    // Cek apakah sudah pulang
    if (!empty($absen['jam_pulang'])) {
        echo json_encode(['status' => 'error', 'message' => 'Anda sudah pulang hari ini']);
        exit();
    }
    
    // Update jam pulang
    $jam_pulang = date('H:i:s');
    $query = "UPDATE absensi_siswa SET jam_pulang = ? WHERE id = ?";
    $stmt = $conek->prepare($query);
    $stmt->bind_param("si", $jam_pulang, $absen['id']);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'status' => 'success',
            'message' => 'Selamat pulang!',
            'data' => [
                'nama_siswa' => $siswa['nama_siswa'],
                'keterangan' => 'Pulang'
            ]
        ]);
    } else {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Gagal update absensi']);
    }
    exit();
}

// Set waktu
$jam_masuk = date('H:i:s');
$jam_batas_absen = "07:30:00";
$jam_batas_izin = "10:00:00";
$keterangan = 'Hadir';

// Tentukan keterangan berdasarkan jam
if ($jam_masuk > $jam_batas_absen && $jam_masuk <= $jam_batas_izin) {
    $keterangan = 'Terlambat';
} elseif ($jam_masuk > $jam_batas_izin) {
    $keterangan = 'Alfa';
}

// Insert absensi
$query = "INSERT INTO absensi_siswa (nokartu, tanggal, jam_masuk_pertama, keterangan) VALUES (?, ?, ?, ?)";
$stmt = $conek->prepare($query);
$stmt->bind_param("ssss", $nokartu, $tanggal, $jam_masuk, $keterangan);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'status' => 'success',
        'message' => 'Absensi berhasil!',
        'data' => [
            'nama_siswa' => $siswa['nama_siswa'],
            'keterangan' => $keterangan
        ]
    ]);
} else {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan absensi']);
}

$conek->close();
?>