<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Inventaris & Pengadaan';
$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_inventaris'])) {
    $jenis = $_POST['jenis'];
    $bukuId = !empty($_POST['buku_id']) ? (int)$_POST['buku_id'] : null;
    $judulManual = trim($_POST['judul_manual'] ?? '');
    $jumlah = (int)$_POST['jumlah'];
    $sumber = trim($_POST['sumber_dana'] ?? '');
    $ket = trim($_POST['keterangan']);
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO inventaris (jenis, buku_id, judul_manual, jumlah, sumber_dana, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$jenis, $bukuId, $judulManual, $jumlah, $sumber, $ket, $tgl]);
    $pesan = "Catatan inventaris berhasil disimpan.";
}

$daftarBuku = $pdo->query("SELECT id, kode_buku, judul FROM buku ORDER BY judul ASC")->fetchAll();
$daftarInv = $pdo->query("
    SELECT i.*, b.judul AS judul_relasi, b.kode_buku 
    FROM inventaris i 
    LEFT JOIN buku b ON i.buku_id = b.id 
    ORDER BY i.id DESC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Inventaris, Kerusakan & Pengadaan</h2>
        <p class="text-sm text-slate-500">Pencatatan buku rusak, hilang, serta pengadaan bantuan Dana Desa / Donasi.</p>
    </div>
    <button onclick="document.getElementById('modalInv').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-plus"></i> Catat Log Inventaris
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-300">
    <?= e($pesan) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3">Tanggal</th>
                    <th class="p-3">Jenis Mutasi</th>
                    <th class="p-3">Judul Koleksi</th>
                    <th class="p-3 text-center">Jumlah</th>
                    <th class="p-3">Sumber Dana / Asal</th>
                    <th class="p-3">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarInv)): ?>
                <tr><td colspan="6" class="p-6 text-center text-slate-400">Belum ada riwayat inventaris/pengadaan.</td></tr>
                <?php else: foreach ($daftarInv as $iv): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 font-mono text-xs"><?= date('d/m/Y', strtotime($iv['tanggal'])) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold <?= $iv['jenis'] === 'Pengadaan' ? 'bg-emerald-100 text-emerald-800' : ($iv['jenis'] === 'Hilang' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= e($iv['jenis']) ?>
                        </span>
                    </td>
                    <td class="p-3 font-bold text-slate-800"><?= e($iv['judul_relasi'] ?: $iv['judul_manual']) ?></td>
                    <td class="p-3 text-center font-bold"><?= $iv['jumlah'] ?> eks</td>
                    <td class="p-3 text-xs text-slate-600"><?= e($iv['sumber_dana'] ?: '-') ?></td>
                    <td class="p-3 text-xs text-slate-600"><?= e($iv['keterangan']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Log Inventaris -->
<div id="modalInv" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800">Catat Buku Rusak / Hilang / Pengadaan</h3>
            <button onclick="document.getElementById('modalInv').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="inventaris.php" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Mutasi</label>
                    <select name="jenis" required class="w-full border rounded-lg p-2 text-sm">
                        <option value="Rusak">Buku Rusak</option>
                        <option value="Hilang">Buku Hilang</option>
                        <option value="Pengadaan">Pengadaan Buku Baru</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal</label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Buku Katalog (Jika Sudah Ada)</label>
                <select name="buku_id" class="w-full border rounded-lg p-2 text-sm">
                    <option value="">-- Pilih Buku atau Tulis Manual di Bawah --</option>
                    <?php foreach ($daftarBuku as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['judul']) ?> (<?= e($b['kode_buku']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku (Manual jika pengadaan baru)</label>
                <input type="text" name="judul_manual" placeholder="Tuliskan judul jika buku baru..." class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Eksemplar</label>
                    <input type="number" name="jumlah" min="1" value="1" required class="w-full border rounded-lg p-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sumber Dana / Asal</label>
                    <input type="text" name="sumber_dana" placeholder="Dana Desa / Donasi Warga" class="w-full border rounded-lg p-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Alasan</label>
                <textarea name="keterangan" required rows="2" placeholder="Catatan kondisi buku atau pengadaan..." class="w-full border rounded-lg p-2 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalInv').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm">Batal</button>
                <button type="submit" name="simpan_inventaris" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800">Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
