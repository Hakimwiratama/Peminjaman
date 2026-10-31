# SIPINTAR

SIPINTAR adalah aplikasi multi-institusi untuk peminjaman barang, buku, alat laboratorium, dan ruangan. Teknologi yang digunakan PHP 8.1+, MySQL, PDO, HTML, CSS, dan JavaScript.

## Menjalankan dengan Laragon

1. Letakkan folder proyek di `C:\laragon\www\pinjaman` (atau document root Laragon Anda).
2. Jalankan Apache dan MySQL dari Laragon.
3. Buat database `sipintar`, lalu impor `database/sipintar.sql` dan `database/regions.sql` secara berurutan untuk instalasi baru.
4. Salin `config/config.example.php` menjadi `config/config.php`, sesuaikan koneksi lokal, atau set environment `SIPINTAR_DB_HOST`, `SIPINTAR_DB_PORT`, `SIPINTAR_DB_NAME`, `SIPINTAR_DB_USER`, dan `SIPINTAR_DB_PASSWORD`. File `config/config.php` tidak disertakan di Git agar kredensial lokal tidak terunggah.
5. Dari terminal pada folder proyek, jalankan `php scripts/seed.php` untuk membuat akun demo dan data contoh.
6. Buka `http://localhost/pinjaman/` (atau domain virtual host Laragon yang diarahkan ke folder proyek).

Untuk instalasi SIPINTAR lama, cadangkan database lalu jalankan `database/migrate_multitenant.sql` bila belum pernah dijalankan, kemudian `database/migrate_regions.sql` dan `database/regions.sql` sebelum menjalankan seed. Setiap migrasi hanya dijalankan **satu kali**. Jangan jalankan migrasi tersebut pada database baru.

Ekstensi PHP yang dibutuhkan: `pdo_mysql`, `fileinfo`, dan `mbstring`. Folder `uploads/` perlu dapat ditulisi oleh Apache bila fitur foto inventaris digunakan.

## Akun demo

| Peran | Email | Kata sandi |
|---|---|---|
| Admin pusat | `admin@sipintar.local` | `SIPINTAR123!` |
| Admin institusi demo | `sekolah@sipintar.local` | `SIPINTAR123!` |
| Petugas demo | `petugas@sipintar.local` | `SIPINTAR123!` |
| Siswa/mahasiswa demo | `pengguna@sipintar.local` | `SIPINTAR123!` |

Kredensial tersebut hanya untuk pengujian lokal; ganti sebelum aplikasi digunakan sungguhan. Pengguna baru memilih **Pihak sekolah/kampus** untuk mendaftarkan nama, daerah, alamat, akun admin, dan layanan Barang/Buku/Ruangan, atau **Siswa/mahasiswa** untuk memilih daerah lalu mendaftar pada institusi aktif di kabupaten/kota tersebut. Portal institusi dibuka setelah admin pusat menyetujui pendaftaran. Admin SIPINTAR perlu mengatur daerah institusi lama melalui halaman **Kelola institusi** agar dapat ditemukan melalui filter wilayah.

Pilihan wilayah memuat 38 provinsi dan 514 kabupaten/kota berdasarkan dataset kode wilayah Kepmendagri 2025 dari [cahyadsn/wilayah](https://github.com/cahyadsn/wilayah); lisensi MIT tercantum di `database/region_data_license.txt`. Pencarian nama sekolah mengakses direktori Dapodik publik milik [dataset-sekolah-indonesia](https://github.com/prodhokter/dataset-sekolah-indonesia) melalui API pihak ketiga. Pencarian memerlukan koneksi internet; nama sekolah yang tidak ditemukan dapat dimasukkan manual dan akan menunggu verifikasi admin. Direktori kampus nasional yang resmi dan berlisensi jelas belum tersedia untuk diimpor, sehingga nama perguruan tinggi diisi manual dan diverifikasi admin.

## Fitur yang tersedia

- Login, registrasi, logout, session cookie aman, CSRF, password hashing, dan pemeriksaan hak akses server-side.
- Dashboard dan menu berbeda untuk admin pusat, admin institusi, petugas, serta siswa/mahasiswa.
- Data anggota, katalog, kategori, transaksi, pengaturan, dan keluhan dipisahkan per institusi; institusi mengaktifkan layanan yang diperlukan.
- Katalog dengan pencarian dan pengelolaan empat kategori; data anggota mencakup NIS/NIM, kelas/program, dan kontak.
- Permohonan peminjaman, persetujuan/penolakan dengan alasan, penyerahan, pengajuan pengembalian, konfirmasi kondisi, dan notifikasi.
- Pemeriksaan stok dalam transaksi database ketika permohonan diajukan/disetujui; reservasi ruangan memeriksa benturan waktu dan mengunci baris ruangan.
- Keluhan siswa/mahasiswa dengan balasan dan status tindak lanjut, laporan status/kategori, cetak browser, ekspor CSV, profil, dan activity log.
- Pengaturan institusi, layanan aktif, durasi pinjaman maksimal, serta tarif denda keterlambatan.

## Catatan operasional

Database menggunakan InnoDB. Stok tersedia dikalkulasi dari stok katalog dikurangi peminjaman yang telah disetujui/aktif; permohonan menunggu tidak mengurangi stok dan akan divalidasi ulang saat petugas menyetujui. Jadwal ruangan yang menunggu persetujuan juga menahan slot waktu agar permohonan bertabrakan tidak diterima.
