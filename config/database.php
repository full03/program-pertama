<?php
// D:\laragon\www\absensi\config\database.php

// Konfigurasi database
$host = 'localhost';
$dbname = 'db_manajemen_user'; // Ganti dengan nama database Anda
$username = 'root';
$password = ''; // Kosongkan jika menggunakan Laragon

try {
    // Buat koneksi PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set mode error ke exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode ke associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set charset ke UTF-8
    $pdo->exec("SET NAMES utf8mb4");
    
} catch(PDOException $e) {
    // Jika koneksi gagal, tampilkan pesan error
    die("Koneksi database gagal: " . $e->getMessage());
}

// Variabel $pdo sekarang tersedia untuk digunakan di file yang meng-include file ini
?>