<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$stmtLain = $pdo->prepare("SELECT id, judul, tanggal, kategori, likes, views FROM artikel WHERE id != ? AND status = 'Publish' ORDER BY id DESC LIMIT 4");
$stmtLain->execute([$id]);
$artikelLain = $stmtLain->fetchAll();

$setting = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

// Format URL & Teks untuk Berbagi
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = $protocol . $host . $_SERVER['REQUEST_URI'];
$cleanExcerpt = mb_substr(strip_tags($artikel['konten']), 0, 160) . '...';
$shareTextWa = rawurlencode($artikel['judul'] . "\n\n" . $cleanExcerpt . "\n\nBaca selengkapnya di: " . $currentUrl);
$shareUrlEncoded = rawurlencode($currentUrl);
$shareTitleEncoded = rawurlencode($artikel['judul']);

$gbrPath = !empty($artikel['gambar']) ? 'uploads/artikel/' . $artikel['gambar'] : null;
$hasGbr = $gbrPath && file_exists(__DIR__ . '/' . $gbrPath);
$ogImageUrl = $hasGbr ? ($protocol . $host . '/' . $gbrPath) : '';

$isLiked = in_array($id, $_SESSION['liked_articles'] ?? [], true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($artikel['judul']) ?> - Pojok Baca Desa Sejiram</title>
    
    <!-- Open Graph / Meta Berbagi Media Sosial -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= e($artikel['judul']) ?>">
    <meta property="og:description" content="<?= e($cleanExcerpt) ?>">
    <meta property="og:url" content="<?= e($currentUrl) ?>">
    <?php if ($ogImageUrl): ?>
    <meta property="og:image" content="<?= e($ogImageUrl) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($artikel['judul']) ?>">
    <meta name="twitter:description" content="<?= e($cleanExcerpt) ?>">

    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
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
            <span class="text-xs font-semibold text-slate-400">Pojok Baca Pa'edah Desa Sejiram</span>
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
                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 mt-4 pb-4 border-b">
                        <span><i class="fa-regular fa-calendar mr-1 text-emerald-600"></i> <?= date('d F Y', strtotime($artikel['tanggal'])) ?></span>
                        <span><i class="fa-solid fa-user-pen mr-1 text-emerald-600"></i> <?= e($artikel['penulis']) ?></span>
                        <span><i class="fa-solid fa-eye mr-1 text-slate-400"></i> <?= (int)$artikel['views'] ?> kali dibaca</span>
                        <span class="text-rose-600 font-semibold"><i class="fa-solid fa-heart mr-1"></i> <span id="metaLikeCount"><?= (int)($artikel['likes'] ?? 0) ?></span> disukai</span>
                    </div>
                </div>

                <?php if ($hasGbr): ?>
                <div class="mb-8 rounded-2xl overflow-hidden shadow-sm aspect-video bg-slate-100 flex items-center justify-center">
                    <img src="<?= e($gbrPath) ?>" alt="<?= e($artikel['judul']) ?>" class="w-full h-full object-cover">
                </div>
                <?php endif; ?>

                <div class="prose max-w-none text-slate-700 text-sm sm:text-base leading-relaxed space-y-4">
                    <?= nl2br(e($artikel['konten'])) ?>
                </div>

                <!-- ================= SEKSI LIKE & SHARE ================= -->
                <div class="mt-10 pt-8 border-t border-slate-200">
                    <div class="bg-gradient-to-br from-slate-50 via-white to-emerald-50/40 rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm">
                        <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                            
                            <!-- Fitur Like (Suka) -->
                            <div class="flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left w-full lg:w-auto">
                                <button type="button" id="btnLikeMain" onclick="toggleLike(<?= $artikel['id'] ?>)" 
                                        class="group relative inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-2xl font-bold text-sm shadow-sm transition-all duration-300 transform active:scale-95 cursor-pointer <?= $isLiked ? 'bg-rose-500 text-white shadow-rose-200' : 'bg-white hover:bg-rose-50 text-slate-700 hover:text-rose-600 border border-slate-200 hover:border-rose-300' ?>">
                                    <i id="btnLikeIcon" class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart text-xl text-rose-500 group-hover:scale-125 transition-transform duration-200 <?= $isLiked ? 'text-white' : '' ?>"></i>
                                    <span id="btnLikeText"><?= $isLiked ? 'Disukai' : 'Suka Artikel' ?></span>
                                    <span id="btnLikeCount" class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-extrabold <?= $isLiked ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700' ?>">
                                        <?= (int)($artikel['likes'] ?? 0) ?>
                                    </span>
                                </button>
                                <div>
                                    <p class="text-xs font-bold text-slate-800">Apresiasi Tulisan Ini</p>
                                    <p class="text-[11px] text-slate-500">Dukungan Anda menyemangati gerakan literasi Desa Sejiram.</p>
                                </div>
                            </div>

                            <!-- Fitur Share (Bagikan) -->
                            <div class="w-full lg:w-auto flex flex-col items-center lg:items-end gap-2.5">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-share-nodes text-emerald-600"></i> Bagikan Artikel
                                </span>
                                
                                <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2">
                                    <!-- WhatsApp -->
                                    <a href="https://api.whatsapp.com/send?text=<?= $shareTextWa ?>" target="_blank" rel="noopener noreferrer" 
                                       title="Bagikan ke WhatsApp" class="w-10 h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center transition shadow-sm hover:scale-105" aria-label="Bagikan ke WhatsApp">
                                        <i class="fa-brands fa-whatsapp text-lg"></i>
                                    </a>

                                    <!-- Facebook -->
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener noreferrer" 
                                       title="Bagikan ke Facebook" class="w-10 h-10 rounded-xl bg-[#1877F2] hover:bg-[#166fe5] text-white flex items-center justify-center transition shadow-sm hover:scale-105" aria-label="Bagikan ke Facebook">
                                        <i class="fa-brands fa-facebook-f text-base"></i>
                                    </a>

                                    <!-- X / Twitter -->
                                    <a href="https://twitter.com/intent/tweet?url=<?= $shareUrlEncoded ?>&text=<?= $shareTitleEncoded ?>" target="_blank" rel="noopener noreferrer" 
                                       title="Bagikan ke X (Twitter)" class="w-10 h-10 rounded-xl bg-slate-900 hover:bg-black text-white flex items-center justify-center transition shadow-sm hover:scale-105" aria-label="Bagikan ke Twitter / X">
                                        <i class="fa-brands fa-x-twitter text-base"></i>
                                    </a>

                                    <!-- Telegram -->
                                    <a href="https://t.me/share/url?url=<?= $shareUrlEncoded ?>&text=<?= $shareTitleEncoded ?>" target="_blank" rel="noopener noreferrer" 
                                       title="Bagikan ke Telegram" class="w-10 h-10 rounded-xl bg-[#229ED9] hover:bg-[#1f8fc4] text-white flex items-center justify-center transition shadow-sm hover:scale-105" aria-label="Bagikan ke Telegram">
                                        <i class="fa-brands fa-telegram text-lg"></i>
                                    </a>

                                    <!-- Salin Tautan (Copy Link) -->
                                    <button type="button" id="btnSalinTautan" onclick="salinTautan('<?= e($currentUrl) ?>')" 
                                            title="Salin Tautan Artikel" class="px-3.5 h-10 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 flex items-center gap-1.5 text-xs font-bold transition shadow-sm hover:scale-105">
                                        <i class="fa-solid fa-link text-slate-500"></i>
                                        <span id="labelSalin">Salin Link</span>
                                    </button>

                                    <!-- Native Web Share (Tampil di browser yang mendukung) -->
                                    <button type="button" id="btnWebShare" onclick="bagikanNative('<?= e(addslashes($artikel['judul'])) ?>', '<?= e(addslashes($cleanExcerpt)) ?>', '<?= e($currentUrl) ?>')" 
                                            class="hidden px-3.5 h-10 rounded-xl bg-emerald-100 hover:bg-emerald-200 text-emerald-800 border border-emerald-300 flex items-center gap-1.5 text-xs font-bold transition shadow-sm">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        <span>Lainnya</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
                    <a href="portal.php#artikel" class="text-emerald-700 font-bold hover:underline flex items-center gap-1.5">
                        <i class="fa-solid fa-arrow-left"></i> Lihat Semua Artikel & Berita
                    </a>
                    <a href="portal.php#buku-tamu" class="bg-emerald-700 text-white px-5 py-2.5 rounded-xl font-bold hover:bg-emerald-800 transition shadow-sm">
                        <i class="fa-solid fa-pen-nib mr-1.5"></i> Isi Buku Tamu Kunjungan
                    </a>
                </div>
            </article>

            <!-- Artikel Terkait -->
            <?php if (!empty($artikelLain)): ?>
            <div class="mt-12">
                <h3 class="font-bold text-slate-900 text-lg mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-book-open-reader text-emerald-600"></i> Artikel Terkait Lainnya
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($artikelLain as $al): ?>
                    <a href="baca_artikel.php?id=<?= $al['id'] ?>" class="bg-white p-5 rounded-2xl border border-slate-200 hover:border-emerald-500 shadow-sm transition block group">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200"><?= e($al['kategori']) ?></span>
                        <h4 class="font-bold text-slate-800 text-sm mt-2 line-clamp-2 group-hover:text-emerald-700 transition"><?= e($al['judul']) ?></h4>
                        <div class="flex items-center gap-3 text-xs text-slate-400 mt-3">
                            <span><i class="fa-regular fa-calendar mr-1"></i> <?= date('d/m/Y', strtotime($al['tanggal'])) ?></span>
                            <span>&bull;</span>
                            <span class="text-rose-500"><i class="fa-solid fa-heart mr-1"></i> <?= (int)($al['likes'] ?? 0) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Toast Feedback Notifikasi -->
    <div id="toastNotif" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 pointer-events-none transition-all duration-300 flex items-center gap-2.5 bg-slate-900 text-white text-xs font-semibold px-4 py-3 rounded-2xl shadow-xl border border-slate-700">
        <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
        <span id="toastMsg">Tautan berhasil disalin!</span>
    </div>

    <footer class="bg-slate-900 text-slate-400 text-xs py-6 text-center">
        &copy; <?= date('Y') ?> Pojok Baca Pa'edah Desa Sejiram. Seluruh hak cipta dilindungi.
    </footer>

    <script>
    function showToast(msg, isSuccess = true) {
        const toast = document.getElementById('toastNotif');
        const toastMsg = document.getElementById('toastMsg');
        const toastIcon = document.getElementById('toastIcon');
        if (!toast) return;

        toastMsg.textContent = msg;
        toastIcon.className = isSuccess ? 'fa-solid fa-circle-check text-emerald-400 text-sm' : 'fa-solid fa-circle-exclamation text-rose-400 text-sm';
        toast.classList.remove('translate-y-20', 'opacity-0', 'pointer-events-none');
        toast.classList.add('translate-y-0', 'opacity-100');

        setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0', 'pointer-events-none');
            toast.classList.remove('translate-y-0', 'opacity-100');
        }, 3000);
    }

    function salinTautan(url) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                const label = document.getElementById('labelSalin');
                if (label) label.textContent = 'Tersalin! ✓';
                showToast('Tautan artikel berhasil disalin ke papan klip!');
                setTimeout(() => {
                    if (label) label.textContent = 'Salin Link';
                }, 2500);
            }).catch(() => {
                fallbackSalin(url);
            });
        } else {
            fallbackSalin(url);
        }
    }

    function fallbackSalin(url) {
        const dummy = document.createElement('input');
        document.body.appendChild(dummy);
        dummy.value = url;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        showToast('Tautan artikel berhasil disalin!');
    }

    function toggleLike(artikelId) {
        const btn = document.getElementById('btnLikeMain');
        const btnIcon = document.getElementById('btnLikeIcon');
        const btnText = document.getElementById('btnLikeText');
        const btnCount = document.getElementById('btnLikeCount');
        const metaCount = document.getElementById('metaLikeCount');

        fetch('like_artikel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: artikelId, action: 'toggle' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                btnCount.textContent = data.likes;
                if (metaCount) metaCount.textContent = data.likes;

                if (data.liked) {
                    btn.className = "group relative inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-2xl font-bold text-sm shadow-sm transition-all duration-300 transform active:scale-95 cursor-pointer bg-rose-500 text-white shadow-rose-200";
                    btnIcon.className = "fa-solid fa-heart text-xl text-white group-hover:scale-125 transition-transform duration-200";
                    btnText.textContent = "Disukai";
                    btnCount.className = "inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-white/20 text-white";
                    showToast(data.message, true);
                } else {
                    btn.className = "group relative inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-2xl font-bold text-sm shadow-sm transition-all duration-300 transform active:scale-95 cursor-pointer bg-white hover:bg-rose-50 text-slate-700 hover:text-rose-600 border border-slate-200 hover:border-rose-300";
                    btnIcon.className = "fa-regular fa-heart text-xl text-rose-500 group-hover:scale-125 transition-transform duration-200";
                    btnText.textContent = "Suka Artikel";
                    btnCount.className = "inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-rose-100 text-rose-700";
                    showToast(data.message, false);
                }
            } else {
                showToast(data.message || 'Gagal memproses suka.', false);
            }
        })
        .catch(() => {
            showToast('Gagal menghubungi server.', false);
        });
    }

    function bagikanNative(judul, text, url) {
        if (navigator.share) {
            navigator.share({
                title: judul,
                text: text,
                url: url
            }).catch(() => {});
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (navigator.share) {
            const btnWebShare = document.getElementById('btnWebShare');
            if (btnWebShare) btnWebShare.classList.remove('hidden');
        }
    });
    </script>
</body>
</html>
