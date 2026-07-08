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

// ===== PROSES HAPUS MASSAL DENGAN AJAX (DI FILE YANG SAMA) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_massal') {
    header('Content-Type: application/json');
    
    $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada data yang dipilih']);
        exit;
    }
    
    // Convert string IDs ke array
    $idArray = explode(',', $ids);
    $idArray = array_map('intval', $idArray);
    
    // Buat placeholder untuk query
    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
    
    // Query hapus
    $sql = "DELETE FROM siswa WHERE id IN ($placeholders)";
    $stmt = $conek->prepare($sql);
    
    if ($stmt) {
        $types = str_repeat('i', count($idArray));
        $stmt->bind_param($types, ...$idArray);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            echo json_encode(['success' => true, 'message' => "$affected data berhasil dihapus"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query: ' . $conek->error]);
    }
    
    $conek->close();
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include "../header.php"; ?>
    <title>Data Siswa</title>

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
            font-size: 12px;
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

        /* ===== HEADER ===== */
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
            background: linear-gradient(90deg, #4831d4, #6c63ff, #a78bfa, #6c63ff, #4831d4);
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
            background: linear-gradient(135deg, rgba(72, 49, 212, 0.15), rgba(108, 99, 255, 0.10));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid rgba(108, 99, 255, 0.15);
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

        /* ===== TOP BAR ===== */
        .top-bar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px 12px 0px 0px;
            padding: 0.6rem 1.2rem;
            margin-bottom: 0.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.6rem;
            box-shadow: 0 4px 20px rgba(72, 49, 212, 0.06);
            position: relative;
            z-index: 10;
        }

        /* ===== BUTTONS ===== */
        .btn-group {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.7rem;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-modern::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-modern:hover::after {
            opacity: 1;
        }

        .btn-modern:hover {
            transform: translateY(-2px) scale(1.02);
        }

        .btn-modern:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #fff;
            box-shadow: 0 4px 15px rgba(72, 49, 212, 0.25);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 25px rgba(72, 49, 212, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #34d399);
            color: #fff;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.25);
        }

        .btn-success:hover {
            box-shadow: 0 6px 25px rgba(5, 150, 105, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #ef4444);
            color: #fff;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.25);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #d97706, #fbbf24);
            color: #1a1a2e;
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.2);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 25px rgba(217, 119, 6, 0.35);
        }

        /* ===== SEARCH & FILTER ===== */
        .search-filters {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
            position: relative;
            z-index: 20;
        }

        .search-filters select,
        .search-filters input {
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            border: 1px solid rgba(72, 49, 212, 0.12);
            background: rgba(255, 255, 255, 0.7);
            color: #1a1a2e;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            min-height: 30px;
        }

        .search-filters select option {
            background: #ffffff;
            color: #1a1a2e;
        }

        .search-filters select:focus,
        .search-filters input:focus {
            outline: none;
            border-color: #6c63ff;
            background: #ffffff;
            box-shadow: 0 0 20px rgba(72, 49, 212, 0.08);
        }

        .search-filters input::placeholder {
            color: rgba(26, 26, 46, 0.35);
        }

        .search-filters .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            background: rgba(72, 49, 212, 0.06);
            color: #666688;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .search-filters .btn-icon:hover {
            background: rgba(108, 99, 255, 0.12);
            color: #4831d4;
        }

        /* ===== EXPORT DROPDOWN ===== */
        .export-dropdown {
            position: relative;
            display: inline-block;
            z-index: 100;
        }

        .export-btn {
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #d97706, #fbbf24);
            color: #1a1a2e;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.2);
            position: relative;
            z-index: 101;
        }

        .export-btn:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 25px rgba(217, 119, 6, 0.35);
        }

        .export-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            min-width: 160px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(72, 49, 212, 0.08);
            border-radius: 12px;
            padding: 0.4rem;
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.12),
                0 0 60px rgba(72, 49, 212, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            z-index: 999999;
            animation: dropDown 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-origin: top right;
            pointer-events: auto;
        }

        .export-menu.show {
            display: block;
        }

        @keyframes dropDown {
            0% {
                opacity: 0;
                transform: translateY(-12px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .export-menu a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: #1a1a2e;
            font-size: 0.7rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.25s ease;
            position: relative;
            cursor: pointer;
        }

        .export-menu a:hover {
            background: rgba(108, 99, 255, 0.08);
            color: #4831d4;
            padding-left: 1.3rem;
            transform: translateX(3px);
        }

        .export-menu a:active {
            transform: scale(0.97);
        }

        .export-menu a .icon {
            font-size: 1rem;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
        }

        .export-menu a .label {
            flex: 1;
        }

        .export-menu a .badge {
            font-size: 0.5rem;
            background: rgba(108, 99, 255, 0.08);
            color: #4831d4;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .export-menu .menu-divider {
            height: 1px;
            background: rgba(72, 49, 212, 0.08);
            margin: 0.2rem 0.4rem;
        }

        /* ===== TABLE ===== */
        .table-wrapper {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 0px 0px 12px 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(72, 49, 212, 0.06);
            position: relative;
            z-index: 1;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }

        thead {
            background: rgba(72, 49, 212, 0.04);
            border-bottom: 2px solid rgba(72, 49, 212, 0.06);
        }

        thead th {
            padding: 0.6rem 0.8rem;
            text-align: left;
            color: #555577;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        thead th:not(:last-child) {
            border-right: 1px solid rgba(72, 49, 212, 0.04);
        }

        thead th.center {
            text-align: center;
        }

        tbody tr {
            border-bottom: 1px solid rgba(72, 49, 212, 0.05);
            transition: background 0.25s ease;
        }

        tbody tr:hover {
            background: rgba(108, 99, 255, 0.04);
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody td {
            padding: 0.5rem 0.8rem;
            color: #1a1a2e;
            vertical-align: middle;
            font-size: 0.7rem;
        }

        tbody td.center {
            text-align: center;
        }

        /* ===== CHECKBOX ===== */
        .checkbox-custom {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #4831d4;
        }

        .checkbox-custom:hover {
            transform: scale(1.1);
        }

        /* ===== ACTION BUTTONS ===== */
        .action-group {
            display: flex;
            gap: 0.2rem;
            align-items: center;
            justify-content: center;
        }

        .action-btn {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
        }

        .action-btn:hover {
            transform: translateY(-2px) scale(1.05);
        }

        .action-view {
            background: rgba(251, 191, 36, 0.12);
            color: #b45309;
        }
        .action-view:hover {
            background: rgba(251, 191, 36, 0.2);
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.15);
        }

        .action-edit {
            background: rgba(96, 165, 250, 0.12);
            color: #2563eb;
        }
        .action-edit:hover {
            background: rgba(96, 165, 250, 0.2);
            box-shadow: 0 4px 15px rgba(96, 165, 250, 0.15);
        }

        .action-delete {
            background: rgba(248, 113, 113, 0.12);
            color: #dc2626;
        }
        .action-delete:hover {
            background: rgba(248, 113, 113, 0.2);
            box-shadow: 0 4px 15px rgba(248, 113, 113, 0.15);
        }

        /* ===== MODAL IMPORT ===== */
        .modal-import {
            display: none;
            position: fixed;
            z-index: 9999999;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease;
            align-items: center;
            justify-content: center;
        }

        .modal-import.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content-import {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(72, 49, 212, 0.08);
            border-radius: 16px;
            padding: 1.8rem;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from { transform: translateY(40px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header-import {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-header-import h3 {
            color: #1a1a2e;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .modal-close {
            color: rgba(26, 26, 46, 0.3);
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            line-height: 1;
        }

        .modal-close:hover {
            color: #1a1a2e;
            transform: rotate(90deg);
        }

        .import-form-group {
            margin-bottom: 1rem;
        }

        .import-form-group label {
            display: block;
            color: #555577;
            font-weight: 500;
            margin-bottom: 0.3rem;
            font-size: 0.75rem;
        }

        .import-form-group input[type="file"] {
            display: block;
            width: 100%;
            padding: 0.8rem;
            border: 2px dashed rgba(72, 49, 212, 0.15);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.5);
            color: #555577;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .import-form-group input[type="file"]:hover {
            border-color: rgba(108, 99, 255, 0.3);
            background: rgba(255, 255, 255, 0.8);
        }

        .import-form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #8888aa;
            font-size: 0.65rem;
        }

        .import-form-group .hint {
            color: #6c63ff;
            margin-top: 0.2rem;
            font-weight: 500;
            font-size: 0.65rem;
        }

        .btn-import-submit {
            width: 100%;
            padding: 0.6rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #4831d4, #6c63ff);
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-import-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(72, 49, 212, 0.25);
        }

        .btn-import-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== ALERT ===== */
        .alert-import {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.8rem;
            display: none;
            font-size: 0.75rem;
        }

        .alert-import.success {
            display: block;
            background: rgba(5, 150, 105, 0.12);
            color: #065f46;
            border: 1px solid rgba(5, 150, 105, 0.15);
        }

        .alert-import.error {
            display: block;
            background: rgba(239, 68, 68, 0.12);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .alert-import.info {
            display: block;
            background: rgba(59, 130, 246, 0.12);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.15);
        }

        /* ===== ALERT MESSAGE ===== */
        .alert-message {
            padding: 0.6rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-message.success {
            background: rgba(5, 150, 105, 0.12);
            color: #065f46;
            border: 1px solid rgba(5, 150, 105, 0.15);
        }

        .alert-message.error {
            background: rgba(239, 68, 68, 0.12);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .alert-message .icon {
            font-size: 1rem;
        }

        /* ===== KELAS BADGE ===== */
        .kelas-badge {
            background: rgba(108, 99, 255, 0.10);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            color: #4831d4;
            font-weight: 600;
        }

        /* ===== TOAST NOTIFICATION ===== */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.4s ease;
            font-size: 14px;
            max-width: 400px;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container-fluid {
                margin-left: 0;
                padding: 0.8rem;
            }

            .header-modern {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            .header-stats {
                width: 100%;
                justify-content: space-around;
                padding: 0.4rem 0.8rem;
            }

            .top-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 0.8rem;
            }

            .search-filters {
                flex-wrap: wrap;
            }

            .search-filters select,
            .search-filters input {
                flex: 1;
                min-width: 100px;
            }

            .export-menu {
                right: 0;
                left: auto;
                min-width: 150px;
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

            .btn-group .btn-modern {
                font-size: 0.65rem;
                padding: 0.3rem 0.7rem;
            }

            .search-filters {
                flex-direction: column;
            }

            .search-filters select,
            .search-filters input {
                width: 100%;
                min-width: unset;
                font-size: 0.65rem;
            }

            .modal-content-import {
                padding: 1.2rem;
            }

            table {
                font-size: 0.6rem;
            }

            thead th,
            tbody td {
                padding: 0.3rem 0.5rem;
                font-size: 0.6rem;
            }

            thead th {
                font-size: 0.5rem;
            }

            .export-menu {
                right: 0;
                left: 0;
                min-width: unset;
                width: 100%;
            }

            .kelas-badge {
                font-size: 0.55rem;
                padding: 1px 6px;
            }

            .action-btn {
                width: 22px;
                height: 22px;
                font-size: 0.6rem;
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

<?php include "menu.php"; ?>

<div class="container-fluid">

    <!-- ===== HEADER MODERN ===== -->
    <div class="header-modern">
        <div class="header-left">
            <div class="header-icon">👨‍🎓</div>
            <div class="header-text">
                <h1>Data Siswa</h1>
                <div class="sub">Kelola data siswa yang terdaftar di sistem</div>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-chip">
                <span class="number" id="totalSiswa">
                    <?php 
                    if (isset($conek) && $conek) {
                        $filterSiswa = "WHERE 1=1";
                        
                        if (!empty($_GET['cari'])) {
                            $cari = mysqli_real_escape_string($conek, $_GET['cari']);
                            $filterSiswa .= " AND (nama_siswa LIKE '%$cari%' OR nis LIKE '%$cari%')";
                        }
                        
                        if (!empty($_GET['kelas'])) {
                            $kelas = mysqli_real_escape_string($conek, $_GET['kelas']);
                            $filterSiswa .= " AND kelas='$kelas'";
                        }
                        
                        if (!empty($_GET['jurusan'])) {
                            $jurusan = mysqli_real_escape_string($conek, $_GET['jurusan']);
                            $filterSiswa .= " AND jurusan='$jurusan'";
                        }
                        
                        $qCount = mysqli_query($conek, "SELECT COUNT(*) as total FROM siswa $filterSiswa");
                        if ($qCount) {
                            $count = mysqli_fetch_assoc($qCount);
                            echo $count['total'];
                        } else {
                            echo "0";
                        }
                    } else {
                        echo "0";
                    }
                    ?>
                </span>
                <span class="label">Total Siswa</span>
            </div>
            <div class="stat-chip" style="border-left:1px solid rgba(72,49,212,0.10); padding-left:1rem;">
                <span class="number">🟢</span>
                <span class="label">Aktif</span>
            </div>
        </div>
    </div>

    <!-- ===== ALERT MESSAGE ===== -->
    <?php if (isset($message)): ?>
        <div class="alert-message <?= $message_type ?>">
            <span class="icon"><?= $message_type == 'success' ? '✅' : '❌' ?></span>
            <?= htmlspecialchars($message ?? '') ?>
        </div>
    <?php endif; ?>

    <!-- ===== TOP BAR ===== -->
    <div class="top-bar">

        <div class="btn-group">
            <a href="tambah_siswa.php" class="btn-modern btn-primary">
                ✚ Tambah Siswa
            </a>
            <button id="btnImport" class="btn-modern btn-success">
                📥 Import Data
            </button>
            <!-- Tombol Hapus Massal -->
            <button type="button" id="deleteMultipleBtn" class="btn-modern btn-danger" disabled>
                🗑️ Hapus Terpilih <span id="selectedCount" style="display:none; background:rgba(255,255,255,0.2); padding:0 6px; border-radius:4px; font-size:0.6rem;"></span>
            </button>
        </div>

        <form method="GET" action="" class="search-filters" id="filterForm">
            <select name="kelas" class="filter-select">
                <option value="" <?= empty($_GET['kelas']) ? 'selected' : '' ?>>Semua Kelas</option>
                <?php 
                if (isset($conek) && $conek) {
                    $qKelas = mysqli_query($conek, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas ASC");
                    if ($qKelas) {
                        while ($k = mysqli_fetch_assoc($qKelas)) {
                            $sel = (isset($_GET['kelas']) && $_GET['kelas']==$k['kelas']) ? "selected" : "";
                            echo "<option value='{$k['kelas']}' $sel>{$k['kelas']}</option>";
                        }
                    }
                }
                ?>
            </select>

            <select name="jurusan" class="filter-select">
                <option value="" <?= empty($_GET['jurusan']) ? 'selected' : '' ?>>Semua Jurusan</option>
                <?php 
                if (isset($conek) && $conek) {
                    $qJurusan = mysqli_query($conek, "SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan ASC");
                    if ($qJurusan) {
                        while ($j = mysqli_fetch_assoc($qJurusan)) {
                            $sel = (isset($_GET['jurusan']) && $_GET['jurusan']==$j['jurusan']) ? "selected" : "";
                            echo "<option value='{$j['jurusan']}' $sel>{$j['jurusan']}</option>";
                        }
                    }
                }
                ?>
            </select>

            <input type="text" name="cari" placeholder="Cari NIS / Nama..."
                value="<?= isset($_GET['cari']) ? htmlspecialchars($_GET['cari'] ?? '') : '' ?>">

            <button type="submit" class="btn-icon">🔍</button>
            <a href="data_siswa.php" class="btn-icon">↻</a>

            <!-- === EXPORT DROPDOWN === -->
            <div class="export-dropdown">
                <button type="button" class="export-btn">
                    <span>📤</span> Export <span style="font-size:0.5rem; opacity:0.6;">▾</span>
                </button>

                <div class="export-menu">
                    <a href="../export/export_data_siswa.php?type=excel">
                        <span class="icon">📗</span>
                        <span class="label">Excel</span>
                        <span class="badge">.xlsx</span>
                    </a>
                    <a href="../export/export_data_siswa.php?type=word">
                        <span class="icon">📘</span>
                        <span class="label">Word</span>
                        <span class="badge">.docx</span>
                    </a>
                    <div class="menu-divider"></div>
                    <a href="../export/export_data_siswa.php?type=pdf">
                        <span class="icon">📕</span>
                        <span class="label">PDF</span>
                        <span class="badge">.pdf</span>
                    </a>
                </div>
            </div>
        </form>

    </div>

    <!-- ===== MODAL IMPORT ===== -->
    <div id="modalImport" class="modal-import">
        <div class="modal-content-import">
            <div class="modal-header-import">
                <h3>📥 Import Data Siswa</h3>
                <span class="modal-close" id="closeModal">&times;</span>
            </div>

            <div id="importStatus" class="alert-import"></div>

            <form onsubmit="return submitImport()" enctype="multipart/form-data">
                <div class="import-form-group">
                    <label for="fileImport">📂 Pilih File Excel / CSV</label>
                    <input type="file" id="fileImport" name="file" accept=".xlsx,.xls,.csv">
                    <small>Format: .csv, .xlsx, .xls (Maksimal 5MB)</small>
                    <small class="hint">📌 Kolom: NIS, Nama, JK, Kelas, Jurusan, No HP, Alamat</small>
                </div>

                <button type="submit" id="importSubmit" class="btn-import-submit">
                    📤 Import Data
                </button>
            </form>
        </div>
    </div>

    <!-- ===== TABLE ===== -->
    <div class="table-wrapper">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width: 35px; text-align:center;">
                            <input type="checkbox" id="selectAll" class="checkbox-custom" title="Pilih Semua">
                        </th>
                        <th style="width: 35px;">No.</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th style="text-align:center;">Jenis Kelamin</th>
                        <th>Kelas</th>
                        <th>Jurusan</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th style="width: 100px; text-align:center;">Aksi</th>
                    </tr>
                </thead>

                <tbody id="tableBody">
                    <?php  
                    if (isset($conek) && $conek) {
                        $filter = "WHERE 1=1";

                        if (!empty($_GET['cari'])) {
                            $cari = mysqli_real_escape_string($conek, $_GET['cari']);
                            $filter .= " AND (nama_siswa LIKE '%$cari%' 
                                         OR nis LIKE '%$cari%' )";
                        }
                        if (!empty($_GET['kelas'])) {
                            $kelas = mysqli_real_escape_string($conek, $_GET['kelas']);
                            $filter .= " AND kelas='$kelas'";
                        }
                        if (!empty($_GET['jurusan'])) {
                            $jurusan = mysqli_real_escape_string($conek, $_GET['jurusan']);
                            $filter .= " AND jurusan='$jurusan'";
                        }

                        $sql = mysqli_query($conek, "SELECT * FROM siswa $filter ORDER BY id DESC");

                        if ($sql) {
                            $no = 1;
                            while ($data = mysqli_fetch_array($sql)) { 
                    ?>
                    <tr data-id="<?= $data['id'] ?>">
                        <td style="text-align:center;">
                            <input type="checkbox" class="checkbox-item" value="<?= $data['id'] ?>">
                        </td>
                        <td style="text-align:center; color:rgba(26,26,46,0.3); font-weight:600; font-size:0.65rem;"><?= $no++; ?></td>
                        <td style="font-weight:500; color:#1a1a2e; font-size:0.7rem;"><?= htmlspecialchars($data['nis'] ?? '') ?></td>
                        <td style="font-weight:600; color:#1a1a2e; font-size:0.7rem;"><?= htmlspecialchars($data['nama_siswa'] ?? '') ?></td>
                        <td style="color:#1a1a2e; font-size:0.7rem; text-align:center;"><?= htmlspecialchars($data['jenis_kelamin'] ?? '') ?></td>
                        <td><span class="kelas-badge"><?= htmlspecialchars($data['kelas'] ?? '') ?></span></td>
                        <td style="color:#1a1a2e; font-size:0.7rem;"><?= htmlspecialchars($data['jurusan'] ?? '') ?></td>
                        <td style="color:#1a1a2e; font-size:0.7rem;"><?= htmlspecialchars($data['no_hp'] ?? '') ?></td>
                        <td style="max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#1a1a2e; font-size:0.7rem;"><?= htmlspecialchars($data['alamat'] ?? '') ?></td>
                        <td>
                            <div class="action-group">
                                <a href="../profil_siswa/profil_siswa.php?id=<?= $data['id']; ?>" class="action-btn action-view" title="Lihat Profil">👁</a>
                                <a href="edit_siswa.php?id=<?= $data['id']; ?>" class="action-btn action-edit" title="Edit">✏</a>
                                <a href="hapus_siswa.php?id=<?= $data['id']; ?>" class="action-btn action-delete" title="Hapus">🗑</a>
                            </div>
                        </td>
                    </tr>
                    <?php 
                            }
                        } else {
                            echo '<tr><td colspan="10" style="text-align:center; color:#555577; padding:1.5rem; font-size:0.7rem;">Tidak ada data siswa</td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan="10" style="text-align:center; color:#555577; padding:1.5rem; font-size:0.7rem;">Koneksi database gagal</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// ===== EXPORT DROPDOWN =====
document.addEventListener("DOMContentLoaded", function() {
    const exportDropdown = document.querySelector('.export-dropdown');
    const exportBtn = document.querySelector('.export-btn');
    const exportMenu = document.querySelector('.export-menu');

    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            document.querySelectorAll('.export-menu.show').forEach(menu => {
                if (menu !== exportMenu) {
                    menu.classList.remove('show');
                }
            });
            
            exportMenu.classList.toggle('show');
            
            if (exportMenu.classList.contains('show')) {
                exportMenu.style.zIndex = '999999';
                void exportMenu.offsetHeight;
            }
        });

        document.addEventListener('click', function(e) {
            if (!exportDropdown.contains(e.target)) {
                exportMenu.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                exportMenu.classList.remove('show');
            }
        });

        exportMenu.addEventListener('mouseenter', function() {
            this.style.zIndex = '999999';
        });
    }

    // ===== MODAL IMPORT =====
    const modal = document.getElementById("modalImport");
    const btnImport = document.getElementById("btnImport");
    const closeModal = document.getElementById("closeModal");

    if(btnImport) {
        btnImport.onclick = function() {
            modal.classList.add("show");
            document.getElementById("importStatus").style.display = "none";
            document.getElementById("importStatus").className = "alert-import";
        }
    }

    if(closeModal) {
        closeModal.onclick = function() {
            modal.classList.remove("show");
        }
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.classList.remove("show");
        }
    }

    // ==========================================
    // ===== FITUR CHECKBOX & HAPUS MASSAL =====
    // ==========================================
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.checkbox-item');
    const deleteMultipleBtn = document.getElementById('deleteMultipleBtn');
    const selectedCount = document.getElementById('selectedCount');
    const tableBody = document.getElementById('tableBody');
    const totalSiswa = document.getElementById('totalSiswa');

    // Fungsi update tombol
    function updateButton() {
        const checked = document.querySelectorAll('.checkbox-item:checked');
        const count = checked.length;
        
        if (deleteMultipleBtn && selectedCount) {
            if (count > 0) {
                deleteMultipleBtn.disabled = false;
                selectedCount.textContent = count;
                selectedCount.style.display = 'inline';
            } else {
                deleteMultipleBtn.disabled = true;
                selectedCount.style.display = 'none';
            }
        }
    }

    // Event untuk select all
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateButton();
        });
    }

    // Event untuk setiap checkbox
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (selectAll) {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                selectAll.checked = allChecked;
            }
            updateButton();
        });
    });

    // ===== TOMBOL HAPUS MASSAL - LANGSUNG HAPUS TANPA POPUP =====
    if (deleteMultipleBtn) {
        deleteMultipleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const checked = document.querySelectorAll('.checkbox-item:checked');
            const count = checked.length;
            
            if (count === 0) {
                showToast('⚠️ Pilih data yang akan dihapus!', 'error');
                return;
            }
            
            // Ambil ID yang dipilih
            const ids = [];
            checked.forEach(cb => {
                ids.push(cb.value);
            });
            
            // Disable tombol
            deleteMultipleBtn.disabled = true;
            deleteMultipleBtn.innerHTML = '⏳ Menghapus...';
            
            // Kirim request AJAX ke file yang SAMA
            const formData = new FormData();
            formData.append('action', 'delete_massal');
            formData.append('ids', ids.join(','));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hapus baris dari tabel
                    checked.forEach(cb => {
                        const row = cb.closest('tr');
                        if (row) {
                            row.remove();
                        }
                    });
                    
                    // Update total siswa
                    const remainingRows = document.querySelectorAll('#tableBody tr').length;
                    if (totalSiswa) {
                        totalSiswa.textContent = remainingRows;
                    }
                    
                    // Reset checkbox select all
                    if (selectAll) {
                        selectAll.checked = false;
                    }
                    
                    // Reset tombol
                    deleteMultipleBtn.innerHTML = '🗑️ Hapus Terpilih <span id="selectedCount" style="display:none; background:rgba(255,255,255,0.2); padding:0 6px; border-radius:4px; font-size:0.6rem;"></span>';
                    deleteMultipleBtn.disabled = true;
                    if (selectedCount) {
                        selectedCount.style.display = 'none';
                    }
                    
                    // Toast sukses
                    showToast('✅ ' + data.message, 'success');
                } else {
                    showToast('❌ ' + data.message, 'error');
                    deleteMultipleBtn.innerHTML = '🗑️ Hapus Terpilih <span id="selectedCount" style="display:none; background:rgba(255,255,255,0.2); padding:0 6px; border-radius:4px; font-size:0.6rem;"></span>';
                    updateButton();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Terjadi kesalahan pada server!', 'error');
                deleteMultipleBtn.innerHTML = '🗑️ Hapus Terpilih <span id="selectedCount" style="display:none; background:rgba(255,255,255,0.2); padding:0 6px; border-radius:4px; font-size:0.6rem;"></span>';
                updateButton();
            });
        });
    }

    // ===== TOAST NOTIFICATION =====
    function showToast(message, type = 'success') {
        // Hapus toast lama
        const oldToast = document.querySelector('.toast-notification');
        if (oldToast) oldToast.remove();
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.background = type === 'success' ? '#4CAF50' : (type === 'error' ? '#f44336' : '#ff9800');
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Auto hilang setelah 3 detik
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.4s ease';
            setTimeout(() => {
                toast.remove();
            }, 400);
        }, 3000);
    }

    // Inisialisasi awal
    updateButton();
});

// ===== FUNCTION IMPORT =====
function submitImport() {
    const fileInput = document.getElementById("fileImport");
    const formData = new FormData();
    const statusDiv = document.getElementById("importStatus");

    statusDiv.style.display = "none";
    statusDiv.className = "alert-import";

    if (!fileInput.files || fileInput.files.length === 0) {
        statusDiv.className = "alert-import error";
        statusDiv.innerHTML = "⚠️ Silakan pilih file terlebih dahulu!";
        statusDiv.style.display = "block";
        return false;
    }

    const file = fileInput.files[0];
    const validExtensions = ['.csv', '.xlsx', '.xls'];
    const isValid = validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));

    if (!isValid) {
        statusDiv.className = "alert-import error";
        statusDiv.innerHTML = "⚠️ Format file tidak didukung! Gunakan .csv, .xlsx, atau .xls";
        statusDiv.style.display = "block";
        return false;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        statusDiv.className = "alert-import error";
        statusDiv.innerHTML = "⚠️ Ukuran file terlalu besar! Maksimal 5MB";
        statusDiv.style.display = "block";
        return false;
    }

    formData.append("file", file);
    formData.append("action", "import");

    const submitBtn = document.getElementById("importSubmit");
    submitBtn.disabled = true;
    submitBtn.textContent = "⏳ Mengimport...";

    statusDiv.className = "alert-import info";
    statusDiv.innerHTML = "⏳ Sedang memproses data...";
    statusDiv.style.display = "block";

    fetch("../import/import_data_siswa.php", {
        method: "POST",
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            statusDiv.className = "alert-import success";
            statusDiv.innerHTML = "✅ " + data.message;
            statusDiv.style.display = "block";

            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            statusDiv.className = "alert-import error";
            statusDiv.innerHTML = "❌ " + data.message;
            statusDiv.style.display = "block";
        }
    })
    .catch(error => {
        statusDiv.className = "alert-import error";
        statusDiv.innerHTML = "❌ Terjadi kesalahan: " + error.message;
        statusDiv.style.display = "block";
        console.error('Error:', error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = "📤 Import Data";
    });

    return false;
}
</script>

<?php include "../footer.php"; ?>
</body>
</html>