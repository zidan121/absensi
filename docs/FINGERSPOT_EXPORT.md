# Ekspor SQL ke Fingerspot Personnel

Generator membuat **SQL incremental dari data log NEURO ABSENSI terbaru** untuk dimasukkan ke database Fingerspot Personnel yang sudah ada.

## Mode aman (log saja)

- File hasil hanya menjalankan `INSERT IGNORE INTO att_log`.
- Tidak ada `UPDATE` atau `INSERT` ke tabel `pegawai`.
- Tidak mengubah PIN, NIP, nama, status pegawai, tanggal mulai kerja/resign, pembagian, jadwal, perangkat, atau pengaturan Fingerspot.
- Serial mesin diverifikasi dari arsip SQL Fingerspot sumber yang sudah disimpan di menu Backup & Pemulihan.
- Generator berhenti jika arsip sumber tidak menunjukkan tepat satu serial mesin.
- Scan diambil dari `attendance_logs` NEURO untuk perangkat yang dipilih, dan hanya PIN yang terdaftar unik pada perangkat itu yang disertakan.
- PIN ganda/ambigu dan tanggal scan yang tidak valid dilewati serta dihitung di laporan.
- Baris SQL dibuat dalam kelompok agar file lebih mudah diproses.
- `INSERT IGNORE` menggunakan primary key Fingerspot `(sn, scan_date, pin)`, sehingga scan yang sudah ada tidak dimasukkan ulang.

## Cara menggunakan

1. Simpan arsip backup SQL asli Fingerspot SMPIT pada menu **Backup & Pemulihan**.
2. Pastikan data log dan PIN pegawai SMPIT sudah masuk ke NEURO ABSENSI pada perangkat SMPIT.
3. Buka **Generator SQL Fingerspot** lalu pilih **Buat SQL Impor SMPIT**.
4. Periksa serial mesin, jumlah scan siap diimpor, dan jumlah scan yang dilewati.
5. Unduh file SQL.
6. Buat backup database Fingerspot sebelum impor.
7. Impor file incremental ke database Fingerspot SMPIT yang sudah ada (database `fin_pro`).

## Penting

- File yang dihasilkan **bukan backup penuh** dan bukan file untuk menu **Restore database** di Fingerspot Personnel.
- File tidak membuat tabel dan tidak boleh diimpor ke database NEURO ABSENSI.
- Impor tetap dilakukan ke database Fingerspot yang sudah ada. Koneksi langsung dari NEURO ke MySQL Fingerspot tidak dilakukan oleh generator ini.
- Arsip SQL sumber hanya dipakai untuk memverifikasi serial mesin; isinya tidak dieksekusi.
