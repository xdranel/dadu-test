# 36 — Rescan preview sebelum commit student

Status: pengguna melaporkan **27 fokus, 124 PHP, 12 Node, manual preview berhasil**. Rescan menemukan satu masalah struktur HTML yang perlu diperbaiki sebelum commit, serta beberapa perapian kecil. Asisten hanya menulis docs, tidak mengubah source atau menjalankan commit/push.

## 1. Hasil pemeriksaan

| Temuan | Status/tindakan |
| --- | --- |
| Include modal menjadi baris pertama pada wizard-review dan show, sebelum x-app-layout | **Perbaiki sebelum commit**: pindahkan ke dalam layout, tepat sebelum tag penutup |
| Header index masih dibungkus section card lama | Kosmetik yang terlewat dari34: hapus wrapper section pertama saja |
| Test Node preview di dadu-test berada pada tests/Feature | Pindahkan ke tests/Js; file original sudah pada lokasi benar dan isinya identik |
| Staged diff mempunyai blank line tambahan pada EOF StudentPolishTest | Hapus blank line berlebih dan stage ulang |
| phpunit.xml masih memuat setting Podman lokal; package-lock hanya perubahan nama Dadu ke dadu | Keduanya **belum staged** pada project asli; kembalikan konfigurasi sementara sebelum commit sesuai rencana pengguna |

Source aplikasi TA kedua folder identik. Perbedaan test antarfolder hanya lokasi file Node tersebut. Pemeriksaan sintaks34 file PHP TA/routes lulus. Asisten menjalankan ulang ketiga file Node pada project asli: **12 lulus**. Review independen staged diff tidak menemukan blocker lain pada owner/reviewer, MIME, version, completeness, cleanup file revisi atau provider modal.

Hasil124 PHP/manual berasal dari laporan pengguna; asisten tidak menjalankan aplikasi/DB/suite PHP/build. Test saat ini memeriksa keberadaan modal, tetapi belum memeriksa letaknya terhadap layout; itu sebabnya markup keliru dapat lolos.

## 2. Koreksi posisi include pada dua view

Edit kedua file existing di project asli:

```text
resources/views/sidang_ta_registrations/wizard-review.blade.php
resources/views/sidang_ta_registrations/show.blade.php
```

Hapus baris pertama ini pada masing-masing file:

```blade
@include('sidang_ta_registrations.partials.document-preview-modal')
```

File harus mulai dengan `<x-app-layout>`. Pindahkan include itu ke akhir **di dalam** komponen, tepat sebelum `</x-app-layout>`:

```blade
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

Potongan di atas menunjukkan penutup container utama yang sudah ada, bukan penambahan div baru. Pastikan hanya satu include modal per file.

Layout `resources/views/layouts/app.blade.php` dimulai dengan `<!DOCTYPE html>`. Include di luar/sebelum komponen mengeluarkan elemen modal sebelum doctype dan berpotensi memicu quirks mode. Memindahkannya ke slot layout membuat modal berada di dalam dokumen/body yang benar.

## 3. Rapikan wrapper index dan akhir file test

Pada `resources/views/sidang_ta_registrations/index.blade.php`, hapus hanya pembuka ini tepat setelah `<div class="space-y-5">`:

```blade
<section class="campus-card space-y-3 p-5">
```

Hapus pasangan `</section>`-nya tepat sebelum `<section data-testid="drafts-panel" ...>`. Seluruh isinya (alerts/header/action/pesan tutup) dipertahankan langsung dalam wrapper space-y-5. Jangan menghapus section draft atau tabel riwayat.

Pada `tests/Feature/SidangTaStudentPolishTest.php`, hapus blank line setelah penutup test terakhir. File tetap diakhiri satu newline sesudah `});`.

## 4. Regression test lengkap: posisi modal

Tidak perlu generator karena file test sudah ada. Tambahkan **dua test berikut sekali saja** di akhir `tests/Feature/SidangTaDocumentPreviewTest.php`; gunakan fixture/helper yang sudah ada dari35:

```php
test('preview_review_modal_is_inside_layout_after_document_doctype', function () {
    $draft = ($this->completeDraft)();
    $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->version])
        ->assertOk();
    $html = $this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});

test('preview_final_modal_is_inside_layout_after_document_doctype', function () {
    [$registration] = ($this->makeFinal)();
    $html = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
});
```

Kedua test ini semestinya gagal pada susunan include saat rescan. Crawler sudah di-import pada file itu. Sesudah koreksi, jumlah test file Preview menjadi **10**, suite TA **126**.

Untuk menangkap wrapper card yang terlewat, pada test existing `s2_index_keeps_draft_and_history_when_multiple_periods_close` di `tests/Feature/SidangTaStudentPolishTest.php`, tambahkan setelah assertion class header bukan campus-card:

```php
$headerNode = $header->getNode(0);
$xpath = new \DOMXPath($headerNode->ownerDocument);
expect($xpath->query(
    'ancestor::section[contains(concat(" ", normalize-space(@class), " "), " campus-card ")]',
    $headerNode
)->length)->toBe(0);
```

Assertion class lama hanya memeriksa elemen header sendiri; pemeriksaan ancestor ini juga menangkap card pembungkusnya. Ini menambah assertion pada test existing, bukan test baru.

## 5. Koreksi salinan test Node pada dadu-test

Project asli **sudah benar** pada `tests/Js/sidang-ta-document-preview.test.mjs`. Hanya di dadu-test salah lokasi. Jalankan di terminal dadu-test:

```bash
cd /home/xdranel/Code/Project/secret/dadu-test
mv tests/Feature/sidang-ta-document-preview.test.mjs tests/Js/sidang-ta-document-preview.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
```

Saat rescan, file tujuan belum ada dan isi file sumber identik dengan test original. Jangan membuat salinan tambahan di Feature. Tidak perlu memindahkan file apa pun di project asli.

## 6. Verifikasi sesudah koreksi

Jalankan di project asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaStudentPolishTest.php
```

Target fokus **29 passed**. Command suite lengkap pada35 bagian10 tetap sama, tetapi target sekarang **126 passed**. Node tetap12. Tidak perlu menambah file test baru atau mengubah konfigurasi database lagi.

Manual singkat: buka tinjau dan detail, pastikan modal PDF/gambar, Unduh, tab baru, Tutup/Escape tetap bekerja. Di Console DevTools pada kedua halaman, periksa:

```js
document.compatMode
```

Harus `CSS1Compat`. Header index tidak lagi mempunyai card pembungkus; panel draft dan riwayat tetap utuh.

Sesudah lulus, salin ulang lima file yang berubah ke dadu-test: dua view tinjau/detail, index, DocumentPreviewTest dan StudentPolishTest. Minta rescan akhir sebelum menganggap student selesai.

## 7. Menyiapkan commit project asli

Pada hasil git status saat rescan, 24 file fitur sudah staged; phpunit.xml dan package-lock.json hanya unstaged. Keduanya tidak termasuk staged commit saat ini. Jangan menggunakan git add . sebelum konfigurasi sementara ditangani.

Karena diff phpunit.xml hanya setting test Podman sementara, kembalikan sesuai rencana sebelumnya:

```bash
git restore -- phpunit.xml
```

Diff package-lock.json hanya mengubah name dari Dadu menjadi dadu. Jika itu perubahan tidak disengaja, kembalikan juga; tidak ada dependency baru untuk preview:

```bash
git restore -- package-lock.json
```

Stage ulang lima file koreksi secara spesifik:

```bash
git add resources/views/sidang_ta_registrations/wizard-review.blade.php resources/views/sidang_ta_registrations/show.blade.php resources/views/sidang_ta_registrations/index.blade.php tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaStudentPolishTest.php
git diff --cached --check
git diff --cached --stat
git status --short
```

`--check` harus tanpa output error. Pemeriksaan ini tidak membuat commit. Pada scope staged yang diperiksa, pesan commit yang sesuai setelah koreksi/test/rescan bersih:

```text
feat(sidang-ta): sempurnakan draft parsial dan preview dokumen mahasiswa

- simpan progres draft tanpa membuka tahap yang belum lengkap
- rapikan tampilan pendaftaran dan status tombol tinjau
- tambahkan preview privat PDF dan gambar dengan opsi unduh
- lengkapi pengujian akses berkas dan struktur halaman mahasiswa
```

**Belum memberi status siap commit tanpa syarat pada rescan ini**, karena posisi include masih perlu diperbaiki. Tidak ada blocker lain ditemukan pada lingkup fitur yang diperiksa. Setelah koreksi ini selesai dan salinan diverifikasi, bagian student dapat ditutup sebelum P1 petugas. Asisten tidak menjalankan restore/add/commit/push atau mengubah file aplikasi.
