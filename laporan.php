<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$jenis = $_GET['jenis'] ?? 'peminjaman';
$allowedJenis = ['buku', 'anggota', 'peminjaman', 'denda', 'inventaris', 'presensi'];
if (!in_array($jenis, $allowedJenis, true)) {
    $jenis = 'peminjaman';
}

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$kategori = $_GET['kategori'] ?? '';
$kondisi = $_GET['kondisi'] ?? '';
$status = $_GET['status'] ?? '';
$kategoriTingkat = $_GET['kategori_tingkat'] ?? '';
$jenisInventaris = $_GET['jenis_inventaris'] ?? '';

$aturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch() ?: [
    'nama_instansi' => 'Pojok Baca Pa\'edah',
    'desa' => 'Desa Sejiram',
    'kabupaten' => 'Kabupaten Sambas',
    'alamat' => 'Jl. Pendidikan No. 01, Kantor Desa Sejiram',
    'jabatan_kades' => 'Kepala Desa Sejiram',
    'nama_kades' => 'Hemdi',
    'nama_pengelola' => 'Syarif Ahmad, S.Pd'
];

$namaBulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tglCetak = date('d') . ' ' . ($namaBulanIndo[(int)date('m')] ?? date('F')) . ' ' . date('Y');
$namaDesaClean = preg_replace('/^Desa\s+/i', '', $aturan['desa'] ?? 'Sejiram');
$jabatanKades = !empty($aturan['jabatan_kades']) ? $aturan['jabatan_kades'] : ('Kepala Desa ' . $namaDesaClean);
$namaKades = (!empty($aturan['nama_kades']) && $aturan['nama_kades'] !== 'Kepala Desa Sejiram') ? $aturan['nama_kades'] : '';

// ---------------------------------------------------------
// QUERY DATA SESUAI JENIS LAPORAN
// ---------------------------------------------------------
$dataLaporan = [];
$judulLaporan = '';
$subJudul = '';
$periodeText = '';

switch ($jenis) {
    case 'buku':
        $pageTitle = 'Laporan Koleksi Buku';
        $judulLaporan = 'REKAPITULASI DAFTAR KOLEKSI BUKU';
        $subJudul = 'Laporan seluruh inventaris koleksi buku berdasarkan kategori, kondisi, dan ketersediaan stok';
        $periodeText = 'Per Tanggal ' . $tglCetak;
        
        $where = [];
        $params = [];
        if ($kategori !== '') {
            $where[] = "kategori = ?";
            $params[] = $kategori;
        }
        if ($kondisi !== '') {
            $where[] = "kondisi = ?";
            $params[] = $kondisi;
        }
        $sql = "SELECT * FROM buku" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY judul ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Koleksi_Buku_' . date('Ymd_His') . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'KODE BUKU', 'JUDUL BUKU', 'PENULIS', 'PENERBIT', 'TAHUN', 'KATEGORI', 'LOKASI RAK', 'KONDISI', 'TOTAL STOK', 'STOK TERSEDIA']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                fputcsv($out, [
                    $no++, $r['kode_buku'], $r['judul'], $r['penulis'], $r['penerbit'],
                    $r['tahun'], $r['kategori'], $r['lokasi_rak'], $r['kondisi'],
                    $r['stok_total'], $r['stok_tersedia']
                ]);
            }
            fclose($out);
            exit;
        }
        break;

    case 'anggota':
        $pageTitle = 'Laporan Data Anggota';
        $judulLaporan = 'REKAPITULASI DATA ANGGOTA PEMUSTAKA';
        $subJudul = 'Laporan data seluruh anggota pemustaka yang terdaftar pada sistem';
        $periodeText = 'Per Tanggal ' . $tglCetak;

        $where = [];
        $params = [];
        if ($kategoriTingkat !== '') {
            $where[] = "kategori_tingkat = ?";
            $params[] = $kategoriTingkat;
        }
        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
        }
        $sql = "SELECT * FROM anggota" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Data_Anggota_' . date('Ymd_His') . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'NO ANGGOTA', 'NAMA LENGKAP', 'KATEGORI TINGKAT', 'INSTITUSI / SEKOLAH', 'KONTAK/WA', 'STATUS', 'TANGGAL BERGABUNG']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                fputcsv($out, [
                    $no++, $r['no_anggota'], $r['nama'], $r['kategori_tingkat'], $r['institusi'],
                    $r['kontak'], $r['status'], date('d/m/Y', strtotime($r['created_at']))
                ]);
            }
            fclose($out);
            exit;
        }
        break;

    case 'denda':
        $pageTitle = 'Laporan Denda Keterlambatan';
        $judulLaporan = 'REKAPITULASI KAS & DENDA KETERLAMBATAN';
        $subJudul = 'Laporan rekapitulasi penerimaan denda atas keterlambatan pengembalian buku';
        $periodeText = 'Periode: ' . ($namaBulanIndo[(int)$bulan] ?? ('Bulan ' . $bulan)) . ' ' . $tahun;

        $stmt = $pdo->prepare("
            SELECT p.*, a.nama AS nama_anggota, a.institusi, b.judul, b.kategori
            FROM peminjaman p
            JOIN anggota a ON p.anggota_id = a.id
            JOIN buku b ON p.buku_id = b.id
            WHERE MONTH(p.tanggal_pinjam) = ? AND YEAR(p.tanggal_pinjam) = ? AND p.denda > 0
            ORDER BY p.id ASC
        ");
        $stmt->execute([$bulan, $tahun]);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Denda_' . $tahun . '_' . $bulan . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'KODE TRANSAKSI', 'NAMA ANGGOTA', 'INSTITUSI', 'JUDUL BUKU', 'TGL PINJAM', 'JATUH TEMPO', 'TGL KEMBALI', 'HARI TELAT', 'DENDA (RP)', 'STATUS']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                fputcsv($out, [
                    $no++, $r['kode_transaksi'], $r['nama_anggota'], $r['institusi'], $r['judul'],
                    $r['tanggal_pinjam'], $r['jatuh_tempo'], $r['tanggal_kembali'] ?: '-',
                    $r['hari_terlambat'], $r['denda'], $r['status']
                ]);
            }
            fclose($out);
            exit;
        }
        break;

    case 'inventaris':
        $pageTitle = 'Laporan Inventaris Buku';
        $judulLaporan = 'REKAPITULASI MUTASI INVENTARIS BUKU';
        $subJudul = 'Laporan mutasi kondisi buku rusak, buku hilang, dan penerimaan pengadaan buku baru';
        $periodeText = $tahun ? ('Tahun ' . $tahun) : ('Per Tanggal ' . $tglCetak);

        $where = [];
        $params = [];
        if ($jenisInventaris !== '') {
            $where[] = "i.jenis = ?";
            $params[] = $jenisInventaris;
        }
        if ($tahun !== '') {
            $where[] = "YEAR(i.tanggal) = ?";
            $params[] = $tahun;
        }
        $sql = "SELECT i.*, b.judul AS judul_buku, b.kode_buku 
                FROM inventaris i 
                LEFT JOIN buku b ON i.buku_id = b.id " .
                ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY i.tanggal DESC, i.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Inventaris_' . ($tahun ?: date('Y')) . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'TANGGAL', 'JENIS MUTASI', 'JUDUL BUKU / PENGADAAN', 'JUMLAH', 'SUMBER DANA', 'KETERANGAN']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                $judulBuku = $r['judul_buku'] ?: ($r['judul_manual'] ?: '-');
                fputcsv($out, [
                    $no++, date('d/m/Y', strtotime($r['tanggal'])), $r['jenis'],
                    $judulBuku, $r['jumlah'], $r['sumber_dana'] ?: '-', $r['keterangan']
                ]);
            }
            fclose($out);
            exit;
        }
        break;

    case 'presensi':
        $pageTitle = 'Laporan Presensi Kunjungan Tamu';
        $judulLaporan = 'REKAPITULASI BUKU TAMU & PRESENSI KUNJUNGAN';
        $subJudul = 'Laporan riwayat kunjungan pemustaka dan tamu Pojok Baca Desa Sejiram';
        $periodeText = 'Periode: ' . ($namaBulanIndo[(int)$bulan] ?? ('Bulan ' . $bulan)) . ' ' . $tahun;

        $where = ["MONTH(tanggal) = ?", "YEAR(tanggal) = ?"];
        $params = [$bulan, $tahun];
        if ($kategoriTingkat !== '') {
            $where[] = "kategori_pemustaka = ?";
            $params[] = $kategoriTingkat;
        }
        $sql = "SELECT * FROM kunjungan WHERE " . implode(" AND ", $where) . " ORDER BY tanggal ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Presensi_Tamu_' . $tahun . '_' . $bulan . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'TANGGAL', 'NAMA PENGUNJUNG', 'KATEGORI PEMUSTAKA', 'ASAL INSTITUSI / SEKOLAH', 'KEPERLUAN KUNJUNGAN']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                fputcsv($out, [
                    $no++, date('d/m/Y', strtotime($r['tanggal'])), $r['nama'],
                    $r['kategori_pemustaka'], $r['asal_institusi'], $r['keperluan']
                ]);
            }
            fclose($out);
            exit;
        }
        break;

    case 'peminjaman':
    default:
        $pageTitle = 'Laporan Peminjaman Buku';
        $judulLaporan = 'REKAPITULASI PEMINJAMAN DAN SIRKULASI BUKU';
        $subJudul = 'Laporan sirkulasi perputaran peminjaman dan pengembalian buku pemustaka';
        $periodeText = 'Periode: ' . ($namaBulanIndo[(int)$bulan] ?? ('Bulan ' . $bulan)) . ' ' . $tahun;

        $where = ["MONTH(p.tanggal_pinjam) = ?", "YEAR(p.tanggal_pinjam) = ?"];
        $params = [$bulan, $tahun];
        if ($status !== '') {
            $where[] = "p.status = ?";
            $params[] = $status;
        }
        $sql = "SELECT p.*, a.nama AS nama_anggota, a.institusi, b.judul, b.kategori 
                FROM peminjaman p 
                JOIN anggota a ON p.anggota_id = a.id 
                JOIN buku b ON p.buku_id = b.id 
                WHERE " . implode(" AND ", $where) . " ORDER BY p.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dataLaporan = $stmt->fetchAll();

        // Export CSV
        if (isset($_GET['ekspor']) && $_GET['ekspor'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Laporan_Peminjaman_' . $tahun . '_' . $bulan . '.csv');
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['NO', 'KODE TRANSAKSI', 'NAMA ANGGOTA', 'INSTITUSI', 'JUDUL BUKU', 'KATEGORI', 'TGL PINJAM', 'JATUH TEMPO', 'TGL KEMBALI', 'STATUS', 'DENDA (RP)']);
            $no = 1;
            foreach ($dataLaporan as $r) {
                fputcsv($out, [
                    $no++, $r['kode_transaksi'], $r['nama_anggota'], $r['institusi'],
                    $r['judul'], $r['kategori'], $r['tanggal_pinjam'], $r['jatuh_tempo'],
                    $r['tanggal_kembali'] ?: '-', $r['status'], $r['denda']
                ]);
            }
            fclose($out);
            exit;
        }
        break;
}

// Data kategori buku untuk filter buku
$kategoriBukuList = [
    'Koleksi buku anak',
    'Buku pendidikan',
    'Buku kesehatan',
    'Buku keterampilan',
    'Buku agama',
    'Buku budaya/kearifan lokal',
    'Buku lainnya'
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Tab Pilihan Laporan (Tidak Dicetak) -->
<div class="no-print mb-6">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-emerald-600"></i> Modul Laporan Resmi
            </h2>
            <p class="text-xs text-slate-500">Pilih jenis rekapitulasi data di bawah ini untuk mencetak dokumen ber-KOP resmi atau ekspor Excel (.CSV).</p>
        </div>
        <div class="flex gap-2">
            <?php 
            $queryExport = $_GET;
            $queryExport['ekspor'] = 'csv';
            $urlExport = 'laporan.php?' . http_build_query($queryExport);
            ?>
            <a href="<?= e($urlExport) ?>" class="bg-emerald-600 text-white px-3.5 py-2 rounded-lg text-xs font-semibold hover:bg-emerald-700 flex items-center gap-2 shadow-sm transition">
                <i class="fa-solid fa-file-excel"></i> Ekspor CSV / Excel
            </a>
            <button onclick="window.print()" class="bg-blue-600 text-white px-3.5 py-2 rounded-lg text-xs font-semibold hover:bg-blue-700 flex items-center gap-2 shadow-sm transition">
                <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- Quick Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        <a href="laporan.php?jenis=buku" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'buku' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-book"></i> Laporan Koleksi Buku
        </a>
        <a href="laporan.php?jenis=anggota" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'anggota' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-users"></i> Laporan Anggota
        </a>
        <a href="laporan.php?jenis=peminjaman" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'peminjaman' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-arrow-right-arrow-left"></i> Laporan Peminjaman
        </a>
        <a href="laporan.php?jenis=denda" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'denda' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-receipt"></i> Laporan Denda
        </a>
        <a href="laporan.php?jenis=inventaris" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'inventaris' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-boxes-stacked"></i> Laporan Inventaris
        </a>
        <a href="laporan.php?jenis=presensi" class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition <?= $jenis === 'presensi' ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
            <i class="fa-solid fa-clipboard-user"></i> Laporan Presensi Tamu
        </a>
    </div>
</div>

<!-- Filter Box Khusus Masing-masing Laporan (Tidak Dicetak) -->
<form method="GET" class="no-print bg-white p-4 rounded-xl border border-slate-200 mb-6 flex flex-wrap items-center gap-4 shadow-sm text-xs">
    <input type="hidden" name="jenis" value="<?= e($jenis) ?>">

    <?php if ($jenis === 'peminjaman' || $jenis === 'denda' || $jenis === 'presensi'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Pilih Bulan</label>
        <select name="bulan" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= (int)$bulan === $m ? 'selected' : '' ?>><?= $namaBulanIndo[$m] ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'peminjaman' || $jenis === 'denda' || $jenis === 'presensi' || $jenis === 'inventaris'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Tahun</label>
        <select name="tahun" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <?php for ($y = 2024; $y <= 2028; $y++): ?>
                <option value="<?= $y ?>" <?= (int)$tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'buku'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Kategori Koleksi</label>
        <select name="kategori" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategoriBukuList as $kat): ?>
                <option value="<?= e($kat) ?>" <?= $kategori === $kat ? 'selected' : '' ?>><?= e($kat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Kondisi Buku</label>
        <select name="kondisi" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Kondisi</option>
            <option value="Baik" <?= $kondisi === 'Baik' ? 'selected' : '' ?>>Baik</option>
            <option value="Rusak Ringan" <?= $kondisi === 'Rusak Ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
            <option value="Rusak Parah" <?= $kondisi === 'Rusak Parah' ? 'selected' : '' ?>>Rusak Parah</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'anggota'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Kategori Tingkat</label>
        <select name="kategori_tingkat" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Tingkat</option>
            <option value="SD" <?= $kategoriTingkat === 'SD' ? 'selected' : '' ?>>SD</option>
            <option value="SMP" <?= $kategoriTingkat === 'SMP' ? 'selected' : '' ?>>SMP</option>
            <option value="SMA" <?= $kategoriTingkat === 'SMA' ? 'selected' : '' ?>>SMA</option>
            <option value="Warga Umum" <?= $kategoriTingkat === 'Warga Umum' ? 'selected' : '' ?>>Warga Umum</option>
            <option value="Aparatur Desa" <?= $kategoriTingkat === 'Aparatur Desa' ? 'selected' : '' ?>>Aparatur Desa</option>
        </select>
    </div>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Status Anggota</label>
        <select name="status" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Status</option>
            <option value="Aktif" <?= $status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="Nonaktif" <?= $status === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'peminjaman'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Status Transaksi</label>
        <select name="status" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Status</option>
            <option value="Dipinjam" <?= $status === 'Dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
            <option value="Kembali" <?= $status === 'Kembali' ? 'selected' : '' ?>>Kembali</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'inventaris'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Jenis Mutasi Log</label>
        <select name="jenis_inventaris" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Mutasi</option>
            <option value="Pengadaan" <?= $jenisInventaris === 'Pengadaan' ? 'selected' : '' ?>>Pengadaan</option>
            <option value="Rusak" <?= $jenisInventaris === 'Rusak' ? 'selected' : '' ?>>Buku Rusak</option>
            <option value="Hilang" <?= $jenisInventaris === 'Hilang' ? 'selected' : '' ?>>Buku Hilang</option>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($jenis === 'presensi'): ?>
    <div>
        <label class="font-bold text-slate-600 block mb-1">Kategori Pemustaka</label>
        <select name="kategori_tingkat" class="border rounded-lg px-3 py-1.5 text-xs bg-slate-50">
            <option value="">Semua Kategori</option>
            <option value="SD" <?= $kategoriTingkat === 'SD' ? 'selected' : '' ?>>SD</option>
            <option value="SMP" <?= $kategoriTingkat === 'SMP' ? 'selected' : '' ?>>SMP</option>
            <option value="SMA" <?= $kategoriTingkat === 'SMA' ? 'selected' : '' ?>>SMA</option>
            <option value="Warga Umum" <?= $kategoriTingkat === 'Warga Umum' ? 'selected' : '' ?>>Warga Umum</option>
            <option value="Aparatur Desa" <?= $kategoriTingkat === 'Aparatur Desa' ? 'selected' : '' ?>>Aparatur Desa</option>
        </select>
    </div>
    <?php endif; ?>

    <div class="pt-5">
        <button type="submit" class="bg-slate-700 text-white px-4 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-800 transition shadow-sm">
            <i class="fa-solid fa-filter mr-1"></i> Terapkan Filter
        </button>
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
        <h4 class="font-bold text-base uppercase text-slate-800 underline"><?= e($judulLaporan) ?></h4>
        <p class="text-xs text-slate-500 font-medium mt-0.5"><?= e($periodeText) ?></p>
        <p class="text-[11px] text-slate-400 mt-0.5"><?= e($subJudul) ?></p>
    </div>

    <div class="overflow-x-auto">
        <!-- 1. TABEL LAPORAN KOLEKSI BUKU -->
        <?php if ($jenis === 'buku'): ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">Kode</th>
                    <th class="p-2 border-r">Judul Koleksi Buku</th>
                    <th class="p-2 border-r">Penulis & Penerbit</th>
                    <th class="p-2 border-r">Kategori</th>
                    <th class="p-2 border-r">Lokasi Rak</th>
                    <th class="p-2 border-r text-center">Kondisi</th>
                    <th class="p-2 border-r text-center">Total Stok</th>
                    <th class="p-2 text-center">Tersedia</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="9" class="p-4 text-center text-slate-400">Tidak ada koleksi buku yang sesuai dengan filter.</td></tr>
                <?php else: 
                    $no = 1;
                    $sumTotal = 0;
                    $sumTersedia = 0;
                    foreach ($dataLaporan as $b): 
                    $sumTotal += (int)$b['stok_total'];
                    $sumTersedia += (int)$b['stok_tersedia'];
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r font-mono text-[11px]"><?= e($b['kode_buku']) ?></td>
                    <td class="p-2 border-r font-medium text-slate-900"><?= e($b['judul']) ?></td>
                    <td class="p-2 border-r text-slate-600"><?= e($b['penulis']) ?> (<?= e($b['penerbit']) ?>, <?= (int)$b['tahun'] ?>)</td>
                    <td class="p-2 border-r"><?= e($b['kategori']) ?></td>
                    <td class="p-2 border-r"><?= e($b['lokasi_rak']) ?></td>
                    <td class="p-2 border-r text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $b['kondisi'] === 'Baik' ? 'bg-emerald-100 text-emerald-800' : ($b['kondisi'] === 'Rusak Ringan' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                            <?= e($b['kondisi']) ?>
                        </span>
                    </td>
                    <td class="p-2 border-r text-center font-bold"><?= (int)$b['stok_total'] ?></td>
                    <td class="p-2 text-center font-bold text-emerald-700"><?= (int)$b['stok_tersedia'] ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="7" class="p-2 text-right border-r">Total Jumlah Buku (Eksemplar):</td>
                    <td class="p-2 text-center border-r font-mono"><?= $sumTotal ?></td>
                    <td class="p-2 text-center font-mono text-emerald-700"><?= $sumTersedia ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 2. TABEL LAPORAN DATA ANGGOTA -->
        <?php elseif ($jenis === 'anggota'): ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">No. Anggota</th>
                    <th class="p-2 border-r">Nama Lengkap</th>
                    <th class="p-2 border-r">Kategori Tingkat</th>
                    <th class="p-2 border-r">Asal Sekolah / Institusi</th>
                    <th class="p-2 border-r">No. Kontak / WA</th>
                    <th class="p-2 border-r text-center">Status</th>
                    <th class="p-2 text-center">Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="8" class="p-4 text-center text-slate-400">Tidak ada data anggota pemustaka pada filter ini.</td></tr>
                <?php else: 
                    $no = 1;
                    foreach ($dataLaporan as $a): 
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r font-mono text-[11px]"><?= e($a['no_anggota']) ?></td>
                    <td class="p-2 border-r font-semibold text-slate-900"><?= e($a['nama']) ?></td>
                    <td class="p-2 border-r"><?= e($a['kategori_tingkat']) ?></td>
                    <td class="p-2 border-r"><?= e($a['institusi']) ?></td>
                    <td class="p-2 border-r font-mono text-[11px]"><?= e($a['kontak']) ?></td>
                    <td class="p-2 border-r text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $a['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                            <?= e($a['status']) ?>
                        </span>
                    </td>
                    <td class="p-2 text-center text-slate-600"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="7" class="p-2 text-right border-r">Total Anggota Terdaftar:</td>
                    <td class="p-2 text-center font-mono text-emerald-800"><?= count($dataLaporan) ?> Orang</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 3. TABEL LAPORAN KAS & DENDA KETERLAMBATAN -->
        <?php elseif ($jenis === 'denda'): ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">Kode Trx</th>
                    <th class="p-2 border-r">Nama Anggota</th>
                    <th class="p-2 border-r">Judul Koleksi</th>
                    <th class="p-2 border-r">Tgl Pinjam</th>
                    <th class="p-2 border-r">Jatuh Tempo</th>
                    <th class="p-2 border-r">Tgl Kembali</th>
                    <th class="p-2 border-r text-center">Telat (Hari)</th>
                    <th class="p-2 border-r text-right">Denda (Rp)</th>
                    <th class="p-2 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="10" class="p-4 text-center text-slate-400">Tidak ada catatan denda keterlambatan pada periode ini.</td></tr>
                <?php else: 
                    $no = 1;
                    $totalDenda = 0;
                    foreach ($dataLaporan as $d): 
                    $totalDenda += (int)$d['denda'];
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r font-mono text-[11px]"><?= e($d['kode_transaksi']) ?></td>
                    <td class="p-2 border-r font-medium text-slate-900"><?= e($d['nama_anggota']) ?></td>
                    <td class="p-2 border-r"><?= e($d['judul']) ?></td>
                    <td class="p-2 border-r"><?= date('d/m/Y', strtotime($d['tanggal_pinjam'])) ?></td>
                    <td class="p-2 border-r"><?= date('d/m/Y', strtotime($d['jatuh_tempo'])) ?></td>
                    <td class="p-2 border-r"><?= $d['tanggal_kembali'] ? date('d/m/Y', strtotime($d['tanggal_kembali'])) : '<span class="text-rose-600 font-semibold">Belum Kembali</span>' ?></td>
                    <td class="p-2 border-r text-center font-bold text-rose-600"><?= (int)$d['hari_terlambat'] ?> hari</td>
                    <td class="p-2 border-r text-right font-mono font-bold text-rose-700">Rp <?= number_format($d['denda'], 0, ',', '.') ?></td>
                    <td class="p-2 text-center font-semibold"><?= e($d['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="8" class="p-2 text-right border-r">Total Penerimaan Kas Denda:</td>
                    <td class="p-2 text-right font-mono text-emerald-800">Rp <?= number_format($totalDenda, 0, ',', '.') ?></td>
                    <td class="p-2 text-center">-</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 4. TABEL LAPORAN INVENTARIS BUKU -->
        <?php elseif ($jenis === 'inventaris'): ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">Tanggal</th>
                    <th class="p-2 border-r">Jenis Mutasi</th>
                    <th class="p-2 border-r">Judul Koleksi / Pengadaan</th>
                    <th class="p-2 border-r text-center">Jumlah (Eks)</th>
                    <th class="p-2 border-r">Sumber Dana</th>
                    <th class="p-2">Keterangan / Alasan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="7" class="p-4 text-center text-slate-400">Tidak ada catatan inventaris pada periode ini.</td></tr>
                <?php else: 
                    $no = 1;
                    $sumInv = 0;
                    foreach ($dataLaporan as $iv): 
                    $sumInv += (int)$iv['jumlah'];
                    $judulBuku = $iv['judul_buku'] ?: ($iv['judul_manual'] ?: '-');
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r text-slate-600"><?= date('d/m/Y', strtotime($iv['tanggal'])) ?></td>
                    <td class="p-2 border-r">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $iv['jenis'] === 'Pengadaan' ? 'bg-emerald-100 text-emerald-800' : ($iv['jenis'] === 'Rusak' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') ?>">
                            <?= e($iv['jenis']) ?>
                        </span>
                    </td>
                    <td class="p-2 border-r font-medium text-slate-900"><?= e($judulBuku) ?></td>
                    <td class="p-2 border-r text-center font-bold"><?= (int)$iv['jumlah'] ?></td>
                    <td class="p-2 border-r text-slate-600"><?= e($iv['sumber_dana'] ?: '-') ?></td>
                    <td class="p-2 text-slate-600"><?= e($iv['keterangan']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="4" class="p-2 text-right border-r">Total Jumlah Buku:</td>
                    <td class="p-2 text-center font-mono text-emerald-800"><?= $sumInv ?> Eksemplar</td>
                    <td colspan="2" class="p-2"></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 5. TABEL LAPORAN PRESENSI TAMU -->
        <?php elseif ($jenis === 'presensi'): ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">Tanggal</th>
                    <th class="p-2 border-r">Nama Pengunjung</th>
                    <th class="p-2 border-r">Kategori Pemustaka</th>
                    <th class="p-2 border-r">Asal Sekolah / Institusi</th>
                    <th class="p-2">Keperluan / Agenda Kunjungan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="6" class="p-4 text-center text-slate-400">Tidak ada riwayat kunjungan tamu pada periode ini.</td></tr>
                <?php else: 
                    $no = 1;
                    foreach ($dataLaporan as $k): 
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r text-slate-600"><?= date('d/m/Y', strtotime($k['tanggal'])) ?></td>
                    <td class="p-2 border-r font-semibold text-slate-900"><?= e($k['nama']) ?></td>
                    <td class="p-2 border-r">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <?= e($k['kategori_pemustaka']) ?>
                        </span>
                    </td>
                    <td class="p-2 border-r"><?= e($k['asal_institusi']) ?></td>
                    <td class="p-2 text-slate-700"><?= e($k['keperluan']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="5" class="p-2 text-right border-r">Total Kunjungan Pemustaka:</td>
                    <td class="p-2 font-mono text-emerald-800"><?= count($dataLaporan) ?> Pemustaka</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 6. TABEL LAPORAN PEMINJAMAN BUKU (DEFAULT) -->
        <?php else: ?>
        <table class="w-full text-left text-xs border border-slate-300 mb-8">
            <thead class="bg-slate-100 border-b border-slate-300">
                <tr>
                    <th class="p-2 border-r text-center w-10">No</th>
                    <th class="p-2 border-r">Kode Trx</th>
                    <th class="p-2 border-r">Nama Anggota</th>
                    <th class="p-2 border-r">Judul Koleksi</th>
                    <th class="p-2 border-r">Kategori</th>
                    <th class="p-2 border-r">Tgl Pinjam</th>
                    <th class="p-2 border-r">Tgl Kembali</th>
                    <th class="p-2 border-r text-center">Status</th>
                    <th class="p-2 text-right">Denda</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php if (empty($dataLaporan)): ?>
                <tr><td colspan="9" class="p-4 text-center text-slate-400">Tidak ada riwayat sirkulasi peminjaman pada periode ini.</td></tr>
                <?php else: 
                    $no = 1;
                    $grandDenda = 0;
                    foreach ($dataLaporan as $lp): 
                    $grandDenda += (int)$lp['denda'];
                ?>
                <tr>
                    <td class="p-2 border-r text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r font-mono text-[11px]"><?= e($lp['kode_transaksi']) ?></td>
                    <td class="p-2 border-r font-medium text-slate-900"><?= e($lp['nama_anggota']) ?></td>
                    <td class="p-2 border-r"><?= e($lp['judul']) ?></td>
                    <td class="p-2 border-r"><?= e($lp['kategori']) ?></td>
                    <td class="p-2 border-r"><?= date('d/m/Y', strtotime($lp['tanggal_pinjam'])) ?></td>
                    <td class="p-2 border-r"><?= $lp['tanggal_kembali'] ? date('d/m/Y', strtotime($lp['tanggal_kembali'])) : '<span class="text-amber-600 font-medium">Sedang Dipinjam</span>' ?></td>
                    <td class="p-2 border-r text-center">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $lp['status'] === 'Kembali' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= e($lp['status']) ?>
                        </span>
                    </td>
                    <td class="p-2 text-right font-mono">Rp <?= number_format($lp['denda'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold bg-slate-50 border-t">
                    <td colspan="8" class="p-2 text-right border-r">Total Pendapatan Denda Keterlambatan:</td>
                    <td class="p-2 text-right font-mono text-emerald-800">Rp <?= number_format($grandDenda, 0, ',', '.') ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Tanda Tangan Resmi Pengesahan -->
    <div class="grid grid-cols-2 text-center text-xs mt-12 pt-4">
        <div>
            <p>Mengetahui,</p>
            <p class="font-bold mb-16"><?= e($jabatanKades) ?></p>
            <p class="font-bold underline uppercase">
                <?php if ($namaKades !== ''): ?>
                    <?= e($namaKades) ?>
                <?php else: ?>
                    ( .................................................. )
                <?php endif; ?>
            </p>
            <p class="text-[11px] text-slate-500">Pemerintah <?= e($aturan['desa']) ?></p>
        </div>
        <div>
            <p><?= e($namaDesaClean) ?>, <?= $tglCetak ?></p>
            <p class="font-bold mb-16">Koordinator <?= e($aturan['nama_instansi']) ?></p>
            <p class="font-bold underline uppercase"><?= e($aturan['nama_pengelola']) ?></p>
            <p class="text-[11px] text-slate-500">Pengelola Teknis</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
