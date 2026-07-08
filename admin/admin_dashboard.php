<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] !== 'admin') {
    header("Location: menu.php");
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// ===== PROSES HAPUS =====
$delete_error = '';
$delete_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_action']) && $_POST['delete_action'] === 'confirm_delete') {
    $table = $_POST['delete_table'] ?? '';
    $id = intval($_POST['delete_id'] ?? 0);
    $name = $_POST['delete_name'] ?? '';
    
    if ($id > 0 && !empty($table)) {
        if ($table === 'admin' || $table === 'wali_kelas') {
            if ($table === 'admin') {
                if ($id == $_SESSION['user_id']) {
                    $delete_error = "Tidak dapat menghapus akun sendiri!";
                } else {
                    $check_count = $conek->query("SELECT COUNT(*) as total FROM admin");
                    $total_admin = $check_count->fetch_assoc()['total'] ?? 0;
                    
                    if ($total_admin <= 1) {
                        $delete_error = "Tidak dapat menghapus admin terakhir!";
                    } else {
                        $sql = "DELETE FROM $table WHERE id = ?";
                        $stmt = $conek->prepare($sql);
                        $stmt->bind_param("i", $id);
                        
                        if ($stmt->execute()) {
                            $delete_success = "Data '" . htmlspecialchars($name) . "' berhasil dihapus!";
                        } else {
                            $delete_error = "Gagal menghapus: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            } else {
                $sql = "DELETE FROM $table WHERE id = ?";
                $stmt = $conek->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $delete_success = "Data '" . htmlspecialchars($name) . "' berhasil dihapus!";
                } else {
                    $delete_error = "Gagal menghapus: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $delete_error = "Tabel tidak valid!";
        }
    } else {
        $delete_error = "ID atau tabel tidak valid!";
    }
    
    if (!empty($delete_success)) {
        $_SESSION['delete_success'] = $delete_success;
    }
    if (!empty($delete_error)) {
        $_SESSION['delete_error'] = $delete_error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ===== PROSES TAMBAH =====
$add_error = '';
$add_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    $errors = [];
    
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap harus diisi!";
    if (empty($username)) $errors[] = "Username harus diisi!";
    if (strlen($username) < 3) $errors[] = "Username minimal 3 karakter!";
    if (empty($email)) $errors[] = "Email harus diisi!";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid!";
    if (empty($password)) $errors[] = "Password harus diisi!";
    if (strlen($password) < 6) $errors[] = "Password minimal 6 karakter!";
    if ($password !== $confirm_password) $errors[] = "Password dan konfirmasi password tidak cocok!";
    if (empty($role)) $errors[] = "Role harus dipilih!";

    if (empty($errors)) {
        $check_query = "SELECT id FROM admin WHERE username = ? OR email = ? 
                        UNION 
                        SELECT id FROM wali_kelas WHERE username = ? OR email = ?";
        $check_stmt = $conek->prepare($check_query);
        $check_stmt->bind_param("ssss", $username, $email, $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $add_error = "Username atau email sudah digunakan!";
        } else {
            $table = ($role === 'admin') ? 'admin' : 'wali_kelas';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $encryption_key = 'absensi-rfid-secret-key-2024';
            $encrypted_password = openssl_encrypt($password, 'AES-256-CBC', $encryption_key, 0, '1234567890123456');
            
            $check_column = $conek->query("SHOW COLUMNS FROM $table LIKE 'encrypted_password'");
            $has_encrypted = ($check_column && $check_column->num_rows > 0);
            
            if ($has_encrypted) {
                $sql = "INSERT INTO $table (username, email, password, nama_lengkap, encrypted_password, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conek->prepare($sql);
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $nama_lengkap, $encrypted_password, $status);
            } else {
                $sql = "INSERT INTO $table (username, email, password, nama_lengkap, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conek->prepare($sql);
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $nama_lengkap, $status);
            }
            
            if ($stmt->execute()) {
                $add_success = ucfirst($role) . " berhasil ditambahkan!";
            } else {
                $add_error = "Gagal menambahkan: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    } else {
        $add_error = implode("\n", $errors);
    }
    
    if (!empty($add_success)) {
        $_SESSION['add_success'] = $add_success;
    }
    if (!empty($add_error)) {
        $_SESSION['add_error'] = $add_error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ===== TAMPILKAN NOTIFIKASI DARI SESSION =====
$show_success = '';
$show_error = '';

if (isset($_SESSION['delete_success'])) {
    $show_success = $_SESSION['delete_success'];
    unset($_SESSION['delete_success']);
}
if (isset($_SESSION['delete_error'])) {
    $show_error = $_SESSION['delete_error'];
    unset($_SESSION['delete_error']);
}
if (isset($_SESSION['add_success'])) {
    $show_success = $_SESSION['add_success'];
    unset($_SESSION['add_success']);
}
if (isset($_SESSION['add_error'])) {
    $show_error = $_SESSION['add_error'];
    unset($_SESSION['add_error']);
}

// ===== HITUNG STATISTIK =====
$result = $conek->query("SELECT COUNT(*) as total FROM admin");
$total_admin = $result->fetch_assoc()['total'] ?? 0;

$result = $conek->query("SELECT COUNT(*) as total FROM wali_kelas");
$total_wali_kelas = $result->fetch_assoc()['total'] ?? 0;

$result = $conek->query("SELECT COUNT(*) as total FROM siswa");
$total_siswa = $result->fetch_assoc()['total'] ?? 0;

$result = $conek->query("SELECT COUNT(*) as total FROM absensi_siswa WHERE DATE(tanggal) = CURDATE()");
$total_absensi = $result->fetch_assoc()['total'] ?? 0;

// ===== AMBIL DATA ADMIN =====
$query_admin = "SELECT id, username, nama_lengkap, email, status, created_at FROM admin ORDER BY created_at DESC LIMIT 10";
$result_admin = $conek->query($query_admin);

// ===== AMBIL DATA WALI KELAS =====
$query_wali = "SELECT id, username, nama_lengkap, email, status, created_at FROM wali_kelas ORDER BY created_at DESC LIMIT 10";
$result_wali = $conek->query($query_wali);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Absensi RFID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #e8e6f0;
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            display: flex;
            font-size: 12px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 10% 20%, rgba(72, 49, 212, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 80%, rgba(108, 99, 255, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(120, 119, 198, 0.03) 0%, transparent 70%),
                linear-gradient(135deg, #f0edf7 0%, #e8e4f0 30%, #ddd8e8 60%, #e8e4f0 100%);
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 40%, rgba(108, 99, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 70% 60%, rgba(72, 49, 212, 0.02) 0%, transparent 50%);
            z-index: 0;
            animation: floatGlow 25s ease-in-out infinite alternate;
        }

        @keyframes floatGlow {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            100% { transform: translate(3%, 2%) rotate(5deg) scale(1.05); }
        }

        .container-fluid {
            position: relative;
            z-index: 1;
            padding: 0;
            width: calc(100%);
        }

        /* ===== HEADER MODERN ===== */
        .header-modern {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0px 0px 14px 14px;
            padding: 1.2rem 1.8rem;
            margin-bottom: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.08);
            position: relative;
            overflow: hidden;
            z-index: 2;
        }

        .header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #4831d4, #6c63ff, #a78bfa, #6c63ff, #4831d4);
            background-size: 200% 100%;
            animation: gradientMove 4s linear infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(72, 49, 212, 0.15), rgba(108, 99, 255, 0.10));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid rgba(108, 99, 255, 0.15);
        }

        .header-text h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .header-text .sub {
            font-size: 0.7rem;
            color: #555577;
            font-weight: 400;
            margin-top: 0.05rem;
        }

        .header-stats {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.4rem 1.2rem;
            border-radius: 10px;
            border: 1px solid rgba(72, 49, 212, 0.08);
        }

        .stat-chip {
            text-align: center;
        }

        .stat-chip .number {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a2e;
            display: block;
            line-height: 1.2;
        }

        .stat-chip .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #666688;
            font-weight: 600;
        }

        /* ===== STATISTICS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 0 1.5rem 1.5rem 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
        }

        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, #4831d4, #6c63ff); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #059669, #34d399); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #dc2626, #ef4444); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.12);
        }

        .stat-card .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-card .stat-icon.blue {
            background: rgba(72, 49, 212, 0.12);
            color: #4831d4;
        }

        .stat-card .stat-icon.green {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .stat-card .stat-icon.orange {
            background: rgba(217, 119, 6, 0.12);
            color: #d97706;
        }

        .stat-card .stat-icon.red {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
        }

        .stat-card .stat-trend {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 20px;
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
            line-height: 1.2;
        }

        .stat-card p {
            color: #6c757d;
            font-size: 0.7rem;
            margin: 0;
            font-weight: 500;
        }

        .stat-card .stat-change {
            font-size: 0.6rem;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .stat-card .stat-change.up {
            color: #059669;
        }

        /* ===== CONTENT SECTION ===== */
        .content-section1 {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 14px 14px 0px 0px;
            padding: 1rem 1.5rem;
            margin-bottom: 0.1rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
        }
        .content-section2 {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0px 0px 14px 14px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .content-header-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .content-header h2 {
            color: #1a1a2e;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .content-header h2 i {
            font-size: 0.8rem;
        }

        .content-header .badge-count {
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .admin-section h2 i { color: #4831d4; }
        .wali-section h2 i { color: #4831d4; }
        .admin-section .badge-count {
            background: rgba(72, 49, 212, 0.08);
            color: #4831d4;
        }
        .wali-section .badge-count {
            background: rgba(72, 49, 212, 0.08);
            color: #4831d4;
        }

        .btn-add {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
            box-shadow: 0 2px 12px rgba(72, 49, 212, 0.25);
        }

        .btn-add-admin {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
        }
        .btn-add-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.35);
        }
        .btn-add-wali {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
        }
        .btn-add-wali:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.35);
        }

        /* ===== TABLE ===== */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }

        table thead {
            background: rgba(72, 49, 212, 0.04);
            border-bottom: 2px solid rgba(72, 49, 212, 0.06);   
        }

        .wali-section table thead {
            background: rgba(72, 49, 212, 0.04);
            border-bottom: 2px solid rgba(72, 49, 212, 0.06);
        }

        table th {
            padding: 0.5rem 0.8rem;
            text-align: left;
            font-weight: 700;
            color: #555577;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(72, 49, 212, 0.06);
        }

        table td {
            padding: 0.5rem 0.8rem;
            border-bottom: 1px solid rgba(72, 49, 212, 0.05);
            color: #1a1a2e;
            font-size: 0.7rem;
            vertical-align: middle;
        }

        table tr:hover td {
            background: rgba(108, 99, 255, 0.04);
        }

        .wali-section table tr:hover td {
            background: rgba(108, 99, 255, 0.04);
        }

        table tr:last-child td {
            border-bottom: none;
        }

        /* ===== STATUS BADGE ===== */
        .status-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-badge.active {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .status-badge.pending {
            background: rgba(217, 119, 6, 0.12);
            color: #d97706;
        }

        .status-badge.inactive {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
        }

        /* ===== BUTTON ===== */
        .btn-action {
            padding: 2px 10px;
            border: none;
            border-radius: 5px;
            font-size: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: inline-block;
            margin: 1px 0;
        }

        .btn-action.view {
            background: rgba(72, 49, 212, 0.10);
            color: #4831d4;
        }
        .btn-action.view:hover {
            background: rgba(72, 49, 212, 0.20);
            transform: translateY(-1px);
        }
        .btn-action.edit {
            background: rgba(59, 130, 246, 0.10);
            color: #2563eb;
        }
        .btn-action.edit:hover {
            background: rgba(59, 130, 246, 0.20);
            transform: translateY(-1px);
        }
        .btn-action.delete {
            background: rgba(220, 38, 38, 0.10);
            color: #dc2626;
            border: none;
        }
        .btn-action.delete:hover {
            background: rgba(220, 38, 38, 0.20);
            transform: translateY(-1px);
        }
        .btn-action i {
            font-size: 0.55rem;
            margin-right: 2px;
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            max-width: 550px;
            width: 92%;
            max-height: 90vh;
            overflow-y: auto;
            animation: scaleIn 0.3s ease;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px 20px 0 0;
            z-index: 1;
        }

        .modal-header h3 {
            color: #1a1a2e;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #4831d4;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 0 6px;
            line-height: 1;
        }

        .close-modal:hover {
            color: #dc2626;
        }

        .modal-body {
            padding: 24px;
        }

        /* ===== DETAIL MODAL STYLES ===== */
        .detail-grid {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px 20px;
            margin: 10px 0;
        }

        .detail-grid .label {
            color: #6c757d;
            font-size: 0.7rem;
            font-weight: 500;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }

        .detail-grid .value {
            color: #1a1a2e;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .detail-grid .value .status-badge {
            font-size: 0.6rem;
        }

        .detail-grid .value i {
            margin-right: 5px;
            color: #4831d4;
            width: 16px;
        }

        .detail-grid .value .password-toggle {
            cursor: pointer;
            color: #6c757d;
            font-size: 0.7rem;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .detail-grid .value .password-toggle:hover {
            color: #4831d4;
            background: rgba(72, 49, 212, 0.08);
        }

        .detail-grid .value .password-text {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .detail-grid .value .password-note {
            font-size: 0.6rem;
            color: #6c757d;
            font-weight: 400;
            width: 100%;
        }

        .detail-icon {
            text-align: center;
            padding: 10px 0;
            font-size: 3rem;
        }

        .detail-icon.admin-icon {
            color: #4831d4;
        }

        .detail-icon.wali-icon {
            color: #f59e0b;
        }

        .modal-body .btn-close-detail {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            background: rgba(0, 0, 0, 0.06);
            color: #555577;
            margin-top: 15px;
        }

        .modal-body .btn-close-detail:hover {
            background: rgba(0, 0, 0, 0.10);
        }

        /* ===== MODAL ADD ===== */
        .modal-body .form-group {
            margin-bottom: 14px;
        }

        .modal-body label {
            display: block;
            color: #1a1a2e;
            font-weight: 600;
            font-size: 0.75rem;
            margin-bottom: 4px;
        }

        .modal-body label i {
            color: #4831d4;
            margin-right: 5px;
            width: 16px;
        }

        .modal-body input,
        .modal-body select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-family: 'Poppins', sans-serif;
            color: #1a1a2e;
        }

        .modal-body input:focus,
        .modal-body select:focus {
            outline: none;
            border-color: #4831d4;
            box-shadow: 0 0 0 3px rgba(72, 49, 212, 0.12);
        }

        .modal-body .password-wrapper {
            position: relative;
        }

        .modal-body .password-wrapper input {
            padding-right: 40px;
        }

        .modal-body .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-body .toggle-password:hover {
            color: #4831d4;
        }

        .modal-body .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: white;
            box-shadow: 0 2px 16px rgba(72, 49, 212, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 24px rgba(72, 49, 212, 0.35);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-submit.btn-danger {
            background: linear-gradient(135deg, #dc2626, #ef4444);
        }
        .btn-submit.btn-danger:hover {
            box-shadow: 0 4px 24px rgba(220, 38, 38, 0.35);
        }

        /* ===== ALERT ===== */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            padding: 14px 22px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            animation: slideDown 0.5s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            max-width: 400px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .custom-alert .alert-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0 0 0 10px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .custom-alert .alert-close:hover {
            opacity: 1;
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.95);
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.95);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.95);
        }

        .hidden {
            display: none !important;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container-fluid { padding: 0; }
            .header-modern {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
                margin: 0.8rem;
            }
            .header-stats {
                width: 100%;
                justify-content: space-around;
                padding: 0.4rem 0.8rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 0 0.8rem 0.8rem 0.8rem;
                gap: 12px;
            }
            .content-section1, .content-section2 {
                margin: 0 0.8rem 0.8rem 0.8rem;
                padding: 0.8rem;
            }
            .detail-grid {
                grid-template-columns: 110px 1fr;
                gap: 6px 15px;
            }
        }

        @media (max-width: 600px) {
            body { font-size: 11px; }
            .header-text h1 { font-size: 1rem; }
            .header-stats { gap: 0.3rem; flex-wrap: wrap; }
            .stat-chip .number { font-size: 0.85rem; }
            .stats-grid { grid-template-columns: 1fr; gap: 8px; padding: 0 0.5rem 0.5rem 0.5rem; }
            .content-section1, .content-section2 {
                padding: 0.7rem;
                margin: 0 0.5rem 0.5rem 0.5rem;
            }
            .stat-card h3 { font-size: 1.2rem; }
            .content-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .content-header-left { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .header-icon { width: 32px; height: 32px; font-size: 16px; }
            .header-modern { padding: 0.7rem; margin: 0.4rem; }
            table { font-size: 0.6rem; }
            table th { font-size: 0.5rem; padding: 0.3rem 0.5rem; }
            table td { font-size: 0.6rem; padding: 0.3rem 0.5rem; }
            .status-badge { font-size: 0.5rem; padding: 1px 8px; }
            .btn-action { font-size: 0.5rem; padding: 1px 8px; }
            .btn-action i { font-size: 0.5rem; }
            .modal-body .form-row { grid-template-columns: 1fr; }
            .modal-content { width: 95%; margin: 10px; }
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 2px;
            }
            .detail-grid .label {
                padding: 2px 0;
                font-size: 0.65rem;
            }
            .detail-grid .value {
                padding: 2px 0 6px 0;
                font-size: 0.7rem;
                flex-wrap: wrap;
            }
            .detail-icon {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR FROM MENU.PHP ===== -->
<?php include "menu.php"; ?>

<!-- ===== MAIN CONTENT ===== -->
<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">📊</div>
            <div class="header-text">
                <h1>Dashboard Admin</h1>
                <div class="sub">Selamat datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin'); ?></div>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-chip">
                <span class="number"><?php echo date('d'); ?></span>
                <span class="label">Tanggal</span>
            </div>
            <div class="stat-chip" style="border-left:1px solid rgba(72,49,212,0.10); padding-left:1rem;">
                <span class="number"><?php echo date('F Y'); ?></span>
                <span class="label">Bulan</span>
            </div>
        </div>
    </div>

    <!-- ===== STATISTICS GRID ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon blue"><i class="fas fa-user-shield"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Terbaru</span>
            </div>
            <h3><?php echo number_format($total_admin); ?></h3>
            <p>Total Admin</p>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> Data terbaru</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon orange"><i class="fas fa-chalkboard-teacher"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Terbaru</span>
            </div>
            <h3><?php echo number_format($total_wali_kelas); ?></h3>
            <p>Total Wali Kelas</p>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> Data terbaru</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Terbaru</span>
            </div>
            <h3><?php echo number_format($total_siswa); ?></h3>
            <p>Total Siswa</p>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> +5 dari bulan lalu</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon red"><i class="fas fa-calendar-check"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Hari ini</span>
            </div>
            <h3><?php echo number_format($total_absensi); ?></h3>
            <p>Absensi Hari Ini</p>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> <?php echo date('H:i'); ?></div>
        </div>
    </div>

    <!-- ===== RECENT ADMIN ===== -->
    <div class="content-section1 admin-section">
        <div class="content-header">
            <div class="content-header-left">
                <h2><i class="fas fa-user-shield"></i> Admin Terbaru</h2>
                <span class="badge-count"><?php echo $result_admin->num_rows ?? 0; ?> admin</span>
            </div>
            <button class="btn-add btn-add-admin" onclick="openAddModal('admin')">
                <i class="fas fa-plus-circle"></i> Tambah Admin
            </button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:center; width: 300px;">Nama</th>
                        <th style="text-align:center; width: 250px;">Username</th>
                        <th style="text-align:center;">Email</th>
                        <th style="text-align:center; width: 100px;">Status</th>
                        <th style="text-align:center; width: 210px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_admin && $result_admin->num_rows > 0) {
                        while ($row = $result_admin->fetch_assoc()) {
                            $status_class = $row['status'] === 'active' ? 'active' : ($row['status'] === 'pending' ? 'pending' : 'inactive');
                    ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['nama_lengkap'] ?? $row['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="text-align:center;"><span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span></td>
                                <td style="text-align:center;">
                                    <button class="btn-action view" onclick="openDetailModal('admin', <?php echo $row['id']; ?>)">
                                        <i class="fas fa-eye"></i> Detail
                                    </button>
                                    <a href="edit_admin.php?id=<?php echo $row['id']; ?>" class="btn-action edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn-action delete" onclick="openDeleteModal('admin', <?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap'] ?? $row['username']); ?>')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; color: #6c757d; padding: 15px; font-size: 0.7rem;'>Belum ada admin terdaftar</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== RECENT WALI KELAS ===== -->
    <div class="content-section2 wali-section">
        <div class="content-header">
            <div class="content-header-left">
                <h2><i class="fas fa-chalkboard-teacher"></i> Wali Kelas Terbaru</h2>
                <span class="badge-count"><?php echo $result_wali->num_rows ?? 0; ?> wali kelas</span>
            </div>
            <button class="btn-add btn-add-wali" onclick="openAddModal('wali_kelas')">
                <i class="fas fa-plus-circle"></i> Tambah Wali Kelas
            </button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:center; width: 300px;">Nama</th>
                        <th style="text-align:center; width: 250px;">Username</th>
                        <th style="text-align:center;">Email</th>
                        <th style="text-align:center; width: 100px;">Status</th>
                        <th style="text-align:center; width: 210px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_wali && $result_wali->num_rows > 0) {
                        while ($row = $result_wali->fetch_assoc()) {
                            $status_class = $row['status'] === 'active' ? 'active' : ($row['status'] === 'pending' ? 'pending' : 'inactive');
                    ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['nama_lengkap'] ?? $row['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="text-align:center;"><span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span></td>
                                <td style="text-align:center;">
                                    <button class="btn-action view" onclick="openDetailModal('wali_kelas', <?php echo $row['id']; ?>)">
                                        <i class="fas fa-eye"></i> Detail
                                    </button>
                                    <a href="edit_wali_kelas.php?id=<?php echo $row['id']; ?>" class="btn-action edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn-action delete" onclick="openDeleteModal('wali_kelas', <?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap'] ?? $row['username']); ?>')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; color: #6c757d; padding: 15px; font-size: 0.7rem;'>Belum ada wali kelas terdaftar</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== MODAL TAMBAH ===== -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="addModalTitle"><i class="fas fa-user-plus"></i> Tambah Admin</h3>
            <button class="close-modal" onclick="closeModal('addModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addForm" method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="role" id="addRole" value="admin">

                <div class="form-group">
                    <label for="add_nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" id="add_nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label for="add_username"><i class="fas fa-user-tag"></i> Username</label>
                    <input type="text" id="add_username" name="username" placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="add_email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="add_email" name="email" placeholder="Masukkan email" required>
                </div>

                <div class="form-group">
                    <label for="add_password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="add_password" name="password" placeholder="Minimal 6 karakter" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('add_password')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_confirm_password"><i class="fas fa-check-circle"></i> Konfirmasi Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="add_confirm_password" name="confirm_password" placeholder="Konfirmasi password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('add_confirm_password')"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="add_status" name="status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; justify-content: flex-end;">
                        <button type="submit" class="btn-submit" id="addSubmitBtn">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL KONFIRMASI HAPUS ===== -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i> Konfirmasi Hapus</h3>
            <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.85rem; color: #1a1a2e; margin-bottom: 0.5rem;">
                Apakah Anda yakin ingin menghapus data:
            </p>
            <p style="font-size: 0.9rem; font-weight: 700; color: #dc2626; margin-bottom: 0.5rem; text-align: center;" id="deleteName">
                -
            </p>
            <p style="font-size: 0.7rem; color: #6c757d; margin-bottom: 1.5rem; text-align: center;">
                <i class="fas fa-info-circle"></i> Tindakan ini tidak dapat dibatalkan!
            </p>
            
            <form id="deleteForm" method="POST" action="">
                <input type="hidden" name="delete_action" value="confirm_delete">
                <input type="hidden" name="delete_table" id="deleteTable" value="">
                <input type="hidden" name="delete_id" id="deleteId" value="">
                <input type="hidden" name="delete_name" id="deleteNameInput" value="">
                
                <div style="display: flex; gap: 0.8rem;">
                    <button type="submit" class="btn-submit btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                    <button type="button" onclick="closeModal('deleteModal')" class="btn-submit" style="background: rgba(0,0,0,0.08); color: #555577;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL DETAIL ===== -->
<div class="modal" id="detailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="detailTitle"><i class="fas fa-user"></i> Detail Admin</h3>
            <button class="close-modal" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detailContent">
                <div class="detail-icon" id="detailIcon">
                    <i class="fas fa-user-shield admin-icon"></i>
                </div>
                <div class="detail-grid" id="detailGrid">
                    <!-- Detail akan diisi oleh JavaScript -->
                </div>
                <button class="btn-close-detail" onclick="closeModal('detailModal')">
                    <i class="fas fa-times"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ===== MODAL FUNCTIONS =====
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    // Reset password visibility saat close detail modal
    if (modalId === 'detailModal') {
        passwordVisible = false;
    }
}

// Close modal on click outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = 'auto';
            if (this.id === 'detailModal') {
                passwordVisible = false;
            }
        }
    });
});

// ===== OPEN ADD MODAL =====
function openAddModal(role) {
    const modal = document.getElementById('addModal');
    const title = document.getElementById('addModalTitle');
    const roleInput = document.getElementById('addRole');
    const submitBtn = document.getElementById('addSubmitBtn');
    
    // Reset form
    document.getElementById('addForm').reset();
    
    // Set role
    roleInput.value = role;
    
    // Set title
    if (role === 'admin') {
        title.innerHTML = '<i class="fas fa-user-shield"></i> Tambah Admin';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Admin';
    } else {
        title.innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Tambah Wali Kelas';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Wali Kelas';
    }
    
    openModal('addModal');
}

// ===== OPEN DETAIL MODAL =====
let passwordVisible = false;
let currentPassword = '';
let currentPasswordNote = '';

function openDetailModal(table, id) {
    // Reset password visibility
    passwordVisible = false;
    currentPassword = '';
    currentPasswordNote = '';
    
    const modal = document.getElementById('detailModal');
    const title = document.getElementById('detailTitle');
    const icon = document.getElementById('detailIcon');
    const grid = document.getElementById('detailGrid');
    
    // Set loading state
    grid.innerHTML = '<div style="text-align:center;padding:20px;color:#6c757d;font-size:0.8rem;"><i class="fas fa-spinner fa-spin"></i> Memuat data...</div>';
    
    // Tampilkan modal terlebih dahulu
    openModal('detailModal');
    
    // Fetch data via AJAX
    fetch('get_detail.php?table=' + table + '&id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showDetail(data.data, table);
            } else {
                grid.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626;font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Gagal mengambil data') + '</div>';
                showAlert('Gagal mengambil data detail: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            grid.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626;font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> Terjadi kesalahan saat mengambil data!</div>';
            showAlert('Terjadi kesalahan saat mengambil data! Periksa koneksi atau file get_detail.php', 'error');
        });
}

function showDetail(data, table) {
    const title = document.getElementById('detailTitle');
    const icon = document.getElementById('detailIcon');
    const grid = document.getElementById('detailGrid');
    
    // Simpan password untuk toggle
    currentPassword = data.password || '••••••••';
    currentPasswordNote = data.password_note || '';
    
    // Set title dan icon
    if (table === 'admin') {
        title.innerHTML = '<i class="fas fa-user-shield"></i> Detail Admin';
        icon.innerHTML = '<i class="fas fa-user-shield"></i>';
        icon.className = 'detail-icon admin-icon';
    } else {
        title.innerHTML = '<i class="fas fa-chalkboard-teacher"></i> Detail Wali Kelas';
        icon.innerHTML = '<i class="fas fa-chalkboard-teacher"></i>';
        icon.className = 'detail-icon wali-icon';
    }
    
    // Buat grid detail
    let html = '';
    const fields = {
        'id': 'ID',
        'username': 'Username',
        'nama_lengkap': 'Nama Lengkap',
        'email': 'Email',
        'password': 'Password',
        'status': 'Status',
        'created_at': 'Tanggal Dibuat'
    };
    
    for (const [key, label] of Object.entries(fields)) {
        if (data[key] !== undefined && data[key] !== null) {
            let value = data[key];
            if (key === 'status') {
                const statusClass = value === 'active' ? 'active' : (value === 'pending' ? 'pending' : 'inactive');
                value = '<span class="status-badge ' + statusClass + '">' + value.charAt(0).toUpperCase() + value.slice(1) + '</span>';
            } else if (key === 'created_at') {
                value = new Date(value).toLocaleString('id-ID', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (key === 'password') {
                // Admin BISA melihat password
                // Tampilkan dengan toggle
                const displayPassword = value || '••••••••';
                const hiddenPassword = '••••••••';
                const passwordDisplay = passwordVisible ? displayPassword : hiddenPassword;
                const iconClass = passwordVisible ? 'fa-eye-slash' : 'fa-eye';
                const btnText = passwordVisible ? 'Sembunyikan' : 'Tampilkan';
                
                value = '<span class="password-text" id="passwordDisplay">' + passwordDisplay + '</span>' +
                        '<button class="password-toggle" onclick="togglePasswordVisibility()">' +
                            '<i class="fas ' + iconClass + '"></i> ' + btnText +
                        '</button>' +
                        '<span class="password-note">' + (data.password_note || '🔓 Password dapat dilihat oleh admin') + '</span>';
            } else if (key === 'nama_lengkap') {
                value = '<i class="fas fa-user"></i> ' + value;
            } else if (key === 'email') {
                value = '<i class="fas fa-envelope"></i> ' + value;
            } else if (key === 'username') {
                value = '<i class="fas fa-user-tag"></i> ' + value;
            } else if (key === 'id') {
                value = '<i class="fas fa-hashtag"></i> #' + value;
            }
            html += '<div class="label">' + label + '</div>';
            html += '<div class="value">' + value + '</div>';
        }
    }
    
    grid.innerHTML = html;
}

function togglePasswordVisibility() {
    passwordVisible = !passwordVisible;
    const passwordDisplay = document.getElementById('passwordDisplay');
    if (passwordDisplay) {
        if (passwordVisible) {
            passwordDisplay.textContent = currentPassword || 'Tidak tersedia';
        } else {
            passwordDisplay.textContent = '••••••••';
        }
    }
    // Update button
    const toggleBtn = document.querySelector('.password-toggle');
    if (toggleBtn) {
        if (passwordVisible) {
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Sembunyikan';
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i> Tampilkan';
        }
    }
}

// ===== OPEN DELETE MODAL =====
function openDeleteModal(table, id, name) {
    document.getElementById('deleteTable').value = table;
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteNameInput').value = name;
    document.getElementById('deleteName').textContent = '"' + name + '"';
    
    openModal('deleteModal');
}

// ===== TOGGLE PASSWORD =====
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ===== FORM VALIDATION =====
document.getElementById('addForm').addEventListener('submit', function(e) {
    const password = document.getElementById('add_password').value;
    const confirm = document.getElementById('add_confirm_password').value;
    const submitBtn = document.getElementById('addSubmitBtn');
    
    if (password !== confirm) {
        e.preventDefault();
        showAlert('Password dan konfirmasi password tidak cocok!', 'error');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        showAlert('Password minimal 6 karakter!', 'error');
        return;
    }
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
});

document.getElementById('deleteForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menghapus...';
    submitBtn.disabled = true;
});

// ===== ALERT NOTIFICATION =====
function showAlert(message, type = 'info') {
    const oldAlert = document.querySelector('.custom-alert');
    if (oldAlert) oldAlert.remove();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `custom-alert alert-${type}`;
    
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    alertDiv.innerHTML = `
        <i class="fas ${iconMap[type] || iconMap.info}"></i>
        <span>${message}</span>
        <button class="alert-close">&times;</button>
    `;
    
    document.body.appendChild(alertDiv);
    
    alertDiv.querySelector('.alert-close').addEventListener('click', function() {
        alertDiv.remove();
    });
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateX(100px)';
            alertDiv.style.transition = 'all 0.5s ease';
            setTimeout(() => alertDiv.remove(), 500);
        }
    }, 5000);
}

// ===== TAMPILKAN ALERT DARI PHP =====
<?php if (!empty($show_success)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo addslashes($show_success); ?>', 'success');
        setTimeout(function() {
            closeModal('addModal');
            closeModal('deleteModal');
        }, 1500);
    });
<?php endif; ?>

<?php if (!empty($show_error)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo addslashes($show_error); ?>', 'error');
        // Reset button
        document.querySelectorAll('.btn-submit').forEach(btn => {
            if (btn.innerHTML.includes('Simpan')) {
                btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
            } else if (btn.innerHTML.includes('Hapus')) {
                btn.innerHTML = '<i class="fas fa-trash"></i> Ya, Hapus';
            }
            btn.disabled = false;
        });
    });
<?php endif; ?>

<?php if (!empty($add_success)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo addslashes($add_success); ?>', 'success');
        setTimeout(function() {
            closeModal('addModal');
        }, 1500);
    });
<?php endif; ?>

<?php if (!empty($add_error)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo addslashes($add_error); ?>', 'error');
        const submitBtn = document.getElementById('addSubmitBtn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan';
            submitBtn.disabled = false;
        }
    });
<?php endif; ?>

console.log('🚀 Dashboard Admin siap!');
console.log('📊 Statistik: Admin=<?php echo $total_admin; ?>, Wali Kelas=<?php echo $total_wali_kelas; ?>, Siswa=<?php echo $total_siswa; ?>');
</script>

</body>
</html>