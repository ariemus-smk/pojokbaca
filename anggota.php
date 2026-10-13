<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Data Anggota Pemustaka';
$pesan = '';
$tipePesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_anggota'])) {
    $no = trim($_POST['no_anggota']);
    $nama = trim($_POST['nama']);
    $tingkat = $_POST['kategori_tingkat'];
    $institusi = trim($_POST['institusi']);
    $kontak = trim($_POST['kontak']);
    $status = $_POST['status'] ?? 'Aktif';

    try {
        $stmt = $pdo->prepare("INSERT INTO anggota (no_anggota, nama, kategori_tingkat, institusi, kontak, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$no, $nama, $tingkat, $institusi, $kontak, $status]);
        $pesan = "Anggota baru berhasil didaftarkan.";
        $tipePesan = 'success';
    } catch (PDOException $e) {
        $pesan = "Gagal mendaftarkan: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

$daftarAnggota = $pdo->query("SELECT * FROM anggota ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Data Anggota Pemustaka</h2>
        <p class="text-sm text-slate-500">Pendaftaran pelajar SD, SMP, SMA, aparatur desa, dan warga umum Desa Sejiram.</p>
    </div>
    <button onclick="document.getElementById('modalAnggota').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-user-plus"></i> Tambah Anggota
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-lg <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
    <?= e($pesan) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3">No Anggota</th>
                    <th class="p-3">Nama Lengkap</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3">Asal Sekolah / Alamat Dusun</th>
                    <th class="p-3">Kontak / WA</th>
                    <th class="p-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarAnggota)): ?>
                <tr><td colspan="6" class="p-6 text-center text-slate-400">Belum ada anggota terdaftar.</td></tr>
                <?php else: foreach ($daftarAnggota as $ag): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 font-mono text-xs font-bold text-emerald-800"><?= e($ag['no_anggota']) ?></td>
                    <td class="p-3 font-bold text-slate-800"><?= e($ag['nama']) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-slate-100 text-slate-700"><?= e($ag['kategori_tingkat']) ?></span>
                    </td>
                    <td class="p-3 text-slate-600"><?= e($ag['institusi']) ?></td>
                    <td class="p-3 font-mono text-xs text-slate-600"><?= e($ag['kontak']) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-0.5 rounded font-medium <?= $ag['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                            <?= e($ag['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Anggota -->
<div id="modalAnggota" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800">Form Pendaftaran Anggota Baru</h3>
            <button onclick="document.getElementById('modalAnggota').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="anggota.php" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Anggota</label>
                    <input type="text" name="no_anggota" required value="AGT-<?= rand(100, 999) ?>" class="w-full border rounded-lg p-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenjang / Kategori</label>
                    <select name="kategori_tingkat" required class="w-full border rounded-lg p-2 text-sm">
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="SMA">SMA</option>
                        <option value="Warga Umum">Warga Umum</option>
                        <option value="Aparatur Desa">Aparatur Desa</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="nama" required placeholder="Nama lengkap..." class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat Dusun</label>
                <input type="text" name="institusi" required placeholder="Misal: Dusun Suka Maju / SMPN 1 Sejiram" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp / HP</label>
                <input type="text" name="kontak" required placeholder="08xxxxxxxxxx" class="w-full border rounded-lg p-2 text-sm">
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalAnggota').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm">Batal</button>
                <button type="submit" name="simpan_anggota" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800">Simpan Anggota</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
