<?php
// ===== PERBAIKAN: Cek status session =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Koneksi database
require_once '../../koneksi.php';

if (!isset($conek) || $conek->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Set header JSON
header('Content-Type: application/json');

// Ambil action
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'aktifkan_semua') {
    // ===== PERBAIKAN 1: Cek dulu berapa yang akan diupdate =====
    $checkQuery = "SELECT COUNT(*) as total FROM siswa WHERE notifikasi_wa = 0";
    $checkResult = mysqli_query($conek, $checkQuery);
    $totalInactive = 0;
    
    if ($checkResult) {
        $row = mysqli_fetch_assoc($checkResult);
        $totalInactive = intval($row['total']);
    }
    
    // ===== PERBAIKAN 2: Update hanya yang belum aktif =====
    $query = "UPDATE siswa SET notifikasi_wa = 1 WHERE notifikasi_wa = 0";
    
    if (mysqli_query($conek, $query)) {
        $affected_rows = mysqli_affected_rows($conek);
        
        // ===== PERBAIKAN 3: Validasi jumlah yang terupdate =====
        if ($affected_rows > 0) {
            $message = "Berhasil mengaktifkan $affected_rows siswa";
        } else {
            $message = "Semua siswa sudah aktif";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'updated' => $affected_rows,
            'total_inactive_before' => $totalInactive
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengupdate database: ' . mysqli_error($conek)
        ]);
    }
    exit();
}

// ===== UPDATE STATUS NOTIFIKASI PER SISWA =====
if (isset($_POST['nokartu']) && isset($_POST['status'])) {
    $nokartu = $_POST['nokartu'];
    $status = intval($_POST['status']);
    
    if (empty($nokartu) || $status < 0 || $status > 1) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        exit();
    }
    
    // Update status notifikasi
    $query = "UPDATE siswa SET notifikasi_wa = ? WHERE nokartu = ?";
    $stmt = mysqli_prepare($conek, $query);
    mysqli_stmt_bind_param($stmt, "is", $status, $nokartu);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Status notifikasi berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update: ' . mysqli_error($conek)]);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// ===== UPDATE NOMOR WHATSAPP =====
if (isset($_POST['nomor_whatsapp']) && isset($_POST['nokartu'])) {
    $nomor = $_POST['nomor_whatsapp'];
    $nokartu = $_POST['nokartu'];
    
    if (empty($nokartu) || empty($nomor)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        exit();
    }
    
    // Validasi nomor
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (!preg_match('/^(62|08)/', $nomor)) {
        echo json_encode(['success' => false, 'message' => 'Nomor harus dimulai dengan 62 atau 08']);
        exit();
    }
    
    $query = "UPDATE siswa SET no_hp = ? WHERE nokartu = ?";
    $stmt = mysqli_prepare($conek, $query);
    mysqli_stmt_bind_param($stmt, "ss", $nomor, $nokartu);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Nomor WhatsApp berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update: ' . mysqli_error($conek)]);
    }
    mysqli_stmt_close($stmt);
    exit();
}

// ===== PERBAIKAN 4: Jika tidak ada action yang dikenali =====
echo json_encode([
    'success' => false,
    'message' => 'Aksi tidak dikenal'
]);

mysqli_close($conek);
?>