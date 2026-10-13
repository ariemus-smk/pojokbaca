<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan kata sandi wajib diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE username = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Password hash check atau fallback jika kata sandi admin123 / petugas123
        $passValid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $passValid = true;
            } elseif (($username === 'admin' && $password === 'admin123') || ($username === 'petugas' && $password === 'petugas123')) {
                // Update ke hash BCRYPT yang baru
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upHash = $pdo->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
                $upHash->execute([$newHash, $user['id']]);
                $passValid = true;
            }
        }

        if ($passValid) {
            $up = $pdo->prepare("UPDATE pengguna SET terakhir_login = NOW() WHERE id = ?");
            $up->execute([$user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nama'] = $user['nama_lengkap'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Username atau kata sandi tidak valid!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Pojok Baca Desa Sejiram</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-slate-200">
        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-emerald-700 text-white rounded-full flex items-center justify-center text-2xl mx-auto shadow-md mb-3">
                <i class="fa-solid fa-book-open-reader"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-800">POJOK BACA PA'EDAH</h1>
            <p class="text-xs text-slate-500">Pemerintah Desa Sejiram, Kec. Tebas, Kab. Sambas</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="bg-rose-100 border border-rose-300 text-rose-700 px-4 py-2.5 rounded-lg text-xs mb-4">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'belum_login'): ?>
        <div class="bg-amber-100 border border-amber-300 text-amber-800 px-4 py-2.5 rounded-lg text-xs mb-4">
            <i class="fa-solid fa-lock mr-1"></i> Silakan masuk terlebih dahulu untuk mengakses sistem.
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pengguna (Username)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" required autofocus placeholder="Masukkan Username" class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi (Password)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <input type="password" name="password" required placeholder="Masukkan Kata Sandi" class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
            </div>

            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-2.5 rounded-lg text-sm transition shadow-sm">
                Masuk ke Sistem
            </button>
        </form>
        <a href="portal.php" class="mt-3 text-center block w-full bg-slate-600 hover:bg-slate-700 text-white font-semibold py-2.5 rounded-lg text-sm transition shadow-sm">
            Kembali ke Portal Publik
        </a>
    </div>
</body>
</html>
