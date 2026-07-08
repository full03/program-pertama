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

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// ===== AMBIL WAKTU DARI CLIENT =====
$client_time = null;
$client_time_for_comparison = null;

if (isset($_POST['client_time'])) {
    $client_time = $_POST['client_time'];
    $_SESSION['client_time'] = $client_time;
} elseif (isset($_GET['client_time'])) {
    $client_time = $_GET['client_time'];
    $_SESSION['client_time'] = $client_time;
} elseif (isset($_SESSION['client_time'])) {
    $client_time = $_SESSION['client_time'];
}

// Jika ada client_time, gunakan untuk perbandingan
if ($client_time) {
    $client_time_for_comparison = $client_time;
} else {
    // Jika tidak ada, gunakan waktu server
    $client_time_for_comparison = date('H:i:s');
}

// ===== FUNGSI UNTUK CEK JAM ABSEN AKTIF =====
function isJamAbsenActive($conek, $jam_mulai, $jam_selesai, $client_time = null) {
    // Gunakan waktu dari client jika ada, jika tidak gunakan waktu server
    if ($client_time) {
        $waktu_sekarang = $client_time;
    } else {
        $waktu_sekarang = date('H:i:s');
    }
    
    // Konversi ke timestamp untuk perbandingan yang akurat
    $waktu_sekarang_ts = strtotime($waktu_sekarang);
    $jam_mulai_ts = strtotime($jam_mulai);
    $jam_selesai_ts = strtotime($jam_selesai);
    
    // Jika jam_mulai > jam_selesai (melewati tengah malam)
    if ($jam_mulai_ts > $jam_selesai_ts) {
        return ($waktu_sekarang_ts >= $jam_mulai_ts || $waktu_sekarang_ts <= $jam_selesai_ts);
    } else {
        return ($waktu_sekarang_ts >= $jam_mulai_ts && $waktu_sekarang_ts <= $jam_selesai_ts);
    }
}

function hasActiveJamAbsen($conek, $client_time = null) {
    $query = "SELECT * FROM pengaturan_jam_absen";
    $result = mysqli_query($conek, $query);
    
    if (!$result) {
        return false;
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (isJamAbsenActive($conek, $row['jam_mulai'], $row['jam_selesai'], $client_time)) {
            return true;
        }
    }
    return false;
}

// ===== CEK STATUS ABSENSI =====
$absen_aktif = hasActiveJamAbsen($conek, $client_time_for_comparison);

// ===== AMBIL PARAMETER =====
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'harian';
$tanggal_input = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$bulan_input = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_input = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$semester_input = isset($_GET['semester']) ? $_GET['semester'] : '1';
$search_nama = isset($_GET['search_nama']) ? $_GET['search_nama'] : '';

// ===== TENTUKAN TANGGAL FILTER =====
$tanggal_awal_filter = null;
$tanggal_akhir_filter = null;

if ($mode == 'harian') {
    // Harian - satu hari tertentu
    $tanggal_filter = $tanggal_input ? $tanggal_input : date('Y-m-d');
    $tanggal_awal_filter = $tanggal_filter;
    $tanggal_akhir_filter = $tanggal_filter;
} elseif ($mode == 'mingguan') {
    // Mingguan - 7 hari dari tanggal yang dipilih
    $tanggal_akhir = $tanggal_input ? $tanggal_input : date('Y-m-d');
    $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
    $tanggal_awal_filter = $tanggal_awal;
    $tanggal_akhir_filter = $tanggal_akhir;
} elseif ($mode == 'bulanan') {
    // Bulanan - satu bulan penuh
    $bulan = intval($bulan_input);
    $tahun = intval($tahun_input);
    $tanggal_awal_filter = date('Y-m-d', mktime(0, 0, 0, $bulan, 1, $tahun));
    $tanggal_akhir_filter = date('Y-m-t', mktime(0, 0, 0, $bulan, 1, $tahun));
} elseif ($mode == 'semester') {
    // Semester - sesuai dengan semester
    $semester = intval($semester_input);
    $tahun = intval($tahun_input);
    
    if ($semester == 1) {
        // Semester 1: Juli - Desember
        $tanggal_awal_filter = ($tahun - 1) . '-07-01';
        $tanggal_akhir_filter = ($tahun - 1) . '-12-31';
    } else {
        // Semester 2: Januari - Juni
        $tanggal_awal_filter = $tahun . '-01-01';
        $tanggal_akhir_filter = $tahun . '-06-30';
    }
}

// ===== AMBIL DATA DARI RIWAYAT_SISWA =====
$sql = null;
$total_hadir = 0;
$total_sakit = 0;
$total_izin = 0;
$total_alfa = 0;

// HAPUS KONDISI if ($absen_aktif && ...) agar data tetap tampil meskipun absen tidak aktif
if ($tanggal_awal_filter && $tanggal_akhir_filter) {
    // Build query untuk mengambil data dari riwayat_siswa
    $query = "
    SELECT 
        s.nokartu,
        s.nama_siswa,
        s.kelas,
        s.jurusan,
        s.id AS siswa_id,
        COALESCE(SUM(r.hadir), 0) AS hadir,
        COALESCE(SUM(r.sakit), 0) AS sakit,
        COALESCE(SUM(r.izin), 0) AS izin,
        COALESCE(SUM(r.alfa), 0) AS alfa,
        MAX(r.tanggal) AS tanggal_terakhir_absen
    FROM siswa s
    LEFT JOIN riwayat_siswa r ON r.siswa_id = s.id
    WHERE 1=1
    ";

    // Filter berdasarkan rentang tanggal
    $tanggal_awal_clean = mysqli_real_escape_string($conek, $tanggal_awal_filter);
    $tanggal_akhir_clean = mysqli_real_escape_string($conek, $tanggal_akhir_filter);
    $query .= " AND r.tanggal BETWEEN '$tanggal_awal_clean' AND '$tanggal_akhir_clean' ";

    // Filter kelas
    if (!empty($_GET['kelas'])) {
        $kelas = mysqli_real_escape_string($conek, $_GET['kelas']);
        $query .= " AND s.kelas = '$kelas' ";
    }

    // Filter jurusan
    if (!empty($_GET['jurusan'])) {
        $jurusan = mysqli_real_escape_string($conek, $_GET['jurusan']);
        $query .= " AND s.jurusan = '$jurusan' ";
    }

    // ===== TAMBAHKAN FILTER PENCARIAN NAMA =====
    if (!empty($search_nama)) {
        $search_nama_clean = mysqli_real_escape_string($conek, $search_nama);
        $query .= " AND s.nama_siswa LIKE '%$search_nama_clean%' ";
    }

    // PERBAIKAN: Menggunakan CASE untuk menangani NULL di semua versi MySQL
    $query .= " GROUP BY s.nokartu, s.nama_siswa, s.kelas, s.jurusan, s.id 
                ORDER BY 
                    CASE WHEN MAX(r.tanggal) IS NULL THEN 1 ELSE 0 END,
                    MAX(r.tanggal) DESC,
                    s.id ASC";

    $sql = mysqli_query($conek, $query);
    
    // Cek error query
    if (!$sql) {
        die("Error query: " . mysqli_error($conek));
    }
    
    // Hitung total untuk statistik
    if ($sql && mysqli_num_rows($sql) > 0) {
        mysqli_data_seek($sql, 0);
        while ($row = mysqli_fetch_assoc($sql)) {
            $total_hadir += intval($row['hadir']);
            $total_sakit += intval($row['sakit']);
            $total_izin += intval($row['izin']);
            $total_alfa += intval($row['alfa']);
        }
        mysqli_data_seek($sql, 0);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <?php include "../header.php"; ?>

    <title>Rekapitulasi Skor Siswa</title>

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

    /* ===== HEADER ===== */
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

    .header-stats {
        display: flex;
        gap: 1.5rem;
        align-items: center;
        background: rgba(255, 255, 255, 0.5);
        padding: 0.4rem 1.2rem;
        border-radius: 10px;
        border: 1px solid rgba(72, 49, 212, 0.08);
        flex-wrap: wrap;
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

    /* ===== TOP BAR ===== */
    .top-bar {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 12px 12px 0px 0px;
        padding: 0.7rem 1.2rem;
        margin-bottom: 0.1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.7rem;
        box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
        position: relative;
        z-index: 10;
    }

    .rekap-switch {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex-wrap: wrap;
        position: relative;
        z-index: 20;
    }

    .switch-group {
        display: flex;
        gap: 0.2rem;
        background: rgba(72, 49, 212, 0.04);
        padding: 0.2rem;
        border-radius: 10px;
        border: 1px solid rgba(72, 49, 212, 0.06);
    }

    .switch-btn {
        padding: 0.3rem 0.8rem;
        border-radius: 7px;
        border: none;
        background: transparent;
        color: #666688;
        font-weight: 600;
        font-size: 0.65rem;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
    }

    .switch-btn:hover {
        color: #1a1a2e;
        transform: translateY(-1px);
    }

    .switch-btn.active {
        background: linear-gradient(135deg, #4831d4, #6c63ff);
        color: #ffffff;
        box-shadow: 0 4px 15px rgba(72, 49, 212, 0.3);
    }

    .filter-inline {
        position: relative;
        z-index: 30;
    }

    .filter-inline form {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .filter-inline input,
    .filter-inline select {
        padding: 0.3rem 0.7rem;
        border-radius: 8px;
        border: 1px solid rgba(72, 49, 212, 0.12);
        background: rgba(255, 255, 255, 0.7);
        color: #1a1a2e;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        min-height: 30px;
    }

    .filter-inline select option {
        background: #ffffff;
        color: #1a1a2e;
    }

    .filter-inline input:focus,
    .filter-inline select:focus {
        outline: none;
        border-color: #6c63ff;
        background: #ffffff;
        box-shadow: 0 0 20px rgba(72, 49, 212, 0.08);
    }

    .filter-inline input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(0.3);
        cursor: pointer;
    }

    /* ===== STYLE KHUSUS UNTUK INPUT PENCARIAN NAMA ===== */
    .search-input-wrapper {
        position: relative;
        display: inline-block;
    }

    .search-input-wrapper input {
        padding-right: 30px !important;
        min-width: 150px;
    }

    .search-clear-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        font-size: 14px;
        padding: 0;
        line-height: 1;
    }

    .search-clear-btn:hover {
        color: #4831d4;
    }

    .filter-inline .btn-filter {
        padding: 0.3rem 1rem;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #4831d4, #6c63ff);
        color: #fff;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
    }

    .filter-inline .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 25px rgba(72, 49, 212, 0.4);
    }

    .filter-inline .btn-reset {
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
        font-size: 0.9rem;
        text-decoration: none;
    }

    .filter-inline .btn-reset:hover {
        background: rgba(72, 49, 212, 0.12);
        color: #1a1a2e;
        transform: rotate(45deg);
    }

    .export-dropdown {
        position: relative;
        display: inline-block;
        z-index: 100;
    }

    .export-btn-main {
        padding: 0.3rem 1rem;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #d97706, #fbbf24);
        color: #1a1a2e;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 4px 15px rgba(217, 119, 6, 0.2);
        position: relative;
        z-index: 101;
    }

    .export-btn-main:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 25px rgba(217, 119, 6, 0.35);
    }

    .export-menu {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 150px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(24px) saturate(180%);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        border: 1px solid rgba(72, 49, 212, 0.08);
        border-radius: 12px;
        padding: 0.4rem;
        box-shadow: 
            0 25px 80px rgba(0, 0, 0, 0.12),
            0 0 60px rgba(72, 49, 212, 0.04),
            inset 0 1px 0 rgba(255, 255, 255, 0.8);
        z-index: 999999;
        animation: dropDown 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        transform-origin: top right;
        pointer-events: auto;
    }

    .export-menu.show {
        display: block;
    }

    @keyframes dropDown {
        0% { opacity: 0; transform: translateY(-12px) scale(0.95); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    .export-menu a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 1rem;
        text-decoration: none;
        color: #1a1a2e;
        font-size: 0.7rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.25s ease;
        cursor: pointer;
    }

    .export-menu a:hover {
        background: rgba(108, 99, 255, 0.08);
        color: #4831d4;
        padding-left: 1.3rem;
        transform: translateX(3px);
    }

    .export-menu a:active {
        transform: scale(0.97);
    }

    .export-menu a .icon {
        font-size: 1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }

    .export-menu .menu-divider {
        height: 1px;
        background: rgba(72, 49, 212, 0.08);
        margin: 0.2rem 0.4rem;
    }

    .table-wrapper {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px) saturate(180%);
        -webkit-backdrop-filter: blur(12px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 0px 0px 12px 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(72, 49, 212, 0.06);
        position: relative;
        z-index: 1;
    }

    .table-responsive-custom {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.7rem;
    }

    thead {
        background: rgba(72, 49, 212, 0.04);
        border-bottom: 2px solid rgba(72, 49, 212, 0.06);
    }

    thead th {
        padding: 0.6rem 0.8rem;
        text-align: left;
        color: #555577;
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        white-space: nowrap;
    }

    thead th:not(:last-child) {
        border-right: 1px solid rgba(72, 49, 212, 0.04);
    }

    thead th.center {
        text-align: center;
    }

    tbody tr {
        border-bottom: 1px solid rgba(72, 49, 212, 0.05);
        transition: background 0.25s ease;
    }

    tbody tr:hover {
        background: rgba(108, 99, 255, 0.04);
    }

    tbody tr:last-child {
        border-bottom: none;
    }

    tbody td {
        padding: 0.5rem 0.8rem;
        color: #1a1a2e;
        vertical-align: middle;
        font-size: 0.7rem;
    }

    tbody td.center {
        text-align: center;
    }

    .score-badge {
        display: inline-block;
        padding: 0.1rem 0.5rem;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        min-width: 25px;
        text-align: center;
    }

    .score-hadir {
        background: rgba(52, 211, 153, 0.15);
        color: #0b8a5e;
    }

    .score-sakit {
        background: rgba(96, 165, 250, 0.15);
        color: #2563eb;
    }

    .score-izin {
        background: rgba(251, 191, 36, 0.15);
        color: #b45309;
    }

    .score-alfa {
        background: rgba(248, 113, 113, 0.15);
        color: #dc2626;
    }

    .btn-review {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.3rem 0.8rem;
        border-radius: 8px;
        border: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        font-size: 0.65rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
    }

    .btn-review:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
    }

    .empty-state {
        text-align: center;
        padding: 2rem 1.5rem;
        color: rgba(26, 26, 46, 0.4);
    }

    .empty-state .icon {
        font-size: 2.5rem;
        margin-bottom: 0.3rem;
        display: block;
    }

    .empty-state .text {
        font-size: 0.85rem;
        font-weight: 500;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 9999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(8px);
        animation: fadeIn 0.3s ease;
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(72, 49, 212, 0.08);
        border-radius: 16px;
        width: 95%;
        max-width: 950px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
        animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-50px) scale(0.95);
            opacity: 0;
        }
        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 0.8rem 1.2rem;
        background: rgba(72, 49, 212, 0.04);
        border-bottom: 1px solid rgba(72, 49, 212, 0.06);
        border-radius: 16px 16px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .modal-header h4 {
        color: #1a1a2e;
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .modal-header .modal-search {
        display: flex;
        gap: 0.4rem;
        align-items: center;
    }

    .modal-header .modal-search input {
        padding: 0.25rem 0.7rem;
        border-radius: 7px;
        border: 1px solid rgba(72, 49, 212, 0.12);
        background: rgba(255, 255, 255, 0.7);
        color: #1a1a2e;
        font-size: 0.7rem;
        min-width: 160px;
    }

    .modal-header .modal-search input::placeholder {
        color: rgba(26, 26, 46, 0.35);
    }

    .modal-header .modal-search input:focus {
        outline: none;
        border-color: #6c63ff;
    }

    .modal-header .modal-search button {
        padding: 0.25rem 0.7rem;
        border-radius: 7px;
        border: none;
        background: linear-gradient(135deg, #4831d4, #6c63ff);
        color: #fff;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.65rem;
        transition: all 0.3s ease;
    }

    .modal-header .modal-search button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(72, 49, 212, 0.3);
    }

    .close {
        color: rgba(26, 26, 46, 0.3);
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        line-height: 1;
        margin-left: 0.8rem;
    }

    .close:hover {
        color: #1a1a2e;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 1rem 1.2rem;
        overflow-y: auto;
        flex: 1;
    }

    .modal-body::-webkit-scrollbar {
        width: 5px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: rgba(72, 49, 212, 0.04);
        border-radius: 3px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: rgba(108, 99, 255, 0.3);
        border-radius: 3px;
    }

    .modal-footer {
        padding: 0.7rem 1.2rem;
        border-top: 1px solid rgba(72, 49, 212, 0.06);
        border-radius: 0 0 16px 16px;
        text-align: right;
        flex-shrink: 0;
    }

    .modal-footer .btn-cancel {
        padding: 0.4rem 1.2rem;
        border-radius: 8px;
        border: none;
        background: rgba(72, 49, 212, 0.06);
        color: #555577;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }

    .modal-footer .btn-cancel:hover {
        background: rgba(72, 49, 212, 0.12);
        color: #1a1a2e;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.7rem;
    }

    .history-table th {
        background: rgba(72, 49, 212, 0.04);
        padding: 0.4rem 0.6rem;
        text-align: left;
        color: #555577;
        font-weight: 700;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .history-table td {
        padding: 0.4rem 0.6rem;
        color: #1a1a2e;
        border-bottom: 1px solid rgba(72, 49, 212, 0.05);
        font-size: 0.7rem;
    }

    .history-table tr:last-child td {
        border-bottom: none;
    }

    .history-table tr:hover td {
        background: rgba(108, 99, 255, 0.04);
    }

    .badge-status {
        display: inline-block;
        padding: 0.1rem 0.6rem;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .badge-hadir {
        background: rgba(52, 211, 153, 0.15);
        color: #0b8a5e;
    }

    .badge-sakit {
        background: rgba(96, 165, 250, 0.15);
        color: #2563eb;
    }

    .badge-izin {
        background: rgba(251, 191, 36, 0.15);
        color: #b45309;
    }

    .badge-alfa {
        background: rgba(248, 113, 113, 0.15);
        color: #dc2626;
    }

    .edit-actions {
        display: flex;
        gap: 0.2rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .edit-select {
        padding: 0.15rem 0.3rem;
        border-radius: 5px;
        border: 1px solid rgba(72, 49, 212, 0.12);
        background: rgba(255, 255, 255, 0.7);
        color: #1a1a2e;
        font-size: 0.6rem;
        min-height: 26px;
    }

    .edit-select option {
        background: #ffffff;
        color: #1a1a2e;
    }

    .edit-select:focus {
        outline: none;
        border-color: #6c63ff;
    }

    .btn-save-edit {
        padding: 0.15rem 0.6rem;
        border-radius: 5px;
        border: none;
        background: linear-gradient(135deg, #059669, #34d399);
        color: #fff;
        font-weight: 600;
        font-size: 0.6rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-save-edit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
    }

    .btn-cancel-edit {
        padding: 0.15rem 0.6rem;
        border-radius: 5px;
        border: none;
        background: rgba(72, 49, 212, 0.06);
        color: #666688;
        font-weight: 600;
        font-size: 0.6rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel-edit:hover {
        background: rgba(72, 49, 212, 0.12);
        color: #1a1a2e;
    }

    .btn-edit-row {
        padding: 0.15rem 0.6rem;
        border-radius: 5px;
        border: none;
        background: rgba(251, 191, 36, 0.12);
        color: #b45309;
        font-weight: 600;
        font-size: 0.6rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-edit-row:hover {
        background: rgba(251, 191, 36, 0.2);
        transform: translateY(-1px);
    }

    .loading {
        text-align: center;
        padding: 1.5rem;
        color: rgba(26, 26, 46, 0.4);
    }

    .loading::after {
        content: '⏳';
        font-size: 1.8rem;
        display: block;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .status-absen-aktif {
        background: rgba(52, 211, 153, 0.15);
        color: #0b8a5e;
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-absen-nonaktif {
        background: rgba(248, 113, 113, 0.15);
        color: #dc2626;
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }

    .info-tanggal {
        background: rgba(108, 99, 255, 0.08);
        padding: 0.3rem 1rem;
        border-radius: 8px;
        font-size: 0.7rem;
        color: #4831d4;
        font-weight: 500;
        display: inline-block;
        border: 1px solid rgba(108, 99, 255, 0.10);
    }

    .info-tanggal .tanggal {
        font-weight: 700;
        color: #1a1a2e;
    }
    
    .jam-aktif-indicator {
        background: rgba(52, 211, 153, 0.12);
        border: 1px solid rgba(52, 211, 153, 0.20);
        border-radius: 20px;
        padding: 0.2rem 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.65rem;
        color: #0b8a5e;
        font-weight: 600;
    }

    .jam-aktif-indicator .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #34d399;
        animation: pulse-dot 2s ease-in-out infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
    }

    .jam-aktif-indicator.inactive {
        background: rgba(248, 113, 113, 0.12);
        border-color: rgba(248, 113, 113, 0.20);
        color: #dc2626;
    }

    .jam-aktif-indicator.inactive .dot {
        background: #f87171;
    }

    .client-time {
        font-size: 0.65rem;
        color: #555577;
        background: rgba(72, 49, 212, 0.06);
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        border: 1px solid rgba(72, 49, 212, 0.08);
        display: inline-block;
    }

    .client-time .time {
        font-weight: 700;
        color: #1a1a2e;
        font-size: 0.75rem;
    }

    .kelas-badge {
        background: rgba(108, 99, 255, 0.10);
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        color: #4831d4;
        font-weight: 600;
    }

    .absen-off-message {
        padding: 3rem 1.5rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 12px;
    }

    .absen-off-message .big-icon {
        font-size: 4rem;
        display: block;
        margin-bottom: 1rem;
    }

    .absen-off-message .title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 0.5rem;
    }

    .absen-off-message .desc {
        font-size: 0.85rem;
        color: #666688;
    }

    /* Info mode yang sedang aktif */
    .mode-info {
        background: rgba(108, 99, 255, 0.06);
        padding: 0.3rem 1rem;
        border-radius: 8px;
        font-size: 0.7rem;
        color: #4831d4;
        font-weight: 500;
        display: inline-block;
        border: 1px solid rgba(108, 99, 255, 0.08);
        margin-right: 0.5rem;
    }

    .mode-info .mode-label {
        font-weight: 700;
        color: #1a1a2e;
    }

    .range-info {
        font-size: 0.65rem;
        color: #666688;
        display: inline-block;
        background: rgba(72, 49, 212, 0.04);
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        border: 1px solid rgba(72, 49, 212, 0.06);
    }

    /* Total statistik di footer tabel */
    .table-footer-stats {
        background: rgba(72, 49, 212, 0.04);
        border-top: 2px solid rgba(72, 49, 212, 0.06);
        padding: 0.5rem 1rem;
        display: flex;
        justify-content: flex-end;
        gap: 1.5rem;
        flex-wrap: wrap;
        font-size: 0.7rem;
    }

    .table-footer-stats .stat-item {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .table-footer-stats .stat-item .label {
        color: #555577;
        font-weight: 500;
    }

    .table-footer-stats .stat-item .value {
        font-weight: 700;
        color: #1a1a2e;
    }

    .table-footer-stats .stat-item .value.hadir { color: #0b8a5e; }
    .table-footer-stats .stat-item .value.sakit { color: #2563eb; }
    .table-footer-stats .stat-item .value.izin { color: #b45309; }
    .table-footer-stats .stat-item .value.alfa { color: #dc2626; }

    @media (max-width: 992px) {
        .header-modern {
            flex-direction: column;
            align-items: flex-start;
            padding: 1rem;
        }

        .header-stats {
            width: 100%;
            justify-content: space-around;
            padding: 0.4rem 0.8rem;
        }

        .top-bar {
            flex-direction: column;
            align-items: stretch;
            padding: 0.8rem;
        }

        .rekap-switch {
            flex-direction: column;
            align-items: stretch;
        }

        .switch-group {
            justify-content: center;
        }

        .filter-inline form {
            flex-wrap: wrap;
        }

        .filter-inline input,
        .filter-inline select {
            flex: 1;
            min-width: 80px;
        }

        .container-fluid {
            padding: 0.8rem;
        }

        .modal-content {
            width: 98%;
            margin: 0.8rem;
        }

        .modal-header {
            flex-direction: column;
            gap: 0.4rem;
        }

        .modal-header .modal-search input {
            min-width: 120px;
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

        .switch-group {
            flex-wrap: wrap;
        }

        .switch-btn {
            font-size: 0.6rem;
            padding: 0.2rem 0.6rem;
        }

        .filter-inline form {
            flex-direction: column;
        }

        .filter-inline input,
        .filter-inline select {
            width: 100%;
            min-width: unset;
            font-size: 0.65rem;
        }

        table {
            font-size: 0.6rem;
        }

        thead th,
        tbody td {
            padding: 0.3rem 0.5rem;
            font-size: 0.6rem;
        }

        thead th {
            font-size: 0.5rem;
        }

        .modal-header .modal-search input {
            min-width: 100px;
            width: 100%;
        }

        .export-menu {
            right: 0;
            left: 0;
            min-width: unset;
            width: 100%;
        }

        .score-badge {
            font-size: 0.55rem;
            padding: 0.1rem 0.4rem;
        }

        .btn-review {
            font-size: 0.55rem;
            padding: 0.2rem 0.6rem;
        }

        .table-footer-stats {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.3rem;
        }
        
        .search-input-wrapper input {
            min-width: unset;
            width: 100%;
        }
    }

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

    /* ===== EDIT FORM STYLE ===== */
    .edit-form {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: center;
    }

    .edit-input {
        width: 32px;
        padding: 2px 4px;
        border-radius: 4px;
        border: 1px solid rgba(72, 49, 212, 0.15);
        background: rgba(255, 255, 255, 0.9);
        color: #1a1a2e;
        font-size: 11px;
        text-align: center;
        height: 24px;
    }

    .edit-input:focus {
        outline: none;
        border-color: #6c63ff;
        box-shadow: 0 0 8px rgba(108, 99, 255, 0.15);
    }

    .edit-input::placeholder {
        color: #aaa;
        font-size: 9px;
    }

    .btn-edit-row {
        padding: 2px 8px;
        border-radius: 4px;
        border: none;
        background: rgba(251, 191, 36, 0.15);
        color: #b45309;
        font-weight: 600;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-edit-row:hover {
        background: rgba(251, 191, 36, 0.25);
        transform: translateY(-1px);
    }

    .btn-delete-row {
        padding: 2px 8px;
        border-radius: 4px;
        border: none;
        background: rgba(248, 113, 113, 0.15);
        color: #dc2626;
        font-weight: 600;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-delete-row:hover {
        background: rgba(248, 113, 113, 0.25);
        transform: translateY(-1px);
    }

    .btn-save-edit {
        padding: 2px 8px;
        border-radius: 4px;
        border: none;
        background: linear-gradient(135deg, #059669, #34d399);
        color: #fff;
        font-weight: 600;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-save-edit:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
    }

    .btn-cancel-edit {
        padding: 2px 8px;
        border-radius: 4px;
        border: none;
        background: rgba(72, 49, 212, 0.08);
        color: #666688;
        font-weight: 600;
        font-size: 11px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-cancel-edit:hover {
        background: rgba(72, 49, 212, 0.15);
        color: #1a1a2e;
    }

    @media (max-width: 768px) {
        .edit-form {
            flex-direction: column;
            gap: 3px;
        }
        .edit-input {
            width: 100%;
        }
    }
    </style>
</head>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let currentNokartu = '';
let currentNama = '';

function toggleDark(){
    document.body.classList.toggle('dark');
    let icon = document.getElementById('darkIcon');
    if(icon) {
        icon.className = document.body.classList.contains('dark') ? 'fas fa-sun' : 'fas fa-moon';
        localStorage.setItem("dark", document.body.classList.contains("dark"));
    }
}

if(localStorage.getItem("dark") === "true"){
    toggleDark();
    var darkIcon = document.getElementById('darkIcon');
    if(darkIcon) darkIcon.className='fas fa-sun';
}

// ===== DETEKSI WAKTU CLIENT =====
function updateClientTime() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeString = hours + ':' + minutes + ':' + seconds;
    
    const clientTimeDisplay = document.getElementById('clientTimeDisplay');
    if (clientTimeDisplay) {
        clientTimeDisplay.textContent = timeString;
    }
    
    syncClientTime(timeString);
    return timeString;
}

function syncClientTime(timeString) {
    if (!timeString) {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        timeString = hours + ':' + minutes + ':' + seconds;
    }
    
    const url = new URL(window.location.href);
    url.searchParams.set('client_time', timeString);
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    }).catch(error => console.log('Sync error:', error));
}

setInterval(updateClientTime, 1000);
setInterval(function() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeString = hours + ':' + minutes + ':' + seconds;
    syncClientTime(timeString);
}, 15000);

document.addEventListener("DOMContentLoaded", function() {
    updateClientTime();
    
    // Export dropdown
    const exportDropdown = document.querySelector('.export-dropdown');
    const exportBtn = document.querySelector('.export-btn-main');
    const exportMenu = document.querySelector('.export-menu');

    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            document.querySelectorAll('.export-menu.show').forEach(menu => {
                if (menu !== exportMenu) {
                    menu.classList.remove('show');
                }
            });
            
            exportMenu.classList.toggle('show');
            
            if (exportMenu.classList.contains('show')) {
                exportMenu.style.zIndex = '999999';
                void exportMenu.offsetHeight;
            }
        });

        document.addEventListener('click', function(e) {
            if (!exportDropdown.contains(e.target)) {
                exportMenu.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                exportMenu.classList.remove('show');
            }
        });

        exportMenu.addEventListener('mouseenter', function() {
            this.style.zIndex = '999999';
        });
    }
});

// ===== FUNGSI UNTUK CLEAR SEARCH =====
function clearSearch() {
    var searchInput = document.querySelector('input[name="search_nama"]');
    if (searchInput) {
        searchInput.value = '';
        // Submit form secara otomatis
        var form = searchInput.closest('form');
        if (form) {
            form.submit();
        }
    }
}

// ===== FUNGSI OPEN REVIEW =====
function openReview(nokartu, nama) {
    currentNokartu = nokartu;
    currentNama = nama;
    
    var modal = document.getElementById('reviewModal');
    var title = document.getElementById('reviewTitle');
    var body = document.getElementById('reviewBody');
    
    if(modal) modal.classList.add('show');
    if(title) title.innerHTML = '📋 Riwayat Absensi - ' + nama;
    if(body) body.innerHTML = '<div class="loading"></div>';
    
    // Ambil riwayat dari database
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax_handler_riwayat.php?action=get_riwayat_siswa_by_nokartu&nokartu=' + encodeURIComponent(nokartu), true);
    xhr.onload = function() {
        if (xhr.status === 200 && body) {
            body.innerHTML = xhr.responseText;
        } else if(body) {
            body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Gagal memuat data</p>';
        }
    };
    xhr.onerror = function() {
        if(body) body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Terjadi kesalahan jaringan</p>';
    };
    xhr.send();
}

// ===== FUNGSI UNTUK MENUTUP MODAL =====
function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if(modal) modal.classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

// ===== FUNGSI UNTUK MENCARI RIWAYAT =====
function searchRiwayat() {
    var searchInput = document.getElementById('searchRiwayat');
    var searchValue = searchInput ? searchInput.value : '';
    
    if(!currentNokartu) return;
    
    var body = document.getElementById('reviewBody');
    if(body) body.innerHTML = '<div class="loading"></div>';
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax_handler_riwayat.php?action=search_riwayat&nokartu=' + encodeURIComponent(currentNokartu) + '&search=' + encodeURIComponent(searchValue), true);
    xhr.onload = function() {
        if (xhr.status === 200 && body) {
            body.innerHTML = xhr.responseText;
        } else if(body) {
            body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Gagal memuat data</p>';
        }
    };
    xhr.onerror = function() {
        if(body) body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Terjadi kesalahan jaringan</p>';
    };
    xhr.send();
}

// ===== FUNGSI EDIT STATUS ROW (DI MODAL) - UPDATE =====
function editStatusRowModal(nokartu, tanggal, statusSekarang, rowId) {
    // Buat dropdown untuk memilih status
    var statusOptions = ['Hadir', 'Sakit', 'Izin', 'Alfa'];
    var optionsHtml = '';
    
    statusOptions.forEach(function(status) {
        var selected = (status == statusSekarang) ? 'selected' : '';
        optionsHtml += '<option value="' + status + '" ' + selected + '>' + status + '</option>';
    });
    
    var html = '<div class="edit-form" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">';
    html += '<select id="edit_status_' + rowId + '" class="edit-select" style="padding:4px 8px; border-radius:4px; border:1px solid rgba(72,49,212,0.15);">';
    html += optionsHtml;
    html += '</select>';
    html += '<button class="btn-save-edit" onclick="saveStatusEditModal(\'' + nokartu + '\', \'' + tanggal + '\', \'' + rowId + '\')">💾 Simpan</button>';
    html += '<button class="btn-cancel-edit" onclick="cancelEditStatusModal(\'' + rowId + '\', \'' + statusSekarang + '\', \'' + nokartu + '\', \'' + tanggal + '\')">✖ Batal</button>';
    html += '</div>';
    
    document.getElementById('actions_' + rowId).innerHTML = html;
}

// ===== FUNGSI SIMPAN EDIT STATUS (DI MODAL) - UPDATE =====
function saveStatusEditModal(nokartu, tanggal, rowId) {
    var statusSelect = document.getElementById('edit_status_' + rowId);
    var status = statusSelect ? statusSelect.value : '';
    
    if (!status) {
        Swal.fire({
            title: 'Error!',
            text: 'Status tidak valid!',
            icon: 'error',
            background: '#ffffff',
            color: '#1a1a2e',
            confirmButtonColor: '#6c63ff'
        });
        return;
    }
    
    // Tampilkan loading
    Swal.fire({
        title: 'Menyimpan...',
        text: 'Sedang mengupdate status',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Kirim data ke server
    var formData = new FormData();
    formData.append('action', 'update_status_absensi');
    formData.append('nokartu', nokartu);
    formData.append('tanggal', tanggal);
    formData.append('status', status);
    
    fetch('ajax_handler_riwayat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: data.message || 'Status berhasil diupdate menjadi ' + status,
                icon: 'success',
                background: '#ffffff',
                color: '#1a1a2e',
                confirmButtonColor: '#6c63ff',
                timer: 1500
            });
            
            // Refresh modal setelah 1.5 detik
            setTimeout(function() {
                openReview(currentNokartu, currentNama);
            }, 1500);
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan',
                icon: 'error',
                background: '#ffffff',
                color: '#1a1a2e',
                confirmButtonColor: '#6c63ff'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Gagal menyimpan: ' + error.message,
            icon: 'error',
            background: '#ffffff',
            color: '#1a1a2e',
            confirmButtonColor: '#6c63ff'
        });
    });
}

// ===== FUNGSI BATAL EDIT STATUS (DI MODAL) - UPDATE =====
function cancelEditStatusModal(rowId, statusSekarang, nokartu, tanggal) {
    var html = '<button class="btn-edit-row" onclick="editStatusRowModal(\'' + nokartu + '\', \'' + tanggal + '\', \'' + statusSekarang + '\', \'' + rowId + '\')">✏️ Edit Status</button>';
    var container = document.getElementById('actions_' + rowId);
    if (container) {
        container.innerHTML = html;
    }
}

// ===== FUNGSI DELETE RIWAYAT =====
function deleteRiwayat(rowId) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data riwayat ini akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c63ff',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading
            Swal.fire({
                title: 'Menghapus...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            var formData = new FormData();
            formData.append('action', 'delete_riwayat');
            formData.append('id', rowId);
            
            fetch('ajax_handler_riwayat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message || 'Data berhasil dihapus',
                        icon: 'success',
                        background: '#ffffff',
                        color: '#1a1a2e',
                        confirmButtonColor: '#6c63ff',
                        timer: 1500
                    });
                    
                    // Refresh modal setelah 1.5 detik
                    setTimeout(function() {
                        openReview(currentNokartu, currentNama);
                    }, 1500);
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: data.message || 'Terjadi kesalahan',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1a1a2e',
                        confirmButtonColor: '#6c63ff'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Gagal menghapus: ' + error.message,
                    icon: 'error',
                    background: '#ffffff',
                    color: '#1a1a2e',
                    confirmButtonColor: '#6c63ff'
                });
            });
        }
    });
}

// ===== FUNGSI OPEN REVIEW - UPDATE =====
function openReview(nokartu, nama) {
    currentNokartu = nokartu;
    currentNama = nama;
    
    var modal = document.getElementById('reviewModal');
    var title = document.getElementById('reviewTitle');
    var body = document.getElementById('reviewBody');
    
    if(modal) modal.classList.add('show');
    if(title) title.innerHTML = '📋 Riwayat Absensi - ' + nama;
    if(body) body.innerHTML = '<div class="loading"></div>';
    
    // Ambil riwayat dari database
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax_handler_riwayat.php?action=get_riwayat_siswa_by_nokartu&nokartu=' + encodeURIComponent(nokartu), true);
    xhr.onload = function() {
        if (xhr.status === 200 && body) {
            body.innerHTML = xhr.responseText;
        } else if(body) {
            body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Gagal memuat data</p>';
        }
    };
    xhr.onerror = function() {
        if(body) body.innerHTML = '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Terjadi kesalahan jaringan</p>';
    };
    xhr.send();
}

// ===== FUNGSI DELETE RIWAYAT =====
function deleteRiwayat(rowId, nokartu) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Data riwayat ini akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c63ff',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading
            Swal.fire({
                title: 'Menghapus...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            var formData = new FormData();
            formData.append('action', 'delete_riwayat');
            formData.append('id', rowId);
            formData.append('nokartu', nokartu);
            
            fetch('ajax_handler_riwayat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message || 'Data berhasil dihapus',
                        icon: 'success',
                        background: '#ffffff',
                        color: '#1a1a2e',
                        confirmButtonColor: '#6c63ff',
                        timer: 1500
                    });
                    
                    // Refresh modal setelah 1.5 detik
                    setTimeout(function() {
                        openReview(currentNokartu, currentNama);
                    }, 1500);
                } else {
                    Swal.fire({
                        title: 'Gagal!',
                        text: data.message || 'Terjadi kesalahan',
                        icon: 'error',
                        background: '#ffffff',
                        color: '#1a1a2e',
                        confirmButtonColor: '#6c63ff'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Gagal menghapus: ' + error.message,
                    icon: 'error',
                    background: '#ffffff',
                    color: '#1a1a2e',
                    confirmButtonColor: '#6c63ff'
                });
            });
        }
    });
}

// ===== FUNGSI EDIT STATUS ROW (DI MODAL) =====
function editStatusRowModal(nokartu, tanggal, statusSekarang, rowId) {
    // Buat dropdown untuk memilih status
    var statusOptions = ['Hadir', 'Sakit', 'Izin', 'Alfa'];
    var optionsHtml = '';
    
    statusOptions.forEach(function(status) {
        var selected = (status == statusSekarang) ? 'selected' : '';
        optionsHtml += '<option value="' + status + '" ' + selected + '>' + status + '</option>';
    });
    
    var html = '<div class="edit-form" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap; justify-content:center;">';
    html += '<select id="edit_status_' + rowId + '" class="edit-select" style="padding:4px 8px; border-radius:4px; border:1px solid rgba(72,49,212,0.15);">';
    html += optionsHtml;
    html += '</select>';
    html += '<button class="btn-save-edit" onclick="saveStatusEditModal(\'' + nokartu + '\', \'' + tanggal + '\', \'' + rowId + '\')">💾 Simpan</button>';
    html += '<button class="btn-cancel-edit" onclick="cancelEditStatusModal(\'' + rowId + '\', \'' + statusSekarang + '\', \'' + nokartu + '\', \'' + tanggal + '\')">✖ Batal</button>';
    html += '</div>';
    
    document.getElementById('actions_' + rowId).innerHTML = html;
}

// ===== FUNGSI SIMPAN EDIT STATUS (DI MODAL) =====
function saveStatusEditModal(nokartu, tanggal, rowId) {
    var statusSelect = document.getElementById('edit_status_' + rowId);
    var status = statusSelect ? statusSelect.value : '';
    
    if (!status) {
        Swal.fire({
            title: 'Error!',
            text: 'Status tidak valid!',
            icon: 'error',
            background: '#ffffff',
            color: '#1a1a2e',
            confirmButtonColor: '#6c63ff'
        });
        return;
    }
    
    // Tampilkan loading
    Swal.fire({
        title: 'Menyimpan...',
        text: 'Sedang mengupdate status',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Kirim data ke server
    var formData = new FormData();
    formData.append('action', 'update_status_absensi');
    formData.append('nokartu', nokartu);
    formData.append('tanggal', tanggal);
    formData.append('status', status);
    
    fetch('ajax_handler_riwayat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: data.message || 'Status berhasil diupdate menjadi ' + status,
                icon: 'success',
                background: '#ffffff',
                color: '#1a1a2e',
                confirmButtonColor: '#6c63ff',
                timer: 1500
            });
            
            // Refresh modal setelah 1.5 detik
            setTimeout(function() {
                openReview(currentNokartu, currentNama);
            }, 1500);
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan',
                icon: 'error',
                background: '#ffffff',
                color: '#1a1a2e',
                confirmButtonColor: '#6c63ff'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Gagal menyimpan: ' + error.message,
            icon: 'error',
            background: '#ffffff',
            color: '#1a1a2e',
            confirmButtonColor: '#6c63ff'
        });
    });
}

// ===== FUNGSI BATAL EDIT STATUS (DI MODAL) =====
function cancelEditStatusModal(rowId, statusSekarang, nokartu, tanggal) {
    var html = '<div style="display:flex; gap:4px; align-items:center; justify-content:center; flex-wrap:wrap;">';
    html += '<button class="btn-edit-row" onclick="editStatusRowModal(\'' + nokartu + '\', \'' + tanggal + '\', \'' + statusSekarang + '\', \'' + rowId + '\')">✏️ Edit Status</button>';
    html += '<button class="btn-delete-row" onclick="deleteRiwayat(\'' + rowId + '\', \'' + nokartu + '\')">🗑️ Hapus</button>';
    html += '</div>';
    
    var container = document.getElementById('actions_' + rowId);
    if (container) {
        container.innerHTML = html;
    }
}
</script>

<body>
<?php include "menu.php"; ?>

<div class="container-fluid">
    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">🏆</div>
            <div class="header-text">
                <h1>Rekapitulasi Skor Siswa</h1>
            </div>
        </div>

        <div class="header-stats">
            <!-- INDICATOR JAM AKTIF -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <div class="jam-aktif-indicator <?= $absen_aktif ? '' : 'inactive' ?>">
                    <span class="dot"></span>
                    <?php if ($absen_aktif): ?>
                        <span>🟢 Jam Absen Aktif</span>
                    <?php else: ?>
                        <span>⏸️ Tidak Ada Jam Absen Aktif</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- WAKTU CLIENT -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <div class="client-time">
                    🕐 <span class="time" id="clientTimeDisplay"><?= $client_time_for_comparison ?></span>
                </div>
            </div>

            <!-- TOTAL SISWA YANG SUDAH ABSEN -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <span class="number">
                    <?php 
                    if (isset($conek) && $conek) {
                        $filterKelas = "";
                        $filterJurusan = "";
                        $filterSearch = "";
                        
                        if(!empty($_GET['kelas'])){
                            $kelas = mysqli_real_escape_string($conek, $_GET['kelas']);
                            $filterKelas = " AND s.kelas='$kelas' ";
                        }
                        if(!empty($_GET['jurusan'])){
                            $jurusan = mysqli_real_escape_string($conek, $_GET['jurusan']);
                            $filterJurusan = " AND s.jurusan='$jurusan' ";
                        }
                        if(!empty($search_nama)){
                            $search_nama_clean = mysqli_real_escape_string($conek, $search_nama);
                            $filterSearch = " AND s.nama_siswa LIKE '%$search_nama_clean%' ";
                        }
                        
                        if ($tanggal_awal_filter && $tanggal_akhir_filter) {
                            $tanggal_awal_clean = mysqli_real_escape_string($conek, $tanggal_awal_filter);
                            $tanggal_akhir_clean = mysqli_real_escape_string($conek, $tanggal_akhir_filter);
                            $qCount = mysqli_query($conek, "SELECT COUNT(DISTINCT r.siswa_id) as total 
                                FROM riwayat_siswa r
                                INNER JOIN siswa s ON r.siswa_id = s.id
                                WHERE r.tanggal BETWEEN '$tanggal_awal_clean' AND '$tanggal_akhir_clean'
                                $filterKelas
                                $filterJurusan
                                $filterSearch");
                                
                            if ($qCount) {
                                $count = mysqli_fetch_assoc($qCount);
                                echo $count['total'] ?? '0';
                            } else {
                                echo '0';
                            }
                        } else {
                            echo '0';
                        }
                    } else {
                        echo '0';
                    }
                    ?>
                </span>
                <span class="label">Sudah Absen</span>
            </div>

            <!-- TOTAL SISWA (SEMUA) -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <span class="number">
                    <?php 
                    if (isset($conek) && $conek) {
                        $filterKelas = "";
                        $filterJurusan = "";
                        $filterSearch = "";
                        
                        if(!empty($_GET['kelas'])){
                            $kelas = mysqli_real_escape_string($conek, $_GET['kelas']);
                            $filterKelas = " AND kelas='$kelas' ";
                        }
                        if(!empty($_GET['jurusan'])){
                            $jurusan = mysqli_real_escape_string($conek, $_GET['jurusan']);
                            $filterJurusan = " AND jurusan='$jurusan' ";
                        }
                        if(!empty($search_nama)){
                            $search_nama_clean = mysqli_real_escape_string($conek, $search_nama);
                            $filterSearch = " AND nama_siswa LIKE '%$search_nama_clean%' ";
                        }
                        
                        $qCount = mysqli_query($conek, "SELECT COUNT(*) as total FROM siswa WHERE 1=1 $filterKelas $filterJurusan $filterSearch");
                        if ($qCount) {
                            $count = mysqli_fetch_assoc($qCount);
                            echo $count['total'] ?? '0';
                        } else {
                            echo '0';
                        }
                    } else {
                        echo '0';
                    }
                    ?>
                </span>
                <span class="label">Total Siswa</span>
            </div>

            <!-- STATUS ABSENSI -->
            <div class="stat-chip">
                <span class="number">
                    <?php 
                    if ($absen_aktif) {
                        echo '🟢';
                    } else {
                        echo '🔴';
                    }
                    ?>
                </span>
                <span class="label">Status Absensi</span>
            </div>
        </div>
    </div>

    <!-- ===== TOP BAR ===== -->
    <div class="top-bar">
        <div class="rekap-switch">
            <!-- MODE SWITCH -->
            <div class="switch-group">
                <a href="?mode=harian<?= isset($_GET['kelas']) ? '&kelas='.$_GET['kelas'] : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['tanggal']) ? '&tanggal='.$_GET['tanggal'] : '' ?><?= isset($_GET['search_nama']) ? '&search_nama='.urlencode($_GET['search_nama']) : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?>" class="switch-btn <?php echo (!isset($_GET['mode']) || $_GET['mode']=='harian') ? 'active' : ''; ?>">📅 Harian</a>
                <a href="?mode=mingguan<?= isset($_GET['kelas']) ? '&kelas='.$_GET['kelas'] : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['tanggal']) ? '&tanggal='.$_GET['tanggal'] : '' ?><?= isset($_GET['search_nama']) ? '&search_nama='.urlencode($_GET['search_nama']) : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?>" class="switch-btn <?php echo (isset($_GET['mode']) && $_GET['mode']=='mingguan') ? 'active' : ''; ?>">📆 Mingguan</a>
                <a href="?mode=bulanan<?= isset($_GET['kelas']) ? '&kelas='.$_GET['kelas'] : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['bulan']) ? '&bulan='.$_GET['bulan'] : '' ?><?= isset($_GET['tahun']) ? '&tahun='.$_GET['tahun'] : '' ?><?= isset($_GET['search_nama']) ? '&search_nama='.urlencode($_GET['search_nama']) : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?>" class="switch-btn <?php echo (isset($_GET['mode']) && $_GET['mode']=='bulanan') ? 'active' : ''; ?>">📊 Bulanan</a>
                <a href="?mode=semester<?= isset($_GET['kelas']) ? '&kelas='.$_GET['kelas'] : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['semester']) ? '&semester='.$_GET['semester'] : '' ?><?= isset($_GET['tahun']) ? '&tahun='.$_GET['tahun'] : '' ?><?= isset($_GET['search_nama']) ? '&search_nama='.urlencode($_GET['search_nama']) : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?>" class="switch-btn <?php echo (isset($_GET['mode']) && $_GET['mode']=='semester') ? 'active' : ''; ?>">🎓 Semester</a>
            </div>

            <!-- FILTER -->
            <div class="filter-inline">
                <form method="GET" action="">
                    <?php  
                    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'harian';

                    if ($mode == 'bulanan') {
                        $bulanDipilih = $_GET['bulan'] ?? date("m");
                        $tahunDipilih = $_GET['tahun'] ?? date("Y");

                        echo '<select name="bulan">';
                        for ($i = 1; $i <= 12; $i++) {
                            $bulan = str_pad($i, 2, "0", STR_PAD_LEFT);
                            $nama_bulan = date("F", mktime(0, 0, 0, $i, 1));
                            $selected = ($bulanDipilih == $bulan) ? "selected" : "";
                            echo "<option value='$bulan' $selected>$nama_bulan</option>";
                        }
                        echo '</select>';

                        echo '<select name="tahun">';
                        $tahun_sekarang = date("Y");
                        for ($t = $tahun_sekarang; $t >= 2020; $t--) {
                            $selected = ($tahunDipilih == $t) ? "selected" : "";
                            echo "<option value='$t' $selected>$t</option>";
                        }
                        echo '</select>';

                    } elseif ($mode == 'semester') {
                        $semesterDipilih = $_GET['semester'] ?? '1';
                        $tahunDipilih = $_GET['tahun'] ?? date("Y");
                        
                        echo '<select name="semester">';
                        echo '<option value="1" ' . ($semesterDipilih == '1' ? 'selected' : '') . '>Semester 1 (Jul-Des)</option>';
                        echo '<option value="2" ' . ($semesterDipilih == '2' ? 'selected' : '') . '>Semester 2 (Jan-Jun)</option>';
                        echo '</select>';
                        
                        echo '<select name="tahun">';
                        $tahun_sekarang = date("Y");
                        for ($t = $tahun_sekarang; $t >= 2020; $t--) {
                            $selected = ($tahunDipilih == $t) ? "selected" : "";
                            echo "<option value='$t' $selected>$t</option>";
                        }
                        echo '</select>';
                        
                    } else {
                        // Untuk mode harian dan mingguan, gunakan input date
                        $tanggalDipilih = $_GET["tanggal"] ?? date("Y-m-d");
                        echo '<input type="date" name="tanggal" value="'.$tanggalDipilih.'">';
                    }
                    ?>

                    <select name="kelas">
                        <option value="">Semua Kelas</option>
                        <?php 
                        if (isset($conek) && $conek) {
                            $qKelas = mysqli_query($conek, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC");
                            if ($qKelas) {
                                while ($k = mysqli_fetch_assoc($qKelas)) {
                                    $sel = (isset($_GET['kelas']) && $_GET['kelas']==$k['kelas']) ? "selected" : "";
                                    echo "<option value='{$k['kelas']}' $sel>{$k['kelas']}</option>";
                                }
                            }
                        }
                        ?>
                    </select>

                    <select name="jurusan">
                        <option value="">Semua Jurusan</option>
                        <?php 
                        if (isset($conek) && $conek) {
                            $qJurusan = mysqli_query($conek, "SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan ASC");
                            if ($qJurusan) {
                                while ($j = mysqli_fetch_assoc($qJurusan)) {
                                    $sel = (isset($_GET['jurusan']) && $_GET['jurusan']==$j['jurusan']) ? "selected" : "";
                                    echo "<option value='{$j['jurusan']}' $sel>{$j['jurusan']}</option>";
                                }
                            }
                        }
                        ?>
                    </select>

                    <!-- ===== INPUT PENCARIAN NAMA ===== -->
                    <div class="search-input-wrapper">
                        <input type="text" name="search_nama" placeholder="🔍 Cari Nama..." 
                               value="<?= isset($_GET['search_nama']) ? htmlspecialchars($_GET['search_nama']) : '' ?>">
                        <?php if (!empty($search_nama)): ?>
                            <button type="button" class="search-clear-btn" onclick="clearSearch()" title="Hapus pencarian">✕</button>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="mode" value="<?= $mode ?>">
                    <?php if ($client_time): ?>
                        <input type="hidden" name="client_time" value="<?= htmlspecialchars($client_time) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-filter">🔍 Tampilkan</button>
                    <a href="rekab_skor_siswa.php" class="btn-reset" title="Reset Filter">↻</a>
                </form>
            </div>
        </div>

        <!-- EXPORT -->
        <?php  
            $params = $_GET;
            $queryString = http_build_query($params);
        ?>
        <div class="export-dropdown">
            <button type="button" class="export-btn-main">
                📤 Export <span style="font-size:0.5rem; opacity:0.6;">▾</span>
            </button>
            <div class="export-menu">
                <a href="../export/export_skor_siswa.php?type=excel&<?= $queryString ?>">
                    <span class="icon">📗</span> Excel
                </a>
                <a href="../export/export_skor_siswa.php?type=word&<?= $queryString ?>">
                    <span class="icon">📘</span> Word
                </a>
                <div class="menu-divider"></div>
                <a href="../export/export_skor_siswa.php?type=pdf&<?= $queryString ?>">
                    <span class="icon">📕</span> PDF
                </a>
            </div>
        </div>
    </div>

    <!-- ===== STATUS ABSENSI & INFO TANGGAL ===== -->
    <div style="padding: 0.5rem 1.2rem; background: rgba(255,255,255,0.6); border-radius: 0; margin-bottom: 0.1rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
        <?php
        if($absen_aktif) {
            echo '<span class="status-absen-aktif">🟢 Absensi Aktif</span>';
        } else {
            echo '<span class="status-absen-nonaktif">🔴 Absensi Tidak Aktif</span>';
        }
        
        // Tampilkan info mode dan range (tetap ditampilkan meskipun absen tidak aktif)
        echo '<span class="mode-info">📌 Mode: <span class="mode-label">' . ucfirst($mode) . '</span></span>';
        
        if ($mode == 'harian') {
            $tanggal_tampil_info = $tanggal_input ? date('d F Y', strtotime($tanggal_input)) : date('d F Y');
            echo '<span class="info-tanggal">📅 Tanggal: <span class="tanggal">' . $tanggal_tampil_info . '</span></span>';
        } elseif ($mode == 'mingguan') {
            $tanggal_akhir = $tanggal_input ? $tanggal_input : date('Y-m-d');
            $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
            echo '<span class="info-tanggal">📆 Minggu: <span class="tanggal">' . date('d F Y', strtotime($tanggal_awal)) . ' - ' . date('d F Y', strtotime($tanggal_akhir)) . '</span></span>';
        } elseif ($mode == 'bulanan') {
            $nama_bulan = date('F Y', mktime(0, 0, 0, $bulan_input, 1, $tahun_input));
            echo '<span class="info-tanggal">📊 Bulan: <span class="tanggal">' . $nama_bulan . '</span></span>';
        } elseif ($mode == 'semester') {
            $semester_nama = $semester_input == 1 ? 'Semester 1 (Jul-Des)' : 'Semester 2 (Jan-Jun)';
            $tahun_akademik = $semester_input == 1 ? ($tahun_input - 1) . '/' . $tahun_input : $tahun_input;
            echo '<span class="info-tanggal">🎓 ' . $semester_nama . ' TA: <span class="tanggal">' . $tahun_akademik . '</span></span>';
        }
        
        // Tampilkan keyword pencarian jika ada
        if (!empty($search_nama)) {
            echo '<span style="background: rgba(108, 99, 255, 0.08); padding: 0.2rem 0.8rem; border-radius: 20px; font-size: 0.65rem; color: #4831d4; border: 1px solid rgba(108, 99, 255, 0.10);">
                🔍 Mencari: <strong>' . htmlspecialchars($search_nama) . '</strong>
            </span>';
        }
        
        echo '<span style="font-size:0.65rem; color:#555577;">| Data dari riwayat siswa</span>';
        ?>
    </div>

    <!-- ===== TABLE ===== -->
    <div class="table-wrapper">
        <div class="table-responsive-custom">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;" class="center">No.</th>
                        <th class="center">Nama Siswa</th>
                        <th style="width: 60px;" class="center">Kelas</th>
                        <th style="width: 100px" class="center">Jurusan</th>
                        <th style="width: 60px;" class="center">✅ Hadir</th>
                        <th style="width: 60px;" class="center">🤒 Sakit</th>
                        <th style="width: 60px;" class="center">📋 Izin</th>
                        <th style="width: 60px;" class="center">❌ Alfa</th>
                        <th style="width: 130px;" class="center">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    // HAPUS KONDISI if (!$absen_aktif) - TETAP TAMPILKAN DATA MESKIPUN ABSEN TIDAK AKTIF
                    if (isset($conek) && $conek) {
                        if (!$sql) {
                            echo "<tr><td colspan='9' class='empty-state'><span class='icon'>❌</span><span class='text'>Error: " . mysqli_error($conek) . "</span></td></tr>";
                        } else if (mysqli_num_rows($sql) == 0) {
                            // Tampilkan pesan berdasarkan mode
                            $mode_text = ucfirst($mode);
                            $mode_detail = '';
                            
                            if ($mode == 'harian') {
                                $tanggal_tampil_info = $tanggal_input ? date('d F Y', strtotime($tanggal_input)) : date('d F Y');
                                $mode_detail = " untuk tanggal $tanggal_tampil_info";
                            } elseif ($mode == 'mingguan') {
                                $tanggal_akhir = $tanggal_input ? $tanggal_input : date('Y-m-d');
                                $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
                                $mode_detail = " untuk minggu " . date('d F Y', strtotime($tanggal_awal)) . ' - ' . date('d F Y', strtotime($tanggal_akhir));
                            } elseif ($mode == 'bulanan') {
                                $nama_bulan = date('F Y', mktime(0, 0, 0, $bulan_input, 1, $tahun_input));
                                $mode_detail = " untuk bulan $nama_bulan";
                            } elseif ($mode == 'semester') {
                                $semester_nama = $semester_input == 1 ? 'Semester 1' : 'Semester 2';
                                $tahun_akademik = $semester_input == 1 ? ($tahun_input - 1) . '/' . $tahun_input : $tahun_input;
                                $mode_detail = " untuk $semester_nama TA $tahun_akademik";
                            }
                            
                            // Tambahkan info pencarian
                            if (!empty($search_nama)) {
                                $mode_detail .= ' dengan nama "' . htmlspecialchars($search_nama) . '"';
                            }
                            
                            echo '<tr><td colspan="9" class="empty-state">
                                <span class="icon">📊</span>
                                <span class="text">Belum ada data riwayat absensi ' . $mode_detail . '</span>
                                <br><span style="font-size:0.7rem; color:rgba(26,26,46,0.3);">Silahkan lakukan absensi terlebih dahulu</span>
                            </td></tr>';
                        } else {
                            $no = 1;
                            while ($data = mysqli_fetch_assoc($sql)) {
                    ?>
                    <tr>
                        <td class="center" style="color:rgba(26,26,46,0.3); font-weight:600; font-size:0.65rem;"><?= $no++ ?></td>
                        <td style="font-weight:600; color:#1a1a2e; font-size:0.7rem;">
                            <?= htmlspecialchars($data['nama_siswa']) ?>
                            <?php if (!empty($search_nama)): ?>
                                <span style="background: rgba(108, 99, 255, 0.08); padding: 1px 6px; border-radius: 10px; font-size: 0.5rem; color: #4831d4; margin-left: 4px;">✓</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <span class="kelas-badge">
                                <?= htmlspecialchars($data['kelas']) ?>
                            </span>
                        </td>
                        <td style="color:#1a1a2e; font-size:0.7rem;" class="center"><?= htmlspecialchars($data['jurusan']) ?></td>
                        <td class="center"><span class="score-badge score-hadir"><?= $data['hadir'] ?></span></td>
                        <td class="center"><span class="score-badge score-sakit"><?= $data['sakit'] ?></span></td>
                        <td class="center"><span class="score-badge score-izin"><?= $data['izin'] ?></span></td>
                        <td class="center"><span class="score-badge score-alfa"><?= $data['alfa'] ?></span></td>
                        <td class="center">
                            <button class="btn-review" onclick="openReview('<?= $data['nokartu'] ?>', '<?= addslashes(htmlspecialchars($data['nama_siswa'])) ?>')">
                                📋 Lihat & Edit
                            </button>
                        </td>
                    </tr>
                    <?php 
                            }
                        }
                    } else {
                        echo '<tr><td colspan="9" class="empty-state"><span class="icon">❌</span><span class="text">Koneksi database gagal</span></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- ===== TABLE FOOTER STATISTIK ===== -->
        <?php if ($sql && mysqli_num_rows($sql) > 0): ?>
        <div class="table-footer-stats">
            <div class="stat-item">
                <span class="label">Total Keseluruhan:</span>
            </div>
            <div class="stat-item">
                <span class="label">✅ Hadir:</span>
                <span class="value hadir"><?= $total_hadir ?></span>
            </div>
            <div class="stat-item">
                <span class="label">🤒 Sakit:</span>
                <span class="value sakit"><?= $total_sakit ?></span>
            </div>
            <div class="stat-item">
                <span class="label">📋 Izin:</span>
                <span class="value izin"><?= $total_izin ?></span>
            </div>
            <div class="stat-item">
                <span class="label">❌ Alfa:</span>
                <span class="value alfa"><?= $total_alfa ?></span>
            </div>
            <div class="stat-item">
                <span class="label">Total Kehadiran:</span>
                <span class="value" style="font-weight:700; color:#4831d4;"><?= $total_hadir + $total_sakit + $total_izin + $total_alfa ?></span>
            </div>
            <?php if (!empty($search_nama)): ?>
            <div class="stat-item">
                <span class="label">🔍 Menampilkan hasil untuk:</span>
                <span class="value" style="font-weight:600; color:#4831d4; font-size:0.7rem;">"<?= htmlspecialchars($search_nama) ?>"</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL REVIEW ===== -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="reviewTitle">📋 Riwayat Absensi</h4>
            <div style="display:flex; align-items:center; gap:0.4rem;">
                <div class="modal-search">
                    <input type="text" id="searchRiwayat" placeholder="🔍 Cari tanggal..." onkeyup="if(event.key==='Enter') searchRiwayat()">
                    <button onclick="searchRiwayat()">Cari</button>
                </div>
                <span class="close" onclick="closeModal('reviewModal')">&times;</span>
            </div>
        </div>
        <div class="modal-body" id="reviewBody">
            <div class="loading"></div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal('reviewModal')">Tutup</button>
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>

</body>
</html>