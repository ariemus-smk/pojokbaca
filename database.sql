-- Basis Data Sistem Administrasi Pojok Baca Desa Sejiram
-- Kompatibel dengan MySQL 5.7+ / MariaDB 10.3+ / MySQL 8.x

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- 1. Tabel Pengaturan Sistem & Profil Desa
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_instansi` VARCHAR(150) NOT NULL,
  `desa` VARCHAR(100) NOT NULL,
  `kabupaten` VARCHAR(100) NOT NULL,
  `alamat` TEXT NOT NULL,
  `jam_operasional` VARCHAR(100) NOT NULL DEFAULT '08.00 - 16.00 WIB',
  `durasi_pinjam_hari` INT NOT NULL DEFAULT 7,
  `denda_per_hari` INT NOT NULL DEFAULT 1000,
  `nama_kades` VARCHAR(100) NOT NULL,
  `nama_pengelola` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pengaturan` (`id`, `nama_instansi`, `desa`, `kabupaten`, `alamat`, `jam_operasional`, `durasi_pinjam_hari`, `denda_per_hari`, `nama_kades`, `nama_pengelola`) 
VALUES (1, 'Pojok Baca Pa''edah', 'Desa Sejiram', 'Kabupaten Sambas', 'Jl. Pendidikan No. 01, Kantor Desa Sejiram, Kec. Tebas', '08.00 - 15.30 WIB', 7, 1000, 'Kepala Desa Sejiram', 'Syarif Ahmad, S.Pd')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 2. Tabel Pengguna (Login & Hak Akses)
-- Password default: 'admin123' untuk admin & 'petugas123' untuk petugas
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'petugas') NOT NULL DEFAULT 'petugas',
  `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `terakhir_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pengguna` (`id`, `nama_lengkap`, `username`, `password`, `role`, `status`) VALUES
(1, 'Administrator Pojok Baca', 'admin', '$2y$10$3c6sDfvH4lFfqoP325McfO6e5o2Psq3iNnQ6H7XwFek.Fk5x1pYgS', 'admin', 'aktif'),
(2, 'Petugas Layanan Sejiram', 'petugas', '$2y$10$83Z88xJ38y1p81aGZkW17uJb6u1j84N4w8zE0z1sU9PzZ6jG8dEee', 'petugas', 'aktif')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 3. Tabel Koleksi Buku (Dengan 7 Kategori Baku)
CREATE TABLE IF NOT EXISTS `buku` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_buku` VARCHAR(50) UNIQUE NOT NULL,
  `judul` VARCHAR(255) NOT NULL,
  `penulis` VARCHAR(150) NOT NULL,
  `penerbit` VARCHAR(150) NOT NULL,
  `tahun` INT NOT NULL,
  `kategori` ENUM(
    'Koleksi buku anak',
    'Buku pendidikan',
    'Buku kesehatan',
    'Buku keterampilan',
    'Buku agama',
    'Buku budaya/kearifan lokal',
    'Buku lainnya'
  ) NOT NULL,
  `lokasi_rak` VARCHAR(50) NOT NULL,
  `stok_total` INT NOT NULL DEFAULT 1,
  `stok_tersedia` INT NOT NULL DEFAULT 1,
  `kondisi` ENUM('Baik', 'Rusak Ringan', 'Rusak Parah') NOT NULL DEFAULT 'Baik',
  `cover` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Anggota Pemustaka
CREATE TABLE IF NOT EXISTS `anggota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_anggota` VARCHAR(50) UNIQUE NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `kategori_tingkat` ENUM('SD', 'SMP', 'SMA', 'Warga Umum', 'Aparatur Desa') NOT NULL,
  `institusi` VARCHAR(150) NOT NULL,
  `kontak` VARCHAR(30) NOT NULL,
  `status` ENUM('Aktif', 'Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Transaksi Peminjaman & Pengembalian
CREATE TABLE IF NOT EXISTS `peminjaman` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_transaksi` VARCHAR(50) UNIQUE NOT NULL,
  `anggota_id` INT NOT NULL,
  `buku_id` INT NOT NULL,
  `tanggal_pinjam` DATE NOT NULL,
  `jatuh_tempo` DATE NOT NULL,
  `tanggal_kembali` DATE DEFAULT NULL,
  `status` ENUM('Dipinjam', 'Kembali') NOT NULL DEFAULT 'Dipinjam',
  `hari_terlambat` INT NOT NULL DEFAULT 0,
  `denda` INT NOT NULL DEFAULT 0,
  `petugas` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`anggota_id`) REFERENCES `anggota`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`buku_id`) REFERENCES `buku`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Presensi Kunjungan Tamu
CREATE TABLE IF NOT EXISTS `kunjungan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tanggal` DATE NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `kategori_pemustaka` ENUM('SD', 'SMP', 'SMA', 'Warga Umum', 'Aparatur Desa') NOT NULL,
  `asal_institusi` VARCHAR(150) NOT NULL,
  `keperluan` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Inventaris & Log Buku Rusak/Hilang/Pengadaan
CREATE TABLE IF NOT EXISTS `inventaris` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `jenis` ENUM('Rusak', 'Hilang', 'Pengadaan') NOT NULL,
  `buku_id` INT NULL,
  `judul_manual` VARCHAR(255) NULL,
  `jumlah` INT NOT NULL DEFAULT 1,
  `sumber_dana` VARCHAR(150) NULL,
  `keterangan` TEXT NOT NULL,
  `tanggal` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`buku_id`) REFERENCES `buku`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- 8. Tabel Galeri Foto Kegiatan & Kegiatan PKK
CREATE TABLE IF NOT EXISTS `galeri` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(200) NOT NULL,
  `kategori` ENUM('Kegiatan Pojok Baca', 'Kegiatan PKK') NOT NULL,
  `foto` VARCHAR(255) NOT NULL,
  `tanggal` DATE NOT NULL,
  `keterangan` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. Tabel Artikel & Berita Literasi Desa
CREATE TABLE IF NOT EXISTS `artikel` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `judul` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) UNIQUE NOT NULL,
  `kategori` ENUM('Literasi Desa', 'Kegiatan PKK', 'Edukasi & Pertanian', 'Kearifan Lokal') NOT NULL,
  `gambar` VARCHAR(255) NULL,
  `konten` TEXT NOT NULL,
  `penulis` VARCHAR(100) NOT NULL,
  `tanggal` DATE NOT NULL,
  `views` INT NOT NULL DEFAULT 0,
  `status` ENUM('Publish', 'Draft') NOT NULL DEFAULT 'Publish',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
