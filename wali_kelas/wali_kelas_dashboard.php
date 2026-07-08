<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Hanya wali_kelas yang bisa mengakses
if ($_SESSION['role'] !== 'wali_kelas') {
    header("Location: menu.php");
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// Ambil data wali_kelas berdasarkan session
$username = $_SESSION['username'];
$kelas_wali = $_SESSION['kelas'] ?? ''; // Ambil kelas dari session

// ===== PERBAIKAN: Gunakan prepared statement =====
$query_wali = "SELECT * FROM wali_kelas WHERE username = ?";
$stmt_wali = $conek->prepare($query_wali);
if ($stmt_wali) {
    $stmt_wali->bind_param("s", $username);
    $stmt_wali->execute();
    $result_wali = $stmt_wali->get_result();
    $data_wali = $result_wali->fetch_assoc();
    // Jika kelas belum ada di session, ambil dari database
    if (empty($kelas_wali) && isset($data_wali['kelas'])) {
        $kelas_wali = $data_wali['kelas'];
        $_SESSION['kelas'] = $kelas_wali;
    }
    $stmt_wali->close();
} else {
    $data_wali = null;
}

// Jika tidak ada kelas, tampilkan error
if (empty($kelas_wali)) {
    $error_message = "Data kelas wali tidak ditemukan! Silahkan hubungi admin.";
}

// ===== AMBIL DATA SISWA HANYA DARI KELAS WALI =====
$query_siswa = "SELECT * FROM siswa WHERE kelas = ?";
$params = array($kelas_wali);
$types = "s";

// Filter pencarian nama (opsional)
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

if (!empty($search_nama)) {
    $query_siswa .= " AND nama_siswa LIKE ?";
    $params[] = "%" . $search_nama . "%";
    $types .= "s";
}

$query_siswa .= " ORDER BY nama_siswa";

// Eksekusi query dengan prepared statement
$result_siswa = null;
$stmt_siswa = $conek->prepare($query_siswa);
if ($stmt_siswa) {
    $stmt_siswa->bind_param($types, ...$params);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    $stmt_siswa->close();
}

// ===== AMBIL DATA ABSENSI HARI INI (HANYA SISWA DARI KELAS WALI) =====
$query_absensi_hari = "SELECT a.*, s.nama_siswa, s.kelas 
                       FROM absensi_siswa a 
                       JOIN siswa s ON a.nokartu = s.nokartu 
                       WHERE DATE(a.tanggal) = CURDATE()
                       AND s.kelas = ?";

$absensi_params = array($kelas_wali);
$absensi_types = "s";

// Filter tambahan
if (!empty($search_nama)) {
    $query_absensi_hari .= " AND s.nama_siswa LIKE ?";
    $absensi_params[] = "%" . $search_nama . "%";
    $absensi_types .= "s";
}

$query_absensi_hari .= " ORDER BY a.tanggal DESC, a.jam_masuk_pertama DESC";

// Eksekusi query absensi dengan filter
$result_absensi_hari = null;
$stmt_absensi = $conek->prepare($query_absensi_hari);
if ($stmt_absensi) {
    $stmt_absensi->bind_param($absensi_types, ...$absensi_params);
    $stmt_absensi->execute();
    $result_absensi_hari = $stmt_absensi->get_result();
    $stmt_absensi->close();
}

// ===== AMBIL DATA SISWA YANG BELUM ABSENSI HARI INI (HANYA DARI KELAS WALI) =====
$query_belum_absensi = "SELECT s.* 
                        FROM siswa s 
                        WHERE s.kelas = ?
                        AND s.nokartu NOT IN (
                            SELECT a.nokartu 
                            FROM absensi_siswa a 
                            WHERE DATE(a.tanggal) = CURDATE()
                        )";

$belum_params = array($kelas_wali);
$belum_types = "s";

// Filter tambahan
if (!empty($search_nama)) {
    $query_belum_absensi .= " AND s.nama_siswa LIKE ?";
    $belum_params[] = "%" . $search_nama . "%";
    $belum_types .= "s";
}

$query_belum_absensi .= " ORDER BY s.nama_siswa ASC";

// Eksekusi query belum absensi dengan filter
$result_belum_absensi = null;
$stmt_belum = $conek->prepare($query_belum_absensi);
if ($stmt_belum) {
    $stmt_belum->bind_param($belum_types, ...$belum_params);
    $stmt_belum->execute();
    $result_belum_absensi = $stmt_belum->get_result();
    $stmt_belum->close();
}

// ===== HITUNG STATISTIK =====
// Total siswa
$total_siswa = ($result_siswa) ? $result_siswa->num_rows : 0;

// Absensi hari ini
$absensi_hari_ini = ($result_absensi_hari) ? $result_absensi_hari->num_rows : 0;

// Siswa belum absensi
$belum_absensi = ($result_belum_absensi) ? $result_belum_absensi->num_rows : 0;

// Hitung status absensi hari ini
$hadir = 0;
$izin = 0;
$sakit = 0;
$alpha = 0;

if ($result_absensi_hari && $result_absensi_hari->num_rows > 0) {
    $result_absensi_hari->data_seek(0);
    while ($row = $result_absensi_hari->fetch_assoc()) {
        $keterangan = isset($row['keterangan']) ? $row['keterangan'] : '';
        switch ($keterangan) {
            case 'Hadir': $hadir++; break;
            case 'Izin': $izin++; break;
            case 'Sakit': $sakit++; break;
            case 'Alfa': $alpha++; break;
            default: break;
        }
    }
    $result_absensi_hari->data_seek(0);
}

// ===== AMBIL DATA ABSENSI DETAIL =====
$query_absensi_detail = "SELECT a.*, s.nama_siswa, s.kelas 
                         FROM absensi_siswa a 
                         JOIN siswa s ON a.nokartu = s.nokartu 
                         WHERE DATE(a.tanggal) = CURDATE()
                         AND s.kelas = ?";

$detail_params = array($kelas_wali);
$detail_types = "s";

if (!empty($search_nama)) {
    $query_absensi_detail .= " AND s.nama_siswa LIKE ?";
    $detail_params[] = "%" . $search_nama . "%";
    $detail_types .= "s";
}

$query_absensi_detail .= " ORDER BY a.tanggal DESC, a.id DESC";

$result_absensi_detail = null;
$stmt_detail = $conek->prepare($query_absensi_detail);
if ($stmt_detail) {
    $stmt_detail->bind_param($detail_types, ...$detail_params);
    $stmt_detail->execute();
    $result_absensi_detail = $stmt_detail->get_result();
    $stmt_detail->close();
}

$list_absensi = array();
if ($result_absensi_detail && $result_absensi_detail->num_rows > 0) {
    while ($row = $result_absensi_detail->fetch_assoc()) {
        $list_absensi[] = $row;
    }
}

// ===== AMBIL DATA SISWA BELUM ABSEN =====
$query_belum_detail = "SELECT s.* 
                       FROM siswa s 
                       LEFT JOIN absensi_siswa a ON s.nokartu = a.nokartu AND DATE(a.tanggal) = CURDATE()
                       WHERE s.kelas = ?
                       AND a.nokartu IS NULL";

$belum_detail_params = array($kelas_wali);
$belum_detail_types = "s";

if (!empty($search_nama)) {
    $query_belum_detail .= " AND s.nama_siswa LIKE ?";
    $belum_detail_params[] = "%" . $search_nama . "%";
    $belum_detail_types .= "s";
}

$query_belum_detail .= " ORDER BY s.nama_siswa";

$result_belum_detail = null;
$stmt_belum_detail = $conek->prepare($query_belum_detail);
if ($stmt_belum_detail) {
    $stmt_belum_detail->bind_param($belum_detail_types, ...$belum_detail_params);
    $stmt_belum_detail->execute();
    $result_belum_detail = $stmt_belum_detail->get_result();
    $stmt_belum_detail->close();
}

$list_belum = array();
if ($result_belum_detail && $result_belum_detail->num_rows > 0) {
    while ($row = $result_belum_detail->fetch_assoc()) {
        $list_belum[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Wali Kelas - Absensi RFID</title>
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

        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #059669, #34d399); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
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

        .stat-card .stat-icon.yellow {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
        }

        .stat-card .stat-icon.green {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .stat-card .stat-icon.blue {
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
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

        .stat-card .stat-change.down {
            color: #dc2626;
        }

        /* ===== SEARCH BAR ===== */
        .search-section {
            padding: 0 1.5rem 1.2rem 1.5rem;
        }

        .search-container {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 1rem 1.2rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            align-items: flex-end;
        }

        .search-group {
            flex: 1;
            min-width: 150px;
        }

        .search-group label {
            display: block;
            font-size: 0.6rem;
            font-weight: 600;
            color: #555577;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .search-group input {
            width: 100%;
            padding: 0.5rem 0.8rem;
            border: 1px solid rgba(72, 49, 212, 0.10);
            border-radius: 8px;
            font-size: 0.7rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            color: #1a1a2e;
        }

        .search-group input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.08);
            background: rgba(255, 255, 255, 0.8);
        }

        .search-group input::placeholder {
            color: #9999aa;
            font-size: 0.65rem;
        }

        .search-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-search {
            padding: 0.5rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
        }

        .btn-search.primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #1a1a2e;
        }

        .btn-search.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-search.secondary {
            background: rgba(156, 163, 175, 0.12);
            color: #6b7280;
        }

        .btn-search.secondary:hover {
            background: rgba(156, 163, 175, 0.2);
            transform: translateY(-2px);
        }

        .btn-search i {
            font-size: 0.65rem;
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

        .content-header h2 {
            color: #1a1a2e;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .content-header h2 i {
            color: #4831d4;;
            font-size: 0.8rem;
        }

        .content-header .badge-count {
            background: rgba(245, 158, 11, 0.08);
            color: #4831d4;;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
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

        .status-badge.Hadir {
            background: rgba(5, 150, 105, 0.12);
            color: #059669;
        }

        .status-badge.Izin {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
        }

        .status-badge.Sakit {
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
        }

        .status-badge.Alfa {
            background: rgba(220, 38, 38, 0.12);
            color: #dc2626;
        }

        .status-badge.belum {
            background: rgba(156, 163, 175, 0.15);
            color: #6b7280;
        }

        /* ===== FILTER INDICATOR ===== */
        .filter-indicator {
            font-size: 0.6rem;
            color: #6b7280;
            padding: 2px 10px;
            background: rgba(245, 158, 11, 0.05);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .filter-indicator i {
            color: #f59e0b;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container-fluid {
                padding: 0;
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                padding: 0 0.8rem 0.8rem 0.8rem;
                gap: 12px;
            }

            .search-section {
                padding: 0 0.8rem 0.8rem 0.8rem;
            }

            .content-section1,
            .content-section2 {
                margin: 0 0.8rem 0.8rem 0.8rem;
                padding: 0.8rem;
            }
        }

        @media (max-width: 600px) {
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

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 0 0.5rem 0.5rem 0.5rem;
            }

            .search-container {
                flex-direction: column;
                align-items: stretch;
                padding: 0.8rem;
            }

            .search-group {
                min-width: 100%;
            }

            .search-actions {
                width: 100%;
                justify-content: stretch;
            }

            .search-actions .btn-search {
                flex: 1;
                justify-content: center;
            }

            .content-section1,
            .content-section2 {
                padding: 0.7rem;
                margin: 0 0.5rem 0.5rem 0.5rem;
            }

            .stat-card h3 {
                font-size: 1.2rem;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
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

            .status-badge {
                font-size: 0.5rem;
                padding: 1px 8px;
            }

            .info-kelas {
                font-size: 0.6rem;
                padding: 0.3rem 0.7rem;
            }
        }
    </style>
</head>
<body>

<?php include "menu.php"; ?>

<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">📚</div>
            <div class="header-text">
                <h1>Dashboard Wali Kelas</h1>
                <div class="sub">
                    Selamat datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Wali Kelas'); ?> 
                    <?php if (!empty($kelas_wali)): ?>
                        <span style="background: rgba(245,158,11,0.12); padding: 2px 12px; border-radius: 12px; font-weight: 600; color: #f59e0b;">
                            <i class="fas fa-school"></i> Kelas <?php echo htmlspecialchars($kelas_wali); ?>
                        </span>
                    <?php endif; ?>
                </div>
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
                <div class="stat-icon yellow"><i class="fas fa-user-graduate"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Total</span>
            </div>
            <h3><?php echo number_format($total_siswa); ?></h3>
            <p>Total Siswa Kelas <?php echo htmlspecialchars($kelas_wali); ?></p>
            <div class="stat-change up">
                <i class="fas fa-users"></i> Data terbaru
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Hari Ini</span>
            </div>
            <h3><?php echo number_format($absensi_hari_ini); ?></h3>
            <p>Sudah Absensi</p>
            <div class="stat-change up">
                <i class="fas fa-clock"></i> <?php echo date('H:i'); ?>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon blue"><i class="fas fa-user-check"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> Hadir</span>
            </div>
            <h3><?php echo number_format($hadir); ?></h3>
            <p>Siswa Hadir</p>
            <div class="stat-change up">
                <i class="fas fa-check-circle"></i> <?php echo $total_siswa > 0 ? round(($hadir/$total_siswa)*100, 1) : 0; ?>%
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
                <span class="stat-trend"><i class="fas fa-arrow-down"></i> Belum</span>
            </div>
            <h3><?php echo number_format($belum_absensi); ?></h3>
            <p>Belum Absensi</p>
            <div class="stat-change down">
                <i class="fas fa-exclamation-circle"></i> <?php echo $total_siswa > 0 ? round(($belum_absensi/$total_siswa)*100, 1) : 0; ?>%
            </div>
        </div>
    </div>

    <!-- ===== SEARCH SECTION ===== -->
    <div class="search-section">
        <div class="search-container">
            
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 0.8rem; flex: 1; align-items: flex-end;">
                <div class="search-group">
                    <label for="search_nama"><i class="fas fa-user"></i> Cari Nama Siswa</label>
                    <input type="text" id="search_nama" name="search_nama" 
                           placeholder="Masukkan nama siswa..." 
                           value="<?php echo htmlspecialchars($search_nama); ?>">
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn-search primary">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if (!empty($search_nama)): ?>
                        <a href="dashboard_wali_kelas.php" class="btn-search secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if (!empty($search_nama)): ?>
        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <span class="filter-indicator">
                <i class="fas fa-filter"></i> Filter aktif:
            </span>
            <span class="filter-indicator">
                <i class="fas fa-user"></i> Nama: <?php echo htmlspecialchars($search_nama); ?>
            </span>
            <span style="font-size: 0.6rem; color: #6b7280;">
                (Menampilkan <?php echo $total_siswa; ?> data)
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== ABSENSI HARI INI ===== -->
    <div class="content-section1">
        <div class="content-header">
            <h2><i class="fas fa-calendar-day"></i> Absensi Hari Ini</h2>
            <span class="badge-count"><?php echo count($list_absensi); ?> data</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>Waktu</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($list_absensi)) {
                        $no = 1;
                        foreach ($list_absensi as $row) {
                    ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_siswa'] ?? '-'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['kelas'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($row['jam_masuk_pertama'])) {
                                        echo date('H:i:s', strtotime($row['jam_masuk_pertama']));
                                    } elseif (!empty($row['tanggal'])) {
                                        echo date('H:i:s', strtotime($row['tanggal']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo isset($row['keterangan']) ? $row['keterangan'] : 'belum'; ?>">
                                        <?php echo isset($row['keterangan']) ? ucfirst($row['keterangan']) : 'Belum'; ?>
                                    </span>
                                </td>
                            </tr>
                    <?php
                        }
                        if ($no == 1) {
                            echo "<tr><td colspan='5' style='text-align: center; color: #6c757d; padding: 15px; font-size: 0.7rem;'>Tidak ada absensi hari ini untuk kelas Anda</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; color: #6c757d; padding: 15px; font-size: 0.7rem;'>Belum ada absensi hari ini untuk kelas Anda</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== SISWA BELUM ABSENSI ===== -->
    <div class="content-section2">
        <div class="content-header">
            <h2><i class="fas fa-user-clock"></i> Siswa Belum Absensi</h2>
            <span class="badge-count"><?php echo count($list_belum); ?> siswa</span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Siswa</th>
                        <th>Kelas</th>
                        <th>NIS</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($list_belum)) {
                        $no = 1;
                        foreach ($list_belum as $row) {
                    ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['nama_siswa'] ?? '-'); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['kelas'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['nis'] ?? '-'); ?></td>
                                <td><span class="status-badge belum">
                                    <i class="fas fa-clock"></i> Belum Absen
                                </span></td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align: center; color: #059669; padding: 15px; font-size: 0.8rem;'>✅ Semua siswa di kelas Anda sudah melakukan absensi hari ini!</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>