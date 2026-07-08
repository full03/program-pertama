<?php
// Matikan semua error
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// ==================== FUNGSI UNTUK LOG ====================
function write_log($message) {
    $log_file = __DIR__ . '/import_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// ==================== FUNGSI TRIM DATA ====================
function clean_data($data, $max_length = null) {
    if($data === null || $data === '') return '';
    $data = trim($data);
    // Hapus karakter aneh
    $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
    if($max_length !== null && strlen($data) > $max_length) {
        $data = substr($data, 0, $max_length);
    }
    return $data;
}

// ==================== FUNGSI CEK APAKAH BARIS ADALAH DATA SISWA ====================
function is_valid_student_row($row) {
    // Cek minimal ada data
    if(empty($row) || count($row) < 2) return false;
    
    // Cek apakah kolom kedua adalah angka (NIS) - karena format file: Nama Siswa | NIS | ...
    $nis_col = trim($row[1] ?? '');
    if(empty($nis_col)) return false;
    
    // NIS harus berupa angka
    if(!is_numeric($nis_col)) return false;
    
    // NIS harus lebih dari 0
    if((int)$nis_col <= 0) return false;
    
    // Cek apakah ada nama di kolom pertama
    $nama_col = trim($row[0] ?? '');
    if(empty($nama_col)) return false;
    
    // Nama tidak boleh terlalu pendek (minimal 2 karakter)
    if(strlen($nama_col) < 2) return false;
    
    return true;
}

// ==================== FUNGSI DETEKSI HEADER ====================
function detect_header_row($row) {
    $header_keywords = ['nama siswa', 'nis', 'jenis kelamin', 'ttl', 'kelas', 'jurusan', 'nama wali', 'no. hp', 'alamat'];
    $row_lower = array_map('strtolower', array_map('trim', $row));
    
    $match_count = 0;
    foreach($row_lower as $cell) {
        foreach($header_keywords as $keyword) {
            if(stripos($cell, $keyword) !== false) {
                $match_count++;
                break;
            }
        }
    }
    
    // Jika setidaknya 3 kata kunci header terdeteksi, anggap ini header
    return $match_count >= 3;
}

// ==================== FUNGSI CEK DATA SUDAH ADA DI DATABASE ====================
function check_nis_exists($conek, $nis) {
    $query = "SELECT id, kelas FROM siswa WHERE nis = '" . mysqli_real_escape_string($conek, $nis) . "'";
    $result = mysqli_query($conek, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['kelas']; // Return kelas yang sudah ada
    }
    return false;
}

// ==================== FUNGSI EKSTRAK KELAS DARI BARIS ====================
function extract_class_from_row($row) {
    if(empty($row)) return null;
    
    $first_cell = trim($row[0] ?? '');
    // Cari pola "Kelas : 9" atau "Kelas : 8" atau "Kelas : 7"
    if(preg_match('/kelas\s*[:]\s*(\d+)/i', $first_cell, $matches)) {
        return $matches[1];
    }
    return null;
}

// ==================== FUNGSI CEK APAKAH DATA SUDAH DIPROSES DALAM FILE ====================
function is_already_processed_in_file($processed_data, $nis, $kelas) {
    foreach($processed_data as $data) {
        if($data['nis'] == $nis && $data['kelas'] == $kelas) {
            return true;
        }
    }
    return false;
}

try {
    // Include koneksi
    include "../koneksi.php";
    
    // Cek koneksi
    if(!$conek) {
        throw new Exception('Koneksi database gagal');
    }
    
    // Cek method
    if($_SERVER['REQUEST_METHOD'] != 'POST') {
        throw new Exception('Method tidak diizinkan');
    }
    
    if(!isset($_POST['action']) || $_POST['action'] != 'import') {
        throw new Exception('Action tidak valid');
    }
    
    // Cek file upload
    if(!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        $error_msg = 'Gagal upload file';
        if(isset($_FILES['file']['error'])) {
            switch($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_msg = 'File terlalu besar (max: ' . ini_get('upload_max_filesize') . ')';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_msg = 'File terlalu besar';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_msg = 'File hanya terupload sebagian';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_msg = 'Tidak ada file yang dipilih';
                    break;
                default:
                    $error_msg = 'Error upload file (kode: ' . $_FILES['file']['error'] . ')';
            }
        }
        throw new Exception($error_msg);
    }
    
    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validasi ekstensi
    if(!in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
        throw new Exception('Format file tidak didukung. Gunakan .csv, .xlsx, atau .xls');
    }
    
    // Validasi ukuran (5MB)
    if($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB');
    }
    
    // Cek apakah file bisa dibaca
    if(!is_readable($file['tmp_name'])) {
        throw new Exception('File tidak bisa dibaca');
    }
    
    // ==================== PROSES FILE ====================
    $rows = [];
    
    if($file_ext == 'csv') {
        // ========== PROSES CSV ==========
        $handle = fopen($file['tmp_name'], 'r');
        if(!$handle) {
            throw new Exception('Gagal membuka file CSV');
        }
        
        while(($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            // Filter baris kosong
            $filtered = array_filter($data, function($val) {
                return trim($val) !== '';
            });
            if(!empty($filtered)) {
                $rows[] = $data;
            }
        }
        fclose($handle);
        
    } else {
        // ========== PROSES EXCEL (XLSX/XLS) ==========
        $data_parsed = false;
        
        // Coba baca dengan ZIP (XLSX)
        $zip = new ZipArchive();
        if($zip->open($file['tmp_name']) === TRUE) {
            // Baca shared strings
            $shared_strings = [];
            $xml_content = $zip->getFromName('xl/sharedStrings.xml');
            if($xml_content) {
                $xml = simplexml_load_string($xml_content);
                if($xml) {
                    foreach($xml->si as $si) {
                        $shared_strings[] = (string)$si->t;
                    }
                }
            }
            
            // Baca sheet
            $xml_content = $zip->getFromName('xl/worksheets/sheet1.xml');
            if($xml_content) {
                $xml = simplexml_load_string($xml_content);
                if($xml) {
                    foreach($xml->sheetData->row as $row) {
                        $row_data = [];
                        foreach($row->c as $cell) {
                            $value = (string)$cell->v;
                            if(isset($cell['t']) && $cell['t'] == 's') {
                                $value = isset($shared_strings[(int)$value]) ? $shared_strings[(int)$value] : '';
                            }
                            $row_data[] = $value;
                        }
                        // Filter baris kosong
                        $filtered = array_filter($row_data, function($val) {
                            return trim($val) !== '';
                        });
                        if(!empty($filtered)) {
                            $rows[] = $row_data;
                        }
                    }
                    $data_parsed = true;
                }
            }
            $zip->close();
        }
        
        // Jika gagal parse dengan ZIP, coba dengan SimpleXML langsung
        if(!$data_parsed) {
            $xml = simplexml_load_file($file['tmp_name']);
            if($xml !== false) {
                foreach($xml->Worksheet->Table->Row as $row) {
                    $row_data = [];
                    foreach($row->Cell as $cell) {
                        $row_data[] = (string)$cell->Data;
                    }
                    // Filter baris kosong
                    $filtered = array_filter($row_data, function($val) {
                        return trim($val) !== '';
                    });
                    if(!empty($filtered)) {
                        $rows[] = $row_data;
                    }
                }
                $data_parsed = true;
            }
        }
        
        if(!$data_parsed) {
            throw new Exception('Gagal membaca file Excel. Pastikan file valid dan tidak corrupt.');
        }
    }
    
    // ==================== PROSES DATA ====================
    if(empty($rows)) {
        throw new Exception('File kosong atau tidak ada data');
    }
    
    // ==================== EKSTRAK DATA SISWA ====================
    $student_data = [];
    $current_class = null;
    $header_found = false;
    $processed_in_file = []; // Untuk tracking data yang sudah diproses dalam file (NIS + Kelas)
    $skipped_due_to_db = []; // Data yang di-skip karena sudah ada di database
    $skipped_due_to_file = []; // Data yang di-skip karena duplikat dalam file
    
    foreach($rows as $row_index => $row) {
        // Cek apakah ini baris kelas
        $class = extract_class_from_row($row);
        if($class !== null) {
            $current_class = $class;
            $header_found = false;
            continue;
        }
        
        // Cek apakah ini header
        if(detect_header_row($row)) {
            $header_found = true;
            continue;
        }
        
        // Jika header sudah ditemukan
        if($header_found) {
            // Cek apakah ini data siswa yang valid
            if(is_valid_student_row($row)) {
                $nis = clean_data($row[1] ?? '');
                $nama = clean_data($row[0] ?? '');
                $kelas = $current_class ?: clean_data($row[4] ?? '');
                
                // Cek apakah NIS sudah ada di database
                $existing_kelas = check_nis_exists($conek, $nis);
                if($existing_kelas !== false) {
                    // NIS sudah ada di database, skip
                    $skipped_due_to_db[] = [
                        'nis' => $nis,
                        'nama' => $nama,
                        'kelas_file' => $kelas,
                        'kelas_db' => $existing_kelas
                    ];
                    continue;
                }
                
                // Cek apakah data (NIS + Kelas) sudah diproses dalam file
                if(is_already_processed_in_file($processed_in_file, $nis, $kelas)) {
                    $skipped_due_to_file[] = [
                        'nis' => $nis,
                        'nama' => $nama,
                        'kelas' => $kelas
                    ];
                    continue;
                }
                
                // Format: Nama Siswa | NIS | Jenis Kelamin | TTL | Kelas | Jurusan | Nama Wali | No. HP | Alamat
                $student_data[] = [
                    'nama' => $nama,
                    'nis' => $nis,
                    'jk' => clean_data($row[2] ?? ''),
                    'ttl' => clean_data($row[3] ?? ''),
                    'kelas' => $kelas,
                    'jurusan' => clean_data($row[5] ?? ''),
                    'nama_wali' => clean_data($row[6] ?? ''),
                    'no_hp' => clean_data($row[7] ?? ''),
                    'alamat' => clean_data($row[8] ?? '')
                ];
                
                $processed_in_file[] = [
                    'nis' => $nis,
                    'kelas' => $kelas
                ];
            }
        }
    }
    
    // ==================== CEK STRUKTUR TABEL ====================
    $column_lengths = [];
    $col_check = mysqli_query($conek, "SHOW COLUMNS FROM siswa");
    if($col_check) {
        while($col = mysqli_fetch_assoc($col_check)) {
            $field = $col['Field'];
            $type = $col['Type'];
            
            if(preg_match('/varchar\((\d+)\)/i', $type, $matches)) {
                $column_lengths[$field] = (int)$matches[1];
            } elseif(preg_match('/char\((\d+)\)/i', $type, $matches)) {
                $column_lengths[$field] = (int)$matches[1];
            } elseif(preg_match('/int\((\d+)\)/i', $type, $matches)) {
                $column_lengths[$field] = (int)$matches[1];
            } else {
                $column_lengths[$field] = null;
            }
        }
    }
    
    // Cek apakah kolom-kolom yang dibutuhkan ada
    $has_ttl = in_array('ttl', array_keys($column_lengths));
    $has_nama_wali = in_array('nama_wali', array_keys($column_lengths));
    $has_no_hp = in_array('no_hp', array_keys($column_lengths));
    $has_alamat = in_array('alamat', array_keys($column_lengths));
    $has_jurusan = in_array('jurusan', array_keys($column_lengths));
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $total_rows = count($student_data);
    
    // Mulai transaction
    mysqli_begin_transaction($conek);
    
    foreach($student_data as $index => $data) {
        // ==================== BERSIHKAN DATA ====================
        $nis = clean_data($data['nis'] ?? '', isset($column_lengths['nis']) ? $column_lengths['nis'] : null);
        $nama = clean_data($data['nama'] ?? '', isset($column_lengths['nama_siswa']) ? $column_lengths['nama_siswa'] : null);
        $jk = clean_data($data['jk'] ?? '', isset($column_lengths['jenis_kelamin']) ? $column_lengths['jenis_kelamin'] : null);
        $kelas = clean_data($data['kelas'] ?? '', isset($column_lengths['kelas']) ? $column_lengths['kelas'] : null);
        $jurusan = clean_data($data['jurusan'] ?? '', isset($column_lengths['jurusan']) ? $column_lengths['jurusan'] : null);
        $ttl = clean_data($data['ttl'] ?? '', isset($column_lengths['ttl']) ? $column_lengths['ttl'] : null);
        $nama_wali = clean_data($data['nama_wali'] ?? '', isset($column_lengths['nama_wali']) ? $column_lengths['nama_wali'] : null);
        $no_hp = clean_data($data['no_hp'] ?? '', isset($column_lengths['no_hp']) ? $column_lengths['no_hp'] : null);
        $alamat = clean_data($data['alamat'] ?? '', isset($column_lengths['alamat']) ? $column_lengths['alamat'] : null);
        
        // ==================== VALIDASI ====================
        // Validasi NIS (harus angka dan > 0)
        if(!is_numeric($nis) || (int)$nis <= 0) {
            $error_count++;
            $errors[] = "Baris " . ($index + 1) . ": NIS harus berupa angka positif (input: $nis)";
            continue;
        }
        
        // Validasi JK (L/P) - jika kosong, biarkan kosong
        if(!empty($jk) && !in_array(strtoupper($jk), ['L', 'P'])) {
            $error_count++;
            $errors[] = "Baris " . ($index + 1) . ": Jenis Kelamin harus 'L' atau 'P' (input: $jk)";
            continue;
        }
        
        // Validasi wajib: NIS dan Nama
        if(empty($nis) || empty($nama)) {
            $error_count++;
            $errors[] = "Baris " . ($index + 1) . ": NIS atau Nama kosong";
            continue;
        }
        
        // Escape untuk keamanan
        $nis_escaped = mysqli_real_escape_string($conek, $nis);
        $nama_escaped = mysqli_real_escape_string($conek, $nama);
        $jk_escaped = !empty($jk) ? mysqli_real_escape_string($conek, strtoupper($jk)) : '';
        $kelas_escaped = !empty($kelas) ? mysqli_real_escape_string($conek, $kelas) : '';
        $jurusan_escaped = !empty($jurusan) ? mysqli_real_escape_string($conek, $jurusan) : '';
        $ttl_escaped = !empty($ttl) ? mysqli_real_escape_string($conek, $ttl) : '';
        $nama_wali_escaped = !empty($nama_wali) ? mysqli_real_escape_string($conek, $nama_wali) : '';
        $no_hp_escaped = !empty($no_hp) ? mysqli_real_escape_string($conek, $no_hp) : '';
        $alamat_escaped = !empty($alamat) ? mysqli_real_escape_string($conek, $alamat) : '';
        
        // ==================== BUILD QUERY ====================
        // Buat nokartu dari NIS
        $nokartu_escaped = $nis_escaped;
        
        // Cek apakah nokartu sudah ada
        $cek_kartu = mysqli_query($conek, "SELECT id FROM siswa WHERE nokartu='$nokartu_escaped'");
        if($cek_kartu && mysqli_num_rows($cek_kartu) > 0) {
            $nokartu_escaped = $nis_escaped . rand(100, 999);
        }
        
        // Build query dengan NULL untuk data kosong
        $columns = ['nis', 'nama_siswa', 'nokartu'];
        $values = ["'$nis_escaped'", "'$nama_escaped'", "'$nokartu_escaped'"];
        
        // Tambahkan kolom opsional - jika kosong, gunakan NULL
        if(!empty($jk_escaped)) {
            $columns[] = 'jenis_kelamin';
            $values[] = "'$jk_escaped'";
        } else {
            $columns[] = 'jenis_kelamin';
            $values[] = 'NULL';
        }
        
        if(!empty($kelas_escaped)) {
            $columns[] = 'kelas';
            $values[] = "'$kelas_escaped'";
        } else {
            $columns[] = 'kelas';
            $values[] = 'NULL';
        }
        
        if($has_jurusan) {
            if(!empty($jurusan_escaped)) {
                $columns[] = 'jurusan';
                $values[] = "'$jurusan_escaped'";
            } else {
                $columns[] = 'jurusan';
                $values[] = 'NULL';
            }
        }
        
        if($has_ttl) {
            if(!empty($ttl_escaped)) {
                $columns[] = 'ttl';
                $values[] = "'$ttl_escaped'";
            } else {
                $columns[] = 'ttl';
                $values[] = 'NULL';
            }
        }
        
        if($has_nama_wali) {
            if(!empty($nama_wali_escaped)) {
                $columns[] = 'nama_wali';
                $values[] = "'$nama_wali_escaped'";
            } else {
                $columns[] = 'nama_wali';
                $values[] = 'NULL';
            }
        }
        
        if($has_no_hp) {
            if(!empty($no_hp_escaped)) {
                $columns[] = 'no_hp';
                $values[] = "'$no_hp_escaped'";
            } else {
                $columns[] = 'no_hp';
                $values[] = 'NULL';
            }
        }
        
        if($has_alamat) {
            if(!empty($alamat_escaped)) {
                $columns[] = 'alamat';
                $values[] = "'$alamat_escaped'";
            } else {
                $columns[] = 'alamat';
                $values[] = 'NULL';
            }
        }
        
        $query = "INSERT INTO siswa (" . implode(', ', $columns) . ") 
                  VALUES (" . implode(', ', $values) . ")";
        
        if(mysqli_query($conek, $query)) {
            $success_count++;
        } else {
            $error_count++;
            $error_msg = mysqli_error($conek);
            
            if(strpos($error_msg, 'Data too long') !== false) {
                $error_msg .= " (Data terlalu panjang untuk kolom)";
            } elseif(strpos($error_msg, 'Duplicate entry') !== false) {
                $error_msg .= " (Data duplikat)";
            }
            
            $errors[] = "Baris " . ($index + 1) . ": " . $error_msg;
        }
    }
    
    // Commit jika tidak ada error
    mysqli_commit($conek);
    
    // ==================== RESPONSE ====================
    $message = "Import selesai!";
    if($success_count > 0) {
        $message .= " Berhasil: $success_count data";
    }
    
    $skipped_total = count($skipped_due_to_db) + count($skipped_due_to_file);
    if($skipped_total > 0) {
        $message .= ", Di-skip: $skipped_total data";
        if(count($skipped_due_to_db) > 0) {
            $message .= " (" . count($skipped_due_to_db) . " sudah ada di database";
            if(count($skipped_due_to_file) > 0) {
                $message .= ", " . count($skipped_due_to_file) . " duplikat dalam file";
            }
            $message .= ")";
        }
    }
    
    if($error_count > 0) {
        $message .= ", Gagal: $error_count data";
    }
    
    if($success_count == 0 && $error_count == 0 && $skipped_total == 0) {
        $message = "Tidak ada data yang diimport. Pastikan file berisi data dengan format yang benar.";
    }
    
    if(!empty($errors)) {
        $message .= ". Detail error: " . implode("; ", array_slice($errors, 0, 5));
        if(count($errors) > 5) {
            $message .= " ... dan " . (count($errors) - 5) . " error lainnya";
        }
    }
    
    // Log hasil
    write_log("Import selesai: Success=$success_count, Error=$error_count, Skipped_DB=" . count($skipped_due_to_db) . ", Skipped_File=" . count($skipped_due_to_file) . ", Total=" . count($student_data));
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'skipped_db' => count($skipped_due_to_db),
        'skipped_file' => count($skipped_due_to_file),
        'total_rows' => count($student_data),
        'skipped_db_details' => $skipped_due_to_db,
        'skipped_file_details' => $skipped_due_to_file
    ]);
    
} catch(Exception $e) {
    // ==================== HANDLE ERROR ====================
    if(isset($conek) && $conek) {
        mysqli_rollback($conek);
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    
    $error_message = $e->getMessage();
    write_log("ERROR: " . $error_message);
    
    echo json_encode([
        'success' => false,
        'message' => $error_message
    ]);
}
?>