<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Kelola Artikel & Berita';
$pesan = '';
$tipePesan = '';

$uploadDirArtikel = __DIR__ . '/uploads/artikel/';
if (!is_dir($uploadDirArtikel)) {
    mkdir($uploadDirArtikel, 0755, true);
}

$kategoriList = [
    'Literasi Desa',
    'Kegiatan PKK',
    'Edukasi & Pertanian',
    'Kearifan Lokal'
];

$statusList = [
    'Publish',
    'Draft'
];

// 1. Simpan Artikel Baru / Edit Artikel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_artikel']) || isset($_POST['edit_artikel']))) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Literasi Desa';
    $konten = trim($_POST['konten'] ?? '');
    $status = $_POST['status'] ?? 'Publish';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $penulis = trim($_POST['penulis'] ?? ($_SESSION['user_nama'] ?? 'Pengelola Pojok Baca'));
    $editId = !empty($_POST['artikel_id']) ? (int)$_POST['artikel_id'] : null;
    $hapusGambar = !empty($_POST['hapus_gambar']);

    if (empty($judul) || empty($konten) || empty($penulis)) {
        $pesan = 'Judul artikel, penulis, dan isi tulisan wajib diisi!';
        $tipePesan = 'error';
    } elseif (!in_array($kategori, $kategoriList, true)) {
        $pesan = 'Kategori artikel tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($status, $statusList, true)) {
        $pesan = 'Status publikasi tidak valid!';
        $tipePesan = 'error';
    } else {
        // Handle Upload Gambar Artikel
        $gambarName = null;
        $uploadOk = true;

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['gambar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $pesan = 'Format gambar artikel harus JPG, JPEG, PNG, atau WEBP!';
                $tipePesan = 'error';
                $uploadOk = false;
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $pesan = 'Ukuran gambar artikel maksimal 5 MB!';
                $tipePesan = 'error';
                $uploadOk = false;
            } else {
                $gambarName = 'art_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDirArtikel . $gambarName)) {
                    $pesan = 'Gagal mengunggah berkas gambar ke server!';
                    $tipePesan = 'error';
                    $uploadOk = false;
                }
            }
        }

        if ($uploadOk) {
            try {
                if ($editId) {
                    // Ambil gambar lama jika ada
                    $stmtOld = $pdo->prepare("SELECT gambar FROM artikel WHERE id = ?");
                    $stmtOld->execute([$editId]);
                    $oldGbr = $stmtOld->fetchColumn();

                    $slugClean = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul), '-'));
                    $slug = $slugClean . '-' . $editId;

                    if ($gambarName) {
                        if ($oldGbr && file_exists($uploadDirArtikel . $oldGbr)) {
                            @unlink($uploadDirArtikel . $oldGbr);
                        }
                        $stmt = $pdo->prepare("UPDATE artikel SET judul=?, slug=?, kategori=?, konten=?, status=?, tanggal=?, penulis=?, gambar=? WHERE id=?");
                        $stmt->execute([$judul, $slug, $kategori, $konten, $status, $tanggal, $penulis, $gambarName, $editId]);
                    } elseif ($hapusGambar) {
                        if ($oldGbr && file_exists($uploadDirArtikel . $oldGbr)) {
                            @unlink($uploadDirArtikel . $oldGbr);
                        }
                        $stmt = $pdo->prepare("UPDATE artikel SET judul=?, slug=?, kategori=?, konten=?, status=?, tanggal=?, penulis=?, gambar=NULL WHERE id=?");
                        $stmt->execute([$judul, $slug, $kategori, $konten, $status, $tanggal, $penulis, $editId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE artikel SET judul=?, slug=?, kategori=?, konten=?, status=?, tanggal=?, penulis=? WHERE id=?");
                        $stmt->execute([$judul, $slug, $kategori, $konten, $status, $tanggal, $penulis, $editId]);
                    }
                    $pesan = "Artikel '{$judul}' berhasil diperbarui.";
                } else {
                    $slugClean = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul), '-'));
                    $slug = $slugClean . '-' . rand(100, 999);

                    $stmt = $pdo->prepare("INSERT INTO artikel (judul, slug, kategori, gambar, konten, penulis, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$judul, $slug, $kategori, $gambarName, $konten, $penulis, $tanggal, $status]);
                    $pesan = "Artikel baru '{$judul}' berhasil dipublikasikan.";
                }
                $tipePesan = 'success';
            } catch (PDOException $ex) {
                if ($gambarName && file_exists($uploadDirArtikel . $gambarName)) {
                    @unlink($uploadDirArtikel . $gambarName);
                }
                $pesan = "Gagal memproses artikel: " . $ex->getMessage();
                $tipePesan = 'error';
            }
        }
    }
}

// 2. Hapus Artikel
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        $stmtOld = $pdo->prepare("SELECT gambar, judul FROM artikel WHERE id = ?");
        $stmtOld->execute([$idHapus]);
        $artHapus = $stmtOld->fetch();

        if ($artHapus) {
            if (!empty($artHapus['gambar']) && file_exists($uploadDirArtikel . $artHapus['gambar'])) {
                @unlink($uploadDirArtikel . $artHapus['gambar']);
            }
            $stmt = $pdo->prepare("DELETE FROM artikel WHERE id = ?");
            $stmt->execute([$idHapus]);
            $pesan = "Artikel '{$artHapus['judul']}' berhasil dihapus.";
            $tipePesan = 'success';
        }
    } catch (PDOException $ex) {
        $pesan = "Gagal menghapus artikel: " . $ex->getMessage();
        $tipePesan = 'error';
    }
}

// 3. Filter & Pencarian
$katFilter = $_GET['kategori'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM artikel WHERE 1=1";
$params = [];
if ($katFilter !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $katFilter;
}
if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
if ($cari !== '') {
    $sql .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY tanggal DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarArtikel = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Manajemen Artikel & Berita Literasi</h2>
        <p class="text-sm text-slate-500">Publikasikan tulisan edukatif, berita kegiatan literasi pojok baca, dan kabar kegiatan PKK Desa Sejiram.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="portal.php#artikel" target="_blank" class="bg-blue-600 text-white px-3.5 py-2.5 rounded-xl font-semibold text-sm hover:bg-blue-700 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat di Portal Publik
        </a>
        <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-pen-nib"></i> Tulis Artikel Baru
        </button>
    </div>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-6 rounded-xl <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?> flex items-center gap-2 shadow-sm">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg"></i>
    <span class="text-sm font-medium"><?= e($pesan) ?></span>
</div>
<?php endif; ?>

<!-- Baris Filter & Cari -->
<form method="GET" class="bg-white p-4 rounded-xl border border-slate-200 mb-6 flex flex-wrap gap-3 items-center shadow-sm">
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari judul artikel, topik, atau penulis..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
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
    <a href="artikel.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
</form>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3 text-center">Gambar</th>
                    <th class="p-3">Judul Artikel & Penulis</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3">Tanggal Terbit</th>
                    <th class="p-3 text-center">Dibaca</th>
                    <th class="p-3 text-center">Status</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarArtikel)): ?>
                <tr><td colspan="7" class="p-8 text-center text-slate-400">Belum ada artikel yang cocok.</td></tr>
                <?php else: foreach ($daftarArtikel as $art): 
                    $gbrPath = !empty($art['gambar']) ? 'uploads/artikel/' . $art['gambar'] : null;
                    $hasGbr = $gbrPath && file_exists(__DIR__ . '/' . $gbrPath);
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 text-center w-20">
                        <?php if ($hasGbr): ?>
                            <a href="<?= e($gbrPath) ?>" target="_blank" title="Klik untuk melihat gambar penuh">
                                <img src="<?= e($gbrPath) ?>" alt="Gambar <?= e($art['judul']) ?>" class="w-16 h-12 object-cover rounded shadow-sm border mx-auto hover:scale-105 transition">
                            </a>
                        <?php else: ?>
                            <div class="w-16 h-12 bg-slate-100 rounded border border-dashed flex items-center justify-center text-slate-400 text-xs mx-auto" title="Tanpa gambar">
                                <i class="fa-solid fa-newspaper text-base"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <p class="font-bold text-slate-800"><?= e($art['judul']) ?></p>
                        <p class="text-xs text-slate-500">Oleh: <?= e($art['penulis']) ?></p>
                    </td>
                    <td class="p-3">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <?= e($art['kategori']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-xs text-slate-600"><?= date('d/m/Y', strtotime($art['tanggal'])) ?></td>
                    <td class="p-3 text-center text-xs font-mono text-slate-600"><?= (int)$art['views'] ?>x</td>
                    <td class="p-3 text-center">
                        <span class="text-xs px-2.5 py-0.5 rounded font-semibold <?= $art['status'] === 'Publish' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                            <?= e($art['status']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Baca -->
                            <a href="baca_artikel.php?id=<?= $art['id'] ?>" target="_blank" title="Baca Artikel di Tab Baru" class="w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>

                            <!-- Tombol Edit Artikel -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => (int)$art['id'],
                                'judul' => $art['judul'],
                                'kategori' => $art['kategori'],
                                'status' => $art['status'],
                                'tanggal' => $art['tanggal'],
                                'penulis' => $art['penulis'],
                                'konten' => $art['konten'],
                                'gambar' => $art['gambar'],
                                'gambar_url' => $hasGbr ? $gbrPath : ''
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting Artikel" aria-label="Sunting Artikel" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Hapus Artikel -->
                            <a href="artikel.php?hapus=<?= $art['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus artikel \'<?= e(addslashes($art['judul'])) ?>\'?')" title="Hapus Artikel" aria-label="Hapus Artikel" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
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

<!-- ================= MODAL TULIS ARTIKEL BARU ================= -->
<div id="modalArtikel" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-feather text-emerald-300"></i> Tulis Artikel & Berita Baru
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="artikel.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="simpan_artikel" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Artikel / Berita <span class="text-rose-500">*</span></label>
                <input type="text" name="judul" required placeholder="Contoh: Pentingnya Literasi Finansial Rumah Tangga..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Artikel <span class="text-rose-500">*</span></label>
                    <select name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Publikasi <span class="text-rose-500">*</span></label>
                    <select name="status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="Publish">Publish (Tampil di Publik)</option>
                        <option value="Draft">Draft (Simpan Sementara)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Terbit <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" name="penulis" required value="<?= e($_SESSION['user_nama'] ?? 'Pengelola Pojok Baca') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Gambar Utama Artikel (JPG/PNG/WEBP)</label>
                <input type="file" id="tambah_gambar" name="gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewGambar(this, 'preview_tambah_gbr_img', 'preview_tambah_gbr_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer bg-white">
                <span class="text-[11px] text-slate-400 mt-1 block">Opsional, ukuran file maksimal 5 MB.</span>

                <!-- Pratinjau Gambar Baru -->
                <div id="preview_tambah_gbr_wrapper" class="hidden mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3">
                    <img id="preview_tambah_gbr_img" src="" alt="Pratinjau" class="w-20 h-14 object-cover rounded-lg border shadow-sm">
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-emerald-700">Gambar Terpilih</span>
                        <p class="text-[11px] text-slate-400">Akan diunggah sebagai sampul utama artikel.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Isi Artikel / Berita <span class="text-rose-500">*</span></label>
                <textarea name="konten" required rows="7" placeholder="Tuliskan isi artikel lengkap di sini..." class="w-full border border-slate-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan & Publikasikan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT ARTIKEL ================= -->
<div id="modalEditArtikel" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-200"></i> Sunting Artikel & Berita
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="artikel.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_artikel" value="1">
            <input type="hidden" id="edit_artikel_id" name="artikel_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Artikel / Berita <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_judul" name="judul" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Artikel <span class="text-rose-500">*</span></label>
                    <select id="edit_kategori" name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= $kat ?>"><?= $kat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Publikasi <span class="text-rose-500">*</span></label>
                    <select id="edit_status" name="status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="Publish">Publish (Tampil di Publik)</option>
                        <option value="Draft">Draft (Simpan Sementara)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Terbit <span class="text-rose-500">*</span></label>
                    <input type="date" id="edit_tanggal" name="tanggal" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_penulis" name="penulis" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <!-- Bagian Gambar Utama & Ganti Gambar -->
            <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-200/70 space-y-3">
                <label class="block text-xs font-bold text-slate-800">Gambar Utama Artikel</label>

                <!-- Gambar Saat Ini -->
                <div id="edit_current_gbr_box" class="flex items-center gap-3 bg-white p-2.5 rounded-lg border border-slate-200">
                    <a id="edit_gbr_link" href="#" target="_blank" title="Lihat ukuran penuh">
                        <img id="edit_current_img" src="" alt="Gambar Saat Ini" class="w-20 h-14 object-cover rounded shadow border border-slate-200 hover:scale-105 transition">
                    </a>
                    <div class="text-xs">
                        <span class="font-semibold text-slate-700">Gambar Utama Terpasang</span>
                        <div class="mt-1 flex items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 text-xs text-rose-700 cursor-pointer">
                                <input type="checkbox" id="edit_hapus_gambar" name="hapus_gambar" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                <span>Hapus gambar (gunakan tanpa gambar)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="edit_no_gbr_notice" class="hidden text-xs text-slate-500 italic flex items-center gap-2 bg-white p-2.5 rounded-lg border border-slate-200">
                    <i class="fa-solid fa-image text-slate-400 text-base"></i>
                    <span>Artikel ini belum memiliki gambar utama.</span>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Unggah Gambar Pengganti (Opsional)</label>
                    <input type="file" id="edit_gambar" name="gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewGambar(this, 'preview_edit_gbr_img', 'preview_edit_gbr_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer bg-white">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format JPG/PNG/WEBP, maksimal 5 MB. Pilih berkas baru untuk mengganti gambar.</span>

                    <!-- Preview Gambar Pengganti -->
                    <div id="preview_edit_gbr_wrapper" class="hidden mt-2 p-2.5 bg-white border border-amber-300 rounded-xl flex items-center gap-3">
                        <img id="preview_edit_gbr_img" src="" alt="Pratinjau Gambar Baru" class="w-20 h-14 object-cover rounded-lg border shadow-sm">
                        <div class="text-xs text-slate-700">
                            <span class="font-semibold text-amber-700">Gambar Baru Terpilih</span>
                            <p class="text-[10px] text-slate-500">Gambar ini akan menggantikan gambar utama sebelumnya.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Isi Artikel / Berita <span class="text-rose-500">*</span></label>
                <textarea id="edit_konten" name="konten" required rows="7" placeholder="Tuliskan isi artikel lengkap di sini..." class="w-full border border-slate-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-amber-500"></textarea>
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
    document.getElementById('modalArtikel').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalArtikel').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_tambah_gbr_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

function bukaModalEdit(art) {
    document.getElementById('edit_artikel_id').value = art.id;
    document.getElementById('edit_judul').value = art.judul || '';
    document.getElementById('edit_kategori').value = art.kategori || 'Literasi Desa';
    document.getElementById('edit_status').value = art.status || 'Publish';
    document.getElementById('edit_tanggal').value = art.tanggal || '';
    document.getElementById('edit_penulis').value = art.penulis || '';
    document.getElementById('edit_konten').value = art.konten || '';

    // Reset input file & checkbox & preview
    const fileInput = document.getElementById('edit_gambar');
    if (fileInput) fileInput.value = '';
    const hapusGbrCb = document.getElementById('edit_hapus_gambar');
    if (hapusGbrCb) hapusGbrCb.checked = false;
    const previewWrapper = document.getElementById('preview_edit_gbr_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');

    // Tampilkan gambar saat ini
    const currentBox = document.getElementById('edit_current_gbr_box');
    const noGbrNotice = document.getElementById('edit_no_gbr_notice');
    const currentImg = document.getElementById('edit_current_img');
    const gbrLink = document.getElementById('edit_gbr_link');

    if (art.gambar_url && art.gambar_url !== '') {
        currentImg.src = art.gambar_url;
        gbrLink.href = art.gambar_url;
        currentBox.classList.remove('hidden');
        noGbrNotice.classList.add('hidden');
    } else {
        currentBox.classList.add('hidden');
        noGbrNotice.classList.remove('hidden');
    }

    document.getElementById('modalEditArtikel').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditArtikel').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_edit_gbr_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

// Pratinjau gambar saat file dipilih
function previewGambar(input, targetImgId, targetWrapperId) {
    const file = input.files && input.files[0];
    const wrapper = document.getElementById(targetWrapperId);
    const img = document.getElementById(targetImgId);

    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Silakan pilih berkas gambar yang valid (JPG, PNG, atau WEBP)!');
            input.value = '';
            if (wrapper) wrapper.classList.add('hidden');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file gambar maksimal 5 MB!');
            input.value = '';
            if (wrapper) wrapper.classList.add('hidden');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            if (img) img.src = e.target.result;
            if (wrapper) wrapper.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        if (wrapper) wrapper.classList.add('hidden');
    }
}

// Tutup modal jika klik di luar area dialog
window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modalArtikel');
    const modalEdit = document.getElementById('modalEditArtikel');
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

