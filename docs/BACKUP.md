# Backup & Pemulihan NEURO ABSENSI

## Tiga file terpisah

- `Backup-SMP-Fingerspot.sql`: salinan byte-for-byte dari arsip sumber SMPIT yang disimpan melalui menu aplikasi.
- `Backup-RA-KB-Fingerspot.sql`: salinan byte-for-byte dari arsip sumber RA&KB yang disimpan melalui menu aplikasi.
- `Backup-NEURO-ABSENSI-YYYYMMDD-HHMMSS.sql`: dump database aktif NEURO ABSENSI yang dibuat saat tombol unduh ditekan.

## Menyiapkan dua arsip Fingerspot

1. Buka menu **Backup & Pemulihan**.
2. Pada kartu Backup SMPIT, pilih file SQL asli SMPIT lalu tekan **Simpan arsip sumber**.
3. Pada kartu Backup RA&KB, pilih file SQL asli RA&KB lalu tekan **Simpan arsip sumber**.
4. Unduh masing-masing dari kartu yang sesuai. File tidak pernah digabung.

Jika arsip sudah tersimpan, aplikasi meminta konfirmasi sebelum file lama diganti.

## Backup database NEURO

Tekan **Unduh Backup NEURO ABSENSI**. Aplikasi membaca semua tabel database aktif, mengambil definisi tabel melalui `SHOW CREATE TABLE`, lalu mengekspor baris data dalam batch. Dump mencakup definisi tabel, indeks/constraint yang tercantum pada definisi tabel, serta data. Tidak ada perintah untuk menghapus atau membuat ulang database tujuan di dalam file.

## Batasan versi ini

- Backup SMPIT dan RA&KB merupakan arsip sumber asli yang disimpan dan diunduh kembali tanpa perubahan. Perubahan master pegawai dan scan di NEURO ABSENSI belum disinkronkan ke file Fingerspot.
- Kompatibilitas impor kembali ke Fingerspot Personnel belum diklaim sebelum dilakukan uji restore pada instalasi uji.
- Dump database mencakup tabel dasar. Objek server terpisah seperti stored procedure, event scheduler, dan trigger tidak termasuk dalam implementasi ini.
- Simpan hasil backup di lokasi lain (HDD eksternal/cloud tepercaya) karena file di komputer aplikasi bukan salinan cadangan jika perangkat rusak.
- Uji pemulihan pada database uji, bukan database aktif.

## Lokasi penyimpanan

- Arsip Fingerspot: `writable/backups/fingerspot/smp.sql` dan `writable/backups/fingerspot/rakb.sql`.
- Dump NEURO: `writable/backups/`.
