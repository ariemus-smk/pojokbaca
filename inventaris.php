<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Inventaris & Pengadaan';
$pesan = '';
$tipePesan = '';

$jenisList = ['Rusak', 'Hilang', 'Pengadaan'];

// 1. Tambah Log Inventaris
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_inventaris']) || isset($_POST['simpan_inventaris']))) {
    $jenis = $_POST['jenis'] ?? '';
    $bukuId = !empty($_POST['buku_id']) ? (int)$_POST['buku_id'] : null;
    $judulManual = trim($_POST['judul_manual'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 1);
    $sumber = trim($_POST['sumber_dana'] ?? '');
    $ket = trim($_POST['keterangan'] ?? '');
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    if (!in_array($jenis, $jenisList, true)) {
        $pesan = 'Jenis mutasi inventaris tidak valid!';
        $tipePesan = 'error';
    } elseif (empty($bukuId) && empty($judulManual)) {
        $pesan = 'Silakan pilih buku dari katalog atau ketikkan judul buku manual!';
        $tipePesan = 'error';
    } elseif ($jumlah < 1) {
        $pesan = 'Jumlah eksemplar minimal harus 1!';
        $tipePesan = 'error';
    } elseif (empty($ket)) {
        $pesan = 'Keterangan/alasan mutasi inventaris wajib diisi!';
        $tipePesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO inventaris (jenis, buku_id, judul_manual, jumlah, sumber_dana, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$jenis, $bukuId, $judulManual, $jumlah, $sumber, $ket, $tgl]);
            $pesan = "Catatan inventaris berhasil disimpan.";
            $tipePesan = 'success';
        } catch (PDOException $e) {
            $pesan = "Gagal menyimpan catatan inventaris: " . $e->getMessage();
            $tipePesan = 'error';
        }
    }
}

// 2. Edit Log Inventaris
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_inventaris'])) {
    $id = (int)($_POST['inventaris_id'] ?? 0);
    $jenis = $_POST['jenis'] ?? '';
    $bukuId = !empty($_POST['buku_id']) ? (int)$_POST['buku_id'] : null;
    $judulManual = trim($_POST['judul_manual'] ?? '');
    $jumlah = (int)($_POST['jumlah'] ?? 1);
    $sumber = trim($_POST['sumber_dana'] ?? '');
    $ket = trim($_POST['keterangan'] ?? '');
    $tgl = $_POST['tanggal'] ?? date('Y-m-d');

    if ($id <= 0) {
        $pesan = 'ID catatan inventaris tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($jenis, $jenisList, true)) {
        $pesan = 'Jenis mutasi inventaris tidak valid!';
        $tipePesan = 'error';
    } elseif (empty($bukuId) && empty($judulManual)) {
        $pesan = 'Silakan pilih buku dari katalog atau ketikkan judul buku manual!';
        $tipePesan = 'error';
    } elseif ($jumlah < 1) {
        $pesan = 'Jumlah eksemplar minimal harus 1!';
        $tipePesan = 'error';
    } elseif (empty($ket)) {
        $pesan = 'Keterangan/alasan mutasi inventaris wajib diisi!';
        $tipePesan = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE inventaris SET jenis = ?, buku_id = ?, judul_manual = ?, jumlah = ?, sumber_dana = ?, keterangan = ?, tanggal = ? WHERE id = ?");
            $stmt->execute([$jenis, $bukuId, $judulManual, $jumlah, $sumber, $ket, $tgl, $id]);
            $pesan = "Catatan inventaris berhasil diperbarui.";
            $tipePesan = 'success';
        } catch (PDOException $e) {
            $pesan = "Gagal memperbarui catatan inventaris: " . $e->getMessage();
            $tipePesan = 'error';
        }
    }
}

// 3. Hapus Log Inventaris
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        $stmtCek = $pdo->prepare("
            SELECT i.*, b.judul AS judul_relasi 
            FROM inventaris i 
            LEFT JOIN buku b ON i.buku_id = b.id 
            WHERE i.id = ?
        ");
        $stmtCek->execute([$idHapus]);
        $inv = $stmtCek->fetch();

        if (!$inv) {
            $pesan = 'Catatan inventaris tidak ditemukan!';
            $tipePesan = 'error';
        } else {
            $judulTampil = $inv['judul_relasi'] ?: ($inv['judul_manual'] ?: 'Buku');
            $stmtDel = $pdo->prepare("DELETE FROM inventaris WHERE id = ?");
            $stmtDel->execute([$idHapus]);
            $pesan = "Catatan inventaris [{$inv['jenis']}] '{$judulTampil}' berhasil dihapus.";
            $tipePesan = 'success';
        }
    } catch (PDOException $e) {
        $pesan = "Gagal menghapus catatan inventaris: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

// 4. Filter & Pencarian
$jenisFilter = $_GET['jenis'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "
    SELECT i.*, b.judul AS judul_relasi, b.kode_buku 
    FROM inventaris i 
    LEFT JOIN buku b ON i.buku_id = b.id 
    WHERE 1=1
";
$params = [];
if ($jenisFilter !== '') {
    $sql .= " AND i.jenis = ?";
    $params[] = $jenisFilter;
}
if ($cari !== '') {
    $sql .= " AND (b.judul LIKE ? OR i.judul_manual LIKE ? OR b.kode_buku LIKE ? OR i.sumber_dana LIKE ? OR i.keterangan LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY i.tanggal DESC, i.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarInv = $stmt->fetchAll();

$daftarBuku = $pdo->query("SELECT id, kode_buku, judul FROM buku ORDER BY judul ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Inventaris, Kerusakan & Pengadaan</h2>
        <p class="text-sm text-slate-500">Pencatatan buku rusak, hilang, serta pengadaan bantuan Dana Desa / Donasi.</p>
    </div>
    <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-plus"></i> Catat Log Inventaris
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
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari judul buku, kode, sumber dana, keterangan..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full sm:w-48">
        <select name="jenis" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Jenis Mutasi --</option>
            <?php foreach ($jenisList as $j): ?>
                <option value="<?= $j ?>" <?= $jenisFilter === $j ? 'selected' : '' ?>><?= $j ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">Filter</button>
    <a href="inventaris.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
</form>

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
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarInv)): ?>
                <tr><td colspan="7" class="p-6 text-center text-slate-400">Belum ada riwayat inventaris/pengadaan yang cocok.</td></tr>
                <?php else: foreach ($daftarInv as $iv): 
                    $judulTampil = $iv['judul_relasi'] ?: $iv['judul_manual'];
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 font-mono text-xs font-medium text-slate-700"><?= date('d/m/Y', strtotime($iv['tanggal'])) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold <?= $iv['jenis'] === 'Pengadaan' ? 'bg-emerald-100 text-emerald-800' : ($iv['jenis'] === 'Hilang' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= e($iv['jenis']) ?>
                        </span>
                    </td>
                    <td class="p-3 font-bold text-slate-800">
                        <?= e($judulTampil) ?>
                        <?php if (!empty($iv['kode_buku'])): ?>
                            <span class="block text-[11px] font-mono text-slate-400 font-normal"><?= e($iv['kode_buku']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-center font-bold text-slate-700"><?= $iv['jumlah'] ?> eks</td>
                    <td class="p-3 text-xs text-slate-600"><?= e($iv['sumber_dana'] ?: '-') ?></td>
                    <td class="p-3 text-xs text-slate-600"><?= e($iv['keterangan']) ?></td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Edit Inventaris -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => (int)$iv['id'],
                                'jenis' => $iv['jenis'],
                                'buku_id' => $iv['buku_id'] ? (int)$iv['buku_id'] : '',
                                'judul_manual' => $iv['judul_manual'] ?: '',
                                'jumlah' => (int)$iv['jumlah'],
                                'sumber_dana' => $iv['sumber_dana'] ?: '',
                                'keterangan' => $iv['keterangan'] ?: '',
                                'tanggal' => $iv['tanggal']
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting Catatan Inventaris" aria-label="Sunting Catatan Inventaris" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Hapus Inventaris -->
                            <a href="inventaris.php?hapus=<?= $iv['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan [<?= e($iv['jenis']) ?>] \'<?= e(addslashes($judulTampil)) ?>\'?')" title="Hapus Catatan" aria-label="Hapus Catatan" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
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

<!-- ================= MODAL TAMBAH INVENTARIS ================= -->
<div id="modalTambahInv" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-boxes-stacked text-emerald-300"></i> Catat Buku Rusak / Hilang / Pengadaan
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="inventaris.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="tambah_inventaris" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Mutasi <span class="text-rose-500">*</span></label>
                    <select name="jenis" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="Rusak">Buku Rusak</option>
                        <option value="Hilang">Buku Hilang</option>
                        <option value="Pengadaan">Pengadaan Buku Baru</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Mutasi <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Koleksi Buku Katalog (Jika Sudah Ada)</label>
                <select name="buku_id" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                    <option value="">-- Pilih Buku atau Tulis Manual di Bawah --</option>
                    <?php foreach ($daftarBuku as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['judul']) ?> (<?= e($b['kode_buku']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku Manual (Jika Belum Terdaftar di Katalog)</label>
                <input type="text" name="judul_manual" placeholder="Tuliskan judul jika pengadaan buku baru..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                <span class="text-[10px] text-slate-400 mt-1 block">Wajib diisi jika tidak memilih buku dari katalog.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Eksemplar <span class="text-rose-500">*</span></label>
                    <input type="number" name="jumlah" min="1" value="1" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sumber Dana / Asal Donasi</label>
                    <input type="text" name="sumber_dana" placeholder="Contoh: Dana Desa / Donasi Warga" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Alasan <span class="text-rose-500">*</span></label>
                <textarea name="keterangan" required rows="2" placeholder="Catatan kondisi kerusakan, kehilangan, atau rincian pengadaan..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Catatan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT INVENTARIS ================= -->
<div id="modalEditInv" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-200"></i> Sunting Catatan Inventaris
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="inventaris.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_inventaris" value="1">
            <input type="hidden" id="edit_inventaris_id" name="inventaris_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Mutasi <span class="text-rose-500">*</span></label>
                    <select id="edit_jenis" name="jenis" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="Rusak">Buku Rusak</option>
                        <option value="Hilang">Buku Hilang</option>
                        <option value="Pengadaan">Pengadaan Buku Baru</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Mutasi <span class="text-rose-500">*</span></label>
                    <input type="date" id="edit_tanggal" name="tanggal" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Koleksi Buku Katalog (Jika Ada)</label>
                <select id="edit_buku_id" name="buku_id" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    <option value="">-- Tidak Memilih Buku Katalog (Manual) --</option>
                    <?php foreach ($daftarBuku as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['judul']) ?> (<?= e($b['kode_buku']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku Manual (Jika Tidak Memilih Katalog)</label>
                <input type="text" id="edit_judul_manual" name="judul_manual" placeholder="Tuliskan judul jika pengadaan buku baru..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                <span class="text-[10px] text-slate-400 mt-1 block">Wajib diisi jika buku tidak diambil dari katalog.</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Eksemplar <span class="text-rose-500">*</span></label>
                    <input type="number" id="edit_jumlah" name="jumlah" min="1" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sumber Dana / Asal Donasi</label>
                    <input type="text" id="edit_sumber_dana" name="sumber_dana" placeholder="Contoh: Dana Desa / Donasi Warga" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Alasan <span class="text-rose-500">*</span></label>
                <textarea id="edit_keterangan" name="keterangan" required rows="2" placeholder="Catatan kondisi kerusakan, kehilangan, atau rincian pengadaan..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500"></textarea>
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
    document.getElementById('modalTambahInv').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalTambahInv').classList.add('hidden');
}

function bukaModalEdit(inv) {
    document.getElementById('edit_inventaris_id').value = inv.id;
    document.getElementById('edit_jenis').value = inv.jenis || 'Rusak';
    document.getElementById('edit_tanggal').value = inv.tanggal || '';
    document.getElementById('edit_buku_id').value = inv.buku_id || '';
    document.getElementById('edit_judul_manual').value = inv.judul_manual || '';
    document.getElementById('edit_jumlah').value = inv.jumlah || 1;
    document.getElementById('edit_sumber_dana').value = inv.sumber_dana || '';
    document.getElementById('edit_keterangan').value = inv.keterangan || '';

    document.getElementById('modalEditInv').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditInv').classList.add('hidden');
}

// Tutup modal saat klik luar
window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modalTambahInv');
    const modalEdit = document.getElementById('modalEditInv');
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

