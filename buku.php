<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Data Koleksi Buku';
$pesan = '';
$tipePesan = '';

$uploadDirCover = __DIR__ . '/uploads/covers/';
if (!is_dir($uploadDirCover)) {
    mkdir($uploadDirCover, 0755, true);
}

$kategoriList = [
    'Koleksi buku anak',
    'Buku pendidikan',
    'Buku kesehatan',
    'Buku keterampilan',
    'Buku agama',
    'Buku budaya/kearifan lokal',
    'Buku lainnya'
];

// Simpan / Edit Buku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_buku'])) {
    $kode = trim($_POST['kode_buku']);
    $judul = trim($_POST['judul']);
    $penulis = trim($_POST['penulis']);
    $penerbit = trim($_POST['penerbit']);
    $tahun = (int)$_POST['tahun'];
    $kategori = $_POST['kategori'];
    $lokasi = trim($_POST['lokasi_rak']);
    $stok = (int)$_POST['stok_total'];
    $kondisi = $_POST['kondisi'];
    $editId = !empty($_POST['buku_id']) ? (int)$_POST['buku_id'] : null;

    // Handle Upload Cover (JPG/PNG/WEBP)
    $coverName = null;
    $uploadOk = true;

    if (isset($_FILES['cover_buku']) && $_FILES['cover_buku']['error'] === UPLOAD_ERR_OK) {
        $fileCover = $_FILES['cover_buku'];
        $ext = strtolower(pathinfo($fileCover['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $pesan = 'Format sampul buku harus JPG, JPEG, PNG, atau WEBP!';
            $tipePesan = 'error';
            $uploadOk = false;
        } elseif ($fileCover['size'] > 3 * 1024 * 1024) {
            $pesan = 'Ukuran cover buku maksimal 3 MB!';
            $tipePesan = 'error';
            $uploadOk = false;
        } else {
            $coverName = 'cover_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
            if (!move_uploaded_file($fileCover['tmp_name'], $uploadDirCover . $coverName)) {
                $pesan = 'Gagal menyimpan file cover buku ke server!';
                $tipePesan = 'error';
                $uploadOk = false;
            }
        }
    }

    if ($uploadOk) {
        try {
            if ($editId) {
                if ($coverName) {
                    // Hapus cover lama
                    $stmtOld = $pdo->prepare("SELECT cover FROM buku WHERE id = ?");
                    $stmtOld->execute([$editId]);
                    $oldCover = $stmtOld->fetchColumn();
                    if ($oldCover && file_exists($uploadDirCover . $oldCover)) {
                        @unlink($uploadDirCover . $oldCover);
                    }

                    $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, lokasi_rak=?, stok_total=?, kondisi=?, cover=? WHERE id=?");
                    $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $kondisi, $coverName, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, lokasi_rak=?, stok_total=?, kondisi=? WHERE id=?");
                    $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $kondisi, $editId]);
                }
                $pesan = "Data buku berhasil diperbarui.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO buku (kode_buku, judul, penulis, penerbit, tahun, kategori, lokasi_rak, stok_total, stok_tersedia, kondisi, cover) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $stok, $kondisi, $coverName]);
                $pesan = "Koleksi buku baru berhasil ditambahkan lengkap dengan cover.";
            }
            $tipePesan = 'success';
        } catch (PDOException $ex) {
            $pesan = "Gagal menyimpan buku: " . $ex->getMessage();
            $tipePesan = 'error';
        }
    }
}

// Hapus Buku
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        $stmtOld = $pdo->prepare("SELECT cover FROM buku WHERE id = ?");
        $stmtOld->execute([$idHapus]);
        $oldCover = $stmtOld->fetchColumn();
        if ($oldCover && file_exists($uploadDirCover . $oldCover)) {
            @unlink($uploadDirCover . $oldCover);
        }

        $stmt = $pdo->prepare("DELETE FROM buku WHERE id = ?");
        $stmt->execute([$idHapus]);
        $pesan = "Buku berhasil dihapus dari katalog.";
        $tipePesan = 'success';
    } catch (PDOException $ex) {
        $pesan = "Buku tidak dapat dihapus karena masih terkait riwayat transaksi peminjaman.";
        $tipePesan = 'error';
    }
}

// Filter & Cari
$katFilter = $_GET['kategori'] ?? '';
$cari = $_GET['cari'] ?? '';

$sql = "SELECT * FROM buku WHERE 1=1";
$params = [];
if ($katFilter !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $katFilter;
}
if ($cari !== '') {
    $sql .= " AND (judul LIKE ? OR kode_buku LIKE ? OR penulis LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarBuku = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Koleksi Buku & Sampul</h2>
        <p class="text-sm text-slate-500">Kelola katalog 7 kategori baku, stok fisik rak, kondisi, serta upload cover buku.</p>
    </div>
    <button onclick="document.getElementById('modalBuku').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-plus"></i> Tambah Koleksi Buku
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-4 rounded-lg <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-1.5"></i> <?= e($pesan) ?>
</div>
<?php endif; ?>

<!-- Baris Filter & Cari -->
<form method="GET" class="bg-white p-4 rounded-xl border border-slate-200 mb-6 flex flex-wrap gap-3 items-center shadow-sm">
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari kode buku, judul, atau penulis..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full md:w-56">
        <select name="kategori" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
            <option value="">-- Semua Kategori (7 Jenis) --</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k ?>" <?= $katFilter === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium">Filter</button>
    <a href="buku.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300">Reset</a>
</form>

<!-- Tabel Koleksi Buku -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3 text-center">Cover</th>
                    <th class="p-3">Kode</th>
                    <th class="p-3">Judul Buku & Penulis</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3">Lokasi Rak</th>
                    <th class="p-3 text-center">Stok (Ready / Total)</th>
                    <th class="p-3">Kondisi</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarBuku)): ?>
                <tr><td colspan="8" class="p-6 text-center text-slate-400">Tidak ada koleksi buku yang cocok.</td></tr>
                <?php else: foreach ($daftarBuku as $b): 
                    $coverFile = !empty($b['cover']) ? 'uploads/covers/' . $b['cover'] : null;
                    $hasCover = $coverFile && file_exists(__DIR__ . '/' . $coverFile);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 text-center w-16">
                        <?php if ($hasCover): ?>
                            <a href="<?= e($coverFile) ?>" target="_blank" title="Klik untuk memperbesar cover">
                                <img src="<?= e($coverFile) ?>" alt="Cover" class="w-12 h-16 object-cover rounded shadow border border-slate-200 mx-auto hover:scale-105 transition">
                            </a>
                        <?php else: ?>
                            <div class="w-12 h-16 bg-slate-100 rounded border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 mx-auto">
                                <i class="fa-solid fa-book text-base"></i>
                                <span class="text-[9px] mt-0.5">Polos</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 font-mono text-xs font-semibold text-slate-600"><?= e($b['kode_buku']) ?></td>
                    <td class="p-3">
                        <p class="font-bold text-slate-800"><?= e($b['judul']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($b['penulis']) ?> &bull; <?= e($b['penerbit']) ?> (<?= $b['tahun'] ?>)</p>
                    </td>
                    <td class="p-3">
                        <span class="inline-block bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs px-2.5 py-0.5 rounded-full font-medium"><?= e($b['kategori']) ?></span>
                    </td>
                    <td class="p-3 text-xs font-semibold text-slate-700"><?= e($b['lokasi_rak']) ?></td>
                    <td class="p-3 text-center">
                        <span class="font-bold text-emerald-700"><?= $b['stok_tersedia'] ?></span> / <span class="text-slate-500"><?= $b['stok_total'] ?></span>
                    </td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-0.5 rounded font-medium <?= $b['kondisi'] === 'Baik' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= e($b['kondisi']) ?></span>
                    </td>
                    <td class="p-3 text-center">
                        <a href="buku.php?hapus=<?= $b['id'] ?>" onclick="return confirm('Hapus koleksi buku ini?')" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tambah Koleksi -->
<div id="modalBuku" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-book-medical text-emerald-700"></i> Form Koleksi Buku & Cover
            </h3>
            <button onclick="document.getElementById('modalBuku').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="buku.php" enctype="multipart/form-data" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode Buku</label>
                    <input type="text" name="kode_buku" required placeholder="BK-008" class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Terbit</label>
                    <input type="number" name="tahun" required value="<?= date('Y') ?>" class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku</label>
                <input type="text" name="judul" required placeholder="Judul buku..." class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penulis</label>
                    <input type="text" name="penulis" required class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penerbit</label>
                    <input type="text" name="penerbit" required class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori (7 Baku)</label>
                    <select name="kategori" required class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Lokasi Rak</label>
                    <input type="text" name="lokasi_rak" required placeholder="Rak A1" class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Stok</label>
                    <input type="number" name="stok_total" min="1" value="1" required class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kondisi Fisik</label>
                    <select name="kondisi" class="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Parah">Rusak Parah</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Upload Cover Buku (JPG/JPEG/PNG/WEBP)</label>
                <input type="file" name="cover_buku" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full text-xs border rounded-lg p-2 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200">
                <span class="text-[11px] text-slate-500">Opsional, format JPG/PNG dengan ukuran maks 3MB.</span>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalBuku').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm">Batal</button>
                <button type="submit" name="simpan_buku" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800 transition">Simpan Koleksi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
