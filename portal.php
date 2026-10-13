<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_presensi'])) {
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

                <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-slate-600">
                    <a href="#beranda" class="hover:text-emerald-700 transition">Beranda</a>
                    <a href="#katalog" class="hover:text-emerald-700 transition">Katalog Buku</a>
                    <a href="#artikel" class="hover:text-emerald-700 transition">Artikel & Literasi</a>
                    <a href="#galeri" class="hover:text-emerald-700 transition">Galeri & PKK</a>
                    <a href="#buku-tamu" class="hover:text-emerald-700 transition">Buku Tamu</a>
                    <a href="#kontak" class="hover:text-emerald-700 transition">Kontak</a>
                </nav>

                <div class="md:hidden">
                    <a href="#katalog" class="text-xs bg-emerald-700 text-white px-3 py-2 rounded-lg font-semibold">Cari Buku</a>
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
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition duration-200">
                    <div>
                        <div class="aspect-[3/4] bg-slate-100 relative overflow-hidden flex items-center justify-center border-b">
                            <?php if ($hasCover): ?>
                                <img src="<?= e($coverFile) ?>" alt="<?= e($bk['judul']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="text-center p-3 text-slate-400">
                                    <i class="fa-solid fa-book text-3xl mb-1 text-slate-300"></i>
                                    <p class="text-[10px] font-semibold uppercase">Pojok Baca</p>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded-md shadow-sm <?= $bk['stok_tersedia'] > 0 ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' ?>">
                                <?= $bk['stok_tersedia'] > 0 ? 'Ready ' . $bk['stok_tersedia'] : 'Kosong' ?>
                            </span>
                        </div>
                        <div class="p-3">
                            <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide block truncate"><?= e($bk['kategori']) ?></span>
                            <h3 class="font-bold text-slate-800 text-xs mt-1 line-clamp-2 leading-tight" title="<?= e($bk['judul']) ?>"><?= e($bk['judul']) ?></h3>
                            <p class="text-[11px] text-slate-500 mt-1 truncate"><?= e($bk['penulis']) ?></p>
                        </div>
                    </div>
                    <div class="px-3 py-2 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-[11px] text-slate-500">
                        <span class="font-mono text-emerald-800 font-bold"><?= e($bk['lokasi_rak']) ?></span>
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
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-xs">
                        <span class="text-slate-400"><i class="fa-solid fa-eye mr-1"></i> <?= (int)$art['views'] ?> dibaca</span>
                        <a href="baca_artikel.php?id=<?= $art['id'] ?>" class="text-emerald-700 hover:text-emerald-900 font-bold flex items-center gap-1">
                            Baca Selengkapnya <i class="fa-solid fa-arrow-right text-[10px]"></i>
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

    // Tutup modal ketika tombol Escape ditekan
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            tutupModalGaleri();
        }
    });
    </script>

</body>
</html>
