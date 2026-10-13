# 📚 Pojok Baca Pa'edah - Desa Sejiram

<p align="center">
  <img src="uploads/img/banner.jpeg" alt="Banner Pojok Baca Pa'edah Desa Sejiram" width="100%" style="border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);" />
</p>

<p align="center">
  <strong>Sistem Informasi Manajemen Perpustakaan & Portal Literasi Desa Terpadu</strong><br>
  Pemerintah Desa Sejiram, Kecamatan Tebas, Kabupaten Sambas, Kalimantan Barat
</p>

<p align="center">
  <a href="#-fitur-utama"><img src="https://img.shields.io/badge/Status-Production%20Ready-emerald?style=for-the-badge&logo=checkmarx" alt="Status"></a>
  <a href="#-teknologi-yang-digunakan"><img src="https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"></a>
  <a href="#-teknologi-yang-digunakan"><img src="https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL"></a>
  <a href="#-teknologi-yang-digunakan"><img src="https://img.shields.io/badge/Styling-TailwindCSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="TailwindCSS"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License"></a>
</p>

---

## 📖 Tentang Aplikasi

**Pojok Baca Pa'edah** adalah platform sistem informasi perpustakaan desa berbasis web yang dibangun untuk memfasilitasi gerakan literasi warga, pelajar, serta kegiatan Tim Penggerak PKK (TP-PKK) di **Desa Sejiram, Kecamatan Tebas, Kabupaten Sambas**.

Aplikasi ini menggabungkan dua ekosistem terpadu:
1. **Portal Publik (`portal.php`)**: Halaman terbuka yang menyajikan katalog koleksi buku digital, pencarian ketersediaan stok buku secara real-time, artikel/warta literasi desa, dokumentasi galeri kegiatan, serta presensi mandiri (buku tamu digital) bagi para pengunjung.
2. **Sistem Informasi Manajemen Petugas (`dashboard.php`)**: Panel kendali aman dengan autentikasi berbasis peran (*Role-Based Access Control*) untuk mengelola sirkulasi buku (peminjaman & pengembalian otomatis denda), data anggota pemustaka, log inventaris, dan cetak laporan resmi ber-KOP Surat atau ekspor Excel/CSV.

---

## 🌟 Fitur Utama

### 1. 🌐 Portal Publik & Transparansi Informasi
* **Katalog Buku Real-Time**: Pencarian cepat berdasarkan judul, penulis, nomor rak, serta filter 7 kategori baku koleksi.
* **Indikator Ketersediaan Stok**: Informasi jumlah eksemplar *Ready* atau *Kosong* secara langsung sebelum pemustaka berkunjung.
* **Buku Tamu / Presensi Digital Mandiri**: Pengunjung dapat mengisi kehadiran secara mandiri berdasarkan kategori (Pelajar SD, SMP, SMA, Warga Umum/PKK, Aparatur Desa).
* **Warta & Artikel Literasi**: Publikasi artikel edukatif, info pertanian jeruk lokal, kegiatan TP-PKK, dan panduan keluarga.
* **Galeri Dokumentasi**: Dokumentasi foto kegiatan anak-anak dan pembinaan ibu-ibu PKK.
* **Banner Resmi Dinamis**: Banner identitas visual Pojok Baca Pa'edah yang responsif dan elegan.

### 2. ⚡ Sirkulasi Peminjaman & Pengembalian
* **Pencatatan Transaksi Cepat**: Validasi stok buku otomatis saat transaksi peminjaman dibuat.
* **Kalkulasi Denda Otomatis**: Perhitungan hari keterlambatan dan nominal denda secara instan sesuai batas waktu peminjaman di sistem.
* **Monitoring Jatuh Tempo**: Peringatan visual untuk buku yang mendekati atau telah melewati batas jatuh tempo.

### 3. 👥 Manajemen Pemustaka & Koleksi
* **Data Anggota**: Pengelolaan anggota dari berbagai tingkatan (SD, SMP, SMA, Masyarakat Umum, dan Perangkat Desa).
* **7 Kategori Koleksi Terstandarisasi**:
  - Koleksi buku anak & dongeng
  - Buku pendidikan & pelajaran
  - Buku kesehatan & gizi keluarga
  - Buku keterampilan praktis & pertanian
  - Buku agama & akhlak
  - Buku budaya & kearifan lokal Sambas
  - Buku lainnya
* **Pencatatan Fisik Buku**: Penataan kode buku unik, lokasi rak, kondisi buku (Baik, Rusak Ringan, Rusak Parah), dan unggah sampul (*cover*).

### 4. 📦 Inventarisasi & Pengadaan Buku
* **Pencatatan Mutasi Koleksi**: Pelacakan buku hilang, buku rusak/afkir, dan pengadaan buku baru.
* **Transparansi Sumber Dana**: Pencatatan alokasi dana pengadaan (Dana Desa, Hibah/Donasi, PKK, Swadaya).

### 5. 📊 Laporan & Cetak Dokumen Resmi
* **Format Cetak KOP Surat Resmi**: Cetak laporan rekapitulasi sirkulasi bulanan siap tanda tangan Kepala Desa & Pengelola Pojok Baca (`Window Print` teroptimasi CSS).
* **Ekspor Data Excel / CSV**: Unduh data laporan ke format `.csv` dengan penanganan UTF-8 BOM untuk kompatibilitas penuh Microsoft Excel.

### 6. 🔒 Keamanan, Manajemen Pengguna & Profil
* **User Manager Terintegrasi (`pengguna.php`)**: Administrator dapat menambah pengguna baru, menyunting data akun, mengganti kata sandi pengguna lain, mengubah status (aktif/nonaktif), serta menghapus akun dengan proteksi anti-lockout (mencegah penghapusan akun sendiri atau penghapusan admin terakhir).
* **Profil Diri & Ganti Kata Sandi (`profil.php`)**: Seluruh pengguna (Admin & Petugas) dapat memperbarui nama lengkap, username, serta mengganti kata sandi dengan verifikasi kata sandi saat ini dan tombol intip (*toggle show/hide password*).
* **Multi-Role User**: Pemisahan hak akses ketat antara **Administrator** (akses penuh termasuk pengaturan dan user manager) dan **Petugas** (operasional harian).
* **Proteksi Keamanan**: Query database berbasis *Prepared Statements* (PDO anti SQL-Injection), sanitasi XSS (`htmlspecialchars`), serta enkripsi kata sandi menggunakan `password_hash()` BCRYPT.

---

## 🏗️ Struktur Direktori Proyek

```text
pojokbaca/
├── config/
│   ├── auth.php              # Session handler & middleware otorisasi role
│   └── database.php          # Koneksi database PDO & fungsi helper sanitasi
├── includes/
│   ├── header.php            # Navigasi sidebar & header panel petugas
│   └── footer.php            # Footer template panel petugas
├── uploads/
│   ├── artikel/              # Berkas gambar artikel & warta desa
│   ├── covers/               # Berkas gambar cover buku
│   ├── galeri/               # Berkas dokumentasi foto kegiatan
│   └── img/
│       └── banner.jpeg       # Banner utama portal publik
├── anggota.php               # Modul manajemen anggota perpustakaan
├── artikel.php               # Modul manajemen konten artikel & warta
├── baca_artikel.php          # Halaman pembaca artikel publik
├── buku.php                  # Modul CRUD koleksi buku & stok
├── dashboard.php             # Dashboard ringkasan analitik & metrik
├── database.sql              # Skrip skema database & data inisialisasi
├── galeri.php                # Modul upload dokumentasi kegiatan & PKK
├── index.php                 # Gerbang masuk utama (mengarahkan ke portal publik)
├── inventaris.php            # Modul log buku rusak/hilang/pengadaan
├── kunjungan.php             # Modul rekapitulasi presensi buku tamu
├── laporan.php               # Modul cetak laporan resmi & ekspor CSV
├── login.php                 # Halaman autentikasi petugas & admin
├── logout.php                # Handler keluar sesi sistem
├── peminjaman.php            # Modul transaksi sirkulasi peminjaman
├── pengguna.php              # User Manager: manajemen akun admin & petugas (Admin)
├── pengaturan.php            # Konfigurasi profil desa & aturan denda (Admin)
├── pengembalian.php          # Modul proses pengembalian buku & denda
├── portal.php                # Halaman portal utama untuk masyarakat umum
└── profil.php                # Modul profil diri & ubah kata sandi pengguna
```

---

## 💻 Kebutuhan Sistem

Sebelum memasang aplikasi, pastikan lingkungan server Anda memenuhi spesifikasi berikut:

| Komponen | Spesifikasi Minimum | Rekomendasi |
| :--- | :--- | :--- |
| **PHP** | PHP 8.0+ | PHP 8.2 / 8.3 |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | MySQL 8.0+ |
| **Ekstensi PHP** | `pdo`, `pdo_mysql`, `mbstring`, `fileinfo` | Aktif secara default |
| **Web Server** | Apache (mod_rewrite) / Nginx / PHP Built-in Server | Apache / Nginx |
| **Browser** | Chrome, Edge, Firefox, Safari (Modern Browser) | Versi terbaru |

---

## 🚀 Panduan Instalasi Lokal

### 1. Kloning Repositori
```bash
git clone git@github.com:ariemus-smk/pojokbaca.git
cd pojokbaca
```

### 2. Konfigurasi Basis Data (.env)
Salin berkas template `.env.example` menjadi `.env`, kemudian sesuaikan kredensial koneksi basis data Anda:

```bash
cp .env.example .env
```

Buka dan edit berkas `.env`:
```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=db_pojokbaca_sejiram
DB_USER=root
DB_PASS=password_database_anda
```

> [!TIP]
> Berkas `.env` telah tercatat di `.gitignore` sehingga pengaturan server lokal maupun server produksi tidak akan tertimpa atau terganggu saat melakukan `git pull` atau pembaruan kode.


### 3. Impor Skema Basis Data
Buat basis data baru di MySQL lalu impor berkas `database.sql`:

* **Melalui CLI:**
  ```bash
  mysql -u root -p -e "CREATE DATABASE db_pojokbaca_sejiram CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u root -p db_pojokbaca_sejiram < database.sql
  ```
* **Melalui phpMyAdmin:**
  1. Buat database baru bernama `db_pojokbaca_sejiram`.
  2. Buka tab **Import**, pilih berkas `database.sql`, lalu klik **Go**.

### 4. Menjalankan Server Lokal
Anda dapat langsung menggunakan PHP Built-in Server untuk pengujian lokal:
```bash
php -S 127.0.0.1:8000
```
Buka browser dan akses:
* **Portal Publik**: [http://127.0.0.1:8000/portal.php](http://127.0.0.1:8000/portal.php)
* **Login Petugas**: [http://127.0.0.1:8000/login.php](http://127.0.0.1:8000/login.php)

*(Jika menggunakan Laragon / XAMPP, tempatkan folder project pada direktori `htdocs` atau `www`).*

---

## 🔑 Akun Akses Default

Sistem telah dilengkapi dengan akun bawaan untuk pengujian awal:

| Peran (Role) | Username | Password Default | Hak Akses |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Akses penuh seluruh modul & pengaturan instansi |
| **Petugas Layanan** | `petugas` | `petugas123` | Akses operasional sirkulasi, buku, dan presensi |

> [!IMPORTANT]
> **Peringatan Keamanan**: Harap segera ubah kata sandi default setelah sistem berhasil di-deploy ke server produksi untuk mencegah akses yang tidak sah.

---

## 🌐 Panduan Deployment ke Hosting / cPanel

1. **Unggah Berkas**:
   - Kompres seluruh isi folder project ke dalam format `.zip`.
   - Unggah dan ekstrak file `.zip` ke dalam direktori `public_html` (atau subdomain Anda) via File Manager cPanel.
2. **Buat Database MySQL**:
   - Masuk ke menu **MySQL Databases** di cPanel.
   - Buat database baru beserta user dan password-nya, lalu berikan hak akses (*All Privileges*).
3. **Impor Database**:
   - Masuk ke **phpMyAdmin** di cPanel.
   - Pilih database yang baru dibuat, lalu impor berkas `database.sql`.
4. **Sesuaikan Konfigurasi (.env)**:
   - Buat atau sunting berkas `.env` di root hosting (bisa menyalin dari `.env.example`) dan masukkan nama database, user, serta password hosting Anda. Berkas ini tidak akan tertimpa saat update aplikasi berikutnya.
5. **Set Izin Folder Upload**:
   - Pastikan folder `uploads/` dan sub-direktorinya memiliki izin akses tulis (permission `755` atau `775`).

---

## 🛠️ Teknologi yang Digunakan

* **Bahasa Pemrograman**: [PHP 8+](https://www.php.net/) - Pendekatan *Native Architecture* yang ringan, cepat, dan mudah dipelihara tanpa ketergantungan library vendor yang berat.
* **Basis Data**: [MySQL / MariaDB](https://www.mysql.com/) - Relasional dengan relasi tabel *Foreign Key Cascade*.
* **Antarmuka Pengguna (UI)**: [Tailwind CSS](https://tailwindcss.com/) - Desain antarmuka modern, bersih, dan responsif untuk kenyamanan membaca.
* **Ikonografi**: [Font Awesome 6](https://fontawesome.com/) - Koleksi ikon informatif dan ramah pengguna.
* **Tipografi**: [Google Fonts (Plus Jakarta Sans)](https://fonts.google.com/specimen/Plus+Jakarta+Sans) - Tipografi modern yang nyaman dibaca di seluruh layar.

---

## 👥 Pengembang & Hak Cipta

* **Pengembang**: Tim Inovasi & Pengelola Literasi Desa Sejiram
* **Instansi**: Pemerintah Desa Sejiram, Kecamatan Tebas, Kabupaten Sambas
* **Lisensi**: Proyek ini dirilis di bawah lisensi [MIT License](LICENSE).

<p align="center">
  <sub>Membaca Hari Ini, Membangun Masa Depan Gemilang Desa Sejiram.</sub>
</p>
