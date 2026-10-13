<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Laporan Sirkulasi & Rekapitulasi';

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Handler Ekspor CSV untuk Excel
if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_PojokBaca_' . $tahun . '_' . $bulan . '.csv');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($output, ['KODE TRANSAKSI', 'NAMA ANGGOTA', 'INSTITUSI', 'JUDUL BUKU', 'KATEGORI', 'TGL PINJAM', 'JATUH TEMPO', 'TGL KEMBALI', 'STATUS', 'DENDA (RP)']);
    
    $stmtC = $pdo->prepare("
        SELECT p.kode_transaksi, a.nama, a.institusi, b.judul, b.kategori, p.tanggal_pinjam, p.jatuh_tempo, p.tanggal_kembali, p.status, p.denda
        FROM peminjaman p
        JOIN anggota a ON p.anggota_id = a.id
        JOIN buku b ON p.buku_id = b.id
        WHERE MONTH(p.tanggal_pinjam) = ? AND YEAR(p.tanggal_pinjam) = ?
    ");
    $stmtC->execute([$bulan, $tahun]);
    while ($row = $stmtC->fetch()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Data Laporan
$stmtLap = $pdo->prepare("
    SELECT p.*, a.nama AS nama_anggota, a.institusi, b.judul, b.kategori
    FROM peminjaman p
    JOIN anggota a ON p.anggota_id = a.id
    JOIN buku b ON p.buku_id = b.id
    WHERE MONTH(p.tanggal_pinjam) = ? AND YEAR(p.tanggal_pinjam) = ?
    ORDER BY p.id ASC
");
$stmtLap->execute([$bulan, $tahun]);
$dataLaporan = $stmtLap->fetchAll();

$aturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Alat Ekspor (Tidak Dicetak) -->
<div class="no-print mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Laporan Sirkulasi Pojok Baca</h2>
        <p class="text-sm text-slate-500">Cetak rekapitulasi bulanan resmi ber-KOP Surat atau unduh format Excel (.CSV).</p>
    </div>
    <div class="flex gap-2">
        <a href="laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&ekspor=csv" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-emerald-700 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-file-excel"></i> Ekspor CSV / Excel
        </a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<form method="GET" class="no-print bg-white p-4 rounded-xl border border-slate-200 mb-6 flex items-center gap-4 shadow-sm">
    <div>
        <label class="text-xs font-bold text-slate-600 block mb-1">Pilih Bulan</label>
        <select name="bulan" class="border rounded-lg px-3 py-1.5 text-sm">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= (int)$bulan === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="text-xs font-bold text-slate-600 block mb-1">Tahun</label>
        <select name="tahun" class="border rounded-lg px-3 py-1.5 text-sm">
            <?php for ($y = 2024; $y <= 2028; $y++): ?>
                <option value="<?= $y ?>" <?= (int)$tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="pt-5">
        <button type="submit" class="bg-slate-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-slate-800">Muat Data</button>
    </div>
</form>

<!-- KOP SURAT RESMI (Tampil Saat Cetak & Layar) -->
<div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
    <div class="text-center pb-4 border-b-2 border-slate-900 mb-6">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600">Pemerintah Kabupaten Sambas &bull; Kecamatan Tebas</h3>
        <h2 class="text-xl md:text-2xl font-black uppercase text-slate-900 tracking-wide">PENGELOLA <?= e($aturan['nama_instansi']) ?> <?= e($aturan['desa']) ?></h2>
        <p class="text-xs text-slate-600"><?= e($aturan['alamat']) ?>, <?= e($aturan['desa']) ?>, <?= e($aturan['kabupaten']) ?></p>
    </div>

    <div class="text-center mb-6">
        <h4 class="font-bold text-base uppercase text-slate-800 underline">REKAPITULASI PEMINJAMAN DAN SIRKULASI BUKU</h4>
        <p class="text-xs text-slate-500">Periode: Bulan <?= $bulan ?> / Tahun <?= $tahun ?></p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r">No</th>
                    <th class="p-2 border-r">Kode Trx</th>
                    <th class="p-2 border-r">Nama Anggota</th>
                    <th class="p-2 border-r">Judul Koleksi</th>
                    <th class="p-2 border-r">Kategori</th>
                    <th class="p-2 border-r">Tgl Pinjam</th>
                    <th class="p-2 border-r">Tgl Kembali</th>
                    <th class="p-2 border-r">Status</th>
                    <th class="p-2 text-right">Denda</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="9" class="p-4 text-center text-slate-400">Tidak ada riwayat sirkulasi pada periode ini.</td></tr>
                <?php else: 
                    $no = 1;
                    $grandDenda = 0;
                    foreach ($dataLaporan as $lp): 
                    $grandDenda += (int)$lp['denda'];
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r font-mono"><?= e($lp['kode_transaksi']) ?></td>
                    <td class="p-2 border-r font-medium"><?= e($lp['nama_anggota']) ?></td>
                    <td class="p-2 border-r"><?= e($lp['judul']) ?></td>
                    <td class="p-2 border-r"><?= e($lp['kategori']) ?></td>
                    <td class="p-2 border-r"><?= date('d/m/Y', strtotime($lp['tanggal_pinjam'])) ?></td>
                    <td class="p-2 border-r"><?= $lp['tanggal_kembali'] ? date('d/m/Y', strtotime($lp['tanggal_kembali'])) : '-' ?></td>
                    <td class="p-2 border-r font-semibold"><?= e($lp['status']) ?></td>
                    <td class="p-2 text-right font-mono">Rp <?= number_format($lp['denda'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="8" class="p-2 text-right border-r">Total Pendapatan Denda Keterlambatan:</td>
                    <td class="p-2 text-right font-mono">Rp <?= number_format($grandDenda, 0, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Tanda Tangan Resmi Pengesahan -->
    <div class="grid grid-cols-2 text-center text-xs mt-12 pt-4">
        <div>
            <p>Mengetahui,</p>
            <p class="font-bold mb-16"><?= e($aturan['nama_kades']) ?></p>
            <p class="font-bold underline uppercase">( .................................................. )</p>
            <p class="text-[11px] text-slate-500">Pemerintah Desa Sejiram</p>
        </div>
        <div>
            <p>Sejiram, <?= date('d F Y') ?></p>
            <p class="font-bold mb-16">Koordinator Pojok Baca Pa'edah</p>
            <p class="font-bold underline uppercase"><?= e($aturan['nama_pengelola']) ?></p>
            <p class="text-[11px] text-slate-500">Pengelola Teknis</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
