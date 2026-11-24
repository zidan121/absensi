# Generator SQL Fingerspot

Generator menghasilkan skrip merge untuk database Fingerspot Personnel yang sudah ada.

- Hanya tabel pegawai dan att_log yang disentuh. Tabel konfigurasi, jadwal, pembagian, dan perangkat tidak dihapus atau diganti.
- Pegawai lama dicocokkan berdasarkan PIN pada arsip perangkat dan diperbarui menggunakan pegawai_id yang sudah ada.
- Pegawai baru hanya disertakan bila pemetaan pembagian1/2/3 tersedia atau dapat diinferensikan secara tunggal dari pemetaan pegawai lain pada unit yang sama.
- ID pegawai baru dihitung saat SQL dijalankan dari MAX(pegawai_id) pada database tujuan, bukan ditebak dari arsip lama. Insert hanya dilakukan bila PIN belum ada pada database tujuan. Baris baru yang sudah pernah disinkronkan dapat diperbarui lagi berdasarkan PIN.
- PIN kosong, PIN ganda, dan pembagian yang belum jelas masuk laporan dan tidak diekspor sebagai master.
- Riwayat scan dimasukkan dengan INSERT IGNORE memakai primary key Fingerspot (sn, scan_date, pin), sehingga scan duplikat dilewati.
- Scan untuk PIN yang tidak termasuk master pegawai yang berhasil dipetakan dilewati.
- Bila data scan berisi lebih dari satu serial mesin, seluruh scan diblokir dari file SQL sampai sumbernya diperiksa.

Sebelum impor, backup database Fingerspot tujuan dan uji skrip pada salinan database. Periksa laporan pemetaan dan serial mesin. File ini bukan dump penuh untuk membuat instalasi Fingerspot baru.
