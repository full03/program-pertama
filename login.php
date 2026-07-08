<?php
session_start();

// Jika sudah login, redirect berdasarkan role
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'wali_kelas') {
        header("Location: wali_kelas/wali_kelas_dashboard.php");
        exit();
    } else {
        session_destroy();
        header("Location: login.php?error=invalid_role");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Style tambahan untuk halaman login */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            background: #f0f2f5;
        }

        .container {
            position: relative;
            width: 100%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Background Wrapper - CERAH dan TANPA SAMAR */
        .background-wrapper {
            position: absolute;
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
            transition: all 0.5s ease;
            filter: brightness(0.95) saturate(1.2);
        }

        /* Overlay - SANGAT TIPIS, hanya untuk sedikit efek */
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

        /* Login Wrapper */
        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: slideUp 0.6s ease;
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

        /* Login Card - SAMAR / TRANSPARAN dengan efek kaca */
        .login-card {
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

        .login-card:hover {
            background: rgba(255, 255, 255, 0.40);
            box-shadow: 
                0 12px 48px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transform: translateY(-3px);
        }

        /* Login Header - Lebih terang agar terbaca */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
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

        .login-header h2 {
            color: #1a1a2e;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 
                0 2px 8px rgba(255, 255, 255, 0.8),
                0 1px 4px rgba(255, 255, 255, 0.6);
        }

        .login-header p {
            color: #2d3436;
            font-size: 15px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.8),
                0 1px 3px rgba(255, 255, 255, 0.5);
        }

        .login-header .role-info {
            font-size: 12px;
            color: #666688;
            text-align: center;
            margin-top: 8px;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.08);
            padding: 6px 14px;
            border-radius: 20px;
            display: inline-block;
            border: 1px solid rgba(102, 126, 234, 0.10);
        }

        .login-header .role-info i {
            color: #667eea;
            margin-right: 4px;
        }

        /* Login Form */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        .form-group input {
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
        }

        .form-group input::placeholder {
            color: rgba(26, 26, 46, 0.5);
            font-weight: 400;
        }

        .form-group input:focus {
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

        /* Form Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1a1a2e;
            cursor: pointer;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .forgot-password:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Login Button */
        .login-btn {
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
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.35);
            letter-spacing: 0.5px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.45);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Login Footer */
        .login-footer {
            margin-top: 30px;
            text-align: center;
        }

        .login-footer p {
            color: #1a1a2e;
            font-size: 14px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .register-link {
            margin-top: 15px;
        }

        .register-link p small {
            font-size: 12px;
            opacity: 0.7;
            display: block;
            margin-top: 5px;
            font-weight: 400;
        }

        /* Tombol Ubah Background */
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

        /* Modal */
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

        /* Custom Alert */
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

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
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

            .login-header h2 {
                font-size: 24px;
            }
        }

        /* Scrollbar */
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
    <div class="container">
        <!-- Background CERAH dengan overlay sangat tipis -->
        <div class="background-wrapper" id="backgroundWrapper">
            <div class="overlay"></div>
            <img id="backgroundImage" src="images/default-bg.jpg" alt="Background" onerror="this.src='images/bg-default.jpg'">
        </div>

        <!-- Konten Login - DIBUAT SAMAR -->
        <div class="login-wrapper">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo">
                        <i class="fas fa-school"></i>
                        <span>Absensi</span>
                    </div>
                    <h2>Selamat Datang</h2>
                    <p>Silakan login untuk melanjutkan</p>
                    <div class="role-info">
                        <i class="fas fa-info-circle"></i>
                        Login sebagai Admin atau Wali Kelas
                    </div>
                </div>

                <!-- FORM LOGIN - action ke proses_login.php -->
                <form id="loginForm" class="login-form" action="proses_login.php" method="POST">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Username / Email
                        </label>
                        <input type="text" id="username" name="username" 
                               placeholder="Masukkan username" 
                               value="<?php echo isset($_COOKIE['remember_username']) ? htmlspecialchars($_COOKIE['remember_username']) : ''; ?>"
                               autofocus
                               required>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" id="rememberMe" 
                                   <?php echo isset($_COOKIE['remember_username']) ? 'checked' : ''; ?>>
                            <span>Ingat saya</span>
                        </label>
                        <a href="lupa_password.php" class="forgot-password">Lupa password?</a>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                </form>

                <div class="login-footer">
                    <div class="register-link">
                        <p>Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
                        <p><small>* Pendaftaran untuk Wali Kelas akan diproses oleh admin</small></p>
                    </div>
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ========== LOGIN FORM ==========
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Auto-focus username jika tidak ada cookie
            if (!usernameInput.value) {
                usernameInput.focus();
            }

            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                // Validasi client-side
                if (username === '' || password === '') {
                    e.preventDefault();
                    showAlert('Mohon isi username dan password!', 'error');
                    return;
                }

                // Tampilkan loading
                loginBtn.innerHTML = '<span class="spinner"></span> Memproses...';
                loginBtn.disabled = true;
            });

            // ========== TOGGLE PASSWORD ==========
            document.getElementById('togglePassword').addEventListener('click', function() {
                const input = document.getElementById('password');
                const icon = this;
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            // ========== MODAL BACKGROUND ==========
            const modal = document.getElementById('bgModal');
            const changeBgBtn = document.getElementById('changeBgBtn');
            const closeModal = document.getElementById('closeModal');
            const backgroundImage = document.getElementById('backgroundImage');

            // Buka modal
            changeBgBtn.addEventListener('click', function() {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });

            // Tutup modal
            closeModal.addEventListener('click', function() {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            // Tutup modal dengan klik di luar
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
                    // Validasi ukuran file (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        showAlert('Ukuran gambar maksimal 5MB!', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Validasi tipe file
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
                    localStorage.setItem('loginBackground', imageUrl);
                    
                    backgroundImage.style.opacity = '0';
                    setTimeout(() => {
                        backgroundImage.src = imageUrl;
                        backgroundImage.onload = function() {
                            backgroundImage.style.opacity = '1';
                        };
                        backgroundImage.onerror = function() {
                            // Jika gambar gagal dimuat, gunakan default
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
                    const savedBg = localStorage.getItem('loginBackground');
                    if (savedBg) {
                        backgroundImage.src = savedBg;
                        // Cek apakah savedBg ada di opsi
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

            // ========== TAMPILKAN PESAN ERROR DARI URL ==========
            function checkUrlParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const error = urlParams.get('error');
                const success = urlParams.get('success');
                const message = urlParams.get('message');
                
                if (error) {
                    const messages = {
                        'empty': 'Username dan password harus diisi!',
                        'invalid': 'Username atau password salah!',
                        'not_found': 'Akun tidak ditemukan!',
                        'inactive': 'Akun Anda tidak aktif! Silahkan hubungi admin.',
                        'pending': 'Akun Anda masih menunggu aktivasi oleh admin! Silahkan tunggu konfirmasi dari admin.',
                        'session': 'Sesi login telah berakhir, silakan login ulang.',
                        'invalid_role': 'Role tidak dikenali!',
                        'invalid_status': 'Status akun tidak valid!',
                        'method': 'Metode request tidak valid!'
                    };
                    const errorMsg = messages[error] || 'Terjadi kesalahan!';
                    showAlert(errorMsg, 'error');
                    
                    // Reset button jika ada error
                    loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
                    loginBtn.disabled = false;
                }
                
                if (success) {
                    if (success === 'logout') {
                        showAlert('Anda telah berhasil logout.', 'success');
                    }
                    if (success === 'registered') {
                        showAlert(message || 'Pendaftaran berhasil! Silahkan login setelah akun diaktivasi oleh admin.', 'success');
                    }
                    if (success === 'login') {
                        // Login berhasil, tidak perlu alert karena sudah redirect
                    }
                }
            }

            // ========== INISIALISASI ==========
            loadSavedBackground();
            checkUrlParams();

            // Keyboard shortcut: Enter untuk submit
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const activeElement = document.activeElement;
                    if (activeElement && (activeElement.id === 'username' || activeElement.id === 'password')) {
                        e.preventDefault();
                        loginForm.dispatchEvent(new Event('submit'));
                    }
                }
            });

            // ========== RESET BUTTON SAAT PAGE LOAD ==========
            // Jika ada error, reset button
            if (window.location.search.includes('error')) {
                loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
                loginBtn.disabled = false;
            }

            console.log('🚀 Sistem Absensi siap!');
            console.log('💡 Gunakan akun yang sudah terdaftar untuk login');
            console.log('🎨 Klik tombol "Ubah Background" untuk mengganti background');
            console.log('👨‍🏫 Wali Kelas: login setelah diaktivasi oleh admin');
            console.log('👨‍💼 Admin: login langsung setelah registrasi');
        });
    </script>
</body>
</html>