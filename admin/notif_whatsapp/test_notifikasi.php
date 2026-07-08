<?php
/**
 * File untuk testing notifikasi WhatsApp
 * Akses: test_notifikasi.php?nokartu=XXXXX
 * Mengambil nomor dari kolom no_hp di tabel siswa
 */

require_once '../../koneksi.php';
require_once 'kirim_notifikasi.php';

// Set header
header('Content-Type: text/html; charset=utf-8');

// Cek parameter
$nokartu = $_GET['nokartu'] ?? '';

if (empty($nokartu)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Notifikasi WhatsApp</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #333; }
            .form-group { margin-bottom: 15px; }
            label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
            input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
            button { background: #25D366; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; }
            button:hover { background: #128C7E; }
            .info { margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px; border-left: 4px solid #2196F3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🧪 Test Notifikasi WhatsApp</h1>
            <p>Masukkan Nokartu siswa untuk mengirim notifikasi test</p>
            <p><small>Nomor WhatsApp diambil dari kolom <strong>no_hp</strong> di database siswa</small></p>
            
            <form method="GET">
                <div class="form-group">
                    <label>Nokartu Siswa:</label>
                    <input type="text" name="nokartu" placeholder="Masukkan nokartu..." required>
                </div>
                <button type="submit">Kirim Notifikasi Test</button>
            </form>
            
            <div class="info">
                <strong>Info:</strong> Notifikasi akan dikirim ke nomor WhatsApp yang terdaftar di kolom <strong>no_hp</strong> pada database siswa.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Proses test
$nokartu = mysqli_real_escape_string($conek, $nokartu);

// Ambil data absensi terbaru
$query = "SELECT * FROM absensi_siswa WHERE nokartu = '$nokartu' ORDER BY tanggal DESC, id_absensi DESC LIMIT 1";
$result = mysqli_query($conek, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $data_absensi = mysqli_fetch_assoc($result);
    $resultNotif = kirimNotifikasiAbsensi($data_absensi);
    
    // Ambil data siswa untuk menampilkan no_hp
    $querySiswa = "SELECT * FROM siswa WHERE nokartu = '$nokartu'";
    $resultSiswa = mysqli_query($conek, $querySiswa);
    $siswa = mysqli_fetch_assoc($resultSiswa);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Hasil Test Notifikasi</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .result { padding: 15px; border-radius: 5px; margin-bottom: 15px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
            .pre-wrap { white-space: pre-wrap; background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow: auto; }
            .back { display: inline-block; margin-top: 15px; color: #007bff; text-decoration: none; }
            .back:hover { text-decoration: underline; }
            h1 { color: #333; }
            .info-box { background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107; margin-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📱 Hasil Test Notifikasi</h1>
            
            <div class="info-box">
                <strong>📋 Informasi Siswa:</strong><br>
                Nama: <?= htmlspecialchars($siswa['nama_siswa'] ?? '-') ?><br>
                Nokartu: <?= htmlspecialchars($nokartu) ?><br>
                Nomor WhatsApp (no_hp): <?= htmlspecialchars($siswa['no_hp'] ?? '-') ?>
            </div>
            
            <div class="result <?= $resultNotif['success'] ? 'success' : 'error' ?>">
                <strong><?= $resultNotif['success'] ? '✅ BERHASIL' : '❌ GAGAL' ?></strong>
                <p><?= $resultNotif['message'] ?></p>
            </div>
            
            <h3>Detail:</h3>
            <div class="pre-wrap">
                <?php 
                echo "Nokartu: {$data_absensi['nokartu']}\n";
                echo "Tanggal: {$data_absensi['tanggal']}\n";
                echo "Status: {$data_absensi['keterangan']}\n";
                echo "Nomor Tujuan: " . ($siswa['no_hp'] ?? '-') . "\n";
                echo "\nResponse: " . json_encode($resultNotif, JSON_PRETTY_PRINT);
                ?>
            </div>
            
            <a href="?nokartu=<?= $nokartu ?>" class="back">↻ Kirim Ulang</a>
            <a href="test_notifikasi.php" class="back" style="margin-left: 15px;">← Kembali</a>
        </div>
    </body>
    </html>
    <?php
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Data Tidak Ditemukan</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
            .back { display: inline-block; margin-top: 15px; color: #007bff; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error">
                <strong>❌ Error:</strong> Data absensi untuk nokartu <?= htmlspecialchars($nokartu) ?> tidak ditemukan.
            </div>
            <a href="test_notifikasi.php" class="back">← Kembali</a>
        </div>
    </body>
    </html>
    <?php
}

mysqli_close($conek);
?>