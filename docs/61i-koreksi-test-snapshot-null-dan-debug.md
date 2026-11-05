# 61i — Test snapshot null harus menetapkan mode respons error

## Hasil rescan dan kegagalan pasca-salin

Seluruh48 file manifest identik byte-per-byte. Tujuh file retire sudah terhapus di kedua clone. Perbandingan seluruh app/resources/routes/tests/database/config menemukan503 file di masing-masing clone, tanpa perbedaan source.

Log `/tmp/dadu-sidang-ta-61-after-copy.log` menunjukkan **346 passed,1 failed**. Satu test yang gagal adalah `current_registration_with_null_snapshot_fails_closed_without_live_student_values`, pada assertDontSee nama mahasiswa. AssertStatus409 sebelumnya **sudah lulus**.

## Penyebab

Pengaturan lokal berbeda: `.env` development asli memakai APP_DEBUG=false; `.env` dadu-test memakai APP_DEBUG=true. phpunit.xml kedua clone tidak menetapkan APP_DEBUG, sehingga tiap clone mewarisinya dari environment. Source Laravel renderer dapat memasukkan data diagnostik (termasuk binding query) dalam halaman error terperinci. Teks fixture yang muncul di HTML debug bukan bukti panel akademik melakukan fallback ke Student live; guard menolak snapshot tidak lengkap dengan409.

Test sebelumnya belum menetapkan jenis respons error yang ingin diperiksa, sehingga assertion isi halaman bergantung pada konfigurasi lokal. Koreksinya adalah menetapkan app.debug=false **di dalam test ini saja**, untuk menguji response pengguna biasa. Semua assertion409/nama/IPK/marker/snapshot tetap dipertahankan; tambahkan pemeriksaan DOM bahwa panel akademik tidak dirender. Tidak mengubah .env, phpunit.xml, middleware, controller, guard409, atau data akademik.

Pengaruh debug pada banyaknya informasi error mengikuti [dokumentasi konfigurasi Laravel](https://laravel.com/docs/13.x/configuration#debug-mode). Nilai debug pada test lain tetap mengikuti lifecycle aplikasi test masing-masing; konfigurasi tidak dipasang pada beforeEach seluruh suite.

## 1. Perbaiki satu test di folder asli

Buka `tests/Feature/SidangTaDetailUiTest.php` dan ganti **keseluruhan test bernama** `current_registration_with_null_snapshot_fails_closed_without_live_student_values` dengan berikut. Crawler sudah di-import di file ini. Test lain dipertahankan.

```php
test('current_registration_with_null_snapshot_fails_closed_without_live_student_values', function () {
    // Assert the normal error response independently of the local .env debug setting.
    config(['app.debug' => false]);
    $registration = ($this->makeRegistration)(['academic_snapshot' => null]);
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertStatus(409)
        ->assertDontSee('Nama Saat Diajukan')->assertDontSee('3,25');
    expect((new Crawler($response->getContent()))->filter('[data-academic-detail]')->count())->toBe(0);
    expect($registration->fresh()->requirements_version)->toBe(2);
    expect($registration->fresh()->academic_snapshot)->toBeNull();
});
```

Tidak perlu generator, migration, reset database aplikasi, atau perubahan kode fitur.

## 2. Jalankan test fokus di folder asli

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php
```

## 3. Jika fokus lulus, salin satu file koreksi ke dadu-test

Tidak perlu mengulang penyalinan48 file:

```bash
cp -- /home/xdranel/Code/Project/php-laravel/dadu/tests/Feature/SidangTaDetailUiTest.php /home/xdranel/Code/Project/secret/dadu-test/tests/Feature/SidangTaDetailUiTest.php
```

## 4. Jalankan ulang kelompok TA di dadu-test

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php 2>&1 | tee /tmp/dadu-sidang-ta-61-after-copy.log
```

Sesudah selesai, beri tahu asisten untuk membaca log yang sama lagi. Jumlah test tidak berubah; assertion DOM bertambah. Pengujian Node dan manual yang telah lulus tidak perlu diulang hanya karena koreksi fixture respons error ini; source fitur/JavaScript tidak diubah.

## Status verifikasi

File usulan lolos PHP syntax lint. Rescan hash source dua clone diperiksa sebelum/sesudah setiap penulisan Markdown dan tetap sama. Asisten tidak menjalankan test database atau menulis source. Hasil pasca-koreksi masih menunggu eksekusi pengguna. Masalah full suite Auth61f dan JSON resmi tetap terbuka.


## Hasil akhir setelah pengguna menerapkan koreksi

Log `/tmp/dadu-sidang-ta-61-after-copy.log` diperbarui5 November2026 pukul07:24 waktu filesystem dan dibaca langsung: **347 passed,3988 assertions,36.44s**, tanpa failure. Koreksi test menetapkan app.debug=false di dalam test negatif saja, semua assertion lama dipertahankan dan pemeriksaan DOM ditambahkan.

Rescan kedua clone:503 file source di masing-masing app/resources/routes/tests/database/config, seluruh hash identik.48 file manifest tersalin, tujuh file retire terhapus, tidak ada perubahan fitur tambahan yang perlu disalin. Manual TA lokal lulus menurut laporan pengguna. Node13 passed yang sudah diuji tetap relevan karena tidak ada perubahan JavaScript setelahnya.

Checkpoint61 alur tunggal TA sudah diverifikasi untuk cakupan tersebut. Ini bukan pernyataan bahwa full suite proyek lulus: Auth PasswordResetTest61f dan celah reset tanpa bukti pemilik akun belum ditangani. JSON resmi SKPI, approval iGracias, riwayat EPRT dan TAK juga masih menunggu konfirmasi; fixture dummy tidak menjadi kontrak resmi. Jangan mengaktifkan nilai unknown sebagai eligible.

Tidak perlu mengulang reset, penyalinan48 file, Node, atau manual test hanya untuk menutup catatan ini. Tidak ada commit/push/deploy yang dilakukan asisten.

Commit message jika ingin menyimpan checkpoint TA ini:

```text
refactor(sidang-ta): unify current registration requirements

- apply the current requirements to all registration accounts
- retire the legacy workflow and add explicit TA test-data cleanup
- align review, revision, preview, export, and regression coverage
- make authentication fixtures and error-response tests deterministic
```

Gunakan untuk perubahan checkpoint terkait yang memang akan di-commit; jangan menyertakan .env, kredensial, data upload, atau phpunit.xml lokal yang sebelumnya disepakati bersifat sementara. Pekerjaan Auth61f tetap terbuka setelah commit checkpoint ini.
