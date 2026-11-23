# Format impor Revo TXT

## Sumber V1
- SMPIT / Revo-16B: `GLogData.txt`
- RAIT & KBIT / Revo-185BNC: `GLogData_1.txt`

## Transformasi byte
Untuk setiap byte b, hasil byte = b XOR 0xD7. Hasil dekode kemudian diurai sebagai teks dengan delimiter yang ditemukan pada file sumber.

## Syarat implementasi
1. Baca sebagai byte, jangan memakai konversi encoding sebelum XOR.
2. Jangan memodifikasi file sumber.
3. Validasi header/kolom dan jumlah field setiap baris.
4. Validasi PIN sebagai string (jangan hilangkan nol di depan).
5. Validasi tanggal/waktu secara eksplisit.
6. Pertahankan nilai mode verifikasi dan mode IN/OUT dari sumber.
7. Tolak atau karantina baris rusak; simpan alasan dan nomor baris.
8. Simpan identitas mesin/sumber yang dipilih saat impor dan hash file.
9. Nama file saja bukan bukti mesin: pengguna harus memilih sumber atau aplikasi harus cocokkan fingerprint file ke perangkat yang terdaftar.
10. Lakukan deduplikasi berdasarkan device_id + PIN + timestamp + informasi pembeda yang sesuai struktur sumber.
