<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: portal.php#ebook');
    exit;
}

$isStaffLoggedIn = isset($_SESSION['user_id']);
if ($isStaffLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM ebook WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM ebook WHERE id = ? AND status = 'Publish' LIMIT 1");
    $stmt->execute([$id]);
}
$ebook = $stmt->fetch();

if (!$ebook) {
    header('Location: portal.php#ebook');
    exit;
}

$pdfFileName = basename((string)$ebook['file_pdf']);
$pdfRelUrl = 'uploads/ebooks/' . rawurlencode($pdfFileName);
$pdfFullPath = __DIR__ . '/uploads/ebooks/' . $pdfFileName;
$pdfExists = ($pdfFileName !== '' && file_exists($pdfFullPath) && is_file($pdfFullPath));

// Mode Unduh File PDF
if (($_GET['action'] ?? '') === 'download') {
    if (!$pdfExists) {
        http_response_code(404);
        die('Maaf, berkas PDF e-book tidak ditemukan di server.');
    }

    $pdo->prepare("UPDATE ebook SET downloads = downloads + 1 WHERE id = ?")->execute([$id]);

    $safeTitle = trim((string)preg_replace('/[^A-Za-z0-9\-_]+/', '_', (string)$ebook['judul']), '_');
    if ($safeTitle === '') {
        $safeTitle = 'ebook_' . $id;
    }
    $downloadName = $safeTitle . '.pdf';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($pdfFullPath));
    readfile($pdfFullPath);
    exit;
}

// Tambah view counter berbasis session agar akurat
if (!isset($_SESSION['viewed_ebooks']) || !is_array($_SESSION['viewed_ebooks'])) {
    $_SESSION['viewed_ebooks'] = [];
}
if (!in_array($id, $_SESSION['viewed_ebooks'], true)) {
    $pdo->prepare("UPDATE ebook SET views = views + 1 WHERE id = ?")->execute([$id]);
    $_SESSION['viewed_ebooks'][] = $id;
    $ebook['views'] = (int)$ebook['views'] + 1;
}

function formatUkuranEbook(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

$setting = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch() ?: [
    'nama_instansi' => 'Pojok Baca Pa\'edah',
    'desa' => 'Desa Sejiram'
];

// E-Book lainnya
$stmtLain = $pdo->prepare("SELECT id, judul, penulis, tahun, kategori, cover, ukuran_file, views, downloads FROM ebook WHERE id != ? AND status = 'Publish' ORDER BY id DESC LIMIT 4");
$stmtLain->execute([$id]);
$ebookLain = $stmtLain->fetchAll();

// Format URL & Teks Berbagi
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = $protocol . $host . ($_SERVER['REQUEST_URI'] ?? '');
$shareTextWa = rawurlencode("Baca & Unduh E-Book Gratis: " . $ebook['judul'] . " (" . $ebook['penulis'] . ")\n\n" . $currentUrl);
$shareUrlEncoded = rawurlencode($currentUrl);
$shareTitleEncoded = rawurlencode($ebook['judul']);

$coverPath = !empty($ebook['cover']) ? 'uploads/covers/' . $ebook['cover'] : null;
$hasCover = $coverPath && file_exists(__DIR__ . '/' . $coverPath);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ebook['judul']) ?> - E-Book <?= e($setting['nama_instansi']) ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        #pdfReaderContainer:fullscreen {
            background: #0f172a;
            padding: 0;
            width: 100vw;
            height: 100vh;
            border-radius: 0;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col min-h-screen">

    <!-- Header Navigasi Reader -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <a href="portal.php#ebook" class="inline-flex items-center gap-2 text-emerald-800 hover:text-emerald-950 font-bold text-xs sm:text-sm bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-xl transition flex-shrink-0">
                    <i class="fa-solid fa-arrow-left"></i> <span class="hidden sm:inline">Katalog E-Book</span>
                </a>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="bg-rose-100 text-rose-700 text-[10px] font-extrabold px-2 py-0.5 rounded uppercase">PDF</span>
                        <span class="text-[11px] font-semibold text-emerald-700 truncate"><?= e($ebook['kategori']) ?></span>
                        <?php if ($ebook['status'] === 'Draft'): ?>
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded">Pratinjau Draft</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="font-extrabold text-slate-900 text-sm sm:text-base truncate" title="<?= e($ebook['judul']) ?>">
                        <?= e($ebook['judul']) ?>
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($pdfExists): ?>
                <button type="button" onclick="toggleFullscreenPdf()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5" title="Mode Layar Penuh">
                    <i class="fa-solid fa-expand"></i> <span class="hidden md:inline">Layar Penuh</span>
                </button>
                <a href="<?= e($pdfRelUrl) ?>" target="_blank" rel="noopener" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5" title="Buka PDF di Tab Baru">
                    <i class="fa-solid fa-up-right-from-square"></i> <span class="hidden sm:inline">Tab Baru</span>
                </a>
                <a href="baca_ebook.php?id=<?= (int)$ebook['id'] ?>&action=download" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Unduh PDF (<?= e(formatUkuranEbook((int)$ebook['ukuran_file'])) ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-1 py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- Area Pembaca PDF Langsung -->
            <div id="pdfReaderContainer" class="bg-slate-900 rounded-2xl sm:rounded-3xl overflow-hidden shadow-xl border border-slate-200 flex flex-col">
                <?php if ($pdfExists): ?>
                    <div class="bg-slate-800 text-slate-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs border-b border-slate-700">
                        <div class="flex items-center gap-3">
                            <span><i class="fa-solid fa-file-pdf text-rose-400 mr-1.5"></i> Pembaca E-Book Langsung</span>
                            <span class="hidden sm:inline text-slate-400">&bull;</span>
                            <span class="hidden sm:inline text-slate-300"><?= e($ebook['penulis']) ?></span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-300">
                            <span><i class="fa-solid fa-eye text-emerald-400 mr-1"></i> <?= (int)$ebook['views'] ?> dibaca</span>
                            <span><i class="fa-solid fa-download text-sky-400 mr-1"></i> <?= (int)$ebook['downloads'] ?> diunduh</span>
                        </div>
                    </div>

                    <div class="relative w-full h-[70vh] sm:h-[78vh] bg-slate-800">
                        <object data="<?= e($pdfRelUrl) ?>#toolbar=1&view=FitH" type="application/pdf" class="w-full h-full">
                            <iframe src="<?= e($pdfRelUrl) ?>#toolbar=1&view=FitH" class="w-full h-full border-0" title="Pembaca PDF <?= e($ebook['judul']) ?>">
                                <div class="p-8 text-center text-white space-y-4">
                                    <i class="fa-solid fa-file-pdf text-5xl text-rose-400"></i>
                                    <p class="text-sm font-semibold">Peramban Anda tidak mendukung pratinjau PDF secara langsung.</p>
                                    <div class="flex flex-wrap justify-center gap-3">
                                        <a href="<?= e($pdfRelUrl) ?>" target="_blank" class="bg-white text-slate-900 px-4 py-2 rounded-xl text-xs font-bold">Buka File PDF</a>
                                        <a href="baca_ebook.php?id=<?= (int)$ebook['id'] ?>&action=download" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-bold">Unduh PDF</a>
                                    </div>
                                </div>
                            </iframe>
                        </object>
                    </div>

                    <!-- Bantuan khusus perangkat mobile -->
                    <div class="sm:hidden bg-slate-800/95 border-t border-slate-700 p-3 flex items-center justify-between gap-2 text-xs text-slate-200">
                        <span class="text-[11px] text-slate-300">Tampilan PDF kurang nyaman di layar HP?</span>
                        <div class="flex gap-2 flex-shrink-0">
                            <a href="<?= e($pdfRelUrl) ?>" target="_blank" class="bg-slate-700 text-white px-3 py-1.5 rounded-lg font-bold text-[11px]">Buka Penuh</a>
                            <a href="baca_ebook.php?id=<?= (int)$ebook['id'] ?>&action=download" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg font-bold text-[11px]">Unduh</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-12 text-center text-slate-300 space-y-3">
                        <i class="fa-solid fa-triangle-exclamation text-5xl text-amber-400"></i>
                        <h2 class="text-lg font-bold text-white">Berkas PDF Tidak Ditemukan</h2>
                        <p class="text-xs text-slate-400 max-w-md mx-auto">Mohon maaf, berkas dokumen PDF untuk buku ini belum tersedia atau telah dipindahkan di server.</p>
                        <a href="portal.php#ebook" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Katalog E-Book
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Detail Informasi & Sinopsis Buku + Bagikan -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-5">
                    <div class="flex flex-col sm:flex-row gap-5 items-start">
                        <div class="w-28 sm:w-36 flex-shrink-0">
                            <div class="aspect-[3/4] rounded-2xl overflow-hidden border border-slate-200 bg-slate-100 shadow-sm flex items-center justify-center">
                                <?php if ($hasCover): ?>
                                    <img src="<?= e($coverPath) ?>" alt="<?= e($ebook['judul']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="text-center p-3 text-rose-500">
                                        <i class="fa-solid fa-file-pdf text-4xl mb-1"></i>
                                        <p class="text-[10px] font-extrabold uppercase">E-Book PDF</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-0.5 rounded-full"><?= e($ebook['kategori']) ?></span>
                                <span class="bg-slate-100 text-slate-700 font-mono text-xs font-bold px-2.5 py-0.5 rounded-full"><?= e($ebook['kode_ebook']) ?></span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-snug"><?= e($ebook['judul']) ?></h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600 pt-1">
                                <div><i class="fa-solid fa-user-pen text-emerald-600 w-4 mr-1"></i> Penulis: <strong class="text-slate-800"><?= e($ebook['penulis']) ?></strong></div>
                                <div><i class="fa-solid fa-building text-emerald-600 w-4 mr-1"></i> Penerbit: <strong class="text-slate-800"><?= e($ebook['penerbit']) ?> (<?= (int)$ebook['tahun'] ?>)</strong></div>
                                <div><i class="fa-solid fa-hard-drive text-emerald-600 w-4 mr-1"></i> Ukuran File: <strong class="text-slate-800"><?= e(formatUkuranEbook((int)$ebook['ukuran_file'])) ?></strong></div>
                                <div><i class="fa-solid fa-chart-simple text-emerald-600 w-4 mr-1"></i> Akses: <strong class="text-slate-800"><?= (int)$ebook['views'] ?> baca &bull; <?= (int)$ebook['downloads'] ?> unduh</strong></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($ebook['deskripsi'])): ?>
                    <div class="pt-4 border-t border-slate-100">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">Sinopsis / Deskripsi Buku</h3>
                        <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line"><?= e($ebook['deskripsi']) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Bagikan E-Book -->
                    <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-share-nodes text-emerald-600"></i> Bagikan E-Book Ini:
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="https://api.whatsapp.com/send?text=<?= $shareTextWa ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 transition">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-2 rounded-xl bg-[#1877F2] hover:opacity-90 text-white text-xs font-bold flex items-center gap-1.5 transition">
                                <i class="fa-brands fa-facebook-f"></i> Facebook
                            </a>
                            <a href="https://t.me/share/url?url=<?= $shareUrlEncoded ?>&text=<?= $shareTitleEncoded ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-2 rounded-xl bg-[#229ED9] hover:opacity-90 text-white text-xs font-bold flex items-center gap-1.5 transition">
                                <i class="fa-brands fa-telegram"></i> Telegram
                            </a>
                            <button type="button" onclick="salinTautanEbook(this)" class="px-3 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold flex items-center gap-1.5 transition">
                                <i class="fa-solid fa-copy"></i> Salin Tautan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- E-Book Lainnya -->
                <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                            <i class="fa-solid fa-book-open text-emerald-600"></i> E-Book Lainnya
                        </h3>
                        <a href="portal.php#ebook" class="text-xs font-bold text-emerald-700 hover:underline">Semua &rarr;</a>
                    </div>

                    <?php if (empty($ebookLain)): ?>
                    <p class="text-xs text-slate-400 py-6 text-center">Belum ada koleksi e-book lainnya.</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($ebookLain as $el): ?>
                        <a href="baca_ebook.php?id=<?= (int)$el['id'] ?>" class="block p-3 rounded-2xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/40 transition group">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-12 rounded-lg bg-rose-50 border border-rose-200 text-rose-600 flex flex-col items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-file-pdf text-base"></i>
                                    <span class="text-[8px] font-bold">PDF</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-bold text-emerald-700 uppercase block truncate"><?= e($el['kategori']) ?></span>
                                    <h4 class="font-bold text-xs text-slate-800 group-hover:text-emerald-800 line-clamp-2 leading-snug"><?= e($el['judul']) ?></h4>
                                    <p class="text-[11px] text-slate-500 mt-1 truncate"><?= e($el['penulis']) ?> &bull; <?= e(formatUkuranEbook((int)$el['ukuran_file'])) ?></p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <footer class="bg-slate-900 text-slate-400 py-6 text-center text-xs mt-auto">
        &copy; <?= date('Y') ?> <?= e($setting['nama_instansi']) ?> <?= e($setting['desa']) ?>. Layanan Literasi Digital Terbuka.
    </footer>

    <script>
    function toggleFullscreenPdf() {
        const container = document.getElementById('pdfReaderContainer');
        if (!container) return;
        if (!document.fullscreenElement) {
            container.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen().catch(() => {});
        }
    }

    function salinTautanEbook(btn) {
        const url = window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check text-emerald-700"></i> Tersalin!';
                setTimeout(() => { btn.innerHTML = orig; }, 2000);
            });
        } else {
            prompt('Salin tautan e-book ini:', url);
        }
    }
    </script>
</body>
</html>

