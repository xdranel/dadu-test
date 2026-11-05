# 46 — Meet A: riwayat mahasiswa disembunyikan dan audit petugas dapat dibuka

**Goal:** menerapkan satu bagian revisi meet yang sudah pasti tanpa menunggu JSON.
**Architecture:** audit tetap di DB; student detail tidak merender atau memuat relasi logs. Admin detail memakai details/summary native default tertutup, chevron berputar saat terbuka. Tanpa JavaScript/dependency/migrasi baru.
**Spec:**45 disetujui pengguna untuk dikerjakan bertahap; yang belum pasti memakai status Belum tersedia, bukan dianggap lulus.

## 1. Urutan dan batas

CheckpointA ini aman dikerjakan dahulu. Checkpoint berikutnya: registry requirements/dataotomatis/EPRTpolicy dan panel, kemudian targetedrevision/reviewerlock/deadline, kemudian grid2kolom+status/ikon/export. Susunan ini mendahulukan kontrakdata sebelum menyusun kartu yang jumlahnya berubah. Tidak perlu impor JSON untuk46; JSON tersedia baru berguna saat checkpointimport berikutnya. Sumberdata yang belum tersedia tetap pending, tidak diisi tebakan.

Belum pindahkan sourceparsial untuk mengejar penutupan P3lama. Terapkan46 di folder asli, jalankan test/manual, lalu laporkan. Jangan menjalankan migrate:fresh; tidak ada perubahan schema. Jangan commit/push petugas dahulu.

Fileexisting5: controller, student show, admin show, SidangTaDetailUiTest, SidangTaReviewFeedbackTest. Tidak ada filebaru sehingga tidak ada make command.

## 2. Tulis test dahulu

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
```

- [ ] Pada tests/Feature/SidangTaDetailUiTest.php ganti test lama bernama detail_has_indonesian_history_private_download_and_no_revision_for_submitted_status dengan test lengkap ini. Jangan menambahkan duplikat. Pengecekan preview/private download/audit tetap dipertahankan.

```php
test('detail_hides_history_keeps_private_preview_and_preserves_audit', function () {
    $registration = ($this->makeRegistration)(['status' => 'submitted']);
    $path = 'sidang_ta/detail-ui/berkas.pdf';
    Storage::disk('local')->put($path, '%PDF-detail');
    $document = $registration->documents()->create([
        'document_type' => 'thesis_draft', 'file_name' => 'berkas.pdf',
        'file_path' => $path, 'file_size' => 11, 'file_type' => 'pdf',
    ]);
    $registration->logAction('submitted', null, 'submitted', 'Pendaftaran pertama.', $this->user->id);
    $registration->logAction('needs_review', 'submitted', 'needs_review', 'Periksa dokumen.', $this->user->id);
    $registration->logAction('resubmitted', 'needs_review', 'submitted', '<b>Catatan literal</b>', $this->user->id);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    expect($crawler->filter('[data-registration-history]')->count())->toBe(0);
    expect($crawler->text(null, true))->not->toContain('Pendaftaran pertama.')
        ->not->toContain('Periksa dokumen.')->not->toContain('Catatan literal');
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $preview = $crawler->filter('a[data-document-preview]');
    expect($preview->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($preview->attr('data-download-url'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect(preg_split('/\s+/', trim($preview->attr('class'))))->toContain('btn')->toContain('btn-secondary');
    $this->get($preview->attr('href'))->assertOk();
    $this->get($preview->attr('data-download-url'))->assertDownload('berkas.pdf');
    expect($registration->logs()->where('action', 'resubmitted')->exists())->toBeTrue();
});
```

- [ ] Pada tests/Feature/SidangTaReviewFeedbackTest.php ganti test p3_student_detail_shows_escaped_document_feedback_and_indonesian_log dengan ini. Catatan perbaikan mahasiswa tetap terlihat dan escaped; yang disembunyikan hanya riwayat audit.

```php
test('p3_student_detail_shows_escaped_feedback_without_history', function () {
    $source = ($this->makeSource)();
    $document = $source->documents()->firstOrFail();
    ($this->reviewDoc)($source, $document, ['status' => 'rejected', 'notes' => '<b>Perbaiki scan</b>'])->assertOk();
    $listing = $this->get(route('admin.sidang-ta-registrations.index'))->assertOk();
    expect($listing->viewData('counts')['needs_review'])->toBe(1);
    $crawler = new Crawler($listing->getContent());
    expect($crawler->filter('[data-ta-row="'.$source->id.'"] [data-ta-doc="'.$document->document_type.'"]')->attr('data-doc-state'))->toBe('rejected');
    $response = $this->actingAs($this->owner)->get(route('sidang-ta-registrations.show', $source))->assertOk();
    $crawler = new Crawler($response->getContent());
    $status = $crawler->filter('[data-student-document-state="'.$document->id.'"]');
    expect($status->text(null, true))->toContain('Perlu perbaikan')->toContain('<b>Perbaiki scan</b>');
    expect($status->filter('b')->count())->toBe(0);
    expect($crawler->filter('[data-registration-history]')->count())->toBe(0);
    expect($source->logs()->where('action', 'document_rejected')->exists())->toBeTrue();
    expect($crawler->filter('[data-doc-review-form]')->count())->toBe(0);
});
```

- [ ] Tambahkan dua test berikut di SidangTaReviewFeedbackTest.php dengan beforeEach/import existing, tanpa filetestbaru:

```php
test('meet_staff_history_is_collapsed_localized_and_escaped', function () {
    $source = ($this->makeSource)();
    $source->logAction('document_rejected', 'submitted', 'submitted', '<b>Catatan audit internal</b>', $this->reviewer->id);
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    $history = $crawler->filter('details[data-staff-registration-history]');
    expect($history->count())->toBe(1);
    expect($history->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($history->filter('summary')->count())->toBe(1);
    expect($history->filter('summary svg')->count())->toBe(1);
    expect($history->text(null, true))->toContain('Riwayat status')
        ->toContain('Dokumen diminta perbaikan')->toContain('<b>Catatan audit internal</b>');
    expect($history->filter('b')->count())->toBe(0);
    expect($source->logs()->count())->toBe(1);
});

test('meet_staff_empty_history_is_collapsed_with_fallback', function () {
    $source = ($this->makeSource)();
    $crawler = new Crawler($this->get(route('admin.sidang-ta-registrations.show', $source))->assertOk()->getContent());
    $history = $crawler->filter('details[data-staff-registration-history]');
    expect($history->count())->toBe(1);
    expect($history->getNode(0)->hasAttribute('open'))->toBeFalse();
    expect($history->text(null, true))->toContain('Belum ada riwayat status.');
});
```

- [ ] Jalankan test fokus sebelum mengubah produksi, pastikan perilaku baru gagal:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```

## 3. Student detail

- [ ] File app/Http/Controllers/SidangTaRegistrationController.php, **hanya method show() mahasiswa**, ganti:

```php
$registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
```

dengan:

```php
$registration = $sidang_ta_registration->load(['period', 'documents']);
$registration->unsetRelation('logs');
```

Jangan mengubah load logs pada adminShow. Ownership/permission dan canRevise tetap. unsetRelation memastikan relasi yang kebetulan sudah termuat juga tidak diteruskan sebagai payload viewstudent; ini bukan penghapusan auditDB.

- [ ] File resources/views/sidang_ta_registrations/show.blade.php hapus seluruh section beratribut data-registration-history, dari opening section itu sampai closing section sebelum div tombolKembali/PerbaikiPengajuan. Jangan menghapus loopdokumen atau tombolrevisi.
- [ ] Hapus definisi variabel $actionLabel (static fn match lengkap) pada blok @php awal student show karena hanya dipakai riwayat yang dihapus. Pertahankan $statusLabel yang masih dipakai badge. Catatan umum petugas dan partial status/catatan dokumen tetap.

## 4. Riwayat petugas

- [ ] File resources/views/sidang_ta_registrations/admin/show.blade.php, ganti **seluruh section Riwayat status** di bawah catatan petugas dengan blok berikut. Jangan mengganti section Dokumen pengajuan. $actionLabels existing tetap dipakai:

```blade
<details data-staff-registration-history class="campus-card group p-5">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-bold text-slate-800 [&::-webkit-details-marker]:hidden">
        <span>Riwayat status</span>
        <svg aria-hidden="true" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
        </svg>
    </summary>
    <ol class="mt-4 space-y-3">
        @forelse($registration->logs as $log)
            <li class="border-l-2 border-rose-200 pl-3">
                <p class="text-sm font-semibold text-slate-800">{{ $actionLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $log->performed_at?->format('d/m/Y H:i') }}</p>
                @if(filled($log->notes))
                    <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">{{ $log->notes }}</p>
                @endif
            </li>
        @empty
            <li class="text-sm text-slate-500">Belum ada riwayat status.</li>
        @endforelse
    </ol>
</details>
```

Tidak menambahkan atribut open. Summary adalah kontrol native yang dapat difokuskan dan diaktifkan keyboard; jangan menambah rolebutton/onclick yang menggandakan perilaku. Chevron decorative memakai aria-hidden. Browser akan menutup kembali saat halamanreload; status collapsed bukan preferensi tersimpan.

## 5. Verifikasi sesudah kode

```bash
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDocumentReviewTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaRevisionDraftTest.php
```

Jika44bagian6 sudah diterapkan lengkap, targetfokus pertama18 (8DetailUi+10Feedback), fokusP3kedua48, seluruhsuiteTA187 dan12Node. Jika44bagian6belumditerapkan, baselineFeedback6 dan totallebihkecil2; jangan menggandakan testuntukmengejarangka. Hitunghasilaktual danlaporkan. Targetlama yangmengharapkanstudenthistory sengajadiganti, bukan dilonggarkan tanpa pengganti.

Manual:

1. Student membuka detail submitted dan needs_review: Riwayat status tidakada; statusutama, catatanperbaikan, preview, tombolrevisi yangsesuai tetaptampil.
2. Petugas membuka detailpengajuan: Riwayatstatus tertutup. Kliksummary atau fokusdenganTab laluEnter/Space; daftarlogterbuka, chevronberputar; klikkembalimenutup.
3. Pengajuan tanpalog menampilkan fallback sesudahdibuka; logdengan karakterHTML menjadi teksbiasa.
4. Uji mobile dan desktop. Previewberkas dan reviewdocument tetapberfungsi; anchor44tetapmengarahkan kekartu sesudahaksi.

Setelah lulus, laporkan hasil dan statusfile. Checkpoint ini tidakmenyelesaikan seluruhrevisimeet; checkpointberikutnya akanmemuatrequirements danfixtureJSONsintetis yangjelas, tanpamenganggapdatahilanglulus. Importyangdiperlukanakandiberitahukansebelumpengujianintegrasi.
