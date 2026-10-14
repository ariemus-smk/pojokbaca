<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$baseUrl = $protocol . $host . $baseDir;
$likedArticlesSession = $_SESSION['liked_articles'] ?? [];

// Pengaturan Instansi Desa
$stmtSetting = $pdo->query("SELECT * FROM pengaturan LIMIT 1");
$setting = $stmtSetting->fetch() ?: [
    'nama_instansi' => 'Pojok Baca Pa\'edah',
    'desa' => 'Desa Sejiram',
    'kabupaten' => 'Kabupaten Sambas',
    'alamat' => 'Jl. Pendidikan No. 01, Kantor Desa Sejiram',
    'jam_operasional' => '08.00 - 15.30 WIB',
    'durasi_pinjam_hari' => 7,
    'denda_per_hari' => 1000
];

// Presensi Mandiri Pemustaka dari Publik
$pesanPresensi = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['kirim_presensi'])) {
    $nama = trim($_POST['nama'] ?? '');
    $kategori = $_POST['kategori_pemustaka'] ?? 'Warga Umum';
    $institusi = trim($_POST['asal_institusi'] ?? '');
    $keperluan = trim($_POST['keperluan'] ?? '');
    $tgl = date('Y-m-d');

    if (!empty($nama) && !empty($institusi)) {
        $stmtPres = $pdo->prepare("INSERT INTO kunjungan (tanggal, nama, kategori_pemustaka, asal_institusi, keperluan) VALUES (?, ?, ?, ?, ?)");
        $stmtPres->execute([$tgl, $nama, $kategori, $institusi, $keperluan]);
        $pesanPresensi = "Terima kasih, presensi kunjungan Anda di Pojok Baca Desa Sejiram telah tercatat!";
    }
}

// Registrasi Mandiri Anggota Baru dari Portal Publik
$pesanRegistrasi = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['daftar_anggota'])) {
    $nama = trim($_POST['nama'] ?? '');
    $kategori = trim($_POST['kategori_tingkat'] ?? 'Warga Umum');
    $institusi = trim($_POST['institusi'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');

    $kategoriTingkatList = ['SD', 'SMP', 'SMA', 'Warga Umum', 'Aparatur Desa'];

    if (empty($nama) || empty($institusi) || empty($kontak)) {
        $pesanRegistrasi = [
            'tipe' => 'error',
            'judul' => 'Data Belum Lengkap',
            'pesan' => 'Mohon lengkapi Nama Lengkap, Asal Dusun/Sekolah, dan Nomor WhatsApp/Kontak Anda!'
        ];
    } elseif (!in_array($kategori, $kategoriTingkatList, true)) {
        $pesanRegistrasi = [
            'tipe' => 'error',
            'judul' => 'Kategori Tidak Valid',
            'pesan' => 'Pilihan kategori/jenjang pemustaka tidak valid!'
        ];
    } else {
        // Cek apakah nomor kontak sudah terdaftar sebelumnya
        $stmtCek = $pdo->prepare("SELECT id, no_anggota, nama, status FROM anggota WHERE kontak = ?");
        $stmtCek->execute([$kontak]);
        $anggotaLama = $stmtCek->fetch();

        if ($anggotaLama) {
            if ($anggotaLama['status'] === 'Aktif') {
                $pesanRegistrasi = [
                    'tipe' => 'info',
                    'judul' => 'Nomor Kontak Sudah Terdaftar Aktif',
                    'pesan' => "Nomor kontak ({$kontak}) sudah terdaftar sebagai anggota aktif atas nama '{$anggotaLama['nama']}' dengan Nomor Anggota resmi: {$anggotaLama['no_anggota']}. Anda sudah dapat langsung meminjam buku di Pojok Baca."
                ];
            } elseif ($anggotaLama['status'] === 'Menunggu') {
                $pesanRegistrasi = [
                    'tipe' => 'warning',
                    'judul' => 'Pendaftaran Sedang Menunggu Verifikasi',
                    'no_reg' => $anggotaLama['no_anggota'],
                    'nama' => $anggotaLama['nama'],
                    'institusi' => $institusi,
                    'kontak' => $kontak,
                    'pesan' => "Pendaftaran atas nama '{$anggotaLama['nama']}' dengan kode registrasi ({$anggotaLama['no_anggota']}) sudah kami terima sebelumnya dan sedang dalam antrean verifikasi oleh petugas/admin perpustakaan desa."
                ];
            } else {
                // Status Ditolak atau Nonaktif -> buatkan pendaftaran baru dengan status Menunggu
                $kodeReg = 'REG-' . date('ymd') . '-' . rand(100, 999);
                $stmtUp = $pdo->prepare("UPDATE anggota SET no_anggota = ?, nama = ?, kategori_tingkat = ?, institusi = ?, status = 'Menunggu', created_at = NOW() WHERE id = ?");
                $stmtUp->execute([$kodeReg, $nama, $kategori, $institusi, $anggotaLama['id']]);
                $pesanRegistrasi = [
                    'tipe' => 'success',
                    'judul' => 'Pendaftaran Berhasil Dikirim Ulang!',
                    'no_reg' => $kodeReg,
                    'nama' => $nama,
                    'kategori' => $kategori,
                    'institusi' => $institusi,
                    'kontak' => $kontak,
                    'pesan' => 'Permohonan pendaftaran anggota baru Anda berhasil diperbarui dan dikirim. Petugas/admin desa akan segera meninjau dan menerbitkan nomor anggota resmi Anda.'
                ];
            }
        } else {
            // Generate kode pendaftaran sementara yang unik: REG-YYMMDD-XXX
            $kodeReg = '';
            for ($i = 0; $i < 5; $i++) {
                $candidate = 'REG-' . date('ymd') . '-' . rand(100, 999);
                $stmtUniq = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE no_anggota = ?");
                $stmtUniq->execute([$candidate]);
                if ((int)$stmtUniq->fetchColumn() === 0) {
                    $kodeReg = $candidate;
                    break;
                }
            }
            if (empty($kodeReg)) {
                $kodeReg = 'REG-' . date('ymd') . '-' . (time() % 10000);
            }

            try {
                $stmtIns = $pdo->prepare("INSERT INTO anggota (no_anggota, nama, kategori_tingkat, institusi, kontak, status, created_at) VALUES (?, ?, ?, ?, ?, 'Menunggu', NOW())");
                $stmtIns->execute([$kodeReg, $nama, $kategori, $institusi, $kontak]);

                $pesanRegistrasi = [
                    'tipe' => 'success',
                    'judul' => 'Pendaftaran Anggota Berhasil Dikirim!',
                    'no_reg' => $kodeReg,
                    'nama' => $nama,
                    'kategori' => $kategori,
                    'institusi' => $institusi,
                    'kontak' => $kontak,
                    'pesan' => 'Permohonan pendaftaran anggota baru Anda berhasil dicatat ke sistem. Petugas/admin perpustakaan desa akan segera memverifikasi dan menyetujui pendaftaran Anda.'
                ];
            } catch (PDOException $e) {
                $pesanRegistrasi = [
                    'tipe' => 'error',
                    'judul' => 'Gagal Mengirim Pendaftaran',
                    'pesan' => 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage()
                ];
            }
        }
    }
}

// Statistik Singkat
$totalJudul = $pdo->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$totalStok = $pdo->query("SELECT COALESCE(SUM(stok_total), 0) FROM buku")->fetchColumn();
$totalPengunjung = $pdo->query("SELECT COUNT(*) FROM kunjungan")->fetchColumn();

// Pencarian Buku Publik
$cariBuku = trim($_GET['cari_buku'] ?? '');
$katBuku = $_GET['kategori_buku'] ?? '';

$sqlBuku = "SELECT * FROM buku WHERE 1=1";
$paramsBuku = [];
if (!empty($katBuku)) {
    $sqlBuku .= " AND kategori = ?";
    $paramsBuku[] = $katBuku;
}
if (!empty($cariBuku)) {
    $sqlBuku .= " AND (judul LIKE ? OR penulis LIKE ? OR kode_buku LIKE ?)";
    $paramsBuku[] = "%$cariBuku%";
    $paramsBuku[] = "%$cariBuku%";
    $paramsBuku[] = "%$cariBuku%";
}
$sqlBuku .= " ORDER BY id DESC LIMIT 12";
$stmtBuku = $pdo->prepare($sqlBuku);
$stmtBuku->execute($paramsBuku);
$koleksiBuku = $stmtBuku->fetchAll();

// Galeri Terbaru (Max 8 foto)
$stmtGaleri = $pdo->query("SELECT * FROM galeri ORDER BY tanggal DESC, id DESC LIMIT 8");
$galeriPublik = $stmtGaleri->fetchAll();

// Artikel & Berita Terbaru (Max 6 artikel)
$stmtArt = $pdo->query("SELECT * FROM artikel WHERE status = 'Publish' ORDER BY tanggal DESC, id DESC LIMIT 6");
$artikelPublik = $stmtArt->fetchAll();

$kategoriBukuList = [
    'Koleksi buku anak',
    'Buku pendidikan',
    'Buku kesehatan',
    'Buku keterampilan',
    'Buku agama',
    'Buku budaya/kearifan lokal',
    'Buku lainnya'
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($setting['nama_instansi']) ?> - <?= e($setting['desa']) ?>, Kabupaten Sambas</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col min-h-screen">

    <!-- Top Info Bar -->
    <div class="bg-emerald-900 text-emerald-100 text-xs py-2 px-4 border-b border-emerald-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center gap-4">
                <span><i class="fa-solid fa-location-dot mr-1 text-emerald-400"></i> <?= e($setting['alamat']) ?></span>
                <span class="hidden md:inline">&bull;</span>
                <span class="hidden md:inline"><i class="fa-solid fa-clock mr-1 text-emerald-400"></i> Buka Setiap Hari Kerja (<?= e(!empty($setting['jam_operasional']) ? $setting['jam_operasional'] : '08.00 - 15.30 WIB') ?>)</span>
            </div>
            <div>
                <a href="login.php" class="bg-emerald-800 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs font-semibold inline-flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-lock"></i> Area Petugas / Login
                </a>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="portal.php" class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-emerald-700 to-teal-500 text-white flex items-center justify-center font-bold text-xl shadow-md">
                        <i class="fa-solid fa-book-open-reader"></i>
                    </div>
                    <div>
                        <span class="text-xs uppercase tracking-wider font-extrabold text-emerald-700 block">Pemerintah <?= e($setting['desa']) ?></span>
                        <h1 class="text-lg md:text-xl font-black text-slate-900 tracking-tight leading-tight"><?= e($setting['nama_instansi']) ?></h1>
                    </div>
                </a>

                <nav class="hidden md:flex items-center gap-6 lg:gap-7 text-sm font-semibold text-slate-600">
                    <a href="#beranda" class="hover:text-emerald-700 transition">Beranda</a>
                    <a href="#katalog" class="hover:text-emerald-700 transition">Katalog Buku</a>
                    <a href="#daftar-anggota" class="hover:text-emerald-700 text-emerald-700 font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-id-card text-emerald-600"></i> Daftar Anggota
                    </a>
                    <a href="#artikel" class="hover:text-emerald-700 transition">Artikel & Literasi</a>
                    <a href="#galeri" class="hover:text-emerald-700 transition">Galeri & PKK</a>
                    <a href="#buku-tamu" class="hover:text-emerald-700 transition">Buku Tamu</a>
                    <a href="#kontak" class="hover:text-emerald-700 transition">Kontak</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="#daftar-anggota" class="hidden sm:inline-flex items-center gap-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-bold px-3.5 py-2 rounded-xl shadow-sm transition">
                        <i class="fa-solid fa-user-plus"></i> Gabung Anggota
                    </a>
                    <a href="#katalog" class="md:hidden text-xs bg-slate-100 text-slate-700 hover:bg-slate-200 px-2.5 py-1.5 rounded-lg font-semibold">Cari Buku</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Banner Utama Halaman Publik -->
    <?php 
    $bannerFile = 'uploads/img/banner.jpeg';
    $hasBanner = file_exists(__DIR__ . '/' . $bannerFile);
    ?>
    <?php if ($hasBanner): ?>
    <section id="beranda" class="bg-slate-100 py-4 sm:py-6 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-2xl md:rounded-3xl shadow-md border border-slate-200/80 bg-white">
                <img src="<?= e($bannerFile) ?>" 
                     alt="Banner Resmi <?= e($setting['nama_instansi']) ?> <?= e($setting['desa']) ?>" 
                     class="w-full h-auto object-cover block"
                     loading="eager">
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Hero Section -->
    <section <?= !$hasBanner ? 'id="beranda"' : '' ?> class="relative bg-gradient-to-br from-emerald-800 via-teal-800 to-slate-900 text-white py-14 md:py-20 overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-2 bg-emerald-700/60 border border-emerald-500/40 text-emerald-200 px-3.5 py-1.5 rounded-full text-xs font-bold tracking-wide">
                        <i class="fa-solid fa-bullhorn text-emerald-300"></i> Gerakan Literasi Desa & Pemberdayaan PKK
                    </div>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight tracking-tight">
                        Membangun Desa Pintar, <br>
                        <span class="text-emerald-300">Merajut Masa Depan Generasi Gemilang.</span>
                    </h2>
                    <p class="text-slate-200 text-sm sm:text-base leading-relaxed max-w-2xl font-normal">
                        Selamat datang di portal informasi resmi <?= e($setting['nama_instansi']) ?> <?= e($setting['desa']) ?>, Kabupaten Sambas. Layanan literasi terbuka untuk adik-adik pelajar SD, SMP, SMA, ibu-ibu TP-PKK, hingga masyarakat tani dan umum.
                    </p>

                    <!-- Search Bar Instan di Hero -->
                    <form action="#katalog" method="GET" class="bg-white p-2 rounded-2xl shadow-2xl flex flex-col sm:flex-row gap-2 max-w-xl text-slate-800">
                        <div class="flex-1 flex items-center px-3">
                            <i class="fa-solid fa-magnifying-glass text-slate-400 mr-2.5"></i>
                            <input type="text" name="cari_buku" placeholder="Cari judul buku, dongeng, pertanian..." class="w-full text-sm outline-none bg-transparent py-2">
                        </div>
                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs uppercase px-6 py-3 rounded-xl transition shadow">
                            Cari Buku
                        </button>
                    </form>

                    <!-- CTA Registrasi Anggota Cepat -->
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="#daftar-anggota" class="inline-flex items-center gap-2 bg-amber-400 hover:bg-amber-300 text-slate-900 font-extrabold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl shadow-lg transition transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-id-card text-amber-900"></i> Daftar Anggota Pemustaka Gratis
                        </a>
                        <span class="text-xs text-emerald-200 hidden sm:inline"><i class="fa-solid fa-check-circle text-emerald-300 mr-1"></i>Pinjam buku pulang & gratis seumur hidup</span>
                    </div>

                    <!-- Angka Statistik Mini -->
                    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-emerald-700/60 max-w-lg">
                        <div>
                            <p class="text-2xl font-black text-white"><?= (int)$totalJudul ?>+</p>
                            <p class="text-xs text-emerald-200">Judul Koleksi</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-white"><?= (int)$totalStok ?>+</p>
                            <p class="text-xs text-emerald-200">Total Eksemplar</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-white"><?= (int)$totalPengunjung ?>+</p>
                            <p class="text-xs text-emerald-200">Pemustaka Hadir</p>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="bg-white/10 backdrop-blur-md p-6 rounded-3xl border border-white/20 shadow-2xl text-white">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 font-bold text-lg">
                                <i class="fa-solid fa-book-sparkles"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-base leading-tight">Layanan Ramah Pemustaka</h3>
                                <p class="text-xs text-emerald-200">Akses Buku & Bahan Bacaan Gratis</p>
                            </div>
                        </div>
                        <ul class="space-y-3 text-xs text-slate-200">
                            <li class="flex items-start gap-2.5">
                                <i class="fa-solid fa-check text-emerald-400 mt-0.5"></i>
                                <span><strong>7 Kategori Koleksi:</strong> Buku anak, pendidikan, kesehatan, keterampilan tani, agama, kearifan lokal Sambas.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i class="fa-solid fa-check text-emerald-400 mt-0.5"></i>
                                <span><strong>Pojok Baca Ramah Anak & PKK:</strong> Dilengkapi ruang baca santai dan buku panduan parenting keluarga.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <i class="fa-solid fa-check text-emerald-400 mt-0.5"></i>
                                <span><strong>Peminjaman Cepat:</strong> Terbuka bagi seluruh warga dan peserta didik berdomisili di Desa Sejiram.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Katalog Buku Publik -->
    <section id="katalog" class="py-16 bg-slate-100/70 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-700">Koleksi Terbuka</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Jelajahi Katalog Buku</h2>
                    <p class="text-sm text-slate-500 mt-1">Cari ketersediaan stok buku secara real-time sebelum berkunjung ke perpustakaan.</p>
                </div>

                <!-- Filter Kategori Buku -->
                <form method="GET" action="#katalog" class="flex flex-wrap gap-2">
                    <select name="kategori_buku" onchange="this.form.submit()" class="bg-white text-xs border border-slate-300 rounded-xl px-3 py-2 font-medium outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
                        <option value="">Semua Kategori Koleksi</option>
                        <?php foreach ($kategoriBukuList as $kb): ?>
                        <option value="<?= $kb ?>" <?= $katBuku === $kb ? 'selected' : '' ?>><?= $kb ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($katBuku) || !empty($cariBuku)): ?>
                    <a href="portal.php#katalog" class="bg-slate-200 text-slate-600 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-slate-300 transition">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Grid Buku -->
            <?php if (empty($koleksiBuku)): ?>
            <div class="bg-white rounded-2xl p-12 text-center text-slate-400 border border-slate-200">
                <i class="fa-solid fa-book-open text-4xl mb-3 text-slate-300"></i>
                <p class="font-semibold text-slate-700">Buku yang Anda cari tidak ditemukan.</p>
                <p class="text-xs mt-1">Coba gunakan kata kunci judul yang lain atau pilih kategori yang berbeda.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-5">
                <?php foreach ($koleksiBuku as $bk): 
                    $coverFile = !empty($bk['cover']) ? 'uploads/covers/' . $bk['cover'] : null;
                    $hasCover = $coverFile && file_exists(__DIR__ . '/' . $coverFile);
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-xl hover:-translate-y-1 transition duration-200 cursor-pointer group"
                     onclick="bukaModalDetailBuku(this)"
                     data-id="<?= (int)$bk['id'] ?>"
                     data-kode="<?= e($bk['kode_buku']) ?>"
                     data-judul="<?= e($bk['judul']) ?>"
                     data-penulis="<?= e($bk['penulis']) ?>"
                     data-penerbit="<?= e($bk['penerbit']) ?>"
                     data-tahun="<?= (int)$bk['tahun'] ?>"
                     data-kategori="<?= e($bk['kategori']) ?>"
                     data-rak="<?= e($bk['lokasi_rak']) ?>"
                     data-stok-total="<?= (int)$bk['stok_total'] ?>"
                     data-stok-tersedia="<?= (int)$bk['stok_tersedia'] ?>"
                     data-kondisi="<?= e($bk['kondisi']) ?>"
                     data-cover="<?= $hasCover ? e($coverFile) : '' ?>"
                     title="Klik untuk melihat detail buku: <?= e($bk['judul']) ?>">
                    <div>
                        <div class="aspect-[3/4] bg-slate-100 relative overflow-hidden flex items-center justify-center border-b">
                            <?php if ($hasCover): ?>
                                <img src="<?= e($coverFile) ?>" alt="<?= e($bk['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            <?php else: ?>
                                <div class="text-center p-3 text-slate-400">
                                    <i class="fa-solid fa-book text-3xl mb-1 text-slate-300"></i>
                                    <p class="text-[10px] font-semibold uppercase">Pojok Baca</p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Hover Overlay Detail -->
                            <div class="absolute inset-0 bg-slate-900/30 opacity-0 group-hover:opacity-100 transition duration-200 flex items-center justify-center">
                                <span class="px-2.5 py-1 rounded-full bg-white/95 text-emerald-800 font-bold text-[11px] shadow-md transform scale-90 group-hover:scale-100 transition flex items-center gap-1">
                                    <i class="fa-solid fa-circle-info text-xs"></i> Detail
                                </span>
                            </div>

                            <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded-md shadow-sm <?= $bk['stok_tersedia'] > 0 ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' ?>">
                                <?= $bk['stok_tersedia'] > 0 ? 'Ready ' . $bk['stok_tersedia'] : 'Kosong' ?>
                            </span>
                        </div>
                        <div class="p-3">
                            <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide block truncate"><?= e($bk['kategori']) ?></span>
                            <h3 class="font-bold text-slate-800 text-xs mt-1 line-clamp-2 leading-tight group-hover:text-emerald-700 transition" title="<?= e($bk['judul']) ?>"><?= e($bk['judul']) ?></h3>
                            <p class="text-[11px] text-slate-500 mt-1 truncate"><?= e($bk['penulis']) ?></p>
                        </div>
                    </div>
                    <div class="px-3 py-2 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-[11px] text-slate-500">
                        <span class="font-mono text-emerald-800 font-bold flex items-center gap-1">
                            <i class="fa-solid fa-layer-group text-[10px] text-emerald-600"></i> <?= e($bk['lokasi_rak']) ?>
                        </span>
                        <span><?= $bk['tahun'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Artikel & Berita Literasi Desa -->
    <section id="artikel" class="py-16 bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-10">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-700">Warta & Literasi</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Artikel & Kabar Literasi Desa</h2>
                    <p class="text-sm text-slate-500 mt-1">Tulisan inspiratif, informasi kegiatan TP-PKK, edukasi tani jeruk, dan kabar literasi Desa Sejiram.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($artikelPublik as $art): 
                    $gbrPath = !empty($art['gambar']) ? 'uploads/artikel/' . $art['gambar'] : null;
                    $hasGbr = $gbrPath && file_exists(__DIR__ . '/' . $gbrPath);
                    $artUrl = $baseUrl . '/baca_artikel.php?id=' . $art['id'];
                    $isArtLiked = in_array($art['id'], $likedArticlesSession, true);
                ?>
                <article class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="aspect-video bg-slate-100 relative overflow-hidden flex items-center justify-center">
                            <?php if ($hasGbr): ?>
                                <img src="<?= e($gbrPath) ?>" alt="<?= e($art['judul']) ?>" class="w-full h-full object-cover hover:scale-105 transition duration-300">
                            <?php else: ?>
                                <div class="text-center p-6 text-slate-400">
                                    <i class="fa-solid fa-newspaper text-4xl mb-2 text-slate-300"></i>
                                    <p class="text-xs">Berita Desa Sejiram</p>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-3 left-3 bg-emerald-700 text-white text-[11px] font-bold px-3 py-1 rounded-full shadow-sm">
                                <?= e($art['kategori']) ?>
                            </span>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center gap-2 text-xs text-slate-400 mb-2">
                                <span><i class="fa-regular fa-calendar mr-1"></i> <?= date('d M Y', strtotime($art['tanggal'])) ?></span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-user-pen mr-1"></i> <?= e($art['penulis']) ?></span>
                            </div>
                            <h3 class="font-bold text-slate-900 text-base leading-snug hover:text-emerald-700 transition">
                                <a href="baca_artikel.php?id=<?= $art['id'] ?>"><?= e($art['judul']) ?></a>
                            </h3>
                            <p class="text-xs text-slate-600 mt-3 line-clamp-3 leading-relaxed">
                                <?= e(strip_tags($art['konten'])) ?>
                            </p>
                        </div>
                    </div>
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400" title="<?= (int)$art['views'] ?> kali dibaca">
                                <i class="fa-solid fa-eye mr-1"></i> <?= (int)$art['views'] ?>
                            </span>
                            <span class="text-slate-300">&bull;</span>
                            <button type="button" onclick="likeArtikelPortal(event, <?= $art['id'] ?>)" 
                                    id="portalLikeBtn_<?= $art['id'] ?>"
                                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg transition <?= $isArtLiked ? 'bg-rose-50 text-rose-600 font-bold' : 'text-slate-500 hover:text-rose-600 hover:bg-rose-50' ?>" 
                                    title="Sukai artikel ini">
                                <i class="<?= $isArtLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart text-rose-500 text-xs" id="portalLikeIcon_<?= $art['id'] ?>"></i>
                                <span id="portalLikeCount_<?= $art['id'] ?>" class="text-xs"><?= (int)($art['likes'] ?? 0) ?></span>
                            </button>
                            <button type="button" 
                                    onclick="bukaModalSharePortal(event, this)" 
                                    data-id="<?= (int)$art['id'] ?>"
                                    data-judul="<?= e($art['judul']) ?>"
                                    data-kategori="<?= e($art['kategori']) ?>"
                                    data-url="<?= e($artUrl) ?>"
                                    class="w-7 h-7 rounded-lg text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 flex items-center justify-center transition" 
                                    title="Bagikan artikel ini">
                                <i class="fa-solid fa-share-nodes text-xs"></i>
                            </button>
                        </div>
                        <a href="baca_artikel.php?id=<?= $art['id'] ?>" class="text-emerald-700 hover:text-emerald-900 font-bold flex items-center gap-1">
                            Baca <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section Galeri Foto & Kegiatan PKK -->
    <section id="galeri" class="py-16 bg-slate-100/70 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-10">
                <div>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-700">Dokumentasi Lapangan</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Galeri Pojok Baca & Kegiatan PKK</h2>
                    <p class="text-sm text-slate-500 mt-1">Momen kebersamaan, aksi gemar membaca anak-anak, dan aktivitas pemberdayaan keluarga TP-PKK Sejiram.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php if (empty($galeriPublik)): ?>
                <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-300">
                    <i class="fa-regular fa-images text-4xl mb-2 text-slate-300"></i>
                    <p class="text-sm font-medium">Belum ada dokumentasi foto kegiatan yang diunggah.</p>
                </div>
                <?php else: ?>
                <?php foreach ($galeriPublik as $gl): 
                    $fotoPath = 'uploads/galeri/' . $gl['foto'];
                    $hasFoto = file_exists(__DIR__ . '/' . $fotoPath);
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition duration-200 cursor-pointer group"
                     onclick="bukaModalGaleri(this)"
                     data-judul="<?= e($gl['judul']) ?>"
                     data-kategori="<?= e($gl['kategori']) ?>"
                     data-foto="<?= $hasFoto ? e($fotoPath) : '' ?>"
                     data-tanggal="<?= date('d M Y', strtotime($gl['tanggal'])) ?>"
                     data-keterangan="<?= e($gl['keterangan'] ?? '') ?>">
                    <div>
                        <div class="aspect-video bg-slate-100 relative overflow-hidden flex items-center justify-center">
                            <?php if ($hasFoto): ?>
                                <img src="<?= e($fotoPath) ?>" alt="<?= e($gl['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <div class="absolute inset-0 bg-slate-900/30 opacity-0 group-hover:opacity-100 transition duration-200 flex items-center justify-center">
                                    <span class="w-10 h-10 rounded-full bg-white/95 text-emerald-800 flex items-center justify-center text-sm shadow-md transform scale-75 group-hover:scale-100 transition duration-200">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4 text-slate-400">
                                    <i class="fa-regular fa-image text-4xl mb-1 text-slate-300"></i>
                                    <p class="text-xs">Foto Dokumentasi</p>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-2 left-2 text-[10px] font-bold px-2.5 py-0.5 rounded shadow-sm <?= $gl['kategori'] === 'Kegiatan PKK' ? 'bg-rose-600 text-white' : 'bg-emerald-700 text-white' ?>">
                                <?= e($gl['kategori']) ?>
                            </span>
                        </div>
                        <div class="p-4">
                            <span class="text-[11px] text-slate-400"><i class="fa-regular fa-calendar mr-1"></i> <?= date('d M Y', strtotime($gl['tanggal'])) ?></span>
                            <h3 class="font-bold text-slate-800 text-sm mt-1 leading-snug line-clamp-2 group-hover:text-emerald-700 transition"><?= e($gl['judul']) ?></h3>
                            <?php if (!empty($gl['keterangan'])): ?>
                            <p class="text-xs text-slate-500 mt-1.5 line-clamp-2"><?= e($gl['keterangan']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ================= SECTION REGISTRASI ANGGOTA MANDIRI ================= -->
    <section id="daftar-anggota" class="py-16 sm:py-20 bg-slate-50 border-b border-slate-200 relative overflow-hidden">
        <!-- Dekorasi Background Lembut -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-emerald-100 rounded-full blur-3xl opacity-50 pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-teal-100 rounded-full blur-3xl opacity-50 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            
            <!-- Judul Section -->
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs font-black uppercase tracking-wider text-emerald-700 bg-emerald-100 border border-emerald-200 px-3.5 py-1.5 rounded-full inline-flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-address-card text-emerald-600"></i> Keanggotaan Pemustaka Mandiri
                </span>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 mt-3 tracking-tight">
                    Daftar Menjadi Anggota Perpustakaan
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 mt-2 leading-relaxed">
                    Daftarkan diri Anda atau putra-putri Anda secara online. Dapatkan akses bebas meminjam ratusan koleksi buku bacaan, edukasi, dan pemberdayaan masyarakat Desa Sejiram secara gratis!
                </p>
            </div>

            <!-- Kartu Notifikasi Hasil Pendaftaran -->
            <?php if ($pesanRegistrasi): ?>
                <?php if ($pesanRegistrasi['tipe'] === 'success'): ?>
                <div class="mb-12 bg-white border-2 border-emerald-500 rounded-3xl p-6 sm:p-8 shadow-xl max-w-3xl mx-auto relative overflow-hidden animate-fadeIn">
                    <div class="absolute top-0 right-0 bg-emerald-600 text-white text-[10px] uppercase font-black px-4 py-1 rounded-bl-xl tracking-wider">
                        Permohonan Baru
                    </div>
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-bold text-3xl shadow-lg flex-shrink-0">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="flex-1 space-y-3">
                            <span class="inline-flex items-center gap-1.5 bg-emerald-100 text-emerald-800 font-extrabold text-xs px-3 py-1 rounded-full uppercase tracking-wider">
                                <i class="fa-solid fa-paper-plane text-emerald-600"></i> Pendaftaran Terkirim
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 leading-tight">
                                Terima Kasih, <?= e($pesanRegistrasi['nama']) ?>!
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                <?= e($pesanRegistrasi['pesan']) ?>
                            </p>

                            <!-- Kotak Kode Registrasi Sementara -->
                            <div class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row justify-between items-center gap-4 mt-2">
                                <div class="text-center sm:text-left">
                                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kode Registrasi Sementara Anda</p>
                                    <p class="text-2xl font-black font-mono text-emerald-800 tracking-wider mt-0.5" id="teksKodeReg"><?= e($pesanRegistrasi['no_reg']) ?></p>
                                </div>
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    <button type="button" onclick="salinKodeReg('<?= e($pesanRegistrasi['no_reg']) ?>')" id="btnSalinReg" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow flex items-center gap-1.5">
                                        <i class="fa-regular fa-copy"></i> Salin Kode
                                    </button>
                                    <span class="bg-amber-100 text-amber-900 border border-amber-300 text-xs font-bold px-3 py-2 rounded-xl flex items-center gap-1.5">
                                        <i class="fa-solid fa-clock-rotate-left animate-spin text-amber-700"></i> Menunggu Persetujuan Admin
                                    </span>
                                </div>
                            </div>

                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span><i class="fa-solid fa-graduation-cap text-emerald-600 mr-1"></i> Jenjang: <strong><?= e($pesanRegistrasi['kategori']) ?></strong></span>
                                <span><i class="fa-solid fa-location-dot text-emerald-600 mr-1"></i> Asal: <strong><?= e($pesanRegistrasi['institusi']) ?></strong></span>
                                <span><i class="fa-brands fa-whatsapp text-emerald-600 mr-1"></i> WhatsApp: <strong><?= e($pesanRegistrasi['kontak']) ?></strong></span>
                            </div>

                            <p class="text-[11px] text-slate-400 italic">
                                * Simpan kode di atas atau tunjukkan nomor kontak Anda saat berkunjung ke Pojok Baca Pa'edah Desa Sejiram.
                            </p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-10 max-w-2xl mx-auto p-4 rounded-2xl flex items-start gap-3.5 <?= $pesanRegistrasi['tipe'] === 'error' ? 'bg-rose-50 border border-rose-300 text-rose-900' : ($pesanRegistrasi['tipe'] === 'warning' ? 'bg-amber-50 border border-amber-300 text-amber-900' : 'bg-sky-50 border border-sky-300 text-sky-900') ?>">
                    <div class="text-xl flex-shrink-0 mt-0.5">
                        <i class="fa-solid <?= $pesanRegistrasi['tipe'] === 'error' ? 'fa-circle-xmark text-rose-600' : ($pesanRegistrasi['tipe'] === 'warning' ? 'fa-triangle-exclamation text-amber-600' : 'fa-circle-info text-sky-600') ?>"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm"><?= e($pesanRegistrasi['judul']) ?></h4>
                        <p class="text-xs mt-1 leading-relaxed"><?= e($pesanRegistrasi['pesan']) ?></p>
                        <?php if (!empty($pesanRegistrasi['no_reg'])): ?>
                        <p class="text-xs font-mono font-bold mt-2 bg-white/60 px-2.5 py-1 rounded inline-block">
                            Kode: <?= e($pesanRegistrasi['no_reg']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Konten 2 Kolom: Alur & Keuntungan vs Formulir Registrasi -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
                
                <!-- Kolom Kiri: Manfaat & Alur Verifikasi -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider block mb-1">Kenapa Harus Jadi Anggota?</span>
                            <h3 class="text-lg sm:text-xl font-bold text-slate-900">Keuntungan Menjadi Pemustaka Resmi</h3>
                        </div>

                        <ul class="space-y-4 text-xs sm:text-sm text-slate-600">
                            <li class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                                    <i class="fa-solid fa-book-bookmark"></i>
                                </div>
                                <div>
                                    <strong class="text-slate-900 block font-bold">Peminjaman Buku Pulang Gratis</strong>
                                    <span>Bisa meminjam hingga <?= (int)$setting['durasi_pinjam_hari'] ?> hari untuk dibaca santai bersama keluarga di rumah.</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                                    <i class="fa-solid fa-children"></i>
                                </div>
                                <div>
                                    <strong class="text-slate-900 block font-bold">Ramah Anak & Bimbingan Belajar</strong>
                                    <span>Koleksi dongeng anak bergambar, buku tema sekolah SD, SMP, SMA, dan ruang baca santai.</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                                    <i class="fa-solid fa-seedling"></i>
                                </div>
                                <div>
                                    <strong class="text-slate-900 block font-bold">Edukasi Tani Jeruk & TP-PKK</strong>
                                    <span>Tersedia buku budidaya pertanian khas Sambas, kuliner, kesehatan, dan keterampilan ibu rumah tangga.</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center flex-shrink-0 font-bold text-sm">
                                    <i class="fa-solid fa-id-card"></i>
                                </div>
                                <div>
                                    <strong class="text-slate-900 block font-bold">Nomor Anggota Resmi Terdata</strong>
                                    <span>Terdaftar di sistem komputerisasi perpustakaan Desa Sejiram secara resmi dan tanpa biaya apapun.</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Alur Proses Pendaftaran -->
                    <div class="bg-gradient-to-br from-slate-900 to-emerald-950 text-white rounded-3xl p-6 sm:p-7 shadow-lg">
                        <h4 class="text-sm font-extrabold uppercase tracking-wider text-emerald-400 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-list-check"></i> Alur Persetujuan Keanggotaan
                        </h4>
                        <div class="space-y-4 text-xs text-slate-300">
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">1</span>
                                <div>
                                    <strong class="text-white block">Kirim Formulir Online</strong>
                                    <span>Masyarakat mengisi identitas diri dan mendapatkan kode pendaftaran sementara.</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">2</span>
                                <div>
                                    <strong class="text-white block">Pemeriksaan oleh Admin / Petugas</strong>
                                    <span>Admin akan melihat notifikasi pendaftar baru dan melakukan verifikasi data (Setujui / Tolak).</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">3</span>
                                <div>
                                    <strong class="text-white block">Penerbitan Nomor Anggota Resmi</strong>
                                    <span>Setelah disetujui, nomor anggota resmi (format AGT-XXXX) aktif dan siap digunakan untuk meminjam buku.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Formulir Registrasi Mandiri -->
                <div class="lg:col-span-7">
                    <div class="bg-white rounded-3xl p-6 sm:p-8 md:p-10 border border-slate-200/90 shadow-xl relative">
                        <div class="mb-6">
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900">Formulir Pendaftaran Pemustaka</h3>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1">Lengkapi data diri Anda di bawah ini dengan benar.</p>
                        </div>

                        <form method="POST" action="#daftar-anggota" class="space-y-5">
                            <input type="hidden" name="daftar_anggota" value="1">

                            <!-- Nama Lengkap -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Nama Lengkap <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i class="fa-solid fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           name="nama" 
                                           required 
                                           placeholder="Nama lengkap sesuai KTP / Kartu Pelajar..." 
                                           class="w-full text-slate-800 text-sm pl-10 pr-4 py-3 rounded-xl border border-slate-300 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm bg-slate-50/50">
                                </div>
                            </div>

                            <!-- Jenjang / Kategori -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Kategori / Jenjang <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                        </span>
                                        <select name="kategori_tingkat" required class="w-full text-slate-800 text-sm pl-10 pr-4 py-3 rounded-xl border border-slate-300 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm bg-slate-50/50">
                                            <option value="SD">Pelajar SD / MI</option>
                                            <option value="SMP">Pelajar SMP / MTs</option>
                                            <option value="SMA">Pelajar SMA / SMK / MA</option>
                                            <option value="Warga Umum" selected>Warga Umum / TP-PKK</option>
                                            <option value="Aparatur Desa">Aparatur Desa / RT / Dusun</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Kontak WhatsApp -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        No. WhatsApp / HP <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600">
                                            <i class="fa-brands fa-whatsapp font-bold text-base"></i>
                                        </span>
                                        <input type="tel" 
                                               name="kontak" 
                                               required 
                                               placeholder="Contoh: 081234567890" 
                                               class="w-full text-slate-800 text-sm pl-10 pr-4 py-3 rounded-xl border border-slate-300 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm bg-slate-50/50">
                                    </div>
                                    <span class="text-[10px] text-slate-400 mt-1 block">Untuk konfirmasi & pengingat pinjaman buku.</span>
                                </div>
                            </div>

                            <!-- Asal Sekolah / Dusun -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Asal Sekolah / Alamat Dusun <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i class="fa-solid fa-house-chimney"></i>
                                    </span>
                                    <input type="text" 
                                           name="institusi" 
                                           required 
                                           placeholder="Contoh: Dusun Sejiram / RT 04 / SDN 01 Sejiram..." 
                                           class="w-full text-slate-800 text-sm pl-10 pr-4 py-3 rounded-xl border border-slate-300 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition shadow-sm bg-slate-50/50">
                                </div>
                            </div>

                            <!-- Tombol Kirim Pendaftaran -->
                            <div class="pt-2">
                                <button type="submit" class="w-full bg-gradient-to-r from-emerald-700 to-teal-700 hover:from-emerald-800 hover:to-teal-800 text-white font-extrabold text-sm uppercase tracking-wider py-4 rounded-xl transition duration-200 shadow-lg hover:shadow-xl flex items-center justify-center gap-2 transform active:scale-[0.99]">
                                    <i class="fa-solid fa-paper-plane"></i> Kirim Pendaftaran Anggota
                                </button>
                            </div>

                            <!-- Jaminan Keamanan -->
                            <div class="pt-2 flex items-center justify-center gap-2 text-center text-[11px] text-slate-500">
                                <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                                <span>Pendaftaran 100% Gratis &bull; Data aman hanya untuk arsip Pojok Baca Desa Sejiram.</span>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Section Presensi Kunjungan Mandiri (Buku Tamu Digital) -->
    <section id="buku-tamu" class="py-16 bg-gradient-to-br from-emerald-800 to-teal-900 border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl p-8 sm:p-12 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10">
                    <div class="text-center max-w-xl mx-auto mb-8">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-300 bg-emerald-700/60 px-3 py-1 rounded-full inline-block mb-3">Presensi Pemustaka</span>
                        <h2 class="text-2xl sm:text-3xl font-black">Sedang Berkunjung? Isi Buku Tamu</h2>
                        <p class="text-xs sm:text-sm text-slate-200 mt-2">Bantu kami mencatat tingkat kunjungan perpustakaan desa untuk laporan penguatan literasi dan pengadaan buku.</p>
                    </div>

                    <?php if ($pesanPresensi): ?>
                    <div class="bg-emerald-100 border border-emerald-300 text-emerald-900 px-4 py-3 rounded-xl text-xs sm:text-sm mb-6 text-center font-bold">
                        <i class="fa-solid fa-circle-check mr-2 text-emerald-700"></i> <?= e($pesanPresensi) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="#buku-tamu" class="space-y-4 max-w-xl mx-auto bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/20">
                        <input type="hidden" name="kirim_presensi" value="1">
                        <div>
                            <label class="block text-xs font-bold mb-1 text-slate-100">Nama Lengkap Anda</label>
                            <input type="text" name="nama" required placeholder="Tuliskan nama lengkap..." class="w-full text-slate-800 text-sm px-3.5 py-2.5 rounded-xl border-0 outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold mb-1 text-slate-100">Kategori Pemustaka</label>
                                <select name="kategori_pemustaka" required class="w-full text-slate-800 text-sm px-3.5 py-2.5 rounded-xl border-0 outline-none focus:ring-2 focus:ring-emerald-400">
                                    <option value="SD">Pelajar SD</option>
                                    <option value="SMP">Pelajar SMP</option>
                                    <option value="SMA">Pelajar SMA</option>
                                    <option value="Warga Umum" selected>Warga Umum / TP-PKK</option>
                                    <option value="Aparatur Desa">Aparatur Desa</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold mb-1 text-slate-100">Asal Sekolah / Alamat Dusun</label>
                                <input type="text" name="asal_institusi" required placeholder="Misal: Dusun Sejiram / SMPN 1" class="w-full text-slate-800 text-sm px-3.5 py-2.5 rounded-xl border-0 outline-none focus:ring-2 focus:ring-emerald-400">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1 text-slate-100">Keperluan Kunjungan</label>
                            <input type="text" name="keperluan" required placeholder="Contoh: Membaca buku dongeng / Meminjam buku tani" class="w-full text-slate-800 text-sm px-3.5 py-2.5 rounded-xl border-0 outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-emerald-950 font-black text-xs uppercase tracking-wider py-3.5 rounded-xl transition shadow-lg mt-2">
                            Simpan Presensi Kunjungan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Publik -->
    <footer id="kontak" class="bg-slate-900 text-slate-300 py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-book-open-reader"></i>
                        </div>
                        <div>
                            <h4 class="font-extrabold text-white text-base leading-tight"><?= e($setting['nama_instansi']) ?></h4>
                            <p class="text-xs text-emerald-400"><?= e($setting['desa']) ?>, Kabupaten Sambas</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 max-w-sm leading-relaxed">
                        Pusat sarana literasi masyarakat, pojok baca anak, dan pembinaan keluarga PKK terpadu yang didukung oleh Pemerintah Desa Sejiram, Kecamatan Tebas, Kabupaten Sambas.
                    </p>
                </div>

                <div>
                    <h5 class="font-bold text-white text-xs uppercase tracking-wider mb-3">Tautan Cepat</h5>
                    <ul class="space-y-2 text-xs">
                        <li><a href="#katalog" class="hover:text-white transition">Katalog 7 Kategori Buku</a></li>
                        <li><a href="#daftar-anggota" class="hover:text-white text-emerald-400 font-semibold transition flex items-center gap-1.5"><i class="fa-solid fa-id-card text-[11px]"></i> Daftar Anggota Baru</a></li>
                        <li><a href="#artikel" class="hover:text-white transition">Artikel Literasi & Warta Desa</a></li>
                        <li><a href="#galeri" class="hover:text-white transition">Dokumentasi Kegiatan & PKK</a></li>
                        <li><a href="#buku-tamu" class="hover:text-white transition">Isi Presensi Pemustaka</a></li>
                    </ul>
                </div>

                <div>
                    <h5 class="font-bold text-white text-xs uppercase tracking-wider mb-3">Aturan Pinjam</h5>
                    <ul class="space-y-1.5 text-xs text-slate-400">
                        <li>Jam Buka: <strong class="text-white"><?= e(!empty($setting['jam_operasional']) ? $setting['jam_operasional'] : '08.00 - 15.30 WIB') ?></strong></li>
                        <li>Batas Waktu: <strong class="text-white"><?= (int)$setting['durasi_pinjam_hari'] ?> Hari</strong></li>
                        <li>Denda Terlambat: <strong class="text-white">Rp <?= number_format($setting['denda_per_hari'], 0, ',', '.') ?>/hari</strong></li>
                        <li class="pt-2"><a href="login.php" class="text-emerald-400 hover:underline font-semibold">&rarr; Portal Khusus Petugas</a></li>
                    </ul>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-800 text-center text-xs text-slate-500">
                &copy; <?= date('Y') ?> <?= e($setting['nama_instansi']) ?> <?= e($setting['desa']) ?>.
            </div>
        </div>
    </footer>

    <!-- ================= MODAL DETAIL FOTO GALERI ================= -->
    <div id="modalGaleri" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-5 overflow-y-auto" onclick="if(event.target === this) tutupModalGaleri()">
        <div class="relative bg-white rounded-2xl md:rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col my-auto border border-slate-200">
            <!-- Tombol Tutup Modal -->
            <button type="button" onclick="tutupModalGaleri()" class="absolute top-3 right-3 z-20 w-9 h-9 rounded-full bg-slate-900/70 hover:bg-slate-950 text-white flex items-center justify-center transition shadow-lg focus:outline-none" aria-label="Tutup Modal">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>

            <!-- Wadah Gambar Galeri Lengkap -->
            <div id="modalGaleriImgWrapper" class="relative bg-slate-950 flex items-center justify-center min-h-[220px] max-h-[60vh] sm:max-h-[70vh] overflow-hidden select-none">
                <img id="modalGaleriImg" src="" alt="" class="w-full h-auto max-h-[60vh] sm:max-h-[70vh] object-contain block">
                <div id="modalGaleriNoImg" class="hidden py-16 text-center text-slate-400">
                    <i class="fa-regular fa-image text-5xl mb-2 text-slate-500"></i>
                    <p class="text-xs">Foto dokumentasi tidak ditemukan pada server</p>
                </div>
            </div>

            <!-- Bagian Informasi: Kategori, Tanggal, Judul & Keterangan -->
            <div class="p-5 sm:p-6 bg-white space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span id="modalGaleriKategori" class="text-xs font-bold px-3 py-1 rounded-full shadow-sm"></span>
                    <span id="modalGaleriTanggal" class="text-xs font-semibold text-slate-400 flex items-center gap-1.5">
                        <i class="fa-regular fa-calendar text-slate-400"></i>
                        <span></span>
                    </span>
                </div>

                <h3 id="modalGaleriJudul" class="text-lg sm:text-xl font-bold text-slate-900 leading-snug"></h3>

                <div id="modalGaleriKetWrapper" class="pt-2 border-t border-slate-100">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-slate-400 mb-1">Keterangan Kegiatan</p>
                    <p id="modalGaleriKeterangan" class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL DETAIL BUKU (PORTAL) ================= -->
    <div id="modalDetailBuku" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-5 overflow-y-auto" onclick="if(event.target === this) tutupModalDetailBuku()">
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col my-auto border border-slate-200 animate-fadeIn max-h-[92vh]">
            
            <!-- Header Modal -->
            <div class="bg-gradient-to-r from-emerald-800 to-teal-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center text-emerald-300">
                        <i class="fa-solid fa-book-bookmark text-sm"></i>
                    </span>
                    <div>
                        <h3 class="font-bold text-sm leading-tight">Detail Koleksi Buku</h3>
                        <p class="text-[11px] text-emerald-200">Katalog Perpustakaan Pojok Baca Pa'edah</p>
                    </div>
                </div>
                <button type="button" onclick="tutupModalDetailBuku()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none" aria-label="Tutup Modal">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <!-- Body Modal (Scrollable) -->
            <div class="p-6 sm:p-7 overflow-y-auto space-y-6">
                <!-- Bagian Utama: Cover & Metadata Kunci -->
                <div class="flex flex-col sm:flex-row gap-5 items-start">
                    <!-- Cover Buku -->
                    <div class="w-32 sm:w-44 flex-shrink-0 mx-auto sm:mx-0">
                        <div class="aspect-[3/4] bg-slate-100 rounded-2xl overflow-hidden shadow-md border border-slate-200 relative flex items-center justify-center">
                            <img id="modalBukuCover" src="" alt="" class="w-full h-full object-cover hidden">
                            <div id="modalBukuNoCover" class="text-center p-4 text-slate-400">
                                <i class="fa-solid fa-book-open text-4xl mb-2 text-slate-300"></i>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pojok Baca</p>
                                <span class="text-[9px] text-slate-400">Tanpa Cover</span>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Judul, Kategori & Pengarang -->
                    <div class="flex-grow space-y-3 w-full">
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="modalBukuKategori" class="text-[11px] font-bold uppercase bg-emerald-100 text-emerald-800 px-3 py-0.5 rounded-full"></span>
                            <span id="modalBukuStokBadge" class="text-[11px] font-bold px-3 py-0.5 rounded-full shadow-sm"></span>
                        </div>

                        <h2 id="modalBukuJudul" class="text-lg sm:text-xl font-extrabold text-slate-900 leading-snug"></h2>

                        <div class="space-y-1.5 text-xs text-slate-600 pt-1">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-user-pen text-emerald-700 w-4 text-center"></i>
                                <span>Penulis: <strong id="modalBukuPenulis" class="text-slate-800"></strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-building text-emerald-700 w-4 text-center"></i>
                                <span>Penerbit: <strong id="modalBukuPenerbitTahun" class="text-slate-800"></strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-emerald-700 w-4 text-center"></i>
                                <span>Lokasi Rak: <span id="modalBukuRak" class="font-mono font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md text-[11px]"></span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid Spesifikasi Informasi Teknis Buku -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5">
                        <div class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium mb-1">
                            <i class="fa-solid fa-barcode text-emerald-600"></i>
                            <span>Kode Buku</span>
                        </div>
                        <p id="modalBukuKode" class="font-mono font-bold text-xs sm:text-sm text-slate-800 truncate"></p>
                    </div>

                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5">
                        <div class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium mb-1">
                            <i class="fa-solid fa-boxes-stacked text-emerald-600"></i>
                            <span>Status Stok</span>
                        </div>
                        <p id="modalBukuStokInfo" class="font-bold text-xs sm:text-sm truncate"></p>
                    </div>

                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5">
                        <div class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium mb-1">
                            <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                            <span>Kondisi Fisik</span>
                        </div>
                        <div id="modalBukuKondisiWrapper">
                            <span id="modalBukuKondisi" class="text-xs font-bold px-2 py-0.5 rounded"></span>
                        </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5">
                        <div class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium mb-1">
                            <i class="fa-solid fa-map-pin text-emerald-600"></i>
                            <span>Posisi Rak</span>
                        </div>
                        <p id="modalBukuLokasiRak" class="font-mono font-bold text-xs sm:text-sm text-slate-800 truncate"></p>
                    </div>
                </div>

                <!-- Kotak Edukasi / Petunjuk Layanan Peminjaman -->
                <div class="bg-emerald-50/70 border border-emerald-200/70 rounded-2xl p-4 flex items-start gap-3 text-xs text-emerald-950">
                    <span class="w-8 h-8 rounded-xl bg-emerald-700 text-white flex-shrink-0 flex items-center justify-center text-sm shadow-sm">
                        <i class="fa-solid fa-circle-question"></i>
                    </span>
                    <div class="space-y-1">
                        <h4 class="font-bold text-emerald-900">Cara Meminjam & Membaca di Tempat</h4>
                        <p class="text-emerald-800/90 leading-relaxed text-[11px] sm:text-xs">
                            Buku ini tersedia untuk dibaca langsung di ruang Pojok Baca atau dipinjam untuk dibawa pulang selama <strong><?= (int)$setting['durasi_pinjam_hari'] ?> hari</strong>. Silakan kunjungi meja petugas perpustakaan di Kantor Desa Sejiram dengan menyebutkan <strong>Kode Buku</strong> di atas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Modal -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3 flex-shrink-0">
                <a href="#buku-tamu" onclick="tutupModalDetailBuku()" class="inline-flex items-center gap-1.5 text-xs text-emerald-700 hover:text-emerald-900 font-bold transition">
                    <i class="fa-solid fa-pen-to-square"></i> Sedang berkunjung? Isi Buku Tamu
                </a>
                <button type="button" onclick="tutupModalDetailBuku()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl text-xs font-bold transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL BAGIKAN ARTIKEL (PORTAL) ================= -->
    <div id="modalSharePortal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto">
            <div class="bg-gradient-to-r from-emerald-800 to-teal-800 text-white px-6 py-4 flex justify-between items-center">
                <h3 class="font-bold text-sm flex items-center gap-2">
                    <i class="fa-solid fa-share-nodes text-emerald-300"></i> Bagikan Artikel & Kabar Desa
                </h3>
                <button type="button" onclick="tutupModalSharePortal()" class="text-emerald-200 hover:text-white transition">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <span id="shareModalKat" class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full"></span>
                    <h4 id="shareModalJudul" class="font-bold text-slate-900 text-sm mt-1.5 leading-snug"></h4>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-600 mb-3">Pilih Media Sosial:</p>
                    <div class="grid grid-cols-4 gap-3 text-center">
                        <a id="shareWaLink" href="#" target="_blank" rel="noopener noreferrer" class="p-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-2xl flex flex-col items-center justify-center gap-1 transition group">
                            <i class="fa-brands fa-whatsapp text-2xl group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold">WhatsApp</span>
                        </a>
                        <a id="shareFbLink" href="#" target="_blank" rel="noopener noreferrer" class="p-3 bg-blue-50 hover:bg-blue-100 text-[#1877F2] rounded-2xl flex flex-col items-center justify-center gap-1 transition group">
                            <i class="fa-brands fa-facebook-f text-2xl group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold">Facebook</span>
                        </a>
                        <a id="shareXLink" href="#" target="_blank" rel="noopener noreferrer" class="p-3 bg-slate-100 hover:bg-slate-200 text-slate-900 rounded-2xl flex flex-col items-center justify-center gap-1 transition group">
                            <i class="fa-brands fa-x-twitter text-2xl group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold">X (Twitter)</span>
                        </a>
                        <a id="shareTgLink" href="#" target="_blank" rel="noopener noreferrer" class="p-3 bg-sky-50 hover:bg-sky-100 text-[#229ED9] rounded-2xl flex flex-col items-center justify-center gap-1 transition group">
                            <i class="fa-brands fa-telegram text-2xl group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold">Telegram</span>
                        </a>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100">
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Tautan Langsung Artikel:</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="shareInputUrl" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-600 outline-none select-all">
                        <button type="button" onclick="salinUrlDariModal()" id="btnSalinModal" class="bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1 whitespace-nowrap shadow-sm">
                            <i class="fa-solid fa-copy"></i> Salin
                        </button>
                    </div>
                </div>

                <!-- Tombol Web Share Native untuk Smartphone -->
                <div id="wrapperNativeShare" class="hidden pt-2">
                    <button type="button" onclick="panggilNativeShareDariModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 rounded-xl text-xs transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-arrow-up-right-from-square text-emerald-600"></i> Opsi Berbagi Bawaan Perangkat
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Portal -->
    <div id="toastPortal" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 pointer-events-none transition-all duration-300 flex items-center gap-2.5 bg-slate-900 text-white text-xs font-semibold px-4 py-3 rounded-2xl shadow-xl border border-slate-700">
        <i id="toastPortalIcon" class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
        <span id="toastPortalMsg">Tautan berhasil disalin!</span>
    </div>

    <script>
    function bukaModalGaleri(el) {
        const modal = document.getElementById('modalGaleri');
        const img = document.getElementById('modalGaleriImg');
        const noImg = document.getElementById('modalGaleriNoImg');
        const kategori = document.getElementById('modalGaleriKategori');
        const tanggalText = document.getElementById('modalGaleriTanggal').querySelector('span');
        const judul = document.getElementById('modalGaleriJudul');
        const ket = document.getElementById('modalGaleriKeterangan');
        const ketWrapper = document.getElementById('modalGaleriKetWrapper');

        const foto = el.getAttribute('data-foto') || '';
        const kat = el.getAttribute('data-kategori') || 'Kegiatan';
        const jdl = el.getAttribute('data-judul') || '';
        const tgl = el.getAttribute('data-tanggal') || '';
        const keterangan = el.getAttribute('data-keterangan') || '';

        if (foto) {
            img.src = foto;
            img.alt = jdl;
            img.classList.remove('hidden');
            noImg.classList.add('hidden');
        } else {
            img.src = '';
            img.classList.add('hidden');
            noImg.classList.remove('hidden');
        }

        kategori.textContent = kat;
        if (kat === 'Kegiatan PKK') {
            kategori.className = 'text-xs font-bold px-3 py-1 rounded-full shadow-sm bg-rose-600 text-white';
        } else {
            kategori.className = 'text-xs font-bold px-3 py-1 rounded-full shadow-sm bg-emerald-700 text-white';
        }

        tanggalText.textContent = tgl;
        judul.textContent = jdl;

        if (keterangan.trim() !== '') {
            ket.textContent = keterangan;
            ketWrapper.classList.remove('hidden');
        } else {
            ketWrapper.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function tutupModalGaleri() {
        const modal = document.getElementById('modalGaleri');
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    // Fungsi Like & Share Portal
    let toastTimeoutPortal = null;
    function showToastPortal(msg, isSuccess = true) {
        const toast = document.getElementById('toastPortal');
        const toastMsg = document.getElementById('toastPortalMsg');
        const toastIcon = document.getElementById('toastPortalIcon');
        if (!toast) return;

        toastMsg.textContent = msg;
        toastIcon.className = isSuccess ? 'fa-solid fa-circle-check text-emerald-400 text-sm' : 'fa-solid fa-circle-exclamation text-rose-400 text-sm';
        toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-y-0', 'opacity-100');

        if (toastTimeoutPortal) clearTimeout(toastTimeoutPortal);
        toastTimeoutPortal = setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
            toast.classList.remove('translate-y-0', 'opacity-100');
        }, 3000);
    }

    function likeArtikelPortal(event, artikelId) {
        if (event) event.stopPropagation();
        const btn = document.getElementById('portalLikeBtn_' + artikelId);
        const icon = document.getElementById('portalLikeIcon_' + artikelId);
        const count = document.getElementById('portalLikeCount_' + artikelId);

        if (btn) btn.disabled = true;

        fetch('like_artikel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: artikelId, action: 'toggle' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (count) count.textContent = data.likes;
                if (btn && icon) {
                    if (data.liked) {
                        btn.className = "inline-flex items-center gap-1.5 px-2 py-1 rounded-lg transition bg-rose-50 text-rose-600 font-bold";
                        icon.className = "fa-solid fa-heart text-rose-500 text-xs";
                        showToastPortal(data.message, true);
                    } else {
                        btn.className = "inline-flex items-center gap-1.5 px-2 py-1 rounded-lg transition text-slate-500 hover:text-rose-600 hover:bg-rose-50";
                        icon.className = "fa-regular fa-heart text-rose-500 text-xs";
                        showToastPortal(data.message, false);
                    }
                }
            } else {
                showToastPortal(data.message || 'Gagal memproses suka.', false);
            }
        })
        .catch(() => {
            showToastPortal('Gagal menghubungi server.', false);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
        });
    }

    let activeShareArt = null;

    function bukaModalSharePortal(event, target) {
        if (event) event.stopPropagation();
        let art = target;
        if (target instanceof HTMLElement) {
            art = {
                id: target.getAttribute('data-id'),
                judul: target.getAttribute('data-judul') || '',
                kategori: target.getAttribute('data-kategori') || 'Artikel',
                url: target.getAttribute('data-url') || ''
            };
        }
        activeShareArt = art;

        document.getElementById('shareModalKat').textContent = art.kategori || 'Artikel';
        document.getElementById('shareModalJudul').textContent = art.judul || '';
        document.getElementById('shareInputUrl').value = art.url;

        const shareText = encodeURIComponent(art.judul + "\n" + art.url);
        const encUrl = encodeURIComponent(art.url);
        const encTitle = encodeURIComponent(art.judul);

        document.getElementById('shareWaLink').href = 'https://api.whatsapp.com/send?text=' + shareText;
        document.getElementById('shareFbLink').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encUrl;
        document.getElementById('shareXLink').href = 'https://twitter.com/intent/tweet?url=' + encUrl + '&text=' + encTitle;
        document.getElementById('shareTgLink').href = 'https://t.me/share/url?url=' + encUrl + '&text=' + encTitle;

        if (navigator.share) {
            document.getElementById('wrapperNativeShare').classList.remove('hidden');
        }

        document.getElementById('modalSharePortal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function tutupModalSharePortal() {
        document.getElementById('modalSharePortal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function salinUrlDariModal() {
        const input = document.getElementById('shareInputUrl');
        const btn = document.getElementById('btnSalinModal');
        if (!input) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(() => {
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
                showToastPortal('Tautan artikel berhasil disalin!');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i> Salin';
                }, 2500);
            }).catch(() => {
                input.select();
                document.execCommand('copy');
                showToastPortal('Tautan artikel berhasil disalin!');
            });
        } else {
            input.select();
            document.execCommand('copy');
            showToastPortal('Tautan artikel berhasil disalin!');
        }
    }

    function panggilNativeShareDariModal() {
        if (navigator.share && activeShareArt) {
            navigator.share({
                title: activeShareArt.judul,
                text: activeShareArt.judul + ' - Pojok Baca Pa\'edah Desa Sejiram',
                url: activeShareArt.url
            }).catch(() => {});
        }
    }

    // Fungsi Modal Detail Buku Portal
    function bukaModalDetailBuku(el) {
        const modal = document.getElementById('modalDetailBuku');
        if (!modal || !el) return;

        const kode = el.getAttribute('data-kode') || '-';
        const judul = el.getAttribute('data-judul') || 'Tanpa Judul';
        const penulis = el.getAttribute('data-penulis') || '-';
        const penerbit = el.getAttribute('data-penerbit') || '-';
        const tahun = el.getAttribute('data-tahun') || '-';
        const kategori = el.getAttribute('data-kategori') || 'Buku';
        const rak = el.getAttribute('data-rak') || '-';
        const stokTotal = parseInt(el.getAttribute('data-stok-total') || '0', 10);
        const stokTersedia = parseInt(el.getAttribute('data-stok-tersedia') || '0', 10);
        const kondisi = el.getAttribute('data-kondisi') || 'Baik';
        const cover = el.getAttribute('data-cover') || '';

        // Teks & Info
        document.getElementById('modalBukuJudul').textContent = judul;
        document.getElementById('modalBukuKode').textContent = kode;
        document.getElementById('modalBukuPenulis').textContent = penulis;
        document.getElementById('modalBukuPenerbitTahun').textContent = penerbit + (tahun !== '-' ? ' (' + tahun + ')' : '');
        document.getElementById('modalBukuKategori').textContent = kategori;
        document.getElementById('modalBukuRak').textContent = rak;
        document.getElementById('modalBukuLokasiRak').textContent = rak;

        // Cover
        const imgCover = document.getElementById('modalBukuCover');
        const noCover = document.getElementById('modalBukuNoCover');
        if (cover) {
            imgCover.src = cover;
            imgCover.alt = judul;
            imgCover.classList.remove('hidden');
            noCover.classList.add('hidden');
        } else {
            imgCover.src = '';
            imgCover.classList.add('hidden');
            noCover.classList.remove('hidden');
        }

        // Badge & Info Stok
        const badgeStok = document.getElementById('modalBukuStokBadge');
        const infoStok = document.getElementById('modalBukuStokInfo');
        if (stokTersedia > 0) {
            badgeStok.className = 'text-[11px] font-bold px-3 py-0.5 rounded-full shadow-sm bg-emerald-600 text-white';
            badgeStok.innerHTML = '<i class="fa-solid fa-circle-check mr-1 text-[10px]"></i> Tersedia ' + stokTersedia + ' Buku';
            infoStok.className = 'font-bold text-xs sm:text-sm text-emerald-700 truncate';
            infoStok.textContent = stokTersedia + ' / ' + stokTotal + ' Tersedia';
        } else {
            badgeStok.className = 'text-[11px] font-bold px-3 py-0.5 rounded-full shadow-sm bg-rose-600 text-white';
            badgeStok.innerHTML = '<i class="fa-solid fa-circle-xmark mr-1 text-[10px]"></i> Sedang Dipinjam';
            infoStok.className = 'font-bold text-xs sm:text-sm text-rose-600 truncate';
            infoStok.textContent = 'Kosong (0/' + stokTotal + ')';
        }

        // Kondisi
        const elKondisi = document.getElementById('modalBukuKondisi');
        let kondisiClass = 'bg-emerald-100 text-emerald-800';
        if (kondisi === 'Rusak Ringan') kondisiClass = 'bg-amber-100 text-amber-800';
        if (kondisi === 'Rusak Parah') kondisiClass = 'bg-rose-100 text-rose-800';
        elKondisi.className = 'text-xs font-bold px-2 py-0.5 rounded ' + kondisiClass;
        elKondisi.textContent = kondisi;

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function tutupModalDetailBuku() {
        const modal = document.getElementById('modalDetailBuku');
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    function salinKodeReg(kode) {
        if (!kode) return;
        navigator.clipboard.writeText(kode).then(() => {
            const btn = document.getElementById('btnSalinReg');
            if (btn) {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check text-emerald-300"></i> Tersalin!';
                btn.classList.add('bg-emerald-900');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-emerald-900');
                }, 2000);
            }
        }).catch(err => {
            alert('Kode pendaftaran Anda: ' + kode);
        });
    }

    // Tutup modal ketika tombol Escape ditekan atau klik backdrop
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            tutupModalGaleri();
            tutupModalDetailBuku();
            tutupModalSharePortal();
        }
    });

    window.addEventListener('click', function(e) {
        const modalShare = document.getElementById('modalSharePortal');
        if (e.target === modalShare) {
            tutupModalSharePortal();
        }
        const modalDetail = document.getElementById('modalDetailBuku');
        if (e.target === modalDetail) {
            tutupModalDetailBuku();
        }
        const modalGaleri = document.getElementById('modalGaleri');
        if (e.target === modalGaleri) {
            tutupModalGaleri();
        }
    });
    </script>

</body>
</html>
