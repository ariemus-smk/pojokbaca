<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekRoleAdmin(); // Khusus Administrator

$pageTitle = 'Pengaturan Identitas & Aturan';
$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $namaInstansi = trim($_POST['nama_instansi']);
    $desa = trim($_POST['desa']);
    $kabupaten = trim($_POST['kabupaten']);
    $alamat = trim($_POST['alamat']);
    $durasi = (int)$_POST['durasi_pinjam_hari'];
    $denda = (int)$_POST['denda_per_hari'];
    $kades = trim($_POST['nama_kades']);
    $pengelola = trim($_POST['nama_pengelola']);

    $stmt = $pdo->prepare("UPDATE pengaturan SET nama_instansi=?, desa=?, kabupaten=?, alamat=?, durasi_pinjam_hari=?, denda_per_hari=?, nama_kades=?, nama_pengelola=? WHERE id=1");
    $stmt->execute([$namaInstansi, $desa, $kabupaten, $alamat, $durasi, $denda, $kades, $pengelola]);
    $pesan = "Konfigurasi instansi dan aturan peminjaman berhasil diperbarui.";
}

$setting = $pdo->query("SELECT * FROM pengaturan LIMIT 1")->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Pengaturan Identitas Desa & Aturan</h2>
    <p class="text-sm text-slate-500">Atur kop surat laporan, tarif denda per hari, durasi batas peminjaman, serta nama pejabat pengesah.</p>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-300">
    <?= e($pesan) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 max-w-3xl">
    <form method="POST" action="pengaturan.php" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Perpustakaan / Pojok Baca</label>
                <input type="text" name="nama_instansi" required value="<?= e($setting['nama_instansi']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Desa</label>
                <input type="text" name="desa" required value="<?= e($setting['desa']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kabupaten</label>
                <input type="text" name="kabupaten" required value="<?= e($setting['kabupaten']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Kantor / Pojok Baca</label>
                <input type="text" name="alamat" required value="<?= e($setting['alamat']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Batas Waktu Pinjam (Hari)</label>
                <input type="number" name="durasi_pinjam_hari" min="1" required value="<?= (int)$setting['durasi_pinjam_hari'] ?>" class="w-full border rounded-lg p-2 text-sm">
                <span class="text-[11px] text-slate-500">Masa peminjaman standar buku</span>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Denda Keterlambatan / Hari (Rp)</label>
                <input type="number" name="denda_per_hari" min="0" required value="<?= (int)$setting['denda_per_hari'] ?>" class="w-full border rounded-lg p-2 text-sm">
                <span class="text-[11px] text-slate-500">Dihitung otomatis per hari telat</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Kepala Desa (Penandatangan)</label>
                <input type="text" name="nama_kades" required value="<?= e($setting['nama_kades']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Koordinator Pengelola</label>
                <input type="text" name="nama_pengelola" required value="<?= e($setting['nama_pengelola']) ?>" class="w-full border rounded-lg p-2 text-sm">
            </div>
        </div>

        <div class="pt-4 border-t flex justify-end">
            <button type="submit" name="simpan_pengaturan" class="bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-emerald-800 shadow-sm">
                Simpan Perubahan Pengaturan
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
