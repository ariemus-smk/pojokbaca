<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekRoleAdmin();

$pageTitle = 'Manajemen Pengguna';
$pesan = '';
$tipePesan = '';

// 1. Tambah Pengguna Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengguna'])) {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'petugas';
    $status = $_POST['status'] ?? 'aktif';

    if (empty($nama) || empty($username) || empty($password)) {
        $pesan = 'Nama lengkap, username, dan kata sandi wajib diisi!';
        $tipePesan = 'error';
    } elseif (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
        $pesan = 'Username harus 3-50 karakter alfanumerik (huruf kecil, angka, garis bawah, atau strip)!';
        $tipePesan = 'error';
    } elseif (strlen($password) < 6) {
        $pesan = 'Kata sandi minimal harus 6 karakter!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi username
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ?");
        $stmtCek->execute([$username]);
        if ($stmtCek->fetchColumn() > 0) {
            $pesan = "Username '{$username}' sudah digunakan, silakan pilih username lain!";
            $tipePesan = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmtIns = $pdo->prepare("INSERT INTO pengguna (nama_lengkap, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmtIns->execute([$nama, $username, $hash, $role, $status]);
                $pesan = "Pengguna baru '{$nama}' (@{$username}) berhasil ditambahkan!";
                $tipePesan = 'success';
            } catch (PDOException $e) {
                $pesan = "Gagal menambahkan pengguna: " . $e->getMessage();
                $tipePesan = 'error';
            }
        }
    }
}

// 2. Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pengguna'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $passwordBaru = $_POST['password_baru'] ?? '';
    $role = $_POST['role'] ?? 'petugas';
    $status = $_POST['status'] ?? 'aktif';

    // Cek keberadaan pengguna
    $stmtUser = $pdo->prepare("SELECT * FROM pengguna WHERE id = ?");
    $stmtUser->execute([$userId]);
    $currentUser = $stmtUser->fetch();

    if (!$currentUser) {
        $pesan = 'Pengguna tidak ditemukan!';
        $tipePesan = 'error';
    } elseif (empty($nama) || empty($username)) {
        $pesan = 'Nama lengkap dan username tidak boleh kosong!';
        $tipePesan = 'error';
    } elseif (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
        $pesan = 'Username harus 3-50 karakter alfanumerik!';
        $tipePesan = 'error';
    } else {
        // Cek duplikasi username untuk user lain
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ? AND id != ?");
        $stmtCek->execute([$username, $userId]);
        if ($stmtCek->fetchColumn() > 0) {
            $pesan = "Username '{$username}' sudah digunakan pengguna lain!";
            $tipePesan = 'error';
        } else {
            // Proteksi: Akun sendiri tidak boleh dinonaktifkan atau diturunkan dari admin
            $isSelf = ($userId === (int)$_SESSION['user_id']);
            if ($isSelf) {
                $role = $currentUser['role'];
                $status = $currentUser['status'];
            }

                try {
                    if (!empty($passwordBaru)) {
                        if (strlen($passwordBaru) < 6) {
                            $pesan = 'Kata sandi baru minimal harus 6 karakter!';
                            $tipePesan = 'error';
                        } else {
                            $hash = password_hash($passwordBaru, PASSWORD_BCRYPT);
                            $stmtUp = $pdo->prepare("UPDATE pengguna SET nama_lengkap = ?, username = ?, password = ?, role = ?, status = ? WHERE id = ?");
                            $stmtUp->execute([$nama, $username, $hash, $role, $status, $userId]);
                            $pesan = "Data dan kata sandi pengguna '{$nama}' berhasil diperbarui!";
                            $tipePesan = 'success';
                        }
                    } else {
                        $stmtUp = $pdo->prepare("UPDATE pengguna SET nama_lengkap = ?, username = ?, role = ?, status = ? WHERE id = ?");
                        $stmtUp->execute([$nama, $username, $role, $status, $userId]);
                        $pesan = "Data pengguna '{$nama}' berhasil diperbarui!";
                        $tipePesan = 'success';
                    }

                    // Sinkronisasi session jika mengubah akun sendiri
                    if ($isSelf && $tipePesan === 'success') {
                        $_SESSION['user_nama'] = $nama;
                        $_SESSION['user_username'] = $username;
                    }
                } catch (PDOException $e) {
                    $pesan = "Gagal memperbarui pengguna: " . $e->getMessage();
                    $tipePesan = 'error';
                }
        }
    }
}

// 3. Toggle Status Pengguna (Aktif / Nonaktif)
if (isset($_GET['toggle_status'])) {
    $userId = (int)$_GET['toggle_status'];
    if ($userId === (int)$_SESSION['user_id']) {
        $pesan = 'Anda tidak dapat menonaktifkan akun Anda sendiri!';
        $tipePesan = 'error';
    } else {
        $stmtUser = $pdo->prepare("SELECT status, nama_lengkap FROM pengguna WHERE id = ?");
        $stmtUser->execute([$userId]);
        $u = $stmtUser->fetch();
        if ($u) {
            $newStatus = ($u['status'] === 'aktif') ? 'nonaktif' : 'aktif';
            $stmtUp = $pdo->prepare("UPDATE pengguna SET status = ? WHERE id = ?");
            $stmtUp->execute([$newStatus, $userId]);
            $pesan = "Status akun '{$u['nama_lengkap']}' berhasil diubah menjadi " . strtoupper($newStatus) . "!";
            $tipePesan = 'success';
        }
    }
}

// 4. Hapus Pengguna
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];
    if ($idHapus === (int)$_SESSION['user_id']) {
        $pesan = 'Anda tidak dapat menghapus akun Anda sendiri!';
        $tipePesan = 'error';
    } else {
        // Cek apakah target adalah admin dan pastikan masih ada admin aktif lain
        $stmtTarget = $pdo->prepare("SELECT role, nama_lengkap FROM pengguna WHERE id = ?");
        $stmtTarget->execute([$idHapus]);
        $target = $stmtTarget->fetch();

        if ($target) {
            if ($target['role'] === 'admin') {
                $totalAdminAktif = $pdo->query("SELECT COUNT(*) FROM pengguna WHERE role = 'admin' AND status = 'aktif' AND id != {$idHapus}")->fetchColumn();
                if ($totalAdminAktif < 1) {
                    $pesan = 'Penghapusan ditolak! Sistem harus memiliki minimal 1 Administrator aktif.';
                    $tipePesan = 'error';
                }
            }

            if ($tipePesan !== 'error') {
                try {
                    $stmtDel = $pdo->prepare("DELETE FROM pengguna WHERE id = ?");
                    $stmtDel->execute([$idHapus]);
                    $pesan = "Pengguna '{$target['nama_lengkap']}' berhasil dihapus dari sistem.";
                    $tipePesan = 'success';
                } catch (PDOException $e) {
                    $pesan = "Gagal menghapus pengguna: " . $e->getMessage();
                    $tipePesan = 'error';
                }
            }
        }
    }
}

// Statistik Pengguna
$totalUser = (int)$pdo->query("SELECT COUNT(*) FROM pengguna")->fetchColumn();
$totalAdmin = (int)$pdo->query("SELECT COUNT(*) FROM pengguna WHERE role = 'admin'")->fetchColumn();
$totalPetugas = (int)$pdo->query("SELECT COUNT(*) FROM pengguna WHERE role = 'petugas'")->fetchColumn();
$totalAktif = (int)$pdo->query("SELECT COUNT(*) FROM pengguna WHERE status = 'aktif'")->fetchColumn();

// Filter & Pencarian
$cari = trim($_GET['cari'] ?? '');
$filterRole = $_GET['filter_role'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';

$sql = "SELECT * FROM pengguna WHERE 1=1";
$params = [];
if (!empty($cari)) {
    $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if (!empty($filterRole)) {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}
if (!empty($filterStatus)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY id ASC";
$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$daftarPengguna = $stmtList->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Halaman -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2.5">
            <i class="fa-solid fa-users-gear text-emerald-700"></i> Manajemen Pengguna
        </h2>
        <p class="text-sm text-slate-500">Kelola akun administrator dan petugas layanan sirkulasi Pojok Baca Desa Sejiram.</p>
    </div>
    <button onclick="bukaModalTambah()" class="bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2.5 rounded-xl font-semibold text-sm flex items-center gap-2 shadow-sm transition">
        <i class="fa-solid fa-user-plus"></i> Tambah Pengguna Baru
    </button>
</div>

<!-- Alert Notifikasi -->
<?php if ($pesan): ?>
<div class="p-4 mb-6 rounded-xl flex items-center gap-3 shadow-sm <?= $tipePesan === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
    <i class="fa-solid <?= $tipePesan === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-lg flex-shrink-0"></i>
    <span class="text-sm font-medium"><?= e($pesan) ?></span>
</div>
<?php endif; ?>

<!-- 4 Kartu Metrik Ringkas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
            <span>Total Pengguna</span>
            <i class="fa-solid fa-users text-slate-400"></i>
        </div>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= $totalUser ?></p>
        <p class="text-[11px] text-slate-400 mt-1">Akun Terdaftar</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between text-purple-600 text-xs font-semibold">
            <span>Administrator</span>
            <i class="fa-solid fa-shield-halved text-purple-400"></i>
        </div>
        <p class="text-2xl font-bold text-purple-700 mt-1"><?= $totalAdmin ?></p>
        <p class="text-[11px] text-slate-400 mt-1">Hak Akses Penuh</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between text-blue-600 text-xs font-semibold">
            <span>Petugas Layanan</span>
            <i class="fa-solid fa-user-gear text-blue-400"></i>
        </div>
        <p class="text-2xl font-bold text-blue-700 mt-1"><?= $totalPetugas ?></p>
        <p class="text-[11px] text-slate-400 mt-1">Sirkulasi & Tamu</p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between text-emerald-600 text-xs font-semibold">
            <span>Akun Aktif</span>
            <i class="fa-solid fa-circle-check text-emerald-400"></i>
        </div>
        <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $totalAktif ?></p>
        <p class="text-[11px] text-slate-400 mt-1">Dapat Login</p>
    </div>
</div>

<!-- Filter & Pencarian -->
<div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6">
    <form method="GET" action="pengguna.php" class="flex flex-col md:flex-row gap-3">
        <div class="flex-1 relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari berdasarkan nama atau username..." class="w-full pl-9 pr-3 py-2 text-xs border rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <select name="filter_role" class="text-xs border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
            <option value="">Semua Role</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Administrator</option>
            <option value="petugas" <?= $filterRole === 'petugas' ? 'selected' : '' ?>>Petugas Layanan</option>
        </select>
        <select name="filter_status" class="text-xs border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
            <option value="">Semua Status</option>
            <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
        <button type="submit" class="bg-emerald-700 text-white px-4 py-2 rounded-lg text-xs font-semibold hover:bg-emerald-800 transition">
            Filter
        </button>
        <?php if (!empty($cari) || !empty($filterRole) || !empty($filterStatus)): ?>
        <a href="pengguna.php" class="bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-xs font-semibold hover:bg-slate-300 transition text-center">
            Reset
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabel Data Pengguna -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 text-xs uppercase tracking-wider">
                <tr>
                    <th class="p-3.5 w-12 text-center">No</th>
                    <th class="p-3.5">Nama & Username</th>
                    <th class="p-3.5">Role</th>
                    <th class="p-3.5">Status</th>
                    <th class="p-3.5">Terakhir Login</th>
                    <th class="p-3.5">Dibuat Pada</th>
                    <th class="p-3.5 text-center w-36">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($daftarPengguna)): ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-400">
                        <i class="fa-solid fa-user-slash text-3xl mb-2 text-slate-300 block"></i>
                        Tidak ada data pengguna yang sesuai kriteria pencarian.
                    </td>
                </tr>
                <?php else: 
                    $no = 1;
                    foreach ($daftarPengguna as $usr): 
                        $isSelf = ($usr['id'] === (int)$_SESSION['user_id']);
                ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-3.5 text-center text-xs text-slate-500 font-mono"><?= $no++ ?></td>
                    <td class="p-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full <?= $usr['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?> flex items-center justify-center font-bold text-xs shadow-sm">
                                <?= strtoupper(substr($usr['nama_lengkap'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 text-xs sm:text-sm flex items-center gap-1.5">
                                    <?= e($usr['nama_lengkap']) ?>
                                    <?php if ($isSelf): ?>
                                    <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-1.5 py-0.5 rounded">Anda</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs font-mono text-slate-500">@<?= e($usr['username']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="p-3.5">
                        <?php if ($usr['role'] === 'admin'): ?>
                        <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-bold bg-purple-100 text-purple-800 border border-purple-200">
                            <i class="fa-solid fa-shield-halved text-[11px]"></i> Administrator
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-bold bg-blue-100 text-blue-800 border border-blue-200">
                            <i class="fa-solid fa-user-gear text-[11px]"></i> Petugas
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3.5">
                        <?php if ($usr['status'] === 'aktif'): ?>
                        <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-md font-semibold bg-emerald-100 text-emerald-800">
                            <i class="fa-solid fa-circle text-[8px] text-emerald-600"></i> Aktif
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-md font-semibold bg-rose-100 text-rose-800">
                            <i class="fa-solid fa-circle text-[8px] text-rose-600"></i> Nonaktif
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3.5 text-xs text-slate-600">
                        <?php if (!empty($usr['terakhir_login'])): ?>
                            <span class="font-medium text-slate-700"><?= date('d M Y, H:i', strtotime($usr['terakhir_login'])) ?> WIB</span>
                        <?php else: ?>
                            <span class="text-slate-400 italic">Belum pernah login</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3.5 text-xs text-slate-500">
                        <?= date('d M Y', strtotime($usr['created_at'])) ?>
                    </td>
                    <td class="p-3.5 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <!-- Tombol Edit -->
                            <button onclick="bukaModalEdit(<?= htmlspecialchars(json_encode([
                                'id' => $usr['id'],
                                'nama_lengkap' => $usr['nama_lengkap'],
                                'username' => $usr['username'],
                                'role' => $usr['role'],
                                'status' => $usr['status'],
                                'is_self' => $isSelf
                            ])) ?>)" title="Sunting Pengguna" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 flex items-center justify-center transition">
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                            </button>

                            <!-- Tombol Toggle Status -->
                            <?php if (!$isSelf): ?>
                            <a href="pengguna.php?toggle_status=<?= $usr['id'] ?>" onclick="return confirm('Ubah status akun <?= e($usr['nama_lengkap']) ?> menjadi <?= $usr['status'] === 'aktif' ? 'Nonaktif' : 'Aktif' ?>?')" title="<?= $usr['status'] === 'aktif' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>" class="w-8 h-8 rounded-lg <?= $usr['status'] === 'aktif' ? 'bg-slate-100 hover:bg-slate-200 text-slate-600' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700' ?> flex items-center justify-center transition">
                                <i class="fa-solid <?= $usr['status'] === 'aktif' ? 'fa-ban' : 'fa-check' ?> text-xs"></i>
                            </a>

                            <!-- Tombol Hapus -->
                            <a href="pengguna.php?hapus=<?= $usr['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus akun pengguna <?= e($usr['nama_lengkap']) ?>? Tindakan ini tidak dapat dibatalkan!')" title="Hapus Pengguna" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 flex items-center justify-center transition">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </a>
                            <?php else: ?>
                            <span class="w-8 h-8 rounded-lg bg-slate-50 text-slate-300 flex items-center justify-center text-xs" title="Akun Sendiri (Terkunci)">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= MODAL TAMBAH PENGGUNA ================= -->
<div id="modalTambah" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-fadeIn">
        <div class="bg-emerald-800 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-300"></i> Tambah Pengguna Baru
            </h3>
            <button onclick="tutupModalTambah()" class="text-emerald-200 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="pengguna.php" class="p-6 space-y-4">
            <input type="hidden" name="tambah_pengguna" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_lengkap" required placeholder="Contoh: Rian Pratama, S.Kom" class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                <input type="text" name="username" required placeholder="Contoh: rian_petugas" class="w-full text-xs font-mono border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500">
                <p class="text-[10px] text-slate-400 mt-1">Hanya huruf kecil, angka, dan garis bawah (min. 3 karakter).</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <input type="password" id="tambah_password" name="password" required placeholder="Minimal 6 karakter..." class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 pr-10 outline-none focus:ring-2 focus:ring-emerald-500">
                    <button type="button" onclick="togglePassVisibility('tambah_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Role / Peran <span class="text-rose-500">*</span></label>
                    <select name="role" required class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="petugas" selected>Petugas Layanan</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Akun <span class="text-rose-500">*</span></label>
                    <select name="status" required class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="aktif" selected>Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalTambah()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                    Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDIT PENGGUNA ================= -->
<div id="modalEdit" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-fadeIn">
        <div class="bg-amber-700 text-white px-6 py-4 flex justify-between items-center">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-amber-200"></i> Sunting Data Pengguna
            </h3>
            <button onclick="tutupModalEdit()" class="text-amber-200 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="pengguna.php" class="p-6 space-y-4">
            <input type="hidden" name="edit_pengguna" value="1">
            <input type="hidden" id="edit_user_id" name="user_id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_nama" name="nama_lengkap" required class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_username" name="username" required class="w-full text-xs font-mono border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div class="bg-amber-50/70 p-3.5 rounded-xl border border-amber-200/80">
                <label class="block text-xs font-bold text-slate-800 mb-1">Kata Sandi Baru (Opsional)</label>
                <div class="relative">
                    <input type="password" id="edit_password_baru" name="password_baru" placeholder="Kosongkan jika tidak ingin mengubah..." class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2 pr-10 outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    <button type="button" onclick="togglePassVisibility('edit_password_baru', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <p class="text-[10px] text-amber-800 mt-1">Isi hanya jika ingin mengganti kata sandi pengguna ini.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Role / Peran</label>
                    <select id="edit_role" name="role" required class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="petugas">Petugas Layanan</option>
                        <option value="admin">Administrator</option>
                    </select>
                    <p id="alert_role_self" class="text-[10px] text-rose-500 mt-1 hidden">Akun sendiri harus tetap Admin.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status Akun</label>
                    <select id="edit_status" name="status" required class="w-full text-xs border border-slate-300 rounded-xl px-3 py-2.5 outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="tutupModalEdit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalTambah() {
    document.getElementById('modalTambah').classList.remove('hidden');
}

function tutupModalTambah() {
    document.getElementById('modalTambah').classList.add('hidden');
}

function bukaModalEdit(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_nama').value = user.nama_lengkap;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_password_baru').value = '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;

    // Jika akun sendiri, disabled role & status agar tidak terkunci
    const roleSelect = document.getElementById('edit_role');
    const statusSelect = document.getElementById('edit_status');
    const alertRole = document.getElementById('alert_role_self');

    if (user.is_self) {
        roleSelect.disabled = true;
        statusSelect.disabled = true;
        alertRole.classList.remove('hidden');
    } else {
        roleSelect.disabled = false;
        statusSelect.disabled = false;
        alertRole.classList.add('hidden');
    }

    document.getElementById('modalEdit').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEdit').classList.add('hidden');
}

function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
