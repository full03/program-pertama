<?php
include "../koneksi.php";

date_default_timezone_set('Asia/Jakarta');

$uid  = isset($_GET['uid'])  ? strtoupper(trim($_GET['uid']))  : '';
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : '';

if ($uid == '' && $mode != 'TAMBAH_SISWA' && $mode != 'HAPUS_TMPRFID') {
    if ($mode == '') {
        exit("ERROR");
    }
}

/* ========= MODE CEK ========= */
if ($mode == "CEK") {
    $stmt = mysqli_prepare($conek, "SELECT id FROM siswa WHERE nokartu = ?");
    mysqli_stmt_bind_param($stmt, "s", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo "ABSEN";
    } else {
        echo "DAFTAR";
    }
    mysqli_stmt_close($stmt);
    exit;
}

/* ========= MODE DAFTAR ========= */
if ($mode == "DAFTAR") {
    // sudah ada?
    $stmt = mysqli_prepare($conek, "SELECT id FROM siswa WHERE nokartu = ?");
    mysqli_stmt_bind_param($stmt, "s", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo "SUDAH_TERDAFTAR";
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // ambil siswa yang belum punya kartu (tanpa filter status)
    $stmt = mysqli_prepare($conek, "SELECT id FROM siswa WHERE (nokartu IS NULL OR nokartu = '') LIMIT 1");
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo "GAGAL";
        mysqli_stmt_close($stmt);4
        exit;
    }
    
    $d = mysqli_fetch_assoc($result);
    $id = $d['id'];
    mysqli_stmt_close($stmt);
    
    // Update siswa
    $stmt = mysqli_prepare($conek, "UPDATE siswa SET nokartu = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $uid, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Simpan ke tmprfid_siswa untuk ditampilkan di web
    mysqli_query($conek, "DELETE FROM tmprfid_siswa");
    
    // Ambil nama siswa
    $stmt = mysqli_prepare($conek, "SELECT nama_siswa FROM siswa WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $siswa = mysqli_fetch_assoc($result);
    $nama_siswa = $siswa['nama_siswa'] ?? '';
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conek, "INSERT INTO tmprfid_siswa (nokartu, nama_siswa, status) VALUES (?, ?, 'terdaftar')");
    mysqli_stmt_bind_param($stmt, "ss", $uid, $nama_siswa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo "BERHASIL_DIDAFTAR|" . $nama_siswa;
    exit;
}

/* ========= MODE ABSEN ========= */
if ($mode == "ABSEN") {
    // Cari siswa berdasarkan nokartu (tanpa filter status)
    $stmt = mysqli_prepare($conek, "SELECT id, nama_siswa FROM siswa WHERE nokartu = ?");
    mysqli_stmt_bind_param($stmt, "s", $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo "GAGAL";
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $d = mysqli_fetch_assoc($result);
    $siswa_id = $d['id'];
    $nama_siswa = $d['nama_siswa'];
    mysqli_stmt_close($stmt);
    
    $tgl = date('Y-m-d');
    $jam = date('H:i:s');
    
    // sudah absen hari ini? (gunakan tabel absensi_siswa)
    $stmt = mysqli_prepare($conek, "SELECT id, jam_masuk_pertama FROM absensi_siswa WHERE nokartu = ? AND tanggal = ?");
    mysqli_stmt_bind_param($stmt, "ss", $uid, $tgl);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $absen_data = mysqli_fetch_assoc($result);
        $jam_absen = $absen_data['jam_masuk_pertama'];
        mysqli_stmt_close($stmt);
        
        // Simpan ke tmprfid_siswa untuk ditampilkan di web
        mysqli_query($conek, "DELETE FROM tmprfid_siswa");
        $stmt2 = mysqli_prepare($conek, "INSERT INTO tmprfid_siswa (nokartu, nama_siswa, jam_absen, status) VALUES (?, ?, ?, 'sudah_absen')");
        mysqli_stmt_bind_param($stmt2, "sss", $uid, $nama_siswa, $jam_absen);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        echo "SUKSES_SUDAH_ABSEN";
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // Insert absensi baru ke absensi_siswa
    $stmt = mysqli_prepare($conek, "INSERT INTO absensi_siswa (nokartu, tanggal, jam_masuk_pertama, keterangan) VALUES (?, ?, ?, 'Hadir')");
    mysqli_stmt_bind_param($stmt, "sss", $uid, $tgl, $jam);
    $insert = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($insert) {
        // Simpan ke tmprfid_siswa untuk ditampilkan di web
        mysqli_query($conek, "DELETE FROM tmprfid_siswa");
        $stmt2 = mysqli_prepare($conek, "INSERT INTO tmprfid_siswa (nokartu, nama_siswa, jam_absen, status) VALUES (?, ?, ?, 'baru_absen')");
        mysqli_stmt_bind_param($stmt2, "sss", $uid, $nama_siswa, $jam);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
        
        echo "SUKSES_ABSEN|" . $nama_siswa . "|" . $jam;
    } else {
        echo "GAGAL";
    }
    exit;
}

/* ========= MODE TAMBAH_SISWA (untuk menyimpan dari form) ========= */
if ($mode == "TAMBAH_SISWA") {
    // Ambil data dari POST
    $nokartu       = isset($_POST['nokartu']) ? trim($_POST['nokartu']) : '';
    $nis           = isset($_POST['nis']) ? trim($_POST['nis']) : '';
    $nama_siswa    = isset($_POST['nama_siswa']) ? trim($_POST['nama_siswa']) : '';
    $jenis_kelamin = isset($_POST['jenis_kelamin']) ? trim($_POST['jenis_kelamin']) : '';
    $ttl           = isset($_POST['ttl']) ? trim($_POST['ttl']) : '';
    $kelas         = isset($_POST['kelas']) ? trim($_POST['kelas']) : '';
    $jurusan       = isset($_POST['jurusan']) ? trim($_POST['jurusan']) : '';
    $nama_wali     = isset($_POST['nama_wali']) ? trim($_POST['nama_wali']) : '';
    $no_hp         = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $alamat        = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    
    // Validasi data
    if ($nama_siswa == '' || $nis == '') {
        echo "ERROR_DATA_KOSONG";
        exit;
    }
    
    // Cek apakah nokartu sudah ada (jika ada kartu)
    if ($nokartu != '') {
        $stmt = mysqli_prepare($conek, "SELECT id FROM siswa WHERE nokartu = ?");
        mysqli_stmt_bind_param($stmt, "s", $nokartu);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            echo "ERROR_KARTU_SUDAH_TERDAFTAR";
            mysqli_stmt_close($stmt);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Cek apakah NIS sudah ada
    $stmt = mysqli_prepare($conek, "SELECT id FROM siswa WHERE nis = ?");
    mysqli_stmt_bind_param($stmt, "s", $nis);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        echo "ERROR_NIS_SUDAH_ADA";
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // Insert data siswa baru (tanpa kolom status)
    $stmt = mysqli_prepare($conek, "INSERT INTO siswa (nokartu, nis, nama_siswa, jenis_kelamin, ttl, kelas, jurusan, nama_wali, no_hp, alamat, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "ssssssssss", $nokartu, $nis, $nama_siswa, $jenis_kelamin, $ttl, $kelas, $jurusan, $nama_wali, $no_hp, $alamat);
    
    if (mysqli_stmt_execute($stmt)) {
        // Kosongkan tabel tmprfid setelah digunakan
        mysqli_query($conek, "DELETE FROM tmprfid_siswa");
        echo "BERHASIL";
    } else {
        echo "GAGAL";
    }
    mysqli_stmt_close($stmt);
    exit;
}

/* ========= MODE HAPUS_TMPRFID ========= */
if ($mode == "HAPUS_TMPRFID") {
    mysqli_query($conek, "DELETE FROM tmprfid_siswa");
    echo "BERHASIL_HAPUS";
    exit;
}

echo "ERROR";
?>