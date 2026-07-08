<?php
// ===== AWAL: Session dan koneksi =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// ===== TENTUKAN HALAMAN KEMBALI (DIPERBAIKI) =====
$return_page = '../admin/data_siswa.php'; // Default

// 1. PRIORITAS UTAMA: Parameter return di URL (paling akurat)
if (isset($_GET['return'])) {
    $return = $_GET['return'];
    if ($return === 'admin') {
        $return_page = '../admin/data_siswa.php';
    } elseif ($return === 'wali') {
        $return_page = '../wali_kelas/data_siswa.php';
    }
} 
// 2. KEDUA: Cek dari mana pengguna datang (HTTP_REFERER)
else {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Cek apakah dari halaman wali kelas
    if (strpos($referer, 'wali_kelas') !== false || 
        strpos($referer, '/wali_kelas/') !== false) {
        $return_page = '../wali_kelas/data_siswa.php';
    } 
    // Cek apakah dari halaman admin
    elseif (strpos($referer, 'admin') !== false || 
            strpos($referer, '/admin/') !== false) {
        $return_page = '../admin/data_siswa.php';
    }
    // 3. TERAKHIR: Gunakan session role sebagai fallback
    elseif (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            $return_page = '../admin/data_siswa.php';
        } elseif ($_SESSION['role'] === 'wali_kelas') {
            $return_page = '../wali_kelas/data_siswa.php';
        }
    }
}

// ===== CEK ROLE USER =====
$role = $_SESSION['role'] ?? 'admin';
$isAdmin = ($role === 'admin');
$isWaliKelas = ($role === 'wali_kelas');

// ===== AMBIL DATA SISWA =====
if (!isset($_GET['id'])) {
    header("Location: " . $return_page . "?error=ID siswa tidak ditemukan");
    exit();
}

$id = intval($_GET['id']);

// Gunakan prepared statement untuk keamanan
$query = "SELECT * FROM siswa WHERE id = ?";
$stmt = $conek->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();
    $stmt->close();
}

if (!$siswa) {
    header("Location: " . $return_page . "?error=Data siswa tidak ditemukan");
    exit();
}

// ===== AMBIL RIWAYAT =====
$riwayat = [];
$queryRiwayat = "SELECT * FROM riwayat_siswa WHERE siswa_id = ? ORDER BY id DESC LIMIT 10";
$stmtRiwayat = $conek->prepare($queryRiwayat);
if ($stmtRiwayat) {
    $stmtRiwayat->bind_param("i", $id);
    $stmtRiwayat->execute();
    $resultRiwayat = $stmtRiwayat->get_result();
    while ($row = $resultRiwayat->fetch_assoc()) {
        $riwayat[] = $row;
    }
    $stmtRiwayat->close();
}

// ===== FOTO PROFIL =====
$fotoSiswa = $siswa['foto'] ?? '';
$pathFoto = __DIR__ . "/foto_siswa/" . $fotoSiswa;
$fotoDisplay = (!empty($fotoSiswa) && file_exists($pathFoto)) ? $fotoSiswa : 'default.png';

// ===== TENTUKAN RETURN UNTUK FORM =====
$returnValue = isset($_GET['return']) ? $_GET['return'] : (isset($_SESSION['role']) && $_SESSION['role'] === 'wali_kelas' ? 'wali' : 'admin');

// ===== TAMPILAN BERDASARKAN ROLE =====
$isReadOnly = $isWaliKelas; // Wali kelas hanya bisa melihat
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Siswa - <?= htmlspecialchars($siswa['nama_siswa'] ?? '') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        /* ===== MAIN CONTAINER ===== */
        .container-fluid {
            position: relative;
            z-index: 1;
            padding: 0;
            width: calc(100%);
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== HEADER MODERN ===== */
        .header-modern {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
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

        /* ===== BADGE ROLE ===== */
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0.5rem;
        }

        .role-badge.admin {
            background: rgba(72, 49, 212, 0.15);
            color: #4831d4;
        }

        .role-badge.wali {
            background: rgba(5, 150, 105, 0.15);
            color: #059669;
        }

        /* ===== BUTTON BACK ===== */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 1rem;
            border-radius: 8px;
            background: rgba(72, 49, 212, 0.10);
            color: #4831d4;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(72, 49, 212, 0.10);
        }

        .btn-back:hover {
            background: rgba(72, 49, 212, 0.20);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.15);
        }

        /* ===== PROFILE CARD ===== */
        .profile-wrapper {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.08);
            position: relative;
            z-index: 1;
        }

        /* ===== READ-ONLY OVERLAY ===== */
        .readonly-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.01);
            pointer-events: none;
            border-radius: 16px;
            z-index: 0;
        }

        .profile-row {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .profile-info {
            flex: 1;
            min-width: 280px;
        }

        .profile-photo {
            flex-shrink: 0;
            text-align: center;
        }

        .profile-photo img {
            width: 150px;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            border: 3px solid rgba(108, 99, 255, 0.20);
            box-shadow: 0 8px 25px rgba(72, 49, 212, 0.15);
            transition: all 0.3s ease;
        }

        .profile-photo img:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 35px rgba(72, 49, 212, 0.25);
        }

        .profile-photo .photo-label {
            margin-top: 0.5rem;
            font-size: 0.65rem;
            color: #8888aa;
            font-weight: 500;
            display: block;
        }

        /* ===== INFO ITEMS ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.4rem 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: baseline;
            padding: 0.3rem 0;
            border-bottom: 1px solid rgba(72, 49, 212, 0.05);
        }

        .info-item .label {
            font-weight: 600;
            color: #555577;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            min-width: 100px;
            flex-shrink: 0;
        }

        .info-item .colon {
            color: #8888aa;
            margin: 0 0.5rem;
            font-weight: 300;
        }

        .info-item .value {
            color: #1a1a2e;
            font-size: 0.8rem;
            font-weight: 500;
            word-break: break-word;
        }

        /* ===== FORM UPLOAD ===== */
        .upload-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(72, 49, 212, 0.08);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.6rem;
        }

        .upload-form label {
            font-weight: 600;
            color: #555577;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .upload-form input[type="file"] {
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            border: 1px solid rgba(72, 49, 212, 0.12);
            background: rgba(255, 255, 255, 0.7);
            color: #1a1a2e;
            font-size: 0.7rem;
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
        }

        .upload-form input[type="file"]:focus {
            outline: none;
            border-color: #6c63ff;
        }

        .upload-form input[type="file"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn {
            padding: 0.4rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #fff;
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
        }

        .btn-primary:hover:not(:disabled) {
            box-shadow: 0 6px 25px rgba(72, 49, 212, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #ef4444);
            color: #fff;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.25);
        }

        .btn-danger:hover:not(:disabled) {
            box-shadow: 0 6px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #34d399);
            color: #fff;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.25);
        }

        .btn-success:hover:not(:disabled) {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.4);
        }

        .btn-secondary {
            background: rgba(108, 99, 255, 0.10);
            color: #555577;
            border: 1px solid rgba(108, 99, 255, 0.10);
            cursor: not-allowed;
        }

        /* ===== READ-ONLY NOTICE ===== */
        .readonly-notice {
            background: rgba(5, 150, 105, 0.08);
            border: 1px solid rgba(5, 150, 105, 0.15);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin-top: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #059669;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .readonly-notice i {
            font-size: 0.9rem;
        }

        /* ===== RIWAYAT SECTION ===== */
        .riwayat-wrapper {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.08);
            position: relative;
            z-index: 1;
        }

        .riwayat-wrapper h3 {
            color: #1a1a2e;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .riwayat-wrapper h3 i {
            color: #4831d4;
        }

        .riwayat-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }

        .riwayat-table th {
            background: rgba(72, 49, 212, 0.04);
            padding: 0.5rem 0.8rem;
            text-align: left;
            color: #555577;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(72, 49, 212, 0.06);
        }

        .riwayat-table td {
            padding: 0.4rem 0.8rem;
            color: #1a1a2e;
            border-bottom: 1px solid rgba(72, 49, 212, 0.05);
            font-size: 0.7rem;
        }

        .riwayat-table tr:hover td {
            background: rgba(108, 99, 255, 0.04);
        }

        .riwayat-table tr:last-child td {
            border-bottom: none;
        }

        .riwayat-empty {
            text-align: center;
            padding: 1.5rem;
            color: rgba(26, 26, 46, 0.4);
            font-size: 0.8rem;
        }

        /* ===== DARK MODE TOGGLE ===== */
        .dark-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            color: #1a1a2e;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.10);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dark-toggle:hover {
            transform: scale(1.1) rotate(20deg);
            box-shadow: 0 6px 30px rgba(72, 49, 212, 0.20);
        }

        body.dark {
            background: #0a0f1a !important;
        }

        body.dark .header-modern,
        body.dark .profile-wrapper,
        body.dark .riwayat-wrapper {
            background: rgba(20, 25, 40, 0.85);
            border-color: rgba(255, 255, 255, 0.05);
        }

        body.dark .header-text h1,
        body.dark .header-text .sub,
        body.dark .info-item .value,
        body.dark .riwayat-wrapper h3,
        body.dark .riwayat-table td {
            color: #e0e0e0;
        }

        body.dark .info-item .label,
        body.dark .riwayat-table th {
            color: #8888aa;
        }

        body.dark .dark-toggle {
            background: rgba(20, 25, 40, 0.85);
            color: #fbbf24;
        }

        body.dark .readonly-notice {
            background: rgba(5, 150, 105, 0.15);
            border-color: rgba(5, 150, 105, 0.20);
            color: #34d399;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-modern {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            .profile-wrapper {
                padding: 1rem;
            }

            .profile-row {
                flex-direction: column-reverse;
                align-items: center;
            }

            .profile-photo img {
                width: 120px;
                height: 140px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .upload-form {
                flex-direction: column;
                align-items: stretch;
            }

            .upload-form input[type="file"] {
                width: 100%;
            }

            .riwayat-wrapper {
                padding: 1rem;
            }

            .riwayat-table {
                font-size: 0.6rem;
            }

            .riwayat-table th,
            .riwayat-table td {
                padding: 0.3rem 0.5rem;
            }
        }

        @media (max-width: 600px) {
            .header-text h1 {
                font-size: 1rem;
            }

            .profile-photo img {
                width: 100px;
                height: 120px;
            }

            .info-item {
                flex-wrap: wrap;
            }

            .info-item .label {
                min-width: 80px;
                font-size: 0.65rem;
            }

            .info-item .value {
                font-size: 0.7rem;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
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
</head>
<body>

<?php include "menu.php"; ?>

<div class="container-fluid">

    <!-- ===== HEADER ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">👤</div>
            <div class="header-text">
                <h1>
                    Profil Siswa
                    <?php if ($isAdmin): ?>
                        <span class="role-badge admin"><i class="fas fa-user-shield"></i> Admin</span>
                    <?php else: ?>
                        <span class="role-badge wali"><i class="fas fa-chalkboard-teacher"></i> Wali Kelas</span>
                    <?php endif; ?>
                </h1>
                <div class="sub">
                    <?= $isAdmin ? 'Informasi lengkap data siswa' : 'Informasi lengkap data siswa' ?>
                </div>
            </div>
        </div>
        <a href="<?= $return_page ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- ===== DARK MODE TOGGLE ===== -->
    <button class="dark-toggle" onclick="toggleDark()" id="darkToggle">
        <i id="darkIcon" class="fas fa-moon"></i>
    </button>

    <!-- ===== PROFIL ===== -->
    <div class="profile-wrapper">
        <?php if ($isReadOnly): ?>
            <div class="readonly-overlay"></div>
        <?php endif; ?>
        
        <div class="profile-row">
            <!-- Informasi -->
            <div class="profile-info">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Nama Siswa</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['nama_siswa'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">NIS</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['nis'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">NIK / Kartu</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['nokartu'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Jenis Kelamin</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['jenis_kelamin'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">TTL</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['ttl'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Kelas</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['kelas'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Jurusan</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['jurusan'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Nama Wali</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['nama_wali'] ?? '-') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">No. HP</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['no_hp'] ?? '-') ?></span>
                    </div>
                    <div class="info-item" style="grid-column: span 2;">
                        <span class="label">Alamat</span>
                        <span class="colon">:</span>
                        <span class="value"><?= htmlspecialchars($siswa['alamat'] ?? '-') ?></span>
                    </div>
                </div>

                <!-- Form Upload - HANYA UNTUK ADMIN -->
                <?php if ($isAdmin): ?>
                    <form action="profil_upload_foto.php" method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="siswa_id" value="<?= $id ?>">
                        <input type="hidden" name="return" value="<?= $returnValue ?>">
                        <label for="fotoInput"><i class="fas fa-camera"></i> Ganti Foto</label>
                        <input type="file" name="foto" id="fotoInput" accept="image/*" required>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                        <?php if (!empty($fotoSiswa) && file_exists($pathFoto)): ?>
                            <a href="profil_hapus_foto.php?id=<?= $id ?>&return=<?= $returnValue ?>" 
                               onclick="return confirm('Yakin ingin menghapus foto profil?')"
                               class="btn btn-danger">
                                <i class="fas fa-trash"></i> Hapus
                            </a>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <!-- READ-ONLY NOTICE untuk Wali Kelas -->
                    <div class="readonly-notice">
                        <i class="fas fa-info-circle"></i>
                        <span>Anda hanya dapat melihat data siswa. Untuk mengubah data, silahkan hubungi Admin.</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Foto -->
            <div class="profile-photo">
                <img src="foto_siswa/<?= htmlspecialchars($fotoDisplay) ?>" 
                     alt="Foto <?= htmlspecialchars($siswa['nama_siswa'] ?? 'Siswa') ?>"
                     onerror="this.src='foto_siswa/default.png'">
                <span class="photo-label">
                    <?= (!empty($fotoSiswa) && file_exists($pathFoto)) ? '✅ Foto Profil' : '📷 Belum ada foto' ?>
                    <?php if ($isReadOnly): ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ===== RIWAYAT ===== -->
    <div class="riwayat-wrapper">
        <h3><i class="fas fa-history"></i> Riwayat Aktivitas</h3>
        <?php if (count($riwayat) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="riwayat-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Aktivitas</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($riwayat as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d-m-Y H:i', strtotime($row['created_at'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($row['aktivitas'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="riwayat-empty">
                <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.3;"></i>
                Belum ada riwayat aktivitas
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
// ===== DARK MODE TOGGLE =====
function toggleDark() {
    document.body.classList.toggle('dark');
    let icon = document.getElementById('darkIcon');
    if (document.body.classList.contains('dark')) {
        icon.className = 'fas fa-sun';
    } else {
        icon.className = 'fas fa-moon';
    }
    localStorage.setItem('darkMode', document.body.classList.contains('dark'));
}

// ===== LOAD DARK MODE STATE =====
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark');
        document.getElementById('darkIcon').className = 'fas fa-sun';
    }
});

// ===== VALIDASI FILE UPLOAD (HANYA UNTUK ADMIN) =====
<?php if ($isAdmin): ?>
document.querySelector('input[name="foto"]')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!validTypes.includes(file.type)) {
            alert('⚠️ Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.');
            this.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            alert('⚠️ Ukuran file terlalu besar! Maksimal 2MB.');
            this.value = '';
            return;
        }
    }
});
<?php endif; ?>
</script>

<?php include "../footer.php"; ?>

</body>
</html>