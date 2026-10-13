<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: portal.php#artikel');
    exit;
}

// Tambah view counter
$pdo->prepare("UPDATE artikel SET views = views + 1 WHERE id = ?")->execute([$id]);

// Ambil artikel
$stmt = $pdo->prepare("SELECT * FROM artikel WHERE id = ? AND status = 'Publish' LIMIT 1");
$stmt->execute([$id]);
$artikel = $stmt->fetch();

if (!$artikel) {
    header('Location: portal.php#artikel');
    exit;
}

// Ambil artikel terkait / artikel lainnya
$stmtLain = $pdo->prepare("SELECT id, judul, tanggal, kategori FROM artikel WHERE id != ? AND status = 'Publish' ORDER BY id DESC LIMIT 4");
$stmtLain->execute([$id]);
$artikelLain = $stmtLain->fetchAll();

$setting = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($artikel['judul']) ?> - Pojok Baca Desa Sejiram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col min-h-screen">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="portal.php" class="flex items-center gap-2 text-emerald-800 font-bold text-sm hover:text-emerald-950 transition">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Portal Publik
            </a>
            <span class="text-xs font-semibold text-slate-400">Pojok Baca Desa Sejiram</span>
        </div>
    </header>

    <main class="flex-1 py-10">
        <div class="max-w-4xl mx-auto px-4">
            <article class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-sm">
                <div class="mb-6">
                    <span class="inline-block bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full mb-3">
                        <?= e($artikel['kategori']) ?>
                    </span>
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">
                        <?= e($artikel['judul']) ?>
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400 mt-4 pb-4 border-b">
                        <span><i class="fa-regular fa-calendar mr-1"></i> <?= date('d F Y', strtotime($artikel['tanggal'])) ?></span>
                        <span><i class="fa-solid fa-user-pen mr-1"></i> <?= e($artikel['penulis']) ?></span>
                        <span><i class="fa-solid fa-eye mr-1"></i> <?= (int)$artikel['views'] ?> kali dibaca</span>
                    </div>
                </div>

                <?php 
                $gbrPath = !empty($artikel['gambar']) ? 'uploads/artikel/' . $artikel['gambar'] : null;
                if ($gbrPath && file_exists(__DIR__ . '/' . $gbrPath)): 
                ?>
                <div class="mb-8 rounded-2xl overflow-hidden shadow-sm aspect-video bg-slate-100 flex items-center justify-center">
                    <img src="<?= e($gbrPath) ?>" alt="<?= e($artikel['judul']) ?>" class="w-full h-full object-cover">
                </div>
                <?php endif; ?>

                <div class="prose max-w-none text-slate-700 text-sm sm:text-base leading-relaxed space-y-4">
                    <?= nl2br(e($artikel['konten'])) ?>
                </div>

                <div class="mt-12 pt-6 border-t flex justify-between items-center text-xs">
                    <a href="portal.php#artikel" class="text-emerald-700 font-bold hover:underline">
                        &larr; Lihat Artikel Lainnya
                    </a>
                    <a href="portal.php#buku-tamu" class="bg-emerald-700 text-white px-4 py-2 rounded-xl font-semibold hover:bg-emerald-800">
                        Isi Buku Tamu Kunjungan
                    </a>
                </div>
            </article>

            <!-- Artikel Terkait -->
            <?php if (!empty($artikelLain)): ?>
            <div class="mt-10">
                <h3 class="font-bold text-slate-900 text-lg mb-4">Artikel Lainnya</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($artikelLain as $al): ?>
                    <a href="baca_artikel.php?id=<?= $al['id'] ?>" class="bg-white p-4 rounded-2xl border border-slate-200 hover:border-emerald-500 shadow-sm transition block">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase"><?= e($al['kategori']) ?></span>
                        <h4 class="font-bold text-slate-800 text-sm mt-1 line-clamp-2"><?= e($al['judul']) ?></h4>
                        <span class="text-xs text-slate-400 mt-2 block"><?= date('d/m/Y', strtotime($al['tanggal'])) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-slate-900 text-slate-400 text-xs py-6 text-center">
        &copy; <?= date('Y') ?> Pojok Baca Pa'edah Desa Sejiram. Seluruh hak cipta dilindungi.
    </footer>

</body>
</html>
