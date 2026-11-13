<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'E-Book Digital (PDF)';
$pesan = '';
$tipePesan = '';

$uploadDirCover = __DIR__ . '/uploads/covers/';
if (!is_dir($uploadDirCover)) {
    mkdir($uploadDirCover, 0755, true);
}

$uploadDirEbook = __DIR__ . '/uploads/ebooks/';
if (!is_dir($uploadDirEbook)) {
    mkdir($uploadDirEbook, 0755, true);
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

$statusList = ['Publish', 'Draft'];
$maxPdfBytes = 25 * 1024 * 1024; // 25 MB
$maxCoverBytes = 3 * 1024 * 1024; // 3 MB

function formatUkuranFile(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function validasiUploadPdf(array $file, int $maxBytes): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file PDF melebihi batas upload_max_filesize pada server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file PDF melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL => 'File PDF hanya terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'File PDF wajib diunggah!',
        ];
        return [false, $errMap[$file['error']] ?? 'Terjadi kesalahan saat mengunggah file PDF.'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return [false, 'Format file e-book wajib berakhiran .pdf!'];
    }

    if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
        return [false, 'Ukuran file PDF maksimal 25 MB dan tidak boleh kosong!'];
    }

    // Validasi MIME type menggunakan fileinfo jika tersedia
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf'];
            if ($mime !== false && !in_array($mime, $allowedMimes, true)) {
                return [false, 'File yang dipilih bukan dokumen PDF yang valid (MIME: ' . $mime . ').'];
            }
        }
    }

    // Cek magic bytes %PDF-
    $fh = @fopen($file['tmp_name'], 'rb');
    if ($fh !== false) {
        $header = fread($fh, 5);
        fclose($fh);
        if ($header !== '%PDF-') {
            return [false, 'Isi file bukan dokumen PDF yang valid!'];
        }
    }

    return [true, ''];
}

// Deteksi jika POST melebihi post_max_size server
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $pesan = 'Ukuran berkas yang dikirim melebihi batas maksimum server (post_max_size). Coba file PDF dengan ukuran lebih kecil.';
    $tipePesan = 'error';
}

// Simpan / Edit E-Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['simpan_ebook']) || isset($_POST['edit_ebook']))) {
    $kode = trim($_POST['kode_ebook'] ?? '');
    $judul = trim($_POST['judul'] ?? '');
    $penulis = trim($_POST['penulis'] ?? '');
    $penerbit = trim($_POST['penerbit'] ?? '');
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $kategori = $_POST['kategori'] ?? '';
    $status = $_POST['status'] ?? 'Publish';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $editId = !empty($_POST['ebook_id']) ? (int)$_POST['ebook_id'] : null;
    $hapusCover = !empty($_POST['hapus_cover']);

    if (empty($kode) || empty($judul) || empty($penulis) || empty($penerbit)) {
        $pesan = 'Mohon lengkapi Kode E-Book, Judul, Penulis, dan Penerbit!';
        $tipePesan = 'error';
    } elseif ($tahun < 1900 || $tahun > (int)date('Y') + 1) {
        $pesan = 'Tahun terbit tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($kategori, $kategoriList, true)) {
        $pesan = 'Kategori e-book yang dipilih tidak valid!';
        $tipePesan = 'error';
    } elseif (!in_array($status, $statusList, true)) {
        $pesan = 'Status publikasi tidak valid!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi kode_ebook
        if ($editId) {
            $stmtCekKode = $pdo->prepare("SELECT COUNT(*) FROM ebook WHERE kode_ebook = ? AND id != ?");
            $stmtCekKode->execute([$kode, $editId]);
        } else {
            $stmtCekKode = $pdo->prepare("SELECT COUNT(*) FROM ebook WHERE kode_ebook = ?");
            $stmtCekKode->execute([$kode]);
        }

        if ((int)$stmtCekKode->fetchColumn() > 0) {
            $pesan = "Kode e-book '{$kode}' sudah digunakan. Gunakan kode yang unik!";
            $tipePesan = 'error';
        } else {
            $uploadOk = true;
            $coverName = null;
            $pdfName = null;
            $pdfSize = 0;

            // Handle Upload File PDF (wajib saat tambah, opsional saat edit)
            $hasPdfUpload = isset($_FILES['file_pdf']) && ($_FILES['file_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$editId && !$hasPdfUpload) {
                $pesan = 'File PDF e-book wajib diunggah!';
                $tipePesan = 'error';
                $uploadOk = false;
            } elseif ($hasPdfUpload) {
                [$pdfValid, $pdfErrMsg] = validasiUploadPdf($_FILES['file_pdf'], $maxPdfBytes);
                if (!$pdfValid) {
                    $pesan = $pdfErrMsg;
                    $tipePesan = 'error';
                    $uploadOk = false;
                } else {
                    $pdfName = 'ebook_' . date('Ymd_His') . '_' . rand(100, 999) . '.pdf';
                    $pdfSize = (int)$_FILES['file_pdf']['size'];
                    if (!move_uploaded_file($_FILES['file_pdf']['tmp_name'], $uploadDirEbook . $pdfName)) {
                        $pesan = 'Gagal menyimpan file PDF ke server! Pastikan folder uploads/ebooks/ dapat ditulisi.';
                        $tipePesan = 'error';
                        $uploadOk = false;
                        $pdfName = null;
                    }
                }
            }

            // Handle Upload Cover (Opsional)
            if ($uploadOk && isset($_FILES['cover_ebook']) && ($_FILES['cover_ebook']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['cover_ebook']['error'] !== UPLOAD_ERR_OK) {
                    $pesan = 'Gagal mengunggah gambar cover e-book!';
                    $tipePesan = 'error';
                    $uploadOk = false;
                } else {
                    $fileCover = $_FILES['cover_ebook'];
                    $ext = strtolower(pathinfo($fileCover['name'], PATHINFO_EXTENSION));
                    $allowedCover = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($ext, $allowedCover, true)) {
                        $pesan = 'Format sampul e-book harus JPG, JPEG, PNG, atau WEBP!';
                        $tipePesan = 'error';
                        $uploadOk = false;
                    } elseif ($fileCover['size'] > $maxCoverBytes) {
                        $pesan = 'Ukuran cover e-book maksimal 3 MB!';
                        $tipePesan = 'error';
                        $uploadOk = false;
                    } else {
                        $coverName = 'cover_ebook_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
                        if (!move_uploaded_file($fileCover['tmp_name'], $uploadDirCover . $coverName)) {
                            $pesan = 'Gagal menyimpan file cover e-book ke server!';
                            $tipePesan = 'error';
                            $uploadOk = false;
                            $coverName = null;
                        }
                    }
                }
            }

            if (!$uploadOk) {
                if ($pdfName && file_exists($uploadDirEbook . $pdfName)) {
                    @unlink($uploadDirEbook . $pdfName);
                }
                if ($coverName && file_exists($uploadDirCover . $coverName)) {
                    @unlink($uploadDirCover . $coverName);
                }
            } else {
                try {
                    if ($editId) {
                        $stmtCur = $pdo->prepare("SELECT * FROM ebook WHERE id = ?");
                        $stmtCur->execute([$editId]);
                        $curEbook = $stmtCur->fetch();

                        if (!$curEbook) {
                            if ($pdfName && file_exists($uploadDirEbook . $pdfName)) @unlink($uploadDirEbook . $pdfName);
                            if ($coverName && file_exists($uploadDirCover . $coverName)) @unlink($uploadDirCover . $coverName);
                            $pesan = 'Data e-book tidak ditemukan!';
                            $tipePesan = 'error';
                        } else {
                            $finalCover = $curEbook['cover'];
                            if ($coverName) {
                                if (!empty($curEbook['cover']) && file_exists($uploadDirCover . $curEbook['cover'])) {
                                    @unlink($uploadDirCover . $curEbook['cover']);
                                }
                                $finalCover = $coverName;
                            } elseif ($hapusCover) {
                                if (!empty($curEbook['cover']) && file_exists($uploadDirCover . $curEbook['cover'])) {
                                    @unlink($uploadDirCover . $curEbook['cover']);
                                }
                                $finalCover = null;
                            }

                            $finalPdf = $curEbook['file_pdf'];
                            $finalSize = (int)$curEbook['ukuran_file'];
                            if ($pdfName) {
                                if (!empty($curEbook['file_pdf']) && file_exists($uploadDirEbook . $curEbook['file_pdf'])) {
                                    @unlink($uploadDirEbook . $curEbook['file_pdf']);
                                }
                                $finalPdf = $pdfName;
                                $finalSize = $pdfSize;
                            }

                            $stmt = $pdo->prepare("UPDATE ebook SET kode_ebook=?, judul=?, penulis=?, penerbit=?, tahun=?, kategori=?, deskripsi=?, cover=?, file_pdf=?, ukuran_file=?, status=? WHERE id=?");
                            $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $deskripsi, $finalCover, $finalPdf, $finalSize, $status, $editId]);

                            $pesan = "Data e-book '{$judul}' berhasil diperbarui.";
                            $tipePesan = 'success';
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO ebook (kode_ebook, judul, penulis, penerbit, tahun, kategori, deskripsi, cover, file_pdf, ukuran_file, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$kode, $judul, $penulis, $penerbit, $tahun, $kategori, $deskripsi, $coverName, $pdfName, $pdfSize, $status]);

                        $pesan = "E-Book PDF baru '{$judul}' berhasil ditambahkan dan siap dibaca publik.";
                        $tipePesan = 'success';
                    }
                } catch (PDOException $ex) {
                    if ($pdfName && file_exists($uploadDirEbook . $pdfName)) @unlink($uploadDirEbook . $pdfName);
                    if ($coverName && file_exists($uploadDirCover . $coverName)) @unlink($uploadDirCover . $coverName);
                    $pesan = "Gagal menyimpan data e-book: " . $ex->getMessage();
                    $tipePesan = 'error';
                }
            }
        }
    }
}

// Hapus E-Book
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    try {
        $stmtOld = $pdo->prepare("SELECT judul, cover, file_pdf FROM ebook WHERE id = ?");
        $stmtOld->execute([$idHapus]);
        $old = $stmtOld->fetch();

        if ($old) {
            $stmt = $pdo->prepare("DELETE FROM ebook WHERE id = ?");
            $stmt->execute([$idHapus]);

            if (!empty($old['cover']) && file_exists($uploadDirCover . $old['cover'])) {
                @unlink($uploadDirCover . $old['cover']);
            }
            if (!empty($old['file_pdf']) && file_exists($uploadDirEbook . $old['file_pdf'])) {
                @unlink($uploadDirEbook . $old['file_pdf']);
            }

            $pesan = "E-Book '{$old['judul']}' berhasil dihapus.";
            $tipePesan = 'success';
        }
    } catch (PDOException $ex) {
        $pesan = "Gagal menghapus e-book: " . $ex->getMessage();
        $tipePesan = 'error';
    }
}

// Filter & Cari
$katFilter = $_GET['kategori'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM ebook WHERE 1=1";
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
    $sql .= " AND (judul LIKE ? OR kode_ebook LIKE ? OR penulis LIKE ? OR penerbit LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarEbook = $stmt->fetchAll();

// Statistik ringkas E-Book
$statEbook = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(views),0) AS total_views, COALESCE(SUM(downloads),0) AS total_downloads FROM ebook")->fetch() ?: ['total' => 0, 'total_views' => 0, 'total_downloads' => 0];

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Koleksi E-Book Digital (PDF)</h2>
        <p class="text-sm text-slate-500">Unggah dan kelola buku elektronik berformat PDF agar dapat dicari, dibaca langsung, dan diunduh oleh masyarakat.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="portal.php#ebook" target="_blank" class="bg-blue-600 text-white px-3.5 py-2.5 rounded-xl font-semibold text-sm hover:bg-blue-700 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat di Portal Publik
        </a>
        <button type="button" onclick="bukaModalTambahEbook()" class="bg-emerald-700 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-emerald-800 flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-file-circle-plus"></i> Tambah E-Book PDF
        </button>
    </div>
</div>

<!-- Statistik Mini E-Book -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg">
            <i class="fa-solid fa-file-pdf"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-slate-500">Total E-Book PDF</p>
            <h3 class="text-xl font-extrabold text-slate-800"><?= (int)$statEbook['total'] ?> Judul</h3>
        </div>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-book-open-reader"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-slate-500">Total Dibaca Online</p>
            <h3 class="text-xl font-extrabold text-emerald-700"><?= (int)$statEbook['total_views'] ?> Kali</h3>
        </div>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3.5">
        <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg">
            <i class="fa-solid fa-cloud-arrow-down"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-slate-500">Total Diunduh Publik</p>
            <h3 class="text-xl font-extrabold text-blue-700"><?= (int)$statEbook['total_downloads'] ?> Kali</h3>
        </div>
    </div>
</div>

<?php if ($pesan): ?>
<div class="p-4 mb-5 rounded-xl <?= $tipePesan === 'success' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?> flex items-center gap-2 shadow-sm">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg"></i>
    <span class="text-sm font-medium"><?= e($pesan) ?></span>
</div>
<?php endif; ?>

<!-- Baris Filter & Cari -->
<form method="GET" class="bg-white p-4 rounded-xl border border-slate-200 mb-6 flex flex-wrap gap-3 items-center shadow-sm">
    <div class="flex-1 min-w-[200px]">
        <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari kode, judul e-book, penulis, atau penerbit..." class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600">
    </div>
    <div class="w-full md:w-52">
        <select name="kategori" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">-- Semua Kategori --</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= $k ?>" <?= $katFilter === $k ? 'selected' : '' ?>><?= $k ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-full md:w-36">
        <select name="status" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-600 bg-white">
            <option value="">Semua Status</option>
            <?php foreach ($statusList as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">Filter</button>
    <a href="ebook.php" class="bg-slate-200 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-300 transition">Reset</a>
</form>

<!-- Tabel Koleksi E-Book -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100 text-slate-700 font-semibold border-b">
                <tr>
                    <th class="p-3 text-center">Cover</th>
                    <th class="p-3">Kode</th>
                    <th class="p-3">Judul & Info E-Book</th>
                    <th class="p-3">Kategori</th>
                    <th class="p-3 text-center">File PDF</th>
                    <th class="p-3 text-center">Statistik</th>
                    <th class="p-3 text-center">Status</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarEbook)): ?>
                <tr><td colspan="8" class="p-8 text-center text-slate-400">Belum ada koleksi e-book PDF yang sesuai filter.</td></tr>
                <?php else: foreach ($daftarEbook as $eb):
                    $coverFile = !empty($eb['cover']) ? 'uploads/covers/' . $eb['cover'] : null;
                    $hasCover = $coverFile && file_exists(__DIR__ . '/' . $coverFile);
                    $pdfExists = !empty($eb['file_pdf']) && file_exists($uploadDirEbook . $eb['file_pdf']);
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3 text-center w-16">
                        <?php if ($hasCover): ?>
                            <img src="<?= e($coverFile) ?>" alt="Cover <?= e($eb['judul']) ?>" class="w-12 h-16 object-cover rounded shadow border border-slate-200 mx-auto">
                        <?php else: ?>
                            <div class="w-12 h-16 bg-rose-50 rounded border border-rose-200 flex flex-col items-center justify-center text-rose-500 mx-auto">
                                <i class="fa-solid fa-file-pdf text-lg"></i>
                                <span class="text-[9px] font-bold mt-0.5">PDF</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 font-mono text-xs font-semibold text-slate-600"><?= e($eb['kode_ebook']) ?></td>
                    <td class="p-3">
                        <p class="font-bold text-slate-800"><?= e($eb['judul']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($eb['penulis']) ?> &bull; <?= e($eb['penerbit']) ?> (<?= (int)$eb['tahun'] ?>)</p>
                        <?php if (!empty($eb['deskripsi'])): ?>
                        <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1"><?= e($eb['deskripsi']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <span class="inline-block bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs px-2.5 py-0.5 rounded-full font-medium"><?= e($eb['kategori']) ?></span>
                    </td>
                    <td class="p-3 text-center">
                        <?php if ($pdfExists): ?>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-1 rounded-lg">
                                <i class="fa-solid fa-file-pdf"></i> <?= e(formatUkuranFile((int)$eb['ukuran_file'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs text-rose-600 font-semibold">File hilang</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-center text-xs text-slate-600">
                        <div class="flex items-center justify-center gap-3">
                            <span title="Dibaca online"><i class="fa-solid fa-eye text-emerald-600 mr-1"></i><?= (int)$eb['views'] ?></span>
                            <span title="Diunduh"><i class="fa-solid fa-download text-blue-600 mr-1"></i><?= (int)$eb['downloads'] ?></span>
                        </div>
                    </td>
                    <td class="p-3 text-center">
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-bold <?= $eb['status'] === 'Publish' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' ?>">
                            <?= e($eb['status']) ?>
                        </span>
                    </td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="baca_ebook.php?id=<?= (int)$eb['id'] ?>" target="_blank" title="Baca / Pratinjau E-Book" class="w-8 h-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-book-open text-xs"></i>
                            </a>
                            <a href="baca_ebook.php?id=<?= (int)$eb['id'] ?>&action=download" title="Unduh PDF" class="w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-download text-xs"></i>
                            </a>
                            <button type="button" onclick="bukaModalEditEbook(<?= htmlspecialchars(json_encode([
                                'id' => (int)$eb['id'],
                                'kode_ebook' => $eb['kode_ebook'],
                                'judul' => $eb['judul'],
                                'penulis' => $eb['penulis'],
                                'penerbit' => $eb['penerbit'],
                                'tahun' => (int)$eb['tahun'],
                                'kategori' => $eb['kategori'],
                                'status' => $eb['status'],
                                'deskripsi' => $eb['deskripsi'] ?? '',
                                'has_cover' => $hasCover,
                                'cover_url' => $hasCover ? $coverFile : '',
                                'file_pdf' => $eb['file_pdf'],
                                'ukuran_text' => formatUkuranFile((int)$eb['ukuran_file'])
                            ]), ENT_QUOTES, 'UTF-8') ?>)" title="Sunting E-Book" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>
                            <a href="ebook.php?hapus=<?= (int)$eb['id'] ?>" onclick="return confirm('Yakin ingin menghapus e-book \'<?= e(addslashes($eb['judul'])) ?>\' beserta file PDF-nya?')" title="Hapus E-Book" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition shadow-sm">
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

<!-- ================= MODAL TAMBAH E-BOOK ================= -->
<div id="modalTambahEbook" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200 my-auto max-h-[92vh] flex flex-col">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-file-circle-plus text-emerald-300"></i> Tambah E-Book Digital (PDF)
            </h3>
            <button type="button" onclick="tutupModalTambahEbook()" class="text-emerald-200 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="ebook.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="simpan_ebook" value="1">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode E-Book <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_ebook" required placeholder="Contoh: EB-001" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Terbit <span class="text-rose-500">*</span></label>
                    <input type="number" name="tahun" required min="1900" max="<?= date('Y') + 1 ?>" value="<?= date('Y') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul E-Book <span class="text-rose-500">*</span></label>
                <input type="text" name="judul" required placeholder="Tulis judul lengkap e-book..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" name="penulis" required placeholder="Nama penulis..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penerbit <span class="text-rose-500">*</span></label>
                    <input type="text" name="penerbit" required placeholder="Nama penerbit / instansi..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori <span class="text-rose-500">*</span></label>
                    <select name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Publikasi <span class="text-rose-500">*</span></label>
                    <select name="status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="Publish" selected>Publish (Tampil Publik)</option>
                        <option value="Draft">Draft (Disembunyikan)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Sinopsis / Deskripsi Singkat</label>
                <textarea name="deskripsi" rows="3" placeholder="Ringkasan singkat isi buku agar memudahkan pembaca..." class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="bg-rose-50/60 p-3.5 rounded-xl border border-rose-200">
                <label class="block text-xs font-bold text-slate-800 mb-1">Upload File E-Book (PDF) <span class="text-rose-500">*</span></label>
                <input type="file" name="file_pdf" required accept=".pdf,application/pdf" onchange="validasiFilePdfClient(this)" class="w-full text-xs border border-slate-300 rounded-xl p-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-rose-100 file:text-rose-800 hover:file:bg-rose-200 cursor-pointer">
                <span class="text-[11px] text-slate-500 mt-1 block">Wajib format <strong>.pdf</strong>, ukuran maksimal <strong>25 MB</strong>.</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Upload Sampul / Cover E-Book (Opsional)</label>
                <input type="file" name="cover_ebook" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewCoverEbook(this, 'preview_tambah_ebook_img', 'preview_tambah_ebook_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-100 file:text-emerald-800 hover:file:bg-emerald-200 cursor-pointer">
                <span class="text-[11px] text-slate-400 mt-1 block">Format JPG/PNG/WEBP, maksimal 3 MB.</span>
                <div id="preview_tambah_ebook_wrapper" class="hidden mt-2 p-2 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3">
                    <img id="preview_tambah_ebook_img" src="" alt="Pratinjau Cover" class="w-12 h-16 object-cover rounded shadow border border-slate-200">
                    <span class="text-xs font-semibold text-emerald-700">Pratinjau Cover Baru</span>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambahEbook()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">Batal</button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan E-Book
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT E-BOOK ================= -->
<div id="modalEditEbook" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200 my-auto max-h-[92vh] flex flex-col">
        <div class="bg-amber-600 text-white px-6 py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-amber-200"></i> Sunting Data E-Book (PDF)
            </h3>
            <button type="button" onclick="tutupModalEditEbook()" class="text-amber-100 hover:text-white text-lg transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="ebook.php" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="edit_ebook" value="1">
            <input type="hidden" id="edit_ebook_id" name="ebook_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode E-Book <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_kode_ebook" name="kode_ebook" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Terbit <span class="text-rose-500">*</span></label>
                    <input type="number" id="edit_tahun_ebook" name="tahun" required min="1900" max="<?= date('Y') + 1 ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul E-Book <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_judul_ebook" name="judul" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penulis <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_penulis_ebook" name="penulis" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Penerbit <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_penerbit_ebook" name="penerbit" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori <span class="text-rose-500">*</span></label>
                    <select id="edit_kategori_ebook" name="kategori" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= $k ?>"><?= $k ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Publikasi <span class="text-rose-500">*</span></label>
                    <select id="edit_status_ebook" name="status" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="Publish">Publish (Tampil Publik)</option>
                        <option value="Draft">Draft (Disembunyikan)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Sinopsis / Deskripsi Singkat</label>
                <textarea id="edit_deskripsi_ebook" name="deskripsi" rows="3" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <div class="bg-rose-50/50 p-3.5 rounded-xl border border-rose-200 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-800">File PDF Saat Ini:</span>
                    <span id="edit_info_pdf" class="font-mono text-rose-700 font-semibold"></span>
                </div>
                <label class="block text-[11px] font-semibold text-slate-700">Ganti File PDF (Opsional)</label>
                <input type="file" id="edit_file_pdf" name="file_pdf" accept=".pdf,application/pdf" onchange="validasiFilePdfClient(this)" class="w-full text-xs border border-slate-300 rounded-xl p-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-rose-100 file:text-rose-800 hover:file:bg-rose-200 cursor-pointer">
                <span class="text-[10px] text-slate-500 block">Kosongkan jika tetap menggunakan file PDF yang sudah ada. Maksimal 25 MB.</span>
            </div>

            <div class="bg-amber-50/50 p-3.5 rounded-xl border border-amber-200/70 space-y-3">
                <label class="block text-xs font-bold text-slate-800">Sampul E-Book</label>
                <div id="edit_current_ebook_cover" class="hidden flex items-center gap-3 bg-white p-2.5 rounded-lg border border-slate-200">
                    <img id="edit_ebook_cover_img" src="" alt="Cover Saat Ini" class="w-12 h-16 object-cover rounded shadow border border-slate-200">
                    <div class="text-xs">
                        <span class="font-semibold text-slate-700">Cover Terpasang Saat Ini</span>
                        <label class="mt-1.5 flex items-center gap-1.5 text-xs text-rose-700 cursor-pointer">
                            <input type="checkbox" id="edit_hapus_cover_ebook" name="hapus_cover" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            <span>Hapus cover</span>
                        </label>
                    </div>
                </div>
                <div>
                    <input type="file" id="edit_cover_ebook" name="cover_ebook" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="previewCoverEbook(this, 'preview_edit_ebook_img', 'preview_edit_ebook_wrapper')" class="w-full text-xs border border-slate-300 rounded-xl p-2 bg-white file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200 cursor-pointer">
                    <div id="preview_edit_ebook_wrapper" class="hidden mt-2 p-2 bg-white border border-amber-300 rounded-xl flex items-center gap-3">
                        <img id="preview_edit_ebook_img" src="" alt="Pratinjau Cover Baru" class="w-12 h-16 object-cover rounded shadow border border-slate-200">
                        <span class="text-xs font-semibold text-amber-700">Pratinjau Cover Baru</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalEditEbook()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">Batal</button>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalTambahEbook() {
    document.getElementById('modalTambahEbook').classList.remove('hidden');
}

function tutupModalTambahEbook() {
    document.getElementById('modalTambahEbook').classList.add('hidden');
    const wrap = document.getElementById('preview_tambah_ebook_wrapper');
    if (wrap) wrap.classList.add('hidden');
}

function bukaModalEditEbook(eb) {
    document.getElementById('edit_ebook_id').value = eb.id;
    document.getElementById('edit_kode_ebook').value = eb.kode_ebook || '';
    document.getElementById('edit_tahun_ebook').value = eb.tahun || new Date().getFullYear();
    document.getElementById('edit_judul_ebook').value = eb.judul || '';
    document.getElementById('edit_penulis_ebook').value = eb.penulis || '';
    document.getElementById('edit_penerbit_ebook').value = eb.penerbit || '';
    document.getElementById('edit_kategori_ebook').value = eb.kategori || '';
    document.getElementById('edit_status_ebook').value = eb.status || 'Publish';
    document.getElementById('edit_deskripsi_ebook').value = eb.deskripsi || '';
    document.getElementById('edit_info_pdf').textContent = (eb.file_pdf || '-') + ' (' + (eb.ukuran_text || '0 B') + ')';

    const pdfIn = document.getElementById('edit_file_pdf');
    if (pdfIn) pdfIn.value = '';
    const covIn = document.getElementById('edit_cover_ebook');
    if (covIn) covIn.value = '';
    const hapusCb = document.getElementById('edit_hapus_cover_ebook');
    if (hapusCb) hapusCb.checked = false;
    const prevWrap = document.getElementById('preview_edit_ebook_wrapper');
    if (prevWrap) prevWrap.classList.add('hidden');

    const curCov = document.getElementById('edit_current_ebook_cover');
    const curImg = document.getElementById('edit_ebook_cover_img');
    if (eb.has_cover && eb.cover_url) {
        curImg.src = eb.cover_url;
        curCov.classList.remove('hidden');
    } else {
        curCov.classList.add('hidden');
    }

    document.getElementById('modalEditEbook').classList.remove('hidden');
}

function tutupModalEditEbook() {
    document.getElementById('modalEditEbook').classList.add('hidden');
    const prevWrap = document.getElementById('preview_edit_ebook_wrapper');
    if (prevWrap) prevWrap.classList.add('hidden');
}

function validasiFilePdfClient(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.pdf')) {
        alert('Format file wajib .pdf!');
        input.value = '';
        return;
    }
    if (file.size > 25 * 1024 * 1024) {
        alert('Ukuran file PDF maksimal adalah 25 MB!');
        input.value = '';
    }
}

function previewCoverEbook(input, targetImgId, targetWrapperId) {
    const file = input.files && input.files[0];
    const wrapper = document.getElementById(targetWrapperId);
    const img = document.getElementById(targetImgId);
    if (file) {
        if (!file.type.startsWith('image/')) {
            alert('Pilih file gambar yang valid (JPG, PNG, WEBP)!');
            input.value = '';
            if (wrapper) wrapper.classList.add('hidden');
            return;
        }
        if (file.size > 3 * 1024 * 1024) {
            alert('Ukuran cover maksimal 3 MB!');
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

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('modalTambahEbook')) tutupModalTambahEbook();
    if (e.target === document.getElementById('modalEditEbook')) tutupModalEditEbook();
});

window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        tutupModalTambahEbook();
        tutupModalEditEbook();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

