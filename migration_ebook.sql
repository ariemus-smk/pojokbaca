-- Migrasi Tambahan Fitur E-Book Digital (PDF)
-- Aman dijalankan pada database produksi yang sudah berjalan (tidak menghapus atau mengubah data lama)

CREATE TABLE IF NOT EXISTS `ebook` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_ebook` VARCHAR(50) UNIQUE NOT NULL,
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
  `deskripsi` TEXT NULL,
  `cover` VARCHAR(255) NULL DEFAULT NULL,
  `file_pdf` VARCHAR(255) NOT NULL,
  `ukuran_file` BIGINT NOT NULL DEFAULT 0,
  `views` INT NOT NULL DEFAULT 0,
  `downloads` INT NOT NULL DEFAULT 0,
  `status` ENUM('Publish', 'Draft') NOT NULL DEFAULT 'Publish',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

