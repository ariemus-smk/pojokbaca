<?php
declare(strict_types=1);

// Fungsi pembaca berkas .env mandiri tanpa dependensi luar
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            // Lewati baris komentar (# atau ;) dan baris kosong
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);

                // Bersihkan tanda kutip pembungkus
                if (
                    (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))
                ) {
                    $val = substr($val, 1, -1);
                }

                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Fungsi helper membaca variabel environment dengan nilai default
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

// Muat konfigurasi dari berkas .env jika tersedia
loadEnv(__DIR__ . '/../.env');

// Ambil konfigurasi database dari .env atau fallback default
$db_host = env('DB_HOST', 'localhost');
$db_port = (int)env('DB_PORT', 3306);
$db_name = env('DB_NAME', 'db_pojokbaca_sejiram');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;max-width:600px;margin:40px auto;border:1px solid #f87171;'>
        <h3 style='margin-top:0;'>Koneksi Basis Data Gagal</h3>
        <p>Pastikan berkas <code>.env</code> di root aplikasi sudah berisi pengaturan koneksi MySQL yang benar.</p>
        <p style='font-size:12px;background:#fff;padding:8px;border-radius:4px;border:1px solid #fca5a5;'><strong>Detail Error:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
    </div>");
}

// Fungsi helper escape output HTML (XSS prevention)
if (!function_exists('e')) {
    function e(string|null $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
