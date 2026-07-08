<?php
// ===== AWAL: Output buffering dan session =====
ob_start();

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Koneksi database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

$status = '';
$pesan = '';
$hasil = []; // Inisialisasi variabel $hasil

// ===== AMBIL DATA KARTU DARI tmprfid_siswa =====
$queryTmp = "SELECT nokartu FROM tmprfid_siswa LIMIT 1";
$resultTmp = mysqli_query($conek, $queryTmp);
if ($resultTmp && mysqli_num_rows($resultTmp) > 0) {
    $rowTmp = mysqli_fetch_assoc($resultTmp);
    $hasil['nokartu'] = $rowTmp['nokartu'];
} else {
    $hasil['nokartu'] = '';
}

// ===== PROSES SIMPAN DATA =====
if (isset($_POST['btnSimpan'])) {
    // Sanitasi input
    $nokartu       = trim(mysqli_real_escape_string($conek, $_POST['nokartu'] ?? ''));
    $nis           = trim(mysqli_real_escape_string($conek, $_POST['nis'] ?? ''));
    $nama_siswa    = trim(mysqli_real_escape_string($conek, $_POST['nama_siswa'] ?? ''));
    $jenis_kelamin = trim(mysqli_real_escape_string($conek, $_POST['jenis_kelamin'] ?? ''));
    
    // TTL
    $tempat_lahir = trim(mysqli_real_escape_string($conek, $_POST['tempat_lahir'] ?? ''));
    $tanggal      = trim(mysqli_real_escape_string($conek, $_POST['tanggal'] ?? ''));
    $bulan        = trim(mysqli_real_escape_string($conek, $_POST['bulan'] ?? ''));
    $tahun        = trim(mysqli_real_escape_string($conek, $_POST['tahun'] ?? ''));
    
    // Format TTL: Tempat, Tanggal Bulan Tahun
    $ttl = $tempat_lahir . ', ' . $tanggal . ' ' . $bulan . ' ' . $tahun;
    
    // Batasi panjang TTL maksimal 50 karakter
    if (strlen($ttl) > 40) {
        $ttl = substr($ttl, 0, 40);
    }
    
    $kelas     = trim(mysqli_real_escape_string($conek, $_POST['kelas'] ?? ''));
    $jurusan   = trim(mysqli_real_escape_string($conek, $_POST['jurusan'] ?? ''));
    $nama_wali = trim(mysqli_real_escape_string($conek, $_POST['nama_wali'] ?? ''));
    $no_hp     = trim(mysqli_real_escape_string($conek, $_POST['no_hp'] ?? ''));
    $alamat    = trim(mysqli_real_escape_string($conek, $_POST['alamat'] ?? ''));

    // ===== VALIDASI =====
    $error = false;
    $error_msg = '';

    if (empty($nokartu)) {
        $error = true;
        $error_msg = 'Kartu RFID belum terdeteksi! Silakan tempelkan kartu.';
    } elseif (empty($nis)) {
        $error = true;
        $error_msg = 'NIS wajib diisi!';
    } elseif (empty($nama_siswa)) {
        $error = true;
        $error_msg = 'Nama siswa wajib diisi!';
    } elseif (empty($tempat_lahir) || empty($tanggal) || empty($bulan) || empty($tahun)) {
        $error = true;
        $error_msg = 'Tempat dan tanggal lahir harus diisi lengkap!';
    } elseif (empty($kelas)) {
        $error = true;
        $error_msg = 'Kelas wajib diisi!';
    } elseif (empty($alamat)) {
        $error = true;
        $error_msg = 'Alamat wajib diisi!';
    } elseif (strlen($ttl) > 40) {
        $error = true;
        $error_msg = 'Tempat Tanggal Lahir terlalu panjang! Maksimal 50 karakter.';
    } elseif (strlen($alamat) > 100) {
        $error = true;
        $error_msg = 'Alamat terlalu panjang! Maksimal 255 karakter.';
    } elseif (strlen($nama_wali) > 100) {
        $error = true;
        $error_msg = 'Nama Wali terlalu panjang! Maksimal 100 karakter.';
    }

    // ===== SIMPAN DATA =====
    if (!$error) {
        // Cek apakah NIS sudah ada
        $checkQuery = "SELECT id FROM siswa WHERE nis = ?";
        $checkStmt = $conek->prepare($checkQuery);
        if ($checkStmt) {
            $checkStmt->bind_param("s", $nis);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->num_rows > 0) {
                $error = true;
                $error_msg = 'NIS sudah terdaftar!';
            }
            $checkStmt->close();
        }

        // Cek apakah nokartu sudah ada
        if (!$error) {
            $checkQuery2 = "SELECT id FROM siswa WHERE nokartu = ?";
            $checkStmt2 = $conek->prepare($checkQuery2);
            if ($checkStmt2) {
                $checkStmt2->bind_param("s", $nokartu);
                $checkStmt2->execute();
                $checkResult2 = $checkStmt2->get_result();
                if ($checkResult2->num_rows > 0) {
                    $error = true;
                    $error_msg = 'Kartu RFID sudah terdaftar!';
                }
                $checkStmt2->close();
            }
        }
    }

    // Jika tidak ada error, simpan data
    if (!$error) {
        $query = "INSERT INTO siswa (nokartu, nis, nama_siswa, jenis_kelamin, ttl, kelas, jurusan, nama_wali, no_hp, alamat) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conek->prepare($query);
        if ($stmt) {
            $stmt->bind_param("ssssssssss", 
                $nokartu, $nis, $nama_siswa, $jenis_kelamin, 
                $ttl, $kelas, $jurusan, $nama_wali, 
                $no_hp, $alamat
            );
            
            if ($stmt->execute()) {
                // Hapus data dari tmprfid_siswa setelah berhasil disimpan
                $deleteQuery = "DELETE FROM tmprfid_siswa WHERE nokartu = ?";
                $deleteStmt = $conek->prepare($deleteQuery);
                if ($deleteStmt) {
                    $deleteStmt->bind_param("s", $nokartu);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }
                
                $status = 'berhasil';
                $pesan = 'Data siswa berhasil disimpan!';
                
                // ===== LANGSUNG REDIRECT TANPA DELAY =====
                // Tidak ada echo script dengan setTimeout, langsung redirect via header
                // Tapi karena sudah ada output, kita gunakan JavaScript langsung
                
            } else {
                $status = 'gagal';
                $pesan = 'Gagal menyimpan data: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $status = 'gagal';
            $pesan = 'Gagal mempersiapkan query: ' . $conek->error;
        }
    } else {
        $status = 'gagal';
        $pesan = $error_msg;
    }
}

// Hapus tmprfid hanya jika bukan dari proses simpan
if (!isset($_POST['btnSimpan']) && $status != 'berhasil') {
    mysqli_query($conek, "DELETE FROM tmprfid_siswa");
}

// ===== REDIRECT LANGSUNG TANPA DELAY JIKA BERHASIL =====
if ($status == 'berhasil') {
    // Redirect langsung ke data_siswa.php
    echo "<script>
        window.location.href = 'data_siswa.php';
    </script>";
    exit(); // Hentikan eksekusi script
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "../header.php"; ?>
    <title>Tambah Data Siswa</title>

    <style>
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

        /* ===== FORM WRAPPER ===== */
        .form-wrapper {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            max-width: 650px;
            margin: 0 auto 2rem auto;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.08);
            position: relative;
            z-index: 1;
        }

        .form-wrapper h3 {
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-wrapper h3 i {
            color: #4831d4;
        }

        /* ===== FORM ELEMENTS ===== */
        .form-group {
            margin-bottom: 0.8rem;
        }

        .form-group label {
            font-weight: 600;
            color: #555577;
            font-size: 0.7rem;
            margin-bottom: 0.25rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group .form-control {
            width: 100%;
            padding: 0.45rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(72, 49, 212, 0.12);
            background: rgba(255, 255, 255, 0.7);
            color: #1a1a2e;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
        }

        .form-group .form-control:focus {
            outline: none;
            border-color: #6c63ff;
            background: #ffffff;
            box-shadow: 0 0 20px rgba(72, 49, 212, 0.08);
        }

        .form-group .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .form-group textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }

        .form-group select.form-control {
            appearance: auto;
        }

        .text-muted {
            color: #8888aa;
            font-size: 0.6rem;
            margin-top: 0.2rem;
            display: block;
        }

        .text-muted .highlight {
            color: #dc3545;
            font-weight: 600;
        }

        /* ===== TTL GROUP ===== */
        .ttl-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .ttl-group .form-group {
            flex: 1;
            min-width: 60px;
            margin-bottom: 0;
        }

        .ttl-group .form-group.tempat {
            flex: 2;
            min-width: 100px;
        }

        /* ===== DISPLAY KARTU ===== */
        #nokartu {
            background: rgba(233, 236, 239, 0.5);
            padding: 0.6rem 1rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            color: #28a745;
            border: 2px dashed #6c63ff;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        #nokartu.active {
            background: rgba(212, 237, 218, 0.5);
            border-color: #28a745;
        }

        #nokartu .empty-text {
            color: #dc3545;
            font-weight: 400;
            font-size: 0.8rem;
        }

        /* ===== ALERT ===== */
        .alert-error {
            background: rgba(248, 215, 218, 0.8);
            color: #721c24;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(245, 198, 203, 0.5);
            margin-bottom: 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error .icon {
            font-size: 1rem;
        }

        .alert-success {
            background: rgba(212, 237, 218, 0.8);
            color: #155724;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(195, 230, 203, 0.5);
            margin-bottom: 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ===== BUTTONS ===== */
        .btn-group-form {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.5rem;
        }

        .btn-group-form .btn {
            flex: 1;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .btn-group-form .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #fff;
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 25px rgba(72, 49, 212, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #ef4444);
            color: #fff;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.25);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #34d399);
            color: #fff;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.25);
        }

        .btn-success:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.4);
        }

        /* ===== PERBAIKAN: Style card reader SEDERHANA & OTOMATIS ===== */
        .card-reader-box {
            background: rgba(233, 236, 239, 0.5);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 2px solid #6c63ff;
            transition: all 0.3s ease;
            position: relative;
        }

        .card-reader-box.scanning {
            border-color: #28a745;
            background: rgba(212, 237, 218, 0.3);
            animation: pulseBorder 1.5s ease-in-out infinite;
        }

        @keyframes pulseBorder {
            0% { border-color: #6c63ff; }
            50% { border-color: #28a745; }
            100% { border-color: #6c63ff; }
        }

        .card-reader-box .card-number {
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a2e;
            min-height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.3rem;
        }

        .card-reader-box .card-number .label {
            font-size: 0.6rem;
            font-weight: 400;
            color: #8888aa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-reader-box .card-number .value {
            font-size: 0.9rem;
            background: rgba(72, 49, 212, 0.08);
            padding: 0.2rem 1rem;
            border-radius: 6px;
            color: #4831d4;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .card-reader-box .card-number .value.changed {
            animation: cardChanged 0.6s ease;
        }

        @keyframes cardChanged {
            0% { transform: scale(1); background: rgba(40, 167, 69, 0.2); }
            50% { transform: scale(1.1); background: rgba(40, 167, 69, 0.4); }
            100% { transform: scale(1); background: rgba(72, 49, 212, 0.08); }
        }

        .card-reader-box .card-number .empty-text {
            color: #dc3545;
            font-weight: 400;
            font-size: 0.8rem;
        }

        .card-reader-box .card-status {
            text-align: center;
            font-size: 0.6rem;
            color: #8888aa;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .card-reader-box .card-status .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #28a745;
            animation: blink 1s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }

        /* ===== POPUP STYLE ===== */
        #popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        #popup.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        #popup .content {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem 2.5rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        #popup .popup-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        #popup .popup-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 0.5rem;
        }

        #popup .popup-text {
            font-size: 0.9rem;
            color: #555577;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        #popup .popup-buttons {
            display: flex;
            gap: 0.6rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        #popup .popup-buttons .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 100px;
        }

        #popup .popup-buttons .btn:hover {
            transform: translateY(-2px);
        }

        .btn-popup-primary {
            background: #4831d4;
            color: #fff;
        }

        .btn-popup-success {
            background: #059669;
            color: #fff;
        }

        .btn-popup-danger {
            background: #dc3545;
            color: #fff;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header-modern {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            .form-wrapper {
                padding: 1rem;
                margin: 0 0.8rem 1rem 0.8rem;
            }

            .ttl-group {
                flex-direction: column;
            }

            .ttl-group .form-group {
                min-width: unset;
            }

            .btn-group-form {
                flex-direction: column;
            }

            #popup .content {
                padding: 1.5rem;
                width: 95%;
            }
        }

        @media (max-width: 600px) {
            .header-text h1 {
                font-size: 1rem;
            }

            .form-wrapper h3 {
                font-size: 0.95rem;
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

    <script>
    let lastUID = "";
    let scanInterval = null;
    let isProcessing = false;
    let cardDetected = false;

    function startScanning() {
        if (scanInterval) {
            clearInterval(scanInterval);
        }
        
        scanInterval = setInterval(function(){
            if (isProcessing) return;
            isProcessing = true;
            
            fetch('tampil_kartu.php?_=' + new Date().getTime(), {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                },
                credentials: 'same-origin'
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP error! status: ' + res.status);
                }
                return res.text();
            })
            .then(data => {
                let cardData = data.trim();
                let display = document.getElementById("cardNumber");
                let input = document.getElementById("nokartuInput");
                let box = document.getElementById("cardBox");
                
                console.log("Kartu terdeteksi:", cardData || "(kosong)");
                
                if (cardData !== "" && cardData.length > 0) {
                    if (cardData !== lastUID) {
                        display.innerHTML = `
                            <div class="label">✅ KARTU TERBACA</div>
                            <div class="value changed">${cardData}</div>
                            <div style="font-size:0.55rem;color:#28a745;margin-top:0.2rem;">
                                🎯 Kartu berhasil dideteksi! Klik Simpan untuk menyimpan.
                            </div>
                        `;
                        box.classList.add('scanning');
                        input.value = cardData;
                        lastUID = cardData;
                        cardDetected = true;
                        
                        box.style.borderColor = '#28a745';
                        setTimeout(() => {
                            box.style.borderColor = '#6c63ff';
                        }, 2000);
                        
                        setTimeout(() => {
                            let val = document.querySelector('.value');
                            if(val) val.classList.remove('changed');
                        }, 700);
                    }
                } else {
                    if (cardDetected) {
                        let existingCard = document.getElementById('nokartuInput').value;
                        if (existingCard && existingCard.length > 0) {
                            display.innerHTML = `
                                <div class="label">📋 KARTU TERSIMPAN</div>
                                <div class="value">${existingCard}</div>
                                <div style="font-size:0.55rem;color:#6c63ff;margin-top:0.2rem;">
                                    💡 Tempelkan kartu baru untuk mengganti
                                </div>
                            `;
                        } else {
                            display.innerHTML = `
                                <span class="empty-text">⏳ Tempelkan Kartu RFID...</span>
                            `;
                            box.classList.remove('scanning');
                        }
                        cardDetected = false;
                    }
                }
                
                isProcessing = false;
            })
            .catch(error => {
                console.error('Error:', error);
                isProcessing = false;
            });
        }, 500);
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('RFID Reader siap...');
        
        let existingCard = document.getElementById('nokartuInput').value;
        let display = document.getElementById("cardNumber");
        let box = document.getElementById("cardBox");
        
        if (existingCard && existingCard.length > 0) {
            lastUID = existingCard;
            display.innerHTML = `
                <div class="label">📋 KARTU TERSIMPAN</div>
                <div class="value">${existingCard}</div>
                <div style="font-size:0.55rem;color:#6c63ff;margin-top:0.2rem;">
                    💡 Tempelkan kartu baru untuk mengganti
                </div>
            `;
            box.classList.add('scanning');
        } else {
            display.innerHTML = `
                <span class="empty-text">⏳ Tempelkan Kartu RFID...</span>
            `;
        }
        
        startScanning();
    });

    window.addEventListener('beforeunload', function() {
        if (scanInterval) {
            clearInterval(scanInterval);
        }
    });

    // ===== FUNGSI POPUP =====
    function showPopup(icon, title, text, buttons) {
        let popup = document.getElementById('popup');
        let popupIcon = document.getElementById('popupIcon');
        let popupTitle = document.getElementById('popupTitle');
        let popupText = document.getElementById('popupText');
        let popupButtons = document.getElementById('popupButtons');
        
        popupIcon.textContent = icon;
        popupTitle.textContent = title;
        popupText.textContent = text;
        popupButtons.innerHTML = '';
        
        buttons.forEach(btn => {
            let button = document.createElement('button');
            button.className = 'btn ' + btn.class;
            button.textContent = btn.label;
            button.onclick = btn.action;
            popupButtons.appendChild(button);
        });
        
        popup.classList.add('show');
    }

    function closePopup() {
        document.getElementById('popup').classList.remove('show');
    }

    // ===== VALIDASI FORM =====
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        let nokartu = document.getElementById('nokartuInput').value;
        if (nokartu === '') {
            e.preventDefault();
            showPopup('⚠️', 'Validasi Gagal', 'Silakan tempelkan kartu RFID terlebih dahulu!', [
                {
                    label: 'OK',
                    class: 'btn-popup-primary',
                    action: closePopup
                }
            ]);
            return false;
        }
        
        let nis = document.getElementById('nis').value;
        if (nis === '') {
            e.preventDefault();
            showPopup('⚠️', 'Validasi Gagal', 'NIS tidak boleh kosong!', [
                {
                    label: 'OK',
                    class: 'btn-popup-primary',
                    action: closePopup
                }
            ]);
            return false;
        }
        
        let nama = document.getElementById('nama_siswa').value;
        if (nama === '') {
            e.preventDefault();
            showPopup('⚠️', 'Validasi Gagal', 'Nama siswa tidak boleh kosong!', [
                {
                    label: 'OK',
                    class: 'btn-popup-primary',
                    action: closePopup
                }
            ]);
            return false;
        }
        
        let tempat = document.querySelector('input[name="tempat_lahir"]').value;
        let tanggal = document.querySelector('input[name="tanggal"]').value;
        let bulan = document.querySelector('select[name="bulan"]').value;
        let tahun = document.querySelector('input[name="tahun"]').value;
        
        if (tempat === '' || tanggal === '' || bulan === '' || tahun === '') {
            e.preventDefault();
            showPopup('⚠️', 'Validasi Gagal', 'Tempat, Tanggal, Bulan, dan Tahun Lahir harus diisi!', [
                {
                    label: 'OK',
                    class: 'btn-popup-primary',
                    action: closePopup
                }
            ]);
            return false;
        }
        
        return true;
    });

    <?php if(isset($status) && $status != ''): ?>
        <?php if($status == 'berhasil'): ?>
            // Tidak menampilkan popup karena langsung redirect
            // window.location.href = 'data_siswa.php';
        <?php else: ?>
            document.addEventListener('DOMContentLoaded', function() {
                showPopup('❌', 'Gagal!', '<?= addslashes($pesan) ?>', [
                    {
                        label: 'Coba Lagi',
                        class: 'btn-popup-primary',
                        action: closePopup
                    },
                    {
                        label: 'Ke Data Siswa',
                        class: 'btn-popup-danger',
                        action: function() {
                            window.location.href = 'data_siswa.php';
                        }
                    }
                ]);
            });
        <?php endif; ?>
    <?php endif; ?>
    </script>
</head>

<body>
<?php include "menu.php"; ?>

<div class="container-fluid">

    <!-- ===== HEADER ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">🧑‍🎓</div>
            <div class="header-text">
                <h1>Tambah Data Siswa</h1>
                <div class="sub">Tambahkan data siswa baru ke dalam sistem</div>
            </div>
        </div>
    </div>

    <!-- ===== FORM ===== -->
    <div class="form-wrapper">
        <h3><i class="fas fa-user-plus"></i> Form Tambah Siswa</h3>

        <?php if(isset($status) && $status == 'gagal' && !empty($pesan)): ?>
            <div class="alert-error">
                <span class="icon">⚠️</span> <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formTambah">
            <!-- Display Kartu RFID -->
            <div class="card-reader-box" id="cardBox">
                <div class="card-number" id="cardNumber">
                    <?php if (!empty($hasil['nokartu'])): ?>
                        <div class="label">KARTU SAAT INI</div>
                        <div class="value"><?= htmlspecialchars($hasil['nokartu']); ?></div>
                        <div style="font-size:0.55rem;color:#8888aa;margin-top:0.2rem;">
                            Tempelkan kartu baru untuk mengganti secara otomatis
                        </div>
                    <?php else: ?>
                        <span class="empty-text">⏳ Tempelkan Kartu RFID...</span>
                    <?php endif; ?>
                </div>
                <div class="card-status">
                    <span class="dot"></span>
                    <span>Pembaca aktif - Tempel kartu untuk mengganti otomatis</span>
                </div>
            </div>
            <input type="hidden" id="nokartuInput" name="nokartu" value="<?= htmlspecialchars($hasil['nokartu'] ?? ''); ?>">

            <div class="form-group">
                <label>Nomer Induk Siswa (NIS)</label>
                <input type="number" name="nis" id="nis" placeholder="Nomer Induk Siswa" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Nama Siswa</label>
                <input type="text" name="nama_siswa" id="nama_siswa" placeholder="Nama Siswa" class="form-control" required maxlength="100">
            </div>

            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin" id="jenis_kelamin" class="form-control" required>
                    <option value="" selected disabled>Pilih Jenis Kelamin...</option>
                    <option value="Laki-Laki">Laki-Laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tempat, Tanggal Lahir</label>
                <div class="ttl-group">
                    <div class="form-group tempat">
                        <input type="text" name="tempat_lahir" class="form-control" placeholder="Tempat Lahir" required maxlength="30">
                    </div>
                    <div class="form-group">
                        <input type="number" name="tanggal" class="form-control" placeholder="Tanggal" min="1" max="31" required>
                    </div>
                    <div class="form-group">
                        <select name="bulan" class="form-control" required>
                            <option value="">Bulan</option>
                            <option value="Januari">Januari</option>
                            <option value="Februari">Februari</option>
                            <option value="Maret">Maret</option>
                            <option value="April">April</option>
                            <option value="Mei">Mei</option>
                            <option value="Juni">Juni</option>
                            <option value="Juli">Juli</option>
                            <option value="Agustus">Agustus</option>
                            <option value="September">September</option>
                            <option value="Oktober">Oktober</option>
                            <option value="November">November</option>
                            <option value="Desember">Desember</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" name="tahun" class="form-control" placeholder="Tahun" min="1900" max="<?= date('Y'); ?>" required>
                    </div>
                </div>
                <small class="text-muted">
                    ⚠️ Maksimal <span class="highlight">40 karakter</span> termasuk tanda koma dan spasi.
                </small>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <input type="text" name="kelas" id="kelas" placeholder="Kelas" class="form-control" required maxlength="20">
            </div>

            <div class="form-group">
                <label>Jurusan</label>
                <input type="text" name="jurusan" id="jurusan" placeholder="Jurusan" class="form-control" required maxlength="50">
            </div>

            <div class="form-group">
                <label>Nama Wali</label>
                <input type="text" name="nama_wali" id="nama_wali" placeholder="Nama Wali" class="form-control" required maxlength="100">
            </div>

            <div class="form-group">
                <label>No. HP</label>
                <input type="text" name="no_hp" id="no_hp" placeholder="No. HP" class="form-control" required maxlength="15">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea class="form-control" name="alamat" id="alamat" placeholder="Alamat" required maxlength="255"></textarea>
                <small class="text-muted">Maksimal 255 karakter</small>
            </div>

            <div class="btn-group-form">
                <button class="btn btn-primary" name="btnSimpan" id="btnSimpan">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="data_siswa.php" class="btn btn-success">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>

</div>

<!-- ===== POPUP ===== -->
<div id="popup">
    <div class="content">
        <span class="popup-icon" id="popupIcon">❓</span>
        <div class="popup-title" id="popupTitle">Konfirmasi</div>
        <div class="popup-text" id="popupText">Apakah Anda yakin?</div>
        <div class="popup-buttons" id="popupButtons">
            <!-- Buttons akan di-generate oleh JavaScript -->
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>

<?php
// ===== AKHIR: Flush output =====
ob_end_flush();
?>
</body>
</html>