<?php
session_start();
require_once 'koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

// Ambil data dari form
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$kelas = trim($_POST['kelas'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validasi
$errors = [];

if (empty($nama_lengkap)) {
    $errors[] = "Nama lengkap harus diisi!";
}

if (empty($username)) {
    $errors[] = "Username harus diisi!";
} elseif (strlen($username) < 3) {
    $errors[] = "Username minimal 3 karakter!";
}

if (empty($email)) {
    $errors[] = "Email harus diisi!";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Format email tidak valid!";
}

if (empty($kelas)) {
    $errors[] = "Kelas harus dipilih!";
}

if (empty($password)) {
    $errors[] = "Password harus diisi!";
} elseif (strlen($password) < 6) {
    $errors[] = "Password minimal 6 karakter!";
}

if ($password !== $confirm_password) {
    $errors[] = "Password dan konfirmasi password tidak cocok!";
}

// Cek apakah kelas yang dipilih valid (ada di database)
if (!empty($kelas)) {
    $check_kelas = "SELECT DISTINCT kelas FROM siswa WHERE kelas = ?";
    $stmt_kelas = $conek->prepare($check_kelas);
    $stmt_kelas->bind_param("s", $kelas);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    if ($result_kelas->num_rows === 0) {
        $errors[] = "Kelas yang dipilih tidak valid!";
    }
    $stmt_kelas->close();
}

if (!empty($errors)) {
    $error_msg = implode("\\n", $errors);
    header("Location: register.php?error=" . urlencode($error_msg));
    exit();
}

// ===== CEK APAKAH USER SUDAH ADA =====
// Cek di tabel admin
$check_admin = "SELECT id FROM admin WHERE username = ? OR email = ?";
$stmt_admin = $conek->prepare($check_admin);
$stmt_admin->bind_param("ss", $username, $email);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();

if ($result_admin->num_rows > 0) {
    $stmt_admin->close();
    header("Location: register.php?error=Username atau email sudah digunakan!");
    exit();
}
$stmt_admin->close();

// Cek di tabel wali_kelas
$check_wali = "SELECT id FROM wali_kelas WHERE username = ? OR email = ?";
$stmt_wali = $conek->prepare($check_wali);
$stmt_wali->bind_param("ss", $username, $email);
$stmt_wali->execute();
$result_wali = $stmt_wali->get_result();

if ($result_wali->num_rows > 0) {
    $stmt_wali->close();
    header("Location: register.php?error=Username atau email sudah digunakan!");
    exit();
}
$stmt_wali->close();

// ===== REGISTRASI =====
// Hash password untuk keamanan
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Enkripsi password untuk admin
$encryption_key = 'absensi-rfid-secret-key-2024';
$encrypted_password = openssl_encrypt($password, 'AES-256-CBC', $encryption_key, 0, '1234567890123456');

// Insert ke tabel wali_kelas dengan kelas yang dipilih
$sql = "INSERT INTO wali_kelas (username, email, password, nama_lengkap, kelas, encrypted_password, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = $conek->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $conek->error);
}

$stmt->bind_param("ssssss", $username, $email, $hashed_password, $nama_lengkap, $kelas, $encrypted_password);

if ($stmt->execute()) {
    $stmt->close();
    $conek->close();
    header("Location: login.php?success=registered&message=" . urlencode("Pendaftaran berhasil! Tunggu aktivasi dari admin."));
    exit();
} else {
    $error = $stmt->error;
    $stmt->close();
    $conek->close();
    header("Location: register.php?error=Registrasi gagal: " . urlencode($error));
    exit();
}
?>