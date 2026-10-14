<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Buku Tamu Presensi';
$pesan = '';
$tipePesan = '';

$kategoriList = [
    'SD',
    'SMP',
    'SMA',
    'Warga Umum',
    'Aparatur Desa'
];

// 1. Catat Presensi Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_kunjungan']) || isset($_POST['simpan_tamu']))) {
    $nama = trim($_POST['nama'] ?? '');
    $kat = $_POST['kategori_pemustaka'] ?? '';
    $inst = trim($_POST['asal_institusi'] ?? '');
    $kep = trim($_POST['keperluan'] ?? '');
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    if (empty($nama) || empty($inst) || empty($kep) || empty($tgl)) {
        $pesan = 'Mohon lengkapi seluruh kolom formulir presensi!';
        $tipePesan = 'error';
    } elseif (!in_array($kat, $kategoriList, true)) {
        $pesan = 'Kategori pemustaka tidak valid!';
        $tipePesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO kunjungan (tanggal, nama, kategori_pemustaka, asal_institusi, keperluan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tgl, $nama, $kat, $inst, $kep]);
            $pesan = "Presensi kunjungan atas nama '{$nama}' berhasil dicatat.";
            $tipePesan = 'success';
        } catch (PDOException $e) {
            $pesan = "Gagal mencatat presensi: " . $e->getMessage();
            $tipePesan = 'error';
        }
    }
}

// 2. Edit Data Presensi Kunjungan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_kunjungan'])) {
    $id = (int)($_POST['kunjungan_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $kat = $_POST['kategori_pemustaka'] ?? '';
    $inst = trim($_POST['asal_institusi'] ?? '');
    $kep = trim($_POST['keperluan'] ?? '');
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    if ($id <= 0) {
        $pesan = 'ID kunjungan tidak valid!';
        $tipePesan = 'error';
    } elseif (empty($nama) || empty($inst) || empty($kep) || empty($tgl)) {
        $pesan = 'Mohon lengkapi seluruh kolom formulir presensi!';
        $tipePesan = 'error';
    } elseif (!in_array($kat, $kategoriList, true)) {
        $pesan = 'Kategori pemustaka tidak valid!';
        $tipePesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE kunjungan SET tanggal = ?, nama = ?, kategori_pemustaka = ?, asal_institusi = ?, keperluan = ? WHERE id = ?");
            $stmt->execute([$tgl, $nama, $kat, $inst, $kep, $id]);
            $pesan = "Data presensi kunjungan '{$nama}' berhasil diperbarui.";
            $tipePesan = 'success';
        } catch (PDOException $e) {
            $pesan = "Gagal memperbarui presensi: " . $e->getMessage();
            $tipePesan = 'error';
        }
    }
}

// 3. Hapus Data Presensi Kunjungan
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        $stmtCek = $pdo->prepare("SELECT nama, tanggal FROM kunjungan WHERE id = ?");
        $stmtCek->execute([$idHapus]);
        $kunjungan = $stmtCek->fetch();

        if (!$kunjungan) {
            $pesan = 'Data presensi kunjungan tidak ditemukan!';
            $tipePesan = 'error';
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM kunjungan WHERE id = ?");
            $stmtDel->execute([$idHapus]);
            $pesan = "Presensi kunjungan atas nama '{$kunjungan['nama']}' berhasil dihapus.";
            $tipePesan = 'success';
        }
    } catch (PDOException $e) {
        $pesan = "Gagal menghapus presensi: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

// 4. Filter & Pencarian
$katFilter = $_GET['kategori'] ?? '';
$tglFilter = $_GET['tanggal'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM kunjungan WHERE 1=1";
$params = [];
if ($katFilter !== '') {
    $sql .= " AND kategori_pemustaka = ?";
    $params[] = $katFilter;
}
if ($tglFilter !== '') {
    $sql .= " AND tanggal = ?";
    $params[] = $tglFilter;
}
if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR asal_institusi LIKE ? OR keperluan LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY tanggal DESC, id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarKunjungan = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Presensi Kunjungan Pemustaka</h2>
        <p class="text-sm text-slate-500">Buku tamu harian pengunjung pojok baca dari berbagai lapisan masyarakat.</p>
    </div>
    <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-pen-to-square"></i> Isi Buku Tamu
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-xl <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?> flex items-center gap-2 shadow-sm">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg"></i>
    <span class="text-sm font-medium"><?= e($pesan) ?></span>
</div>
<?php endif; ?>

<!-- Baris Filter & Cari -->
<form method="GET" class="bg-white p-4 rounded-xl border border-slate-200 mb-6 flex flex-wrap gap-3 items-center shadow-sm">
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama pemustaka, sekolah/dusun, keperluan..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full sm:w-48">
        <select name="kategori" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Kategori --</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k ?>" <?= $katFilter === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-full sm:w-44">
        <input type="date" name="tanggal" value="<?= e($tglFilter) ?>" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">Filter</button>
    <a href="kunjungan.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
</form>

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
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarKunjungan)): ?>
                <tr><td colspan="6" class="p-6 text-center text-slate-400">Belum ada catatan kunjungan pemustaka yang cocok.</td></tr>
                <?php else: foreach ($daftarKunjungan as $k): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 text-xs font-mono font-medium text-slate-700"><?= date('d/m/Y', strtotime($k['tanggal'])) ?></td>
                    <td class="p-3 font-bold text-slate-800"><?= e($k['nama']) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($k['kategori_pemustaka']) ?></span>
                    </td>
                    <td class="p-3 text-slate-600"><?= e($k['asal_institusi']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($k['keperluan']) ?></td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Edit Presensi -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => (int)$k['id'],
                                'tanggal' => $k['tanggal'],
                                'nama' => $k['nama'],
                                'kategori_pemustaka' => $k['kategori_pemustaka'],
                                'asal_institusi' => $k['asal_institusi'],
                                'keperluan' => $k['keperluan']
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting Data Presensi" aria-label="Sunting Data Presensi" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Hapus Presensi -->
                            <a href="kunjungan.php?hapus=<?= $k['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan presensi kunjungan \'<?= e(addslashes($k['nama'])) ?>\' tanggal <?= date('d/m/Y', strtotime($k['tanggal'])) ?>?')" title="Hapus Presensi" aria-label="Hapus Presensi" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL TAMBAH KUNJUNGAN ================= -->
<div id="modalTambahKunjungan" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-book-open-reader text-emerald-300"></i> Form Presensi Kunjungan Baru
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="kunjungan.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="tambah_kunjungan" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pemustaka <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" required placeholder="Contoh: Siti Nurhaliza" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Pemustaka <span class="text-rose-500">*</span></label>
                    <select name="kategori_pemustaka" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kunjungan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat Dusun <span class="text-rose-500">*</span></label>
                <input type="text" name="asal_institusi" required placeholder="Contoh: Dusun Sejiram / SMPN 1 Tebas" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keperluan Kunjungan <span class="text-rose-500">*</span></label>
                <input type="text" name="keperluan" required placeholder="Contoh: Membaca buku literasi / Diskusi tugas" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Presensi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT KUNJUNGAN ================= -->
<div id="modalEditKunjungan" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-amber-200"></i> Sunting Data Presensi Kunjungan
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="kunjungan.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_kunjungan" value="1">
            <input type="hidden" id="edit_kunjungan_id" name="kunjungan_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pemustaka <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_nama" name="nama" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Pemustaka <span class="text-rose-500">*</span></label>
                    <select id="edit_kategori_pemustaka" name="kategori_pemustaka" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kunjungan <span class="text-rose-500">*</span></label>
                    <input type="date" id="edit_tanggal" name="tanggal" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat Dusun <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_asal_institusi" name="asal_institusi" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keperluan Kunjungan <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_keperluan" name="keperluan" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalEdit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalTambah() {
    document.getElementById('modalTambahKunjungan').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalTambahKunjungan').classList.add('hidden');
}

function bukaModalEdit(kunjungan) {
    document.getElementById('edit_kunjungan_id').value = kunjungan.id;
    document.getElementById('edit_nama').value = kunjungan.nama || '';
    document.getElementById('edit_kategori_pemustaka').value = kunjungan.kategori_pemustaka || 'Warga Umum';
    document.getElementById('edit_tanggal').value = kunjungan.tanggal || '';
    document.getElementById('edit_asal_institusi').value = kunjungan.asal_institusi || '';
    document.getElementById('edit_keperluan').value = kunjungan.keperluan || '';

    document.getElementById('modalEditKunjungan').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditKunjungan').classList.add('hidden');
}

// Tutup modal jika klik di luar area dialog
window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modalTambahKunjungan');
    const modalEdit = document.getElementById('modalEditKunjungan');
    if (e.target === modalTambah) tutupModalTambah();
    if (e.target === modalEdit) tutupModalEdit();
});

// Tutup modal dengan Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupModalTambah();
        tutupModalEdit();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

