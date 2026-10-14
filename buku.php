<?php
declare(strict_types=1);
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

$kondisiList = [
    'Baik',
    'Rusak Ringan',
    'Rusak Parah'
];

// Simpan / Edit Buku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_buku']) || isset($_POST['edit_buku']))) {
    $kode = trim($_POST['kode_buku'] ?? '');
    $judul = trim($_POST['judul'] ?? '');
    $penulis = trim($_POST['penulis'] ?? '');
    $penerbit = trim($_POST['penerbit'] ?? '');
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $kategori = $_POST['kategori'] ?? '';
    $lokasi = trim($_POST['lokasi_rak'] ?? '');
    $stok = (int)($_POST['stok_total'] ?? 1);
    $kondisi = $_POST['kondisi'] ?? 'Baik';
    $editId = !empty($_POST['buku_id']) ? (int)$_POST['buku_id'] : null;
    $hapusCover = !empty($_POST['hapus_cover']);

    // Validasi dasar
    if (empty($kode) || empty($judul) || empty($penulis) || empty($penerbit) || empty($lokasi)) {
        $pesan = 'Mohon lengkapi semua bidang yang wajib diisi!';
        $tipePesan = 'error';
    } elseif ($stok < 1) {
        $pesan = 'Jumlah stok buku minimal harus 1 eksemplar!';
        $tipePesan = 'error';
    } elseif ($tahun < 1900 || $tahun > (int)date('Y') + 1) {
        $pesan = 'Tahun terbit tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($kategori, $kategoriList, true)) {
        $pesan = 'Kategori buku yang dipilih tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($kondisi, $kondisiList, true)) {
        $pesan = 'Kondisi fisik buku tidak valid!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi kode buku
        if ($editId) {
            $stmtCekKode = $pdo->prepare("SELECT COUNT(*) FROM buku WHERE kode_buku = ? AND id != ?");
            $stmtCekKode->execute([$kode, $editId]);
        } else {
            $stmtCekKode = $pdo->prepare("SELECT COUNT(*) FROM buku WHERE kode_buku = ?");
            $stmtCekKode->execute([$kode]);
        }

        if ($stmtCekKode->fetchColumn() > 0) {
            $pesan = "Kode buku '{$kode}' sudah digunakan untuk koleksi lain. Gunakan kode buku unik!";
            $tipePesan = 'error';
        } else {
            // Handle Upload Cover (JPG/PNG/WEBP)
            $coverName = null;
            $uploadOk = true;

            if (isset($_FILES['cover_buku']) && $_FILES['cover_buku']['error'] === UPLOAD_ERR_OK) {
                $fileCover = $_FILES['cover_buku'];
                $ext = strtolower(pathinfo($fileCover['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed, true)) {
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
                        // Ambil data buku saat ini untuk perhitungan stok tersedia & pembersihan file
                        $stmtCur = $pdo->prepare("SELECT * FROM buku WHERE id = ?");
                        $stmtCur->execute([$editId]);
                        $curBook = $stmtCur->fetch();

                        if (!$curBook) {
                            $pesan = "Koleksi buku tidak ditemukan!";
                            $tipePesan = 'error';
                        } else {
                            $sedangDipinjam = max(0, (int)$curBook['stok_total'] - (int)$curBook['stok_tersedia']);
                            if ($stok < $sedangDipinjam) {
                                $pesan = "Stok total ({$stok}) tidak boleh lebih kecil dari jumlah buku yang sedang dipinjam ({$sedangDipinjam} buku)!";
                                $tipePesan = 'error';
                                if ($coverName && file_exists($uploadDirCover . $coverName)) {
                                    @unlink($uploadDirCover . $coverName);
                                }
                            } else {
                                $stokTersediaBaru = $stok - $sedangDipinjam;

                                if ($coverName) {
                                    // Ganti cover dengan yang baru
                                    if (!empty($curBook['cover']) && file_exists($uploadDirCover . $curBook['cover'])) {
                                        @unlink($uploadDirCover . $curBook['cover']);
                                    }
                                    $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, lokasi_rak=?, stok_total=?, stok_tersedia=?, kondisi=?, cover=? WHERE id=?");
                                    $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $stokTersediaBaru, $kondisi, $coverName, $editId]);
                                } elseif ($hapusCover) {
                                    // Hapus cover menjadi polos (NULL)
                                    if (!empty($curBook['cover']) && file_exists($uploadDirCover . $curBook['cover'])) {
                                        @unlink($uploadDirCover . $curBook['cover']);
                                    }
                                    $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, lokasi_rak=?, stok_total=?, stok_tersedia=?, kondisi=?, cover=NULL WHERE id=?");
                                    $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $stokTersediaBaru, $kondisi, $editId]);
                                } else {
                                    // Cover lama dipertahankan
                                    $stmt = $pdo->prepare("UPDATE buku SET kode_buku=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, lokasi_rak=?, stok_total=?, stok_tersedia=?, kondisi=? WHERE id=?");
                                    $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $stokTersediaBaru, $kondisi, $editId]);
                                }
                                $pesan = "Data koleksi buku '{$judul}' berhasil diperbarui.";
                                $tipePesan = 'success';
                            }
                        }
                    } else {
                        // Tambah buku baru
                        $stmt = $pdo->prepare("INSERT INTO buku (kode_buku, judul, penulis, penerbit, tahun, kategori, lokasi_rak, stok_total, stok_tersedia, kondisi, cover) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $lokasi, $stok, $stok, $kondisi, $coverName]);
                        $pesan = "Koleksi buku baru '{$judul}' berhasil ditambahkan ke katalog.";
                        $tipePesan = 'success';
                    }
                } catch (PDOException $ex) {
                    if ($coverName && file_exists($uploadDirCover . $coverName)) {
                        @unlink($uploadDirCover . $coverName);
                    }
                    $pesan = "Gagal menyimpan buku: " . $ex->getMessage();
                    $tipePesan = 'error';
                }
            }
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

        $stmt = $pdo->prepare("DELETE FROM buku WHERE id = ?");
        $stmt->execute([$idHapus]);

        if ($oldCover && file_exists($uploadDirCover . $oldCover)) {
            @unlink($uploadDirCover . $oldCover);
        }

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
        <p class="text-sm text-slate-500">Kelola katalog 7 kategori baku, stok fisik rak, kondisi, serta upload & sunting cover buku.</p>
    </div>
    <button type="button" onclick="bukaModalTambah()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-plus"></i> Tambah Koleksi Buku
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
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari kode buku, judul, atau penulis..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full md:w-56">
        <select name="kategori" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Kategori (7 Jenis) --</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k ?>" <?= $katFilter === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">Filter</button>
    <a href="buku.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
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
                    $sedangDipinjam = max(0, (int)$b['stok_total'] - (int)$b['stok_tersedia']);
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 text-center w-16">
                        <?php if ($hasCover): ?>
                            <a href="<?= e($coverFile) ?>" target="_blank" title="Klik untuk memperbesar cover">
                                <img src="<?= e($coverFile) ?>" alt="Cover <?= e($b['judul']) ?>" class="w-12 h-16 object-cover rounded shadow border border-slate-200 mx-auto hover:scale-105 transition">
                            </a>
                        <?php else: ?>
                            <div class="w-12 h-16 bg-slate-100 rounded border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 mx-auto" title="Tidak ada cover">
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
                        <?php if ($sedangDipinjam > 0): ?>
                            <div class="text-[10px] text-amber-600 font-medium">(<?= $sedangDipinjam ?> dipinjam)</div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <span class="text-xs px-2 py-0.5 rounded font-medium <?= $b['kondisi'] === 'Baik' ? 'bg-emerald-100 text-emerald-700' : ($b['kondisi'] === 'Rusak Ringan' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') ?>">
                            <?= e($b['kondisi']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Edit Buku -->
                            <button type="button" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => (int)$b['id'],
                                'kode_buku' => $b['kode_buku'],
                                'judul' => $b['judul'],
                                'penulis' => $b['penulis'],
                                'penerbit' => $b['penerbit'],
                                'tahun' => (int)$b['tahun'],
                                'kategori' => $b['kategori'],
                                'lokasi_rak' => $b['lokasi_rak'],
                                'stok_total' => (int)$b['stok_total'],
                                'stok_tersedia' => (int)$b['stok_tersedia'],
                                'kondisi' => $b['kondisi'],
                                'has_cover' => $hasCover,
                                'cover_url' => $hasCover ? $coverFile : ''
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting Koleksi Buku" aria-label="Sunting Koleksi Buku" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Hapus Buku -->
                            <a href="buku.php?hapus=<?= $b['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus buku \'<?= e(addslashes($b['judul'])) ?>\'?')" title="Hapus Buku" aria-label="Hapus Buku" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
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

<!-- ================= MODAL TAMBAH BUKU ================= -->
<div id="modalTambahBuku" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-book-medical text-emerald-300"></i> Tambah Koleksi Buku Baru
            </h3>
            <button type="button" onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="buku.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="simpan_buku" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode Buku <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_buku" required placeholder="Contoh: BK-008" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Terbit <span class="text-rose-500">*</span></label>
                    <input type="number" name="tahun" required min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku <span class="text-rose-500">*</span></label>
                <input type="text" name="judul" required placeholder="Tulis judul buku lengkap..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" name="penulis" required placeholder="Nama penulis..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penerbit <span class="text-rose-500">*</span></label>
                    <input type="text" name="penerbit" required placeholder="Nama penerbit..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori (7 Baku) <span class="text-rose-500">*</span></label>
                    <select name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Lokasi Rak <span class="text-rose-500">*</span></label>
                    <input type="text" name="lokasi_rak" required placeholder="Contoh: Rak A1, Lemari 2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Stok Total <span class="text-rose-500">*</span></label>
                    <input type="number" name="stok_total" min="1" value="1" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kondisi Fisik <span class="text-rose-500">*</span></label>
                    <select name="kondisi" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kondisiList as $kond): ?>
                            <option value="<?= $kond ?>"><?= $kond ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Upload Sampul Buku (JPG/JPEG/PNG/WEBP)</label>
                <input type="file" id="tambah_cover_buku" name="cover_buku" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewUploadImage(this, 'preview_tambah_cover_img', 'preview_tambah_cover_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer">
                <span class="text-[11px] text-slate-400 mt-1 block">Opsional, ukuran file maksimal 3 MB.</span>
                
                <div id="preview_tambah_cover_wrapper" class="hidden mt-2 p-2 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3">
                    <img id="preview_tambah_cover_img" src="" alt="Pratinjau Cover" class="w-12 h-16 object-cover rounded shadow border border-slate-200">
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-emerald-700">Cover Baru Terpilih</span>
                        <p class="text-[11px] text-slate-400">Akan diunggah saat formulir disimpan.</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Koleksi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT BUKU ================= -->
<div id="modalEditBuku" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200 animate-fadeIn my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-200"></i> Sunting Data Buku & Sampul
            </h3>
            <button type="button" onclick="tutupModalEdit()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="buku.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_buku" value="1">
            <input type="hidden" id="edit_buku_id" name="buku_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode Buku <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_kode_buku" name="kode_buku" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Terbit <span class="text-rose-500">*</span></label>
                    <input type="number" id="edit_tahun" name="tahun" required min="1900" max="<?= date('Y') + 1 ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Buku <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_judul" name="judul" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_penulis" name="penulis" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penerbit <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_penerbit" name="penerbit" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori (7 Baku) <span class="text-rose-500">*</span></label>
                    <select id="edit_kategori" name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Lokasi Rak <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_lokasi_rak" name="lokasi_rak" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Stok Total <span class="text-rose-500">*</span></label>
                    <input type="number" id="edit_stok_total" name="stok_total" min="1" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                    <p class="text-[10px] text-slate-500 mt-1">
                        Ready: <span id="edit_info_ready" class="font-bold text-emerald-700">0</span> / Total: <span id="edit_info_total" class="font-bold text-slate-700">0</span> (<span id="edit_info_dipinjam" class="text-amber-600 font-bold">0</span> dipinjam)
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kondisi Fisik <span class="text-rose-500">*</span></label>
                    <select id="edit_kondisi" name="kondisi" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kondisiList as $kond): ?>
                            <option value="<?= $kond ?>"><?= $kond ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Bagian Sampul / Cover Saat Ini & Ganti Sampul -->
            <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-200/70 space-y-3">
                <label class="block text-xs font-bold text-slate-800">Sampul Buku</label>

                <!-- Pratinjau Cover Saat Ini -->
                <div id="edit_current_cover_preview" class="hidden flex items-center gap-3 bg-white p-2.5 rounded-lg border border-slate-200">
                    <a id="edit_cover_link" href="#" target="_blank" title="Lihat ukuran asli">
                        <img id="edit_cover_img" src="" alt="Cover Saat Ini" class="w-12 h-16 object-cover rounded shadow border border-slate-200 hover:scale-105 transition">
                    </a>
                    <div class="text-xs">
                        <span class="font-semibold text-slate-700">Sampul Terpasang Saat Ini</span>
                        <div class="mt-1.5 flex items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 text-xs text-rose-700 cursor-pointer">
                                <input type="checkbox" id="edit_hapus_cover" name="hapus_cover" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                <span>Hapus cover (kembalikan ke sampul polos)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="edit_no_cover_notice" class="hidden text-xs text-slate-500 italic flex items-center gap-2 bg-white p-2.5 rounded-lg border border-slate-200">
                    <i class="fa-solid fa-image text-slate-400 text-base"></i>
                    <span>Belum ada file sampul untuk buku ini.</span>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">Unggah Sampul Baru (Opsional)</label>
                    <input type="file" id="edit_cover_buku" name="cover_buku" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewUploadImage(this, 'preview_edit_cover_img', 'preview_edit_cover_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer bg-white">
                    <span class="text-[10px] text-slate-500 mt-1 block">Format JPG/PNG/WEBP, maksimal 3 MB. Pilih file baru untuk mengganti sampul.</span>

                    <div id="preview_edit_cover_wrapper" class="hidden mt-2 p-2 bg-white border border-amber-300 rounded-xl flex items-center gap-3">
                        <img id="preview_edit_cover_img" src="" alt="Pratinjau Cover Baru" class="w-12 h-16 object-cover rounded shadow border border-slate-200">
                        <div class="text-xs text-slate-700">
                            <span class="font-semibold text-amber-700">Pratinjau Cover Baru</span>
                            <p class="text-[10px] text-slate-500">Cover ini akan menggantikan cover sebelumnya.</p>
                        </div>
                    </div>
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
    document.getElementById('modalTambahBuku').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalTambahBuku').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_tambah_cover_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

function bukaModalEdit(buku) {
    document.getElementById('edit_buku_id').value = buku.id;
    document.getElementById('edit_kode_buku').value = buku.kode_buku || '';
    document.getElementById('edit_tahun').value = buku.tahun || new Date().getFullYear();
    document.getElementById('edit_judul').value = buku.judul || '';
    document.getElementById('edit_penulis').value = buku.penulis || '';
    document.getElementById('edit_penerbit').value = buku.penerbit || '';
    document.getElementById('edit_kategori').value = buku.kategori || '';
    document.getElementById('edit_lokasi_rak').value = buku.lokasi_rak || '';
    document.getElementById('edit_stok_total').value = buku.stok_total || 1;
    document.getElementById('edit_kondisi').value = buku.kondisi || 'Baik';

    // Reset file input & checkbox & new preview
    const coverInput = document.getElementById('edit_cover_buku');
    if (coverInput) coverInput.value = '';
    const hapusCoverCb = document.getElementById('edit_hapus_cover');
    if (hapusCoverCb) hapusCoverCb.checked = false;
    const previewWrapper = document.getElementById('preview_edit_cover_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');

    // Perhitungan info stok
    const stokTotal = parseInt(buku.stok_total, 10) || 0;
    const stokTersedia = parseInt(buku.stok_tersedia, 10) || 0;
    const dipinjam = Math.max(0, stokTotal - stokTersedia);

    document.getElementById('edit_info_ready').textContent = stokTersedia;
    document.getElementById('edit_info_total').textContent = stokTotal;
    document.getElementById('edit_info_dipinjam').textContent = dipinjam;

    // Batas minimum stok total yang diizinkan saat diedit (tidak boleh di bawah yang sedang dipinjam)
    document.getElementById('edit_stok_total').min = Math.max(1, dipinjam);

    // Tampilkan pratinjau cover saat ini jika ada
    const currentCoverBox = document.getElementById('edit_current_cover_preview');
    const noCoverNotice = document.getElementById('edit_no_cover_notice');
    const imgEl = document.getElementById('edit_cover_img');
    const linkEl = document.getElementById('edit_cover_link');

    if (buku.has_cover && buku.cover_url) {
        imgEl.src = buku.cover_url;
        linkEl.href = buku.cover_url;
        currentCoverBox.classList.remove('hidden');
        noCoverNotice.classList.add('hidden');
    } else {
        currentCoverBox.classList.add('hidden');
        noCoverNotice.classList.remove('hidden');
    }

    document.getElementById('modalEditBuku').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEditBuku').classList.add('hidden');
    const previewWrapper = document.getElementById('preview_edit_cover_wrapper');
    if (previewWrapper) previewWrapper.classList.add('hidden');
}

// Pratinjau gambar saat file dipilih
function previewUploadImage(input, targetImgId, targetWrapperId) {
    const file = input.files && input.files[0];
    const wrapper = document.getElementById(targetWrapperId);
    const img = document.getElementById(targetImgId);

    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Silakan pilih file gambar yang valid (JPG, PNG, atau WEBP)!');
            input.value = '';
            if (wrapper) wrapper.classList.add('hidden');
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran file maksimal adalah 3 MB!');
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
    const modalTambah = document.getElementById('modalTambahBuku');
    const modalEdit = document.getElementById('modalEditBuku');
    if (e.target === modalTambah) tutupModalTambah();
    if (e.target === modalEdit) tutupModalEdit();
});

// Tutup modal dengan tombol Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupModalTambah();
        tutupModalEdit();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

