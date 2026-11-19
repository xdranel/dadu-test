# 128 — Hapus klasifikasi TA, pertahankan status ruang

Keputusan pengguna: seluruh master XLSX adalah ruang kelas. Hapus penandaan manual/kolom Klasifikasi TA. **Jangan mengubah status Dapat Dipakai/Tidak Dapat Dipakai**, room ID, alias, jadwal, booking atau riwayat.

- [x] Tambahkan tes gagal: semua master aktif menjadi kandidat tanpa klasifikasi, master nonaktif tetap dikecualikan, CRUD tidak memiliki input/kolom klasifikasi.
- [x] Hapus ketergantungan is_classroom pada model/request/CRUD/sinkronisasi bantuan, pencarian admin+legacy, koreksi, daftar pilihan dan impor plotting. Cakupan semester/alias/bentrok tetap wajib.
- [x] Migration baru128 menghapus kolom/index saja; migration lama110003 tetap utuh. Down mengembalikan flag kompatibilitas defaulttrue tanpa menyentuh status. Tes nativeMySQL membuktikan metadata ruang dan riwayat tidak berubah. Sesuaikan fixture upgrade berurutan128→124→110.
- [x] Tes lama yang menguji klasifikasi diselaraskan ke kontrak status; fixture umum tidak mengubah status asli untuk mengakali pencarian. Skenario penolakan/race boleh menyiapkan room uji nonaktif secara eksplisit.
- [x] Regresi, suite penuh dibandingbaseline45, rescan/copy terarah ke dadu, panduan operator maintenance+backup+migrate biasa pada imagebaru. Tidak menjalankan perubahan schema/live data VPS dalam sesi ini atau reset database aplikasi.

Masalah alias/source yang ditemukan127 terpisah dan tetap perlu penyelesaian identitas ruang. Penghapusan klasifikasi tidak menganggap sumber semester kosong menjadi lengkap.

Catatan data di luar scope: lab/aula pada JSON yang tidak ada di master XLSX tidak boleh otomatis ditambahkan sebagai kelas hanya untuk melewati pemeriksaan alias. Semua master sekarang berarti kelas; pemetaan atau penanganan label di luar katalog ini perlu konfirmasi tersendiri. Importer tetap menolak sumber yang belum pasti.

## Hasil akhir

Semua pekerjaan implementasi/verifikasi/copy selesai:26 file cocok di kedua folder, migration lama tidak diubah. NativeMySQL migration3 tes/30 assertion lulus; suite penuh 1.287 pass/45 baselinefail/2 skip tanpa tambahan; kasus XLSX asli1 tes/10 assertion tetap 46 aktif/14 nonaktif. Tidak ada perubahan database aplikasi, VPS atau production. Detail ada pada [hasil128](../arsip-progres/128-hasil-penghapusan-klasifikasi-ta.md).
