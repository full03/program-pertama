<?php
include "../koneksi.php";
date_default_timezone_set('Asia/Jakarta');

$now     = date('H:i:s');
$tanggal = date('Y-m-d');

// ===== AMBIL PENGATURAN JAM ABSEN DARI TABEL =====
$q = mysqli_query($conek, "SELECT * FROM pengaturan_jam_absen ORDER BY urutan ASC");
$pengaturan_jam = [];
while($row = mysqli_fetch_assoc($q)){
    $pengaturan_jam[] = $row;
}

// ===== CEK MODE ABSEN BERDASARKAN JAM =====
$mode_absen_siswa = 0;
$mode = "Di luar jam absen";
$jam_aturan = null;

foreach($pengaturan_jam as $j){
    if($now >= $j['jam_mulai'] && $now <= $j['jam_selesai']){
        $mode_absen_siswa = $j['urutan'];
        $mode = $j['nama_jam'];
        $jam_aturan = $j;
        break;
    }
}

// ===== FUNGSI UNTUK MENDAPATKAN NAMA KOLOM =====
function getJamColumn($urutan) {
    $mapping = [
        1 => 'jam_masuk_pertama',
        2 => 'jam_istirahat',
        3 => 'jam_masuk_kedua',
        4 => 'jam_pulang'
    ];
    return isset($mapping[$urutan]) ? $mapping[$urutan] : null;
}

// ===== FUNGSI UPDATE RIWAYAT SISWA =====
function updateRiwayatSiswa($conek, $siswa_id, $tanggal, $status) {
    // Cek apakah sudah ada data di riwayat_siswa
    $cek = mysqli_query($conek, "SELECT id FROM riwayat_siswa WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    
    // Mapping status ke kolom
    $status_mapping = [
        'Hadir' => 'hadir',
        'Sakit' => 'sakit',
        'Izin' => 'izin',
        'Alfa' => 'alfa',
        'Telat' => 'hadir' // Telat dianggap hadir tapi dengan catatan telat
    ];
    
    $kolom = isset($status_mapping[$status]) ? $status_mapping[$status] : 'hadir';
    
    if(mysqli_num_rows($cek) > 0) {
        // Reset semua status jadi 0
        $reset = "UPDATE riwayat_siswa SET hadir=0, sakit=0, izin=0, alfa=0 WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'";
        mysqli_query($conek, $reset);
        
        // Set status yang dipilih
        $update = "UPDATE riwayat_siswa SET $kolom=1 WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'";
        return mysqli_query($conek, $update);
    } else {
        // Insert baru
        $hadir = ($kolom == 'hadir') ? 1 : 0;
        $sakit = ($kolom == 'sakit') ? 1 : 0;
        $izin = ($kolom == 'izin') ? 1 : 0;
        $alfa = ($kolom == 'alfa') ? 1 : 0;
        
        $insert = "INSERT INTO riwayat_siswa (siswa_id, tanggal, hadir, sakit, izin, alfa) 
                   VALUES ('$siswa_id', '$tanggal', '$hadir', '$sakit', '$izin', '$alfa')";
        return mysqli_query($conek, $insert);
    }
}

// ===== BACA KARTU =====
$q = mysqli_query($conek, "SELECT * FROM tmprfid_siswa LIMIT 1");
$d = mysqli_fetch_array($q);
$nokartu = $d['nokartu'] ?? "";

// ===== BELUM TAP =====
if($nokartu == ""){
    echo "
    <div style='text-align:center; padding: 40px 20px; font-family: Arial, sans-serif;'>
        <h2 style='color: #1a1a2e; margin-bottom: 10px;'>Absen Siswa : $mode</h2>
        <h3 style='color: #555577; font-weight: 400; margin-bottom: 20px;'>Silahkan Tempelkan Kartu RFID Anda</h3>
        <img src='../images/rfid.png' width='150' style='margin-bottom: 15px;'><br>
        <img src='../images/animasi2.gif' width='150'>
    </div>";
    exit;
}

// ===== CARI KARTU =====
$cari = mysqli_query($conek, "SELECT * FROM siswa WHERE nokartu='$nokartu'");
if(mysqli_num_rows($cari) > 0){

    $s    = mysqli_fetch_array($cari);
    $siswa_id = $s['id'];
    $nama = $s['nama_siswa'];
    $kelas = $s['kelas'] ?? '-';
    $jam  = date('H:i:s');

    // ===== CEK DATA ABSENSI =====
    $cek = mysqli_query($conek,
        "SELECT * FROM absensi_siswa 
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    $data = mysqli_fetch_assoc($cek);

    // ===== STATUS =====
    $status = "Hadir";

    // Cek tabel izin & sakit
    $cekIzin = mysqli_query($conek, "SHOW TABLES LIKE 'izin_siswa'");
    $cekSakit = mysqli_query($conek, "SHOW TABLES LIKE 'sakit_siswa'");

    if(mysqli_num_rows($cekSakit) > 0){
        $qSakit = mysqli_query($conek,
        "SELECT * FROM sakit_siswa WHERE nokartu='$nokartu' AND tanggal='$tanggal'");
        if(mysqli_num_rows($qSakit) > 0){
            $status = "Sakit";
        }
    }

    if($status == "Hadir" && mysqli_num_rows($cekIzin) > 0){
        $qIzin = mysqli_query($conek,
        "SELECT * FROM izin_siswa WHERE nokartu='$nokartu' AND tanggal='$tanggal'");
        if(mysqli_num_rows($qIzin) > 0){
            $status = "Izin";
        }
    }

    $keterangan = $status;

    // ===== INSERT JIKA BELUM ADA =====
    if(!$data){
        $jam_absen = date('H:i:s');
        
        $insert_query = "INSERT INTO absensi_siswa (
            siswa_id, 
            tanggal, 
            jam_urutan, 
            jam_absen, 
            keterangan, 
            status
        ) VALUES (
            '$siswa_id', 
            '$tanggal', 
            '$mode_absen_siswa', 
            '$jam_absen', 
            '$keterangan',
            '$status'
        )";
        
        if(!mysqli_query($conek, $insert_query)){
            die("Error insert: " . mysqli_error($conek));
        }
        
        // *** UPDATE RIWAYAT SISWA ***
        updateRiwayatSiswa($conek, $siswa_id, $tanggal, $status);
        
        // Ambil data terbaru setelah insert
        $cek = mysqli_query($conek,
            "SELECT * FROM absensi_siswa 
             WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
        $data = mysqli_fetch_assoc($cek);
    }

    // ===== PROSES ABSEN BERDASARKAN MODE =====
    $message = "";
    $icon = "";
    $color = "";
    $columnName = getJamColumn($mode_absen_siswa);

    // ===== CEK APAKAH SUDAH ABSEN UNTUK JAM INI =====
    if($columnName && !empty($data[$columnName])){
        $message = "Anda sudah absen untuk " . $mode;
        $icon = "✅";
        $color = "#6b7280";
        
        echo "
        <div style='text-align:center; padding: 30px 20px; font-family: Arial, sans-serif;'>
            <div style='font-size: 4rem; margin-bottom: 15px;'>$icon</div>
            <h2 style='color: $color; margin-bottom: 5px;'>$message</h2>
            <h1 style='color: #1a1a2e; font-size: 2rem; margin-bottom: 5px;'>$nama</h1>
            <p style='color: #555577; font-size: 1.1rem;'>Kelas: $kelas</p>
            <p style='color: #666688; font-size: 0.9rem; margin-top: 10px;'>
                <span style='background: rgba(72,49,212,0.08); padding: 4px 12px; border-radius: 12px;'>
                    🕐 Waktu absen: " . $data[$columnName] . "
                </span>
            </p>
            <p style='color: #6c757d; font-size: 0.8rem; margin-top: 15px;'>
                Mode: <strong>$mode</strong>
            </p>
        </div>";
        
        mysqli_query($conek, "DELETE FROM tmprfid_siswa");
        exit;
    }

    // ===== PROSES ABSEN BERDASARKAN URUTAN =====
    $status_final = $status;
    
    if($mode_absen_siswa == 1){
        $message = "Selamat Masuk " . $mode;
        $icon = "🌅";
        $color = "#059669";
        
        $batas_telat = $jam_aturan ? $jam_aturan['batas_telat'] : 15;
        $jam_aturan_time = strtotime($jam_aturan['jam_mulai']);
        $telat = max(0, strtotime($jam) - $jam_aturan_time);
        $telat = intval($telat / 60);
        
        if($telat > $batas_telat && $status == "Hadir"){
            $keterangan = "Telat";
            $status_final = "Telat";
            mysqli_query($conek,
            "UPDATE absensi_siswa 
             SET keterangan='Telat'
             WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
            
            // *** UPDATE RIWAYAT SISWA JADI HADIR (TELAT TETAP DIHITUNG HADIR) ***
            updateRiwayatSiswa($conek, $siswa_id, $tanggal, "Hadir");
        } else {
            // *** UPDATE RIWAYAT SISWA JADI HADIR ***
            updateRiwayatSiswa($conek, $siswa_id, $tanggal, "Hadir");
        }

        mysqli_query($conek,
        "UPDATE absensi_siswa 
         SET jam_masuk_pertama='$jam', telat='$telat'
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    }
    elseif($mode_absen_siswa == 2){
        $message = "Selamat " . $mode;
        $icon = "☕";
        $color = "#fbbf24";

        mysqli_query($conek,
        "UPDATE absensi_siswa 
         SET jam_istirahat='$jam'
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    }
    elseif($mode_absen_siswa == 3){
        $message = "Selamat " . $mode;
        $icon = "📚";
        $color = "#3b82f6";

        mysqli_query($conek,
        "UPDATE absensi_siswa
         SET jam_masuk_kedua='$jam'
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    }
    elseif($mode_absen_siswa == 4){
        $message = "Selamat " . $mode;
        $icon = "🏠";
        $color = "#8b5cf6";

        mysqli_query($conek,
        "UPDATE absensi_siswa
         SET jam_pulang='$jam'
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    }
    else{
        $message = "Di luar jam absen";
        $icon = "⏰";
        $color = "#ef4444";
    }

    // ===== AMBIL DATA TERBARU =====
    $cek = mysqli_query($conek,
        "SELECT * FROM absensi_siswa 
         WHERE siswa_id='$siswa_id' AND tanggal='$tanggal'");
    $data_terbaru = mysqli_fetch_assoc($cek);
    
    // ===== TAMPILKAN HASIL =====
    $waktu_absen = $jam;
    if($columnName && isset($data_terbaru[$columnName])){
        $waktu_absen = $data_terbaru[$columnName];
    }
    
    $info_tambahan = "";
    if($mode_absen_siswa == 1 && $jam_aturan){
        $info_tambahan = " | Batas Telat: " . $jam_aturan['batas_telat'] . " menit";
        if(isset($telat) && $telat > 0){
            $info_tambahan .= " | Telat: " . $telat . " menit";
        }
    }

    // Tampilkan status berdasarkan data terbaru
    $status_tampil = $data_terbaru['keterangan'] ?? $status;

    echo "
    <div style='text-align:center; padding: 30px 20px; font-family: Arial, sans-serif;'>
        <div style='font-size: 4rem; margin-bottom: 15px;'>$icon</div>
        <h2 style='color: $color; margin-bottom: 5px;'>$message</h2>
        <h1 style='color: #1a1a2e; font-size: 2rem; margin-bottom: 5px;'>$nama</h1>
        <p style='color: #555577; font-size: 1.1rem;'>Kelas: $kelas</p>
        <p style='color: #666688; font-size: 0.9rem; margin-top: 10px;'>
            <span style='background: rgba(72,49,212,0.08); padding: 4px 12px; border-radius: 12px;'>
                🕐 $waktu_absen
            </span>
            <span style='background: rgba(72,49,212,0.08); padding: 4px 12px; border-radius: 12px; margin-left: 8px;'>
                📋 $status_tampil
            </span>
        </p>
        <p style='color: #6c757d; font-size: 0.8rem; margin-top: 15px;'>
            Mode: <strong>$mode</strong>
            $info_tambahan
        </p>
    </div>";

    mysqli_query($conek, "DELETE FROM tmprfid_siswa");
    exit;
}

// KARTU TIDAK DIKENAL
echo "
<div style='text-align:center; padding: 40px 20px; font-family: Arial, sans-serif;'>
    <div style='font-size: 4rem; margin-bottom: 15px;'>❌</div>
    <h1 style='color: #dc2626;'>Maaf! Kartu Tidak Dikenali</h1>
    <p style='color: #555577; margin-top: 10px;'>Silakan hubungi admin untuk mendaftarkan kartu</p>
</div>";
mysqli_query($conek, "DELETE FROM tmprfid_siswa");
?>