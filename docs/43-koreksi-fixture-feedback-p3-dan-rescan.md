# 43 — Koreksi fixture feedback P3 dan hasil rescan implementasi 42

## 1. Penyebab dua test gagal

Kesalahan ada pada fixture panduan42 awal, bukan alasan untuk melonggarkan validasi upload aplikasi. Kedua test mengambil `$source->documents()->firstOrFail()` tanpa menentukan `document_type`, kemudian menganggap dokumen itu milik tahap2. Relasi documents tidak mempunyai orderBy. Tabel mempunyai unique index `(sidang_ta_registration_id, document_type)`; urutan hasil tanpa orderBy tidak menjamin urutan insert. Jangan bergantung pada mesin database atau rencana query tertentu.

Bila yang dipilih `bpp_payment_proof`, partial dengan benar menulis **Tahap3**, sehingga assertion Tahap2 gagal. Mengirim dokumen itu ke endpoint tahap2 juga ditolak oleh aturan `documents` yang hanya menerima CAPTURES. Output pengguna belum memuat jenis dokumen aktual atau response JSON lengkap; contoh bpp ini menunjukkan jalur kegagalan yang konsisten dengan kedua assertion. Kekeliruan asumsi fixture terbukti dari kode dan tidak bergantung pada jenis pertama yang kebetulan dipilih database.

Pilih `level_2_result_capture` secara eksplisit pada kedua test. Jenis itu termasuk CAPTURES dan menerima PDF. Tidak mengubah relasi/orderBy produksi, label tahap, validasi request, status response yang diharapkan, maupun versi draft.

## 2. Ganti dua test lengkap

Tidak ada file baru atau perintah make. Kerjakan pada folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
```

File `tests/Feature/SidangTaReviewFeedbackTest.php`: ganti dua test dengan nama berikut, jangan menambahkan duplikat. beforeEach/import/test lain tetap. Test wizard sekaligus memeriksa file baru benar-benar tersimpan terpisah dari file sumber dan versi naik menjadi2.

```php
test('p3_revision_start_shows_source_document_feedback_without_general_note', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Perbaiki PDF']);
    $crawler = new Crawler($this->actingAs($this->owner)->get(route('sidang-ta-registrations.edit', $source))->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->count())->toBe(1);
    expect($crawler->filter('[data-feedback-type="'.$document->document_type.'"]')->text(null, true))
        ->toContain('Perbaiki PDF')->toContain('Tahap 2');
    expect($source->fresh()->review_notes)->toBeNull();
});

test('p3_revision_wizard_keeps_source_feedback_after_new_draft_file_selection_is_saved', function () {
    $source = ($this->makeSource)(['status' => 'needs_review'], 'verified');
    $document = $source->documents()
        ->where('document_type', 'level_2_result_capture')->firstOrFail();
    $document->update(['status' => 'rejected', 'notes' => 'Catatan dokumen asal']);
    $id = $this->actingAs($this->owner)->postJson(route('sidang-ta-drafts.revision', $source))->assertCreated()->json('data.id');
    $url = route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]);
    $this->get($url)->assertOk()->assertSee('Catatan dokumen asal');
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 2]), [
        'version' => 1, 'documents' => [$document->document_type => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf')],
    ])->assertOk()->assertJsonPath('data.version', 2);
    $saved = \App\Models\SidangTaRegistrationDraftDocument::query()
        ->where('draft_id', $id)->where('document_type', $document->document_type)->firstOrFail();
    expect($saved->file_name)->toBe('baru.pdf');
    expect($saved->source_document_id)->toBeNull();
    expect($saved->file_path)->not->toBe($document->file_path);
    Storage::disk('local')->assertExists($saved->file_path);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-review-feedback]')->text(null, true))->toContain('Catatan dokumen asal')
        ->toContain('Berkas yang diganti akan ditinjau kembali');
    expect($document->fresh()->notes)->toBe('Catatan dokumen asal');
    Storage::disk('local')->assertExists($document->file_path);
});
```

## 3. Satu penempatan partial yang terlewat

Di implementasi asli `resources/views/sidang_ta_registrations/show.blade.php`, include status/catatan sekarang berada setelah tombol Lihat berkas, sebagai kolom ketiga pada flex row. Instruksi42 meletakkannya di dalam metadata setelah paragraf nama/ukuran. Tidak menyebabkan dua test gagal, tetapi pada desktop catatan dapat terpisah dari nama dokumennya.

**Penting:** source terbaru kehilangan @empty dan menyebabkan500. Ikuti [44](44-koreksi-error500-detail-sidang-ta.md) terlebih dahulu. Untuk menghindari batas salin yang ambigu, ganti seluruh section Dokumen yang diunggah setelah include detail-academic, sampai sebelum section data-registration-history, dengan blok lengkap ini; loop/empty ikut disertakan.

```blade
        <section class="campus-card overflow-hidden">
            <h2 class="border-b border-slate-100 px-5 py-4 text-lg font-bold text-slate-900 sm:px-6">
                Dokumen yang diunggah
            </h2>
            <div data-final-documents class="divide-y divide-slate-100 px-5 sm:px-6">
                @forelse($registration->documents as $document)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-relaxed text-slate-800">
                                {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type }}
                            </p>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB
                            </p>
                            @include('sidang_ta_registrations.partials.document-review-status')
                        </div>
                        <a data-document-preview
                           class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                           href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                           data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
                           data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
                           data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                           target="_blank" rel="noopener" aria-haspopup="dialog"
                           x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                            Lihat berkas
                        </a>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Belum ada dokumen tersimpan.</p>
                @endforelse
            </div>
        </section>
```

## 4. Hasil rescan awal sebelum dipindah

Catatan waktu: hasil di bawah berasal dari scan sebelum pengguna mengganti row. Scan susulan menemukan @empty hilang pada source terbaru, direkam dan diperbaiki pada44; status itu menggantikan kesimpulan sintaks halaman detail dari scan awal.

- Perbedaan source asli dan dadu-test tepat15file P3 yang disebut42. File belum tersalin ke workspace sesuai keterangan pengguna.
- Helper eligibility, service review, empat partial baru, dan kedua file test baru cocok dengan snippet42 awal jika whitespace diabaikan. Controller, route, pemasangan partial feedback revisi di wizard/revision-start, label log Indonesia, serta fixture Finalization verified sebelum approval sudah terpasang.
- Tidak menemukan blocker lain pada pemeriksaan statis jalur permission, relasi dokumen terhadap pengajuan, required cabang15/14, versi stale, lock Registration→Document, audit dalam transaksi, terminal read-only, guard Setujui, atau reset status pending pada finalizer existing. Temuan penempatan partial diperbaiki pada bagian3.
- Tujuh file PHP yang berubah lulus `php -l`. Probe memakai konstanta Requirements aktual tanpa bootstrap/DB membuktikan bpp adalah tahap3/tidak diterima tahap2, sedangkan level_2_result_capture tahap2/diterima tahap2. Ini bukan eksekusi Feature test pada DB pengguna.
- Panduan42 bagian9 juga diperbarui dengan dua test terkoreksi agar tidak menyalin fixture salah lagi. Jumlah test tidak berubah: **44 fokus,183 keseluruhan,12 Node**.

## 5. Test ulang dan manual

Jalankan dua test yang diperbaiki dahulu:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewFeedbackTest.php --filter='p3_revision_start_shows_source_document_feedback_without_general_note|p3_revision_wizard_keeps_source_feedback_after_new_draft_file_selection_is_saved'
```

Lalu seluruh fokus P3:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Target44passed. Jalankan seluruh suiteSidangTA dan tiga Node test sesuai42 bagian10; target183PHP/12Node. Jumlah assertion dapat bertambah karena pengecekan file draft yang baru ditambahkan.

Manual tambahan setelah semua test lulus:

1. Petugas meminta perbaikan satu dokumen tahap2 dengan catatan, tanpa mengisi catatan umum pengajuan.
2. Mahasiswa membuka Mulai revisi: catatan dokumen dan Tahap2 tampil walaupun catatan umum kosong.
3. Buka wizard tahap2, ganti berkas itu dan Simpan. Catatan pengajuan sumber tetap tampil; file sumber belum diganti sampai revisi dikirim.
4. Di detail mahasiswa, status/catatan berada di bawah nama/ukuran dokumen; tombol Lihat berkas tetap terpisah dan berfungsi.
5. Selesaikan seluruh manual/konkurensi42 sebelum menutup P3.

Sesudah lulus, salin seluruh15file P3 pada42, termasuk test terkoreksi dan student show, ke dadu-test untuk rescan akhir. Jangan commit petugas dahulu. Asisten tidak menjalankan Feature/DB/build atau manual browser; belum menyatakan P3 lulus dan tidak mengubah source aplikasi.
