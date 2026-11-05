# 25 — Koreksi dua test warning draft stale

Tanggal: 2 November 2026. Hasil pengguna: **2 failed, 83 passed**, seluruh test Node lulus. G1 terbaru masih diperiksa langsung di folder asli; belum menganggap dadu-test sudah tersinkron.

## 1. Penyebab yang terkonfirmasi

Kedua kegagalan yang dikirim pengguna adalah:

1. `SidangTaWizardTest::index_exposes_discard_for_stale_revision`, baris 126.
2. `SidangTaDraftCardTest::stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration`, baris 104.

Keduanya berhenti pada `assertSee('Pengajuan asal berubah.')`. Pada source `index.blade.php` yang sudah diformat, teksnya berbentuk:

```html
<p class="...">Pengajuan asal
    berubah. Hapus draft lama sebelum memulai revisi kembali.</p>
```

Browser memperlakukan newline/indentasi itu sebagai spasi pada tampilan normal. Assertion HTML mentah mencari substring persis dan tidak menemukannya. Jadi kegagalan ini berasal dari assertion yang sensitif terhadap formatting, bukan bukti bahwa warning hilang atau backend revisi/discard rusak.

Kelemahan ini juga ada pada contoh assertion dalam panduan sebelumnya. Koreksi berikut mempertahankan pemeriksaan warning lengkap setelah whitespace dinormalisasi, beserta guard/aksi yang sebelumnya diuji. Tidak perlu mengubah Blade menjadi satu baris, menghapus assertion, memakai regex terlalu longgar, atau mengubah logika aplikasi hanya agar test hijau.

## 2. Ganti satu test pada SidangTaWizardTest

Edit `tests/Feature/SidangTaWizardTest.php`. Cari test bernama `index_exposes_discard_for_stale_revision`, lalu ganti **seluruh blok test tersebut saja** dengan berikut. Fixture `beforeEach` dan 13 test lainnya tetap dipertahankan. Tidak perlu command generator karena file sudah ada.

```php
test('index_exposes_discard_for_stale_revision', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Wizard Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
    ])->fresh();
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertSee(route('sidang-ta-drafts.destroy', $id), false)
        ->assertDontSee(route('sidang-ta-drafts.edit', $id), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $this->delete(route('sidang-ta-drafts.destroy', $id), ['version' => 1])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});
```

`Symfony\Component\DomCrawler` dan `CssSelector` sudah berada pada composer.json/vendor proyek. Tidak menambah dependency atau mengubah composer.lock. Nama class lengkap di atas menghindari kebutuhan import baru.

## 3. Ganti satu test pada SidangTaDraftCardTest

Edit `tests/Feature/SidangTaDraftCardTest.php`. Ganti **seluruh blok test terakhir** yang bernama `stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration`. Fixture, helper XPath dan dua test lainnya tetap dipertahankan.

```php
test('stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration', function () {
    $source = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
        'student_name' => 'Draft Card Test', 'student_phone' => '081234567890',
        'parent_phone' => '081234567891', 'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'needs_review',
    ])->fresh();
    $id = $this->postJson(route('sidang-ta-drafts.revision', $source))
        ->assertCreated()->json('data.id');
    $draft = SidangTaRegistrationDraft::findOrFail($id);
    $source->lock_version++;
    $source->save();

    $response = $this->get(route('sidang-ta-registrations.index'))->assertOk()
        ->assertDontSee(route('sidang-ta-drafts.edit', $draft), false);
    $crawler = new \Symfony\Component\DomCrawler\Crawler($response->getContent());
    $card = $crawler->filter('[data-testid="draft-card"]');
    expect($card->count())->toBe(1);
    expect($card->text(null, true))->toContain('Pengajuan asal berubah.');

    $xpath = sidangTaDraftCardXpath($response->getContent());
    expect($xpath->query('//*[@data-open-discard]')->length)->toBe(1);
    expect($xpath->query('//dialog')->length)->toBe(1);
    $this->delete(route('sidang-ta-drafts.destroy', $draft), ['version' => $draft->version])
        ->assertRedirect(route('sidang-ta-registrations.index'));
    expect(SidangTaRegistrationDraft::find($id))->toBeNull();
    expect($source->fresh()->status)->toBe('needs_review');
});
```

`text(null, true)` hanya merapikan whitespace; warning tetap harus ada dalam card draft. Keberadaan dialog/aksi hapus, tidak adanya link lanjutkan stale, penghapusan draft dan keberadaan pengajuan final tetap diuji. Tidak mengganti assertion dengan sekadar `assertOk()`.

## 4. Jalankan ulang

Fokus pada dua test dahulu:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaDraftCardTest.php --filter='index_exposes_discard_for_stale_revision|stale_revision_has_delete_confirmation_without_continue_and_preserves_final_registration'
```

Sesudah keduanya lulus, jalankan command suite yang tadi kamu gunakan:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php
```

Jumlah definisi test tetap **85**, tidak menambah atau menghapus test. Test Node sudah dilaporkan lulus oleh pengguna; tidak perlu mengganti JavaScript untuk kegagalan assertion PHP ini.

## 5. Batas hasil rescan

- Registrasi `sidangTaUpload` pada app.js asli sekarang sudah ada sebelum Alpine.start, sesuai perbaikan 24.
- Section draft ringkas/dialog dan test baru sudah berada di folder asli. Newline yang menyebabkan mismatch ditemukan pada source aktual, lalu dicocokkan dengan kedua output FAILED pengguna.
- Sebanyak 28 file PHP Sidang TA pada aplikasi/test asli lolos lint. Tidak menjalankan suite Laravel, database, browser, Vite atau build oleh asisten.
- Reproduksi tanpa database memakai HTML warning asli: matcher substring PHPUnit menghasilkan false pada HTML mentah dan true pada teks DOM yang dinormalisasi. Ini memverifikasi penyebab mismatch, bukan klaim seluruh 85 test sudah lulus.
- Asisten hanya menulis docs. Setelah pengguna menguji ulang, selesaikan checklist browser 24 dan salin hasil ke dadu-test untuk rescan sebelum G2. Jika ada kegagalan lain setelah assertion ini terlewati, kirim output barunya; jangan menghapus assertion lanjutan.

Referensi Context7: [Symfony DomCrawler — extracting information](https://symfony.com/doc/current/components/dom_crawler.html#extracting-information), khusus normalisasi whitespace pada `text(null, true)`. API yang dipakai juga diperiksa pada vendor lokal.
