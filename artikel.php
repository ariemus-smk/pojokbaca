<?php
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

// Simpan / Edit Artikel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_artikel'])) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Literasi Desa';
    $konten = trim($_POST['konten'] ?? '');
    $status = $_POST['status'] ?? 'Publish';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $penulis = trim($_POST['penulis'] ?? ($_SESSION['user_nama'] ?? 'Pengelola Pojok Baca'));
    $editId = !empty($_POST['artikel_id']) ? (int)$_POST['artikel_id'] : null;

    // Buat slug otomatis
    $slugClean = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $judul), '-'));
    $slug = $slugClean . '-' . rand(100, 999);

    // Handle Upload Gambar Artikel
    $gambarName = null;
    $uploadOk = true;

    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gambar'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
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
                $pesan = 'Gagal mengunggah berkas gambar!';
                $tipePesan = 'error';
                $uploadOk = false;
            }
        }
    }

    if ($uploadOk) {
        try {
            if ($editId) {
                if ($gambarName) {
                    $stmtOld = $pdo->prepare("SELECT gambar FROM artikel WHERE id = ?");
                    $stmtOld->execute([$editId]);
                    $oldGbr = $stmtOld->fetchColumn();
                    if ($oldGbr && file_exists($uploadDirArtikel . $oldGbr)) {
                        @unlink($uploadDirArtikel . $oldGbr);
                    }
                    $stmt = $pdo->prepare("UPDATE artikel SET judul=?, kategori=?, konten=?, status=?, tanggal=?, penulis=?, gambar=? WHERE id=?");
                    $stmt->execute([$judul, $kategori, $konten, $status, $tanggal, $penulis, $gambarName, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE artikel SET judul=?, kategori=?, konten=?, status=?, tanggal=?, penulis=? WHERE id=?");
                    $stmt->execute([$judul, $kategori, $konten, $status, $tanggal, $penulis, $editId]);
                }
                $pesan = "Artikel berhasil diperbarui.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO artikel (judul, slug, kategori, gambar, konten, penulis, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$judul, $slug, $kategori, $gambarName, $konten, $penulis, $tanggal, $status]);
                $pesan = "Artikel baru berhasil dipublikasikan.";
            }
            $tipePesan = 'success';
        } catch (PDOException $ex) {
            $pesan = "Gagal memproses artikel: " . $ex->getMessage();
            $tipePesan = 'error';
        }
    }
}

// Hapus Artikel
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtOld = $pdo->prepare("SELECT gambar FROM artikel WHERE id = ?");
    $stmtOld->execute([$idHapus]);
    $oldGbr = $stmtOld->fetchColumn();
    if ($oldGbr && file_exists($uploadDirArtikel . $oldGbr)) {
        @unlink($uploadDirArtikel . $oldGbr);
    }
    $stmt = $pdo->prepare("DELETE FROM artikel WHERE id = ?");
    $stmt->execute([$idHapus]);
    $pesan = "Artikel berhasil dihapus.";
    $tipePesan = 'success';
}

$daftarArtikel = $pdo->query("SELECT * FROM artikel ORDER BY tanggal DESC, id DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Manajemen Artikel & Berita Literasi</h2>
        <p class="text-sm text-slate-500">Publikasikan tulisan edukatif, berita kegiatan literasi pojok baca, dan kabar kegiatan PKK Desa Sejiram.</p>
    </div>
    <div class="flex gap-2">
        <a href="portal.php#artikel" target="_blank" class="bg-blue-600 text-white px-3.5 py-2.5 rounded-lg font-semibold text-sm hover:bg-blue-700 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat di Portal Publik
        </a>
        <button onclick="document.getElementById('modalArtikel').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-pen-nib"></i> Tulis Artikel Baru
        </button>
    </div>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-6 rounded-lg <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-1.5"></i> <?= e($pesan) ?>
</div>
<?php endif; ?>

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
                <tr><td colspan="7" class="p-8 text-center text-slate-400">Belum ada artikel yang ditulis.</td></tr>
                <?php else: foreach ($daftarArtikel as $art): 
                    $gbrPath = !empty($art['gambar']) ? 'uploads/artikel/' . $art['gambar'] : null;
                    $hasGbr = $gbrPath && file_exists(__DIR__ . '/' . $gbrPath);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 text-center w-20">
                        <?php if ($hasGbr): ?>
                            <img src="<?= e($gbrPath) ?>" alt="Gambar" class="w-16 h-12 object-cover rounded shadow-sm border mx-auto">
                        <?php else: ?>
                            <div class="w-16 h-12 bg-slate-100 rounded border border-dashed flex items-center justify-center text-slate-400 text-xs mx-auto">
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
                    <td class="p-3 text-center space-x-2">
                        <a href="baca_artikel.php?id=<?= $art['id'] ?>" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Baca</a>
                        <a href="artikel.php?hapus=<?= $art['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus artikel ini?')" class="text-rose-600 hover:text-rose-800 text-xs font-semibold">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tulis Artikel -->
<div id="modalArtikel" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-feather text-emerald-700"></i> Tulis Artikel & Berita Baru
            </h3>
            <button onclick="document.getElementById('modalArtikel').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="artikel.php" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Artikel / Berita</label>
                <input type="text" name="judul" required placeholder="Contoh: Pentingnya Literasi Finansial Rumah Tangga..." class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Artikel</label>
                    <select name="kategori" required class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="Literasi Desa">Literasi Desa</option>
                        <option value="Kegiatan PKK">Kegiatan PKK</option>
                        <option value="Edukasi & Pertanian">Edukasi & Pertanian</option>
                        <option value="Kearifan Lokal">Kearifan Lokal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Publikasi</label>
                    <select name="status" class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="Publish">Publish (Tampil di Publik)</option>
                        <option value="Draft">Draft (Simpan Sementara)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Terbit</label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nama Penulis</label>
                    <input type="text" name="penulis" required value="<?= e($_SESSION['user_nama'] ?? 'Pengelola Pojok Baca') ?>" class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Gambar Utama Artikel (JPG/PNG/WEBP)</label>
                <input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full text-xs border rounded-lg p-2 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200">
                <span class="text-[11px] text-slate-400">Opsional, ukuran file maks 5 MB.</span>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Isi Artikel / Berita</label>
                <textarea name="konten" required rows="8" placeholder="Tuliskan isi artikel lengkap di sini..." class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalArtikel').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm font-medium">Batal</button>
                <button type="submit" name="simpan_artikel" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800 transition">Simpan & Publikasikan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
