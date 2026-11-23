# Keputusan Produk NEURO ABSENSI

## Ruang lingkup V1
Dua sumber TXT:
1. SMPIT — Revo-16B, file umum `GLogData.txt`.
2. RAIT & KBIT — Revo-185BNC, file umum `GLogData_1.txt`.

SDIT — Neo W-201BNC belum termasuk decoder V1. Format laporan SDIT tetap menjadi target ekspor, tetapi sistem tidak boleh mengarang atau mengisi data SDIT dari mesin lain.

## Decoder
File TXT Revo dibaca sebagai bytes, tiap byte di-XOR dengan `0xD7`, kemudian diparse sebagai teks terstruktur. Terapkan validasi ketat; jangan menyimpan baris yang tidak valid sebagai scan sah. File asli tidak dimodifikasi.

## Aturan scan harian
- Nol scan: tanggal dikecualikan dari perhitungan.
- Satu scan: hadir; jam masuk = scan tersebut, jam pulang kosong.
- Dua scan: paling awal = masuk, paling akhir = pulang.
- Lebih dari dua scan: paling awal dan paling akhir digunakan; seluruh scan perantara tetap tersimpan sebagai raw/extra.
- PIN dipetakan dengan kombinasi mesin dan PIN karena PIN bisa berbeda antarperangkat.
- Waktu scan tidak boleh dikoreksi diam-diam; data tanggal/jam tak valid masuk laporan pengecualian.

## Riwayat pegawai
Penempatan unit dan jadwal disimpan dengan rentang tanggal berlaku. Perubahan di tengah bulan tidak boleh mengubah perhitungan periode sebelumnya.

## Jadwal kerja
Jadwal dapat diatur per pegawai: jam mulai, jam selesai, ambang keterlambatan, hari kerja, tanggal efektif. Perlu fungsi salin jadwal dan perubahan massal yang tercatat.

## Laporan
Target lima laporan mengikuti template workbook yang diberikan pengguna: KBIT, RAIT, SDIT, SMPIT, Yayasan. Workbook asli dipertahankan; ekspor dilakukan ke file baru. SDIT menampilkan belum tersedia/ kosong sampai sumber datanya didukung.

## Keamanan dan integritas
- Jangan commit file log biometrik/absensi, workbook pegawai, kredensial, atau data pribadi ke GitHub.
- Simpan konfigurasi rahasia pada `.env` yang tidak di-commit.
- Catat impor, ekspor, perubahan master data, dan koreksi secara audit.
