<?php
declare(strict_types=1);
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

$kategoriValid = ['Kegiatan Pojok Baca', 'Kegiatan PKK'];

// 1. Proses Upload Foto Galeri Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_galeri']) || isset($_POST['simpan_galeri']))) {
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Kegiatan Pojok Baca';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($judul)) {
        $pesan = 'Judul kegiatan wajib diisi!';
        $tipePesan = 'error';
    } elseif (!in_array($kategori, $kategoriValid, true)) {
        $pesan = 'Kategori dokumentasi tidak valid!';
        $tipePesan = 'error';
    } elseif (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $pesan = 'Silakan pilih file foto yang valid untuk diunggah!';
        $tipePesan = 'error';
    } else {
        $file = $_FILES['foto'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed, true)) {
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
                    if (file_exists($destPath)) {
                        @unlink($destPath);
                    }
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

// 2. Proses Edit Data Foto Galeri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_galeri'])) {
    $id = (int)($_POST['galeri_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Kegiatan Pojok Baca';
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id <= 0) {
        $pesan = 'ID foto galeri tidak valid!';
        $tipePesan = 'error';
    } elseif (empty($judul)) {
        $pesan = 'Judul kegiatan wajib diisi!';
        $tipePesan = 'error';
    } elseif (!in_array($kategori, $kategoriValid, true)) {
        $pesan = 'Kategori dokumentasi tidak valid!';
        $tipePesan = 'error';
    } else {
        $stmtCari = $pdo->prepare("SELECT * FROM galeri WHERE id = ?");
        $stmtCari->execute([$id]);
        $oldGaleri = $stmtCari->fetch();

        if (!$oldGaleri) {
            $pesan = 'Data foto kegiatan tidak ditemukan!';
            $tipePesan = 'error';
        } else {
            $fileNameBaru = null;
            $uploadOk = true;

            // Jika user memilih file foto baru
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['foto'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed, true)) {
                    $pesan = 'Format foto harus berupa JPG, JPEG, PNG, atau WEBP!';
                    $tipePesan = 'error';
                    $uploadOk = false;
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $pesan = 'Ukuran file foto baru maksimal 5 MB!';
                    $tipePesan = 'error';
                    $uploadOk = false;
                } else {
                    $fileNameBaru = 'galeri_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
                    $destPath = $uploadDir . $fileNameBaru;
                    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                        $pesan = 'Gagal menyimpan file foto baru ke server!';
                        $tipePesan = 'error';
                        $uploadOk = false;
                    }
                }
            }

            if ($uploadOk) {
                try {
                    if ($fileNameBaru) {
                        // Hapus file lama jika ada
                        if (!empty($oldGaleri['foto']) && file_exists($uploadDir . $oldGaleri['foto'])) {
                            @unlink($uploadDir . $oldGaleri['foto']);
                        }
                        $stmt = $pdo->prepare("UPDATE galeri SET judul = ?, kategori = ?, foto = ?, tanggal = ?, keterangan = ? WHERE id = ?");
                        $stmt->execute([$judul, $kategori, $fileNameBaru, $tanggal, $keterangan, $id]);
                    } else {
                        // Foto lama dipertahankan
                        $stmt = $pdo->prepare("UPDATE galeri SET judul = ?, kategori = ?, tanggal = ?, keterangan = ? WHERE id = ?");
                        $stmt->execute([$judul, $kategori, $tanggal, $keterangan, $id]);
                    }
                    $pesan = "Dokumentasi kegiatan '{$judul}' berhasil diperbarui.";
                    $tipePesan = 'success';
                } catch (PDOException $ex) {
                    if ($fileNameBaru && file_exists($uploadDir . $fileNameBaru)) {
                        @unlink($uploadDir . $fileNameBaru);
                    }
                    $pesan = 'Gagal memperbarui ke database: ' . $ex->getMessage();
                    $tipePesan = 'error';
                }
            }
        }
    }
}

// 3. Proses Hapus Foto Galeri
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    $stmtCari = $pdo->prepare("SELECT foto, judul FROM galeri WHERE id = ?");
    $stmtCari->execute([$idHapus]);
    $galeriHapus = $stmtCari->fetch();

    if ($galeriHapus) {
        $fotoLama = $galeriHapus['foto'];
        if ($fotoLama && file_exists($uploadDir . $fotoLama)) {
            @unlink($uploadDir . $fotoLama);
        }
        $stmtDel = $pdo->prepare("DELETE FROM galeri WHERE id = ?");
        $stmtDel->execute([$idHapus]);
        $pesan = "Foto dokumentasi '{$galeriHapus['judul']}' berhasil dihapus.";
        $tipePesan = 'success';
    }
}

// 4. Filter Kategori & Pencarian
$filterKategori = $_GET['kategori'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM galeri WHERE 1=1";
$params = [];
if (!empty($filterKategori)) {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}
if (!empty($cari)) {
    $sql .= " AND (judul LIKE ? OR keterangan LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY tanggal DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarGaleri = $stmt->fetchAll();

// Hitung rekap kategori
$totalPojokBaca = $pdo->query("SELECT COUNT(*) FROM galeri WHERE kategori = 'Kegiatan Pojok Baca'")->fetchColumn();
$totalPKK = $pdo->query("SELECT COUNT(*) FROM galeri WHERE kategori = 'Kegiatan PKK'")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Galeri Foto Kegiatan & PKK</h2>
        <p class="text-sm text-slate-500">Dokumentasi visual kegiatan Pojok Baca Desa Sejiram dan Kegiatan Pemberdayaan Kesejahteraan Keluarga (PKK).</p>
    </div>
    <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Foto Kegiatan
    </button>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-6 rounded-xl <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?> flex items-center gap-2 shadow-sm">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg"></i>
    <span class="text-sm font-medium"><?= e($pesan) ?></span>
</div>
<?php endif; ?>

<!-- Tab Filter & Pencarian -->
<div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 mb-6 bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex flex-wrap gap-2">
        <a href="galeri.php" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= empty($filterKategori) ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            Semua Foto (<?= (int)$totalPojokBaca + (int)$totalPKK ?>)
        </a>
        <a href="galeri.php?kategori=<?= urlencode('Kegiatan Pojok Baca') ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $filterKategori === 'Kegiatan Pojok Baca' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <i class="fa-solid fa-book-open mr-1"></i> Kegiatan Pojok Baca (<?= (int)$totalPojokBaca ?>)
        </a>
        <a href="galeri.php?kategori=<?= urlencode('Kegiatan PKK') ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition <?= $filterKategori === 'Kegiatan PKK' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <i class="fa-solid fa-hands-holding-child mr-1"></i> Kegiatan PKK (<?= (int)$totalPKK ?>)
        </a>
    </div>

    <form method="GET" class="flex items-center gap-2">
        <?php if (!empty($filterKategori)): ?>
            <input type="hidden" name="kategori" value="<?= e($filterKategori) ?>">
        <?php endif; ?>
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari judul atau keterangan..." class="text-xs border border-slate-300 rounded-lg px-3 py-1.5 outline-none focus:border-emerald-600 w-full md:w-56">
        <button type="submit" class="bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-blue-700 font-medium transition">Cari</button>
        <?php if (!empty($cari)): ?>
            <a href="galeri.php<?= !empty($filterKategori) ? '?kategori=' . urlencode($filterKategori) : '' ?>" class="bg-slate-200 text-slate-700 text-xs px-2.5 py-1.5 rounded-lg hover:bg-slate-300 transition">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Grid Foto Galeri -->
<?php if (empty($daftarGaleri)): ?>
<div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
    <i class="fa-regular fa-images text-5xl mb-3 text-slate-300"></i>
    <p class="font-medium">Belum ada dokumentasi foto yang cocok.</p>
    <p class="text-xs mt-1">Klik tombol "Upload Foto Kegiatan" di atas untuk menambahkan dokumentasi baru.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($daftarGaleri as $g): 
        $fotoPath = 'uploads/galeri/' . $g['foto'];
        $hasFile = file_exists(__DIR__ . '/' . $fotoPath);
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-md transition">
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
            <div class="flex items-center gap-3">
                <?php if ($hasFile): ?>
                <a href="<?= e($fotoPath) ?>" target="_blank" class="text-emerald-700 hover:text-emerald-900 font-semibold flex items-center gap-1 transition" title="Lihat ukuran penuh">
                    <i class="fa-solid fa-up-right-from-square"></i> Lihat
                </a>
                <?php else: ?>
                <span class="text-slate-400 italic">Pratinjau</span>
                <?php endif; ?>

                <!-- Tombol Edit Dokumentasi -->
                <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                    'id' => (int)$g['id'],
                    'judul' => $g['judul'],
                    'kategori' => $g['kategori'],
                    'tanggal' => $g['tanggal'],
                    'keterangan' => $g['keterangan'] ?: '',
                    'foto' => $g['foto'],
                    'foto_url' => $hasFile ? $fotoPath : ''
                ]), ENT_QUOTES, 'UTF-8') ?>)" class="text-amber-600 hover:text-amber-800 font-semibold flex items-center gap-1 transition" title="Sunting Dokumentasi">
                    <i class="fa-solid fa-pen-to-square"></i> Sunting
                </button>
            </div>

            <!-- Tombol Hapus Dokumentasi -->
            <a href="galeri.php?hapus=<?= $g['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus foto dokumentasi \'<?= e(addslashes($g['judul'])) ?>\'?')" class="text-rose-600 hover:text-rose-800 font-semibold flex items-center gap-1 transition" title="Hapus Dokumentasi">
                <i class="fa-solid fa-trash-can"></i> Hapus
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ================= MODAL UPLOAD FOTO BARU ================= -->
<div id="modalUploadGaleri" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-camera text-emerald-300"></i> Upload Foto Kegiatan Baru
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="galeri.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="tambah_galeri" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Dokumentasi <span class="text-rose-500">*</span></label>
                <select name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                    <option value="Kegiatan Pojok Baca">Kegiatan Pojok Baca Desa</option>
                    <option value="Kegiatan PKK">Kegiatan PKK Desa Sejiram</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Kegiatan / Nama Agenda <span class="text-rose-500">*</span></label>
                <input type="text" name="judul" required placeholder="Contoh: Lomba Mewarnai & Membaca Dongeng Balita" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kegiatan <span class="text-rose-500">*</span></label>
                    <input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Pilih File Foto <span class="text-rose-500">*</span></label>
                    <input type="file" id="tambah_foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required onchange="previewFoto(this, 'preview_tambah_foto_img', 'preview_tambah_foto_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer bg-white">
                </div>
            </div>

            <!-- Pratinjau Foto Baru -->
            <div id="preview_tambah_foto_wrapper" class="hidden p-2.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3">
                <img id="preview_tambah_foto_img" src="" alt="Pratinjau" class="w-20 h-14 object-cover rounded-lg border shadow-sm">
                <div class="text-xs text-slate-600">
                    <span class="font-semibold text-emerald-700">Foto Terpilih</span>
                    <p class="text-[11px] text-slate-400">Foto siap diunggah saat formulir disimpan.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Catatan Singkat Kegiatan</label>
                <textarea name="keterangan" rows="3" placeholder="Tuliskan deskripsi agenda, lokasi kegiatan, atau pihak yang hadir..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Unggah Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT FOTO GALERI ================= -->
<div id="modalEditGaleri" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-200"></i> Sunting Dokumentasi Kegiatan
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="galeri.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_galeri" value="1">
            <input type="hidden" id="edit_galeri_id" name="galeri_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Dokumentasi <span class="text-rose-500">*</span></label>
                <select id="edit_kategori" name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    <option value="Kegiatan Pojok Baca">Kegiatan Pojok Baca Desa</option>
                    <option value="Kegiatan PKK">Kegiatan PKK Desa Sejiram</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Kegiatan / Nama Agenda <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_judul" name="judul" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Kegiatan <span class="text-rose-500">*</span></label>
                <input type="date" id="edit_tanggal" name="tanggal" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
            </div>

            <!-- Bagian Foto Saat Ini & Ganti Foto -->
            <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-200/70 space-y-3">
                <label class="block text-xs font-bold text-slate-800">Berkas Foto Dokumentasi</label>

                <!-- Foto Saat Ini -->
                <div id="edit_current_foto_box" class="flex items-center gap-3 bg-white p-2.5 rounded-lg border border-slate-200">
                    <a id="edit_foto_link" href="#" target="_blank" title="Lihat ukuran penuh">
                        <img id="edit_current_img" src="" alt="Foto Saat Ini" class="w-20 h-14 object-cover rounded shadow border border-slate-200 hover:scale-105 transition">
                    </a>
                    <div class="text-xs">
                        <span class="font-semibold text-slate-700">Foto Terpasang Saat Ini</span>
                        <p class="text-[11px] text-slate-400 mt-0.5">Biarkan kolom di bawah kosong jika tidak ingin mengubah foto.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Ganti dengan Foto Baru (Opsional)</label>
                    <input type="file" id="edit_foto" name="foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewFoto(this, 'preview_edit_foto_img', 'preview_edit_foto_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer bg-white">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format JPG, JPEG, PNG, WEBP (Maksimal 5 MB).</span>

                    <!-- Preview Foto Pengganti -->
                    <div id="preview_edit_foto_wrapper" class="hidden mt-2 p-2.5 bg-white border border-amber-300 rounded-xl flex items-center gap-3">
                        <img id="preview_edit_foto_img" src="" alt="Pratinjau Foto Baru" class="w-20 h-14 object-cover rounded-lg border shadow-sm">
                        <div class="text-xs text-slate-700">
                            <span class="font-semibold text-amber-700">Foto Baru Terpilih</span>
                            <p class="text-[10px] text-slate-500">Akan menggantikan foto sebelumnya saat disimpan.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Catatan Singkat Kegiatan</label>
                <textarea id="edit_keterangan" name="keterangan" rows="3" placeholder="Tuliskan deskripsi agenda, lokasi kegiatan, atau pihak yang hadir..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500"></textarea>
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
    document.getElementById('modalUploadGaleri').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalUploadGaleri').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_tambah_foto_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

function bukaModalEdit(g) {
    document.getElementById('edit_galeri_id').value = g.id;
    document.getElementById('edit_kategori').value = g.kategori || 'Kegiatan Pojok Baca';
    document.getElementById('edit_judul').value = g.judul || '';
    document.getElementById('edit_tanggal').value = g.tanggal || '';
    document.getElementById('edit_keterangan').value = g.keterangan || '';

    // Reset input foto baru & preview
    const inputFoto = document.getElementById('edit_foto');
    if (inputFoto) inputFoto.value = '';
    const previewWrapper = document.getElementById('preview_edit_foto_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');

    // Tampilkan foto saat ini
    const currentImg = document.getElementById('edit_current_img');
    const fotoLink = document.getElementById('edit_foto_link');
    const currentBox = document.getElementById('edit_current_foto_box');

    if (g.foto_url && g.foto_url !== '') {
        currentImg.src = g.foto_url;
        fotoLink.href = g.foto_url;
        currentBox.classList.remove('hidden');
    } else {
        currentBox.classList.add('hidden');
    }

    document.getElementById('modalEditGaleri').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditGaleri').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_edit_foto_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

// Pratinjau foto sebelum diunggah
function previewFoto(input, imgId, wrapperId) {
    const file = input.files && input.files[0];
    const wrapper = document.getElementById(wrapperId);
    const img = document.getElementById(imgId);

    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Silakan pilih file gambar yang valid (JPG, PNG, atau WEBP)!');
            input.value = '';
            if (wrapper) wrapper.classList.add('hidden');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file maksimal adalah 5 MB!');
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

// Tutup modal saat klik luar
window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modalUploadGaleri');
    const modalEdit = document.getElementById('modalEditGaleri');
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

