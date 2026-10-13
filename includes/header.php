<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
cekLogin();

$stmtSetting = $pdo->query("SELECT * FROM pengaturan LIMIT 1");
$appSetting = $stmtSetting->fetch() ?: [
    'nama_instansi' => 'Pojok Baca Pa\'edah',
    'desa' => 'Desa Sejiram',
    'kabupaten' => 'Kabupaten Sambas',
    'alamat' => 'Jl. Pendidikan No. 01, Kantor Desa Sejiram',
    'jam_operasional' => '08.00 - 15.30 WIB',
    'durasi_pinjam_hari' => 7,
    'denda_per_hari' => 1000,
    'nama_kades' => 'Kepala Desa Sejiram',
    'nama_pengelola' => 'Pengelola Pojok Baca'
];
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Sistem Pojok Baca') ?> - Pojok Baca Desa Sejiram</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; color: black !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen font-sans">
    <div class="flex flex-1">
        <!-- Sidebar Navigation -->
        <aside class="w-64 bg-emerald-800 text-white flex-shrink-0 min-h-screen p-4 no-print flex flex-col justify-between shadow-lg">
            <div>
                <div class="flex items-center gap-3 pb-4 border-b border-emerald-700/80 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-white text-emerald-800 flex items-center justify-center font-bold text-lg shadow">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-sm tracking-wide leading-tight">POJOK BACA PA'EDAH</h1>
                        <p class="text-[11px] text-emerald-200"><?= e($appSetting['desa']) ?>, Sambas</p>
                    </div>
                </div>

                <nav class="space-y-1 text-sm font-medium">
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'dashboard.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-chart-pie w-5 text-emerald-300"></i> Dashboard
                    </a>
                    <a href="buku.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'buku.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-book w-5 text-emerald-300"></i> Koleksi Buku
                    </a>
                    <a href="anggota.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'anggota.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-users w-5 text-emerald-300"></i> Data Anggota
                    </a>
                    <a href="peminjaman.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'peminjaman.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-arrow-right-arrow-left w-5 text-emerald-300"></i> Peminjaman Buku
                    </a>
                    <a href="pengembalian.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'pengembalian.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-clock-rotate-left w-5 text-emerald-300"></i> Riwayat & Denda
                    </a>
                    <a href="kunjungan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'kunjungan.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-clipboard-user w-5 text-emerald-300"></i> Presensi Tamu
                    </a>
                    <a href="inventaris.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'inventaris.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-boxes-stacked w-5 text-emerald-300"></i> Inventaris & Buku
                    </a>
                    <a href="galeri.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'galeri.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-images w-5 text-emerald-300"></i> Galeri Kegiatan & PKK
                    </a>
                    <a href="artikel.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'artikel.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-newspaper w-5 text-emerald-300"></i> Kelola Artikel & Berita
                    </a>
                    <a href="laporan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'laporan.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-file-pdf w-5 text-emerald-300"></i> Cetak Laporan
                    </a>
                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                    <a href="pengguna.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'pengguna.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-users-gear w-5 text-emerald-300"></i> Manajemen Pengguna
                    </a>
                    <a href="pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'pengaturan.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-sliders w-5 text-emerald-300"></i> Pengaturan Desa
                    </a>
                    <?php endif; ?>
                    <a href="profil.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $currentPage === 'profil.php' ? 'bg-emerald-950 text-white shadow-inner font-bold' : 'hover:bg-emerald-700 text-emerald-100' ?>">
                        <i class="fa-solid fa-id-card-clip w-5 text-emerald-300"></i> Profil & Akun
                    </a>
                </nav>
            </div>
            
            <div class="pt-4 border-t border-emerald-700/80 text-xs">
                <a href="portal.php" target="_blank" class="flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-lg text-xs font-semibold mb-3 shadow transition">
                    <i class="fa-solid fa-globe"></i> Buka Portal Publik
                </a>
                <div class="bg-emerald-900/60 p-3 rounded-lg mb-2">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-white font-semibold flex items-center gap-1.5 truncate">
                            <i class="fa-solid fa-circle-user text-sm text-emerald-300"></i> <?= e($_SESSION['user_nama'] ?? 'Petugas') ?>
                        </p>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] text-emerald-300 capitalize">Role: <span class="font-bold text-white"><?= e($_SESSION['user_role'] ?? 'petugas') ?></span></p>
                        <a href="profil.php" class="text-[11px] text-emerald-200 hover:text-white underline font-semibold flex items-center gap-1 transition">
                            <i class="fa-solid fa-pen-to-square"></i> Profil
                        </a>
                    </div>
                </div>
                <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')" class="inline-flex items-center gap-2 bg-rose-700/90 hover:bg-rose-700 text-white px-3 py-2 rounded-lg text-xs font-semibold w-full justify-center transition shadow-sm">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar (Logout)
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="flex-1 p-6 md:p-8 overflow-x-hidden">
