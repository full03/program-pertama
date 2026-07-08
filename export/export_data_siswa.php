<?php
include "../koneksi.php";
require_once '../dompdf/autoload.inc.php';
use Dompdf\Dompdf;

date_default_timezone_set('Asia/Jakarta');

// ======================================================
// PARAMETER
// ======================================================
$type   = $_GET['type']  ?? 'excel';   // excel | word | pdf
$jam    = $_GET['jam']   ?? date('H:i:s');

$judul      = "Data Siswa";
$judul_jam  = "Jam Export: $jam";

// ======================================================
// QUERY DATA GURU (HANYA TABEL GURU SAJA)
// ======================================================
$query = mysqli_query($conek,
    "SELECT nis, nama_siswa, jenis_kelamin, kelas, jurusan, no_hp, alamat
     FROM siswa
     ORDER BY nama_siswa ASC"
);

// ======================================================
// TEMPLATE HTML
// ======================================================
$html = "
<style>
    @page { 
        margin: 20px;
    }
    body {
        font-family: sans-serif;
        font-size: 12px;
    }

    .title {
        font-size: 20px;
        font-weight: bold;
        margin: 0;
        padding: 0;
    }

    .subtitle {
        font-size: 12px;
        margin: 0;
        padding: 0;
    }

    hr {
        margin-top: 8px;
        margin-bottom: 10px;
        border: 1px solid #000;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    table th, table td {
        border: 1px solid #000;
        padding: 6px;
    }
    table th {
        background: #e5e5e5;
        font-weight: bold;
        text-align: center;
    }

    h2 {
        text-align: center;
        margin: 0;
        padding: 0;
        margin-bottom: 5px;
    }
    .jam-export {
        text-align: center;
        margin: 0;
        margin-bottom: 10px;
    }

    .text-box {
        text-align: center;
        line-height: 1.1;   /* rapat tapi tetap rapi */
    }

    .title {
        font-size: 22px;
        font-weight: bold;
        margin: 0;
        padding: 0;
    }

    .judul {
        font-size: 18px;
        margin: 0;
        padding: 0;
    }

    .jam-export {
        font-size: 14px;
        margin: 2px 0;
        padding: 0;
    }

    .subtitle {
        font-size: 12px;
        margin: 2px 0;
        padding: 0;
    }
</style>

    <div class='text-box'>
        <p class='title'>MTs Plus Nuruttaqwa</p>
        <h3 class='judul'>$judul</h3>
        <p class='jam-export'>$judul_jam</p>
        <p class='subtitle'>Jl. Pendidikan No. 123, Kota Contoh — Telp: 0812-3456-7890</p>
    </div>


<hr>

<table>
<tr>
    <th>No</th>
    <th>NIS</th>
    <th>Nama Siswa</th>
    <th>Jenis Kelamin</th>
    <th>Kelas</th>
    <th>Jurusan</th>
    <th>No. Hp</th>
    <th>Alamat</th>
</tr>";

$no = 1;
while ($row = mysqli_fetch_assoc($query)) {

    $html .= "
    <tr>
        <td style='text-align:center;'>$no</td>
        <td style='text-align:center;'>{$row['nis']}</td>
        <td>{$row['nama_siswa']}</td>
        <td style='text-align:center;'>{$row['jenis_kelamin']}</td>
        <td style='text-align:center;'>{$row['kelas']}</td>
        <td style='text-align:center;'>{$row['jurusan']}</td>
        <td style='text-align:center;'>{$row['no_hp']}</td>
        <td>{$row['alamat']}</td>
    </tr>";

    $no++;
}

$html .= "</table>";

// ======================================================
// EXPORT EXCEL
// ======================================================
if ($type === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Data_Siswa.xls");

    echo "
    <html>
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:excel'
          xmlns='http://www.w3.org/TR/REC-html40'>

    <head>
        <style>
            @page ExcelSection1 {
                size: 29.7cm 21cm; /* A4 landscape */
                margin: 0.3cm;
            }
            div.ExcelSection1 { page: ExcelSection1; }
            table {
                width: 100%;
            }
            table th {
                background: #e5e5e5;
            }
        </style>
    </head>

    <body>
        <div class='ExcelSection1'>
            $html
        </div>
    </body>

    </html>";

    exit;
}



// ======================================================
// EXPORT WORD
// ======================================================
if ($type === 'word') {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=Rekap_Data_Siswa.doc");

    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office'
          xmlns:w='urn:schemas-microsoft-com:office:word'
          xmlns='http://www.w3.org/TR/REC-html40'>

    <head>
        <style>
            @page WordSection1 {
                size: 29.7cm 21cm; /* A4 landscape */
                margin: 0.3cm;
            }
            div.WordSection1 { page: WordSection1; }
            table {
                width: 100%;
            }
            table th {
                background: #e5e5e5;
            }
        </style>
    </head>

    <body>
        <div class='WordSection1'>
            $html
        </div>
    </body>

    </html>";
    exit;
}



// ======================================================
// EXPORT PDF (DOMPDF)
// ======================================================
if ($type === 'pdf') {
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    // Landscape dan full halaman
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("Rekap_Data_Siswa.pdf", ["Attachment" => true]);
    exit;
}
