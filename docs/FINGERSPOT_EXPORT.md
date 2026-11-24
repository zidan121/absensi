# Generator SQL Fingerspot

Generator menghasilkan skrip merge untuk database Fingerspot Personnel yang sudah ada.

- Hanya tabel pegawai dan att_log yang disentuh. Tabel konfigurasi, jadwal, pembagian, dan perangkat tidak dihapus atau diganti.
- Pegawai lama dicocokkan berdasarkan PIN pada arsip perangkat dan diperbarui dengan pegawai_id yang sudah ada.
- Pegawai baru mendapat pegawai_id setelah ID maksimum pada arsip sumber. Pemetaan pembagian1/2/3 harus lengkap atau dapat diinferensikan secara tunggal dari pemetaan pegawai lain pada unit yang sama.
- PIN kosong, PIN ganda, dan pembagian yang belum jelas masuk laporan dan tidak diekspor.
- Riwayat scan dimasukkan dengan INSERT IGNORE memakai primary key Fingerspot (sn, scan_date, pin), sehingga scan duplikat dilewati.
- Scan untuk PIN yang tidak masuk master hasil pemetaan dilewati.

Sebelum impor, backup database Fingerspot tujuan dan uji skrip pada salinan database. Periksa laporan pemetaan dan serial mesin. File ini bukan dump penuh untuk membuat instalasi Fingerspot baru.
