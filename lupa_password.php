<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'wali_kelas') {
        header("Location: wali_kelas/wali_kelas_dashboard.php");
        exit();
    }
}

// Koneksi ke database
require_once 'koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

$message = '';
$message_type = '';
$success = false;
$user_id = null;
$user_table = null;

// ===== PROSES RESET PASSWORD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_otp') {
        // Kirim OTP
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $message = 'Email harus diisi!';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Format email tidak valid!';
            $message_type = 'error';
        } else {
            // Cek di tabel admin
            $check_admin = "SELECT id, username, nama_lengkap FROM admin WHERE email = ?";
            $stmt_admin = $conek->prepare($check_admin);
            $stmt_admin->bind_param("s", $email);
            $stmt_admin->execute();
            $result_admin = $stmt_admin->get_result();
            
            if ($result_admin->num_rows > 0) {
                $user = $result_admin->fetch_assoc();
                $user_id = $user['id'];
                $user_table = 'admin';
                $nama = $user['nama_lengkap'] ?? $user['username'];
            } else {
                // Cek di tabel wali_kelas
                $stmt_admin->close();
                $check_wali = "SELECT id, username, nama_lengkap FROM wali_kelas WHERE email = ?";
                $stmt_wali = $conek->prepare($check_wali);
                $stmt_wali->bind_param("s", $email);
                $stmt_wali->execute();
                $result_wali = $stmt_wali->get_result();
                
                if ($result_wali->num_rows > 0) {
                    $user = $result_wali->fetch_assoc();
                    $user_id = $user['id'];
                    $user_table = 'wali_kelas';
                    $nama = $user['nama_lengkap'] ?? $user['username'];
                }
                $stmt_wali->close();
            }
            $stmt_admin->close();
            
            if ($user_id && $user_table) {
                // Generate OTP 6 digit
                $otp = sprintf("%06d", mt_rand(0, 999999));
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Simpan OTP ke database (perlu tabel reset_password)
                // Jika tabel reset_password belum ada, buat dulu
                $table_exists = $conek->query("SHOW TABLES LIKE 'reset_password'");
                if ($table_exists->num_rows == 0) {
                    // Buat tabel reset_password
                    $create_table = "CREATE TABLE IF NOT EXISTS reset_password (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        user_table VARCHAR(50) NOT NULL,
                        otp VARCHAR(6) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        used BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    $conek->query($create_table);
                }
                
                // Simpan OTP
                $save_otp = "INSERT INTO reset_password (user_id, user_table, otp, expires_at) VALUES (?, ?, ?, ?)";
                $stmt_otp = $conek->prepare($save_otp);
                $stmt_otp->bind_param("isss", $user_id, $user_table, $otp, $expiry);
                
                if ($stmt_otp->execute()) {
                    // Kirim OTP via email (simulasi)
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user_id;
                    $_SESSION['reset_user_table'] = $user_table;
                    
                    $message = "Kode OTP telah dikirim ke email $email. Kode OTP: <strong>$otp</strong>";
                    $message_type = 'success';
                    $success = true;
                    
                    // Dalam produksi, kirim email asli
                    // mail($email, "Kode Reset Password", "Kode OTP Anda: $otp", "From: noreply@absensi.com");
                } else {
                    $message = 'Gagal menyimpan OTP: ' . $stmt_otp->error;
                    $message_type = 'error';
                }
                $stmt_otp->close();
            } else {
                $message = 'Email tidak ditemukan!';
                $message_type = 'error';
            }
        }
        
    } elseif ($action === 'verify_otp') {
        // Verifikasi OTP
        $otp = trim($_POST['otp'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';
        $user_id = $_SESSION['reset_user_id'] ?? 0;
        $user_table = $_SESSION['reset_user_table'] ?? '';
        
        if (empty($otp)) {
            $message = 'Kode OTP harus diisi!';
            $message_type = 'error';
        } elseif (empty($email) || empty($user_id) || empty($user_table)) {
            $message = 'Session expired! Silakan ulangi proses.';
            $message_type = 'error';
        } else {
            // Cek OTP
            $check_otp = "SELECT * FROM reset_password WHERE user_id = ? AND user_table = ? AND otp = ? AND used = FALSE AND expires_at > NOW() ORDER BY id DESC LIMIT 1";
            $stmt_check = $conek->prepare($check_otp);
            $stmt_check->bind_param("iss", $user_id, $user_table, $otp);
            $stmt_check->execute();
            $result_otp = $stmt_check->get_result();
            
            if ($result_otp->num_rows > 0) {
                $otp_data = $result_otp->fetch_assoc();
                
                // Tandai OTP sudah digunakan
                $update_otp = "UPDATE reset_password SET used = TRUE WHERE id = ?";
                $stmt_update = $conek->prepare($update_otp);
                $stmt_update->bind_param("i", $otp_data['id']);
                $stmt_update->execute();
                $stmt_update->close();
                
                $_SESSION['reset_verified'] = true;
                $message = 'OTP berhasil diverifikasi! Silakan buat password baru.';
                $message_type = 'success';
                $success = true;
            } else {
                $message = 'Kode OTP tidak valid atau sudah kadaluarsa!';
                $message_type = 'error';
            }
            $stmt_check->close();
        }
        
    } elseif ($action === 'reset_password') {
        // Reset password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
            $message = 'Silakan verifikasi OTP terlebih dahulu!';
            $message_type = 'error';
        } elseif (empty($new_password)) {
            $message = 'Password baru harus diisi!';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password minimal 6 karakter!';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Password dan konfirmasi password tidak cocok!';
            $message_type = 'error';
        } else {
            $user_id = $_SESSION['reset_user_id'] ?? 0;
            $user_table = $_SESSION['reset_user_table'] ?? '';
            
            if (empty($user_id) || empty($user_table)) {
                $message = 'Session expired! Silakan ulangi proses.';
                $message_type = 'error';
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass = "UPDATE $user_table SET password = ? WHERE id = ?";
                $stmt_update = $conek->prepare($update_pass);
                $stmt_update->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt_update->execute()) {
                    $message = 'Password berhasil direset! Silakan login dengan password baru.';
                    $message_type = 'success';
                    $success = true;
                    
                    // Clear session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_user_table']);
                    unset($_SESSION['reset_verified']);
                } else {
                    $message = 'Gagal mereset password: ' . $stmt_update->error;
                    $message_type = 'error';
                }
                $stmt_update->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Sistem Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 480px;
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

        .card {
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

        .card:hover {
            background: rgba(255, 255, 255, 0.40);
            box-shadow: 
                0 12px 48px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            transform: translateY(-3px);
        }

        .header {
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

        .header h2 {
            color: #1a1a2e;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            text-shadow: 
                0 2px 8px rgba(255, 255, 255, 0.8),
                0 1px 4px rgba(255, 255, 255, 0.6);
        }

        .header p {
            color: #2d3436;
            font-size: 15px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.8),
                0 1px 3px rgba(255, 255, 255, 0.5);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            color: #1a1a2e;
            font-weight: 700;
            font-size: 14px;
            display: block;
            margin-bottom: 6px;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .form-group label i {
            color: #667eea;
            margin-right: 8px;
        }

        .form-group input {
            width: 100%;
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

        .btn {
            width: 100%;
            padding: 14px;
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
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.45);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-success {
            background: linear-gradient(135deg, #00b894, #00a381);
            color: white;
            box-shadow: 0 4px 20px rgba(0, 184, 148, 0.35);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 184, 148, 0.45);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.06);
            color: #555577;
        }

        .btn-secondary:hover {
            background: rgba(0, 0, 0, 0.10);
            transform: translateY(-2px);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .back-link p {
            color: #1a1a2e;
            font-size: 14px;
            font-weight: 600;
            text-shadow: 
                0 2px 6px rgba(255, 255, 255, 0.9),
                0 1px 3px rgba(255, 255, 255, 0.7);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
            border: 1px solid rgba(0, 184, 148, 0.2);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.15);
            color: #2980b9;
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .alert i {
            font-size: 20px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .step {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .step.active {
            background: #667eea;
            transform: scale(1.2);
        }

        .step.done {
            background: #00b894;
        }

        .hidden {
            display: none !important;
        }

        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .otp-input-group input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            padding: 0;
        }

        @media (max-width: 480px) {
            .card {
                padding: 30px 20px;
                background: rgba(255, 255, 255, 0.40);
            }

            .otp-input-group input {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="background-wrapper" id="backgroundWrapper">
        <div class="overlay"></div>
        <img id="backgroundImage" src="images/default-bg.jpg" alt="Background" onerror="this.src='images/bg-default.jpg'">
    </div>

    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <i class="fas fa-key"></i>
                    <span>Reset Password</span>
                </div>
                <h2>Lupa Password</h2>
                <p>Reset password akun Anda</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($success && $message_type === 'success' && $_POST['action'] === 'reset_password'): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary" style="text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Kembali ke Login
                    </a>
                </div>
            <?php else: ?>

            <!-- Step 1: Kirim Email -->
            <div id="step1" class="<?php echo (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) ? 'hidden' : ''; ?>">
                <div class="step-indicator">
                    <div class="step active"></div>
                    <div class="step <?php echo (isset($_SESSION['reset_email'])) ? 'done' : ''; ?>"></div>
                    <div class="step <?php echo (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) ? 'done' : ''; ?>"></div>
                </div>
                <p style="text-align: center; color: #6c757d; font-size: 13px; margin-bottom: 20px;">
                    Masukkan email untuk menerima kode OTP
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_otp">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" placeholder="Masukkan email Anda" required 
                               value="<?php echo isset($_SESSION['reset_email']) ? htmlspecialchars($_SESSION['reset_email']) : ''; ?>"
                               <?php echo isset($_SESSION['reset_email']) ? 'readonly' : ''; ?>>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Kirim OTP
                    </button>
                </form>
            </div>

            <!-- Step 2: Verifikasi OTP -->
            <div id="step2" class="<?php echo (!isset($_SESSION['reset_email']) || (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true)) ? 'hidden' : ''; ?>">
                <div class="step-indicator">
                    <div class="step done"></div>
                    <div class="step active"></div>
                    <div class="step"></div>
                </div>
                <p style="text-align: center; color: #6c757d; font-size: 13px; margin-bottom: 20px;">
                    Masukkan kode OTP yang dikirim ke email Anda
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="form-group">
                        <label><i class="fas fa-shield-alt"></i> Kode OTP</label>
                        <input type="text" name="otp" placeholder="Masukkan kode OTP 6 digit" required maxlength="6" pattern="[0-9]{6}">
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Verifikasi OTP
                    </button>
                </form>
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="location.reload()" class="btn btn-secondary" style="width: auto; padding: 8px 20px; font-size: 13px;">
                        <i class="fas fa-redo"></i> Kirim Ulang OTP
                    </button>
                </div>
            </div>

            <!-- Step 3: Reset Password -->
            <div id="step3" class="<?php echo (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) ? 'hidden' : ''; ?>">
                <div class="step-indicator">
                    <div class="step done"></div>
                    <div class="step done"></div>
                    <div class="step active"></div>
                </div>
                <p style="text-align: center; color: #6c757d; font-size: 13px; margin-bottom: 20px;">
                    Buat password baru untuk akun Anda
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password Baru</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" placeholder="Minimal 6 karakter" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword(this)"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-check-circle"></i> Konfirmasi Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" placeholder="Konfirmasi password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword(this)"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
            </div>

            <?php endif; ?>

            <div class="back-link">
                <p><a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a></p>
            </div>
        </div>
    </div>

    <script>
        // ===== TOGGLE PASSWORD =====
        function togglePassword(element) {
            const input = element.parentElement.querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                element.classList.remove('fa-eye');
                element.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                element.classList.remove('fa-eye-slash');
                element.classList.add('fa-eye');
            }
        }

        // ===== OTP INPUT AUTO-FOCUS =====
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input-group input');
            if (otpInputs.length > 0) {
                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', function() {
                        if (this.value.length === 1 && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }
                    });
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                            otpInputs[index - 1].focus();
                        }
                    });
                });
            }

            // Auto-focus first input
            const firstInput = document.querySelector('input:not([readonly])');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // ===== BACKGROUND =====
        function loadSavedBackground() {
            try {
                const savedBg = localStorage.getItem('loginBackground');
                if (savedBg) {
                    document.getElementById('backgroundImage').src = savedBg;
                }
            } catch (e) {
                console.log('LocalStorage tidak tersedia');
            }
        }
        loadSavedBackground();

        console.log('🔑 Halaman Lupa Password siap!');
        console.log('📧 Masukkan email untuk reset password');
    </script>
</body>
</html>