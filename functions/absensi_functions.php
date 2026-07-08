<?php
// File: functions/absensi_functions.php

function getStatusAbsensi($jam_masuk, $jam_istirahat, $jam_masuk_kedua, $jam_pulang, $tanggal) {
    global $conek;
    
    // Ambil pengaturan dari database
    $query = "SELECT * FROM setting_absensi LIMIT 1";
    $result = mysqli_query($conek, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $setting = mysqli_fetch_assoc($result);
    } else {
        // Default jika belum ada setting
        return 'Hadir'; // Atau status default lainnya
    }
    
    // Cek status berdasarkan jam
    $status = 'Alfa'; // Default
    
    // Cek jam masuk
    if (!empty($jam_masuk)) {
        if ($jam_masuk <= $setting['jam_masuk_sampai']) {
            $status = 'Hadir';
        } else {
            // Cek toleransi
            $batas_toleransi = date('H:i:s', strtotime($setting['jam_masuk_sampai'] . ' + ' . $setting['toleransi'] . ' minutes'));
            if ($jam_masuk <= $batas_toleransi) {
                $status = 'Terlambat';
            } else {
                $status = 'Alfa';
            }
        }
    }
    
    // Cek jam istirahat
    if (!empty($jam_istirahat)) {
        if ($jam_istirahat >= $setting['istirahat_dari'] && $jam_istirahat <= $setting['istirahat_sampai']) {
            $status = 'Hadir'; // Atau status lain
        }
    }
    
    // Cek jam masuk kedua
    if (!empty($jam_masuk_kedua)) {
        if ($jam_masuk_kedua >= $setting['masuk_kedua_dari'] && $jam_masuk_kedua <= $setting['masuk_kedua_sampai']) {
            $status = 'Hadir';
        }
    }
    
    // Cek jam pulang
    if (!empty($jam_pulang)) {
        if ($jam_pulang >= $setting['pulang_dari']) {
            $status = 'Hadir';
        }
    }
    
    return $status;
}
?>