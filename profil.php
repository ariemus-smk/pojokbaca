<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
cekLogin();

$pageTitle = 'Profil & Keamanan Akun';
$pesanProfil = '';
$tipePesanProfil = '';
$pesanPass = '';
$tipePesanPass = '';

$userId = (int)$_SESSION['user_id'];

// Ambil data user terkini
$stmtUser = $pdo->prepare("SELECT * FROM pengguna WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

if (!$userData) {
    session_destroy();
    header('Location: login.php?pesan=sesi_habis');
    exit;
}

// 1. Handler Perbarui Profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));

    if (empty($nama) || empty($username)) {
        $pesanProfil = 'Nama lengkap dan username tidak boleh kosong!';
        $tipePesanProfil = 'error';
    } elseif (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
        $pesanProfil = 'Username harus 3-50 karakter alfanumerik (huruf kecil, angka, atau strip)!';
        $tipePesanProfil = 'error';
    } else {
        // Cek duplikasi username
        $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = ? AND id != ?");
        $stmtCek->execute([$username, $userId]);
        if ($stmtCek->fetchColumn() > 0) {
            $pesanProfil = "Username '{$username}' sudah digunakan oleh pengguna lain!";
            $tipePesanProfil = 'error';
        } else {
            try {
                $stmtUp = $pdo->prepare("UPDATE pengguna SET nama_lengkap = ?, username = ? WHERE id = ?");
                $stmtUp->execute([$nama, $username, $userId]);

                // Sinkronkan session
                $_SESSION['user_nama'] = $nama;
                $_SESSION['user_username'] = $username;

                // Refresh data lokal
                $userData['nama_lengkap'] = $nama;
                $userData['username'] = $username;

                $pesanProfil = 'Data profil berhasil diperbarui!';
                $tipePesanProfil = 'success';
            } catch (PDOException $e) {
                $pesanProfil = 'Gagal memperbarui profil: ' . $e->getMessage();
                $tipePesanProfil = 'error';
            }
        }
    }
}

// 2. Handler Ganti Kata Sandi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $passLama = $_POST['password_lama'] ?? '';
    $passBaru = $_POST['password_baru'] ?? '';
    $passKonfirm = $_POST['konfirmasi_password'] ?? '';

    if (empty($passLama) || empty($passBaru) || empty($passKonfirm)) {
        $pesanPass = 'Seluruh kolom kata sandi wajib diisi!';
        $tipePesanPass = 'error';
    } elseif (!password_verify($passLama, $userData['password'])) {
        $pesanPass = 'Kata sandi saat ini yang Anda masukkan salah!';
        $tipePesanPass = 'error';
    } elseif (strlen($passBaru) < 6) {
        $pesanPass = 'Kata sandi baru minimal harus 6 karakter!';
        $tipePesanPass = 'error';
    } elseif ($passBaru !== $passKonfirm) {
        $pesanPass = 'Konfirmasi kata sandi baru tidak cocok!';
        $tipePesanPass = 'error';
    } elseif ($passLama === $passBaru) {
        $pesanPass = 'Kata sandi baru tidak boleh sama dengan kata sandi saat ini!';
        $tipePesanPass = 'error';
    } else {
        try {
            $newHash = password_hash($passBaru, PASSWORD_BCRYPT);
            $stmtPass = $pdo->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
            $stmtPass->execute([$newHash, $userId]);

            // Refresh hash lokal
            $userData['password'] = $newHash;

            $pesanPass = 'Kata sandi Anda berhasil diperbarui!';
            $tipePesanPass = 'success';
        } catch (PDOException $e) {
            $pesanPass = 'Gagal memperbarui kata sandi: ' . $e->getMessage();
            $tipePesanPass = 'error';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header Halaman -->
<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2.5">
        <i class="fa-solid fa-id-card-clip text-emerald-700"></i> Profil & Pengaturan Akun
    </h2>
    <p class="text-sm text-slate-500">Perbarui identitas profil dan kelola kata sandi akun Anda secara aman.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- Kolom Kiri: Kartu Identitas Profil -->
    <div class="lg:col-span-4 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Header Kartu -->
            <div class="bg-gradient-to-br from-emerald-800 via-teal-800 to-slate-900 text-white p-6 text-center relative">
                <div class="w-20 h-20 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 text-white flex items-center justify-center font-extrabold text-3xl mx-auto shadow-lg mb-3">
                    <?= strtoupper(substr($userData['nama_lengkap'], 0, 1)) ?>
                </div>
                <h3 class="font-extrabold text-lg leading-snug"><?= e($userData['nama_lengkap']) ?></h3>
                <p class="text-xs font-mono text-emerald-200 mt-0.5">@<?= e($userData['username']) ?></p>

                <div class="mt-3 flex items-center justify-center gap-2">
                    <?php if ($userData['role'] === 'admin'): ?>
                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded-full font-bold bg-purple-500/30 border border-purple-300/40 text-purple-100">
                        <i class="fa-solid fa-shield-halved text-[11px]"></i> Administrator
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded-full font-bold bg-blue-500/30 border border-blue-300/40 text-blue-100">
                        <i class="fa-solid fa-user-gear text-[11px]"></i> Petugas Layanan
                    </span>
                    <?php endif; ?>

                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-bold bg-emerald-500/30 border border-emerald-300/40 text-emerald-100">
                        <i class="fa-solid fa-circle text-[7px] text-emerald-300"></i> Aktif
                    </span>
                </div>
            </div>

            <!-- Detail Informasi -->
            <div class="p-5 space-y-3.5 text-xs border-t border-slate-100">
                <div class="flex justify-between items-center py-1 border-b border-slate-100">
                    <span class="text-slate-400 font-medium">ID Pengguna</span>
                    <span class="font-mono font-bold text-slate-700">#<?= $userData['id'] ?></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-slate-100">
                    <span class="text-slate-400 font-medium">Instansi</span>
                    <span class="font-semibold text-slate-800 text-right"><?= e($appSetting['nama_instansi']) ?></span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-slate-100">
                    <span class="text-slate-400 font-medium">Wilayah</span>
                    <span class="font-semibold text-slate-800"><?= e($appSetting['desa']) ?>, Sambas</span>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-slate-100">
                    <span class="text-slate-400 font-medium">Terakhir Login</span>
                    <span class="font-medium text-slate-700 text-right">
                        <?= !empty($userData['terakhir_login']) ? date('d M Y, H:i', strtotime($userData['terakhir_login'])) . ' WIB' : 'Sesi Ini' ?>
                    </span>
                </div>
                <div class="flex justify-between items-center py-1">
                    <span class="text-slate-400 font-medium">Akun Dibuat</span>
                    <span class="font-medium text-slate-700"><?= date('d M Y', strtotime($userData['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Kartu Tips Keamanan -->
        <div class="bg-emerald-50/70 rounded-2xl p-5 border border-emerald-200/80 text-xs text-emerald-900">
            <h4 class="font-bold flex items-center gap-2 mb-2 text-emerald-950">
                <i class="fa-solid fa-lock text-emerald-700"></i> Tips Keamanan Akun
            </h4>
            <ul class="space-y-2 list-disc list-inside text-slate-600 leading-relaxed">
                <li>Gunakan kata sandi unik dengan kombinasi huruf, angka, dan karakter khusus.</li>
                <li>Hindari menggunakan kata sandi yang mudah ditebak seperti tanggal lahir.</li>
                <li>Selalu lakukan <strong>Keluar (Logout)</strong> setelah selesai bertugas di komputer bersama.</li>
            </ul>
        </div>
    </div>

    <!-- Kolom Kanan: Form Ubah Profil & Form Ganti Password -->
    <div class="lg:col-span-8 space-y-6">

        <!-- 1. Form Ubah Data Profil -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-base">Ubah Data Diri</h3>
                    <p class="text-xs text-slate-400">Perbarui nama lengkap dan username akun Anda.</p>
                </div>
            </div>

            <?php if ($pesanProfil): ?>
            <div class="p-3.5 mb-5 rounded-xl flex items-center gap-2.5 text-xs font-semibold <?= $tipePesanProfil === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                <i class="fa-solid <?= $tipePesanProfil === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>"></i>
                <span><?= e($pesanProfil) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="profil.php" class="space-y-4">
                <input type="hidden" name="update_profil" value="1">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap Anda <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_lengkap" value="<?= e($userData['nama_lengkap']) ?>" required class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username" value="<?= e($userData['username']) ?>" required class="w-full text-xs font-mono border border-slate-300 rounded-xl px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">Digunakan saat masuk ke sistem.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Role Pengguna (Terkunci)</label>
                        <input type="text" value="<?= $userData['role'] === 'admin' ? 'Administrator' : 'Petugas Layanan' ?>" disabled class="w-full text-xs border border-slate-200 bg-slate-100 rounded-xl px-3.5 py-2.5 text-slate-500 font-semibold cursor-not-allowed">
                        <p class="text-[10px] text-slate-400 mt-1">Perubahan role hanya dapat dilakukan Administrator utama.</p>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-sm transition">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Profil
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Form Ganti Kata Sandi -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-base">Ganti Kata Sandi</h3>
                    <p class="text-xs text-slate-400">Pastikan memasukkan kata sandi lama untuk memverifikasi kepemilikan akun.</p>
                </div>
            </div>

            <?php if ($pesanPass): ?>
            <div class="p-3.5 mb-5 rounded-xl flex items-center gap-2.5 text-xs font-semibold <?= $tipePesanPass === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                <i class="fa-solid <?= $tipePesanPass === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?>"></i>
                <span><?= e($pesanPass) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="profil.php" class="space-y-4">
                <input type="hidden" name="ganti_password" value="1">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Kata Sandi Saat Ini <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <input type="password" id="pass_lama" name="password_lama" required placeholder="Masukkan kata sandi saat ini..." class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 pr-10 outline-none focus:ring-2 focus:ring-amber-500">
                        <button type="button" onclick="togglePass('pass_lama', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Kata Sandi Baru <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="pass_baru" name="password_baru" required placeholder="Minimal 6 karakter..." class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 pr-10 outline-none focus:ring-2 focus:ring-amber-500">
                            <button type="button" onclick="togglePass('pass_baru', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Ulangi Kata Sandi Baru <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="pass_konfirm" name="konfirmasi_password" required placeholder="Ketik ulang kata sandi baru..." class="w-full text-xs border border-slate-300 rounded-xl px-3.5 py-2.5 pr-10 outline-none focus:ring-2 focus:ring-amber-500">
                            <button type="button" onclick="togglePass('pass_konfirm', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="bg-amber-700 hover:bg-amber-800 text-white px-5 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 shadow-sm transition">
                        <i class="fa-solid fa-shield-halved"></i> Perbarui Kata Sandi
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
function togglePass(inputId, btn) {
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
