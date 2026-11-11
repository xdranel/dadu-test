# 98 — Penyederhanaan impor KRS menjadi format B dan C

## Arahan yang diterapkan

Pengguna sudah menyetujui penghapusan kolom semester studi pada pratinjau, dengan data internal tetap digunakan. Mode impor format lama dihapus karena sumber asli sudah dikonfirmasi B/C. Pada IT Panel → Sinkronisasi, kartu Format Data JSON dipindah ke bawah kartu impor pada kolom kiri, Status Data tetap di kanan, dan bantuan KRS memuat dua contoh beserta penjelasannya.

## Rancangan dan batas perubahan

- Controller memprioritaskan deteksi C; seluruh unggahan KRS datar lainnya memakai pratinjau Term tanpa pilihan mode. Flag lama `study` ditolak. Flag `term` masih diterima untuk request lama yang sudah benar, tetapi tidak diperlukan pada form baru.
- Hapus importer KRS lama dan helper periode yang hanya dipakai olehnya; KRS tidak lagi dapat masuk melalui fallback semester studi atau auto-create mahasiswa/master lama.
- B tetap menambah/memperbarui, mempertahankan MK yang tidak disertakan, dan menerapkan sesudah pratinjau. C tetap menyelaraskan daftar lengkap mahasiswa/periode dan dapat menghapus MK yang tidak tercantum. Tidak mengubah aturan C atau pemaknaan field sumber.
- Semester studi internal tetap berasal dari profil/record KRS; hilangkan kolom dan penjelasan semester studi dari pratinjau operator.
- Dua contoh sintetis: B memakai `tahun_akademik: "2026/2027"`, `semester: 1`, SKS null; C memakai `tahun_ajaran: "2627"`, `semester: "1"`, serta `krs_mata_kuliah`. Tampilkan penjelasan yang membedakan tambah/perbarui dan daftar lengkap.
- Tidak mengubah database aplikasi, schema, SSO, periode TA, ataupun data historis. Tidak menjalankan commit/push/deploy. Reuse worktree pengujian yang tersedia; database dadu_testing digunakan serial.

## Rencana pelaksanaan

- [x] Simpan guard hash source/development/worktree, lalu tulis tes RED default B, penolakan lama, contoh B/C, dan posisi kartu.
- [x] Terapkan routing importer B/C, hapus fallback lama, sederhanakan pratinjau, pindahkan kartu dan ganti bantuan.
- [x] Sesuaikan tes kompatibilitas: mode lama sekarang ditolak; uji record historis tetap dapat diperbarui lewat importer resmi.
- [x] Jalankan gate terkait, review independen, suite penuh dibanding kegagalan baseline yang sudah tercatat, lint/build/JS dan contoh JSON.
- [x] Perbarui dokumentasi 95/97, tulis hasil dan command copy pada 98, salin berkas lingkup ke kedua folder dengan backup, verifikasi hash dan route/berkas lain tetap terjaga.

## Fokus pemeriksaan

Default tanpa flag harus aman dan tidak langsung menulis B; parameter study tidak boleh membuka fallback; nilai 9/13 pada KRS tidak boleh dipakai sebagai term; C harus tetap mengganti daftar periode yang disertakan tanpa menghapus periode lain; card bantuan harus tetap berada di dalam scope Alpine pilihan jenis impor setelah pindah kolom.

## Cara memakai setelah update

1. Pilih **KRS Mahasiswa (krs)** pada IT Panel → Sinkronisasi. Dropdown format lama/Term sudah tidak ada.
2. Unggah B jika yang diperlukan adalah tambahan/perbaruan MK. Sistem menampilkan pratinjau; kolom yang terlihat: Baris, NIM, Mata Kuliah, Periode, Tindakan. Tinjau lalu konfirmasi penyimpanan.
3. Unggah C jika ekspornya berupa daftar resmi lengkap `krs_mata_kuliah`. Sistem mendeteksinya otomatis dan menyelaraskan daftar mahasiswa/periode yang disertakan, seperti perilaku sebelumnya. Ini dapat menghapus MK lama yang absen dari daftar baru.
4. Baca dua contoh pada kartu Format Data JSON di bawah kartu impor sebelah kiri. Status Data tetap di kanan. Contoh menggunakan identitas sintetis; untuk impor aplikasi gunakan sumber sebenarnya.
5. Kedua kontrak tetap membutuhkan master mahasiswa dan periode tahun+term yang sudah terdaftar. Periode tidak harus aktif untuk menyimpan riwayat, tetapi harus menjadi acuan aktif jika KRS hendak dianggap aktif pada pemeriksaan persyaratan.

Semester studi tidak muncul pada kolom pratinjau maupun keterangan perbaikan tautan. Nilainya tetap dipakai oleh sistem; menghilangkan kolom tidak menghapusnya dari record KRS/profil.

## Fixture dan pengujian skenario dummy

File lama `02-krs-ta2-dummy.json` dan `67-02-krs-akun-demo.json` dipertahankan sebagai arsip tanpa diubah. Keduanya memakai kontrak semester studi yang sudah dipensiunkan; petunjuk lama pada dokumen historis tidak lagi menjadi alur unggahan saat ini.

Pengganti yang disediakan:

| File | Pemakaian |
|---|---|
| `docs/fixtures/sidang-ta/98-02-krs-ta2-term-dummy.json` | B untuk empat mahasiswa sintetis pada skenario dummy TA; gunakan master/profil 01 dan 03 yang sesuai |
| `docs/fixtures/sidang-ta/98-03-krs-akun-demo-term.json` | B untuk akun demo Krisna/Dummy002 historis; gunakan master/profil 67 yang sesuai |
| `docs/fixtures/sidang-ta/69-01-krs-format-resmi-akun-demo.json` | C yang sudah tersedia; daftar lengkap per mahasiswa/periode |

Fixture privat 75-02/75-02b yang memakai C tetap mengikuti daftar lengkap; tidak diubah oleh pekerjaan ini. Dua fixture B baru tidak membuat akun atau mahasiswa otomatis. Pengujian endpoint dummy sekarang melalui preview/apply; skenario periode belum terdaftar mengharapkan tidak ada KRS ditulis sampai periode tersedia, bukan menyimpan tautan kosong lewat importer lama.

## Review dan pemeriksaan

Review independen statis pada controller, dua view, contoh JSON, tes, serta fixture tidak menemukan masalah material. Batas review: tidak menjalankan DB atau browser; pengujian dijalankan primer secara serial pada dadu_testing.

Gate setelah penyesuaian fixture awal: **65 lulus, 421 assertion**. Enam tes perubahan kontrak awal telah diamati gagal sebelum implementasi. Suite penuh pertama menemukan satu tes demo lain yang masih mengharapkan impor KRS langsung dari jalur lama; tes tersebut diselaraskan menjadi B dengan konfirmasi pratinjau. Suite lengkap akhir pada MySQL dadu_testing, APP_TIMEZONE=UTC: **922 lulus, 43 gagal, 8.089 assertion**, 94,44 detik. Baseline HEAD ca58c6a dijalankan ulang dari snapshot terpisah: **917 lulus, 43 gagal, 8.039 assertion**, 92,45 detik. Seluruh 43 judul kegagalan dan lokasi test sama persis, berurutan; tidak ada kegagalan baru. Suite penuh tetap merah karena kegagalan baseline pada auth, exam, graduation, public document, queue, sync lama, dan satu fixture zona waktu riwayat TA.

Setelah integrasi ke development asli, gate enam file terkait kembali lulus: **97 tes, 709 assertion**, 16,58 detik. Build pada development juga berhasil (1,37 detik). Seluruh **13 berkas lingkup** sama di kedua folder; `git diff --check` bersih. Backup integrasi: `/tmp/dadu98-integration-backup-0e7ew1o0`. Perbedaan branch dan dokumen/berkas di luar lingkup dipertahankan.

`npm run build` berhasil (60 modul). Tes JavaScript **22 lulus**. Lint pada **delapan** berkas PHP/Blade berubah berhasil, termasuk tes demo tambahan setelah penyesuaian. Tidak ada perubahan dependency.

Log sesi sementara: `/tmp/dadu98-red.log`, `/tmp/dadu98-green.log`, `/tmp/dadu98-focused.log`, `/tmp/dadu98-full.log`, `/tmp/dadu98-full-final.log`, `/tmp/dadu98-baseline-full.log`, `/tmp/dadu98-build.log`, `/tmp/dadu98-js.log`, `/tmp/dadu98-integrated-tests.log`, dan `/tmp/dadu98-integrated-build.log`.

## Memasang perubahan

Tidak ada migrasi baru dan tidak perlu mengimpor ulang data aplikasi hanya karena penghapusan kolom/pemindahan kartu. Data yang memang sebelumnya dilewati tetap perlu diselesaikan sesuai catatan impor masing-masing.

Lokal development: setelah kode disalin, jalankan `php artisan view:clear` jika memakai cache view, dan `npm run build` untuk stylesheet yang mencakup tata letak terbaru. Docker dadu-test: sesudah commit/push dan kode tersedia pada branch server, gunakan 78 bagian 4 — Update Docker. Membangun ulang image dan membuat ulang container diperlukan agar runtime menerima kode baru; tidak perlu reset database.

Kode lingkup disamakan di kedua folder laptop dengan backup. Command berikut dapat mengulang penyalinan setelah perubahan di development:

```bash
bash <<'DADU_COPY98'
set -euo pipefail
umask 022
SRC=/home/xdranel/Code/Project/php-laravel/dadu
DST=/home/xdranel/Code/Project/secret/dadu-test
files=(
  app/Http/Controllers/SyncController.php
  resources/views/sync/index.blade.php
  resources/views/sync/krs-term-preview.blade.php
  tests/Feature/FlatTermKrsImportTest.php
  tests/Feature/OfficialKrsJsonImportTest.php
  tests/Feature/SidangTaDummyImportTest.php
  tests/Feature/SidangTaOfficialAdmissionTest.php
  tests/Feature/SyncKrsFormatsTest.php
  docs/fixtures/sidang-ta/98-02-krs-ta2-term-dummy.json
  docs/fixtures/sidang-ta/98-03-krs-akun-demo-term.json
  docs/95-panduan-periode-akademik-dan-memilih-format-krs.md
  docs/97-hasil-penyederhanaan-kolom-krs-dan-konfirmasi-format.md
  docs/98-penyederhanaan-impor-krs-b-c.md
)
for file in "${files[@]}"; do test -f "$SRC/$file"; done
backup=$(mktemp -d /tmp/dadu98-copy.XXXXXX)
cd "$DST"
for file in "${files[@]}"; do
  if test -f "$file"; then cp --parents -- "$file" "$backup/"; fi
done
printf 'Backup: %s\n' "$backup"
cd "$SRC"
cp --parents -- "${files[@]}" "$DST/"
for file in "${files[@]}"; do cmp -- "$SRC/$file" "$DST/$file"; done
git -C "$DST" diff --check
git -C "$DST" status --short
DADU_COPY98
```

Pekerjaan ini tidak mengubah Kelola Periode Sidang TA pada 96 ataupun memulai implementasi Form Yudisium pada 91. Contoh JSON Kehadiran masih ditunggu.
