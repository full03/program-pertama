<?php
// ===== PERBAIKAN: Cek status session dengan lebih baik =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// Ambil data user dari session (sama dengan menu.php)
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Jika user_id ada, ambil data dari database sesuai role
if ($user_id > 0 && !empty($user_role)) {
    // Tentukan tabel berdasarkan role
    $table = ($user_role === 'admin') ? 'admin' : 'wali_kelas';
    
    $query = "SELECT id, username, nama_lengkap, email, foto_profil, status FROM $table WHERE id = ?";
    $stmt = $conek->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['status'] = $user['status'] ?? 'active';
            
            // Handle foto profil
            if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])) {
                $_SESSION['foto_profil'] = $user['foto_profil'];
            } else {
                $_SESSION['foto_profil'] = '../images/default-user.png';
            }
        }
        $stmt->close();
    }
}

// Set variabel untuk ditampilkan (sama dengan menu.php)
$nama_user = isset($_SESSION['nama_lengkap']) && !empty($_SESSION['nama_lengkap']) 
    ? $_SESSION['nama_lengkap'] 
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest');
    
$foto_user = isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) 
    ? $_SESSION['foto_profil'] 
    : '../images/default-user.png';
    
$role_user = isset($_SESSION['role']) && !empty($_SESSION['role']) 
    ? ucfirst(str_replace('_', ' ', $_SESSION['role'])) 
    : 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - Notifikasi WhatsApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tambahkan SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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

        /* ===== ANIMATED BACKGROUND ===== */
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

        /* ===== CONTAINER ===== */
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
            transition: all 0.3s ease;
        }
        .content-section2 {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0px 0px 14px 14px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
            transition: all 0.3s ease;
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

        .content-section.siswa-section h2 i {
            color: #4831d4;
        }

        .content-section.siswa-section .badge-count {
            background: rgba(72, 49, 212, 0.08);
            color: #4831d4;
        }

        /* ===== SEARCH & FILTER ===== */
        .search-filters {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 20;
        }

        .search-filters input {
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            border: 1px solid rgba(72, 49, 212, 0.12);
            background: rgba(255, 255, 255, 0.7);
            color: #1a1a2e;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            min-height: 30px;
        }

        .search-filters input:focus {
            outline: none;
            border-color: #6c63ff;
            background: #ffffff;
            box-shadow: 0 0 20px rgba(72, 49, 212, 0.08);
        }

        .search-filters input::placeholder {
            color: rgba(26, 26, 46, 0.35);
        }

        .search-filters .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            background: rgba(72, 49, 212, 0.06);
            color: #666688;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .search-filters .btn-icon:hover {
            background: rgba(108, 99, 255, 0.12);
            color: #4831d4;
        }

        /* ===== BUTTONS ===== */
        .btn-group {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-modern::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-modern:hover::after {
            opacity: 1;
        }

        .btn-modern:hover {
            transform: translateY(-2px) scale(1.02);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #fff;
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 25px rgba(72, 49, 212, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #34d399);
            color: #fff;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.25);
        }

        .btn-success:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.4);
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
        }

        table tr:hover td {
            background: rgba(108, 99, 255, 0.04);
        }

        table tr:last-child td {
            border-bottom: none;
        }

        /* ===== BADGE ===== */
        .badge-custom {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.65rem;
            display: inline-block;
        }

        .badge-success {
            background: rgba(5, 150, 105, 0.15);
            color: #059669;
        }

        .badge-warning {
            background: rgba(217, 119, 6, 0.15);
            color: #d97706;
        }

        .badge-danger {
            background: rgba(220, 38, 38, 0.15);
            color: #dc2626;
        }

        .badge-info {
            background: rgba(72, 49, 212, 0.15);
            color: #4831d4;
        }

        /* ===== SWITCH CUSTOM ===== */
        .custom-switch {
            position: relative;
            display: inline-block;
            min-width: 100px;
        }

        .custom-switch .custom-control-input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .custom-switch .custom-control-label {
            display: inline-block;
            padding-left: 2.5rem;
            cursor: pointer;
            user-select: none;
            font-size: 0.7rem;
            min-height: 24px;
            position: relative;
        }

        .custom-switch .custom-control-label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 2.2rem;
            height: 1.2rem;
            background: #ccc;
            border-radius: 15px;
            transition: background 0.3s ease;
        }

        .custom-switch .custom-control-label::after {
            content: '';
            position: absolute;
            left: 0.15rem;
            top: 50%;
            transform: translateY(-50%);
            width: calc(1.1rem - 4px);
            height: calc(1.1rem - 4px);
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #4831d4;
        }

        .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
            transform: translate(1.1rem, -50%);
        }

        /* ===== FORM ===== */
        .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
            border-radius: 8px;
            border: 1px solid rgba(108, 99, 255, 0.15);
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
            width: 100%;
            min-width: 150px;
            max-width: 200px;
            height: 32px;
        }

        .form-control-sm:focus {
            outline: none;
            border-color: #6c63ff;
            background: white;
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        /* ===== BUTTON CUSTOM ===== */
        .btn-custom-primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            border: none;
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 8px;
            font-size: 0.65rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-custom-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.3);
            color: white;
        }

        .btn-custom-success {
            background: linear-gradient(135deg, #059669, #10b981);
            border: none;
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 8px;
            font-size: 0.65rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-custom-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
            color: white;
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

        .status-badge.hadir {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .status-badge.sakit {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
        }

        .status-badge.alpha {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
        }

        .status-badge.izin {
            background: rgba(217, 119, 6, 0.12);
            color: #d97706;
        }

        /* ===== UTILITY ===== */
        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #6c757d;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .py-4 {
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .d-flex {
            display: flex;
        }

        .align-items-center {
            align-items: center;
        }

        .gap-1 {
            gap: 0.25rem;
        }

        .code {
            font-family: 'Courier New', monospace;
            background: rgba(0,0,0,0.05);
            padding: 0.1rem 0.3rem;
            border-radius: 4px;
            font-size: 0.65rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container-fluid {
                padding: 0;
                margin-left: 170px;
                width: calc(100% - 170px);
            }

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

            .content-section1,
            .content-section2 {
                margin: 0 0.8rem 0.8rem 0.8rem;
                padding: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .container-fluid {
                margin-left: 60px;
                width: calc(100% - 60px);
            }

            .custom-switch {
                min-width: 80px;
            }

            .custom-switch .custom-control-label {
                font-size: 0.6rem;
                padding-left: 2rem;
            }

            .custom-switch .custom-control-label::before {
                width: 1.8rem;
                height: 1rem;
            }

            .custom-switch .custom-control-label::after {
                width: 0.9rem;
                height: 0.9rem;
            }

            .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
                transform: translate(0.9rem, -50%);
            }

            .form-control-sm {
                min-width: 100px;
                max-width: 150px;
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .container-fluid {
                margin-left: 0;
                width: 100%;
            }

            body {
                font-size: 11px;
            }

            .header-text h1 {
                font-size: 1rem;
            }

            .header-stats {
                gap: 0.3rem;
                flex-wrap: wrap;
            }

            .stat-chip .number {
                font-size: 0.85rem;
            }

            .content-section1,
            .content-section2 {
                padding: 0.7rem;
                margin: 0 0.5rem 0.5rem 0.5rem;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .content-header-left {
                width: 100%;
                justify-content: space-between;
            }

            .header-icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .header-modern {
                padding: 0.7rem;
                margin: 0.4rem;
            }

            table {
                font-size: 0.6rem;
            }

            table th {
                font-size: 0.5rem;
                padding: 0.3rem 0.5rem;
            }

            table td {
                font-size: 0.6rem;
                padding: 0.3rem 0.5rem;
            }

            .badge-custom {
                font-size: 0.55rem;
                padding: 0.2rem 0.6rem;
            }

            .btn-custom-primary,
            .btn-custom-success {
                font-size: 0.55rem;
                padding: 0.15rem 0.4rem;
            }

            .form-control-sm {
                min-width: 80px;
                max-width: 120px;
                font-size: 0.6rem;
                height: 28px;
            }

            .btn-modern {
                font-size: 0.65rem;
                padding: 0.3rem 0.7rem;
            }

            .search-filters {
                flex-direction: column;
            }

            .search-filters input {
                width: 100%;
                min-width: unset;
                font-size: 0.65rem;
            }

            .custom-switch {
                min-width: 60px;
            }

            .custom-switch .custom-control-label {
                font-size: 0.55rem;
                padding-left: 1.8rem;
            }

            .custom-switch .custom-control-label::before {
                width: 1.5rem;
                height: 0.9rem;
            }

            .custom-switch .custom-control-label::after {
                width: 0.7rem;
                height: 0.7rem;
            }

            .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
                transform: translate(0.8rem, -50%);
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(72, 49, 212, 0.04);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(108, 99, 255, 0.3);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(108, 99, 255, 0.5);
        }

        ::selection {
            background: rgba(108, 99, 255, 0.2);
            color: #1a1a2e;
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- ===== SIDEBAR FROM MENU.PHP ===== -->
<?php include "menu.php"; ?>

<!-- ===== MAIN CONTENT ===== -->
<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">📱</div>
            <div class="header-text">
                <h1>Manajemen Siswa & Notifikasi WhatsApp</h1>
                <div class="sub">Kelola notifikasi WhatsApp untuk siswa</div>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-chip">
                <span class="number">
                    <?php 
                    $countQuery = "SELECT COUNT(*) as total FROM siswa";
                    $countResult = mysqli_query($conek, $countQuery);
                    $total = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
                    echo $total;
                    ?>
                </span>
                <span class="label">Total Siswa</span>
            </div>
            <div class="stat-chip" style="border-left:1px solid rgba(72,49,212,0.10); padding-left:1rem;">
                <span class="number">
                    <?php 
                    $notifQuery = "SELECT COUNT(*) as total FROM siswa WHERE notifikasi_wa = 1";
                    $notifResult = mysqli_query($conek, $notifQuery);
                    $totalNotif = $notifResult ? mysqli_fetch_assoc($notifResult)['total'] : 0;
                    echo $totalNotif;
                    ?>
                </span>
                <span class="label">Notifikasi Aktif</span>
            </div>
        </div>
    </div>

    <!-- ===== CONTENT SECTION 1: DAFTAR SISWA ===== -->
    <div class="content-section1 siswa-section">
        <div class="content-header">
            <div class="content-header-left">
                <h2><i class="fas fa-users"></i> Daftar Siswa</h2>
                <span class="badge-count">
                    <?php 
                    $totalSiswa = mysqli_query($conek, "SELECT COUNT(*) as total FROM siswa");
                    echo $totalSiswa ? mysqli_fetch_assoc($totalSiswa)['total'] : 0; 
                    ?> siswa
                </span>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <div class="btn-group">
                    <button class="btn-modern btn-success" id="btnToggleAll">
                        📢 Aktifkan Semua
                    </button>
                </div>
                <div class="search-filters">
                    <input type="text" id="searchSiswa" placeholder="🔍 Cari nama siswa...">
                    <button class="btn-icon" id="btnSearch">🔍</button>
                    <button class="btn-icon" id="btnReset">↻</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table id="tabelSiswa">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align:center;">No</th>
                        <th style="text-align:center;">Nama</th>
                        <th style="width: 85px; text-align:center;">Kelas</th>
                        <th style="width: 90px; text-align:center;">Jurusan</th>
                        <th style="width: 180px; text-align:center;">Nomor WhatsApp</th>
                        <th style="width: 130px; text-align:center;">Notifikasi</th>
                        <th style="width: 85px; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 0;
                    $query = "SELECT * FROM siswa ORDER BY nama_siswa ASC";
                    $result = mysqli_query($conek, $query);
                    
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $no++;
                            $checked = ($row['notifikasi_wa'] == 1) ? 'checked' : '';
                            $no_hp = $row['no_hp'] ?? '';
                    ?>
                    <tr >
                        <td class="text-center"><?= $no ?></td>
                        <td>
                            <strong> <?= htmlspecialchars($row['nama_siswa']) ?></strong>
                            <br>
                            <small class="text-muted code"><?= htmlspecialchars($row['nokartu']) ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge-custom badge-info">
                                <?= htmlspecialchars($row['kelas']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($row['jurusan']) ?></td>
                        <td>
                            <input type="text" class="form-control-sm" 
                                   value="<?= htmlspecialchars($no_hp) ?>" 
                                   id="wa_<?= $row['nokartu'] ?>"
                                   placeholder="628xxxxxxxxx">
                        </td>
                        <td class="text-center">
                            <div class="custom-switch">
                                <input type="checkbox" class="custom-control-input" 
                                       id="notif_<?= $row['nokartu'] ?>" <?= $checked ?>
                                       data-nokartu="<?= $row['nokartu'] ?>">
                                <label class="custom-control-label" for="notif_<?= $row['nokartu'] ?>">
                                    <span id="label_<?= $row['nokartu'] ?>" style="font-size: 0.7rem;">
                                        <?= $checked ? '✅ Aktif' : '⛔ Nonaktif' ?>
                                    </span>
                                </label>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn-custom-primary btn-update-wa" 
                                        data-nokartu="<?= $row['nokartu'] ?>"
                                        title="Update Nomor">
                                    <i class="fas fa-save"></i>
                                </button>
                                <button class="btn-custom-success btn-test-notif" 
                                        data-nokartu="<?= $row['nokartu'] ?>"
                                        title="Test Notifikasi">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php 
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-users-slash" style="font-size: 2rem; color: #ccc;"></i>
                            <p class="mt-2 text-muted">Belum ada data siswa</p>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== CONTENT SECTION 2: LOG NOTIFIKASI ===== -->
    <div class="content-section2 siswa-section">
        <div class="content-header">
            <div class="content-header-left">
                <h2><i class="fas fa-history"></i> Log Notifikasi WhatsApp</h2>
                <span class="badge-count">Log terbaru</span>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align:center;">No</th>
                        <th style="min-width: 100px; text-align:center;">Nokartu</th>
                        <th style="min-width: 100px; text-align:center;">Tanggal</th>
                        <th style="min-width: 80px; text-align:center;">Status</th>
                        <th style="min-width: 100px; text-align:center;">Hasil Kirim</th>
                        <th style="min-width: 100px; text-align:center;">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 0;
                    $queryLog = "SELECT * FROM log_notifikasi_wa ORDER BY id_log DESC LIMIT 20";
                    $resultLog = mysqli_query($conek, $queryLog);
                    
                    if ($resultLog && mysqli_num_rows($resultLog) > 0) {
                        while ($log = mysqli_fetch_assoc($resultLog)) {
                            $no++;
                            $badgeStatus = ($log['status_absensi'] == 'Hadir') ? 'hadir' : 
                                          (($log['status_absensi'] == 'Sakit') ? 'sakit' : 
                                          (($log['status_absensi'] == 'Izin') ? 'izin' : 'alpha'));
                            $badgeKirim = ($log['status_kirim'] == 'Terkirim') ? 'badge-success' : 'badge-danger';
                    ?>
                    <tr>
                        <td class="text-center"><?= $no ?></td>
                        <td><code><?= htmlspecialchars($log['nokartu']) ?></code></td>
                        <td><?= date('d-m-Y', strtotime($log['tanggal_absensi'])) ?></td>
                        <td>
                            <span class="status-badge <?= $badgeStatus ?>">
                                <?= htmlspecialchars($log['status_absensi']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-custom <?= $badgeKirim ?>">
                                <?= htmlspecialchars($log['status_kirim']) ?>
                            </span>
                        </td>
                        <td><?= date('H:i:s', strtotime($log['waktu_kirim'])) ?></td>
                    </tr>
                    <?php 
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-inbox" style="font-size: 2rem; color: #ccc;"></i>
                            <p class="mt-2 text-muted">Belum ada log notifikasi</p>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
$(document).ready(function() {
    // ===== SEARCH FUNCTION =====
    function searchTable() {
        var value = $("#searchSiswa").val().toLowerCase();
        $("#tabelSiswa tbody tr").each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(value) > -1);
        });
    }

    function resetSearch() {
        $("#searchSiswa").val("");
        searchTable();
    }

    // ===== EVENT LISTENERS =====
    // Search input
    $("#searchSiswa").on('keyup', function(e) {
        if (e.key === 'Enter') {
            searchTable();
        }
    });

    // Search button
    $("#btnSearch").on('click', searchTable);

    // Reset button
    $("#btnReset").on('click', resetSearch);

    // ===== TOGGLE ALL NOTIFIKASI =====
    $("#btnToggleAll").on('click', function() {
        const btn = $(this);
        
        // ===== PERBAIKAN 1: Hitung siswa yang belum aktif =====
        let inactiveCount = 0;
        $('.custom-control-input').each(function() {
            if (!$(this).prop('checked')) {
                inactiveCount++;
            }
        });
        
        // ===== PERBAIKAN 2: Jika semua sudah aktif =====
        if (inactiveCount === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Informasi',
                text: 'Semua siswa sudah dalam status aktif',
                timer: 2000,
                timerProgressBar: true,
                confirmButtonColor: '#4831d4'
            });
            return;
        }
        
        // ===== PERBAIKAN 3: Tampilkan konfirmasi =====
        Swal.fire({
            title: 'Aktifkan Semua Notifikasi?',
            html: `
                <div style="text-align: left; margin: 10px 0;">
                    <p style="margin: 5px 0;">📊 <strong>${inactiveCount}</strong> siswa akan diaktifkan</p>
                    <p style="margin: 5px 0; font-size: 0.9rem; color: #666;">
                        Siswa yang sudah aktif tidak akan berubah
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Aktifkan Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // ===== PROSES AKTIVASI =====
                const originalHtml = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
                btn.prop('disabled', true);
                
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mengaktifkan notifikasi untuk semua siswa',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'notif_whatsapp/update_notifikasi.php',
                    type: 'POST',
                    data: {
                        action: 'aktifkan_semua'
                    },
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // ===== PERBAIKAN 4: Update UI dengan benar =====
                            const updatedFromServer = response.updated || 0;
                            
                            if (updatedFromServer > 0) {
                                // Update switch yang belum aktif saja
                                let updatedInUI = 0;
                                $('.custom-control-input').each(function() {
                                    if (!$(this).prop('checked')) {
                                        $(this).prop('checked', true);
                                        const nokartu = $(this).data('nokartu');
                                        $('#label_' + nokartu).text('✅ Aktif');
                                        updatedInUI++;
                                    }
                                });
                                
                                // ===== PERBAIKAN 5: Update statistik =====
                                updateStatistics(updatedInUI);
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    html: `
                                        <div style="margin: 10px 0;">
                                            <p style="font-size: 1.2rem; font-weight: bold; color: #059669;">
                                                ✅ ${updatedInUI} siswa diaktifkan
                                            </p>
                                            <p style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                                Semua siswa sekarang aktif
                                            </p>
                                        </div>
                                    `,
                                    timer: 3000,
                                    timerProgressBar: true,
                                    confirmButtonColor: '#059669'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Informasi',
                                    text: 'Semua siswa sudah aktif',
                                    timer: 2000,
                                    timerProgressBar: true,
                                    confirmButtonColor: '#4831d4'
                                });
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: response.message || 'Terjadi kesalahan',
                                confirmButtonColor: '#dc2626'
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.close();
                        console.error('Error:', xhr.responseText);
                        
                        let errorMsg = 'Terjadi kesalahan server';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) errorMsg = response.message;
                        } catch(e) {}
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMsg,
                            confirmButtonColor: '#dc2626'
                        });
                    },
                    complete: function() {
                        btn.html(originalHtml);
                        btn.prop('disabled', false);
                    }
                });
            }
        });
    });

    // ===== PERBAIKAN 6: Fungsi update statistik =====
    function updateStatistics(updatedCount) {
        // Update jumlah notifikasi aktif di header
        const currentActive = parseInt($('.stat-chip:eq(1) .number').text()) || 0;
        const newTotal = currentActive + updatedCount;
        $('.stat-chip:eq(1) .number').text(newTotal);
        
        // Update badge count di tabel
        const totalSiswa = parseInt($('.badge-count').text()) || 0;
        // Badge count sudah otomatis update karena menggunakan PHP, 
        // tapi kita bisa refresh halaman atau update dengan AJAX
        // Untuk sementara, kita reload page setelah delay
        setTimeout(() => {
            location.reload();
        }, 1500);
    }

    // ===== UPDATE NOTIFIKASI =====
    $(document).on('change', '.custom-control-input', function() {
        const nokartu = $(this).data('nokartu');
        const status = $(this).prop('checked') ? 1 : 0;
        const label = $('#label_' + nokartu);
        
        label.text('⏳ Loading...');
        
        $.ajax({
            url: 'notif_whatsapp/update_notifikasi.php',
            type: 'POST',
            data: {
                nokartu: nokartu,
                status: status
            },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    label.text(status ? '✅ Aktif' : '⛔ Nonaktif');
                    showToast('success', 'Berhasil', 'Status notifikasi diupdate');
                } else {
                    showToast('error', 'Gagal', response.message || 'Terjadi kesalahan');
                    $(this).prop('checked', !$(this).prop('checked'));
                    label.text(status ? '⛔ Nonaktif' : '✅ Aktif');
                }
            }.bind(this),
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                showToast('error', 'Error', 'Terjadi kesalahan server');
                $(this).prop('checked', !$(this).prop('checked'));
                label.text(status ? '⛔ Nonaktif' : '✅ Aktif');
            }.bind(this)
        });
    });

    // ===== UPDATE NOMOR WA =====
    $(document).on('click', '.btn-update-wa', function() {
        const nokartu = $(this).data('nokartu');
        const nomor = $('#wa_' + nokartu).val().trim();
        
        if (!nomor) {
            showToast('warning', 'Peringatan', 'Nomor WhatsApp tidak boleh kosong');
            return;
        }
        
        // Validate phone number format
        const cleaned = nomor.replace(/[^0-9]/g, '');
        if (!cleaned.startsWith('62') && !cleaned.startsWith('08')) {
            showToast('warning', 'Peringatan', 'Nomor harus dimulai dengan 62 atau 08');
            return;
        }
        
        const btn = $(this);
        const originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i>');
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'notif_whatsapp/update_notifikasi.php',
            type: 'POST',
            data: {
                nokartu: nokartu,
                nomor_whatsapp: nomor
            },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    showToast('success', 'Berhasil', 'Nomor WhatsApp diupdate');
                } else {
                    showToast('error', 'Gagal', response.message || 'Terjadi kesalahan');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                showToast('error', 'Error', 'Terjadi kesalahan server');
            },
            complete: function() {
                btn.html(originalHtml);
                btn.prop('disabled', false);
            }
        });
    });

    // ===== TEST NOTIFIKASI =====
    $(document).on('click', '.btn-test-notif', function() {
        const nokartu = $(this).data('nokartu');
        
        const btn = $(this);
        const originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i>');
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'notif_whatsapp/kirim_notifikasi.php',
            type: 'GET',
            data: {
                nokartu: nokartu,
                test: 1
            },
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                if (response.success) {
                    showToast('success', '✅ Berhasil', response.message || 'Notifikasi terkirim');
                } else {
                    showToast('error', '❌ Gagal', response.message || 'Gagal mengirim notifikasi');
                }
            },
            error: function(xhr) {
                console.error('Error:', xhr.responseText);
                showToast('error', 'Error', 'Terjadi kesalahan server');
            },
            complete: function() {
                btn.html(originalHtml);
                btn.prop('disabled', false);
            }
        });
    });

    // ===== SHOW TOAST =====
    function showToast(type, title, message) {
        const colors = {
            success: '#059669',
            error: '#dc2626',
            warning: '#d97706'
        };
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️'
        };
        
        const toast = $(`
            <div class="toast-custom" style="border-color: ${colors[type]};">
                <div>
                    <span class="toast-icon">${icons[type]}</span>
                    <span class="toast-title" style="color: ${colors[type]};">${title}</span>
                </div>
                <p class="toast-message">${message}</p>
            </div>
        `);
        
        // Tambahkan style untuk toast jika belum ada
        if ($('.toast-custom-style').length === 0) {
            $('head').append(`
                <style class="toast-custom-style">
                    .toast-custom {
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        background: white;
                        padding: 16px 20px;
                        border-radius: 12px;
                        border-left: 4px solid;
                        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
                        z-index: 9999;
                        max-width: 400px;
                        animation: slideInUp 0.4s ease;
                    }
                    .toast-custom .toast-icon {
                        font-size: 1.2rem;
                        margin-right: 8px;
                    }
                    .toast-custom .toast-title {
                        font-weight: 600;
                        font-size: 0.9rem;
                    }
                    .toast-custom .toast-message {
                        margin: 4px 0 0 0;
                        font-size: 0.8rem;
                        color: #555;
                    }
                    @keyframes slideInUp {
                        from { transform: translateY(20px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                </style>
            `);
        }
        
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // ===== PERBAIKAN 7: Fungsi refresh statistik =====
    function refreshStatistics() {
        $.ajax({
            url: 'notif_whatsapp/get_statistik.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('.stat-chip:eq(0) .number').text(response.total_siswa);
                    $('.stat-chip:eq(1) .number').text(response.total_aktif);
                }
            },
            error: function() {
                console.log('Gagal refresh statistik');
            }
        });
    }

    console.log('📋 Manajemen Siswa & Notifikasi WhatsApp siap!');
    console.log('💡 Update nomor dan status notifikasi siswa');
});
</script>

</body>
</html>