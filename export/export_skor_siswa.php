<?php
// =============================================
// PASTIKAN DOMPDF TELAH DI-LOAD SEBELUM LOGIN CHECK
// =============================================
require_once '../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// =============================================
// SESSION & LOGIN CHECK
// =============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// =============================================
// KONEKSI DATABASE
// =============================================
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// =============================================
// AMBIL PARAMETER DARI URL
// =============================================
$type    = $_GET['type'] ?? 'excel';
$mode    = $_GET['mode'] ?? 'harian';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$bulan   = $_GET['bulan'] ?? date('m');
$tahun   = $_GET['tahun'] ?? date('Y');
$semester = $_GET['semester'] ?? '1';
$kelas   = $_GET['kelas'] ?? '';
$jurusan = $_GET['jurusan'] ?? '';
$search_nama = $_GET['search_nama'] ?? '';

// Waktu export
date_default_timezone_set('Asia/Jakarta');
$jam_export = date('H:i:s');
$judul_jam = "Jam Export: $jam_export";

// Nama file
$filename = "Rekap_Skor_Siswa_" . date('Ymd_His');

// =============================================
// TENTUKAN FILTER TANGGAL
// =============================================
$tanggal_awal_filter = null;
$tanggal_akhir_filter = null;
$judul_mode = '';

if ($mode == 'harian') {
    $tanggal_awal_filter = $tanggal;
    $tanggal_akhir_filter = $tanggal;
    $judul_mode = "Rekap Harian Absensi Siswa: " . date('d-m-Y', strtotime($tanggal));
    
} elseif ($mode == 'mingguan') {
    $tanggal_akhir = $tanggal ? $tanggal : date('Y-m-d');
    $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
    $tanggal_awal_filter = $tanggal_awal;
    $tanggal_akhir_filter = $tanggal_akhir;
    $judul_mode = "Rekap Mingguan Absensi Siswa (" . date('d-m-Y', strtotime($tanggal_awal)) . " s/d " . date('d-m-Y', strtotime($tanggal_akhir)) . ")";
    
} elseif ($mode == 'bulanan') {
    $bulan_int = intval($bulan);
    $tahun_int = intval($tahun);
    $tanggal_awal_filter = date('Y-m-d', mktime(0, 0, 0, $bulan_int, 1, $tahun_int));
    $tanggal_akhir_filter = date('Y-m-t', mktime(0, 0, 0, $bulan_int, 1, $tahun_int));
    $nama_bulan = date('F Y', mktime(0, 0, 0, $bulan_int, 1, $tahun_int));
    $judul_mode = "Rekap Bulanan Absensi Siswa: $nama_bulan";
    
} elseif ($mode == 'semester') {
    $semester_int = intval($semester);
    $tahun_int = intval($tahun);
    
    if ($semester_int == 1) {
        $tanggal_awal_filter = ($tahun_int - 1) . '-07-01';
        $tanggal_akhir_filter = ($tahun_int - 1) . '-12-31';
        $nama_semester = "Semester 1 (Juli-Desember " . ($tahun_int - 1) . ")";
    } else {
        $tanggal_awal_filter = $tahun_int . '-01-01';
        $tanggal_akhir_filter = $tahun_int . '-06-30';
        $nama_semester = "Semester 2 (Januari-Juni " . $tahun_int . ")";
    }
    $judul_mode = "Rekap Semester Absensi Siswa: $nama_semester";
}

// =============================================
// QUERY DATA DARI RIWAYAT_SISWA
// =============================================
$sql = null;
$total_hadir = 0;
$total_sakit = 0;
$total_izin = 0;
$total_alfa = 0;
$data_rows = array();

if ($tanggal_awal_filter && $tanggal_akhir_filter) {
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

    // Filter tanggal
    $tanggal_awal_clean = mysqli_real_escape_string($conek, $tanggal_awal_filter);
    $tanggal_akhir_clean = mysqli_real_escape_string($conek, $tanggal_akhir_filter);
    $query .= " AND r.tanggal BETWEEN '$tanggal_awal_clean' AND '$tanggal_akhir_clean' ";

    // Filter kelas
    if (!empty($kelas)) {
        $kelas_clean = mysqli_real_escape_string($conek, $kelas);
        $query .= " AND s.kelas = '$kelas_clean' ";
    }

    // Filter jurusan
    if (!empty($jurusan)) {
        $jurusan_clean = mysqli_real_escape_string($conek, $jurusan);
        $query .= " AND s.jurusan = '$jurusan_clean' ";
    }

    // Filter pencarian nama
    if (!empty($search_nama)) {
        $search_nama_clean = mysqli_real_escape_string($conek, $search_nama);
        $query .= " AND s.nama_siswa LIKE '%$search_nama_clean%' ";
    }

    $query .= " GROUP BY s.nokartu, s.nama_siswa, s.kelas, s.jurusan, s.id 
                ORDER BY 
                    CASE WHEN MAX(r.tanggal) IS NULL THEN 1 ELSE 0 END,
                    MAX(r.tanggal) DESC,
                    s.id ASC";

    $sql = mysqli_query($conek, $query);
    
    if (!$sql) {
        die("Error query: " . mysqli_error($conek));
    }
    
    // Simpan data ke array untuk digunakan nanti
    if ($sql && mysqli_num_rows($sql) > 0) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $data_rows[] = $row;
            $total_hadir += intval($row['hadir']);
            $total_sakit += intval($row['sakit']);
            $total_izin += intval($row['izin']);
            $total_alfa += intval($row['alfa']);
        }
    }
}

// =============================================
// TEMPLATE HTML UNTUK EXPORT
// =============================================
$html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        @page { 
            margin: 15px;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4831d4;
        }
        
        .header .school-name {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }
        
        .header .title {
            font-size: 18px;
            font-weight: 600;
            color: #4831d4;
            margin: 4px 0 2px 0;
        }
        
        .header .subtitle {
            font-size: 11px;
            color: #666688;
            margin: 2px 0;
        }
        
        .header .jam-export {
            font-size: 11px;
            color: #888899;
            margin: 2px 0;
        }
        
        .info-bar {
            background: #f0edf7;
            padding: 6px 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 10px;
            color: #555577;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .info-bar .label {
            font-weight: 600;
            color: #1a1a2e;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        
        table th {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #ffffff;
            padding: 6px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #3d28b5;
        }
        
        table td {
            padding: 5px 8px;
            border: 1px solid #ddd8e8;
            text-align: center;
        }
        
        table tr:nth-child(even) {
            background: #f8f7fc;
        }
        
        table tr:hover {
            background: #edeaf5;
        }
        
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 10px;
            min-width: 20px;
        }
        
        .badge-hadir { background: rgba(52, 211, 153, 0.2); color: #0b8a5e; }
        .badge-sakit { background: rgba(96, 165, 250, 0.2); color: #2563eb; }
        .badge-izin { background: rgba(251, 191, 36, 0.2); color: #b45309; }
        .badge-alfa { background: rgba(248, 113, 113, 0.2); color: #dc2626; }
        
        .footer-stats {
            margin-top: 12px;
            padding: 8px 10px;
            background: #f0edf7;
            border-radius: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .footer-stats .stat-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .footer-stats .stat-item .val-hadir { color: #0b8a5e; font-weight: 700; }
        .footer-stats .stat-item .val-sakit { color: #2563eb; font-weight: 700; }
        .footer-stats .stat-item .val-izin { color: #b45309; font-weight: 700; }
        .footer-stats .stat-item .val-alfa { color: #dc2626; font-weight: 700; }
        .footer-stats .stat-item .val-total { color: #4831d4; font-weight: 700; }
        
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .empty-data {
            text-align: center;
            padding: 30px;
            color: #9999aa;
            font-size: 13px;
        }
        
        .empty-data .icon {
            font-size: 30px;
            display: block;
            margin-bottom: 8px;
        }
        
        .kelas-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            background: rgba(108, 99, 255, 0.1);
            color: #4831d4;
            font-weight: 600;
            font-size: 9px;
        }
        
        .filter-info {
            font-size: 9px;
            color: #666688;
            background: #f5f4fa;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class='header'>
        <p class='school-name'>🏫 MTs Plus Nuruttaqwa</p>
        <p class='title'>$judul_mode</p>
        <p class='jam-export'>🕐 $judul_jam</p>
        <p class='subtitle'>Jl. Pendidikan No. 123, Kota Contoh — Telp: 0812-3456-7890</p>
    </div>
    
    <div class='info-bar'>
        <span>📌 <span class='label'>Mode:</span> " . ucfirst($mode) . "</span>
";

// Tambahkan info filter
if ($mode == 'harian') {
    $html .= "<span>📅 <span class='label'>Tanggal:</span> " . date('d F Y', strtotime($tanggal)) . "</span>";
} elseif ($mode == 'mingguan') {
    $html .= "<span>📆 <span class='label'>Periode:</span> " . date('d F Y', strtotime($tanggal_awal_filter)) . " - " . date('d F Y', strtotime($tanggal_akhir_filter)) . "</span>";
} elseif ($mode == 'bulanan') {
    $html .= "<span>📊 <span class='label'>Bulan:</span> " . date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) . "</span>";
} elseif ($mode == 'semester') {
    $semester_nama = $semester == 1 ? 'Semester 1 (Jul-Des)' : 'Semester 2 (Jan-Jun)';
    $tahun_akademik = $semester == 1 ? ($tahun - 1) . '/' . $tahun : $tahun;
    $html .= "<span>🎓 <span class='label'>$semester_nama</span> TA: $tahun_akademik</span>";
}

if (!empty($kelas)) {
    $html .= "<span>🏷️ <span class='label'>Kelas:</span> $kelas</span>";
}
if (!empty($jurusan)) {
    $html .= "<span>📚 <span class='label'>Jurusan:</span> $jurusan</span>";
}
if (!empty($search_nama)) {
    $html .= "<span>🔍 <span class='label'>Pencarian:</span> \"" . htmlspecialchars($search_nama) . "\"</span>";
}

$html .= "
    </div>
";

// =============================================
// TABEL DATA
// =============================================
if (count($data_rows) > 0) {
    $html .= "
    <table>
        <thead>
            <tr>
                <th style='width:30px;'>No</th>
                <th style='text-align:left;'>Nama Siswa</th>
                <th style='width:50px;'>Kelas</th>
                <th style='width:70px;'>Jurusan</th>
                <th style='width:45px;'>Hadir</th>
                <th style='width:45px;'>Sakit</th>
                <th style='width:45px;'>Izin</th>
                <th style='width:45px;'>Alfa</th>
                <th style='width:55px;'>Total</th>
            </tr>
        </thead>
        <tbody>
    ";
    
    $no = 1;
    foreach ($data_rows as $row) {
        $total_per_siswa = $row['hadir'] + $row['sakit'] + $row['izin'] + $row['alfa'];
        
        $html .= "
            <tr>
                <td style='text-align:center; font-weight:600; color:#888899;'>$no</td>
                <td style='text-align:left; font-weight:500;'>" . htmlspecialchars($row['nama_siswa']) . "</td>
                <td><span class='kelas-badge'>" . htmlspecialchars($row['kelas']) . "</span></td>
                <td>" . htmlspecialchars($row['jurusan']) . "</td>
                <td><span class='badge badge-hadir'>" . $row['hadir'] . "</span></td>
                <td><span class='badge badge-sakit'>" . $row['sakit'] . "</span></td>
                <td><span class='badge badge-izin'>" . $row['izin'] . "</span></td>
                <td><span class='badge badge-alfa'>" . $row['alfa'] . "</span></td>
                <td style='font-weight:700; color:#4831d4;'>$total_per_siswa</td>
            </tr>
        ";
        $no++;
    }
    
    $total_keseluruhan = $total_hadir + $total_sakit + $total_izin + $total_alfa;
    $html .= "
        </tbody>
    </table>
    
    <div class='footer-stats'>
        <div class='stat-item'><span class='label'>📊 Total Keseluruhan:</span></div>
        <div class='stat-item'>✅ Hadir: <span class='val-hadir'>$total_hadir</span></div>
        <div class='stat-item'>🤒 Sakit: <span class='val-sakit'>$total_sakit</span></div>
        <div class='stat-item'>📋 Izin: <span class='val-izin'>$total_izin</span></div>
        <div class='stat-item'>❌ Alfa: <span class='val-alfa'>$total_alfa</span></div>
        <div class='stat-item'>🎯 Total: <span class='val-total'>$total_keseluruhan</span></div>
        <div class='stat-item'>👥 Siswa: <span class='val-total'>" . ($no - 1) . "</span></div>
    </div>
    ";
    
} else {
    $html .= "
    <div class='empty-data'>
        <span class='icon'>📊</span>
        <p>Belum ada data riwayat absensi untuk periode yang dipilih.</p>
        <p style='font-size:11px; color:#aaa;'>Silahkan lakukan absensi terlebih dahulu.</p>
    </div>
    ";
}

$html .= "
    <div style='margin-top:15px; text-align:center; font-size:9px; color:#9999aa; border-top:1px solid #eee; padding-top:8px;'>
        Dicetak pada: " . date('d F Y H:i:s') . " | © MTs Plus Nuruttaqwa
    </div>
</body>
</html>
";

// =============================================
// PROSES EXPORT SESUAI TYPE
// =============================================

if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename={$filename}.xls");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");
    
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:excel'
          xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset='UTF-8'>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page {
                size: 29.7cm 21cm;
                margin: 0.5cm;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
                font-family: 'Segoe UI', Arial, sans-serif;
            }
            table th {
                background: #4831d4;
                color: #ffffff;
                padding: 6px 8px;
                border: 1px solid #3d28b5;
                text-align: center;
                font-weight: 700;
            }
            table td {
                padding: 4px 8px;
                border: 1px solid #ddd8e8;
                text-align: center;
            }
            .header {
                text-align: center;
                margin-bottom: 10px;
            }
            .header .school-name {
                font-size: 20px;
                font-weight: 700;
                color: #1a1a2e;
            }
            .header .title {
                font-size: 16px;
                font-weight: 600;
                color: #4831d4;
            }
            .badge-hadir { color: #0b8a5e; font-weight: 700; }
            .badge-sakit { color: #2563eb; font-weight: 700; }
            .badge-izin { color: #b45309; font-weight: 700; }
            .badge-alfa { color: #dc2626; font-weight: 700; }
            .footer-stats {
                margin-top: 10px;
                padding: 6px 10px;
                background: #f0edf7;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        $html
    </body>
    </html>";
    exit;
}

if ($type === 'word') {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename={$filename}.doc");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");
    
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:word'
          xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta charset='UTF-8'>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page {
                size: 29.7cm 21cm;
                margin: 0.5cm;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
                font-family: 'Segoe UI', Arial, sans-serif;
            }
            table th {
                background: #4831d4;
                color: #ffffff;
                padding: 6px 8px;
                border: 1px solid #3d28b5;
                text-align: center;
                font-weight: 700;
            }
            table td {
                padding: 4px 8px;
                border: 1px solid #ddd8e8;
                text-align: center;
            }
            .header {
                text-align: center;
                margin-bottom: 10px;
            }
            .header .school-name {
                font-size: 20px;
                font-weight: 700;
                color: #1a1a2e;
            }
            .header .title {
                font-size: 16px;
                font-weight: 600;
                color: #4831d4;
            }
            .badge-hadir { color: #0b8a5e; font-weight: 700; }
            .badge-sakit { color: #2563eb; font-weight: 700; }
            .badge-izin { color: #b45309; font-weight: 700; }
            .badge-alfa { color: #dc2626; font-weight: 700; }
            .footer-stats {
                margin-top: 10px;
                padding: 6px 10px;
                background: #f0edf7;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        $html
    </body>
    </html>";
    exit;
}

if ($type === 'pdf') {
    // Konfigurasi DOMPDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("{$filename}.pdf", ["Attachment" => true]);
    exit;
}

// Jika type tidak dikenal
echo "Format export tidak didukung.";
exit;
?>