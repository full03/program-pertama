<?php
/**
 * File untuk memproses absensi siswa dan mengirim notifikasi
 * Dipanggil saat siswa melakukan scan kartu atau absen manual
 */

require_once '../../koneksi.php';

// Set header JSON
header('Content-Type: application/json');

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

// Validasi input
$nokartu = mysqli_real_escape_string($conek, $_POST['nokartu'] ?? '');
$tanggal = mysqli_real_escape_string($conek, $_POST['tanggal'] ?? date('Y-m-d'));
$jam_masuk = mysqli_real_escape_string($conek, $_POST['jam_masuk'] ?? date('H:i:s'));
$keterangan = mysqli_real_escape_string($conek, $_POST['keterangan'] ?? 'Hadir');

if (empty($nokartu)) {
    echo json_encode(['success' => false, 'message' => 'Nokartu tidak boleh kosong']);
    exit;
}

// Cek apakah siswa ada
$querySiswa = "SELECT * FROM siswa WHERE nokartu = '$nokartu'";
$resultSiswa = mysqli_query($conek, $querySiswa);

if (!$resultSiswa || mysqli_num_rows($resultSiswa) == 0) {
    echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
    exit;
}

$siswa = mysqli_fetch_assoc($resultSiswa);

// Cek apakah sudah absen hari ini
$queryCek = "SELECT * FROM absensi_siswa WHERE nokartu = '$nokartu' AND tanggal = '$tanggal'";
$resultCek = mysqli_query($conek, $queryCek);

// Jika belum absen, insert data baru
if (mysqli_num_rows($resultCek) == 0) {
    $queryInsert = "INSERT INTO absensi_siswa 
                    (nokartu, tanggal, jam_masuk_pertama, keterangan) 
                    VALUES 
                    ('$nokartu', '$tanggal', '$jam_masuk', '$keterangan')";
    
    if (mysqli_query($conek, $queryInsert)) {
        // Ambil data absensi yang baru saja diinsert
        $id_absensi = mysqli_insert_id($conek);
        $queryGet = "SELECT * FROM absensi_siswa WHERE id_absensi = '$id_absensi'";
        $resultGet = mysqli_query($conek, $queryGet);
        $data_absensi = mysqli_fetch_assoc($resultGet);
        
        // KIRIM NOTIFIKASI WHATSAPP
        require_once 'kirim_notifikasi.php';
        $result_notif = kirimNotifikasiAbsensi($data_absensi);
        
        echo json_encode([
            'success' => true,
            'message' => 'Absensi berhasil dicatat',
            'data' => [
                'siswa' => $siswa,
                'absensi' => $data_absensi
            ],
            'notifikasi' => $result_notif
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan data absensi: ' . mysqli_error($conek)
        ]);
    }
} else {
    // Jika sudah absen, update data absensi
    $data = mysqli_fetch_assoc($resultCek);
    $updates = [];
    
    // Update jam istirahat
    if (isset($_POST['jam_istirahat']) && !empty($_POST['jam_istirahat'])) {
        $jam_istirahat = mysqli_real_escape_string($conek, $_POST['jam_istirahat']);
        $updates[] = "jam_istirahat = '$jam_istirahat'";
    }
    
    // Update jam masuk kedua
    if (isset($_POST['jam_masuk_kedua']) && !empty($_POST['jam_masuk_kedua'])) {
        $jam_masuk_kedua = mysqli_real_escape_string($conek, $_POST['jam_masuk_kedua']);
        $updates[] = "jam_masuk_kedua = '$jam_masuk_kedua'";
    }
    
    // Update jam pulang
    if (isset($_POST['jam_pulang']) && !empty($_POST['jam_pulang'])) {
        $jam_pulang = mysqli_real_escape_string($conek, $_POST['jam_pulang']);
        $updates[] = "jam_pulang = '$jam_pulang'";
    }
    
    // Update keterangan
    if (isset($_POST['keterangan']) && !empty($_POST['keterangan'])) {
        $keterangan = mysqli_real_escape_string($conek, $_POST['keterangan']);
        $updates[] = "keterangan = '$keterangan'";
    }
    
    if (!empty($updates)) {
        $queryUpdate = "UPDATE absensi_siswa SET " . implode(', ', $updates) . " WHERE id_absensi = '{$data['id_absensi']}'";
        
        if (mysqli_query($conek, $queryUpdate)) {
            // Ambil data absensi terbaru
            $queryGet = "SELECT * FROM absensi_siswa WHERE id_absensi = '{$data['id_absensi']}'";
            $resultGet = mysqli_query($conek, $queryGet);
            $data_absensi = mysqli_fetch_assoc($resultGet);
            
            // KIRIM NOTIFIKASI WHATSAPP
            require_once 'kirim_notifikasi.php';
            $result_notif = kirimNotifikasiAbsensi($data_absensi);
            
            echo json_encode([
                'success' => true,
                'message' => 'Data absensi berhasil diupdate',
                'notifikasi' => $result_notif
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal update data: ' . mysqli_error($conek)
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada data yang diupdate'
        ]);
    }
}

mysqli_close($conek);
?>