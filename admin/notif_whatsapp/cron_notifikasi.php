#!/usr/bin/env php
<?php
/**
 * Cron job untuk mengirim notifikasi WhatsApp otomatis
 * Jalankan setiap 5 menit
 * 
 * Cara setup cron:
 * */5 * * * * /usr/bin/php /path/to/cron_notifikasi.php >> /path/to/log/notifikasi.log 2>&1
 */

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Log file
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . '/notifikasi_' . date('Y-m-d') . '.log';

// Fungsi untuk menulis log
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

writeLog("=== CRON NOTIFIKASI WA DIMULAI ===");

// Include file utama
require_once 'kirim_notifikasi.php';

// Include koneksi
require_once '../../koneksi.php';

// Proses notifikasi
try {
    // Ambil data absensi yang belum dikirim notifikasinya
    $query = "
        SELECT a.* 
        FROM absensi_siswa a
        LEFT JOIN log_notifikasi_wa l ON a.nokartu = l.nokartu AND a.tanggal = l.tanggal_absensi
        WHERE l.id_log IS NULL
        AND a.keterangan IN ('Hadir', 'Telat', 'Sakit', 'Izin', 'Alfa')
        ORDER BY a.tanggal DESC, a.id_absensi DESC
        LIMIT 50
    ";
    
    $result = mysqli_query($conek, $query);
    
    if (!$result) {
        throw new Exception("Query error: " . mysqli_error($conek));
    }
    
    $total = mysqli_num_rows($result);
    writeLog("Ditemukan $total data absensi baru");
    
    if ($total > 0) {
        $terkirim = 0;
        $gagal = 0;
        
        while ($data = mysqli_fetch_assoc($result)) {
            writeLog("Memproses nokartu: {$data['nokartu']}");
            
            $resultNotif = kirimNotifikasiAbsensi($data);
            
            if ($resultNotif['success']) {
                $terkirim++;
                writeLog("✅ Berhasil dikirim ke nokartu: {$data['nokartu']}");
            } else {
                $gagal++;
                writeLog("❌ Gagal: {$resultNotif['message']}");
            }
            
            // Delay untuk menghindari spam
            usleep(500000); // 0.5 detik
        }
        
        writeLog("=== SELESAI ===");
        writeLog("✅ Terkirim: $terkirim");
        writeLog("❌ Gagal: $gagal");
    } else {
        writeLog("ℹ️ Tidak ada data baru, cron selesai");
    }
    
} catch (Exception $e) {
    writeLog("❌ ERROR: " . $e->getMessage());
}

mysqli_close($conek);
writeLog("=== CRON NOTIFIKASI WA SELESAI ===\n");
?>