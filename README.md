# NEURO ABSENSI

Aplikasi absensi lokal untuk Yayasan Bina Insani Grobogan.

## Cakupan versi 1

- **SMPIT:** Fingerspot Revo-16B, file `GLogData.txt`.
- **RAIT & KBIT:** Fingerspot Revo-185BNC, file `GLogData_1.txt`.
- **SDIT:** belum termasuk versi 1.

## Teknologi

- PHP 8.2+
- CodeIgniter 4
- MySQL / MariaDB
- Bootstrap 5
- PhpSpreadsheet

## Alur kerja

1. Salin file log dari mesin fingerprint ke flashdisk.
2. Buka NEURO ABSENSI pada komputer lokal.
3. Impor satu atau beberapa file TXT.
4. Aplikasi mendekode file, memvalidasi format, dan menyimpan log mentah.
5. Sistem mencegah impor scan yang sama dua kali.
6. Sistem menghitung rekap sesuai jadwal serta penempatan pegawai yang berlaku.
7. Ekspor laporan sesuai template Excel untuk KBIT, RAIT, SDIT, SMPIT, dan Yayasan.

> Catatan: meskipun mesin SDIT belum didukung, format laporan SDIT tetap disiapkan. Data SDIT tidak akan diisi dari sumber mesin yang belum didukung.

## Aturan pembacaan log Revo

Decoder TXT versi pertama menggunakan operasi XOR byte `0xD7`, lalu mengurai konten teks hasil dekode. Decoder harus memvalidasi struktur dan format tanggal sebelum menyimpan data. File asli tidak diubah.

## Aturan absensi

- Hari tanpa scan tidak dimasukkan ke perhitungan.
- Satu scan: hadir, sebagai jam masuk; jam pulang kosong.
- Dua scan: scan paling awal sebagai masuk dan paling akhir sebagai pulang.
- Lebih dari dua scan: scan paling awal dan paling akhir digunakan; scan antara tetap disimpan.
- Keterlambatan memakai jadwal individu yang berlaku pada tanggal tersebut.
- Perubahan unit dan jadwal disimpan menggunakan tanggal efektif agar laporan historis tetap benar.

## Pengembangan

Lihat [docs/ROADMAP.md](docs/ROADMAP.md) dan [docs/DECISIONS.md](docs/DECISIONS.md).


## Impor backup Fingerspot Personnel

Menu **Impor Backup SQL** menyediakan dua proses terpisah:

1. Master pegawai: membaca tabel `pegawai` dari backup SQL dan menyimpan PIN, nama, status, pembagian, serta tanggal kerja. Sumber SMPIT dan gabungan RAIT/KBIT dipilih secara eksplisit.
2. Riwayat scan: membaca tabel `att_log` dari backup SQL, lalu mengaitkan scan dengan perangkat yang dipilih. Scan duplikat dilewati.

File SQL tidak pernah dieksekusi ke database aplikasi. Selalu periksa sumber perangkat sebelum mengonfirmasi impor riwayat scan. Master RAIT/KBIT pada tahap ini disimpan sebagai kelompok gabungan; pembagian internal tetap dipertahankan sebagai ID sumber dan belum otomatis dipetakan ke RAIT atau KBIT.
