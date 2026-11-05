# 44 — Error500 detail setelah kirim: @empty pada loop dokumen hilang

## 1. Penyebab yang terkonfirmasi

Log asli `storage/logs/laravel.log`, entri terakhir yang diperiksa 2026-11-03 06:29:35, mencatat `syntax error, unexpected token "endif"` pada `resources/views/sidang_ta_registrations/show.blade.php:95`.

Source saat scan memiliki `@forelse($registration->documents as $document)` dan `@endforelse`, tetapi **tidak memiliki @empty** sebelum teks Belum ada dokumen tersimpan. Blade mengompilasi pasangan yang tidak lengkap ini menjadi PHP tidak valid. Itulah penyebab error500 saat membuka detail. Setelah submit berhasil, browser yang diarahkan ke detail juga mengalami error render yang sama. Status penyimpanan pengajuan tertentu tetap harus diperiksa, jangan menganggap500 membuktikan insert gagal dan langsung mengirim ulang.

Ini terpisah dari dua fixture test pada43. Mengganti fixture tidak memperbaiki sintaks view. migrate:fresh --seed tidak mengubah template sehingga tidak memperbaiki masalah ini. Tidak perlu menjalankan reset database lagi.

Panduan43 awal memberi potongan row dan meminta loop/empty tetap dipertahankan, tetapi batas salinnya masih mudah terlewat. Versi43 kini memakai section lengkap yang sama dengan44 ini.

## 2. Ganti section dokumen lengkap

Tidak ada file baru atau perintah make. Pada folder asli, buka `resources/views/sidang_ta_registrations/show.blade.php`.

Ganti seluruh section **Dokumen yang diunggah**, mulai dari `<section class="campus-card overflow-hidden">` setelah include detail-academic, sampai `</section>` sebelum section data-registration-history. Jangan mengganti section identitas di bagian atas yang juga memakai class campus-card overflow-hidden.

Blok lengkap berikut sudah memuat forelse, empty, endforelse dan status dokumen di dalam metadata:

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

Pastikan ada dua loop terpisah yang tetap utuh di halaman: loop dokumen pada blok di atas dan loop riwayat status setelahnya. Masing-masing mempunyai @empty sendiri.

## 3. Bersihkan cache view lalu test

Setelah source diperbaiki, jalankan sendiri di folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
```

Ini hanya membersihkan hasil kompilasi Blade; tidak menghapus database/upload. Jika source masih kehilangan @empty, perintah ini sendiri tidak akan menyelesaikan error.

Jalankan test yang benar-benar merender halaman detail dan feedback. Kode test lengkap sudah ada pada42/43, tidak perlu file test baru:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```

Target14test (8DetailUi+6ReviewFeedback). Jika dua fixture belum diganti, terapkan43 bagian2 terlebih dahulu.

Lalu fokus P3, target44:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Setelah lulus, lanjutkan suiteSidangTA183/Node12 dan seluruh manual42. Tidak ada tambahan test pada44; suite existing sudah merender detail dan akan menangkap parse error ini.

Manual khusus500: dari Riwayat pengajuan buka Detail pengajuan yang sudah ada; periksa status/notes/preview. Setelah itu uji satu pengajuan baru lengkap sampai Kirim dan pastikan halaman tujuan tampil. Tidak perlu migrate:fresh untuk kedua pemeriksaan ini. Buka detail pengajuan yang sebelumnya memunculkan500 dahulu sebelum mencoba kirim ulang.

## 4. Bukti scan dan batas verifikasi

Asisten membaca log dan mengompilasi20template TA aktual dengan vendor Blade di memori lalu menjalankan php -l pada hasil kompilasi; tag komponen x-* dikeluarkan hanya untuk probe sintaks agar tidak bootstrap aplikasi. **19lulus,1gagal: student show**. Mengembalikan satu @empty dalam salinan di memori membuat halaman lengkap lulus lint. Ini memeriksa sintaks template; bukan pengujian render komponen/routing/DB/browser.

Tidak ada indikasi dari error terbaru ini bahwa diperlukan migrasi/schema atau perubahan service review. Source aplikasi tidak diubah asisten. Perbaikan fisik masih harus diterapkan pengguna, diuji, baru dipindahkan bersama15file P3 sesuai42. Belum dinyatakan siap commit.

Rujukan sintaks yang diperiksa melalui Context7: [Laravel13 Blade loops](https://github.com/laravel/docs/blob/13.x/blade.md#loops).


## 5. Koreksi tambahan: indentasi masuk ke nilai textarea catatan

Pengguna melaporkan seluruh unit/manual sudah lulus setelah perbaikan di atas, tetapi source belum dipindahkan. Scan berikutnya menemukan textarea di kedua partial petugas ditulis dalam beberapa baris dengan indentasi sebelum/sesudah `{{ $notesValue }}`. HTML memperlakukan whitespace di dalam textarea sebagai bagian nilai input, sehingga setelah Minta perbaikan/redirect, catatan terlihat bergeser dan memiliki baris kosong. Service sudah trim catatan saat menyimpan; sumber whitespace tampilan ini ada pada template, bukan kebutuhan migrasi atau perubahan DB.

Tidak ada file baru, perintah make, atau perubahan service. Ganti hanya elemen textarea di dua file asli berikut. **Isi ekspresi Blade harus langsung setelah `>` pembuka dan langsung sebelum `</textarea>`**, tanpa newline/spasi tambahan dari template. Formatter editor jangan memecah bagian isi textarea menjadi baris berindentasi. Atribut boleh dipisah baris selama tidak menambah whitespace di antara tag dan nilai.

Pada `resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php`, ganti seluruh elemen textarea catatan dokumen dengan:

```blade
<textarea id="document-notes-{{ $document->id }}" name="notes" maxlength="2000" class="campus-input min-h-20">{{ $notesValue }}</textarea>
```

Pada `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`, ganti seluruh elemen textarea catatan umum dengan:

```blade
<textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea>
```

Label/form/error/button dan perhitungan old input/version tetap. Tetap gunakan escaped output `{{ }}` agar catatan tidak menjadi HTML. Jangan memakai `{!! !!}` atau trim lewat JavaScript. Baris baru yang sengaja diketik mahasiswa/petugas tetap bagian teks catatan; perbaikan ini menghilangkan whitespace tambahan dari template. Snippet42 bagian6 sudah menulis textarea dengan format yang benar ini, jadi tidak perlu mengubah canonical code42.

Setelah perubahan, jalankan:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
```

Manual verifikasi:

1. Petugas membuka detail pengajuan yang masih dapat ditinjau, tulis `fix en iki` pada catatan dokumen dan klik Minta perbaikan. Sesudah reload, isi textarea tepat `fix en iki` tanpa indentasi/baris kosong tambahan.
2. Muat ulang sekali lagi dan simpan catatan yang sama; whitespace tambahan tidak muncul kembali.
3. Coba catatan dua baris yang disengaja; dua baris itu tetap ada sesudah simpan.
4. Uji juga catatan umum pada keputusan Minta Revisi. Saat validasi gagal pada form lain atau versi stale, catatan/versi tetap mengikuti form masing-masing sesuai42.

Ini perubahan markup kecil; tidak menambah file/jumlah test. Jika ingin menjalankan ulang fokus, gunakan command44bagian3 (44P3), target keseluruhan tetap183PHP/12Node. Asisten tidak menjalankan suite DB/browser dan tidak mengubah source. Setelah koreksi manual lulus, salin15file P3 ke dadu-test untuk rescan akhir sebelum commit petugas.


## 6. Tetap di kartu dokumen setelah Minta perbaikan/Verifikasi

Pengguna menawarkan dua pilihan: daftar vertikal tetap dengan perbaikan posisi setelah aksi, atau layout kanan–kiri menurut tahap. Rekomendasi untuk koreksi ini adalah pilihan1 agar daftar tetap mudah diikuti tanpa mengubah desain seluruh halaman. Layout menurut tahap dapat dinilai sebagai perubahan desain tersendiri. Koreksi ini dikerjakan **sebelum memindahkan file P3**.

Gunakan anchor HTML pada article dokumen, lalu redirect ke anchor yang sama setelah aksi. Browser membawa kartu yang baru diproses ke area tampilan; ini mengembalikan konteks dokumen, bukan menyimpan koordinat scroll piksel sebelumnya. Tidak perlu AJAX, JavaScript, localStorage atau dependency baru. Nilai catatan/status tetap berasal dari hasil simpan server. Error validasi dan konflik versi HTML juga kembali ke kartu yang sama; kontrak JSON200/422/409 tetap.

### 6.1 Tambahkan id pada article

File `resources/views/sidang_ta_registrations/admin/show.blade.php`. Di dalam loop dokumen, ganti hanya opening tag article dengan berikut; seluruh isi dan penutup article/forelse/empty tetap. `scroll-mt-24` memberi jarak atas ketika browser menuju anchor:

```blade
<article id="review-document-{{ $document->id }}" data-ta-detail-document="{{ $document->id }}"
         class="scroll-mt-24 flex flex-wrap items-center justify-between gap-3 py-4">
```

Jangan memberikan id yang sama ke form/elemen lain. Anchor menunjuk satu article yang memuat nama, preview dan form dokumen terkait.

### 6.2 Ganti method controller lengkap

File `app/Http/Controllers/SidangTaRegistrationController.php`, ganti hanya method reviewDocument. Request, model, ValidationException dan HttpExceptionInterface sudah di-import pada source; tidak perlu import/dependency baru. Method review keputusan umum tetap.

```php
    public function reviewDocument(Request $request, SidangTaRegistration $sidang_ta_registration,
                                   SidangTaRegistrationDocument $document)
    {
        $returnUrl = route('admin.sidang-ta-registrations.show', $sidang_ta_registration)
            .'#review-document-'.$document->id;
        try {
            $validated = $request->validate([
                'status' => ['required', 'in:verified,rejected'],
                'notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
                'registration_version' => ['required', 'integer', 'min:1'],
            ], ['notes.required_if' => 'Isi catatan perbaikan dokumen.']);
            $registration = app(\App\Services\SidangTaRegistrationReview::class)->document(
                $request->user(), $sidang_ta_registration, $document, (int) $validated['registration_version'],
                $validated['status'], $validated['notes'] ?? null
            );
        } catch (ValidationException $exception) {
            if (!$request->expectsJson()) $exception->redirectTo($returnUrl);
            throw $exception;
        } catch (HttpExceptionInterface $exception) {
            if ($request->expectsJson() || $exception->getStatusCode() !== 409) throw $exception;
            return redirect()->to($returnUrl)
                ->withErrors(['registration_version' => $exception->getMessage()])->withInput();
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $registration->id,
                'status' => $registration->status, 'lock_version' => $registration->lock_version]]);
        }
        return redirect()->to($returnUrl)->with('success', 'Review dokumen berhasil disimpan.');
    }
```

Penting: request validate berada di dalam try agar error field bisa diarahkan ke kartu. Exception validasi dilempar lagi agar errorbag/old input dan respons JSON ditangani Laravel seperti semula. Versi stale tidak diubah menjadi versi terbaru secara otomatis.

### 6.3 Ganti satu test existing dan tambahkan dua test

Tidak ada file baru atau make command. Pada `tests/Feature/SidangTaReviewFeedbackTest.php`, ganti test existing berikut agar assertion redirect mengikuti anchor baru; semua pengecekan versi/old input tetap:

```php
test('p3_validation_keeps_only_failed_form_old_version_without_rebasing_other_forms', function () {
    $source = ($this->makeSource)();
    $documents = $source->documents()->get();
    $document = $documents->first();
    $other = $documents->get(1);
    $url = route('admin.sidang-ta-registrations.show', $source);
    $this->from($url)->post(route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $document,
    ]), ['registration_version' => 1, 'status' => 'rejected', 'notes' => ['invalid'],
        '_review_form' => 'document:'.$document->id])->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    $source->lock_version++;
    $source->save();
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
    expect($crawler->filter('[data-doc-review-form="'.$other->id.'"] input[name="registration_version"]')->attr('value'))->toBe('2');
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe('2');
    ($this->reviewDoc)($source, $document, ['registration_version' => 1])->assertStatus(409);
    expect($source->fresh()->lock_version)->toBe(2);
});
```

Tambahkan dua test berikut pada file yang sama, memakai beforeEach/import existing. Jangan menggandakan test existing di atas:

```php
test('p3_html_document_review_returns_to_matching_document_card', function () {
    foreach (['verified', 'rejected'] as $status) {
        $source = ($this->makeSource)();
        $document = $source->documents()->firstOrFail();
        $url = route('admin.sidang-ta-registrations.show', $source);
        $response = $this->post(route('admin.sidang-ta-registrations.documents.review', [
            'sidang_ta_registration' => $source, 'document' => $document,
        ]), ['registration_version' => 1, 'status' => $status,
            'notes' => $status === 'rejected' ? 'Perbaiki berkas ini' : null,
            '_review_form' => 'document:'.$document->id]);
        $response->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasNoErrors();
        expect($document->fresh()->status)->toBe($status);
        $crawler = new Crawler($this->get($url)->assertOk()->getContent());
        expect($crawler->filter('article#review-document-'.$document->id)->count())->toBe(1);
        expect($crawler->filter('article#review-document-'.$document->id.' [data-doc-review-form="'.$document->id.'"]')->count())->toBe(1);
    }
});

test('p3_html_document_validation_and_stale_version_return_to_matching_card', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    $url = route('admin.sidang-ta-registrations.show', $source);
    $action = route('admin.sidang-ta-registrations.documents.review', [
        'sidang_ta_registration' => $source, 'document' => $document,
    ]);
    $this->from($url)->post($action, ['registration_version' => 1, 'status' => 'rejected',
        'notes' => '', '_review_form' => 'document:'.$document->id])
        ->assertRedirect($url.'#review-document-'.$document->id)->assertSessionHasErrors('notes');
    expect($document->fresh()->status)->toBe('pending');
    expect($source->fresh()->lock_version)->toBe(1);
    ($this->reviewDoc)($source, $document)->assertOk()->assertJsonPath('data.lock_version', 2);
    $this->post($action, ['registration_version' => 1, 'status' => 'rejected',
        'notes' => 'Catatan dari halaman lama', '_review_form' => 'document:'.$document->id])
        ->assertRedirect($url.'#review-document-'.$document->id)
        ->assertSessionHasErrors('registration_version');
    expect($document->fresh()->status)->toBe('verified');
    expect($source->fresh()->lock_version)->toBe(2);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-doc-review-form="'.$document->id.'"] input[name="registration_version"]')->attr('value'))->toBe('1');
});
```

### 6.4 Jalankan test dan manual

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewFeedbackTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Target **8Feedback,46fokus P3,185suite PHP,12Node**. Ini menggantikan target44/183 sebelum dua test ditambahkan. Jumlah file yang dipindahkan tetap15 karena semuanya file P3 existing.

Manual: buka detail petugas, scroll ke dokumen paling bawah, isi catatan lalu Minta perbaikan. Setelah reload, URL berakhir #review-document-ID dan kartu itu tetap terlihat. Ulangi pada dokumen lain dengan Verifikasi, dan pada dokumen atas. Coba Minta perbaikan tanpa catatan: pesan notes muncul pada kartu yang sama. Uji dua tab untuk versi stale; jangan otomatis mengirim ulang sebelum memuat versi terbaru. Periksa desktop dan layar kecil. Test HTTP memverifikasi target anchor, sedangkan posisi visual browser harus diperiksa manual.

Asisten memeriksa API redirect validasi pada vendor Laravel yang terpasang (ValidationException::redirectTo dan Handler::invalid), serta dokumentasi Context7 [Laravel validation](https://github.com/laravel/docs/blob/13.x/validation.md). Belum menjalankan Feature/DB/browser untuk koreksi ini. Source tidak diubah asisten. Setelah test/manual lulus, baru salin15file lalu minta rescan akhir dan commit message petugas.
