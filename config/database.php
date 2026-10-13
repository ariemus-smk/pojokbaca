<?php
declare(strict_types=1);

// Konfigurasi Database untuk cPanel / Localhost
// Silakan sesuaikan db_name, db_user, dan db_pass dengan database cPanel Anda
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'TebasSungai';
$db_name = 'db_pojokbaca_sejiram';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;'>
        <h3>Koneksi Basis Data Gagal</h3>
        <p>Pastikan nama database, user, dan password pada file <code>config/database.php</code> sudah sesuai dengan konfigurasi cPanel MySQL Anda.</p>
        <p>Pesan Error: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>");
}

// Fungsi helper escape output HTML
if (!function_exists('e')) {
    function e(string|null $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
