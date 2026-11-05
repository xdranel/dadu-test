# 29 — Koreksi lokasi view detail G2b

## 1. Penyebab enam test gagal

Scan awal langsung folder asli menemukan penerapan G2b pada file yang berbeda dari target. **Pembaruan saat rescan berlangsung: kedua view kemudian sudah dibetulkan di folder asli. Bagian 2 mencatat kondisi terakhir; tidak perlu mengulang penyalinan.**

| File | Kondisi aktual |
| --- | --- |
| `resources/views/sidang_ta_registrations/show.blade.php` | Masih view mahasiswa lama; tidak memanggil detail-academic dan tidak memiliki elemen detail baru |
| `resources/views/sidang_ta_registrations/admin/show.blade.php` | Berisi kode detail mahasiswa G2b yang seharusnya masuk file di atas |
| `partials/detail-academic.blade.php` | Sudah dibuat dengan isi G2b |
| Controller `show()` | Sudah menghitung canRevise dan mengirimnya ke view mahasiswa yang benar |

Karena route mahasiswa tetap membuka view mahasiswa lama, enam test tidak menemukan card/field/riwayat baru. Ini belum menunjukkan kesalahan formatter angka atau perhitungan snapshot.

Ada dampak lain yang perlu dipulihkan: adminShow() hanya mengirim registration, sedangkan kode mahasiswa yang sekarang berada pada view admin memakai canRevise. Form keputusan admin sebelumnya juga tergantikan. Mengembalikan file admin adalah pemulihan perubahan yang salah lokasi, bukan pengembangan workflow petugas.

## 2. Kondisi terakhir: lokasi view sudah dibetulkan

Pada pemeriksaan berikutnya selama sesi, hash menunjukkan view mahasiswa sudah berisi kode G2b yang sebelumnya berada pada admin, dan view admin asli sudah kembali identik dengan salinan sebelum G2b di dadu-test. Jadi kedua file kini berada pada tempat yang benar. Asisten tidak menjalankan penyalinan atau mengedit aplikasi.

Tidak perlu menyalin kedua file lagi. Jalankan clear compiled views sebelum menguji ulang bila belum dilakukan:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
```

Hasil pemeriksaan terakhir:

- Mahasiswa: `resources/views/sidang_ta_registrations/show.blade.php` memanggil partial detail-academic, memakai canRevise, dan memiliki riwayat Indonesia.
- Admin: `resources/views/sidang_ta_registrations/admin/show.blade.php` kembali memiliki form Keputusan petugas dan registration_version, serta tidak memakai canRevise.
- Controller, partial dan route tidak perlu diubah lagi untuk memperbaiki salah lokasi ini.

Untuk memastikan file secara baca saja:

```bash
rg -n 'detail-academic|data-academic-detail|data-registration-history|data-revise-registration|registration_version|Keputusan petugas|canRevise' resources/views/sidang_ta_registrations/show.blade.php resources/views/sidang_ta_registrations/admin/show.blade.php resources/views/sidang_ta_registrations/partials/detail-academic.blade.php
```

Target view lengkap tetap tersedia pada [28 bagian 4](28-checkpoint-g2b-detail-pengajuan-student.md#4-isi-lengkap-showbladephp). Nama view mahasiswa **tidak memiliki subfolder admin**. Tidak ada make:view/migrasi/seed/dependency baru pada koreksi ini.

## 3. Perkuat dua assertion negatif

Ada satu kelemahan test yang menjelaskan mengapa sebagian test bisa lulus meskipun view masih lama: pemeriksaan tidak adanya `data-revise-registration` saja bisa lulus karena view lama belum memiliki atribut tersebut, walaupun tautan Perbaiki lama masih terlihat. Tambahkan pemeriksaan **URL edit pengajuan tidak terdapat pada HTML**, bukan hanya atribut baru tidak ada.

Pada `tests/Feature/SidangTaDetailUiTest.php`, ganti dua blok berikut saja. Jumlah test tetap delapan.

```php
test('owner_without_update_permission_sees_no_revise_button_and_edit_is_forbidden', function () {
    $registration = ($this->makeRegistration)();
    $this->user->revokePermissionTo('sidang_ta_registration_update');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertForbidden();
});

test('reviewer_without_student_can_read_detail_but_has_no_student_revise_button', function () {
    $registration = ($this->makeRegistration)();
    $reviewer = User::factory()->create([
        'username' => 'reviewer-detail-28', 'email' => 'reviewer-detail@example.test', 'employee_id' => null,
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $reviewer->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update', 'sidang_ta_registration_review']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $response = $this->actingAs($reviewer)
        ->get(route('sidang-ta-registrations.show', $registration))->assertOk()
        ->assertDontSee(route('sidang-ta-registrations.edit', $registration), false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
});
```

Ini memperkuat assertion, bukan mengurangi test agar lulus. Pemeriksaan nilai 0, snapshot historis, data tambahan, akses mahasiswa lain dan riwayat tetap dipertahankan seperti 28.

## 4. Jalankan ulang

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php
```

Sesudah delapan test fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php
```

Target tetap **98 definisi test PHP TA**. Setelah itu lanjutkan build/checklist browser pada 28. Sebagai pemeriksaan pemulihan, halaman peninjauan lama tidak boleh lagi gagal karena canRevise dan form keputusannya kembali tersedia; ini tidak mengubah workflow petugas.

Setelah semua berhasil, salin empat file G2b pada 28 ke dadu-test. View admin asli yang sudah dipulihkan seharusnya kembali sama dengan dadu-test; jangan menyalin versi admin yang salah tadi. Tidak memindahkan `.env`, phpunit.xml sementara, cache atau database.

## 5. Bukti dan batas verifikasi

Asisten membaca source aktual dan menemukan elemen G2b di admin/show, sementara show mahasiswa masih lama. Controller/test PHP asli lolos lint. Hash view admin dadu-test cocok dengan baseline sebelum penulisan G2b, sehingga sumber pemulihan tidak ditebak dari Git atau direkonstruksi ulang.

Selama sesi berlangsung dua source view asli berubah: hash view admin kembali cocok dengan dadu-test, dan view mahasiswa cocok dengan kode G2b yang sebelumnya salah lokasi. Dokumen ini mengikuti kondisi terbaru tersebut. Jangan menganggap hash seluruh source tetap sama dari awal sesi; perubahan kedua file itu memang terdeteksi dan tidak dilakukan oleh asisten.

Tidak menjalankan cp, Laravel test, view:clear, database atau browser oleh asisten. Test dan kode aplikasi tidak diedit; semua perubahan asisten hanya pada docs. Setelah urutan lokasi ini diperbaiki, bila muncul kegagalan lanjutan kirim output lengkapnya; jangan menghapus assertion atau mengganti semua selector test agar cocok dengan view lama.

Referensi Context7: [views Laravel 13](https://github.com/laravel/docs/blob/13.x/views.md), pemetaan subfolder view dan command view:clear.
