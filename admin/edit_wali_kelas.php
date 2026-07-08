<?php
// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Hanya admin yang bisa mengakses
if ($_SESSION['role'] !== 'admin') {
    header("Location: menu.php");
    exit();
}

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    die("Koneksi database gagal: " . ($conek->connect_error ?? "Koneksi tidak tersedia"));
}

// Ambil ID dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: admin_dashboard.php?error=ID tidak valid");
    exit();
}

// ===== AMBIL DATA WALI KELAS =====
$query = "SELECT * FROM wali_kelas WHERE id = ?";
$stmt = $conek->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: admin_dashboard.php?error=Data wali kelas tidak ditemukan");
    exit();
}

$wali = $result->fetch_assoc();
$stmt->close();

// ===== PROSES UPDATE =====
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi
    $errors = [];
    
    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap harus diisi!";
    }
    
    if (empty($username)) {
        $errors[] = "Username harus diisi!";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username minimal 3 karakter!";
    }
    
    if (empty($email)) {
        $errors[] = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid!";
    }
    
    // Validasi password jika diisi
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password minimal 6 karakter!";
        }
        if ($password !== $confirm_password) {
            $errors[] = "Password dan konfirmasi password tidak cocok!";
        }
    }

    if (empty($errors)) {
        // Cek apakah username/email sudah digunakan oleh wali kelas lain
        $check_query = "SELECT id FROM wali_kelas WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conek->prepare($check_query);
        $check_stmt->bind_param("ssi", $username, $email, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_error = "Username atau email sudah digunakan oleh wali kelas lain!";
        } else {
            // Cek juga di tabel admin
            $check_admin = "SELECT id FROM admin WHERE username = ? OR email = ?";
            $check_admin_stmt = $conek->prepare($check_admin);
            $check_admin_stmt->bind_param("ss", $username, $email);
            $check_admin_stmt->execute();
            $check_admin_result = $check_admin_stmt->get_result();
            
            if ($check_admin_result->num_rows > 0) {
                $update_error = "Username atau email sudah digunakan oleh admin!";
            } else {
                // Build query update
                if (!empty($password)) {
                    // Update dengan password baru
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE wali_kelas SET 
                            username = ?, 
                            email = ?, 
                            password = ?, 
                            nama_lengkap = ?, 
                            status = ? 
                            WHERE id = ?";
                    $stmt = $conek->prepare($sql);
                    $stmt->bind_param("sssssi", $username, $email, $hashed_password, $nama_lengkap, $status, $id);
                } else {
                    // Update tanpa password
                    $sql = "UPDATE wali_kelas SET 
                            username = ?, 
                            email = ?, 
                            nama_lengkap = ?, 
                            status = ? 
                            WHERE id = ?";
                    $stmt = $conek->prepare($sql);
                    $stmt->bind_param("ssssi", $username, $email, $nama_lengkap, $status, $id);
                }
                
                if ($stmt->execute()) {
                    // Redirect langsung ke dashboard dengan parameter success
                    header("Location: admin_dashboard.php?success=updated");
                    exit();
                } else {
                    $update_error = "Gagal mengupdate data: " . $stmt->error;
                }
                $stmt->close();
            }
            $check_admin_stmt->close();
        }
        $check_stmt->close();
    } else {
        $update_error = implode("\n", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Wali Kelas - Absensi RFID</title>
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
            position: relative;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            display: flex;
            font-size: 12px;
        }

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

        /* ===== HEADER MODERN ===== */
        .header-modern {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0px 0px 14px 14px;
            padding: 1.2rem 1.8rem;
            margin-bottom: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.08);
            position: relative;
            overflow: hidden;
            z-index: 2;
        }

        .header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #fcd34d, #fbbf24, #f59e0b);
            background-size: 200% 100%;
            animation: gradientMove 4s linear infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .header-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.10));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid rgba(245, 158, 11, 0.15);
        }

        .header-text h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .header-text .sub {
            font-size: 0.7rem;
            color: #555577;
            font-weight: 400;
            margin-top: 0.05rem;
        }

        .header-stats {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.4rem 1.2rem;
            border-radius: 10px;
            border: 1px solid rgba(72, 49, 212, 0.08);
        }

        .stat-chip {
            text-align: center;
        }

        .stat-chip .number {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a2e;
            display: block;
            line-height: 1.2;
        }

        .stat-chip .label {
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #666688;
            font-weight: 600;
        }

        /* ===== EDIT FORM ===== */
        .edit-container {
            max-width: 700px;
            margin: 0 auto 1.5rem auto;
            padding: 0 1.5rem;
        }

        .edit-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 14px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
            transition: all 0.3s ease;
        }

        .edit-card:hover {
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.10);
        }

        .edit-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 2px solid rgba(245, 158, 11, 0.15);
        }

        .edit-title i {
            color: #f59e0b;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            color: #1a1a2e;
            font-weight: 600;
            font-size: 0.75rem;
            margin-bottom: 5px;
        }

        .form-group label i {
            color: #f59e0b;
            margin-right: 6px;
            width: 16px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-family: 'Poppins', sans-serif;
            color: #1a1a2e;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
        }

        .form-group input:disabled {
            background: rgba(0, 0, 0, 0.04);
            cursor: not-allowed;
        }

        .form-group .password-wrapper {
            position: relative;
        }

        .form-group .password-wrapper input {
            padding-right: 40px;
        }

        .form-group .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .form-group .toggle-password:hover {
            color: #f59e0b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
            box-shadow: 0 2px 12px rgba(245, 158, 11, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(245, 158, 11, 0.35);
        }

        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.06);
            color: #555577;
        }

        .btn-secondary:hover {
            background: rgba(0, 0, 0, 0.10);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: rgba(220, 38, 38, 0.10);
            color: #dc2626;
        }

        .btn-danger:hover {
            background: rgba(220, 38, 38, 0.20);
            transform: translateY(-2px);
        }

        /* ===== ALERT ===== */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            padding: 14px 22px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            animation: slideDown 0.5s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
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
            font-size: 18px;
            cursor: pointer;
            padding: 0 0 0 10px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .custom-alert .alert-close:hover {
            opacity: 1;
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.95);
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.95);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.95);
        }

        /* ===== INFO BOX ===== */
        .info-box {
            background: rgba(245, 158, 11, 0.06);
            border: 1px solid rgba(245, 158, 11, 0.12);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            color: #f59e0b;
            font-size: 1rem;
        }

        .info-box p {
            font-size: 0.7rem;
            color: #555577;
            margin: 0;
        }

        .info-box p strong {
            color: #1a1a2e;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container-fluid {
                padding: 0;
            }

            .header-modern {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
                margin: 0.8rem;
            }

            .header-stats {
                width: 100%;
                justify-content: space-around;
                padding: 0.4rem 0.8rem;
            }

            .edit-container {
                padding: 0 0.8rem;
            }

            .edit-card {
                padding: 1.2rem;
            }
        }

        @media (max-width: 600px) {
            body {
                font-size: 11px;
            }

            .header-text h1 {
                font-size: 1rem;
            }

            .header-stats {
                gap: 0.3rem;
                flex-wrap: wrap;
            }

            .stat-chip .number {
                font-size: 0.85rem;
            }

            .header-icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .header-modern {
                padding: 0.7rem;
                margin: 0.4rem;
            }

            .edit-card {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .edit-title {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR FROM MENU.PHP ===== -->
<?php include "menu.php"; ?>

<!-- ===== MAIN CONTENT ===== -->
<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">✏️</div>
            <div class="header-text">
                <h1>Edit Wali Kelas</h1>
                <div class="sub">Edit data wali kelas yang terdaftar</div>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-chip">
                <span class="number">
                    <?php echo date('d'); ?>
                </span>
                <span class="label">Tanggal</span>
            </div>
            <div class="stat-chip" style="border-left:1px solid rgba(72,49,212,0.10); padding-left:1rem;">
                <span class="number">
                    <?php echo date('F Y'); ?>
                </span>
                <span class="label">Bulan</span>
            </div>
        </div>
    </div>

    <!-- ===== EDIT FORM ===== -->
    <div class="edit-container">
        <div class="edit-card">
            <div class="edit-title">
                <i class="fas fa-chalkboard-teacher"></i>
                Edit Data Wali Kelas
                <span style="margin-left:auto; font-size:0.65rem; font-weight:400; color:#6c757d;">
                    ID: #<?php echo $wali['id']; ?>
                </span>
            </div>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>
                    <strong>Informasi:</strong> 
                    Kosongkan field password jika tidak ingin mengubah password.
                    Status <strong>active</strong> berarti akun dapat login, 
                    <strong>pending</strong> menunggu aktivasi, 
                    <strong>inactive</strong> tidak dapat login.
                </p>
            </div>

            <form id="editForm" method="POST" action="">
                <input type="hidden" name="action" value="update">

                <div class="form-group">
                    <label for="nama_lengkap"><i class="fas fa-user"></i> Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" 
                           value="<?php echo htmlspecialchars($wali['nama_lengkap'] ?? ''); ?>" 
                           placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="form-group">
                    <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?php echo htmlspecialchars($wali['username']); ?>" 
                           placeholder="Masukkan username" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($wali['email']); ?>" 
                           placeholder="Masukkan email" required>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password Baru</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" 
                               placeholder="Kosongkan jika tidak ingin mengubah password">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
                    </div>
                    <small style="font-size:0.6rem; color:#6c757d; display:block; margin-top:3px;">
                        <i class="fas fa-info-circle"></i> Minimal 6 karakter, hanya diisi jika ingin mengganti password
                    </small>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-check-circle"></i> Konfirmasi Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Konfirmasi password baru">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status">
                            <option value="pending" <?php echo $wali['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo $wali['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $wali['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <div style="display:flex; gap:0.5rem; width:100%;">
                            <button type="submit" class="btn btn-primary" id="submitBtn" style="flex:1;">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
// ===== TOGGLE PASSWORD =====
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
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

// ===== FORM VALIDATION =====
document.getElementById('editForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const submitBtn = document.getElementById('submitBtn');
    
    // Jika password diisi, validasi
    if (password !== '') {
        if (password !== confirm) {
            e.preventDefault();
            showAlert('Password dan konfirmasi password tidak cocok!', 'error');
            return;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            showAlert('Password minimal 6 karakter!', 'error');
            return;
        }
    }
    
    // Tampilkan loading pada tombol
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
});

// ===== ALERT NOTIFICATION =====
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

// ===== TAMPILKAN ALERT ERROR DARI PHP =====
<?php if (!empty($update_error)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showAlert('<?php echo addslashes($update_error); ?>', 'error');
    });
<?php endif; ?>

console.log('✏️ Edit Wali Kelas siap!');
console.log('📝 Mengedit data wali kelas ID: <?php echo $wali['id']; ?>');
console.log('👨‍🏫 Nama: <?php echo htmlspecialchars($wali['nama_lengkap'] ?? ''); ?>');
</script>

</body>
</html>