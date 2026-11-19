# 126 — Perbaikan tampilan dan penataan docs

Urutan permintaan: perbaikan UI → jawab hak akses → rapikan docs.
- [x] Satukan Export Excel dengan Terapkan/Reset di Review dan Pelaksanaan; Total merah; ekspor Pelaksanaan hijau+ikon; upload XLSX bergaya input yang konsisten.
- [x] Jalankan tes halaman/akses yang sudah ada dan build; salin dua view ke source dengan backup/hash tanpa konfigurasi/staging.
- [x] Periksa middleware/scope permission view_program untuk kedua halaman dan jawab di chat.
- [x] Audit referensi docs dan simpan snapshot; pindahkan MD lama ke arsip-progres, rencana/catatan ke catatan, tulis kesimpulan setiap nomor progres dan pisahkan panduan-pemakaian. Fixture aktif dipertahankan.
- [x] Hapus artifact tidak aktif dari docs dengan backup privat, perbarui semua referensi repo dan tautan relatif; README indeks, audit brokenlink sebelum/sesudah.
- [x] Rescan akhir, bukti MD hasil126, tidak commit/push/deploy/migrate.

## Rancangan penataan yang digunakan

Dokumen bernomor lama dipertahankan pada arsip-progres; panduan murni02/64/95/96 berada langsung pada panduan-pemakaian. Sub-checkpoint huruf disimpulkan bersama nomor utamanya: satu kesimpulan untuk setiap progres001–126. Catatan bebas dan keputusan/konfirmasi berada pada catatan. Root docs hanya README indeks.

Artefak yang dibuang dari docs:1 script SH,6 daftar TXT,10 laporan JSON,3 patch sekali pakai. Ringkasan buktinya tersedia dalam bukti-verifikasi-historis.md, bukan dump JSON. File mentah dicadangkan pada private-import/126-docs-before/ dan tetap dapat ditelusuri melalui histori Git.10 blok perintah yang bergantung pada artefak lama dipensiunkan secara eksplisit. Helper Docker112 berhenti dengan pesan yang mengarahkan ke panduan salin terbaru.

JSON fixture yang dipakai importer/tes, sumber simulasi privat, ZIP privat dan empat konfigurasi pemulihan VPS tidak dihapus atau dipindahkan. Path fixture dipertahankan agar tes dan generator demo tetap bekerja. Markdown README fixture diperbarui tautannya dan diberi pengantar ke panduan terkini; nilai JSON/config/ZIP tetap identik.

## Tambahan permintaan saat pekerjaan berjalan

Kalender pada Tetapkan jadwal sidang dibatasi dengan min/max dari Pelaksanaan, menggunakan hari saat ini dalam WIB. Pencarian dinonaktifkan jika pengaturan belum aktif/berakhir. Hari pelaksanaan dan ketersediaan tetap diperiksa server. Kasus rentang/past-date dan POST di luar rentang diuji melalui HTTP.

## Pengujian awal

Kasus kalender baru teramati gagal sebelum perbaikan. Paket halaman/akses terakhir:47 tes lulus,344 assertion; hasil final dan audit tautan ada pada arsip126. Progres125 tetap memiliki45 kegagalan baseline suite penuh yang sudah dicatat; perapian126 tidak mengklaim memperbaiki modul lain.

Tidak menjalankan migration/reset/seed pada DB aplikasi, commit, push, atau deploy. Doc/catatan baru hanya dibuat di dadu-test; dadu menerima tiga view dan satu tes terkait.

## Hasil pemeriksaan akhir

- Paket UI/akses:47 lulus, 344 assertion. Pemeriksaan setelah WIB eksplisit dan fixture: 9 lulus, 280 assertion (beririsan tujuh tes halaman dengan paket sebelumnya).
- Build Vite target/source berhasil. Empat file kode/tes sama SHA-256; kedua index Git tidak diubah.
- 1.171 tautan path lokal dan 55 anchor lolos; satu link README lama yang menunjuk file .env yang tidak ada diperjelas menjadi file .env.example sebagai contoh konfigurasi.
- 34 data/JSON/config/ZIP fixture tetap sama hash-nya. Path fixture tetap sama. Data privat tetap di-ignore.
- 320 file docs akhir: 286 Markdown dan 34 data/config/ZIP. 126 halaman kesimpulan dan indeks dapat dibaca dari README root.
- Review dokumentasi menemukan satu prosedur SCP/hash statis dari laptop lama terbawa ke panduan baru; bagian tersebut diganti diagnosis versi host/container dan rujukan ke panduan update VPS. Prosedur historis tetap ada di arsip75.
- Helper salin112:syntax shell valid; ketika dipanggil berhenti dengan exit64 dan arahan ke panduan baru. Tidak ada referensi runtime yang masih membaca 20artefak terhapus.

Backup dokumen asli: private-import/126-docs-before/. Pemetaan pemindahan dan daftar artefak terhapus disimpan privat pada private-import/126-docs-mapping.json. Nilai credential tidak ditulis dalam dokumen baru.
