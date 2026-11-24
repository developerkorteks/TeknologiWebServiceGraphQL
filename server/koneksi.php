<?php
// =====================================================================
// koneksi.php  --  Pengaturan + koneksi database (sama seperti Pertemuan 7)
// Hasilnya: variabel $db siap dipakai oleh file lain.
// =====================================================================

$host    = 'localhost:3306';
$user    = 'root';        // XAMPP / Laragon: root
$pass    = '';            // XAMPP / Laragon: kosong
$nama_db = 'kampus_db';

// Kunci rahasia untuk menandatangani token JWT (lihat Pertemuan 7).
$jwt_secret  = 'ganti-dengan-teks-rahasia-yang-panjang-dan-acak-123';
$jwt_berlaku = 3600;      // token berlaku 1 jam

try {
    $db = new PDO("mysql:host=$host;dbname=$nama_db;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['errors' => [['message' => 'Tidak bisa terhubung ke database']]]);
    exit;
}
