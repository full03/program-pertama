<?php
include "../koneksi.php";

// ======================================================
// 1. JIKA FORM DI-SUBMIT (POST) → PROSES UPDATE DATA
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id          = $_POST['id'];
    $siswa_id    = $_POST['siswa_id'];
    $tanggal_raw = $_POST['tanggal'];
    $sakit       = $_POST['sakit'];
    $izin        = $_POST['izin'];
    $alfa        = $_POST['alfa'];
    $keterangan  = $_POST['keterangan'];

    // ========== VALIDASI ANGKA ==========
    if (!is_numeric($sakit) || !is_numeric($izin) || !is_numeric($alfa)) {
        echo "<script>alert('Input Sakit, Izin, dan Alfa harus berupa angka!'); history.back();</script>";
        exit();
    }

    // ========== VALIDASI FORMAT TANGGAL ==========
    // Format yang benar: dd-mm-yyyy
    $tanggal_raw = str_replace("/", "-", $tanggal_raw);
    $p = explode("-", $tanggal_raw);

    if (count($p) == 3 && checkdate($p[1], $p[0], $p[2])) {
        $tanggal = $p[2] . "-" . $p[1] . "-" . $p[0];
    } else {
        echo "<script>alert('Format tanggal salah! Gunakan format dd-mm-yyyy'); history.back();</script>";
        exit();
    }

    // ========== PROSES UPDATE ==========
    $sql = mysqli_query($conek, "
        UPDATE riwayat_siswa
        SET tanggal='$tanggal',
            sakit='$sakit',
            izin='$izin',
            alfa='$alfa',
            keterangan='$keterangan'
        WHERE id='$id'
    ");

    if ($sql) {
        echo "<script>
                alert('Update berhasil!');
                window.location.href='profil_siswa.php?id=$siswa_id&tab=riwayat&msg=update_sukses';
              </script>";
        exit();
    } else {
        echo "<script>alert('Gagal memperbarui data!'); history.back();</script>";
        exit();
    }
}



// ======================================================
// 2. JIKA HALAMAN DIBUKA (GET) → TAMPILKAN FORM EDIT
// ======================================================
if (!isset($_GET['id'])) {
    die("ID riwayat tidak ditemukan.");
}

$id = intval($_GET['id']);

$sql = mysqli_query($conek, "
    SELECT rs.*, s.nama_siswa 
    FROM riwayat_siswa rs
    JOIN siswa s ON rs.siswa_id = s.id
    WHERE rs.id = '$id'
");

$data = mysqli_fetch_assoc($sql);

if (!$data) {
    die("Data riwayat tidak ditemukan atau ID tidak valid.");
}

// Format tanggal dari yyyy-mm-dd → dd-mm-yyyy
$tanggal_format_tampil = date("d-m-Y", strtotime($data['tanggal']));
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Riwayat Siswa</title>

<style>
    body {
        background: #eef2f7;
        font-family: "Poppins";
        display: flex;
        justify-content: center;
        padding: 40px;
    }
    .card {
        width: 450px;
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    input, textarea {
        width: 100%;
        padding: 10px;
        margin: 8px 0 15px 0;
        border-radius: 8px;
        border: 1px solid #aaa;
        font-size: 15px;
    }
    button {
        width: 100%;
        padding: 12px;
        background: #007bff;
        border: none;
        color: white;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
    }
    button:hover { background: #0056b3; }
    a { text-decoration: none; color: #007bff; }
</style>
</head>

<body>

<div class="card">
    <h2 style="text-align:center;">Edit Riwayat Siswa</h2>

    <h3><b>Siswa:</b> <span style="font-weight: normal;"><?= $data['nama_siswa']; ?></span></h3>

    <form action="" method="POST">

        <input type="hidden" name="id" value="<?= $data['id'] ?>">
        <input type="hidden" name="siswa_id" value="<?= $data['siswa_id'] ?>">

        <label>Tanggal</label>
        <input type="text" name="tanggal" value="<?= $tanggal_format_tampil ?>" required>

        <label>Sakit</label>
        <input type="text" name="sakit" value="<?= $data['sakit'] ?>" required>

        <label>Izin</label>
        <input type="text" name="izin" value="<?= $data['izin'] ?>" required>

        <label>Alfa</label>
        <input type="text" name="alfa" value="<?= $data['alfa'] ?>" required>

        <label>Keterangan</label>
        <textarea name="keterangan" rows="3"><?= $data['keterangan'] ?></textarea>

        <button type="submit">Update Riwayat</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <a href="profil_siswa.php?id=<?= $data['siswa_id'] ?>&tab=riwayat">Kembali</a>
    </div>
</div>

</body>
</html>
