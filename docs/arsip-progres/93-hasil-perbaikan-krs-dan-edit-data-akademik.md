# 93 — Perbaikan KRS dan edit data akademik mahasiswa

> Arsip progres: keputusan dan perintah di sini mencerminkan tahap saat ditulis. Untuk pemakaian terkini buka [panduan](../panduan-pemakaian/README.md); hasil singkat tersedia di [kesimpulan](../kesimpulan/README.md).

## Hasil dan penyebab

Perubahan ini mengerjakan prioritas KRS serta edit pada tab **Data Yudisium & Tugas Akhir**. Kebutuhan Form Yudisium dicatat pada [91](91-catatan-kebutuhan-form-yudisium.md), belum diimplementasikan. Keputusan dan rancangan ada pada [92](92-rancangan-perbaikan-krs-dan-edit-data-akademik.md).

File `rekap_igracias_format_krs_matakuliah.json` berisi **1.068 baris / 277 mahasiswa**, tahun 2026/2027, `semester: 1` berarti **Ganjil** sesuai konfirmasi pengguna, dan `sks: null`. Jalur impor datar sebelumnya memakai semester sebagai semester studi serta tidak menautkan periode akademik. KRS aktif juga memerlukan tepat satu periode akademik aktif. Jadi tulisan “Belum ditentukan” pada roster memang berkaitan dengan periode yang belum tertaut; menyimpan data KRS saja belum cukup.

Perubahan yang tersedia:

- **KRS Mahasiswa → Pengaturan Periode Akademik**: buat/edit periode dan aktifkan secara terpisah. Tanggal harus mengikuti kalender resmi kampus; aplikasi tidak menebak tanggal. Pembuatan selalu tidak aktif, aktivasi memilih tepat satu periode tanpa menghapus riwayat. Tahun/term yang sudah dirujuk tidak boleh diubah.
- Sinkronisasi KRS menyediakan pilihan arti semester: **semester studi** untuk jalur lama, atau **term akademik (Ganjil/Genap)** untuk file baru ini. Mode term membaca semester studi dari profil mahasiswa, menjaga SKS null, dan menggunakan periode tahun+term yang telah dibuat.
- Mode term menampilkan pratinjau sebelum menyimpan. Tautan KRS lama hanya diperbaiki jika kandidatnya tunggal dan NIM/kode/tahun/kelas serta semester lama sesuai sumber; ID lama dipertahankan. Kandidat ambigu, benturan kunci unik, atau data berubah sejak pratinjau ditolak/dilewati dengan catatan. Pratinjau berlaku 30 menit dan terikat pengguna/session. Unggahan ini bersifat menambah/memperbarui, tanpa menghapus MK yang tidak tercantum.
- Daftar dan detail roster membedakan periode akademik. `semester` studi 9 tetap tampil 9, terpisah dari term Ganjil.
- **History Nilai (KRS Aktif)** dihapus. Tersisa tiga bagian buka/tutup: KRS Aktif, Mata Kuliah Semua Semester, dan Histori Nilai.
- Tab **Data Yudisium & Tugas Akhir → Edit Data Yudisium & TA** menyediakan koreksi status sidang tingkat, detail TA, pembimbing/penguji, nilai TA, TAK, BASILA, SKS/list MK, BPP per periode, bayar wisuda, SKPI, serta persetujuan sidang iGracias. Hak akses `student_update` dan policy mahasiswa tetap diperiksa.
- Simpan manual memperbarui database secara atomik, menyelaraskan tabel akademik dan ringkasan profil resmi, serta mencatat pelaku/sebelum/sesudah/catatan pada audit. Berkas JSON tidak ditulis ulang. Form lama ditolak jika data telah berubah sejak dibuka. Snapshot Sidang TA yang sudah dikirim tidak diubah oleh editor.
- Impor JSON berikutnya **boleh memperbarui koreksi manual**. Jalur resmi dan format nested lama diselaraskan; impor nested parsial mempertahankan kelompok yang tidak disertakan bila profil resmi sudah ada. Status belum tersedia dipertahankan sebagai null, bukan otomatis tidak lulus. IPK/bahasa yang tidak diedit dan metadata lain dipertahankan.

Pada mode term baru, kode MK dibatasi huruf Latin, angka, titik, garis miring, underscore, dan minus untuk menghindari benturan identitas pada kolasi MySQL; seluruh kode pada file contoh memenuhi format ini. Nama MK/mahasiswa tetap dapat menggunakan Unicode. Tidak ada JSON pribadi yang ditambahkan ke repository oleh pekerjaan ini.

## Urutan menjalankan di lokal development

1. Pastikan kode terbaru sudah disalin (bagian salin di bawah).
2. Jalankan migrasi biasa berikut pada folder development. Tidak perlu `migrate:fresh` atau seed ulang.

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan migrate
npm run build
php artisan view:clear
```

Migrasi baru:

- `2026_11_10_000003_create_student_academic_data_edits_table.php`: tabel jejak koreksi.
- `2026_11_10_000004_allow_unknown_academic_statuses.php`: status sidang tingkat/BASILA dapat menyimpan null. Nilai lama tidak diubah. Rollback menolak jika masih ada null, supaya data belum diketahui tidak diam-diam diubah menjadi penolakan.

3. Pada **Data Akademik → KRS Mahasiswa → Pengaturan Periode Akademik**, buat **2026/2027 — Ganjil**, isi label dan tanggal awal/akhir berdasarkan kalender akademik resmi, kemudian **Aktifkan periode ini**. Jika periode sudah ada, gunakan yang sesuai; jangan membuat duplikat. Ini berbeda dari periode pendaftaran Sidang TA/Yudisium.
4. Pastikan data mahasiswa sudah diimpor dan semester studinya benar (misalnya 9). Pada **IT Panel → Sinkronisasi**, pilih impor KRS dan arti semester **term akademik (Ganjil/Genap)**, unggah `rekap_igracias_format_krs_matakuliah.json`.
5. Periksa pratinjau: tambah/perbarui/perbaiki tautan/dilewati; centang persetujuan, lalu **Simpan hasil tinjauan**. Bila kandidat ambigu, periksa sumber/riwayat dahulu. Aktivasi periode saja tidak memperbaiki tautan lama; impor ulang melalui mode term diperlukan.
6. Buka detail mahasiswa → Rencana Studi & Nilai dan roster MK; pastikan tahun/term serta KRS aktif sesuai. KRS periode tidak aktif tetap berada pada daftar semua semester.
7. Uji koreksi melalui tab Data Yudisium & Tugas Akhir, simpan, lalu buka ulang. Jika melakukan impor berikutnya, data sumber yang sama dapat mengganti koreksi tersebut sesuai keputusan pengguna.

## Docker dadu-test

Sesudah commit/push dan kode tersedia di branch yang dipakai server, gunakan [78 bagian 4 — Update Docker](78-hasil-implementasi-meeting-mahasiswa-petugas-it-panel.md#4-update-docker). Alur itu membangun image, menjalankan migrasi biasa, membuat ulang container, dan membuka maintenance kembali. Jangan hanya menyalin file host tanpa membangun serta membuat ulang container app: sebelumnya ini menyebabkan host dan runtime memakai kode berbeda.

Sesudah update, lakukan langkah pengaturan periode serta impor ulang pada aplikasi Docker seperti lokal (langkah 3–7). Asisten tidak menjalankan migrasi pada DB aplikasi, reset/seed, impor data aplikasi, commit/push, atau deployment. Production tetap menunggu koordinasi tim seperti permintaan sebelumnya.

## Hasil verifikasi

- Gate terfokus: **87 lulus, 815 assertion** (manual edit, detail mahasiswa, KRS term, pengaturan periode, impor profil resmi, impor dummy lama).
- Suite penuh pada MySQL `dadu_testing`, `APP_TIMEZONE=UTC`: **915 lulus, 43 gagal, 8.027 assertion**. Baseline kode sebelum perubahan (`fef6e09`): **878 lulus, 43 gagal, 7.767 assertion**. Seluruh 43 judul kegagalan beserta lokasi test sama persis dan berurutan; tidak ada kegagalan baru. Suite penuh tetap merah karena kegagalan baseline pada auth, exam, graduation, public document, queue, sync, serta satu fixture zona waktu riwayat TA.
- Tes JavaScript: **22 lulus**. `npm run build` berhasil. PHP lint pada 26 berkas lingkup berhasil; kompilasi Blade dan lint 210 hasil kompilasi berhasil. Setelah penambahan pesan validasi/sukses dan integrasi ke folder development, gate 87 tes kembali lulus (815 assertion) dan build kembali berhasil.
- Pengujian DB dilakukan serial pada database testing. Pemeriksaan file sumber contoh bersifat baca; tidak melakukan impor pada DB aplikasi. Tampilan browser nyata dan server Docker tetap perlu diperiksa operator setelah update.

Rescan setelah penyalinan memeriksa 29 berkas per folder (25 kode/test dan 4 dokumen/patch). Seluruh hash berkas lingkup sama di kedua folder, kecuali `routes/web.php` yang mempertahankan tambahan route masing-masing branch. `git diff --check` bersih. Backup sebelum integrasi: `/tmp/dadu93-integration-backup-6cy_ma_q`.

Log verifikasi sesi tersimpan sementara di `/tmp/dadu93-focused.log`, `/tmp/dadu93-candidate-full.log`, `/tmp/dadu93-baseline-full.log`, `/tmp/dadu93-js.log`, `/tmp/dadu93-build.log`, `/tmp/dadu93-integrated-focused.log`, dan `/tmp/dadu93-integrated-build.log`.

## Salin perubahan dari dadu ke dadu-test

Kode lingkup ini telah disalin ke kedua folder laptop dalam sesi ini. Blok berikut untuk mengulang penyalinan bila diperlukan; ini **bukan** command deployment `/opt/dadu-test`. Command menyiapkan backup, menyalin berkas lingkup, dan menerapkan tambahan route saja supaya route lain pada branch berbeda tetap terjaga. Hentikan bila patch route konflik; jangan mengganti seluruh `routes/web.php` dengan versi branch lain.

> Blok perintah historis ini dipensiunkan karena memakai manifest/script/patch lama. Untuk salinan kode terbaru gunakan [panduan penyelarasan](../panduan-pemakaian/penyelarasan-file.md). Bukti lama diringkas pada [catatan verifikasi](../catatan/bukti-verifikasi-historis.md).


## Pengingat sumber data

**JSON Kehadiran masih belum tersedia.** File KRS baru bukan contoh kehadiran. Untuk menyesuaikan tab Kehadiran, minta contoh yang sudah disamarkan tetapi tetap mempertahankan struktur asli: identitas NIM, kode MK/kelas, tahun dan term, serta field kehadiran yang benar-benar diberikan sumber (rincian pertemuan/tanggal/status hadir atau rekap hadir/total/persentase). Tidak perlu mengarang field atau meminta data pribadi asli. Pengerjaan Form Yudisium tetap dicatat pada 91 sampai prioritas ini selesai dan pengguna melanjutkannya.
