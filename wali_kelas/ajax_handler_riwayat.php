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

// Set header untuk JSON response
header('Content-Type: application/json');

// Koneksi ke database
require_once '../koneksi.php';

// Cek koneksi
if (!isset($conek) || $conek->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . ($conek->connect_error ?? 'Koneksi tidak tersedia')]);
    exit();
}

// Fungsi untuk log aktivitas (dengan error handling)
function logActivity($conek, $user_id, $action, $details) {
    try {
        $user_id = mysqli_real_escape_string($conek, $user_id);
        $action = mysqli_real_escape_string($conek, $action);
        $details = mysqli_real_escape_string($conek, $details);
        $ip = mysqli_real_escape_string($conek, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $user_agent = mysqli_real_escape_string($conek, $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        $query = "INSERT INTO log_aktivitas (user_id, action, details, ip_address, user_agent, created_at) 
                  VALUES ('$user_id', '$action', '$details', '$ip', '$user_agent', NOW())";
        mysqli_query($conek, $query);
    } catch (Exception $e) {
        // Log error tapi jangan hentikan proses
        error_log("Gagal mencatat log: " . $e->getMessage());
    }
}

// Ambil action dari request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// ===== HANDLE GET RIWAYAT SISWA (kembalikan HTML, bukan JSON) =====
if ($action == 'get_riwayat_siswa_by_nokartu') {
    // Untuk action ini, kita kembalikan HTML, bukan JSON
    header('Content-Type: text/html');
    
    $nokartu = isset($_GET['nokartu']) ? mysqli_real_escape_string($conek, $_GET['nokartu']) : '';
    
    if (empty($nokartu)) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ No Kartu tidak valid</p>';
        exit();
    }
    
    // Cari siswa berdasarkan nokartu
    $querySiswa = "SELECT id, nama_siswa, nokartu, kelas, jurusan FROM siswa WHERE nokartu = '$nokartu'";
    $resultSiswa = mysqli_query($conek, $querySiswa);
    
    if (!$resultSiswa || mysqli_num_rows($resultSiswa) == 0) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Siswa tidak ditemukan</p>';
        exit();
    }
    
    $siswa = mysqli_fetch_assoc($resultSiswa);
    $siswa_id = $siswa['id'];
    
    // Ambil riwayat absensi
    $queryRiwayat = "SELECT * FROM riwayat_siswa WHERE siswa_id = '$siswa_id' ORDER BY tanggal DESC";
    $resultRiwayat = mysqli_query($conek, $queryRiwayat);
    
    if (!$resultRiwayat) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Gagal memuat data riwayat</p>';
        exit();
    }
    
    if (mysqli_num_rows($resultRiwayat) == 0) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">📭 Belum ada riwayat absensi untuk siswa ini</p>';
        exit();
    }
    
    // Tampilkan tabel riwayat
    ?>
    <table class="history-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = mysqli_fetch_assoc($resultRiwayat)) {
                $status = '';
                $badgeClass = '';
                
                if ($row['hadir'] == 1) {
                    $status = 'Hadir';
                    $badgeClass = 'badge-hadir';
                } elseif ($row['sakit'] == 1) {
                    $status = 'Sakit';
                    $badgeClass = 'badge-sakit';
                } elseif ($row['izin'] == 1) {
                    $status = 'Izin';
                    $badgeClass = 'badge-izin';
                } elseif ($row['alfa'] == 1) {
                    $status = 'Alfa';
                    $badgeClass = 'badge-alfa';
                } else {
                    $status = 'Belum Absen';
                    $badgeClass = 'badge-alfa';
                }
                
                $rowId = $row['id'];
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d F Y', strtotime($row['tanggal'])) ?></td>
                    <td><span class="badge-status <?= $badgeClass ?>"><?= $status ?></span></td>
                    <td id="actions_<?= $rowId ?>" style="text-align:center;">
                        <div style="display:flex; gap:4px; align-items:center; justify-content:center; flex-wrap:wrap;">
                            <button class="btn-edit-row" onclick="editStatusRowModal('<?= $nokartu ?>', '<?= $row['tanggal'] ?>', '<?= $status ?>', '<?= $rowId ?>')">
                                ✏️ Edit Status
                            </button>
                            <button class="btn-delete-row" onclick="deleteRiwayat('<?= $rowId ?>', '<?= $nokartu ?>')">
                                🗑️ Hapus
                            </button>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?php
    exit();
}

// ===== HANDLE SEARCH RIWAYAT =====
if ($action == 'search_riwayat') {
    header('Content-Type: text/html');
    
    $nokartu = isset($_GET['nokartu']) ? mysqli_real_escape_string($conek, $_GET['nokartu']) : '';
    $search = isset($_GET['search']) ? mysqli_real_escape_string($conek, $_GET['search']) : '';
    
    if (empty($nokartu)) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ No Kartu tidak valid</p>';
        exit();
    }
    
    // Cari siswa
    $querySiswa = "SELECT id FROM siswa WHERE nokartu = '$nokartu'";
    $resultSiswa = mysqli_query($conek, $querySiswa);
    
    if (!$resultSiswa || mysqli_num_rows($resultSiswa) == 0) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">❌ Siswa tidak ditemukan</p>';
        exit();
    }
    
    $siswa = mysqli_fetch_assoc($resultSiswa);
    $siswa_id = $siswa['id'];
    
    // Cari riwayat dengan filter
    $queryRiwayat = "SELECT * FROM riwayat_siswa WHERE siswa_id = '$siswa_id'";
    if (!empty($search)) {
        $queryRiwayat .= " AND tanggal LIKE '%$search%'";
    }
    $queryRiwayat .= " ORDER BY tanggal DESC";
    
    $resultRiwayat = mysqli_query($conek, $queryRiwayat);
    
    if (!$resultRiwayat || mysqli_num_rows($resultRiwayat) == 0) {
        echo '<p style="color:rgba(26,26,46,0.4); text-align:center; padding:1.5rem; font-size:0.8rem;">📭 Tidak ditemukan riwayat untuk pencarian "' . htmlspecialchars($search) . '"</p>';
        exit();
    }
    
    // Tampilkan tabel hasil pencarian
    ?>
    <table class="history-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th style="text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            while ($row = mysqli_fetch_assoc($resultRiwayat)) {
                $status = '';
                $badgeClass = '';
                
                if ($row['hadir'] == 1) {
                    $status = 'Hadir';
                    $badgeClass = 'badge-hadir';
                } elseif ($row['sakit'] == 1) {
                    $status = 'Sakit';
                    $badgeClass = 'badge-sakit';
                } elseif ($row['izin'] == 1) {
                    $status = 'Izin';
                    $badgeClass = 'badge-izin';
                } elseif ($row['alfa'] == 1) {
                    $status = 'Alfa';
                    $badgeClass = 'badge-alfa';
                } else {
                    $status = 'Belum Absen';
                    $badgeClass = 'badge-alfa';
                }
                
                $rowId = $row['id'];
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d F Y', strtotime($row['tanggal'])) ?></td>
                    <td><span class="badge-status <?= $badgeClass ?>"><?= $status ?></span></td>
                    <td id="actions_<?= $rowId ?>" style="text-align:center;">
                        <div style="display:flex; gap:4px; align-items:center; justify-content:center; flex-wrap:wrap;">
                            <button class="btn-edit-row" onclick="editStatusRowModal('<?= $nokartu ?>', '<?= $row['tanggal'] ?>', '<?= $status ?>', '<?= $rowId ?>')">
                                ✏️ Edit Status
                            </button>
                            <button class="btn-delete-row" onclick="deleteRiwayat('<?= $rowId ?>', '<?= $nokartu ?>')">
                                🗑️ Hapus
                            </button>
                        </div>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?php
    exit();
}

// ===== HANDLE UPDATE STATUS ABSENSI (JSON Response) =====
if ($action == 'update_status_absensi') {
    // Pastikan header JSON
    header('Content-Type: application/json');
    
    try {
        // Ambil data POST
        $nokartu = isset($_POST['nokartu']) ? mysqli_real_escape_string($conek, $_POST['nokartu']) : '';
        $tanggal = isset($_POST['tanggal']) ? mysqli_real_escape_string($conek, $_POST['tanggal']) : '';
        $status = isset($_POST['status']) ? mysqli_real_escape_string($conek, $_POST['status']) : '';
        
        // Validasi input
        if (empty($nokartu) || empty($tanggal) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit();
        }
        
        // Validasi status yang diperbolehkan
        $statusValid = ['Hadir', 'Sakit', 'Izin', 'Alfa'];
        if (!in_array($status, $statusValid)) {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            exit();
        }
        
        // Cari siswa berdasarkan nokartu
        $querySiswa = "SELECT id, nama_siswa FROM siswa WHERE nokartu = '$nokartu'";
        $resultSiswa = mysqli_query($conek, $querySiswa);
        
        if (!$resultSiswa || mysqli_num_rows($resultSiswa) == 0) {
            echo json_encode(['success' => false, 'message' => 'Siswa tidak ditemukan']);
            exit();
        }
        
        $siswa = mysqli_fetch_assoc($resultSiswa);
        $siswa_id = $siswa['id'];
        $nama_siswa = $siswa['nama_siswa'];
        
        // Cek apakah data riwayat sudah ada
        $queryCek = "SELECT id FROM riwayat_siswa WHERE siswa_id = '$siswa_id' AND tanggal = '$tanggal'";
        $resultCek = mysqli_query($conek, $queryCek);
        
        if (!$resultCek) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengecek data: ' . mysqli_error($conek)]);
            exit();
        }
        
        // Set nilai berdasarkan status
        $hadir = 0;
        $sakit = 0;
        $izin = 0;
        $alfa = 0;
        
        switch ($status) {
            case 'Hadir': $hadir = 1; break;
            case 'Sakit': $sakit = 1; break;
            case 'Izin': $izin = 1; break;
            case 'Alfa': $alfa = 1; break;
        }
        
        if (mysqli_num_rows($resultCek) == 0) {
            // Jika tidak ada, buat data baru
            $queryInsert = "INSERT INTO riwayat_siswa (siswa_id, tanggal, hadir, sakit, izin, alfa) 
                            VALUES ('$siswa_id', '$tanggal', '$hadir', '$sakit', '$izin', '$alfa')";
            $resultInsert = mysqli_query($conek, $queryInsert);
            
            if ($resultInsert) {
                // Log aktivitas
                $user_id = $_SESSION['user_id'] ?? '1';
                $details = "Menambahkan absensi siswa $nama_siswa (NOK: $nokartu) tanggal $tanggal status $status";
                logActivity($conek, $user_id, 'tambah_absensi', $details);
                
                echo json_encode(['success' => true, 'message' => 'Data berhasil ditambahkan dengan status ' . $status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data: ' . mysqli_error($conek)]);
            }
        } else {
            // Update data
            $queryUpdate = "UPDATE riwayat_siswa 
                            SET hadir = '$hadir', 
                                sakit = '$sakit', 
                                izin = '$izin', 
                                alfa = '$alfa'
                            WHERE siswa_id = '$siswa_id' AND tanggal = '$tanggal'";
            
            $resultUpdate = mysqli_query($conek, $queryUpdate);
            
            if ($resultUpdate) {
                // Log aktivitas
                $user_id = $_SESSION['user_id'] ?? '1';
                $details = "Mengupdate status absensi siswa $nama_siswa (NOK: $nokartu) tanggal $tanggal menjadi $status";
                logActivity($conek, $user_id, 'update_absensi', $details);
                
                echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate menjadi ' . $status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data: ' . mysqli_error($conek)]);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
    exit();
}

// ===== HANDLE DELETE RIWAYAT (JSON Response) =====
if ($action == 'delete_riwayat') {
    header('Content-Type: application/json');
    
    try {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nokartu = isset($_POST['nokartu']) ? mysqli_real_escape_string($conek, $_POST['nokartu']) : '';
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit();
        }
        
        // Ambil data sebelum dihapus untuk log
        $queryGet = "SELECT r.*, s.nama_siswa, s.nokartu 
                     FROM riwayat_siswa r 
                     JOIN siswa s ON r.siswa_id = s.id 
                     WHERE r.id = '$id'";
        $resultGet = mysqli_query($conek, $queryGet);
        
        if (!$resultGet || mysqli_num_rows($resultGet) == 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
            exit();
        }
        
        $data = mysqli_fetch_assoc($resultGet);
        $status = '';
        if ($data['hadir'] == 1) {
            $status = 'Hadir';
        } elseif ($data['sakit'] == 1) {
            $status = 'Sakit';
        } elseif ($data['izin'] == 1) {
            $status = 'Izin';
        } elseif ($data['alfa'] == 1) {
            $status = 'Alfa';
        }
        
        // Hapus data
        $queryDelete = "DELETE FROM riwayat_siswa WHERE id = '$id'";
        $resultDelete = mysqli_query($conek, $queryDelete);
        
        if ($resultDelete) {
            // Log aktivitas
            $user_id = $_SESSION['user_id'] ?? '1';
            $details = "Menghapus riwayat absensi siswa {$data['nama_siswa']} (NOK: {$data['nokartu']}) tanggal {$data['tanggal']} status $status";
            logActivity($conek, $user_id, 'delete_absensi', $details);
            
            echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . mysqli_error($conek)]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
    exit();
}

// ===== HANDLE GET STATUS (JSON Response) =====
if ($action == 'get_status') {
    header('Content-Type: application/json');
    
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit();
        }
        
        $query = "SELECT * FROM riwayat_siswa WHERE id = '$id'";
        $result = mysqli_query($conek, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
            exit();
        }
        
        $data = mysqli_fetch_assoc($result);
        $status = '';
        if ($data['hadir'] == 1) {
            $status = 'Hadir';
        } elseif ($data['sakit'] == 1) {
            $status = 'Sakit';
        } elseif ($data['izin'] == 1) {
            $status = 'Izin';
        } elseif ($data['alfa'] == 1) {
            $status = 'Alfa';
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $data['id'],
                'siswa_id' => $data['siswa_id'],
                'tanggal' => $data['tanggal'],
                'status' => $status,
                'hadir' => $data['hadir'],
                'sakit' => $data['sakit'],
                'izin' => $data['izin'],
                'alfa' => $data['alfa']
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    }
    exit();
}

// Jika action tidak dikenal
echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
exit();
?>