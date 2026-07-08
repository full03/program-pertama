<?php
/**
 * Script untuk mengirim notifikasi WhatsApp otomatis
 * File ini akan dipanggil ketika siswa melakukan absensi
 */

// Koneksi ke database
require_once '../../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Koneksi database gagal']));
}

/**
 * Fungsi untuk mengirim WhatsApp via Fonnte API
 * 
 * @param string $nomor_tujuan Nomor WhatsApp tujuan (format: 628xxxx)
 * @param string $pesan Pesan yang akan dikirim
 * @return array Status pengiriman
 */
function kirimWhatsApp($nomor_tujuan, $pesan) {
    // Konfigurasi Fonnte
    $api_key = "YOUR_API_KEY"; // GANTI DENGAN API KEY ANDA DARI FONNTE
    
    // Format nomor (hapus karakter non-digit)
    $nomor = preg_replace('/[^0-9]/', '', $nomor_tujuan);
    
    // Jika nomor dimulai dengan 0, ganti dengan 62
    if (substr($nomor, 0, 1) == '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    // Jika tidak dimulai dengan 62, tambahkan 62
    elseif (substr($nomor, 0, 2) != '62') {
        $nomor = '62' . $nomor;
    }
    
    $url = "https://api.fonnte.com/send";
    $data = [
        'target' => $nomor,
        'message' => $pesan,
        'countryCode' => '62'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return [
        'success' => ($http_code == 200 && isset($result['status']) && $result['status'] == true),
        'response' => $response,
        'http_code' => $http_code,
        'error' => $error,
        'data' => $result
    ];
}

/**
 * Fungsi untuk mengirim notifikasi ketika siswa absen
 * 
 * @param array $data_absensi Data absensi siswa
 * @return array Hasil pengiriman
 */
function kirimNotifikasiAbsensi($data_absensi) {
    global $conek;
    
    // Validasi data
    if (empty($data_absensi['nokartu'])) {
        return [
            'success' => false,
            'message' => 'Data absensi tidak valid (nokartu kosong)'
        ];
    }
    
    // Ambil data siswa dari database
    $nokartu = mysqli_real_escape_string($conek, $data_absensi['nokartu']);
    $query = "SELECT * FROM siswa WHERE nokartu = '$nokartu'";
    $result = mysqli_query($conek, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        return [
            'success' => false,
            'message' => 'Data siswa tidak ditemukan'
        ];
    }
    
    $siswa = mysqli_fetch_assoc($result);
    
    // Cek apakah notifikasi diaktifkan untuk siswa ini
    if (isset($siswa['notifikasi_wa']) && $siswa['notifikasi_wa'] == 0) {
        return [
            'success' => false,
            'message' => 'Notifikasi WhatsApp dinonaktifkan untuk siswa ini'
        ];
    }
    
    // Ambil nomor WhatsApp dari kolom no_hp
    $nomor_whatsapp = $siswa['no_hp'] ?? '';
    if (empty($nomor_whatsapp)) {
        return [
            'success' => false,
            'message' => 'Nomor WhatsApp siswa tidak tersedia (no_hp kosong)'
        ];
    }
    
    // Format pesan berdasarkan status absensi
    $status = $data_absensi['keterangan'] ?? 'Hadir';
    $tanggal = date('d-m-Y', strtotime($data_absensi['tanggal'] ?? date('Y-m-d')));
    $jam_masuk = $data_absensi['jam_masuk_pertama'] ?? '-';
    $jam_istirahat = $data_absensi['jam_istirahat'] ?? '-';
    $jam_kembali = $data_absensi['jam_masuk_kedua'] ?? '-';
    $jam_pulang = $data_absensi['jam_pulang'] ?? '-';
    
    // Buat pesan dengan template yang informatif
    $pesan = "🏫 *NOTIFIKASI ABSENSI SISWA*\n\n";
    $pesan .= "👤 Nama: *{$siswa['nama_siswa']}*\n";
    $pesan .= "📚 Kelas: *{$siswa['kelas']}*\n";
    $pesan .= "📖 Jurusan: *{$siswa['jurusan']}*\n";
    $pesan .= "📅 Tanggal: *{$tanggal}*\n";
    $pesan .= "📌 Status: *{$status}*\n\n";
    
    if ($status == 'Hadir') {
        $pesan .= "⏰ *Detail Waktu:*\n";
        $pesan .= "└ 🟢 Jam Masuk: {$jam_masuk}\n";
        if (!empty($jam_istirahat) && $jam_istirahat != '-') {
            $pesan .= "└ 🟡 Jam Istirahat: {$jam_istirahat}\n";
        }
        if (!empty($jam_kembali) && $jam_kembali != '-') {
            $pesan .= "└ 🔵 Kembali Masuk: {$jam_kembali}\n";
        }
        if (!empty($jam_pulang) && $jam_pulang != '-') {
            $pesan .= "└ 🔴 Jam Pulang: {$jam_pulang}\n";
        }
    } elseif ($status == 'Sakit') {
        $pesan .= "💊 *Keterangan:* Siswa sakit dan tidak dapat mengikuti pelajaran.\n";
        $pesan .= "🩺 Mohon segera periksa ke dokter jika diperlukan.\n";
    } elseif ($status == 'Izin') {
        $pesan .= "📝 *Keterangan:* Siswa izin tidak masuk sekolah.\n";
        $pesan .= "📋 Mohon sampaikan surat izin kepada wali kelas.\n";
    } elseif ($status == 'Alfa' || $status == 'Alpha') {
        $pesan .= "⚠️ *Keterangan:* Siswa tidak hadir tanpa keterangan.\n";
        $pesan .= "📞 Mohon hubungi wali kelas untuk konfirmasi.\n";
    } elseif ($status == 'Telat' || $status == 'Terlambat') {
        $pesan .= "⏰ *Keterangan:* Siswa datang terlambat.\n";
        $pesan .= "📞 Mohon perhatikan kedisiplinan siswa.\n";
    }
    
    $pesan .= "\n---\n📱 *Notifikasi Otomatis Sistem Absensi*\n";
    $pesan .= "Terima kasih 🙏";
    
    // Kirim WhatsApp
    $result = kirimWhatsApp($nomor_whatsapp, $pesan);
    
    // Simpan log notifikasi
    $nokartu_escaped = mysqli_real_escape_string($conek, $data_absensi['nokartu']);
    $tanggal_absensi = mysqli_real_escape_string($conek, $data_absensi['tanggal'] ?? date('Y-m-d'));
    $status_escaped = mysqli_real_escape_string($conek, $status);
    $pesan_escaped = mysqli_real_escape_string($conek, $pesan);
    $status_kirim = $result['success'] ? 'Terkirim' : 'Gagal';
    $response_escaped = mysqli_real_escape_string($conek, json_encode($result));
    
    $logQuery = "INSERT INTO log_notifikasi_wa 
                  (nokartu, tanggal_absensi, status_absensi, pesan, status_kirim, waktu_kirim, response_api) 
                  VALUES 
                  ('$nokartu_escaped', '$tanggal_absensi', '$status_escaped', '$pesan_escaped', '$status_kirim', NOW(), '$response_escaped')";
    mysqli_query($conek, $logQuery);
    
    return [
        'success' => $result['success'],
        'message' => $result['success'] ? 'Notifikasi berhasil dikirim' : 'Gagal mengirim notifikasi',
        'data' => $result
    ];
}

// Jika file diakses langsung (untuk testing)
if (basename($_SERVER['PHP_SELF']) == 'kirim_notifikasi.php') {
    // Cek apakah ada parameter nokartu (untuk testing manual)
    if (isset($_GET['nokartu'])) {
        $nokartu = mysqli_real_escape_string($conek, $_GET['nokartu']);
        $query = "SELECT * FROM absensi_siswa WHERE nokartu = '$nokartu' ORDER BY tanggal DESC, id_absensi DESC LIMIT 1";
        $result = mysqli_query($conek, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $data_absensi = mysqli_fetch_assoc($result);
            $resultNotif = kirimNotifikasiAbsensi($data_absensi);
            
            header('Content-Type: application/json');
            echo json_encode($resultNotif, JSON_PRETTY_PRINT);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Data absensi tidak ditemukan']);
        }
        exit;
    }
    
    // Mode cron job
    $query = "
        SELECT a.* 
        FROM absensi_siswa a
        LEFT JOIN log_notifikasi_wa l ON a.nokartu = l.nokartu AND a.tanggal = l.tanggal_absensi
        WHERE l.id_log IS NULL
        AND a.keterangan IN ('Hadir', 'Telat', 'Terlambat', 'Sakit', 'Izin', 'Alfa', 'Alpha')
        ORDER BY a.tanggal DESC, a.id_absensi DESC
        LIMIT 100
    ";
    
    $result = mysqli_query($conek, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $terkirim = 0;
        $gagal = 0;
        $errors = [];
        
        while ($data = mysqli_fetch_assoc($result)) {
            $resultNotif = kirimNotifikasiAbsensi($data);
            
            if ($resultNotif['success']) {
                $terkirim++;
            } else {
                $gagal++;
                $errors[] = $resultNotif['message'] . ' (Nokartu: ' . $data['nokartu'] . ')';
            }
            
            // Delay untuk menghindari spam
            usleep(500000); // 0.5 detik
        }
        
        echo "✅ Notifikasi terkirim: $terkirim\n";
        echo "❌ Notifikasi gagal: $gagal\n";
        
        if (!empty($errors)) {
            echo "\n=== DETAIL ERROR ===\n";
            foreach ($errors as $error) {
                echo "- $error\n";
            }
        }
    } else {
        echo "ℹ️ Tidak ada data absensi baru yang perlu dikirim notifikasi.\n";
    }
}
?>