<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Buku Tamu Presensi';
$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_tamu'])) {
    $nama = trim($_POST['nama']);
    $kat = $_POST['kategori_pemustaka'];
    $inst = trim($_POST['asal_institusi']);
    $kep = trim($_POST['keperluan']);
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("INSERT INTO kunjungan (tanggal, nama, kategori_pemustaka, asal_institusi, keperluan) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$tgl, $nama, $kat, $inst, $kep]);
    $pesan = "Kehadiran pemustaka berhasil dicatat.";
}

$daftarKunjungan = $pdo->query("SELECT * FROM kunjungan ORDER BY id DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Presensi Kunjungan Pemustaka</h2>
        <p class="text-sm text-slate-500">Buku tamu harian pengunjung pojok baca dari berbagai lapisan masyarakat.</p>
    </div>
    <button onclick="document.getElementById('modalTamu').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-pen-to-square"></i> Isi Buku Tamu
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
                    <th class="p-3">Nama Pengunjung</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3">Asal Sekolah / Dusun</th>
                    <th class="p-3">Keperluan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarKunjungan)): ?>
                <tr><td colspan="5" class="p-6 text-center text-slate-400">Belum ada catatan kunjungan pemustaka.</td></tr>
                <?php else: foreach ($daftarKunjungan as $k): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 text-xs font-mono"><?= date('d/m/Y', strtotime($k['tanggal'])) ?></td>
                    <td class="p-3 font-bold text-slate-800"><?= e($k['nama']) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium bg-slate-100 text-slate-700"><?= e($k['kategori_pemustaka']) ?></span>
                    </td>
                    <td class="p-3 text-slate-600"><?= e($k['asal_institusi']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($k['keperluan']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tamu -->
<div id="modalTamu" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800">Form Presensi Kunjungan</h3>
            <button onclick="document.getElementById('modalTamu').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="kunjungan.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pemustaka</label>
                <input type="text" name="nama" required placeholder="Nama lengkap..." class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori</label>
                    <select name="kategori_pemustaka" required class="w-full border rounded-lg p-2 text-sm">
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="SMA">SMA</option>
                        <option value="Warga Umum">Warga Umum</option>
                        <option value="Aparatur Desa">Aparatur Desa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal</label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat</label>
                <input type="text" name="asal_institusi" required placeholder="Contoh: SMAN 1 Sambas / Dusun Sejiram" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keperluan</label>
                <input type="text" name="keperluan" required placeholder="Membaca buku / Diskusi / Meminjam buku" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalTamu').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm">Batal</button>
                <button type="submit" name="simpan_tamu" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800">Simpan Presensi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
