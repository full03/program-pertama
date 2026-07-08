<?php
session_start();
require_once '../koneksi.php';

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Belum login']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

if ($user_id <= 0 || empty($user_role)) {
    echo json_encode(['success' => false, 'message' => 'Data user tidak valid']);
    exit();
}

// Ambil data dari POST
$nama = $_POST['nama'] ?? '';
$email = $_POST['email'] ?? '';
$username = $_POST['username'] ?? '';
$foto = $_FILES['foto'] ?? null;

// Validasi
if (empty($nama)) {
    echo json_encode(['success' => false, 'message' => 'Nama lengkap harus diisi']);
    exit();
}

// Tentukan tabel
$table = ($user_role === 'admin') ? 'admin' : 'wali_kelas';

// Siapkan query update
$updateFields = [];
$params = [];
$types = '';

// Update nama
if (!empty($nama)) {
    $updateFields[] = "nama_lengkap = ?";
    $params[] = $nama;
    $types .= 's';
}

// Update email
if (!empty($email)) {
    $updateFields[] = "email = ?";
    $params[] = $email;
    $types .= 's';
}

// Update username
if (!empty($username)) {
    $updateFields[] = "username = ?";
    $params[] = $username;
    $types .= 's';
}

// Handle upload foto
if ($foto && $foto['error'] === 0) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($foto['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Format foto tidak didukung. Gunakan JPG, PNG, atau GIF']);
        exit();
    }
    
    if ($foto['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 2MB']);
        exit();
    }
    
    // Buat direktori jika belum ada
    $uploadDir = '../profil_admin/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate nama file unik
    $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($foto['tmp_name'], $filepath)) {
        // Hapus foto lama jika ada
        $queryFoto = "SELECT foto_profil FROM $table WHERE id = ?";
        $stmtFoto = $conek->prepare($queryFoto);
        $stmtFoto->bind_param("i", $user_id);
        $stmtFoto->execute();
        $resultFoto = $stmtFoto->get_result();
        if ($rowFoto = $resultFoto->fetch_assoc()) {
            if (!empty($rowFoto['foto_profil']) && file_exists($rowFoto['foto_profil']) && $rowFoto['foto_profil'] !== '../images/default-user.png') {
                unlink($rowFoto['foto_profil']);
            }
        }
        $stmtFoto->close();
        
        $updateFields[] = "foto_profil = ?";
        $params[] = $filepath;
        $types .= 's';
        
        // Update session
        $_SESSION['foto_profil'] = $filepath;
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal upload foto']);
        exit();
    }
}

// Jika tidak ada field yang diupdate
if (empty($updateFields)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada data yang diubah']);
    exit();
}

// Build query
$query = "UPDATE $table SET " . implode(", ", $updateFields) . " WHERE id = ?";
$params[] = $user_id;
$types .= 'i';

// Execute
$stmt = $conek->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        // Update session
        $_SESSION['nama_lengkap'] = $nama;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $username;
        
        echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update database: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Error prepare statement: ' . $conek->error]);
}

$conek->close();
?>