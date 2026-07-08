<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "../header.php"; ?>
    <title>Scan Kartu RFID</title>

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
            position: relative;
            overflow-x: hidden;
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

        /* ===== MAIN CONTAINER ===== */
        .container-fluid {
            position: relative;
            z-index: 1;
            padding: 0;
            width: calc(100%);
        }

        /* ===== SCAN CARD ===== */
        .scan-wrapper {
            width: 100%;
            padding: 20px;
        }

        .scan-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 
                0 30px 80px rgba(72, 49, 212, 0.10),
                0 10px 40px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.4s ease;
            min-height: 400px;
        }

        .scan-card:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 40px 100px rgba(72, 49, 212, 0.15),
                0 30px 80px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .scan-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: conic-gradient(
                from 0deg,
                transparent,
                rgba(108, 99, 255, 0.15),
                transparent 30%,
                rgba(72, 49, 212, 0.20),
                transparent 60%,
                rgba(108, 99, 255, 0.15),
                transparent
            );
            border-radius: 26px;
            z-index: -1;
            animation: rotateBorder 8s linear infinite;
        }

        @keyframes rotateBorder {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* ===== TYPOGRAPHY ===== */
        .scan-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 0.3rem;
            letter-spacing: -0.5px;
        }

        .scan-title .highlight {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .scan-subtitle {
            font-size: 0.85rem;
            color: #555577;
            font-weight: 400;
            margin-bottom: 0.3rem;
        }

        .scan-divider {
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(108, 99, 255, 0.3), transparent);
            margin: 0.8rem auto;
            border-radius: 2px;
        }

        /* ===== CARD READER AREA ===== */
        #cekkartu {
            margin-top: 1.2rem;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== FOOTER INFO ===== */
        .scan-footer {
            margin-top: 1.2rem;
            padding-top: 1.2rem;
            border-top: 1px solid rgba(72, 49, 212, 0.06);
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .scan-footer-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #666688;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .scan-footer-item .icon {
            font-size: 0.9rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .scan-card {
                padding: 1.8rem 1.2rem;
                margin: 0 10px;
                min-height: 300px;
            }

            .scan-title {
                font-size: 1.3rem;
            }

            .scan-footer {
                gap: 0.8rem;
            }

            .container-fluid {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .scan-card {
                padding: 1.2rem 0.8rem;
                border-radius: 16px;
                min-height: 250px;
            }

            .scan-title {
                font-size: 1.1rem;
            }

            .scan-subtitle {
                font-size: 0.75rem;
            }

            .scan-footer-item {
                font-size: 0.6rem;
            }

            #cekkartu {
                min-height: 150px;
            }
        }

        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <?php include "menu.php"; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="container-fluid">
        <div class="scan-wrapper">
            <div class="scan-card">

                <!-- Title -->
                <h1 class="scan-title">
                    Scan <span class="highlight">Kartu RFID</span>
                </h1>
                <p class="scan-subtitle">
                    Tempelkan kartu RFID Anda untuk melakukan absensi
                </p>

                <div class="scan-divider"></div>

                <!-- Card Reader Area -->
                <div id="cekkartu">
                    <!-- Konten akan di-load dari bacakartu.php -->
                </div>

                <!-- Footer Info -->
                <div class="scan-footer">
                    <span class="scan-footer-item">
                        <span class="icon">🔒</span>
                        Data Terenkripsi
                    </span>
                    <span class="scan-footer-item">
                        <span class="icon">⚡</span>
                        Real-time
                    </span>
                    <span class="scan-footer-item">
                        <span class="icon">📡</span>
                        RFID Reader Aktif
                    </span>
                </div>
                <?php include "../footer.php"; ?>   
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function(){
        // Fungsi untuk membaca kartu dari bacakartu.php
        function readCard() {
            $("#cekkartu").load('bacakartu.php', function(response, status, xhr) {
                if (status === 'error') {
                    console.log('Error membaca kartu:', xhr.status, xhr.statusText);
                }
            });
        }

        // Jalankan pembacaan kartu setiap 2 detik
        setInterval(readCard, 2000);

        console.log('🚀 Scanner RFID siap!');
        console.log('💡 Tempelkan kartu RFID untuk absensi');
        console.log('📡 Server sedang mendeteksi kartu...');
    });
    </script>

</body>
</html>