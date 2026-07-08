<?php
// ===== AWAL: Session dan koneksi =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login - lebih fleksibel
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$isLoggedIn) {
    // Coba cek dari session lain
    if (isset($_SESSION['user_id']) || isset($_SESSION['username'])) {
        $isLoggedIn = true;
    }
}

if (!$isLoggedIn) {
    http_response_code(403);
    echo "Akses ditolak";
    exit();
}

require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    http_response_code(500);
    echo "Koneksi database gagal";
    exit();
}

// ================================================================
// FUNGSI: Membaca kartu dari tabel tmprfid_siswa
// ================================================================
function bacaKartuDariTmprfid($conek) {
    $query = "SELECT nokartu FROM tmprfid_siswa LIMIT 1";
    $result = mysqli_query($conek, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $card = trim($row['nokartu']);
        
        // Validasi: pastikan kartu tidak kosong
        if (!empty($card)) {
            // LANGSUNG HAPUS setelah dibaca agar tidak terbaca ulang
            mysqli_query($conek, "DELETE FROM tmprfid_siswa");
            return $card;
        }
    }
    return '';
}

// ================================================================
// EKSEKUSI UTAMA
// ================================================================

$card_data = '';

// Baca dari tmprfid_siswa (utama)
$card_data = bacaKartuDariTmprfid($conek);

// Jika kosong, coba dari file (alternatif)
if (empty($card_data)) {
    $file_paths = [
        'card_data.txt',
        '../rfid/card_data.txt',
        'rfid_card.txt'
    ];
    
    foreach ($file_paths as $file_path) {
        if (file_exists($file_path)) {
            $card_data = trim(file_get_contents($file_path));
            if (!empty($card_data)) {
                file_put_contents($file_path, '');
                break;
            }
        }
    }
}

// Output hasil (HANYA nomor kartu, tanpa teks lain)
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $card_data;

// Tutup koneksi
if (isset($conek)) {
    mysqli_close($conek);
}
?>