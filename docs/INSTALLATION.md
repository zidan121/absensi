# Instalasi lokal Windows / XAMPP

## Persyaratan
- Windows 10/11 64-bit
- PHP 8.2 atau lebih baru (extension intl, mbstring, mysqli, json, xml, zip, gd)
- Composer 2
- MySQL/MariaDB dari XAMPP
- Git

## Langkah
1. Clone repository: `git clone https://github.com/zidan121/absensi.git neuro-absensi`.
2. Masuk folder: `cd neuro-absensi`.
3. Install dependency: `composer install`.
4. Buat database MySQL bernama `neuro_absensi` dengan charset `utf8mb4`.
5. Salin `.env.example` menjadi `.env` lalu sesuaikan konfigurasi database dan `app.baseURL`.
6. Pastikan folder `writable/cache`, `writable/logs`, `writable/session`, dan `writable/uploads` dapat ditulis PHP.
7. Jalankan migrasi: `php spark migrate`.
8. Isi data mesin awal: `php spark db:seed InitialDataSeeder`.
9. Jalankan lokal: `php spark serve`, lalu buka `http://localhost:8080`.

## Akun awal pengembangan
- Username: `admin`
- Password sementara: `GantiPassword123!`

Segera ubah password setelah login. Akun awal ini hanya untuk instalasi lokal tahap pengembangan.

## Catatan
Ini adalah fondasi pengembangan, bukan rilis produksi. Sebelum dipakai untuk rekap resmi, decoder harus diuji lagi pada file asli, mapping PIN pegawai/unit harus diselesaikan, dan alur laporan harus diuji terhadap lima template Excel.
