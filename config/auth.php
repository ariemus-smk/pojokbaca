<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function cekLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?pesan=belum_login');
        exit;
    }
}

function cekRoleAdmin(): void {
    cekLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: dashboard.php?pesan=akses_ditolak');
        exit;
    }
}
