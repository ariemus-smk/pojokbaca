<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Galeri Foto Kegiatan & PKK';
$pesan = '';
$tipePesan = '';

$uploadDir = __DIR__ . '/uploads/galeri/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Proses Upload Foto Galeri Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_galeri'])) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Kegiatan Pojok Baca';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($judul)) {
        $pesan = 'Judul kegiatan wajib diisi!';
        $tipePesan = 'error';
    } elseif (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $pesan = 'Silakan pilih file foto yang valid untuk diunggah!';
        $tipePesan = 'error';
    } else {
        $file = $_FILES['foto'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $pesan = 'Format foto harus berupa JPG, JPEG, PNG, atau WEBP!';
            $tipePesan = 'error';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $pesan = 'Ukuran file foto maksimal 5 MB!';
            $tipePesan = 'error';
        } else {
            $fileName = 'galeri_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO galeri (judul, kategori, foto, tanggal, keterangan) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$judul, $kategori, $fileName, $tanggal, $keterangan]);
                    $pesan = 'Foto kegiatan berhasil diunggah ke galeri!';
                    $tipePesan = 'success';
                } catch (PDOException $ex) {
                    $pesan = 'Gagal menyimpan ke database: ' . $ex->getMessage();
                    $tipePesan = 'error';
                }
            } else {
                $pesan = 'Gagal memindahkan file ke folder penyimpanan server!';
                $tipePesan = 'error';
            }
        }
    }
}

// Proses Hapus Foto Galeri
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtCari = $pdo->prepare("SELECT foto FROM galeri WHERE id = ?");
    $stmtCari->execute([$idHapus]);
    $fotoLama = $stmtCari->fetchColumn();

    if ($fotoLama) {
        $fileHapus = $uploadDir . $fotoLama;
        if (file_exists($fileHapus)) {
            @unlink($fileHapus);
        }
        $stmtDel = $pdo->prepare("DELETE FROM galeri WHERE id = ?");
        $stmtDel->execute([$idHapus]);
        $pesan = 'Foto kegiatan berhasil dihapus.';
        $tipePesan = 'success';
    }
}

// Filter Kategori
$filterKategori = $_GET['kategori'] ?? '';
$sql = "SELECT * FROM galeri WHERE 1=1";
$params = [];
if (!empty($filterKategori)) {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}
$sql .= " ORDER BY tanggal DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarGaleri = $stmt->fetchAll();

// Hitung rekap
$totalPojokBaca = $pdo->query("SELECT COUNT(*) FROM galeri WHERE kategori = 'Kegiatan Pojok Baca'")->fetchColumn();
$totalPKK = $pdo->query("SELECT COUNT(*) FROM galeri WHERE kategori = 'Kegiatan PKK'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Galeri Foto Kegiatan & PKK</h2>
        <p class="text-sm text-slate-500">Dokumentasi visual kegiatan Pojok Baca Desa Sejiram dan Kegiatan Pemberdayaan Kesejahteraan Keluarga (PKK).</p>
    </div>
    <button onclick="document.getElementById('modalUploadGaleri').classList.remove('hidden')" class="bg-emerald-700 text-white px-4 py-2.5 rounded-lg font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Foto Kegiatan
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-6 rounded-lg <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-1.5"></i> <?= e($pesan) ?>
</div>
<?php endif; ?>

<!-- Tab Filter & Counter -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6 bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex gap-2">
        <a href="galeri.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= empty($filterKategori) ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            Semua Foto (<?= (int)$totalPojokBaca + (int)$totalPKK ?>)
        </a>
        <a href="galeri.php?kategori=Kegiatan Pojok Baca" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $filterKategori === 'Kegiatan Pojok Baca' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <i class="fa-solid fa-book-open mr-1"></i> Kegiatan Pojok Baca (<?= (int)$totalPojokBaca ?>)
        </a>
        <a href="galeri.php?kategori=Kegiatan PKK" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $filterKategori === 'Kegiatan PKK' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <i class="fa-solid fa-hands-holding-child mr-1"></i> Kegiatan PKK (<?= (int)$totalPKK ?>)
        </a>
    </div>
    <span class="text-xs text-slate-400">Format yang didukung: JPG, JPEG, PNG, WEBP (Maks 5MB)</span>
</div>

<!-- Grid Foto Galeri -->
<?php if (empty($daftarGaleri)): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
    <i class="fa-regular fa-images text-5xl mb-3 text-slate-300"></i>
    <p class="font-medium">Belum ada dokumentasi foto yang diunggah untuk kategori ini.</p>
    <p class="text-xs mt-1">Klik tombol "Upload Foto Kegiatan" di atas untuk menambahkan dokumentasi baru.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($daftarGaleri as $g): 
        $fotoPath = 'uploads/galeri/' . $g['foto'];
        $hasFile = file_exists(__DIR__ . '/' . $fotoPath);
    ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
        <div>
            <div class="relative bg-slate-100 aspect-video flex items-center justify-center overflow-hidden group">
                <?php if ($hasFile): ?>
                    <img src="<?= e($fotoPath) ?>" alt="<?= e($g['judul']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                <?php else: ?>
                    <div class="text-center p-4 text-slate-400">
                        <i class="fa-regular fa-image text-4xl mb-1"></i>
                        <p class="text-xs">Foto Sampel</p>
                    </div>
                <?php endif; ?>
                <span class="absolute top-2 left-2 px-2.5 py-1 rounded-md text-[11px] font-bold shadow-sm <?= $g['kategori'] === 'Kegiatan PKK' ? 'bg-rose-600 text-white' : 'bg-emerald-700 text-white' ?>">
                    <?= e($g['kategori']) ?>
                </span>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-1.5 text-xs text-slate-400 mb-1.5 font-medium">
                    <i class="fa-regular fa-calendar"></i> <?= date('d F Y', strtotime($g['tanggal'])) ?>
                </div>
                <h3 class="font-bold text-slate-800 text-sm leading-snug line-clamp-2"><?= e($g['judul']) ?></h3>
                <?php if (!empty($g['keterangan'])): ?>
                <p class="text-xs text-slate-500 mt-2 line-clamp-3"><?= e($g['keterangan']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-xs">
            <?php if ($hasFile): ?>
            <a href="<?= e($fotoPath) ?>" target="_blank" class="text-emerald-700 hover:text-emerald-900 font-semibold flex items-center gap-1">
                <i class="fa-solid fa-up-right-from-square"></i> Lihat Penuh
            </a>
            <?php else: ?>
            <span class="text-slate-400">Pratinjau</span>
            <?php endif; ?>
            <a href="galeri.php?hapus=<?= $g['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus foto dokumentasi ini?')" class="text-rose-600 hover:text-rose-800 font-semibold flex items-center gap-1">
                <i class="fa-solid fa-trash-can"></i> Hapus
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Upload Galeri -->
<div id="modalUploadGaleri" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
            <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-camera text-emerald-700"></i> Upload Foto Kegiatan Baru
            </h3>
            <button onclick="document.getElementById('modalUploadGaleri').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="galeri.php" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Dokumentasi</label>
                <select name="kategori" required class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="Kegiatan Pojok Baca">Kegiatan Pojok Baca Desa</option>
                    <option value="Kegiatan PKK">Kegiatan PKK Desa Sejiram</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Kegiatan / Nama Agenda</label>
                <input type="text" name="judul" required placeholder="Contoh: Lomba Mewarnai & Membaca Dongeng Balita" class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kegiatan</label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pilih File Foto (JPG/PNG/WEBP)</label>
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required class="w-full text-xs border rounded-lg p-2 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Catatan Singkat Kegiatan</label>
                <textarea name="keterangan" rows="3" placeholder="Tuliskan deskripsi agenda, lokasi kegiatan, atau pihak yang hadir..." class="w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-4 border-t">
                <button type="button" onclick="document.getElementById('modalUploadGaleri').classList.add('hidden')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm font-medium">Batal</button>
                <button type="submit" name="simpan_galeri" class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm font-semibold hover:bg-emerald-800 transition">Unggah Sekarang</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
