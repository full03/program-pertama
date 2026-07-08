<?php
session_start();
require_once 'koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php?error=method");
    exit();
}

// Ambil data dari form
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

// Validasi input
if (empty($username) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

$user = null;
$role = '';

// ===== CEK DI TABEL ADMIN =====
$sql_admin = "SELECT id, username, nama_lengkap, email, foto_profil, password, remember_token, status FROM admin WHERE username = ? OR email = ?";
$stmt_admin = $conek->prepare($sql_admin);

if ($stmt_admin) {
    $stmt_admin->bind_param("ss", $username, $username);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();

    if ($result_admin->num_rows === 1) {
        $user = $result_admin->fetch_assoc();
        $role = 'admin';
    }
    $stmt_admin->close();
}

// ===== CEK DI TABEL WALI_KELAS =====
if ($user === null) {
    $sql_wali = "SELECT id, username, nama_lengkap, email, foto_profil, password, remember_token, status, kelas FROM wali_kelas WHERE username = ? OR email = ?";
    $stmt_wali = $conek->prepare($sql_wali);

    if ($stmt_wali) {
        $stmt_wali->bind_param("ss", $username, $username);
        $stmt_wali->execute();
        $result_wali = $stmt_wali->get_result();

        if ($result_wali->num_rows === 1) {
            $user = $result_wali->fetch_assoc();
            $role = 'wali_kelas';
        }
        $stmt_wali->close();
    }
}

// ===== PROSES LOGIN =====
if ($user !== null) {
    
    // Cek status akun
    if (isset($user['status']) && $user['status'] === 'inactive') {
        $conek->close();
        header("Location: login.php?error=inactive");
        exit();
    }
    
    if (isset($user['status']) && $user['status'] === 'pending') {
        $conek->close();
        header("Location: login.php?error=pending");
        exit();
    }
    
    // Verifikasi password
    if (password_verify($password, $user['password'])) {
        
        // ===== SET SESSION =====
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;
        $_SESSION['nama_lengkap'] = !empty($user['nama_lengkap']) ? $user['nama_lengkap'] : $user['username'];
        $_SESSION['email'] = $user['email'] ?? '';
        
        // ===== KHUSUS UNTUK WALI KELAS: SIMPAN KELAS =====
        if ($role === 'wali_kelas') {
            $_SESSION['kelas'] = $user['kelas'] ?? ''; // Simpan kelas ke session
        }
        
        // ===== SIMPAN PASSWORD ASLI DI SESSION (UNTUK ADMIN) =====
        $_SESSION['plain_password'] = $password;
        
        // ===== SIMPAN PASSWORD SEMUA USER UNTUK ADMIN =====
        if (!isset($_SESSION['all_passwords'])) {
            $_SESSION['all_passwords'] = [];
        }
        
        // Simpan password user yang sedang login
        $_SESSION['all_passwords'][$user['id']] = $password;
        
        // Set foto profil
        if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])) {
            $_SESSION['foto_profil'] = $user['foto_profil'];
        } else {
            $_SESSION['foto_profil'] = 'images/default-user.png';
        }
        
        // Tentukan tabel untuk update remember_token
        $table = ($role === 'admin') ? 'admin' : 'wali_kelas';
        
        // Jika remember me dicentang
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + 86400 * 30; // 30 hari
            
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
            setcookie('remember_user', $user['id'], $expiry, '/', '', false, true);
            setcookie('remember_role', $role, $expiry, '/', '', false, true);
            
            $update_token = "UPDATE $table SET remember_token = ? WHERE id = ?";
            $stmt_token = $conek->prepare($update_token);
            if ($stmt_token) {
                $stmt_token->bind_param("si", $token, $user['id']);
                $stmt_token->execute();
                $stmt_token->close();
            }
        } else {
            // Hapus remember token
            $update_token = "UPDATE $table SET remember_token = NULL WHERE id = ?";
            $stmt_token = $conek->prepare($update_token);
            if ($stmt_token) {
                $stmt_token->bind_param("i", $user['id']);
                $stmt_token->execute();
                $stmt_token->close();
            }
        }
        
        $conek->close();
        
        // ===== REDIRECT BERDASARKAN ROLE =====
        if ($role === 'admin') {
            header("Location: admin/admin_dashboard.php?login=success");
            exit();
        } elseif ($role === 'wali_kelas') {
            // Cek apakah kelas tersimpan
            if (empty($_SESSION['kelas'])) {
                // Jika tidak ada kelas, redirect ke halaman error atau tetap ke dashboard
                // Tapi tetap izinkan akses dengan pesan peringatan
                $_SESSION['warning_message'] = "Data kelas belum terisi! Silahkan hubungi admin.";
            }
            header("Location: wali_kelas/wali_kelas_dashboard.php?login=success");
            exit();
        } else {
            header("Location: login.php?error=invalid_role");
            exit();
        }
    } else {
        // Password salah
        $conek->close();
        header("Location: login.php?error=invalid");
        exit();
    }
} else {
    // Username tidak ditemukan
    $conek->close();
    header("Location: login.php?error=not_found");
    exit();
}
?>