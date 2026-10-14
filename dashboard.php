<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Dashboard Statistik';

// Pengambilan Angka Statistik
$totalJudul = $pdo->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$totalStok = $pdo->query("SELECT COALESCE(SUM(stok_total), 0) FROM buku")->fetchColumn();
$totalTersedia = $pdo->query("SELECT COALESCE(SUM(stok_tersedia), 0) FROM buku")->fetchColumn();
$totalAnggota = $pdo->query("SELECT COUNT(*) FROM anggota WHERE status = 'Aktif'")->fetchColumn();
$totalDipinjam = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'Dipinjam'")->fetchColumn();
$totalTerlambat = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'Dipinjam' AND jatuh_tempo < CURDATE()")->fetchColumn();

// Statistik Per 7 Kategori Buku
$stmtKat = $pdo->query("SELECT kategori, COUNT(*) as jml_judul, COALESCE(SUM(stok_total),0) as jml_stok FROM buku GROUP BY kategori");
$statKategori = $stmtKat->fetchAll();

// Peminjaman Kritis
$stmtKritis = $pdo->query("
    SELECT p.*, a.nama AS nama_peminjam, a.institusi, b.judul 
    FROM peminjaman p
    JOIN anggota a ON p.anggota_id = a.id
    JOIN buku b ON p.buku_id = b.id
    WHERE p.status = 'Dipinjam' AND p.jatuh_tempo <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY p.jatuh_tempo ASC
    LIMIT 5
");
$pinjamanKritis = $stmtKritis->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Dashboard Statistik Pojok Baca</h2>
    <p class="text-sm text-slate-500">Kondisi koleksi, anggota, dan sirkulasi buku Desa Sejiram secara real-time.</p>
</div>

<?php if ($pendingAnggotaCount > 0): ?>
<div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border border-amber-400">
    <div class="flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center font-bold text-xl flex-shrink-0">
            <i class="fa-solid fa-user-clock text-amber-100 animate-pulse"></i>
        </div>
        <div>
            <h4 class="font-extrabold text-sm sm:text-base">Ada <?= $pendingAnggotaCount ?> Permohonan Anggota Baru Menunggu Verifikasi</h4>
            <p class="text-xs text-amber-100">Masyarakat telah mendaftar melalui portal publik dan memerlukan persetujuan dari admin/petugas.</p>
        </div>
    </div>
    <a href="anggota.php?status=Menunggu" class="bg-white text-orange-700 hover:bg-amber-50 px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm whitespace-nowrap flex items-center gap-1.5 flex-shrink-0">
        Tinjau Pendaftar <i class="fa-solid fa-arrow-right"></i>
    </a>
</div>
<?php endif; ?>

<!-- Grid 6 Kartu Metrik -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Total Judul</p>
        <h3 class="text-2xl font-bold text-emerald-700 mt-1"><?= (int)$totalJudul ?></h3>
        <p class="text-[11px] text-slate-400 mt-1">Koleksi Terdaftar</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Total Eksemplar</p>
        <h3 class="text-2xl font-bold text-blue-700 mt-1"><?= (int)$totalStok ?></h3>
        <p class="text-[11px] text-emerald-600 mt-1"><?= (int)$totalTersedia ?> Ready di Rak</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Anggota Aktif</p>
        <h3 class="text-2xl font-bold text-slate-800 mt-1"><?= (int)$totalAnggota ?></h3>
        <p class="text-[11px] text-slate-400 mt-1">Pelajar & Warga</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Sedang Dipinjam</p>
        <h3 class="text-2xl font-bold text-amber-600 mt-1"><?= (int)$totalDipinjam ?></h3>
        <p class="text-[11px] text-slate-400 mt-1">Buku di Pembaca</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Jatuh Tempo / Lewat</p>
        <h3 class="text-2xl font-bold text-rose-600 mt-1"><?= (int)$totalTerlambat ?></h3>
        <p class="text-[11px] text-rose-500 mt-1 font-medium">Perlu Penagihan</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs font-semibold text-slate-500">Buku Siap Baca</p>
        <h3 class="text-2xl font-bold text-teal-600 mt-1"><?= (int)$totalTersedia ?></h3>
        <p class="text-[11px] text-slate-400 mt-1">Tersedia Dipinjam</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Distribusi 7 Kategori Buku -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-emerald-600"></i> Distribusi 7 Kategori Baku
        </h3>
        <div class="space-y-3">
            <?php foreach ($statKategori as $sk): ?>
            <div>
                <div class="flex justify-between text-xs font-semibold mb-1">
                    <span class="text-slate-700"><?= e($sk['kategori']) ?></span>
                    <span class="text-emerald-700"><?= $sk['jml_stok'] ?> eks (<?= $sk['jml_judul'] ?> judul)</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <?php $pct = $totalStok > 0 ? round(($sk['jml_stok'] / $totalStok) * 100) : 0; ?>
                    <div class="bg-emerald-600 h-2 rounded-full" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Peminjaman Jatuh Tempo / Kritis -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col justify-between">
        <div>
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-500"></i> Pengingat Peminjaman Kritis
                </h3>
                <a href="peminjaman.php" class="text-xs text-emerald-700 font-semibold hover:underline">Semua Peminjaman &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs md:text-sm">
                    <thead class="bg-slate-100 text-slate-600 font-semibold border-b">
                        <tr>
                            <th class="p-3">Kode Trx</th>
                            <th class="p-3">Nama Anggota</th>
                            <th class="p-3">Judul Buku</th>
                            <th class="p-3">Jatuh Tempo</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($pinjamanKritis)): ?>
                        <tr><td colspan="5" class="p-6 text-center text-slate-400">Tidak ada peminjaman jatuh tempo atau kritis saat ini.</td></tr>
                        <?php else: foreach ($pinjamanKritis as $row): 
                            $isLate = strtotime($row['jatuh_tempo']) < strtotime(date('Y-m-d'));
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-mono font-medium"><?= e($row['kode_transaksi']) ?></td>
                            <td class="p-3 font-semibold text-slate-800"><?= e($row['nama_peminjam']) ?> <span class="text-xs font-normal text-slate-500">(<?= e($row['institusi']) ?>)</span></td>
                            <td class="p-3"><?= e($row['judul']) ?></td>
                            <td class="p-3 <?= $isLate ? 'text-rose-600 font-bold' : 'text-slate-600' ?>"><?= date('d/m/Y', strtotime($row['jatuh_tempo'])) ?></td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold <?= $isLate ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <?= $isLate ? 'Terlambat' : 'Mendekati Tempo' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-3 bg-slate-50 border-t text-xs text-slate-500 text-center">
            Pojok Baca Desa Sejiram &bull; Pelayanan Literasi Terbuka Pelajar & Masyarakat
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
