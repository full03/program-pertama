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

// ===== AMBIL DATA SISWA =====
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$hasil = null;

if ($id > 0) {
    $query = "SELECT * FROM siswa WHERE id = ?";
    $stmt = $conek->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasil = $result->fetch_assoc();
        $stmt->close();
    }
}

if (!$hasil) {
    header("Location: data_siswa.php?error=Data tidak ditemukan");
    exit();
}

// ===== PARSE TTL =====
$ttl_parts = [];
if (!empty($hasil['ttl'])) {
    if (preg_match('/^(.+?),\s*(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})$/', $hasil['ttl'], $matches)) {
        $ttl_parts['tempat'] = $matches[1];
        $ttl_parts['tanggal'] = $matches[2];
        $ttl_parts['bulan'] = $matches[3];
        $ttl_parts['tahun'] = $matches[4];
    }
}

// ===== PROSES UPDATE =====
$error = false;
$error_msg = '';

if (isset($_POST['btnSimpan'])) {
    // ===== PERBAIKAN: Ambil dan batasi panjang nokartu =====
    $nokartu = isset($_POST['nokartu']) ? trim($_POST['nokartu']) : '';
    
    // Batasi panjang nokartu (sesuaikan dengan panjang kolom di database)
    $max_nokartu_length = 20; // Ganti dengan panjang kolom nokartu di database Anda
    if (strlen($nokartu) > $max_nokartu_length) {
        $nokartu = substr($nokartu, 0, $max_nokartu_length);
    }
    
    // Sanitasi input lainnya
    $nis           = trim(mysqli_real_escape_string($conek, $_POST['nis'] ?? ''));
    $nama_siswa    = trim(mysqli_real_escape_string($conek, $_POST['nama_siswa'] ?? ''));
    $jenis_kelamin = trim(mysqli_real_escape_string($conek, $_POST['jenis_kelamin'] ?? ''));
    
    // TTL
    $tempat_lahir = trim(mysqli_real_escape_string($conek, $_POST['tempat_lahir'] ?? ''));
    $tanggal      = trim(mysqli_real_escape_string($conek, $_POST['tanggal'] ?? ''));
    $bulan        = trim(mysqli_real_escape_string($conek, $_POST['bulan'] ?? ''));
    $tahun        = trim(mysqli_real_escape_string($conek, $_POST['tahun'] ?? ''));
    
    // Format TTL
    $ttl = $tempat_lahir . ', ' . $tanggal . ' ' . $bulan . ' ' . $tahun;
    if (strlen($ttl) > 50) {
        $ttl = substr($ttl, 0, 50);
    }
    
    $kelas     = trim(mysqli_real_escape_string($conek, $_POST['kelas'] ?? ''));
    $jurusan   = trim(mysqli_real_escape_string($conek, $_POST['jurusan'] ?? ''));
    $nama_wali = trim(mysqli_real_escape_string($conek, $_POST['nama_wali'] ?? ''));
    $no_hp     = trim(mysqli_real_escape_string($conek, $_POST['no_hp'] ?? ''));
    $alamat    = trim(mysqli_real_escape_string($conek, $_POST['alamat'] ?? ''));

    // ===== VALIDASI =====
    if (empty($nis)) {
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
    } elseif (strlen($ttl) > 50) {
        $error = true;
        $error_msg = 'Tempat Tanggal Lahir terlalu panjang! Maksimal 50 karakter.';
    } elseif (strlen($alamat) > 255) {
        $error = true;
        $error_msg = 'Alamat terlalu panjang! Maksimal 255 karakter.';
    } elseif (strlen($nama_wali) > 100) {
        $error = true;
        $error_msg = 'Nama Wali terlalu panjang! Maksimal 100 karakter.';
    }

    // ===== SIMPAN DATA =====
    if (!$error) {
        // ===== PERBAIKAN: Cek apakah nokartu sudah digunakan oleh siswa lain =====
        if (!empty($nokartu)) {
            $checkQuery = "SELECT id FROM siswa WHERE nokartu = ? AND id != ?";
            $checkStmt = $conek->prepare($checkQuery);
            if ($checkStmt) {
                $checkStmt->bind_param("si", $nokartu, $id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                if ($checkResult->num_rows > 0) {
                    $error = true;
                    $error_msg = 'Kartu RFID sudah digunakan oleh siswa lain!';
                }
                $checkStmt->close();
            }
        }

        // Cek apakah NIS sudah digunakan oleh siswa lain
        if (!$error) {
            $checkQuery2 = "SELECT id FROM siswa WHERE nis = ? AND id != ?";
            $checkStmt2 = $conek->prepare($checkQuery2);
            if ($checkStmt2) {
                $checkStmt2->bind_param("si", $nis, $id);
                $checkStmt2->execute();
                $checkResult2 = $checkStmt2->get_result();
                if ($checkResult2->num_rows > 0) {
                    $error = true;
                    $error_msg = 'NIS sudah digunakan oleh siswa lain!';
                }
                $checkStmt2->close();
            }
        }
    }

    if (!$error) {
        $query = "UPDATE siswa SET 
            nokartu = ?,
            nis = ?, 
            nama_siswa = ?, 
            jenis_kelamin = ?,
            ttl = ?,
            kelas = ?,
            jurusan = ?, 
            nama_wali = ?,
            no_hp = ?, 
            alamat = ? 
            WHERE id = ?";

        $stmt = $conek->prepare($query);
        if ($stmt) {
            // ===== PERBAIKAN: Pastikan semua parameter sesuai =====
            $stmt->bind_param("ssssssssssi", 
                $nokartu, $nis, $nama_siswa, $jenis_kelamin, 
                $ttl, $kelas, $jurusan, $nama_wali, 
                $no_hp, $alamat, $id
            );
            
            try {
                if ($stmt->execute()) {
                    ob_end_clean();
                    header("Location: data_siswa.php?success=Data berhasil diperbarui");
                    exit();
                } else {
                    $error = true;
                    $error_msg = 'Gagal menyimpan data: ' . $stmt->error;
                }
            } catch (mysqli_sql_exception $e) {
                // ===== PERBAIKAN: Tangani error spesifik =====
                if (strpos($e->getMessage(), 'Data too long for column') !== false) {
                    $error = true;
                    $error_msg = 'Data terlalu panjang! Periksa kembali input Anda.';
                } else {
                    $error = true;
                    $error_msg = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
            $stmt->close();
        } else {
            $error = true;
            $error_msg = 'Gagal mempersiapkan query: ' . $conek->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "../header.php"; ?>
    <title>Edit Data Siswa</title>

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

        .form-group textarea.form-control {
            resize: vertical;
            min-height: 60px;
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

        .btn-kembali {
            background: rgba(108, 117, 125, 0.15);
            color: #555577;
        }

        .btn-kembali:hover {
            background: rgba(108, 117, 125, 0.25);
            color: #1a1a2e;
        }

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
        }

        @media (max-width: 600px) {
            .header-text h1 {
                font-size: 1rem;
            }

            .form-wrapper h3 {
                font-size: 0.95rem;
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
                
                // Debug di console (bisa dihapus nanti)
                console.log("Kartu terdeteksi:", cardData || "(kosong)");
                
                // Jika ada kartu yang terbaca
                if (cardData !== "" && cardData.length > 0) {
                    // Cek apakah kartu baru (berbeda dengan yang terakhir)
                    if (cardData !== lastUID) {
                        // Update tampilan
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
                        
                        // Efek visual
                        box.style.borderColor = '#28a745';
                        setTimeout(() => {
                            box.style.borderColor = '#6c63ff';
                        }, 2000);
                        
                        // Hapus animasi
                        setTimeout(() => {
                            let val = document.querySelector('.value');
                            if(val) val.classList.remove('changed');
                        }, 700);
                        
                        // Opsional: Beri notifikasi
                        // alert('Kartu terbaca: ' + cardData);
                    }
                } 
                else {
                    // Jika kartu sudah dibaca dan dihapus dari database
                    if (cardDetected) {
                        // Tampilkan status kartu tersimpan
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
        }, 500); // Cek setiap 0.5 detik
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log('RFID Reader siap...');
        
        // Tampilkan kartu yang sudah ada (jika ada)
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
        
        // Mulai scanning otomatis
        startScanning();
    });

    window.addEventListener('beforeunload', function() {
        if (scanInterval) {
            clearInterval(scanInterval);
        }
    });
    </script>
</head>

<body>
<?php include "menu.php"; ?>

<div class="container-fluid">

    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">✏️</div>
            <div class="header-text">
                <h1>Edit Data Siswa</h1>
                <div class="sub">Perbarui data siswa yang terdaftar di sistem</div>
            </div>
        </div>
    </div>

    <div class="form-wrapper">
        <h3><i class="fas fa-user-edit"></i> Form Edit Siswa</h3>

        <?php if ($error && !empty($error_msg)): ?>
            <div class="alert-error">
                <span class="icon">⚠️</span> <?= htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formEdit">
            <!-- ===== PERBAIKAN: Box pembaca kartu OTOMATIS ===== -->
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
                <input type="number" name="nis" class="form-control" value="<?= htmlspecialchars($hasil['nis'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Nama Siswa</label>
                <input type="text" name="nama_siswa" class="form-control" value="<?= htmlspecialchars($hasil['nama_siswa'] ?? ''); ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label>Jenis Kelamin</label>
                <select name="jenis_kelamin" class="form-control" required>
                    <option value="Laki-Laki" <?= ($hasil['jenis_kelamin'] ?? '') == 'Laki-Laki' ? 'selected' : ''; ?>>Laki-Laki</option>
                    <option value="Perempuan" <?= ($hasil['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tempat, Tanggal Lahir</label>
                <div class="ttl-group">
                    <div class="form-group tempat">
                        <input type="text" name="tempat_lahir" class="form-control" placeholder="Tempat Lahir" 
                               value="<?= htmlspecialchars($ttl_parts['tempat'] ?? ''); ?>" required maxlength="30">
                    </div>
                    <div class="form-group">
                        <input type="number" name="tanggal" class="form-control" placeholder="Tanggal" min="1" max="31"
                               value="<?= htmlspecialchars($ttl_parts['tanggal'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <select name="bulan" class="form-control" required>
                            <option value="">Bulan</option>
                            <option value="Januari" <?= ($ttl_parts['bulan'] ?? '') == 'Januari' ? 'selected' : ''; ?>>Januari</option>
                            <option value="Februari" <?= ($ttl_parts['bulan'] ?? '') == 'Februari' ? 'selected' : ''; ?>>Februari</option>
                            <option value="Maret" <?= ($ttl_parts['bulan'] ?? '') == 'Maret' ? 'selected' : ''; ?>>Maret</option>
                            <option value="April" <?= ($ttl_parts['bulan'] ?? '') == 'April' ? 'selected' : ''; ?>>April</option>
                            <option value="Mei" <?= ($ttl_parts['bulan'] ?? '') == 'Mei' ? 'selected' : ''; ?>>Mei</option>
                            <option value="Juni" <?= ($ttl_parts['bulan'] ?? '') == 'Juni' ? 'selected' : ''; ?>>Juni</option>
                            <option value="Juli" <?= ($ttl_parts['bulan'] ?? '') == 'Juli' ? 'selected' : ''; ?>>Juli</option>
                            <option value="Agustus" <?= ($ttl_parts['bulan'] ?? '') == 'Agustus' ? 'selected' : ''; ?>>Agustus</option>
                            <option value="September" <?= ($ttl_parts['bulan'] ?? '') == 'September' ? 'selected' : ''; ?>>September</option>
                            <option value="Oktober" <?= ($ttl_parts['bulan'] ?? '') == 'Oktober' ? 'selected' : ''; ?>>Oktober</option>
                            <option value="November" <?= ($ttl_parts['bulan'] ?? '') == 'November' ? 'selected' : ''; ?>>November</option>
                            <option value="Desember" <?= ($ttl_parts['bulan'] ?? '') == 'Desember' ? 'selected' : ''; ?>>Desember</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" name="tahun" class="form-control" placeholder="Tahun" min="1900" max="<?= date('Y'); ?>"
                               value="<?= htmlspecialchars($ttl_parts['tahun'] ?? ''); ?>" required>
                    </div>
                </div>
                <small class="text-muted">
                    ⚠️ Maksimal <span class="highlight">50 karakter</span> termasuk tanda koma dan spasi.
                </small>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($hasil['kelas'] ?? ''); ?>" required maxlength="20">
            </div>

            <div class="form-group">
                <label>Jurusan</label>
                <input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($hasil['jurusan'] ?? ''); ?>" required maxlength="50">
            </div>

            <div class="form-group">
                <label>Nama Wali</label>
                <input type="text" name="nama_wali" class="form-control" value="<?= htmlspecialchars($hasil['nama_wali'] ?? ''); ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label>No. HP</label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($hasil['no_hp'] ?? ''); ?>" required maxlength="15">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" class="form-control" required maxlength="255"><?= htmlspecialchars($hasil['alamat'] ?? ''); ?></textarea>
                <small class="text-muted">Maksimal 255 karakter</small>
            </div>

            <div class="btn-group-form">
                <button type="submit" name="btnSimpan" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="data_siswa.php" class="btn btn-kembali">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </form>
    </div>

</div>

<?php include "../footer.php"; ?>

<?php
ob_end_flush();
?>
</body>
</html>