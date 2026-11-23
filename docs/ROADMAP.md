# Roadmap NEURO ABSENSI

## Tahap 1 — Fondasi
- [x] Hubungkan repository dan inisialisasi README.
- [ ] Siapkan struktur CodeIgniter 4.
- [ ] Siapkan konfigurasi environment dan database.
- [ ] Login admin lokal.

## Tahap 2 — Master data
- [ ] Unit: KBIT, RAIT, SDIT, SMPIT, Yayasan.
- [ ] Data pegawai dan pemetaan PIN per mesin.
- [ ] Riwayat penempatan unit dengan tanggal efektif.
- [ ] Jadwal individu dan riwayat jadwal dengan tanggal efektif.
- [ ] Hari kerja dan toleransi keterlambatan.

## Tahap 3 — Impor fingerprint
- [ ] Pilih beberapa file TXT dalam satu proses.
- [ ] Identifikasi mesin berdasarkan konfigurasi sumber; jangan hanya mengandalkan nama file.
- [ ] Decode XOR 0xD7.
- [ ] Validasi kolom, tanggal, waktu, PIN, dan identitas mesin.
- [ ] Preview sebelum simpan.
- [ ] Simpan file asli dan hash impor.
- [ ] Idempotensi: cegah duplikasi berdasarkan mesin, PIN, dan waktu scan.
- [ ] Simpan catatan invalid/duplikat tanpa menghilangkan jejak audit.

## Tahap 4 — Rekap harian
- [ ] Terapkan aturan satu, dua, atau lebih dari dua scan.
- [ ] Gunakan penempatan unit dan jadwal berdasarkan tanggal scan.
- [ ] Hari tanpa scan dikecualikan.
- [ ] Perhitungan keterlambatan terhadap jadwal individu.

## Tahap 5 — Laporan
- [ ] Gunakan lima workbook template asli.
- [ ] Isi KBIT, RAIT, SDIT, SMPIT, dan Yayasan.
- [ ] Pertahankan layout dan sheet template.
- [ ] Arsipkan riwayat ekspor.

## Tahap 6 — Pengujian dan rilis lokal
- [ ] Regression test dengan pasangan log dan Excel SMPIT.
- [ ] Regression test dengan pasangan log dan Excel RAIT/KBIT.
- [ ] Uji impor ganda, tanggal invalid, PIN tak dikenal, dan perubahan jadwal/unit.
- [ ] Panduan pemasangan Windows/XAMPP.
