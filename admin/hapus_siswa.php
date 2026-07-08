<?php  
// ===== AWAL: Session dan koneksi =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

include "../koneksi.php";

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// baca id data yang akan dihapus
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: data_siswa.php");
    exit;
}

// Ambil data siswa untuk ditampilkan
$query = "SELECT nama_siswa, nis FROM siswa WHERE id = ?";
$stmt = $conek->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();
    $stmt->close();
}

if (!$siswa) {
    header("Location: data_siswa.php");
    exit;
}

// hapus akan dilakukan saat pengguna klik "Iya" di popup (via parameter GET hapus=yes)
if (isset($_GET['hapus']) && $_GET['hapus'] == 'yes') {
    // Gunakan prepared statement untuk keamanan
    $deleteQuery = "DELETE FROM siswa WHERE id = ?";
    $deleteStmt = $conek->prepare($deleteQuery);
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    // setelah hapus langsung arahkan ke halaman data siswa
    header("Location: data_siswa.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hapus Data Siswa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #e8e6f0;
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* ===== ANIMATED BACKGROUND ===== */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 10% 20%, rgba(72, 49, 212, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 80%, rgba(108, 99, 255, 0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(120, 119, 198, 0.03) 0%, transparent 70%),
                linear-gradient(135deg, #f0edf7 0%, #e8e4f0 30%, #ddd8e8 60%, #e8e4f0 100%);
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 40%, rgba(108, 99, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 70% 60%, rgba(72, 49, 212, 0.02) 0%, transparent 50%);
            z-index: 0;
            animation: floatGlow 25s ease-in-out infinite alternate;
        }

        @keyframes floatGlow {
            0% { transform: translate(0, 0) rotate(0deg) scale(1); }
            100% { transform: translate(3%, 2%) rotate(5deg) scale(1.05); }
        }

        /* ===== OVERLAY BACKGROUND (SAMAR/BLUR) ===== */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 9998;
            animation: fadeInOverlay 0.4s ease;
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ===== POPUP CONTAINER ===== */
        #popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            pointer-events: none;
        }

        #popup .content {
            pointer-events: auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            width: 420px;
            max-width: 90%;
            text-align: center;
            box-shadow: 
                0 30px 80px rgba(0, 0, 0, 0.25),
                0 0 60px rgba(72, 49, 212, 0.05);
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        #popup .content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #dc3545, #ef4444, #f87171, #ef4444, #dc3545);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        /* ===== ICON ===== */
        #popup .content .icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.10);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem auto;
            font-size: 32px;
            color: #dc3545;
            border: 2px solid rgba(220, 53, 69, 0.15);
        }

        /* ===== TITLE ===== */
        #popup .content h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 0.3rem;
        }

        #popup .content .subtitle {
            font-size: 0.8rem;
            color: #555577;
            margin-bottom: 0.3rem;
        }

        /* ===== INFO SISWA ===== */
        #popup .content .info-box {
            background: rgba(72, 49, 212, 0.04);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            margin: 0.8rem 0 1.2rem 0;
            border: 1px solid rgba(72, 49, 212, 0.06);
        }

        #popup .content .info-box .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.2rem 0;
            font-size: 0.8rem;
        }

        #popup .content .info-box .info-item .label {
            color: #8888aa;
            font-weight: 500;
        }

        #popup .content .info-box .info-item .value {
            color: #1a1a2e;
            font-weight: 600;
        }

        /* ===== DIVIDER ===== */
        #popup .content .divider {
            height: 1px;
            background: rgba(72, 49, 212, 0.08);
            margin: 0.8rem 0;
        }

        /* ===== WARNING TEXT ===== */
        #popup .content .warning-text {
            font-size: 0.7rem;
            color: #dc3545;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        #popup .content .warning-text i {
            font-size: 0.8rem;
        }

        /* ===== BUTTONS ===== */
        #popup .content .btn-group {
            display: flex;
            gap: 0.8rem;
            justify-content: center;
        }

        #popup .content .btn-group button {
            padding: 0.6rem 1.8rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        #popup .content .btn-group button:hover {
            transform: translateY(-2px);
        }

        #popup .content .btn-group button:active {
            transform: scale(0.97);
        }

        #btnIya {
            background: linear-gradient(135deg, #dc3545, #ef4444);
            color: #fff;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        #btnIya:hover {
            box-shadow: 0 6px 25px rgba(220, 53, 69, 0.45);
        }

        #btnTidak {
            background: rgba(72, 49, 212, 0.08);
            color: #555577;
        }

        #btnTidak:hover {
            background: rgba(72, 49, 212, 0.15);
            color: #1a1a2e;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 600px) {
            #popup .content {
                padding: 1.5rem 1.2rem;
                width: 95%;
            }

            #popup .content .icon-wrapper {
                width: 55px;
                height: 55px;
                font-size: 26px;
            }

            #popup .content h2 {
                font-size: 1.1rem;
            }

            #popup .content .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            #popup .content .btn-group button {
                width: 100%;
                justify-content: center;
            }

            #popup .content .info-box .info-item {
                font-size: 0.7rem;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(72, 49, 212, 0.04);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(108, 99, 255, 0.3);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(108, 99, 255, 0.5);
        }

        ::selection {
            background: rgba(108, 99, 255, 0.2);
            color: #1a1a2e;
        }
    </style>
</head>
<body>

<!-- ===== OVERLAY (SAMAR/BLUR) ===== -->
<div class="overlay"></div>

<!-- ===== POPUP ===== -->
<div id="popup">
    <div class="content">
        <!-- Icon -->
        <div class="icon-wrapper">
            <i class="fas fa-exclamation-triangle"></i>
        </div>

        <!-- Title -->
        <h2>Hapus Data Siswa</h2>
        <p class="subtitle">Anda yakin ingin menghapus data berikut?</p>

        <!-- Info Siswa -->
        <div class="info-box">
            <div class="info-item">
                <span class="label">Nama Siswa</span>
                <span class="value"><?= htmlspecialchars($siswa['nama_siswa'] ?? '-') ?></span>
            </div>
            <div class="info-item">
                <span class="label">NIS</span>
                <span class="value"><?= htmlspecialchars($siswa['nis'] ?? '-') ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Warning -->
        <div class="warning-text">
            <i class="fas fa-exclamation-circle"></i>
            Data yang dihapus tidak dapat dikembalikan!
        </div>

        <!-- Buttons -->
        <div class="btn-group">
            <button id="btnIya">
                <i class="fas fa-trash"></i> Ya, Hapus
            </button>
            <button id="btnTidak">
                <i class="fas fa-times"></i> Batal
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('btnIya').onclick = function() {
    window.location.href = 'hapus_siswa.php?id=<?= $id ?>&hapus=yes';
}

document.getElementById('btnTidak').onclick = function() {
    window.location.href = 'data_siswa.php';
}

// ===== TUTUP POPUP DENGAN ESC =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'data_siswa.php';
    }
});

// ===== KLIK DI LUAR POPUP =====
document.getElementById('popup').addEventListener('click', function(e) {
    if (e.target === this) {
        window.location.href = 'data_siswa.php';
    }
});
</script>

</body>
</html>