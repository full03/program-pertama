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

// ===== PROSES SIMPAN =====
if (isset($_POST['save'])) {
    // Ambil data dari form
    $jam_masuk_dari = mysqli_real_escape_string($conek, $_POST['jam_masuk_dari']);
    $jam_masuk_sampai = mysqli_real_escape_string($conek, $_POST['jam_masuk_sampai']);
    $jam_masuk_nama = mysqli_real_escape_string($conek, $_POST['jam_masuk_nama']);
    
    $istirahat_dari = mysqli_real_escape_string($conek, $_POST['istirahat_dari']);
    $istirahat_sampai = mysqli_real_escape_string($conek, $_POST['istirahat_sampai']);
    $istirahat_nama = mysqli_real_escape_string($conek, $_POST['istirahat_nama']);
    
    $masuk_kedua_dari = mysqli_real_escape_string($conek, $_POST['masuk_kedua_dari']);
    $masuk_kedua_sampai = mysqli_real_escape_string($conek, $_POST['masuk_kedua_sampai']);
    $masuk_kedua_nama = mysqli_real_escape_string($conek, $_POST['masuk_kedua_nama']);
    
    $pulang_dari = mysqli_real_escape_string($conek, $_POST['pulang_dari']);
    $pulang_sampai = mysqli_real_escape_string($conek, $_POST['pulang_sampai']);
    $pulang_nama = mysqli_real_escape_string($conek, $_POST['pulang_nama']);
    
    $toleransi = intval($_POST['toleransi']);
    
    // Cek apakah sudah ada data
    $check_query = "SELECT id FROM setting_absensi LIMIT 1";
    $check_result = mysqli_query($conek, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update data yang sudah ada
        $query = "UPDATE setting_absensi SET 
                  jam_masuk_dari = '$jam_masuk_dari',
                  jam_masuk_sampai = '$jam_masuk_sampai',
                  jam_masuk_nama = '$jam_masuk_nama',
                  istirahat_dari = '$istirahat_dari',
                  istirahat_sampai = '$istirahat_sampai',
                  istirahat_nama = '$istirahat_nama',
                  masuk_kedua_dari = '$masuk_kedua_dari',
                  masuk_kedua_sampai = '$masuk_kedua_sampai',
                  masuk_kedua_nama = '$masuk_kedua_nama',
                  pulang_dari = '$pulang_dari',
                  pulang_sampai = '$pulang_sampai',
                  pulang_nama = '$pulang_nama',
                  toleransi = $toleransi
                  WHERE id = 1";
    } else {
        // Insert data baru
        $query = "INSERT INTO setting_absensi (
                  jam_masuk_dari, jam_masuk_sampai, jam_masuk_nama,
                  istirahat_dari, istirahat_sampai, istirahat_nama,
                  masuk_kedua_dari, masuk_kedua_sampai, masuk_kedua_nama,
                  pulang_dari, pulang_sampai, pulang_nama,
                  toleransi
                  ) VALUES (
                  '$jam_masuk_dari', '$jam_masuk_sampai', '$jam_masuk_nama',
                  '$istirahat_dari', '$istirahat_sampai', '$istirahat_nama',
                  '$masuk_kedua_dari', '$masuk_kedua_sampai', '$masuk_kedua_nama',
                  '$pulang_dari', '$pulang_sampai', '$pulang_nama',
                  $toleransi
                  )";
    }
    
    if (mysqli_query($conek, $query)) {
        $success = "Pengaturan berhasil disimpan!";
    } else {
        $error = "Error: " . mysqli_error($conek);
    }
}

// ===== AMBIL DATA SETTING =====
$query_setting = "SELECT * FROM setting_absensi LIMIT 1";
$result_setting = mysqli_query($conek, $query_setting);

if (mysqli_num_rows($result_setting) > 0) {
    $setting = mysqli_fetch_assoc($result_setting);
} else {
    // Data default jika belum ada
    $setting = [
        'jam_masuk_dari' => '07:00:00',
        'jam_masuk_sampai' => '08:00:00',
        'jam_masuk_nama' => 'Jam Masuk',
        'istirahat_dari' => '12:00:00',
        'istirahat_sampai' => '13:00:00',
        'istirahat_nama' => 'Jam Istirahat',
        'masuk_kedua_dari' => '13:00:00',
        'masuk_kedua_sampai' => '14:00:00',
        'masuk_kedua_nama' => 'Jam Masuk Kedua',
        'pulang_dari' => '15:00:00',
        'pulang_sampai' => '16:00:00',
        'pulang_nama' => 'Jam Pulang',
        'toleransi' => 30
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Jam Absensi</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f4f6f9;
            font-family: 'Poppins', sans-serif;
            padding: 30px;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        /* ===== CARD ===== */
        .card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            padding: 30px;
        }

        .card h2 {
            text-align: center;
            color: #4831d4;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* ===== ALERT ===== */
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group > label {
            display: block;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group .label-desc {
            font-size: 0.75rem;
            color: #666688;
            font-weight: 400;
            display: block;
            margin-top: 2px;
        }

        .time-group {
            background: #f8f9fc;
            border-radius: 10px;
            border: 1px solid #e8e6f0;
            padding: 15px 18px;
            transition: all 0.2s ease;
        }

        .time-group:hover {
            border-color: #4831d4;
            background: #f5f3ff;
        }

        .time-group .header-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .time-group .header-group .icon {
            font-size: 1.2rem;
            width: 36px;
            height: 36px;
            background: rgba(72, 49, 212, 0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .time-group .header-group .group-title {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 0.95rem;
        }

        .time-group .header-group .group-title input[type="text"] {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 4px 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            width: 180px;
            transition: all 0.2s ease;
            background: white;
        }

        .time-group .header-group .group-title input[type="text"]:focus {
            outline: none;
            border-color: #4831d4;
            box-shadow: 0 0 0 3px rgba(72, 49, 212, 0.1);
        }

        .time-input-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .time-input-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .time-input-item label {
            font-size: 0.8rem;
            color: #555577;
            font-weight: 500;
        }

        .time-input-item input[type="time"] {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            background: white;
            width: 120px;
        }

        .time-input-item input[type="time"]:focus {
            outline: none;
            border-color: #4831d4;
            box-shadow: 0 0 0 3px rgba(72, 49, 212, 0.1);
        }

        .time-input-item .separator {
            color: #999;
            font-weight: 300;
            font-size: 0.9rem;
        }

        /* ===== TOLERANSI ===== */
        .toleransi-group {
            background: #f8f9fc;
            border-radius: 10px;
            border: 1px solid #e8e6f0;
            padding: 15px 18px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .toleransi-group .label-icon {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toleransi-group .label-icon .icon {
            font-size: 1.2rem;
            width: 36px;
            height: 36px;
            background: rgba(6, 182, 212, 0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toleransi-group .label-icon span {
            font-weight: 600;
            color: #1a1a2e;
        }

        .toleransi-group input[type="number"] {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            background: white;
            width: 80px;
            text-align: center;
        }

        .toleransi-group input[type="number"]:focus {
            outline: none;
            border-color: #06b6d4;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        .toleransi-group .unit {
            font-size: 0.8rem;
            color: #666688;
            font-weight: 500;
        }

        /* ===== BUTTON ===== */
        .btn-save {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            border: none;
            color: white;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(72, 49, 212, 0.4);
        }

        .btn-save:active {
            transform: scale(0.97);
        }

        /* ===== TOMBOL KEMBALI ===== */
        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0,0,0,0.6);
            padding: 12px 15px;
            border-radius: 50%;
            color: white;
            font-size: 17px;
            text-decoration: none;
            backdrop-filter: blur(5px);
            transition: 0.3s;
            z-index: 999;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-back:hover {
            transform: scale(1.1);
            box-shadow: 0 0 18px rgba(80,80,255,0.9);
        }

        /* ===== INFO BOX ===== */
        .info-box {
            background: #eef2ff;
            border-radius: 10px;
            padding: 15px 18px;
            margin-top: 20px;
            border-left: 4px solid #4831d4;
        }

        .info-box h4 {
            color: #1a1a2e;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .info-box p {
            color: #555577;
            font-size: 0.75rem;
            margin: 0;
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }

            .time-group {
                padding: 12px 15px;
            }

            .time-input-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .time-input-item {
                justify-content: space-between;
            }

            .time-input-item input[type="time"] {
                width: 100%;
                max-width: 150px;
            }

            .time-group .header-group {
                flex-wrap: wrap;
            }

            .time-group .header-group .group-title input[type="text"] {
                width: 100%;
                max-width: 200px;
            }

            .toleransi-group {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .toleransi-group .label-icon {
                width: 100%;
            }

            .toleransi-group .time-input-item {
                justify-content: flex-start;
            }

            .btn-back {
                top: 10px;
                left: 10px;
                padding: 10px 12px;
                font-size: 14px;
            }

            .card h2 {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            .time-input-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
                width: 100%;
            }

            .time-input-item input[type="time"] {
                max-width: 100%;
                width: 100%;
            }

            .time-group .header-group .group-title input[type="text"] {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Tombol Kembali -->
    <button class="btn-back" onclick="goBackSafe()">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="container">
        <div class="card">
            <h2>
                <i class="fas fa-clock" style="color:#4831d4;"></i>
                Pengaturan Jam Absensi
            </h2>

            <!-- Alert -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- FORM -->
            <form method="POST">
                <!-- Jam Masuk -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-sign-in-alt" style="color:#4831d4;"></i>
                        Sesi 1: Jam Masuk
                        <span class="label-desc">Atur rentang waktu untuk absen masuk</span>
                    </label>
                    <div class="time-group">
                        <div class="header-group">
                            <span class="icon">🕐</span>
                            <div class="group-title">
                                <input type="text" 
                                       name="jam_masuk_nama" 
                                       value="<?= htmlspecialchars($setting['jam_masuk_nama']) ?>" 
                                       placeholder="Nama Sesi">
                            </div>
                        </div>
                        <div class="time-input-row">
                            <div class="time-input-item">
                                <label>Dari</label>
                                <input type="time" 
                                       name="jam_masuk_dari" 
                                       value="<?= $setting['jam_masuk_dari'] ?>" 
                                       required>
                            </div>
                            <div class="time-input-item">
                                <label>Sampai</label>
                                <input type="time" 
                                       name="jam_masuk_sampai" 
                                       value="<?= $setting['jam_masuk_sampai'] ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jam Istirahat -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-coffee" style="color:#f59e0b;"></i>
                        Sesi 2: Jam Istirahat
                        <span class="label-desc">Atur rentang waktu untuk absen istirahat</span>
                    </label>
                    <div class="time-group">
                        <div class="header-group">
                            <span class="icon">☕</span>
                            <div class="group-title">
                                <input type="text" 
                                       name="istirahat_nama" 
                                       value="<?= htmlspecialchars($setting['istirahat_nama']) ?>" 
                                       placeholder="Nama Sesi">
                            </div>
                        </div>
                        <div class="time-input-row">
                            <div class="time-input-item">
                                <label>Dari</label>
                                <input type="time" 
                                       name="istirahat_dari" 
                                       value="<?= $setting['istirahat_dari'] ?>" 
                                       required>
                            </div>
                            <div class="time-input-item">
                                <label>Sampai</label>
                                <input type="time" 
                                       name="istirahat_sampai" 
                                       value="<?= $setting['istirahat_sampai'] ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jam Masuk Kedua -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-undo-alt" style="color:#8b5cf6;"></i>
                        Sesi 3: Jam Masuk Kedua
                        <span class="label-desc">Atur rentang waktu untuk absen masuk setelah istirahat</span>
                    </label>
                    <div class="time-group">
                        <div class="header-group">
                            <span class="icon">🔄</span>
                            <div class="group-title">
                                <input type="text" 
                                       name="masuk_kedua_nama" 
                                       value="<?= htmlspecialchars($setting['masuk_kedua_nama']) ?>" 
                                       placeholder="Nama Sesi">
                            </div>
                        </div>
                        <div class="time-input-row">
                            <div class="time-input-item">
                                <label>Dari</label>
                                <input type="time" 
                                       name="masuk_kedua_dari" 
                                       value="<?= $setting['masuk_kedua_dari'] ?>" 
                                       required>
                            </div>
                            <div class="time-input-item">
                                <label>Sampai</label>
                                <input type="time" 
                                       name="masuk_kedua_sampai" 
                                       value="<?= $setting['masuk_kedua_sampai'] ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jam Pulang -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-sign-out-alt" style="color:#ef4444;"></i>
                        Sesi 4: Jam Pulang
                        <span class="label-desc">Atur rentang waktu untuk absen pulang</span>
                    </label>
                    <div class="time-group">
                        <div class="header-group">
                            <span class="icon">🏠</span>
                            <div class="group-title">
                                <input type="text" 
                                       name="pulang_nama" 
                                       value="<?= htmlspecialchars($setting['pulang_nama']) ?>" 
                                       placeholder="Nama Sesi">
                            </div>
                        </div>
                        <div class="time-input-row">
                            <div class="time-input-item">
                                <label>Dari</label>
                                <input type="time" 
                                       name="pulang_dari" 
                                       value="<?= $setting['pulang_dari'] ?>" 
                                       required>
                            </div>
                            <div class="time-input-item">
                                <label>Sampai</label>
                                <input type="time" 
                                       name="pulang_sampai" 
                                       value="<?= $setting['pulang_sampai'] ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toleransi -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-hourglass-half" style="color:#06b6d4;"></i>
                        Toleransi Keterlambatan
                        <span class="label-desc">Menit toleransi sebelum siswa dianggap Alfa</span>
                    </label>
                    <div class="toleransi-group">
                        <div class="label-icon">
                            <span class="icon">⏱️</span>
                            <span>Toleransi</span>
                        </div>
                        <div class="time-input-item">
                            <input type="number" 
                                   name="toleransi" 
                                   value="<?= $setting['toleransi'] ?>" 
                                   min="0"
                                   max="120"
                                   required>
                            <span class="unit">menit</span>
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="info-box">
                    <h4>📌 Informasi</h4>
                    <p>
                        <strong>Rentang Waktu:</strong> Setiap sesi memiliki rentang waktu "Dari" sampai "Sampai".<br>
                        <strong>Nama Sesi:</strong> Anda bisa mengubah nama setiap sesi absensi sesuai kebutuhan.<br>
                        <strong>Toleransi:</strong> Jika siswa tidak absen sampai batas waktu toleransi, akan otomatis menjadi <strong>Alfa</strong>.<br>
                        <strong>Auto Absen:</strong> Fitur ini akan menandai siswa yang belum absen sebagai Alfa setelah waktu toleransi berlalu.
                    </p>
                </div>

                <button type="submit" name="save" class="btn-save">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <script>
        // Fungsi kembali ke halaman sebelumnya
        function goBackSafe() {
            if (document.referrer !== "") {
                window.location.href = "admin_dashboard.php";
            } else {
                window.location.href = "admin_dashboard.php";
            }
        }
    </script>

</body>
</html>