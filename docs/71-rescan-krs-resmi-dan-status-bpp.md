# 71 — Rescan KRS resmi dan status BPP pada alur TA

Pengguna melaporkan semua test passed setelah koreksi baseline JSON dan enam file sudah dipindahkan. Asisten melakukan rescan baca saja pada source kedua repo, lalu mencatat hasilnya di Markdown. Jumlah/lingkup test database pengguna tidak ditebak dari laporan singkat tersebut.

## 1. Hasil salinan checkpoint70

Kedua folder yang dibandingkan:

- `/home/xdranel/Code/Project/php-laravel/dadu`
- `/home/xdranel/Code/Project/secret/dadu-test`

| File | Hasil |
| --- | --- |
| `app/Support/OfficialKrsJsonNormalizer.php` | Identik, lint PHP lulus |
| `app/Services/OfficialKrsJsonImportService.php` | Identik, lint PHP lulus |
| `app/Http/Controllers/SyncController.php` | Identik, lint PHP lulus |
| `database/migrations/2026_11_06_053632_expand_student_course_class_for_official_krs.php` | Identik, lint PHP lulus; tepat satu migration bernama ini |
| `tests/Unit/OfficialKrsJsonNormalizerTest.php` | Identik,17 deklarasi test |
| `tests/Feature/OfficialKrsJsonImportTest.php` | Identik,34 deklarasi test |

Perbandingan SHA-256 seluruh file dalam `app`, `config`, `database`, `resources`, `routes`, dan `tests` menghasilkan **515 file identik**, tanpa file source yang hilang/berbeda antara kedua repo.

Koreksi test profil sudah tersalin: baseline memakai `$profile->fresh()->groups`, sehingga assertion ketat membandingkan dua hasil baca database. Diff controller terhadap baseline Git hanya berisi deteksi format resmi sebelum unwrap JSON serta helper `importOfficialKrs`; jalur impor lama dipertahankan.

## 2. Verifikasi yang dijalankan asisten

```bash
vendor/bin/pest --no-configuration tests/Unit/OfficialKrsJsonNormalizerTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php --colors=never
```

Hasil aktual: **64 passed,478 assertions**, tanpa bootstrap aplikasi Laravel atau akses database. Ketujuh belas unit KRS dijalankan dari file yang benar-benar sudah tersalin ke source, bukan snippet sementara.

Normalizer source aktual juga membaca ulang berkas KRS di `/home/xdranel/Downloads/data_dadu/rekap_igracias_2026-11-06 KRS.json`:4.277 mahasiswa,30.590 mata kuliah,239 nama kelas melebihi20 karakter, tanpa kegagalan normalisasi. Berkas dibaca saja; tidak ada impor database atau salinan data pribadi ke repo.

Asisten tidak menjalankan ulang34 feature test MySQL, migration pada database aplikasi, atau uji browser. Laporan test berhasil dari pengguna dicatat terpisah dari64 unit yang benar-benar dijalankan asisten. Keberadaan file migration juga belum membuktikan bahwa schema lokal/Docker sudah diterapkan.

## 3. Alur kode yang terkonfirmasi saat rescan

- Wrapper/menu/count divalidasi sebelum impor; field resmi bertingkat dibaca tanpa memasukkannya ke jalur datar lama.
- Tahun2627 dipetakan ke2026/2027; nested semester1 adalah term Ganjil. Semester studi Student dipertahankan dan periode harus sudah ada.
- Daftar lengkap terbaru menggantikan KRS pada mahasiswa/periode yang diwakili sumber. Pemangkasan berlangsung setelah penyimpanan item, dalam transaksi mahasiswa yang sama.
- Mahasiswa lain/periode lain dipertahankan; kegagalan baris membatalkan perubahan mahasiswa terkait. Daftar kosong tanpa konteks periode dilewati.
- Riwayat tanpa `academic_period_id` yang menimbulkan ketidakpastian tidak dipetakan secara otomatis. Kunci yang berbenturan dengan periode lain juga tidak ditimpa.
- Source tidak menulis StudentAcademicProfile atau snapshot pengajuan TA. Cache mahasiswa dibersihkan setelah transaksi berhasil.
- Migration memperlebar `course_class` menjadi255. Guard rollback mencegah pengecilan yang akan memotong nama kelas panjang.

## 4. BPP memengaruhi apa?

**Pada alur pendaftaran Sidang TA saat ini, BPP merupakan informasi dan tidak menjadi syarat pemblokir pengiriman.** Ini dikonfirmasi pada `SidangTaAdmissionPolicy::academic` dan `SidangTaDraftFinalizer`, bukan hanya berdasarkan label UI.

Lima syarat akademik yang diperiksa tetap:

1. Sidang tingkat2 lulus.
2. KRS TA sesuai pada satu periode akademik aktif.
3. SKPI Disetujui.
4. TAK minimal60.
5. SKS lulus minimal120.

Aturan bahasa tetap terpisah dan dokumen/scalar wajib tetap diperiksa saat kirim. BPP LUNAS/KREDIT atau periode pembayaran yang belum terkonfirmasi tidak ditambahkan ke daftar blocker tersebut.

Informasi periode BPP yang masih ditunggu berguna untuk ketepatan data tersimpan, riwayat pembayaran, dan tampilan status pada periode yang benar. Jadi pembacaan/penyimpanan BPP tetap mempunyai logika data, tetapi ketiadaan periode itu tidak menahan alur utama TA yang sekarang.

**Pembayaran wisuda adalah persyaratan berbeda:** `graduation_payment_proof` tetap merupakan dokumen wajib dan `graduation_payment_date` tetap harus diisi sesuai validasi. Penjelasan bahwa BPP bukan blocker tidak menghapus dua persyaratan wisuda tersebut.

Kesimpulan ini dibatasi pada pendaftaran Sidang TA yang sedang dikerjakan; tidak mengubah aturan pembayaran modul lain.

## 5. Langkah sesudah rescan

1. Jika belum dilakukan, terapkan migration pada database aplikasi lokal dan uji impor fixture69-01 dengan tipe KRS, sesuai70 tahapF. Expected2 mahasiswa diproses dan2 item disimpan untuk dua akun demo; uji ulang tidak menggandakan KRS.
2. Uji pembatalan mata kuliah TA lewat salinan JSON sintetis, lalu pulihkan fixture dasar. Pastikan mahasiswa/periode lain tidak berubah.
3. Jika hendak memasang kode ke Docker, commit/push source yang siap dipasang lalu ikuti64 bagian12, termasuk backup, migration, rebuild dan recreate. Tidak menggunakan migrate:fresh/seed atau hanya artisan up.
4. Periksa kesamaan kode KRS host/container setelah update. Pemeriksaan dua file profil lama saja belum memverifikasi bahwa class KRS baru ikut terpasang:

```bash
cd /opt/dadu-test
sha256sum app/Support/OfficialKrsJsonNormalizer.php app/Services/OfficialKrsJsonImportService.php
docker compose --env-file .env.docker exec -T --interactive=false --user www-data app \
  sha256sum app/Support/OfficialKrsJsonNormalizer.php app/Services/OfficialKrsJsonImportService.php
```

Setiap file harus tersedia dan hash host/container cocok. Setelah migration dan kode runtime sesuai, impor JSON demo dari browser dengan tipe KRS dan cek log processed/skipped serta halaman mahasiswa.

Pekerjaan berikutnya yang masih tercatat adalah penyelarasan panel persetujuan sidang, label bahasa, pembaca BPP/snapshot, dan pembaca data kampus modul terkait. Periode BPP resmi tetap menunggu informasi sumber dan tidak menghalangi pekerjaan yang aturan datanya sudah jelas.

## 6. Batas tindakan asisten

Selama rescan ini, asisten hanya menulis Markdown71, menautkannya dari70, dan memperbarui indeks fixture agar status dukungan tidak lagi tertinggal. Manifest source/deployment kedua repo dibandingkan sebelum/sesudah dan tidak berubah. Tidak ada migration, seed, impor, SSH, build, restart, commit, push, atau perubahan source oleh asisten.
