# 126 — Perapian tampilan, batas tanggal dan dokumentasi

## Perbaikan halaman

- Review Sidang TA: Terapkan, Reset dan Export Excel berada pada satu baris tindakan dalam kartu filter.
- Pelaksanaan Sidang TA: angka Total merah seperti Review; Export Excel hijau dengan ikon dan sebaris Terapkan/Reset.
- Upload plotting diberi kotak/input berkas yang konsisten, indikator wajib, ikon dan petunjuk XLSX maksimal10 MB/16 kolom/dua baris header. Kontrak impor tidak diubah.
- Kalender Tetapkan jadwal sidang mempunyai min/max dari tanggal Pelaksanaan yang aktif. Tanggal lampau dikeluarkan memakai WIB; pengaturan nonaktif/kedaluwarsa menonaktifkan input dan pencarian. Validasi server tetap menolak POST di luar jadwal.

## Hak akses Pelaksana

Aksi khusus **Lihat Pengajuan (Prodi Dosen)** berlaku untuk **Review Sidang TA dan Pelaksanaan Sidang TA**. Email akun harus cocok dengan satu Email Domain pada Data Dosen; prodi dosen membatasi data yang dibaca. Pembaca tidak mengimpor, mengekspor, mengubah review, jadwal maupun hasil. Permission **Review** memberikan kewenangan petugas, sehingga jangan ditambahkan jika tujuan akun hanya membaca.

## Dokumentasi

Root docs sekarang hanya [README indeks](../README.md). Arsip progres, catatan, kesimpulan dan panduan pemakaian dipisahkan. Ringkasan tersedia untuk setiap progres001–126; sub-checkpoint huruf digabung pada nomor utamanya. Alur admin/mahasiswa/dosen dari hasil125 dipindahkan ke [panduan Pelaksanaan](../panduan-pemakaian/pelaksanaan-sidang-ta.md), sedangkan hasil125 tetap menyimpan perubahan dan bukti teknis.

20 artefak lama dikeluarkan:1 SH,6 TXT,10 JSON laporan,3 patch. Bukti penting diringkas pada [catatan verifikasi](../catatan/bukti-verifikasi-historis.md). Backup mentah: private-import/126-docs-before/. Helper salin112 dipensiunkan secara eksplisit supaya tidak membaca manifest yang sudah dibuang. JSON fixture, sumber privat dan konfigurasi pemulihan VPS dipertahankan; README fixture mengikuti tautan baru.

## File kode yang disamakan ke dadu

- `resources/views/sidang_ta_registrations/admin/index.blade.php`
- `resources/views/sidang_ta_execution/admin/index.blade.php`
- `resources/views/sidang_ta_execution/admin/show.blade.php`
- `tests/Feature/SidangTaExecutionPagesTest.php`

Backup source sebelum penyalinan: private-import/126-source-before/. Perubahan `.gitignore`, `package-lock.json`, `phpunit.xml` source yang sudah ada dipertahankan. Source tidak dibuatkan kembali docs/docker. Referensi salin ulang: [panduan penyelarasan](../panduan-pemakaian/penyelarasan-file.md).

## Verifikasi

Paket halaman/akses/kalender: **47 tes lulus, 344 assertion** (26,47 detik), hanya pada dadu_testing. Setelah penentuan WIB dibuat eksplisit, paket kalender/fixture kembali lulus: **9 tes, 280 assertion**, 19,00 detik (termasuk tujuh tes halaman yang sudah tercakup paket 47). Build akhir berhasil pada **dadu-test dan dadu**, masing-masing 1,92 dan 2,09 detik.

Audit akhir: **1.171 tautan lokal dan 55 anchor diperiksa, nol rusak**; 34 berkas fixture/data/config/ZIP yang dipertahankan identik dengan hash sebelum penataan. Tiga view dan satu tes cocok pada kedua folder. `git diff --check` bersih di kedua repo; syntax tes PHP dan shell helper lulus. Helper usang diuji berhenti terkendali dengan exit64 tanpa membaca manifest atau mengubah berkas.

Struktur akhir: 320 berkas dokumentasi (286 Markdown + 34 data/config), root hanya README. Pertambahan MD berasal terutama dari 126 kesimpulan singkat dan panduan/indeks terpisah, sedangkan 20 artefak lama dihapus dari docs. Log/JUnit sesi126 disimpan privat; tidak dibuat laporan JSON/TXT baru dalam docs. Suite penuh tidak diulang untuk penataan dokumen/tampilan ini; hasil penuh125 tetap 1.275 lulus/45 kegagalan baseline/2 dilewati, tanpa klaim seluruh suite hijau.

Tidak ada migration baru pada126. Tidak mengubah data aplikasi, melakukan commit/push atau deploy. Untuk update lokal/Docker/VPS gunakan [panduan rilis](../panduan-pemakaian/update-lokal-docker-vps.md).

Contoh **JSON kehadiran masih ditunggu**. Pertanyaan lain: [catatan keputusan](../catatan/keputusan-dan-konfirmasi.md).
