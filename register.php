<?php
session_start();
// Jika sudah login, redirect
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin_dashboard.php");
    } elseif ($_SESSION['role'] === 'wali_kelas') {
        header("Location: wali_kelas/wali_kelas_dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

// Koneksi database untuk mengambil daftar kelas
require_once 'koneksi.php';
$daftar_kelas = [];
if (isset($conek) && !$conek->connect_error) {
    $query_kelas = "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC";
    $result_kelas = $conek->query($query_kelas);
    if ($result_kelas) {
        while ($row = $result_kelas->fetch_assoc()) {
            $daftar_kelas[] = $row['kelas'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Wali Kelas - Sistem Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== STYLE SAMA SEPERTI SEBELUMNYA ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f0f2f5;
            overflow-x: hidden;
        }

        .background-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .background-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.95) saturate(1.2);
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.5) 0%, 
                rgba(255, 255, 255, 0.2) 50%,
                rgba(102, 126, 234, 0.08) 100%);
            z-index: 1;
        }

        .register-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 520px;
            animation: slideUp 0.6s ease;
            padding: 20px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-card {
            background: rgba(255, 255, 255, 0.30);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.12),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.4s ease;
        }

        .register-card:hover {
            background: rgba(255, 255, 255, 0.40);
            box-shadow: 
                0 12px 48px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transform: translateY(-3px);
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.35);
        }

        .logo i {
            font-size: 24px;
        }

        .register-header h2 {
            color: #1a1a2e;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 
                0 2px 8px rgba(255, 255, 255, 0.8),
                0 1px 4px rgba(255, 255, 255, 0.6);
        }

        .register-header p {
            color: #2d3436;
            font-size: 15px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.8),
                0 1px 3px rgba(255, 255, 255, 0.5);
        }

        .register-header .role-info {
            background: rgba(102, 126, 234, 0.12);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 15px;
            display: inline-block;
            font-size: 13px;
            color: #4a3f7a;
            font-weight: 600;
        }

        .register-header .role-info i {
            color: #667eea;
            margin-right: 8px;
        }

        .register-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            color: #1a1a2e;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .form-group label i {
            color: #667eea;
            font-size: 14px;
        }

        .form-group label .required {
            color: #e74c3c;
            font-size: 12px;
        }

        .form-group input,
        .form-group select {
            padding: 13px 16px;
            border: 2px solid rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1a1a2e;
            font-family: 'Inter', sans-serif;
            width: 100%;
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-group select option {
            background: #ffffff;
            color: #1a1a2e;
            padding: 8px;
        }

        .form-group input::placeholder {
            color: rgba(26, 26, 46, 0.5);
            font-weight: 400;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.90);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(26, 26, 46, 0.5);
            cursor: pointer;
            transition: color 0.3s ease;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .password-requirements {
            font-size: 12px;
            margin-top: 4px;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 4px 0 0 0;
        }

        .password-requirements ul li {
            padding: 2px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .password-requirements ul li i {
            font-size: 10px;
        }

        .password-requirements ul li.valid {
            color: #00b894;
        }

        .password-requirements ul li.invalid {
            color: #e74c3c;
        }

        .register-btn {
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
            margin-top: 10px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.35);
            letter-spacing: 0.5px;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.45);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .login-link p {
            color: #1a1a2e;
            font-size: 14px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 25px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: slideDown 0.5s ease;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            max-width: 400px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .custom-alert .alert-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0 0 0 10px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .custom-alert .alert-close:hover {
            opacity: 1;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.95);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.95);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.95);
        }

        .change-bg-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            padding: 14px 24px;
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Inter', sans-serif;
        }

        .change-bg-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 1);
        }

        .change-bg-btn i {
            font-size: 18px;
            color: #667eea;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: scaleIn 0.3s ease;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #1a1a2e;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #667eea;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 0 8px;
        }

        .close-btn:hover {
            color: #e74c3c;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-body p {
            color: #495057;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .bg-options {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .bg-option {
            aspect-ratio: 16/9;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .bg-option:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }

        .bg-option.active {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
        }

        .upload-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .upload-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }

        .upload-wrapper input[type="file"] {
            display: none;
        }

        .upload-btn {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            border: none;
            font-family: 'Inter', sans-serif;
        }

        .upload-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .info-box {
            background: rgba(102, 126, 234, 0.06);
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .info-box p {
            font-size: 12px;
            color: #4a3f7a;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box p i {
            color: #667eea;
        }

        .kelas-help {
            font-size: 11px;
            color: #6c757d;
            margin-top: 4px;
            font-weight: 400;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 30px 20px;
                background: rgba(255, 255, 255, 0.40);
            }

            .bg-options {
                grid-template-columns: repeat(2, 1fr);
            }

            .change-bg-btn {
                bottom: 20px;
                right: 20px;
                padding: 12px 18px;
                font-size: 12px;
            }

            .change-bg-btn span {
                display: none;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(241, 241, 241, 0.5);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="background-wrapper" id="backgroundWrapper">
        <div class="overlay"></div>
        <img id="backgroundImage" src="images/default-bg.jpg" alt="Background" onerror="this.src='images/bg-default.jpg'">
    </div>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                    <span>Daftar</span>
                </div>
                <h2>Buat Akun Wali Kelas</h2>
                <p>Daftar untuk mulai menggunakan sistem absensi</p>
                <div class="role-info">
                    <i class="fas fa-info-circle"></i>
                    Pendaftaran khusus untuk Wali Kelas
                </div>
            </div>

            <form id="registerForm" class="register-form" action="proses_register.php" method="POST">
                <div class="form-group">
                    <label for="nama_lengkap">
                        <i class="fas fa-user"></i>
                        Nama Lengkap <span class="required">*</span>
                    </label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user-tag"></i>
                        Username <span class="required">*</span>
                    </label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" placeholder="Masukkan email" required>
                </div>

                <!-- PILIH KELAS -->
                <div class="form-group">
                    <label for="kelas">
                        <i class="fas fa-school"></i>
                        Kelas yang Diajar <span class="required">*</span>
                    </label>
                    <select id="kelas" name="kelas" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($daftar_kelas as $kelas): ?>
                            <option value="<?php echo htmlspecialchars($kelas); ?>">
                                <?php echo htmlspecialchars($kelas); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="kelas-help">
                        <i class="fas fa-info-circle"></i> Pilih kelas yang akan Anda wali. Data siswa yang akan tampil hanya dari kelas ini.
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Password <span class="required">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Minimal 6 karakter" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                    <div class="password-requirements">
                        <ul id="passwordRequirements">
                            <li class="invalid" id="reqLength"><i class="fas fa-circle"></i> Minimal 6 karakter</li>
                            <li class="invalid" id="reqLetter"><i class="fas fa-circle"></i> Mengandung huruf</li>
                            <li class="invalid" id="reqNumber"><i class="fas fa-circle"></i> Mengandung angka</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-check-circle"></i>
                        Konfirmasi Password <span class="required">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Konfirmasi password" required>
                        <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                </div>

                <!-- Informasi Role -->
                <div class="info-box">
                    <p>
                        <i class="fas fa-shield-alt"></i>
                        <span>Akun akan terdaftar sebagai <strong>Wali Kelas</strong> dan menunggu aktivasi oleh Admin</span>
                    </p>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    <i class="fas fa-user-plus"></i>
                    Daftar sebagai Wali Kelas
                </button>
            </form>

            <div class="login-link">
                <p>Sudah punya akun? <a href="login.php">Login sekarang</a></p>
                <p style="font-size: 12px; margin-top: 8px; opacity: 0.6; font-weight: 400;">
                    <i class="fas fa-lock" style="color: #667eea;"></i> Admin menggunakan akun khusus
                </p>
            </div>
        </div>
    </div>

    <!-- Tombol Ubah Background -->
    <button class="change-bg-btn" id="changeBgBtn">
        <i class="fas fa-image"></i>
        <span>Ubah Background</span>
    </button>

    <!-- Modal Ubah Background -->
    <div class="modal" id="bgModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-paint-brush"></i> Ubah Background</h3>
                <button class="close-btn" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Pilih background dari gambar di bawah atau upload sendiri:</p>
                <div class="bg-options" id="bgOptions">
                    <div class="bg-option" data-bg="images/bg1.jpg" style="background-image: url('images/bg1.jpg')"></div>
                    <div class="bg-option" data-bg="images/bg2.jpg" style="background-image: url('images/bg2.jpg')"></div>
                    <div class="bg-option" data-bg="images/bg3.jpg" style="background-image: url('images/bg3.jpg')"></div>
                    <div class="bg-option" data-bg="images/bg4.jpg" style="background-image: url('images/bg4.jpg')"></div>
                </div>
                <div class="upload-section">
                    <p>Atau upload gambar sendiri:</p>
                    <div class="upload-wrapper">
                        <input type="file" id="bgUpload" accept="image/*">
                        <label for="bgUpload" class="upload-btn">
                            <i class="fas fa-upload"></i>
                            Pilih Gambar
                        </label>
                    </div>
                    <p style="font-size: 12px; color: #6c757d; margin-top: 10px; font-weight: 500;">* Gambar akan tersimpan di penyimpanan lokal</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ========== REGISTER FORM ==========
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            // Password requirements validation
            const reqLength = document.getElementById('reqLength');
            const reqLetter = document.getElementById('reqLetter');
            const reqNumber = document.getElementById('reqNumber');

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length >= 6) {
                    reqLength.className = 'valid';
                    reqLength.innerHTML = '<i class="fas fa-check-circle"></i> Minimal 6 karakter';
                } else {
                    reqLength.className = 'invalid';
                    reqLength.innerHTML = '<i class="fas fa-circle"></i> Minimal 6 karakter';
                }
                
                if (/[a-zA-Z]/.test(password)) {
                    reqLetter.className = 'valid';
                    reqLetter.innerHTML = '<i class="fas fa-check-circle"></i> Mengandung huruf';
                } else {
                    reqLetter.className = 'invalid';
                    reqLetter.innerHTML = '<i class="fas fa-circle"></i> Mengandung huruf';
                }
                
                if (/\d/.test(password)) {
                    reqNumber.className = 'valid';
                    reqNumber.innerHTML = '<i class="fas fa-check-circle"></i> Mengandung angka';
                } else {
                    reqNumber.className = 'invalid';
                    reqNumber.innerHTML = '<i class="fas fa-circle"></i> Mengandung angka';
                }
            });

            registerForm.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const kelas = document.getElementById('kelas').value;
                
                if (kelas === '') {
                    e.preventDefault();
                    showAlert('Silakan pilih kelas yang akan diajar!', 'error');
                    return;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showAlert('Password dan konfirmasi password tidak cocok!', 'error');
                    return;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    showAlert('Password minimal 6 karakter!', 'error');
                    return;
                }
                
                registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendaftar...';
                registerBtn.disabled = true;
            });

            // ========== TOGGLE PASSWORD ==========
            document.getElementById('togglePassword').addEventListener('click', function() {
                togglePasswordVisibility(passwordInput, this);
            });

            document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                togglePasswordVisibility(confirmPasswordInput, this);
            });

            function togglePasswordVisibility(input, icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }

            // ========== MODAL BACKGROUND ==========
            const modal = document.getElementById('bgModal');
            const changeBgBtn = document.getElementById('changeBgBtn');
            const closeModal = document.getElementById('closeModal');
            const backgroundImage = document.getElementById('backgroundImage');

            changeBgBtn.addEventListener('click', function() {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });

            closeModal.addEventListener('click', function() {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    modal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });

            // ========== PILIH BACKGROUND ==========
            document.querySelectorAll('.bg-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.bg-option').forEach(opt => {
                        opt.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    const bgUrl = this.dataset.bg;
                    changeBackground(bgUrl);
                });
            });

            // ========== UPLOAD GAMBAR ==========
            document.getElementById('bgUpload').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        showAlert('Ukuran gambar maksimal 5MB!', 'error');
                        this.value = '';
                        return;
                    }
                    
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!validTypes.includes(file.type)) {
                        showAlert('Format gambar tidak didukung! Gunakan JPG, PNG, GIF, atau WEBP.', 'error');
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        changeBackground(event.target.result);
                        document.querySelectorAll('.bg-option').forEach(opt => {
                            opt.classList.remove('active');
                        });
                        setTimeout(() => {
                            modal.classList.remove('active');
                            document.body.style.overflow = 'auto';
                            showAlert('Background berhasil diubah!', 'success');
                        }, 300);
                    };
                    reader.onerror = function() {
                        showAlert('Gagal membaca file!', 'error');
                    };
                    reader.readAsDataURL(file);
                }
            });

            // ========== FUNGSI UBAH BACKGROUND ==========
            function changeBackground(imageUrl) {
                try {
                    localStorage.setItem('registerBackground', imageUrl);
                    
                    backgroundImage.style.opacity = '0';
                    setTimeout(() => {
                        backgroundImage.src = imageUrl;
                        backgroundImage.onload = function() {
                            backgroundImage.style.opacity = '1';
                        };
                        backgroundImage.onerror = function() {
                            backgroundImage.src = 'images/default-bg.jpg';
                            backgroundImage.style.opacity = '1';
                            showAlert('Gambar tidak ditemukan, menggunakan default', 'error');
                        };
                    }, 300);
                } catch (e) {
                    console.log('LocalStorage tidak tersedia');
                }
            }

            // ========== LOAD BACKGROUND TERSIMPAN ==========
            function loadSavedBackground() {
                try {
                    const savedBg = localStorage.getItem('registerBackground');
                    if (savedBg) {
                        backgroundImage.src = savedBg;
                        document.querySelectorAll('.bg-option').forEach(opt => {
                            if (opt.dataset.bg === savedBg) {
                                opt.classList.add('active');
                            }
                        });
                    }
                } catch (e) {
                    console.log('LocalStorage tidak tersedia');
                }
            }

            // ========== ALERT NOTIFICATION ==========
            function showAlert(message, type = 'info') {
                const oldAlert = document.querySelector('.custom-alert');
                if (oldAlert) oldAlert.remove();
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `custom-alert alert-${type}`;
                
                const iconMap = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    info: 'fa-info-circle'
                };
                
                alertDiv.innerHTML = `
                    <i class="fas ${iconMap[type] || iconMap.info}"></i>
                    <span>${message}</span>
                    <button class="alert-close">&times;</button>
                `;
                
                document.body.appendChild(alertDiv);
                
                alertDiv.querySelector('.alert-close').addEventListener('click', function() {
                    alertDiv.remove();
                });
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.style.opacity = '0';
                        alertDiv.style.transform = 'translateX(100px)';
                        alertDiv.style.transition = 'all 0.5s ease';
                        setTimeout(() => alertDiv.remove(), 500);
                    }
                }, 5000);
            }

            // ========== CEK PARAMETER URL ==========
            function checkUrlParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const error = urlParams.get('error');
                
                if (error) {
                    showAlert(decodeURIComponent(error), 'error');
                }
            }

            // ========== INISIALISASI ==========
            loadSavedBackground();
            checkUrlParams();

            console.log('🚀 Halaman Registrasi Wali Kelas siap!');
            console.log('💡 Isi form dengan data yang valid');
            console.log('🎨 Klik tombol "Ubah Background" untuk mengganti background');
            console.log('👨‍🏫 Pilih kelas yang akan diajar');
        });
    </script>
</body>
</html>