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

// Include Dompdf
require_once '../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

date_default_timezone_set('Asia/Jakarta');

// =============================================
// = AMBIL PARAMETER
// =============================================
$type    = isset($_GET['type']) ? $_GET['type'] : 'excel';
$mode    = isset($_GET['mode']) ? $_GET['mode'] : 'harian';
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$bulan   = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun   = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$semester = isset($_GET['semester']) ? $_GET['semester'] : '1';
$kelas   = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$jurusan = isset($_GET['jurusan']) ? $_GET['jurusan'] : '';
$cari    = isset($_GET['cari']) ? $_GET['cari'] : '';
$client_time = isset($_GET['client_time']) ? $_GET['client_time'] : date('H:i:s');

$judul_jam = "Jam Export: $client_time";

// =============================================
// = FILTER KELAS, JURUSAN & PENCARIAN
// =============================================
$filterKelas = "";
$filterJurusan = "";
$filterCari = "";

if (!empty($kelas)) {
    $kelasFilter = mysqli_real_escape_string($conek, $kelas);
    $filterKelas = " AND b.kelas='$kelasFilter'";
}

if (!empty($jurusan)) {
    $jurusanFilter = mysqli_real_escape_string($conek, $jurusan);
    $filterJurusan = " AND b.jurusan='$jurusanFilter'";
}

if (!empty($cari)) {
    $cariFilter = mysqli_real_escape_string($conek, $cari);
    $filterCari = " AND b.nama_siswa LIKE '%$cariFilter%'";
}

// =============================================
// = TENTUKAN FILTER TANGGAL
// =============================================
$tanggal_awal_filter = null;
$tanggal_akhir_filter = null;
$nama_periode = "";

if ($mode == 'semester') {
    if ($semester == '1') {
        $tanggal_awal_filter = ($tahun - 1) . "-07-01";
        $tanggal_akhir_filter = ($tahun - 1) . "-12-31";
        $nama_periode = "Semester 1 (Juli-Desember " . ($tahun - 1) . ")";
        $judul = "Rekap Semester Absensi Siswa: $nama_periode";
    } else {
        $tanggal_awal_filter = $tahun . "-01-01";
        $tanggal_akhir_filter = $tahun . "-06-30";
        $nama_periode = "Semester 2 (Januari-Juni " . $tahun . ")";
        $judul = "Rekap Semester Absensi Siswa: $nama_periode";
    }
} elseif ($mode == 'bulanan') {
    $bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
    $tanggal_awal_filter = date('Y-m-01', strtotime("$tahun-$bulan_padded-01"));
    $tanggal_akhir_filter = date('Y-m-t', strtotime("$tahun-$bulan_padded-01"));
    $judul = "Rekap Bulanan Absensi Siswa: " . date('F Y', strtotime("$tahun-$bulan_padded-01"));
} elseif ($mode == 'mingguan') {
    $tanggal_akhir = $tanggal;
    $tanggal_awal = date('Y-m-d', strtotime('-6 days', strtotime($tanggal_akhir)));
    $tanggal_awal_filter = $tanggal_awal;
    $tanggal_akhir_filter = $tanggal_akhir;
    $judul = "Rekap Mingguan Absensi Siswa (" . date('d-m-Y', strtotime($tanggal_awal)) . " s/d " . date('d-m-Y', strtotime($tanggal_akhir)) . ")";
} else {
    $tanggal_awal_filter = $tanggal;
    $tanggal_akhir_filter = $tanggal;
    $judul = "Rekap Harian Absensi Siswa: " . date('d-m-Y', strtotime($tanggal));
}

// =============================================
// = AMBIL DATA PENGATURAN JAM
// =============================================
$query_pengaturan = "SELECT * FROM pengaturan_jam_absen ORDER BY urutan ASC";
$result_pengaturan = mysqli_query($conek, $query_pengaturan);
$pengaturan_jam = [];
while ($row = mysqli_fetch_assoc($result_pengaturan)) {
    $pengaturan_jam[] = $row;
}

// =============================================
// = FUNGSI GET JAM COLUMN NAME
// =============================================
function getJamColumnName($urutan) {
    $mapping = [
        1 => 'jam_masuk_pertama',
        2 => 'jam_istirahat',
        3 => 'jam_masuk_kedua',
        4 => 'jam_pulang'
    ];
    return isset($mapping[$urutan]) ? $mapping[$urutan] : null;
}

// =============================================
// = AMBIL DATA ABSENSI
// =============================================
$absensi_data = [];

if ($tanggal_awal_filter && $tanggal_akhir_filter) {
    $tanggal_awal_clean = mysqli_real_escape_string($conek, $tanggal_awal_filter);
    $tanggal_akhir_clean = mysqli_real_escape_string($conek, $tanggal_akhir_filter);
    
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
    $filterKelas
    $filterJurusan
    $filterCari
    ORDER BY a.siswa_id, a.tanggal, a.jam_urutan
    ";
    
    $result_absensi = mysqli_query($conek, $query_absensi);
    
    if ($result_absensi) {
        while ($row = mysqli_fetch_assoc($result_absensi)) {
            $key = $row['siswa_id'] . '_' . $row['tanggal'];
            
            if (!isset($absensi_data[$key])) {
                $absensi_data[$key] = [
                    'siswa_id' => $row['siswa_id'],
                    'nokartu' => $row['nokartu'],
                    'nama_siswa' => $row['nama_siswa'],
                    'kelas' => $row['kelas'],
                    'jurusan' => $row['jurusan'],
                    'tanggal' => $row['tanggal'],
                    'jam_masuk_pertama' => null,
                    'jam_istirahat' => null,
                    'jam_masuk_kedua' => null,
                    'jam_pulang' => null,
                    'keterangan' => 'Belum Absen',
                    'status' => 'hadir',
                    'telat' => null,
                ];
            }
            
            $jam_column = getJamColumnName($row['jam_urutan']);
            if ($jam_column && isset($row[$jam_column])) {
                $absensi_data[$key][$jam_column] = $row[$jam_column];
            }
            
            if ($row['keterangan']) {
                $absensi_data[$key]['keterangan'] = $row['keterangan'];
                $absensi_data[$key]['status'] = $row['status'];
                $absensi_data[$key]['telat'] = $row['telat'];
            }
        }
    }
}

// =============================================
// = HITUNG STATISTIK
// =============================================
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

// =============================================
// = BUAT HTML UNTUK EXPORT
// =============================================
function buildExportHTML($data, $pengaturan_jam, $judul, $judul_jam, $total_hadir, $total_sakit, $total_izin, $total_alfa) {
    $html = "
    <style>
        @page { 
            margin: 20px;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            background: #ffffff;
        }

        .text-box {
            text-align: center;
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
            margin: 0;
            padding: 0;
            color: #1a1a2e;
        }

        .judul {
            font-size: 16px;
            margin: 5px 0 0 0;
            padding: 0;
            color: #333355;
        }

        .jam-export {
            font-size: 12px;
            margin: 2px 0;
            padding: 0;
            color: #555577;
        }

        .subtitle {
            font-size: 11px;
            margin: 2px 0;
            padding: 0;
            color: #666688;
        }
        
        hr {
            margin-top: 8px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 10px;
        }
        
        table th {
            background: #e8e6f0;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            padding: 6px 8px;
            color: #1a1a2e;
        }
        
        table td {
            border: 1px solid #000;
            padding: 5px 8px;
            color: #1a1a2e;
        }
        
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        .badge-hadir { color: #0b8a5e; font-weight: bold; }
        .badge-sakit { color: #2563eb; font-weight: bold; }
        .badge-izin { color: #b45309; font-weight: bold; }
        .badge-alfa { color: #dc2626; font-weight: bold; }
        .badge-belum-absen { color: #6b7280; font-weight: bold; }
        
        .kelas-badge {
            background: rgba(108, 99, 255, 0.10);
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: 600;
            color: #4831d4;
        }
    </style>

    <div class='text-box'>
        <p class='title'>MTs Plus Nuruttaqwa</p>
        <p class='judul'>$judul</p>
        <p class='jam-export'>$judul_jam</p>
        <p class='subtitle'>Jl. Pendidikan No. 123, Kota Contoh — Telp: 0812-3456-7890</p>
    </div>

    <hr>

    <table>
        <thead>
            <tr>
                <th style='width: 40px;'>No</th>
                <th style='min-width: 150px; text-align: left;'>Nama Siswa</th>
                <th style='width: 70px;'>Kelas</th>
                <th style='width: 100px;'>Jurusan</th>
                <th style='width: 100px;'>Tanggal</th>";
    
    foreach ($pengaturan_jam as $jam) {
        $html .= "<th style='width: 80px;'>" . htmlspecialchars($jam['nama_jam']) . "</th>";
    }
    
    $html .= "
                <th style='width: 80px;'>Status</th>
            </tr>
        </thead>
        <tbody>";

    if (empty($data)) {
        $totalColumns = 5 + count($pengaturan_jam) + 1;
        $html .= "
            <tr>
                <td colspan='$totalColumns' style='text-align: center; padding: 30px; color: #999;'>
                    <strong>Tidak Ada Data Absensi</strong><br>
                    <span style='font-size: 9px; color: #bbb;'>Belum ada data absensi yang tersimpan untuk periode ini.</span>
                </td>
            </tr>";
    } else {
        $no = 0;
        
        $grouped_data = [];
        foreach ($data as $key => $row) {
            $siswa_id = $row['siswa_id'];
            $tanggal = $row['tanggal'];
            
            if (!isset($grouped_data[$siswa_id])) {
                $grouped_data[$siswa_id] = [
                    'nama_siswa' => $row['nama_siswa'],
                    'nokartu' => $row['nokartu'],
                    'kelas' => $row['kelas'],
                    'jurusan' => $row['jurusan'],
                    'data' => []
                ];
            }
            
            $grouped_data[$siswa_id]['data'][$tanggal] = $row;
        }
        
        ksort($grouped_data);
        
        foreach ($grouped_data as $siswa_id => $siswa) {
            $tanggal_keys = array_keys($siswa['data']);
            sort($tanggal_keys);
            
            $rowspan = count($tanggal_keys);
            $first_row = true;
            
            foreach ($tanggal_keys as $tanggal) {
                $no++;
                $absensi = $siswa['data'][$tanggal];
                
                $keterangan = $absensi['keterangan'] ? $absensi['keterangan'] : 'Belum Absen';
                $badge_class = '';
                if ($keterangan == 'Hadir') {
                    $badge_class = 'badge-hadir';
                } elseif ($keterangan == 'Sakit') {
                    $badge_class = 'badge-sakit';
                } elseif ($keterangan == 'Izin') {
                    $badge_class = 'badge-izin';
                } elseif ($keterangan == 'Alfa') {
                    $badge_class = 'badge-alfa';
                } else {
                    $badge_class = 'badge-belum-absen';
                }
                
                $tanggal_format = date('d-m-Y', strtotime($tanggal));
                
                $html .= "<tr>";
                
                if ($first_row) {
                    $html .= "<td class='text-center' rowspan='$rowspan' style='font-weight: 600; color: #888;'>$no</td>";
                    $html .= "<td class='text-left' rowspan='$rowspan' style='font-weight: 600;'>" . htmlspecialchars($siswa['nama_siswa']) . "</td>";
                    $html .= "<td class='text-center' rowspan='$rowspan'><span class='kelas-badge'>" . htmlspecialchars($siswa['kelas']) . "</span></td>";
                    $html .= "<td class='text-center' rowspan='$rowspan'>" . htmlspecialchars($siswa['jurusan']) . "</td>";
                }
                
                $html .= "<td class='text-center'>$tanggal_format</td>";
                
                foreach ($pengaturan_jam as $jam) {
                    $columnName = getJamColumnName($jam['urutan']);
                    $jamValue = isset($absensi[$columnName]) ? $absensi[$columnName] : '-';
                    $html .= "<td class='text-center'>" . htmlspecialchars($jamValue) . "</td>";
                }
                
                $html .= "<td class='text-center'><span class='$badge_class'>$keterangan</span></td>";
                $html .= "</tr>";
                
                $first_row = false;
            }
        }
    }

    $html .= "
        </tbody>
    </table>";

    return $html;
}

// =============================================
// = BUILD HTML
// =============================================
$html_content = buildExportHTML(
    $absensi_data, 
    $pengaturan_jam, 
    $judul, 
    $judul_jam,
    $total_hadir,
    $total_sakit,
    $total_izin,
    $total_alfa
);

// =============================================
// = EXPORT EXCEL
// =============================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Absensi_Siswa_" . date('Y-m-d') . ".xls");
    
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:excel'
          xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Absensi</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { 
                border-collapse: collapse; 
                width: 100%;
            }
            table th { 
                background: #e8e6f0; 
                font-weight: bold; 
                border: 1px solid #000; 
                padding: 6px 8px; 
                text-align: center;
            }
            table td { 
                border: 1px solid #000; 
                padding: 5px 8px; 
            }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .badge-hadir { color: #0b8a5e; font-weight: bold; }
            .badge-sakit { color: #2563eb; font-weight: bold; }
            .badge-izin { color: #b45309; font-weight: bold; }
            .badge-alfa { color: #dc2626; font-weight: bold; }
            .badge-belum-absen { color: #6b7280; font-weight: bold; }
            .kelas-badge { font-weight: 600; }
            .title { font-size: 22px; font-weight: bold; }
            .judul { font-size: 16px; }
            .subtitle { font-size: 11px; }
        </style>
    </head>
    <body>
        <div style='text-align: center;'>
            <p class='title'>MTs Plus Nuruttaqwa</p>
            <p class='judul'>$judul</p>
            <p style='font-size: 12px;'>$judul_jam</p>
            <p class='subtitle'>Jl. Pendidikan No. 123, Kota Contoh — Telp: 0812-3456-7890</p>
        </div>
        <hr>
        $html_content
    </body>
    </html>";
    exit;
}

// =============================================
// = EXPORT WORD
// =============================================
if ($type === 'word') {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=Rekap_Absensi_Siswa_" . date('Y-m-d') . ".doc");
    
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:word'
          xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <!--[if gte mso 9]>
        <xml>
            <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
            </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page {
                size: 29.7cm 21cm;
                margin: 1cm;
            }
            table { 
                border-collapse: collapse; 
                width: 100%;
            }
            table th { 
                background: #e8e6f0; 
                font-weight: bold; 
                border: 1px solid #000; 
                padding: 6px 8px; 
                text-align: center;
            }
            table td { 
                border: 1px solid #000; 
                padding: 5px 8px; 
            }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            .badge-hadir { color: #0b8a5e; font-weight: bold; }
            .badge-sakit { color: #2563eb; font-weight: bold; }
            .badge-izin { color: #b45309; font-weight: bold; }
            .badge-alfa { color: #dc2626; font-weight: bold; }
            .badge-belum-absen { color: #6b7280; font-weight: bold; }
            .kelas-badge { font-weight: 600; }
            .title { font-size: 22px; font-weight: bold; }
            .judul { font-size: 16px; }
            .subtitle { font-size: 11px; }
        </style>
    </head>
    <body>
        <div style='text-align: center;'>
            <p class='title'>MTs Plus Nuruttaqwa</p>
            <p class='judul'>$judul</p>
            <p style='font-size: 12px;'>$judul_jam</p>
            <p class='subtitle'>Jl. Pendidikan No. 123, Kota Contoh — Telp: 0812-3456-7890</p>
        </div>
        <hr>
        $html_content
    </body>
    </html>";
    exit;
}

// =============================================
// = EXPORT PDF
// =============================================
if ($type === 'pdf') {
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("Rekap_Absensi_Siswa_" . date('Y-m-d') . ".pdf", array("Attachment" => true));
    exit;
}

header("Location: ../rekapitulasi_absensi_siswa.php");
exit;
?>