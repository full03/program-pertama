<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . ($conek->connect_error ?? 'Unknown error')]);
    exit();
}

// Ambil parameter
$table = $_GET['table'] ?? '';
$id = intval($_GET['id'] ?? 0);

// Validasi
if ($id <= 0 || ($table !== 'admin' && $table !== 'wali_kelas')) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Ambil data
$query = "SELECT id, username, nama_lengkap, email, status, created_at FROM $table WHERE id = ?";
$stmt = $conek->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conek->error]);
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Data not found']);
    $stmt->close();
    exit();
}

$data = $result->fetch_assoc();
$stmt->close();

// ===== AMBIL PASSWORD DARI SESSION =====
// Cek di session terlebih dahulu
if (isset($_SESSION['all_passwords']) && isset($_SESSION['all_passwords'][$id])) {
    // Password tersimpan di session
    $data['password'] = $_SESSION['all_passwords'][$id];
    $data['password_note'] = '🔓 Password user ini (dari session)';
} elseif ($id == $_SESSION['user_id']) {
    // Jika user sedang login (diri sendiri)
    $data['password'] = $_SESSION['plain_password'] ?? '••••••••';
    $data['password_note'] = '🔓 Password Anda sendiri';
} else {
    // Untuk user lain yang passwordnya tidak ada di session
    $data['password'] = '•••••••• (Belum pernah login)';
    $data['password_note'] = '⚠️ Password hanya tersedia jika user pernah login. Atau tambahkan kolom encrypted_password di database.';
}

// Kirim response JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $data]);
?>