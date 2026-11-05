# 61 — Satu alur persyaratan Sidang TA untuk seluruh akun uji

> Dokumen ini menggantikan rancangan lama 61/61a tentang upgrade draft dan kompatibilitas versi 1. Terapkan hanya di development asli, uji, lalu salin ke dadu-test. Asisten hanya menulis dokumen, tidak mengubah kode aplikasi.

## Keputusan

Aplikasi lokal dan instance publik saat ini sama-sama lingkungan testing, belum ada pengguna atau data operasional yang perlu dipertahankan. Karena itu implementasi ini membuat **satu alur persyaratan terbaru untuk semua akun** dan menghapus seluruh data Sidang TA uji lama setelah tes lulus. Tidak ada upgrade draft, tombol pilih versi, atau fallback business versi 1.

`requirements_version` tetap ada sebagai penanda kontrak internal: model dan database default ke `2`, endpoint/service hanya menerima `2`, request client tidak boleh mengganti nilainya. Ini menjaga validasi kontrak dan replay. `version`/`lock_version` tetap untuk konkurensi.

Field resmi JSON untuk SKPI, persetujuan iGracias, riwayat tes EPRT dan TAK belum dikonfirmasi. Jangan menebak field atau mengarang fixture baru; data yang belum tersedia tetap fail-closed. KRS `TUGAS AKHIR 2` sudah terkonfirmasi. Tak ada perubahan pada adapter sinkronisasi JSON di checkpoint ini.

## Rescan sebelum implementasi

Pada 5 November 2026: seluruh 14 path checkpoint60 identik di dua folder; satu-satunya selisih source adalah `tests/Feature/SidangTaActivationTest.php` tambahan di source, yang menjadi test baru current-only dan menimpa test aktivasi rancangan lama. Kode aplikasi tidak berubah. Jalankan rescan lagi sebelum menulis kode; jangan menyalin source ke folder asli.

## Paket langkah

- [61a — 20 file test lengkap dan daftar retire](61a-penyesuaian-test-alur-tunggal-sidang-ta.md)
- [61b — 18 file backend, migration, command reset](61b-backend-dan-reset-data-uji-sidang-ta.md)
- [61c — 8 Blade view lengkap](61c-view-alur-tunggal-sidang-ta.md)

Tiga dokumen paket berisi seluruh file (bukan potongan selector). Simpan script berikut sebagai `/tmp/apply_sidang_ta_61.py`. Jalankan dari root Laravel; script memvalidasi path, menyimpan backup di `/tmp`, lalu menulis hanya blok file lengkap. Ia tidak menghapus, migrate, atau menjalankan tes.

```python
from pathlib import Path
from datetime import datetime
import re, shutil, sys
repo=Path.cwd().resolve()
if not (repo/'artisan').is_file(): raise SystemExit('Jalankan dari root Laravel.')
if len(sys.argv)!=2: raise SystemExit('Pemakaian: python3 /tmp/apply_sidang_ta_61.py docs/61b-backend-dan-reset-data-uji-sidang-ta.md')
text=Path(sys.argv[1]).read_text(encoding='utf-8')
blocks=re.findall(r'^### `([^`]+)`\s*\n\s*```(?:php|blade)\s*\n(.*?)^```\s*$',text,re.M|re.S)
if not blocks: raise SystemExit('Tidak ada blok file utuh.')
paths=[Path(p) for p,_ in blocks]
if len(paths)!=len(set(paths)): raise SystemExit('Path duplikat.')
for p in paths:
    if p.is_absolute() or '..' in p.parts or p.parts[0] not in {'app','database','tests','resources'}: raise SystemExit(f'Path ditolak: {p}')
backup=Path('/tmp')/('sidang-ta-61-backup-'+datetime.now().strftime('%Y%m%d-%H%M%S'))
for p in paths:
    target=repo/p
    if target.exists():
        saved=backup/p; saved.parent.mkdir(parents=True,exist_ok=True); shutil.copy2(target,saved)
for p,body in blocks:
    target=repo/p; target.parent.mkdir(parents=True,exist_ok=True); target.write_text(body,encoding='utf-8')
print(f'{len(blocks)} file ditulis; backup: {backup if backup.exists() else "tidak ada"}')
```

Generator stub (isi tetap harus diganti dengan file penuh dari paket):

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:command ResetSidangTaTestData
php artisan make:test SidangTaResetTestDataTest --pest
php artisan make:migration set_sidang_ta_requirements_default_to_two
```

`SidangTaActivationTest.php` sudah ada dari rencana lama. Ganti file itu penuh dengan isi61a; jangan membuat file duplikat.

## Urutan implementasi dan test — mulai dari sini setelah kode sudah diganti

Hasil rescan: implementasi di folder asli sudah sesuai paket61. Controller memakai import `use` sebagai pengganti fully qualified names; itu setara. Migration yang kamu buat bernama `2026_11_04_231319_set_sidang_ta_requirements_default_to_two.php`, isinya benar. Pertahankan nama itu; jangan membuat migration kedua. `dadu-test` masih checkpoint60, jadi jangan disalin sebelum test/manual lulus.

**1. Betulkan test tambahan yang baru ditemukan saat rescan.** Ini kesalahan di panduan test sebelumnya: helper yang tersedia bernama `markReviewed`, bukan `review`; versi konflik harus tetap bilangan positif; file final yang diganti memang dibersihkan. Isi lengkap ActivationTest di61a sudah dibetulkan. Ganti **hanya file itu** dengan perintah berikut. Backend/view tidak perlu diulang:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
python3 - <<'PY'
from pathlib import Path
import re, shutil, tempfile
source=Path('/home/xdranel/Code/Project/secret/dadu-test/docs/61a-penyesuaian-test-alur-tunggal-sidang-ta.md')
path='tests/Feature/SidangTaActivationTest.php'
blocks=dict(re.findall(r'^### `([^`]+)`\s*\n\s*```php\s*\n(.*?)^```\s*$',source.read_text(),re.M|re.S))
if path not in blocks: raise SystemExit('Blok test lengkap tidak ditemukan; file tidak diubah.')
target=Path(path)
backup=Path(tempfile.mkdtemp(prefix='ta61-activation-backup-'))/target.name
if target.exists(): shutil.copy2(target,backup)
target.write_text(blocks[path])
print('Test diperbarui. Backup:',backup)
PY
```

**2. Pastikan database Podman sudah berjalan**, tetap pada terminal folder asli, lalu bersihkan cache konfigurasi dan migrate **dadu_testing**. Ini database unit test, berbeda dari database aplikasi lokal `dadu`. Jangan memakai `migrate:fresh`.

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan migrate --force
```

**3. Jalankan dua file test aktivasi/reset dahulu.** ResetTest memakai DB testing dan Storage fake; menjalankannya tidak sama dengan menjalankan command reset pada database aplikasi.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaActivationTest.php tests/Feature/SidangTaResetTestDataTest.php
```

Jika gagal, hentikan urutan di sini dan kirim blok kegagalannya. Jangan reset data aplikasi atau menyalin file dahulu.

**4. Jika langkah3 lulus, jalankan regresi backend/revisi yang dipertahankan.**

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaMeetStaffReviewTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaRevisionDeadlineTest.php tests/Feature/SidangTaRevisionContractTest.php
```

**5. Jika langkah3 dan4 lulus, hapus tujuh file lama dari folder asli saja.** Kelima test ini menguji alur bisnis v1 yang sekarang dipensiunkan; kedua view tidak dipakai controller/routing. Cakupan current dan proteksi konkurensi tetap ada dalam test pengganti. File di dadu-test baru dibersihkan sesudah semua test/manual lulus dan file baru disalin.

```bash
rm -- tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php resources/views/sidang_ta_registrations/form.blade.php resources/views/sidang_ta_registrations/partials/upload.blade.php
```

**6. Jalankan seluruh test Sidang TA dan Node, kemudian seluruh test proyek.** Wildcard baru dijalankan setelah langkah5 supaya tidak memanggil test alur v1.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test
```

Jika full suite gagal pada SSO, ikuti [61d — koreksi fixture gateway SSO](61d-koreksi-fixture-sso-full-suite.md) dahulu. Patch itu mempertahankan sembilan test login; kegagalan modul lain tetap perlu pesan assertion masing-masing.

Jika test yang memakai `actingAs(User::factory()->create())` diarahkan ke login karena status pada objek fixture null, ikuti [61e — default akun aktif pada UserFactory](61e-koreksi-user-factory-status-akun.md). Perubahan factory perlu diuji kembali terhadap kelompok TA dan full suite.

Kegagalan `reset password link can be requested` dibahas di [61f — diagnosis kontrak lupa password](61f-diagnosis-kontrak-lupa-password.md). Test lama memakai route pengiriman email yang tidak lagi terdaftar; alur reset pengganti juga memerlukan perbaikan verifikasi kepemilikan sebelum kontrak test disesuaikan.

**7. Hasil scoped TA sudah347 passed dan Node13 passed.** Ikuti [61g — hasil test dan uji manual TA lokal](61g-hasil-test-ta-dan-manual-lokal.md) untuk melanjutkan pengujian fitur. Full suite proyek masih gagal pada Auth sesuai61f dan belum dinyatakan lulus. Reset lokal di bawah hanya untuk data TA uji yang lingkupnya sudah disepakati. Database aplikasi lokal mengikuti `.env` (biasanya `dadu`); pastikan database yang dicetak dry-run benar. Lakukan manual test, lalu salin file. Tidak perlu mengulang generator atau script penulisan semua file karena kode sudah kamu ganti.

Perintah test dan pemisahan konfigurasi database mengikuti [dokumentasi Laravel testing](https://laravel.com/docs/13.x/testing); migration incremental mengikuti [dokumentasi migration](https://laravel.com/docs/13.x/migrations).

## Reset data uji setelah test lulus

Command baru `sidang-ta:reset-test-data` default-nya dry-run. Baca nama database, jumlah row dan file sebelum memakai `--execute`. Ia menghapus seluruh metadata Sidang TA dan file lokal yang aman dimiliki fitur itu, tetapi mempertahankan akun, role, mahasiswa, sinkronisasi/profil/KRS/SKPI/TAK/EPRT/BPP, periode Sidang TA, tabel modul lain, berkas bersama, file orphan, dan auto-increment. Tidak menggunakan migrate:fresh, TRUNCATE, disable FK, atau mengubah periode.

Untuk manual TA lokal setelah hasil scoped pada61g, jalankan migration incremental pada database **aplikasi lokal** sesuai `.env`, lalu jalankan command tanpa `--execute` dahulu. Baca nama database/jumlah row; jika benar-benar database aplikasi uji yang dimaksud, baru jalankan baris `--execute`:

```bash
php artisan migrate --force
php artisan sidang-ta:reset-test-data
php artisan sidang-ta:reset-test-data --execute
```

Lakukan terpisah pada instance publik yang memang khusus testing setelah kode ter-deploy. Reset laptop tidak mengubah server publik. Pastikan koneksi aktif benar-benar ke DB testing.

Jika schedule:work berjalan, hentikan dengan `Ctrl+C` ketika memasuki maintenance; jangan jalankan server down saat PHPUnit. Setelah tests lulus, untuk instance publik:

```bash
php artisan down
php artisan migrate --force
php artisan sidang-ta:reset-test-data
php artisan sidang-ta:reset-test-data --execute
php artisan optimize:clear
php artisan up
```

Pada lokal tanpa pengguna, down/up bisa dilewati. Nyalakan kembali `schedule:work` hanya saat menguji expiry dan hentikan `Ctrl+C`. Production scheduler kelak memakai cron/supervisor, bukan terminal ini.

## Manual test dan salin ke dadu-test

Login Krisna `krisna@mahasiswa.test`/`password` dan dummy `dummy-ta-2@mahasiswa.test`/`password` (NIM1202299002), serta akun mahasiswa biasa. Pastikan semuanya melihat alur sama; KRS TA2 valid; facts JSON yang belum dikonfirmasi tetap menghalangi submit dengan penjelasan; draft/review berjalan; petugas melihat pengajuan setelah berhasil dikirim. Pastikan reset mempertahankan periode dan sinkronisasi.

Sesudah rescan sumber dan target, salin daftar path setelah preflight semua source tersedia. Dari terminal folder mana pun:

```bash
python3 - <<'PY'
from pathlib import Path
import shutil
src=Path('/home/xdranel/Code/Project/php-laravel/dadu')
dst=Path('/home/xdranel/Code/Project/secret/dadu-test')
paths=[
    'database/factories/UserFactory.php',
    'tests/Feature/Auth/AuthenticationTest.php',
    'app/Console/Commands/ResetSidangTaTestData.php',
    'app/Http/Controllers/SidangTaDraftController.php',
    'app/Http/Controllers/SidangTaRegistrationController.php',
    'app/Http/Requests/SaveSidangTaDraftStepRequest.php',
    'app/Models/SidangTaRegistration.php',
    'app/Models/SidangTaRegistrationDraft.php',
    'app/Services/SidangTaDraftAccess.php',
    'app/Services/SidangTaDraftDocumentService.php',
    'app/Services/SidangTaDraftFinalizer.php',
    'app/Services/SidangTaDraftService.php',
    'app/Services/SidangTaRegistrationReview.php',
    'app/Services/SidangTaReviewExport.php',
    'app/Services/SidangTaRevisionDraftService.php',
    'app/Support/SidangTaDocumentRequirements.php',
    'app/Support/SidangTaReviewEligibility.php',
    'app/Support/SidangTaRevisionContract.php',
    'app/Support/SidangTaRevisionDeadline.php',
    'database/migrations/2026_11_04_231319_set_sidang_ta_requirements_default_to_two.php',
    'resources/views/sidang_ta_registrations/admin/index.blade.php',
    'resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php',
    'resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php',
    'resources/views/sidang_ta_registrations/admin/show.blade.php',
    'resources/views/sidang_ta_registrations/partials/detail-academic.blade.php',
    'resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php',
    'resources/views/sidang_ta_registrations/wizard-review.blade.php',
    'resources/views/sidang_ta_registrations/wizard.blade.php',
    'tests/Feature/SidangTaActivationTest.php',
    'tests/Feature/SidangTaDetailUiTest.php',
    'tests/Feature/SidangTaDocumentPreviewTest.php',
    'tests/Feature/SidangTaDraftCardTest.php',
    'tests/Feature/SidangTaDraftSchemaTest.php',
    'tests/Feature/SidangTaDraftUploadTest.php',
    'tests/Feature/SidangTaMeetDraftTest.php',
    'tests/Feature/SidangTaMeetFinalizationTest.php',
    'tests/Feature/SidangTaMeetStaffReviewTest.php',
    'tests/Feature/SidangTaPartialDraftTest.php',
    'tests/Feature/SidangTaResetTestDataTest.php',
    'tests/Feature/SidangTaReviewExportTest.php',
    'tests/Feature/SidangTaReviewListTest.php',
    'tests/Feature/SidangTaReviewUiTest.php',
    'tests/Feature/SidangTaRevisionContractTest.php',
    'tests/Feature/SidangTaRevisionDeadlineTest.php',
    'tests/Feature/SidangTaStaffNavigationTest.php',
    'tests/Feature/SidangTaStudentPolishTest.php',
    'tests/Feature/SidangTaWizardTest.php',
    'tests/Feature/SidangTaWizardUiTest.php',
]
missing=[p for p in paths if not (src/p).is_file()]
if missing: raise SystemExit('Source belum lengkap: '+', '.join(missing))
for rel in paths:
    target=dst/rel
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(src/rel,target)
print(f'{len(paths)} file disalin. Jalankan test ulang di dadu-test.')
PY
```

Tujuh file lama sudah dihapus dari folder asli pada langkah5. Sesudah salin48 file baru, hapus tujuh file lama dari dadu-test dengan perintah di bawah, lalu jalankan test di dadu-test dengan konfigurasi MySQL Podman yang sama:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
rm -- tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRequirementsVersionTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php resources/views/sidang_ta_registrations/form.blade.php resources/views/sidang_ta_registrations/partials/upload.blade.php
```

Jangan jalankan perintah retire sebelum test pengganti lulus. Jangan salin `.env`, `phpunit.xml`, atau dokumen. Ulangi rescan dua folder setelah copy dan retire.

Checkpoint TA61 masih menunggu manual lokal dan salin48 file/test pasca-salin. Full suite proyek dan persoalan Auth61f dicatat terpisah sebagai pekerjaan belum selesai; hasil347+13 tidak menutupnya. Data JSON yang menunggu konfirmasi tetap dicatat sebagai keterbatasan, bukan ditebak.
