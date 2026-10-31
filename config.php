<?php
// config.php - Konfigurasi Database dan Session
session_start();

// Konfigurasi Database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fuzzy_ahp_housing';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi untuk mengecek login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Fungsi untuk redirect jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk mendapatkan info user
function getCurrentUser($pdo) {
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

// Fungsi untuk konversi nilai fuzzy
function getFuzzyValue($criteria, $value) {
    switch($criteria) {
        case 'mobilitas_tugas':
            return ($value == 'tinggi') ? 0.8 : (($value == 'sedang') ? 0.5 : 0.2);
        case 'pangkat':
            $pangkatValues = ['Letda' => 0.3, 'Lettu' => 0.5, 'Kapten' => 0.7, 'Mayor' => 0.9];
            return isset($pangkatValues[$value]) ? $pangkatValues[$value] : 0.1;
        case 'jumlah_keluarga':
            return ($value >= 5) ? 0.9 : (($value >= 3) ? 0.6 : 0.3);
        case 'kondisi_kesehatan':
            return ($value == 'sehat') ? 0.8 : (($value == 'cukup_sehat') ? 0.5 : 0.2);
        case 'lokasi_rumah_sekarang':
            // Simplified distance calculation based on street name
            return rand(20, 80) / 100; // Random untuk demo, dalam implementasi nyata gunakan koordinat
    }
    return 0.5;
}
?>