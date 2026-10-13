<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Peminjaman Buku';
$pesan = '';
$tipePesan = '';

$aturan = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

// Kembalikan Buku
if (isset($_GET['aksi']) && $_GET['aksi'] === 'kembali' && isset($_GET['id'])) {
    $pinjamId = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        $stmtP = $pdo->prepare("SELECT * FROM peminjaman WHERE id = ? FOR UPDATE");
        $stmtP->execute([$pinjamId]);
        $trx = $stmtP->fetch();

        if (!$trx) {
            throw new Exception("Transaksi peminjaman tidak ditemukan.");
        }
        if ($trx['status'] === 'Kembali') {
            throw new Exception("Buku pada transaksi ini sudah pernah dikembalikan!");
        }

        $tglHariIni = new DateTime();
        $tglJatuhTempo = new DateTime($trx['jatuh_tempo']);
        $terlambatHari = 0;
        $totalDenda = 0;

        if ($tglHariIni > $tglJatuhTempo) {
            $diff = $tglHariIni->diff($tglJatuhTempo);
            $terlambatHari = (int)$diff->days;
            $totalDenda = $terlambatHari * (int)$aturan['denda_per_hari'];
        }

        $stmtUpTrx = $pdo->prepare("UPDATE peminjaman SET tanggal_kembali = CURDATE(), status = 'Kembali', hari_terlambat = ?, denda = ? WHERE id = ?");
        $stmtUpTrx->execute([$terlambatHari, $totalDenda, $pinjamId]);

        $stmtUpBuku = $pdo->prepare("UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id = ?");
        $stmtUpBuku->execute([$trx['buku_id']]);

        $pdo->commit();
        $pesan = "Buku berhasil dikembalikan! Terlambat: {$terlambatHari} hari, Denda: Rp " . number_format($totalDenda, 0, ',', '.');
        $tipePesan = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = "Error: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

// Tambah Pinjam Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_pinjam'])) {
    $anggotaId = (int)$_POST['anggota_id'];
    $bukuId = (int)$_POST['buku_id'];
    $tglPinjam = $_POST['tanggal_pinjam'];
    $durasi = (int)$aturan['durasi_pinjam_hari'];
    $jatuhTempo = date('Y-m-d', strtotime("+$durasi days", strtotime($tglPinjam)));
    $kodeTrx = 'TRX-' . date('ymd') . '-' . rand(100, 999);
    $petugas = $_SESSION['user_nama'] ?? $aturan['nama_pengelola'];

    try {
        $pdo->beginTransaction();

        $stmtStok = $pdo->prepare("SELECT stok_tersedia FROM buku WHERE id = ? FOR UPDATE");
        $stmtStok->execute([$bukuId]);
        $stokTersedia = (int)$stmtStok->fetchColumn();

        if ($stokTersedia < 1) {
            throw new Exception("Gagal meminjam: Stok buku habis di rak!");
        }

        $stmtIns = $pdo->prepare("INSERT INTO peminjaman (kode_transaksi, anggota_id, buku_id, tanggal_pinjam, jatuh_tempo, petugas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$kodeTrx, $anggotaId, $bukuId, $tglPinjam, $jatuhTempo, $petugas]);

        $stmtMin = $pdo->prepare("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id = ?");
        $stmtMin->execute([$bukuId]);

        $pdo->commit();
        $pesan = "Peminjaman dengan nomor transaksi {$kodeTrx} berhasil diproses!";
        $tipePesan = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $pesan = "Gagal memproses peminjaman: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

$daftarAnggota = $pdo->query("SELECT id, no_anggota, nama, institusi FROM anggota WHERE status = 'Aktif' ORDER BY nama ASC")->fetchAll();
$daftarBukuReady = $pdo->query("SELECT id, kode_buku, judul, stok_tersedia FROM buku WHERE stok_tersedia > 0 ORDER BY judul ASC")->fetchAll();

$pinjamanAktif = $pdo->query("
    SELECT p.*, a.nama AS nama_anggota, a.institusi, b.judul, b.kode_buku 
    FROM peminjaman p
    JOIN anggota a ON p.anggota_id = a.id
    JOIN buku b ON p.buku_id = b.id
    WHERE p.status = 'Dipinjam'
    ORDER BY p.id DESC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Sirkulasi Peminjaman Buku</h2>
        <p class="text-sm text-slate-500">Mencatat peminjaman baru dan memproses pengembalian buku pemustaka.</p>
    </div>
    <button onclick="document.getElementById('modalPinjam').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-plus"></i> Buat Peminjaman Baru
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-lg <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
    <?= e($pesan) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b flex justify-between items-center">
        <h3 class="font-bold text-slate-800 text-sm md:text-base">Daftar Peminjaman Aktif (Sedang Dipinjam)</h3>
        <span class="text-xs bg-amber-100 text-amber-800 font-bold px-2.5 py-1 rounded-full"><?= count($pinjamanAktif) ?> Transaksi Aktif</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3">Kode Trx</th>
                    <th class="p-3">Peminjam</th>
                    <th class="p-3">Buku Dipinjam</th>
                    <th class="p-3">Tgl Pinjam</th>
                    <th class="p-3">Jatuh Tempo</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($pinjamanAktif)): ?>
                <tr><td colspan="7" class="p-6 text-center text-slate-400">Tidak ada buku yang sedang dipinjam saat ini.</td></tr>
                <?php else: foreach ($pinjamanAktif as $p): 
                    $isLate = strtotime($p['jatuh_tempo']) < strtotime(date('Y-m-d'));
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 font-mono font-medium text-xs"><?= e($p['kode_transaksi']) ?></td>
                    <td class="p-3">
                        <p class="font-bold text-slate-800"><?= e($p['nama_anggota']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($p['institusi']) ?></p>
                    </td>
                    <td class="p-3">
                        <p class="font-semibold text-slate-800"><?= e($p['judul']) ?></p>
                        <p class="text-xs font-mono text-slate-500"><?= e($p['kode_buku']) ?></p>
                    </td>
                    <td class="p-3 text-xs"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                    <td class="p-3 text-xs <?= $isLate ? 'text-rose-600 font-bold' : '' ?>"><?= date('d/m/Y', strtotime($p['jatuh_tempo'])) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-0.5 rounded font-medium <?= $isLate ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' ?>">
                            <?= $isLate ? 'Terlambat' : 'Dipinjam' ?>
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <a href="peminjaman.php?aksi=kembali&id=<?= $p['id'] ?>" onclick="return confirm('Proses pengembalian buku dan kembalikan stok ke rak?')" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-blue-700 shadow-sm">Proses Kembali</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Pinjam -->
<div id="modalPinjam" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800">Form Transaksi Peminjaman Baru</h3>
            <button onclick="document.getElementById('modalPinjam').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="peminjaman.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Anggota</label>
                <select name="anggota_id" required class="w-full border rounded-lg p-2 text-sm">
                    <option value="">-- Cari Nama / Anggota --</option>
                    <?php foreach ($daftarAnggota as $ag): ?>
                    <option value="<?= $ag['id'] ?>"><?= e($ag['nama']) ?> (<?= e($ag['institusi']) ?> - <?= e($ag['no_anggota']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Buku (Stok Ready)</label>
                <select name="buku_id" required class="w-full border rounded-lg p-2 text-sm">
                    <option value="">-- Pilih Koleksi Buku --</option>
                    <?php foreach ($daftarBukuReady as $bk): ?>
                    <option value="<?= $bk['id'] ?>"><?= e($bk['judul']) ?> [Ready: <?= $bk['stok_tersedia'] ?>]</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Pinjam</label>
                <input type="date" name="tanggal_pinjam" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <p class="text-xs text-slate-500 bg-slate-50 p-2.5 rounded border border-slate-200">
                *Batas waktu pengembalian standar adalah <strong><?= (int)$aturan['durasi_pinjam_hari'] ?> hari</strong>. Denda keterlambatan Rp <?= number_format($aturan['denda_per_hari'], 0, ',', '.') ?>/hari.
            </p>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalPinjam').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm">Batal</button>
                <button type="submit" name="proses_pinjam" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
