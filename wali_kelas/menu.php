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

// Ambil data user dari session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Jika user_id ada dan role wali_kelas, ambil data dari tabel wali_kelas
if ($user_id > 0 && $user_role === 'wali_kelas') {
    $query = "SELECT id, username, nama_lengkap, email, foto_profil, status FROM wali_kelas WHERE id = ?";
    $stmt = $conek->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['status'] = $user['status'] ?? 'active';
            
            // Handle foto profil
            if (!empty($user['foto_profil']) && file_exists($user['foto_profil'])) {
                $_SESSION['foto_profil'] = $user['foto_profil'];
            } else {
                $_SESSION['foto_profil'] = '../images/default-user.png';
            }
        }
        $stmt->close();
    }
}

// Set variabel untuk ditampilkan
$nama_user = isset($_SESSION['nama_lengkap']) && !empty($_SESSION['nama_lengkap']) 
    ? $_SESSION['nama_lengkap'] 
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Wali Kelas');
    
$foto_user = isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil']) 
    ? $_SESSION['foto_profil'] 
    : '../images/default-user.png';
    
$role_user = 'Wali Kelas'; // Fixed role untuk wali kelas
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Wali Kelas - Absensi RFID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- ===== TAMBAHKAN SweetAlert2 ===== -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            transition: all 0.3s ease;
        }

        /* ===== SIDEBAR UTAMA - STABIL ===== */
        .sidebar {
            width: 170px;
            min-width: 170px;
            background: linear-gradient(180deg, #007bff, #0056b3);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: width 0.3s ease;
            overflow-x: visible;
            overflow-y: auto;
            z-index: 1000;
            flex-shrink: 0;
        }

        .sidebar .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 8px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            padding: 0 10px;
        }

        .sidebar .brand img {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 6px rgba(255,255,255,0.7);
            border: 2px solid rgba(255,255,255,0.4);
            transition: all 0.3s ease;
        }

        .sidebar .brand span {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 3px;
            transition: all 0.3s ease;
            text-align: center;
            line-height: 1.2;
        }

        .sidebar .brand::after {
            content: "";
            display: block;
            width: 80%;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 1px;
            margin-top: 6px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            width: 100%;
            margin-top: 8px;
            flex: 1;
        }

        .sidebar ul li {
            width: 100%;
            position: relative;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            white-space: nowrap;
            cursor: pointer;
            font-size: 0.78rem;
        }

        .sidebar ul li a:hover {
            background: rgba(255,255,255,0.15);
            border-left: 3px solid #fff;
        }

        .sidebar ul li a.active {
            background: rgba(255,255,255,0.20);
            border-left: 3px solid #ffc107;
        }

        .sidebar ul li a .menu-label {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar ul li a .menu-label i {
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .sidebar span {
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .menu-item {
            position: relative;
        }

        .submenu {
            display: none;
            flex-direction: column;
            gap: 3px;
            padding: 4px 0 6px 0;
            margin-left: 8px;
            margin-top: 2px;
            animation: slideDown 0.3s ease;
        }

        .submenu.show {
            display: flex;
        }

        .submenu-btn {
            background: rgba(255,255,255,0.10);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .submenu-btn:hover {
            background: rgba(255,255,255,0.25);
            padding-left: 15px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #absenArrow {
            transition: .3s;
            font-size: 10px;
        }

        #absenArrow.rotate {
            transform: rotate(180deg);
        }

        /* ===== USER PROFILE - DITAMBAHKAN CSS UNTUK EDIT PROFIL ===== */
        .user-profile {
            width: 100%;
            margin-top: auto;
            padding: 10px 0;
            border-top: 2px solid rgba(255,255,255,0.15);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .user-profile .user-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 0 10px;
            width: 100%;
            cursor: pointer; /* Tambahkan cursor pointer */
            transition: all 0.3s ease;
        }

        .user-profile .user-info:hover {
            transform: scale(1.02);
        }

        .user-profile .user-info:hover .user-avatar {
            border-color: #ffc107;
            box-shadow: 0 0 15px rgba(255, 193, 7, 0.4);
        }

        .user-profile .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 4px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* ===== TAMBAHKAN Efek overlay pada avatar ===== */
        .user-profile .user-avatar::after {
            content: "✎";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            opacity: 0;
            transition: all 0.3s ease;
            border-radius: 50%;
        }

        .user-profile .user-info:hover .user-avatar::after {
            opacity: 1;
        }

        .user-profile .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-profile .user-avatar .role-badge {
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: #ffc107;
            color: #000;
            font-size: 8px;
            padding: 1px 5px;
            border-radius: 10px;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .user-profile .user-name {
            font-weight: 600;
            font-size: 14px;
            margin: 0;
            line-height: 1.2;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-profile .user-email {
            font-size: 9px;
            opacity: 0.7;
            margin: 1px 0 0 0;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-profile .user-role {
            font-size: 9px;
            opacity: 0.8;
            margin: 0;
            background: rgba(255,255,255,0.12);
            padding: 1px 10px;
            border-radius: 10px;
            display: inline-block;
        }

        .user-profile .logout-btn {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 4px 14px;
            border-radius: 16px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
            text-decoration: none;
        }

        .user-profile .logout-btn:hover {
            background: rgba(255,0,0,0.3);
            border-color: rgba(255,0,0,0.5);
            transform: scale(1.05);
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 170px;
            padding: 0;
            width: calc(100% - 170px);
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s ease;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                min-width: 60px;
            }
            
            .sidebar .brand img {
                width: 40px;
                height: 40px;
            }
            
            .sidebar .brand span {
                font-size: 0;
            }
            
            .sidebar .brand span::after {
                content: "MTs";
                font-size: 10px;
                display: block;
            }
            
            .sidebar ul li a span {
                font-size: 0;
            }
            
            .sidebar ul li a .menu-label i {
                font-size: 16px;
            }

            .sidebar ul li a {
                padding: 10px 8px;
                justify-content: center;
            }

            .sidebar ul li a .menu-label {
                gap: 0;
            }

            .sidebar .user-profile .user-name,
            .sidebar .user-profile .user-email,
            .sidebar .user-profile .user-role {
                font-size: 0;
                max-width: 0;
            }
            
            .sidebar .user-profile .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }
            
            .sidebar .user-profile .user-avatar .role-badge {
                font-size: 6px;
                padding: 1px 3px;
                bottom: -3px;
                right: -3px;
            }
            
            .sidebar .user-profile .logout-btn span {
                display: none;
            }
            
            .sidebar .user-profile .logout-btn {
                padding: 4px 8px;
            }
            
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
            }

            .submenu {
                margin-left: 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .sidebar {
                width: 0;
                min-width: 0;
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
    <div class="brand">
        <a href="wali_kelas_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'wali_kelas_dashboard.php' ? 'active' : ''; ?>">
        <img src="../images/logo.png" alt="Logo" onerror="this.src='../images/default-logo.png'">
        </a>
        <span>ABSENSI RFID <br> MTs Plus Nuruttaqwa</span>
    </div>

    <ul>
        <li>
            <a href="data_siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'data_siswa.php' ? 'active' : ''; ?>">
                <div class="menu-label">
                    <i class="fas fa-home"></i> 
                    <span>Data Siswa</span>
                </div>
            </a>
        </li>

        <li class="menu-item">
            <a href="#" onclick="toggleSubmenu(event)">
                <div class="menu-label">
                    <i class="fas fa-calendar-check"></i>
                    <span>Absen Siswa</span>
                </div>
                <i class="fas fa-chevron-down" id="absenArrow"></i>
            </a>

            <div class="submenu" id="submenuAbsen">
                <a href="rekab_absensi_siswa.php" class="submenu-btn">
                    <i class="fas fa-chart-bar"></i> Rekap Absensi
                </a>
                <a href="rekab_skor_siswa.php" class="submenu-btn">
                    <i class="fas fa-star"></i> Rekap Skor
                </a>
            </div>
        </li>
    </ul>

    <!-- ===== PROFIL PENGGUNA - DITAMBAHKAN onclick ===== -->
    <div class="user-profile">
        <div class="user-info" id="profileInfo" onclick="openEditProfile()">
            <div class="user-avatar">
                <?php if($foto_user != '../images/default-user.png' && file_exists($foto_user)): ?>
                    <img src="<?php echo htmlspecialchars($foto_user); ?>" alt="Foto Profil" id="profileImage">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 30px;" id="profileImage"></i>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['role'])): ?>
                    <span class="role-badge">
                        <?php 
                            $role = strtolower($_SESSION['role']);
                            if($role == 'admin') echo 'A';
                            elseif($role == 'wali_kelas') echo 'W';
                            else echo 'U';
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <p class="user-name" id="profileName">
                <?php echo htmlspecialchars($nama_user); ?>
            </p>
            
            <p class="user-role" id="profileRole">
                <?php echo htmlspecialchars($role_user); ?>
            </p>
        </div>
        
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">

<script>
function toggleSubmenu(event){
    event.preventDefault();

    const submenu = document.getElementById("submenuAbsen");
    const arrow   = document.getElementById("absenArrow");

    submenu.classList.toggle("show");
    arrow.classList.toggle("rotate");
}

/* Klik di luar sidebar = submenu tertutup */
document.addEventListener("click", function(e){
    const submenu = document.getElementById("submenuAbsen");
    const menuItem = document.querySelector(".menu-item");

    if(menuItem && !menuItem.contains(e.target)){
        submenu.classList.remove("show");
    }
});

// ===== TAMBAHKAN FUNGSI EDIT PROFIL =====
function openEditProfile() {
    Swal.fire({
        title: 'Edit Profil',
        html: `
            <div style="text-align: left;">
                <div style="margin-bottom: 15px; text-align: center;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 3px solid #007bff; position: relative;">
                        <img id="previewImage" src="<?php echo htmlspecialchars($foto_user); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <label for="fileInput" style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: white; padding: 5px; font-size: 12px; cursor: pointer; text-align: center;">
                            <i class="fas fa-camera"></i> Ganti
                        </label>
                        <input type="file" id="fileInput" accept="image/*" style="display: none;">
                    </div>
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Nama Lengkap</label>
                    <input type="text" id="editNama" value="<?php echo htmlspecialchars($nama_user); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Email</label>
                    <input type="email" id="editEmail" value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">Username</label>
                    <input type="text" id="editUsername" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            const nama = document.getElementById('editNama').value;
            const email = document.getElementById('editEmail').value;
            const username = document.getElementById('editUsername').value;
            const fileInput = document.getElementById('fileInput');
            
            if (!nama.trim()) {
                Swal.showValidationMessage('Nama lengkap harus diisi');
                return false;
            }
            
            return { nama, email, username, file: fileInput.files[0] };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const formData = new FormData();
            formData.append('nama', data.nama);
            formData.append('email', data.email);
            formData.append('username', data.username);
            if (data.file) {
                formData.append('foto', data.file);
            }
            
            // Kirim ke server
            fetch('update_profile_wali.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // Refresh halaman untuk update tampilan
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan: ' + error
                });
            });
        }
    });
}

// Preview gambar saat memilih file
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'fileInput') {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const preview = document.getElementById('previewImage');
                if (preview) {
                    preview.src = event.target.result;
                }
            };
            reader.readAsDataURL(file);
        }
    }
});

// Tampilkan pesan jika ada parameter login
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('login') === 'success') {
        console.log('Login berhasil!');
    }
});
</script>

</body>
</html>