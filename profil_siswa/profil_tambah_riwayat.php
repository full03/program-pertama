<?php
include "../koneksi.php";

// ======================================================
// 1. JIKA FORM DIKIRIM (POST) → PROSES SIMPAN DATA
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $siswa_id     = $_POST['siswa_id'];
    $tanggal_raw  = $_POST['tanggal'];

    $sakit        = $_POST['sakit'] ?? 0;
    $izin         = $_POST['izin'] ?? 0;
    $alfa         = $_POST['alfa'] ?? 0;
    $keterangan   = $_POST['keterangan'] ?? '';

    // ---------- Konversi Format Tanggal ----------
    $tanggal_raw = str_replace("/", "-", $tanggal_raw);
    $p = explode("-", $tanggal_raw);

    if (count($p) != 3) {
        die("Format tanggal salah! Gunakan format: 01-01-2025");
    }

    // Format dd-mm-yyyy → yyyy-mm-dd
    $tanggal = $p[2] . "-" . $p[1] . "-" . $p[0];

    // ---------- Query Simpan ----------
    $sql = mysqli_query($conek, "
        INSERT INTO riwayat_siswa (siswa_id, tanggal, sakit, izin, alfa, keterangan)
        VALUES ('$siswa_id', '$tanggal', '$sakit', '$izin', '$alfa', '$keterangan')
    ");

    if ($sql) {
        header("Location: profil_siswa.php?id=$siswa_id&msg=sukses");
        exit();
    } else {
        echo "Gagal menyimpan data: " . mysqli_error($conek);
        exit();
    }
}



// ======================================================
// 2. SAAT HALAMAN DIBUKA (GET) → TAMPILKAN FORM INPUT
// ======================================================
if (!isset($_GET['siswa_id'])) {
    die("ID siswa tidak ditemukan.");
}

$siswa_id = $_GET['siswa_id'];

$sql = mysqli_query($conek, "SELECT nama_siswa FROM siswa WHERE id='$siswa_id'");
$siswa = mysqli_fetch_assoc($sql);

if (!$siswa) {
    die("Data siswa tidak valid.");
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Tambah Riwayat Absensi Siswa</title>

<style>
    body {
        background: #eef2f7;
        font-family: "Poppins";
        display:flex;
        justify-content:center;
        padding:40px;
    }
    .card {
        width: 450px;
        background:white;
        padding:25px;
        border-radius:15px;
        box-shadow:0 6px 20px rgba(0,0,0,0.2);
    }
    input, textarea, select {
        width:100%;
        padding:10px;
        margin:8px 0 15px 0;
        border-radius:8px;
        border:1px solid #aaa;
        font-size:15px;
    }
    button {
        width:100%;
        padding:12px;
        background:#28a745;
        border:none;
        color:white;
        border-radius:8px;
        font-size:16px;
        cursor:pointer;
    }
    button:hover { background:#1f7b35; }
    a { text-decoration:none; color:#007bff; }
</style>
</head>

<body>

<div class="card">
    <h2 style="text-align:center;">Tambah Riwayat Absensi Siswa</h2>

    <p><b>Siswa:</b> <?= $siswa['nama_siswa']; ?></p>

    <form action="" method="POST">

        <input type="hidden" name="siswa_id" value="<?= $siswa_id ?>">

        <label>Tanggal</label>
        <input type="text" name="tanggal" placeholder="01-01-2025" required>

        <label>Sakit</label>
        <input type="number" name="sakit" value="0" required>

        <label>Izin</label>
        <input type="number" name="izin" value="0" required>

        <label>Alfa</label>
        <input type="number" name="alfa" value="0" required>

        <label>Keterangan</label>
        <textarea name="keterangan" rows="3"></textarea>

        <button type="submit">Tambah Riwayat</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <a href="profil_siswa.php?id=<?= $siswa_id ?>">Kembali</a>
    </div>
</div>

</body>
</html>
