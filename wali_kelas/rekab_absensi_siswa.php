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

// Ambil kelas dari session (untuk wali_kelas)
$kelas_wali = $_SESSION['kelas'] ?? '';

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

if ($client_time) {
    $client_time_for_comparison = $client_time;
} else {
    $client_time_for_comparison = date('H:i:s');
}

// FUNGSI CEK JAM ABSEN AKTIF
function isJamAbsenActive($conek, $jam_mulai, $jam_selesai, $client_time = null) {
    if ($client_time) {
        $waktu_sekarang = $client_time;
    } else {
        $waktu_sekarang = date('H:i:s');
    }
    
    $waktu_sekarang_ts = strtotime($waktu_sekarang);
    $jam_mulai_ts = strtotime($jam_mulai);
    $jam_selesai_ts = strtotime($jam_selesai);
    
    if ($jam_mulai_ts > $jam_selesai_ts) {
        return ($waktu_sekarang_ts >= $jam_mulai_ts || $waktu_sekarang_ts <= $jam_selesai_ts);
    } else {
        return ($waktu_sekarang_ts >= $jam_mulai_ts && $waktu_sekarang_ts <= $jam_selesai_ts);
    }
}

function getActiveJamAbsen($conek, $client_time = null) {
    $jam_aktif = [];
    $query = "SELECT * FROM pengaturan_jam_absen ORDER BY urutan ASC";
    $result = mysqli_query($conek, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (isJamAbsenActive($conek, $row['jam_mulai'], $row['jam_selesai'], $client_time)) {
            $jam_aktif[] = $row;
        }
    }
    
    return $jam_aktif;
}

function hasActiveJamAbsen($conek, $client_time = null) {
    $query = "SELECT * FROM pengaturan_jam_absen";
    $result = mysqli_query($conek, $query);
    
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

// Filter pencarian
$search_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$search_jurusan = isset($_GET['jurusan']) ? trim($_GET['jurusan']) : '';
$search_nama = isset($_GET['cari']) ? trim($_GET['cari']) : '';

// ===== FILTER WAJIB UNTUK WALI KELAS =====
if (!empty($kelas_wali)) {
    // Jika wali kelas, hanya bisa melihat kelasnya sendiri
    $search_kelas = $kelas_wali;
}

// ===== TENTUKAN FILTER TANGGAL =====
$tanggal_awal_filter = null;
$tanggal_akhir_filter = null;

if ($mode == 'harian') {
    $tanggal_filter = $tanggal_input ? $tanggal_input : date('Y-m-d');
    $tanggal_awal_filter = $tanggal_filter;
    $tanggal_akhir_filter = $tanggal_filter;
} elseif ($mode == 'mingguan') {
    $tanggal_akhir = $tanggal_input ? $tanggal_input : date('Y-m-d');
    $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
    $tanggal_awal_filter = $tanggal_awal;
    $tanggal_akhir_filter = $tanggal_akhir;
} elseif ($mode == 'bulanan') {
    $bulan = intval($bulan_input);
    $tahun = intval($tahun_input);
    $tanggal_awal_filter = date('Y-m-d', mktime(0, 0, 0, $bulan, 1, $tahun));
    $tanggal_akhir_filter = date('Y-m-t', mktime(0, 0, 0, $bulan, 1, $tahun));
} elseif ($mode == 'semester') {
    $semester = intval($semester_input);
    $tahun = intval($tahun_input);
    
    if ($semester == 1) {
        $tanggal_awal_filter = ($tahun - 1) . '-07-01';
        $tanggal_akhir_filter = ($tahun - 1) . '-12-31';
    } else {
        $tanggal_awal_filter = $tahun . '-01-01';
        $tanggal_akhir_filter = $tahun . '-06-30';
    }
}

// Ambil data pengaturan jam absen
$query_pengaturan = "SELECT * FROM pengaturan_jam_absen ORDER BY urutan ASC";
$result_pengaturan = mysqli_query($conek, $query_pengaturan);
$pengaturan_jam = [];
while ($row = mysqli_fetch_assoc($result_pengaturan)) {
    $pengaturan_jam[] = $row;
}

$has_active_jam = hasActiveJamAbsen($conek, $client_time_for_comparison);
$active_jam_list = getActiveJamAbsen($conek, $client_time_for_comparison);

function getJamColumnName($urutan) {
    $mapping = [
        1 => 'jam_masuk_pertama',
        2 => 'jam_istirahat',
        3 => 'jam_masuk_kedua',
        4 => 'jam_pulang'
    ];
    return isset($mapping[$urutan]) ? $mapping[$urutan] : null;
}

// ===== AMBIL DATA ABSENSI SISWA DENGAN FILTER KELAS =====
$absensi_data = [];
$siswa_data = [];

if ($tanggal_awal_filter && $tanggal_akhir_filter) {
    $tanggal_awal_clean = mysqli_real_escape_string($conek, $tanggal_awal_filter);
    $tanggal_akhir_clean = mysqli_real_escape_string($conek, $tanggal_akhir_filter);
    
    // Query untuk mengambil data absensi dengan join ke siswa
    $query_absensi = "
    SELECT 
        a.siswa_id,
        s.nokartu,
        s.nama_siswa,
        s.kelas,
        s.jurusan,
        a.tanggal,
        a.jam_urutan,
        a.jam_absen,
        a.jam_masuk_pertama,
        a.jam_istirahat,
        a.jam_masuk_kedua,
        a.jam_pulang,
        a.keterangan,
        a.status,
        a.telat
    FROM absensi_siswa a
    LEFT JOIN siswa s ON a.siswa_id = s.id
    WHERE a.tanggal BETWEEN '$tanggal_awal_clean' AND '$tanggal_akhir_clean'
    ";
    
    // FILTER WAJIB: Kelas (untuk wali kelas)
    if (!empty($kelas_wali)) {
        $query_absensi .= " AND s.kelas = '$kelas_wali' ";
    }
    
    // Filter tambahan
    if (!empty($search_kelas)) {
        $kelas = mysqli_real_escape_string($conek, $search_kelas);
        $query_absensi .= " AND s.kelas = '$kelas' ";
    }
    if (!empty($search_jurusan)) {
        $jurusan = mysqli_real_escape_string($conek, $search_jurusan);
        $query_absensi .= " AND s.jurusan = '$jurusan' ";
    }
    if (!empty($search_nama)) {
        $cari = mysqli_real_escape_string($conek, $search_nama);
        $query_absensi .= " AND s.nama_siswa LIKE '%$cari%' ";
    }
    
    $query_absensi .= " ORDER BY a.tanggal DESC, a.siswa_id, a.jam_urutan";
    
    $result_absensi = mysqli_query($conek, $query_absensi);
    
    if ($result_absensi) {
        while ($row = mysqli_fetch_assoc($result_absensi)) {
            $siswa_id = $row['siswa_id'];
            $tanggal = $row['tanggal'];
            $key = $siswa_id . '_' . $tanggal;
            
            // Simpan data siswa
            if (!isset($siswa_data[$siswa_id])) {
                $siswa_data[$siswa_id] = [
                    'nokartu' => $row['nokartu'],
                    'nama_siswa' => $row['nama_siswa'],
                    'kelas' => $row['kelas'],
                    'jurusan' => $row['jurusan']
                ];
            }
            
            // Inisialisasi data absensi jika belum ada
            if (!isset($absensi_data[$key])) {
                $absensi_data[$key] = [
                    'siswa_id' => $siswa_id,
                    'tanggal' => $tanggal,
                    'jam_masuk_pertama' => null,
                    'jam_istirahat' => null,
                    'jam_masuk_kedua' => null,
                    'jam_pulang' => null,
                    'keterangan' => 'Belum Absen',
                    'status' => 'hadir',
                    'telat' => null,
                    'jam_absen' => null,
                    'jam_urutan' => 0
                ];
            }
            
            // Isi kolom jam sesuai urutan
            $jam_column = getJamColumnName($row['jam_urutan']);
            if ($jam_column && isset($row[$jam_column]) && $row[$jam_column] !== null) {
                $absensi_data[$key][$jam_column] = $row[$jam_column];
            }
            
            // Update keterangan dan status (ambil yang terakhir/terbaru)
            if ($row['keterangan'] && $row['keterangan'] != 'Belum Absen') {
                $absensi_data[$key]['keterangan'] = $row['keterangan'];
                $absensi_data[$key]['status'] = $row['status'];
                $absensi_data[$key]['telat'] = $row['telat'];
                $absensi_data[$key]['jam_absen'] = $row['jam_absen'];
                $absensi_data[$key]['jam_urutan'] = $row['jam_urutan'];
            }
        }
    }
}

// ===== HITUNG TOTAL STATISTIK =====
$total_hadir = 0;
$total_sakit = 0;
$total_izin = 0;
$total_alfa = 0;

foreach ($absensi_data as $data) {
    $status = strtolower($data['status'] ?? '');
    if ($status == 'hadir') $total_hadir++;
    elseif ($status == 'sakit') $total_sakit++;
    elseif ($status == 'izin') $total_izin++;
    elseif ($status == 'alfa') $total_alfa++;
}

// ===== FUNGSI UNTUK MENDAPATKAN URUTAN JAM YANG SEDANG AKTIF =====
function getActiveJamUrutan($conek, $client_time = null) {
    $urutan_aktif = [];
    $query = "SELECT urutan FROM pengaturan_jam_absen";
    $result = mysqli_query($conek, $query);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $query_cek = "SELECT jam_mulai, jam_selesai FROM pengaturan_jam_absen WHERE urutan = " . $row['urutan'];
        $result_cek = mysqli_query($conek, $query_cek);
        if ($result_cek && $jam = mysqli_fetch_assoc($result_cek)) {
            if (isJamAbsenActive($conek, $jam['jam_mulai'], $jam['jam_selesai'], $client_time)) {
                $urutan_aktif[] = $row['urutan'];
            }
        }
    }
    
    return $urutan_aktif;
}

// ===== PROSES UPDATE STATUS ABSENSI KE ABSENSI_SISWA =====
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $nokartu = mysqli_real_escape_string($conek, $_POST['nokartu']);
    $tanggal = mysqli_real_escape_string($conek, $_POST['tanggal']);
    $status_baru = mysqli_real_escape_string($conek, $_POST['status_baru']);
    $jam_absen = isset($_POST['jam_absen']) ? mysqli_real_escape_string($conek, $_POST['jam_absen']) : date('H:i:s');
    
    // Cari siswa_id dari nokartu
    $query_siswa = "SELECT id FROM siswa WHERE nokartu = '$nokartu'";
    $result_siswa = mysqli_query($conek, $query_siswa);
    
    if ($result_siswa && $siswa = mysqli_fetch_assoc($result_siswa)) {
        $siswa_id = $siswa['id'];
        
        // Mapping status
        $status_mapping = [
            'Hadir' => 'hadir',
            'Sakit' => 'sakit',
            'Izin' => 'izin',
            'Alfa' => 'alfa'
        ];
        
        $status_column = isset($status_mapping[$status_baru]) ? $status_mapping[$status_baru] : null;
        
        if ($status_column) {
            // Cek jam urutan yang aktif
            $jam_urutan = 0;
            $jam_aktif = getActiveJamUrutan($conek, $client_time_for_comparison);
            if (!empty($jam_aktif)) {
                $jam_urutan = $jam_aktif[0];
            }
            
            // Tentukan kolom jam berdasarkan urutan
            $jam_column = getJamColumnName($jam_urutan);
            
            // Cek apakah sudah ada data absensi
            $query_cek = "SELECT id FROM absensi_siswa WHERE siswa_id = '$siswa_id' AND tanggal = '$tanggal'";
            $result_cek = mysqli_query($conek, $query_cek);
            
            if (mysqli_num_rows($result_cek) > 0) {
                // Update data yang ada
                if ($jam_column) {
                    $query_update = "UPDATE absensi_siswa SET 
                        $jam_column = '$jam_absen',
                        jam_absen = '$jam_absen',
                        jam_urutan = '$jam_urutan',
                        keterangan = '$status_baru',
                        status = '$status_column'
                        WHERE siswa_id = '$siswa_id' AND tanggal = '$tanggal'";
                } else {
                    $query_update = "UPDATE absensi_siswa SET 
                        jam_absen = '$jam_absen',
                        jam_urutan = '$jam_urutan',
                        keterangan = '$status_baru',
                        status = '$status_column'
                        WHERE siswa_id = '$siswa_id' AND tanggal = '$tanggal'";
                }
                mysqli_query($conek, $query_update);
            } else {
                // Insert data baru
                if ($jam_column) {
                    $query_insert = "INSERT INTO absensi_siswa 
                        (siswa_id, nokartu, tanggal, jam_urutan, jam_absen, $jam_column, keterangan, status) 
                        VALUES 
                        ('$siswa_id', '$nokartu', '$tanggal', '$jam_urutan', '$jam_absen', '$jam_absen', '$status_baru', '$status_column')";
                } else {
                    $query_insert = "INSERT INTO absensi_siswa 
                        (siswa_id, nokartu, tanggal, jam_urutan, jam_absen, keterangan, status) 
                        VALUES 
                        ('$siswa_id', '$nokartu', '$tanggal', '$jam_urutan', '$jam_absen', '$status_baru', '$status_column')";
                }
                mysqli_query($conek, $query_insert);
            }
        }
    }
    
    $redirect_url = $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET);
    if ($client_time) {
        $redirect_url .= '&client_time=' . urlencode($client_time);
    }
    header("Location: " . $redirect_url);
    exit();
}

// ===== FUNGSI UNTUK FORMAT TANGGAL =====
function formatTanggal($tanggal) {
    $bulan = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $parts = explode('-', $tanggal);
    return $parts[2] . ' ' . $bulan[(int)$parts[1] - 1] . ' ' . $parts[0];
}

// ===== FUNGSI UNTUK GET STATUS CLASS =====
function getStatusClass($status) {
    if ($status == 'Hadir') return 'badge-hadir';
    if ($status == 'Sakit') return 'badge-sakit';
    if ($status == 'Izin') return 'badge-izin';
    if ($status == 'Alfa') return 'badge-alfa';
    if ($status == 'Telat') return 'badge-telat';
    return 'badge-belum-absen';
}

// Daftar kelas (hanya kelas yang diampu wali)
$daftar_kelas = [];
if (!empty($kelas_wali)) {
    $daftar_kelas[] = $kelas_wali;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "../header.php"; ?>
    <title>Rekapitulasi Absensi Siswa</title>
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
        max-width: 1400px;
        margin: 0 auto;
    }

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

    .jam-aktif-indicator {
        background: rgba(52, 211, 153, 0.12);
        border: 1px solid rgba(52, 211, 153, 0.20);
        border-radius: 20px;
        padding: 0.3rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.7rem;
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
        flex: 1;
    }

    .switch-group {
        display: flex;
        gap: 0.2rem;
        background: rgba(72, 49, 212, 0.04);
        padding: 0.2rem;
        border-radius: 10px;
        border: 1px solid rgba(72, 49, 212, 0.06);
        flex-wrap: wrap;
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
        flex: 1;
        min-width: 200px;
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

    .search-input {
        position: relative;
        min-width: 150px;
        flex: 0.5;
    }

    .search-input input {
        padding: 0.3rem 0.7rem 0.3rem 2rem;
        border-radius: 8px;
        border: 1px solid rgba(72, 49, 212, 0.12);
        background: rgba(255, 255, 255, 0.7);
        color: #1a1a2e;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        min-height: 30px;
        width: 100%;
    }

    .search-input input:focus {
        outline: none;
        border-color: #6c63ff;
        background: #ffffff;
        box-shadow: 0 0 20px rgba(72, 49, 212, 0.08);
    }

    .search-input .search-icon {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        color: #666688;
        font-size: 0.7rem;
    }

    .search-input .clear-search {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        color: #666688;
        font-size: 0.7rem;
        cursor: pointer;
        text-decoration: none;
        display: <?= isset($_GET['cari']) && $_GET['cari'] != '' ? 'block' : 'none' ?>;
    }

    .search-input .clear-search:hover {
        color: #dc2626;
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

    .badge-telat {
        background: rgba(251, 146, 60, 0.15);
        color: #ea580c;
    }

    .badge-belum-absen {
        background: rgba(156, 163, 175, 0.15);
        color: #6b7280;
    }

    .status-actions {
        display: flex;
        gap: 0.2rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .btn-status {
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        border: none;
        font-size: 0.55rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .btn-status:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .btn-sakit {
        background: rgba(96, 165, 250, 0.15);
        color: #2563eb;
    }

    .btn-sakit:hover {
        background: rgba(96, 165, 250, 0.30);
    }

    .btn-izin {
        background: rgba(251, 191, 36, 0.15);
        color: #b45309;
    }

    .btn-izin:hover {
        background: rgba(251, 191, 36, 0.30);
    }

    .btn-alfa {
        background: rgba(248, 113, 113, 0.15);
        color: #dc2626;
    }

    .btn-alfa:hover {
        background: rgba(248, 113, 113, 0.30);
    }

    .kelas-badge {
        background: rgba(108, 99, 255, 0.10);
        padding: 1px 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        color: #4831d4;
        font-weight: 600;
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

    .empty-state .sub-text {
        font-size: 0.7rem;
        color: rgba(26, 26, 46, 0.3);
        margin-top: 0.3rem;
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

    .tanggal-range {
        font-size: 0.65rem;
        color: #555577;
        background: rgba(72, 49, 212, 0.06);
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        border: 1px solid rgba(72, 49, 212, 0.08);
        display: inline-block;
    }

    .highlight {
        background: rgba(255, 235, 59, 0.4);
        padding: 0 2px;
        border-radius: 2px;
        font-weight: 600;
    }

    .tanggal-header {
        font-size: 0.6rem;
        color: #555577;
        background: rgba(72, 49, 212, 0.03);
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
        display: inline-block;
    }

    .data-count {
        font-size: 0.65rem;
        color: #555577;
        background: rgba(72, 49, 212, 0.06);
        padding: 0.2rem 0.8rem;
        border-radius: 20px;
        border: 1px solid rgba(72, 49, 212, 0.08);
    }

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

        .search-input {
            min-width: 100%;
            flex: 1;
        }

        .container-fluid {
            padding: 0.8rem;
        }
        
        .status-actions {
            flex-direction: column;
            align-items: center;
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

        .search-input {
            min-width: 100%;
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

        .export-menu {
            right: 0;
            left: 0;
            min-width: unset;
            width: 100%;
        }
        
        .status-actions {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-status {
            font-size: 0.5rem;
            padding: 0.1rem 0.4rem;
        }
        
        .jam-aktif-indicator {
            font-size: 0.6rem;
            padding: 0.2rem 0.7rem;
        }
        
        .client-time {
            font-size: 0.55rem;
            padding: 0.1rem 0.5rem;
        }
        
        .client-time .time {
            font-size: 0.65rem;
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
    </style>
</head>
<body>

<?php include "menu.php"; ?>

<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">📊</div>
            <div class="header-text">
                <h1>Rekapitulasi Absensi Siswa</h1>
                <?php if (!empty($kelas_wali)): ?>
                    <div class="sub">
                        <span style="background: rgba(245,158,11,0.12); padding: 2px 12px; border-radius: 12px; font-weight: 600; color: #f59e0b;">
                            <i class="fas fa-school"></i> Kelas <?php echo htmlspecialchars($kelas_wali); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-stats">
            <!-- INDICATOR JAM AKTIF -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <div class="jam-aktif-indicator <?= $has_active_jam ? '' : 'inactive' ?>">
                    <span class="dot"></span>
                    <?php if ($has_active_jam): ?>
                        <span>🟢 Jam Absen Aktif</span>
                        <span style="font-weight:400; color:#555577; margin-left:0.3rem;">
                            <?php 
                            $jam_names = [];
                            foreach ($active_jam_list as $jam) {
                                $jam_names[] = htmlspecialchars($jam['nama_jam']);
                            }
                            echo implode(' • ', $jam_names);
                            ?>
                        </span>
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

            <!-- TOTAL DATA ABSENSI -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <span class="number">
                    <?= count($absensi_data) ?>
                </span>
                <span class="label">Total Data Absensi</span>
            </div>

            <!-- TOTAL HADIR -->
            <div class="stat-chip" style="border-right:1px solid rgba(72,49,212,0.10); padding-right:1rem;">
                <span class="number">
                    <?= $total_hadir ?>
                </span>
                <span class="label">Hadir</span>
            </div>

            <!-- MODE -->
            <div class="stat-chip">
                <span class="number">
                    <?php 
                    $modeLabels = [
                        'harian' => '📅',
                        'mingguan' => '📆',
                        'bulanan' => '📊',
                        'semester' => '🎓'
                    ];
                    echo $modeLabels[$mode] ?? '📅';
                    ?>
                </span>
                <span class="label">Mode <?= ucfirst($mode) ?></span>
            </div>
        </div>
    </div>

    <!-- ===== TOP BAR ===== -->
    <div class="top-bar">
        
        <div class="rekap-switch">

            <!-- MODE SWITCH -->
            <div class="switch-group">
                <a href="?mode=harian<?= !empty($kelas_wali) ? '&kelas='.urlencode($kelas_wali) : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['cari']) ? '&cari='.$_GET['cari'] : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?><?= isset($_GET['tanggal']) ? '&tanggal='.$_GET['tanggal'] : '' ?>" class="switch-btn <?php echo ($mode == 'harian') ? 'active' : ''; ?>">📅 Harian</a>
                <a href="?mode=mingguan<?= !empty($kelas_wali) ? '&kelas='.urlencode($kelas_wali) : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['cari']) ? '&cari='.$_GET['cari'] : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?><?= isset($_GET['tanggal']) ? '&tanggal='.$_GET['tanggal'] : '' ?>" class="switch-btn <?php echo ($mode == 'mingguan') ? 'active' : ''; ?>">📆 Mingguan</a>
                <a href="?mode=bulanan<?= !empty($kelas_wali) ? '&kelas='.urlencode($kelas_wali) : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['cari']) ? '&cari='.$_GET['cari'] : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?><?= isset($_GET['bulan']) ? '&bulan='.$_GET['bulan'] : '' ?><?= isset($_GET['tahun']) ? '&tahun='.$_GET['tahun'] : '' ?>" class="switch-btn <?php echo ($mode == 'bulanan') ? 'active' : ''; ?>">📊 Bulanan</a>
                <a href="?mode=semester<?= !empty($kelas_wali) ? '&kelas='.urlencode($kelas_wali) : '' ?><?= isset($_GET['jurusan']) ? '&jurusan='.$_GET['jurusan'] : '' ?><?= isset($_GET['cari']) ? '&cari='.$_GET['cari'] : '' ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?><?= isset($_GET['semester']) ? '&semester='.$_GET['semester'] : '' ?><?= isset($_GET['tahun']) ? '&tahun='.$_GET['tahun'] : '' ?>" class="switch-btn <?php echo ($mode == 'semester') ? 'active' : ''; ?>">🎓 Semester</a>
            </div>

            <!-- FILTER -->
            <div class="filter-inline">
                <form method="GET" action="">

                    <?php  
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

                    } elseif ($mode == 'mingguan') {
                        $tanggal_akhir = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
                        echo '<input type="date" name="tanggal" value="'.$tanggal_akhir.'" title="Tanggal akhir minggu">';

                    } else {
                        $tanggalDipilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
                        echo '<input type="date" name="tanggal" value="'.$tanggalDipilih.'">';
                    }
                    ?>


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

                    <!-- SEARCH INPUT -->
                    <div class="search-input">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="cari" placeholder="Cari nama siswa..." value="<?= isset($_GET['cari']) ? htmlspecialchars($_GET['cari']) : '' ?>">
                        <?php if(isset($_GET['cari']) && $_GET['cari'] != ''): ?>
                            <a href="?<?= http_build_query(array_diff_key($_GET, ['cari' => ''])) ?><?= $client_time ? '&client_time='.urlencode($client_time) : '' ?>" class="clear-search" title="Hapus pencarian">✕</a>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="mode" value="<?= $mode ?>">
                    <?php if ($client_time): ?>
                        <input type="hidden" name="client_time" value="<?= htmlspecialchars($client_time) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-filter">🔍 Tampilkan</button>

                </form>
            </div>
        </div>

        <!-- EXPORT -->
        <?php  
            $params = $_GET;
            // Pastikan kelas wali tetap dipertahankan di URL export
            if (!empty($kelas_wali) && !isset($params['kelas'])) {
                $params['kelas'] = $kelas_wali;
            }
            $queryString = http_build_query($params);
        ?>
        <div class="export-dropdown">
            <button type="button" class="export-btn-main">
                📤 Export <span style="font-size:0.5rem; opacity:0.6;">▾</span>
            </button>
            <div class="export-menu">
                <a href="../export/export_absensi_siswa.php?type=excel&<?= $queryString ?>">
                    <span class="icon">📗</span> Excel
                </a>
                <a href="../export/export_absensi_siswa.php?type=word&<?= $queryString ?>">
                    <span class="icon">📘</span> Word
                </a>
                <div class="menu-divider"></div>
                <a href="../export/export_absensi_siswa.php?type=pdf&<?= $queryString ?>">
                    <span class="icon">📕</span> PDF
                </a>
            </div>
        </div>

    </div>

    <!-- ===== STATUS ABSENSI & INFO TANGGAL ===== -->
    <div style="padding: 0.5rem 1.2rem; background: rgba(255,255,255,0.6); border-radius: 0; margin-bottom: 0.1rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
        <?php
        if($has_active_jam) {
            echo '<span class="status-absen-aktif">🟢 Absensi Aktif</span>';
        } else {
            echo '<span class="status-absen-nonaktif">🔴 Absensi Tidak Aktif</span>';
        }
        
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
        
        echo '<span class="data-count">📊 ' . count($absensi_data) . ' data absensi ditemukan</span>';
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
                        <th style="width: 50px;" class="center">Kelas</th>
                        <th style="width: 90px;" class="center">Jurusan</th>
                        <th style="width: 100px;" class="center">Tanggal</th>
                        <?php 
                        foreach ($pengaturan_jam as $jam) {
                        ?>
                            <th style="width: 80px;" class="center"><?= htmlspecialchars($jam['nama_jam']) ?></th>
                        <?php } ?>
                        <th style="width: 130px;" class="center">Status</th>
                        <th style="width: 200px;" class="center">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    if (isset($conek) && $conek) {
                        $totalColumns = 7 + count($pengaturan_jam);
                        
                        if (empty($absensi_data)) {
                            echo '<tr><td colspan="'.$totalColumns.'" class="empty-state">
                                    <span class="icon">📭</span>
                                    <span class="text">Tidak Ada Data Absensi</span>
                                    <div class="sub-text">Belum ada data absensi yang tersimpan untuk periode ini.</div>
                                  </td></tr>';
                        } else {
                            $no = 0;
                            $keyword = isset($_GET['cari']) ? $_GET['cari'] : '';
                            
                            // Kelompokkan data berdasarkan siswa
                            $grouped_data = [];
                            foreach ($absensi_data as $key => $data) {
                                $siswa_id = $data['siswa_id'];
                                
                                if (!isset($grouped_data[$siswa_id])) {
                                    $grouped_data[$siswa_id] = [
                                        'nama_siswa' => $siswa_data[$siswa_id]['nama_siswa'],
                                        'nokartu' => $siswa_data[$siswa_id]['nokartu'],
                                        'kelas' => $siswa_data[$siswa_id]['kelas'],
                                        'jurusan' => $siswa_data[$siswa_id]['jurusan'],
                                        'data' => []
                                    ];
                                }
                                
                                $grouped_data[$siswa_id]['data'][$key] = $data;
                            }
                            
                            // Urutkan berdasarkan nama siswa
                            uksort($grouped_data, function($a, $b) use ($grouped_data) {
                                return strcmp($grouped_data[$a]['nama_siswa'], $grouped_data[$b]['nama_siswa']);
                            });
                            
                            foreach ($grouped_data as $siswa_id => $siswa) {
                                // Urutkan data berdasarkan tanggal (terbaru di atas)
                                $data_keys = array_keys($siswa['data']);
                                usort($data_keys, function($a, $b) {
                                    return strcmp($b, $a); // Descending
                                });
                                
                                $rowspan = count($data_keys);
                                $first_row = true;
                                
                                foreach ($data_keys as $key) {
                                    $no++;
                                    $absensi = $siswa['data'][$key];
                                    $tanggal = $absensi['tanggal'];
                                    
                                    $nama_display = htmlspecialchars($siswa['nama_siswa']);
                                    if (!empty($keyword)) {
                                        $nama_display = preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlight">$1</span>', $nama_display);
                                    }
                                    
                                    $status = $absensi['keterangan'] ?? 'Belum Absen';
                                    $statusClass = getStatusClass($status);
                                    ?>
                                    <tr>
                                        <?php if ($first_row): ?>
                                            <td class="center" style="color:rgba(26,26,46,0.3); font-weight:600; font-size:0.65rem;" rowspan="<?= $rowspan ?>"><?= $no ?></td>
                                            <td style="font-weight:600; color:#1a1a2e; font-size:0.7rem;" rowspan="<?= $rowspan ?>"><?= $nama_display ?></td>
                                            <td class="center" rowspan="<?= $rowspan ?>">
                                                <span class="kelas-badge"><?= htmlspecialchars($siswa['kelas']) ?></span>
                                            </td>
                                            <td class="center" style="color:#1a1a2e; font-size:0.7rem;" rowspan="<?= $rowspan ?>"><?= htmlspecialchars($siswa['jurusan']) ?></td>
                                        <?php endif; ?>
                                        
                                        <td class="center" style="color:#555577; font-size:0.65rem;">
                                            <span class="tanggal-header"><?= formatTanggal($tanggal) ?></span>
                                        </td>
                                        
                                        <?php 
                                        foreach ($pengaturan_jam as $jam) {
                                            $columnName = getJamColumnName($jam['urutan']);
                                            $jamValue = isset($absensi[$columnName]) ? $absensi[$columnName] : null;
                                        ?>
                                            <td class="center" style="color:#1a1a2e; font-size:0.7rem;">
                                                <?php 
                                                if ($jamValue) {
                                                    echo $jamValue;
                                                } else {
                                                    echo '<span style="color:rgba(26,26,46,0.2);">-</span>';
                                                }
                                                ?>
                                            </td>
                                        <?php } ?>
                                        
                                        <td class="center">
                                            <span class="badge-status <?= $statusClass ?>"><?= $status ?></span>
                                        </td>
                                        
                                        <td class="center">
                                            <div class="status-actions">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Set status Sakit untuk <?= htmlspecialchars($siswa['nama_siswa']) ?> tanggal <?= formatTanggal($tanggal) ?>?')">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="nokartu" value="<?= $siswa['nokartu'] ?>">
                                                    <input type="hidden" name="tanggal" value="<?= $tanggal ?>">
                                                    <input type="hidden" name="status_baru" value="Sakit">
                                                    <input type="hidden" name="jam_absen" value="<?= date('H:i:s') ?>">
                                                    <button type="submit" class="btn-status btn-sakit">🩺 Sakit</button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Set status Izin untuk <?= htmlspecialchars($siswa['nama_siswa']) ?> tanggal <?= formatTanggal($tanggal) ?>?')">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="nokartu" value="<?= $siswa['nokartu'] ?>">
                                                    <input type="hidden" name="tanggal" value="<?= $tanggal ?>">
                                                    <input type="hidden" name="status_baru" value="Izin">
                                                    <input type="hidden" name="jam_absen" value="<?= date('H:i:s') ?>">
                                                    <button type="submit" class="btn-status btn-izin">📝 Izin</button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Set status Alfa untuk <?= htmlspecialchars($siswa['nama_siswa']) ?> tanggal <?= formatTanggal($tanggal) ?>?')">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="nokartu" value="<?= $siswa['nokartu'] ?>">
                                                    <input type="hidden" name="tanggal" value="<?= $tanggal ?>">
                                                    <input type="hidden" name="status_baru" value="Alfa">
                                                    <input type="hidden" name="jam_absen" value="<?= date('H:i:s') ?>">
                                                    <button type="submit" class="btn-status btn-alfa">❌ Alfa</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    $first_row = false;
                                }
                            }
                        }
                    } else {
                        $totalColumns = 7 + count($pengaturan_jam);
                        echo '<tr><td colspan="'.$totalColumns.'" class="empty-state"><span class="icon">❌</span><span class="text">Koneksi database gagal</span></td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- ===== TABLE FOOTER STATISTIK ===== -->
        <?php if (!empty($absensi_data)): ?>
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
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include "../footer.php"; ?>

<script>
// ===== DETEKSI WAKTU CLIENT =====
function updateClientTime() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeString = hours + ':' + minutes + ':' + seconds;
    
    document.getElementById('clientTimeDisplay').textContent = timeString;
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

// ===== EXPORT DROPDOWN =====
document.querySelector('.export-btn-main')?.addEventListener('click', function(e) {
    e.stopPropagation();
    const menu = this.nextElementSibling;
    menu.classList.toggle('show');
});

document.addEventListener('click', function() {
    document.querySelectorAll('.export-menu').forEach(m => m.classList.remove('show'));
});

// ===== CLEAR SEARCH ON ESC =====
document.querySelector('.search-input input')?.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        this.value = '';
        const clearBtn = this.parentElement.querySelector('.clear-search');
        if (clearBtn) {
            window.location.href = clearBtn.href;
        }
    }
});

// ===== KEYBOARD SHORTCUT =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.export-menu').forEach(m => m.classList.remove('show'));
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
});

updateClientTime();

console.log('Client Time:', document.getElementById('clientTimeDisplay').textContent);
console.log('Total Data Absensi:', <?= count($absensi_data) ?>);
console.log('Has Active Jam: <?= $has_active_jam ? 'true' : 'false' ?>');
<?php if (!empty($kelas_wali)): ?>
console.log('Kelas Wali: <?= $kelas_wali ?>');
<?php endif; ?>
</script>

</body>
</html>