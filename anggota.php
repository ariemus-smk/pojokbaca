<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Data Anggota Pemustaka';
$pesan = '';
$tipePesan = '';

$kategoriList = [
    'SD',
    'SMP',
    'SMA',
    'Warga Umum',
    'Aparatur Desa'
];

$statusList = [
    'Aktif',
    'Nonaktif'
];

// 1. Tambah Anggota Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_anggota']) || isset($_POST['simpan_anggota']))) {
    $no = trim($_POST['no_anggota'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $tingkat = $_POST['kategori_tingkat'] ?? '';
    $institusi = trim($_POST['institusi'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';

    if (empty($no) || empty($nama) || empty($institusi) || empty($kontak)) {
        $pesan = 'Mohon lengkapi seluruh data anggota bertanda bintang!';
        $tipePesan = 'error';
    } elseif (!in_array($tingkat, $kategoriList, true)) {
        $pesan = 'Kategori jenjang/tingkat tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($status, $statusList, true)) {
        $pesan = 'Status anggota tidak valid!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi no_anggota
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE no_anggota = ?");
        $stmtCek->execute([$no]);
        if ($stmtCek->fetchColumn() > 0) {
            $pesan = "Nomor anggota '{$no}' sudah digunakan! Gunakan nomor anggota yang berbeda.";
            $tipePesan = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO anggota (no_anggota, nama, kategori_tingkat, institusi, kontak, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$no, $nama, $tingkat, $institusi, $kontak, $status]);
                $pesan = "Anggota baru '{$nama}' ({$no}) berhasil didaftarkan.";
                $tipePesan = 'success';
            } catch (PDOException $e) {
                $pesan = "Gagal mendaftarkan anggota: " . $e->getMessage();
                $tipePesan = 'error';
            }
        }
    }
}

// 2. Edit Data Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_anggota'])) {
    $id = (int)($_POST['anggota_id'] ?? 0);
    $no = trim($_POST['no_anggota'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $tingkat = $_POST['kategori_tingkat'] ?? '';
    $institusi = trim($_POST['institusi'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');
    $status = $_POST['status'] ?? 'Aktif';

    if ($id <= 0) {
        $pesan = 'ID anggota tidak valid!';
        $tipePesan = 'error';
    } elseif (empty($no) || empty($nama) || empty($institusi) || empty($kontak)) {
        $pesan = 'Mohon lengkapi seluruh data anggota bertanda bintang!';
        $tipePesan = 'error';
    } elseif (!in_array($tingkat, $kategoriList, true)) {
        $pesan = 'Kategori jenjang/tingkat tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($status, $statusList, true)) {
        $pesan = 'Status anggota tidak valid!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi no_anggota pada anggota lain
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM anggota WHERE no_anggota = ? AND id != ?");
        $stmtCek->execute([$no, $id]);
        if ($stmtCek->fetchColumn() > 0) {
            $pesan = "Nomor anggota '{$no}' sudah digunakan oleh anggota lain!";
            $tipePesan = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE anggota SET no_anggota = ?, nama = ?, kategori_tingkat = ?, institusi = ?, kontak = ?, status = ? WHERE id = ?");
                $stmt->execute([$no, $nama, $tingkat, $institusi, $kontak, $status, $id]);
                $pesan = "Data anggota '{$nama}' ({$no}) berhasil diperbarui.";
                $tipePesan = 'success';
            } catch (PDOException $e) {
                $pesan = "Gagal memperbarui data anggota: " . $e->getMessage();
                $tipePesan = 'error';
            }
        }
    }
}

// 3. Hapus Anggota
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        // Cek apakah anggota ada
        $stmtCek = $pdo->prepare("SELECT nama, no_anggota FROM anggota WHERE id = ?");
        $stmtCek->execute([$idHapus]);
        $anggota = $stmtCek->fetch();

        if (!$anggota) {
            $pesan = "Data anggota tidak ditemukan!";
            $tipePesan = 'error';
        } else {
            // Cek apakah anggota masih memiliki buku yang sedang dipinjam
            $stmtCekPinjam = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE anggota_id = ? AND status = 'Dipinjam'");
            $stmtCekPinjam->execute([$idHapus]);
            $pinjamAktif = (int)$stmtCekPinjam->fetchColumn();

            if ($pinjamAktif > 0) {
                $pesan = "Anggota '{$anggota['nama']}' tidak dapat dihapus karena masih meminjam {$pinjamAktif} buku yang belum dikembalikan!";
                $tipePesan = 'error';
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM anggota WHERE id = ?");
                $stmtDel->execute([$idHapus]);
                $pesan = "Data anggota '{$anggota['nama']}' ({$anggota['no_anggota']}) berhasil dihapus.";
                $tipePesan = 'success';
            }
        }
    } catch (PDOException $e) {
        $pesan = "Gagal menghapus anggota: " . $e->getMessage();
        $tipePesan = 'error';
    }
}

// 4. Filter & Pencarian
$katFilter = $_GET['kategori'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM anggota WHERE 1=1";
$params = [];
if ($katFilter !== '') {
    $sql .= " AND kategori_tingkat = ?";
    $params[] = $katFilter;
}
if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
if ($cari !== '') {
    $sql .= " AND (nama LIKE ? OR no_anggota LIKE ? OR institusi LIKE ? OR kontak LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarAnggota = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Data Anggota Pemustaka</h2>
        <p class="text-sm text-slate-500">Pendaftaran pelajar SD, SMP, SMA, aparatur desa, dan warga umum Desa Sejiram.</p>
    </div>
    <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-user-plus"></i> Tambah Anggota
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
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari nomor anggota, nama, asal dusun/sekolah..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full sm:w-48">
        <select name="kategori" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Kategori --</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k ?>" <?= $katFilter === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-full sm:w-40">
        <select name="status" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Status --</option>
            <?php foreach ($statusList as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">Filter</button>
    <a href="anggota.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
</form>

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
                    <th class="p-3 text-center">Status</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarAnggota)): ?>
                <tr><td colspan="7" class="p-6 text-center text-slate-400">Belum ada data anggota yang cocok.</td></tr>
                <?php else: foreach ($daftarAnggota as $ag): ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 font-mono text-xs font-bold text-emerald-800"><?= e($ag['no_anggota']) ?></td>
                    <td class="p-3 font-bold text-slate-800"><?= e($ag['nama']) ?></td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><?= e($ag['kategori_tingkat']) ?></span>
                    </td>
                    <td class="p-3 text-slate-600"><?= e($ag['institusi']) ?></td>
                    <td class="p-3 font-mono text-xs text-slate-600"><?= e($ag['kontak']) ?></td>
                    <td class="p-3 text-center">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold <?= $ag['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                            <?= e($ag['status']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Edit Anggota -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => (int)$ag['id'],
                                'no_anggota' => $ag['no_anggota'],
                                'nama' => $ag['nama'],
                                'kategori_tingkat' => $ag['kategori_tingkat'],
                                'institusi' => $ag['institusi'],
                                'kontak' => $ag['kontak'],
                                'status' => $ag['status']
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting Data Anggota" aria-label="Sunting Data Anggota" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Hapus Anggota -->
                            <a href="anggota.php?hapus=<?= $ag['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data anggota \'<?= e(addslashes($ag['nama'])) ?>\' (<?= e($ag['no_anggota']) ?>)?')" title="Hapus Anggota" aria-label="Hapus Anggota" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
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

<!-- ================= MODAL TAMBAH ANGGOTA ================= -->
<div id="modalTambahAnggota" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-300"></i> Form Pendaftaran Anggota Baru
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="anggota.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="tambah_anggota" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Anggota <span class="text-rose-500">*</span></label>
                    <input type="text" name="no_anggota" required value="AGT-<?= rand(100, 999) ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenjang / Kategori <span class="text-rose-500">*</span></label>
                    <select name="kategori_tingkat" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" required placeholder="Contoh: Muhammad Farhan" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat Dusun <span class="text-rose-500">*</span></label>
                <input type="text" name="institusi" required placeholder="Contoh: Dusun Suka Maju / SMPN 1 Sejiram" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-rose-500">*</span></label>
                    <input type="text" name="kontak" required placeholder="Contoh: 08123456789" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Keanggotaan <span class="text-rose-500">*</span></label>
                    <select name="status" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="Aktif" selected>Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Anggota
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT ANGGOTA ================= -->
<div id="modalEditAnggota" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-amber-200"></i> Sunting Data Anggota Pemustaka
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="anggota.php" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_anggota" value="1">
            <input type="hidden" id="edit_anggota_id" name="anggota_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Anggota <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_no_anggota" name="no_anggota" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jenjang / Kategori <span class="text-rose-500">*</span></label>
                    <select id="edit_kategori_tingkat" name="kategori_tingkat" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_nama" name="nama" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Asal Sekolah / Alamat Dusun <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_institusi" name="institusi" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_kontak" name="kontak" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Keanggotaan <span class="text-rose-500">*</span></label>
                    <select id="edit_status" name="status" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
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
    document.getElementById('modalTambahAnggota').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalTambahAnggota').classList.add('hidden');
}

function bukaModalEdit(ag) {
    document.getElementById('edit_anggota_id').value = ag.id;
    document.getElementById('edit_no_anggota').value = ag.no_anggota || '';
    document.getElementById('edit_kategori_tingkat').value = ag.kategori_tingkat || 'Warga Umum';
    document.getElementById('edit_nama').value = ag.nama || '';
    document.getElementById('edit_institusi').value = ag.institusi || '';
    document.getElementById('edit_kontak').value = ag.kontak || '';
    document.getElementById('edit_status').value = ag.status || 'Aktif';

    document.getElementById('modalEditAnggota').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditAnggota').classList.add('hidden');
}

// Tutup modal saat klik luar
window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modalTambahAnggota');
    const modalEdit = document.getElementById('modalEditAnggota');
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

