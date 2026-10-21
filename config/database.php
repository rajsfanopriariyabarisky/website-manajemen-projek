<?php
$host = 'localhost';
$db   = 'db_manajemen_projek';
$user = 'root';
$pass = ''; // Sesuaikan jika MySQL kamu memakai password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}