<?php
// File: config/absensi_config.php
// Fungsi untuk mengambil pengaturan absensi

function getSettingAbsensi() {
    global $conek;
    
    // Cek koneksi
    if (!isset($conek) || $conek->connect_error) {
        return getDefaultSetting();
    }
    
    $query = "SELECT * FROM setting_absensi LIMIT 1";
    $result = mysqli_query($conek, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return getDefaultSetting();
}

function getDefaultSetting() {
    return [
        'jam_masuk_dari' => '07:00:00',
        'jam_masuk_sampai' => '08:00:00',
        'jam_masuk_nama' => 'Jam Masuk',
        'istirahat_dari' => '12:00:00',
        'istirahat_sampai' => '13:00:00',
        'istirahat_nama' => 'Istirahat',
        'masuk_kedua_dari' => '13:00:00',
        'masuk_kedua_sampai' => '14:00:00',
        'masuk_kedua_nama' => 'Jam Masuk 2',
        'pulang_dari' => '15:00:00',
        'pulang_sampai' => '16:00:00',
        'pulang_nama' => 'Pulang',
        'toleransi' => 30
    ];
}

function getStatusAbsensi($data_absensi, $setting) {
    // Jika tidak ada data absensi sama sekali
    if (empty($data_absensi['jam_masuk_pertama']) && 
        empty($data_absensi['jam_istirahat']) && 
        empty($data_absensi['jam_masuk_kedua']) && 
        empty($data_absensi['jam_pulang'])) {
        return 'Alfa';
    }
    
    // Cek berdasarkan jam masuk
    if (!empty($data_absensi['jam_masuk_pertama'])) {
        $jam_masuk = $data_absensi['jam_masuk_pertama'];
        
        // Jika masuk sebelum batas jam masuk
        if ($jam_masuk <= $setting['jam_masuk_sampai']) {
            return 'Hadir';
        }
        
        // Cek toleransi
        $batas_toleransi = date('H:i:s', strtotime($setting['jam_masuk_sampai'] . ' + ' . $setting['toleransi'] . ' minutes'));
        if ($jam_masuk <= $batas_toleransi) {
            return 'Terlambat';
        }
        
        return 'Alfa';
    }
    
    // Cek jam masuk kedua
    if (!empty($data_absensi['jam_masuk_kedua'])) {
        $jam_masuk_kedua = $data_absensi['jam_masuk_kedua'];
        if ($jam_masuk_kedua >= $setting['masuk_kedua_dari'] && 
            $jam_masuk_kedua <= $setting['masuk_kedua_sampai']) {
            return 'Hadir';
        }
    }
    
    // Cek jam pulang
    if (!empty($data_absensi['jam_pulang'])) {
        if ($data_absensi['jam_pulang'] >= $setting['pulang_dari']) {
            return 'Hadir';
        }
    }
    
    return 'Alfa';
}
?>