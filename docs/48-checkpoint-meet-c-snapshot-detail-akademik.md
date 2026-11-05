# 48 — Meet C: detail akademik sesuai panel baru tanpa mengubah snapshot historis

**Goal:** detail mahasiswa dan peninjauan petugas menampilkan field akademik yang sama dengan panel47: identitas, metrik, tingkat2, SKPI, KRS TA2 dan judul.
**Architecture:** mengubah shared partial detail-academic existing, hanya memakai academic_snapshot tersimpan. Field retired disimpan tetapi disaring dari extra. Tidak ada lookup data live, migrasi, perubahan finalizer, atau importJSON.
**Spec:**45,47; checkpoint47 dilaporkan unit/manual lulus dan tersalin,3file dibandingkan identik.

## 1. Urutan dan file

- [ ] Kerjakan di folder asli, bukan dadu-test:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
```

Dua fileexisting: resources/views/sidang_ta_registrations/partials/detail-academic.blade.php dan tests/Feature/SidangTaDetailUiTest.php. Tidak ada filebaru sehingga tidak ada php artisan make. Riwayat46, panel47, privatepreview, permission dan reviewdokumen tetap.

## 2. Test dahulu

Di SidangTaDetailUiTest.php ganti satu test existing berikut dengan versi lengkap. Ubah ekspektasi pembimbing yang sengaja tidak ditampilkan, tetapi pertahankan perlindungan snapshot/nilaihistoris:

```php
test('detail_uses_saved_academic_snapshot_after_student_data_changes', function () {
    $registration = ($this->makeRegistration)();
    $this->student->update(['name' => 'Nama Terbaru Berbeda', 'gpa' => 3.99]);
    $this->student->finalProject()->create(['thesis_title_id' => 'Judul TA Terbaru Berbeda']);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->count())->toBe(1);
    expect($academic->text(null, true))->toContain('Nama Saat Diajukan')->toContain('Judul TA Historis')
        ->toContain('Historical Thesis Title')->not->toContain('Dosen Historis')
        ->not->toContain('Nama Terbaru Berbeda')->not->toContain('Judul TA Terbaru Berbeda');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('3,25');
    expect($academic->filter('[data-academic-group]')->count())->toBe(4);
    $stored = $registration->fresh()->academic_snapshot;
    $expected = $this->snapshot;
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});
```

Tambahkan dua test berikut pada file yang sama memakai fixture/import/beforeEach existing:

```php
test('meet_detail_uses_saved_krs_label_and_hides_retired_snapshot_fields_for_student_and_staff', function () {
    $snapshot = array_replace($this->snapshot, [
        'ta2_krs_status' => 'eligible', 'ta2_krs_label' => 'Terdaftar saat pengajuan',
        'level_3_status' => 'HIDDEN-LEVEL3', 'bpp_status' => 'HIDDEN-BPP',
        'ibasila_status' => 'HIDDEN-BASILA', 'skpi_tak' => 'HIDDEN-AVAILABILITY',
        'advisor_1' => 'HIDDEN-ADVISOR', 'examiner_1' => 'HIDDEN-EXAMINER',
        'exam_date' => 'HIDDEN-DATE', 'exam_time' => 'HIDDEN-TIME', 'exam_location' => 'HIDDEN-LOCATION',
    ]);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $this->student->update(['name' => 'Live student changed']);
    $response = $this->get(route('sidang-ta-registrations.show', $registration))->assertOk();
    $reviewer = \App\Models\User::factory()->create(['status' => 'active', 'auth_source' => 'manual',
        'must_change_password' => false]);
    $reviewer->givePermissionTo('sidang_ta_registration_review');
    $staff = $this->actingAs($reviewer)->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    foreach ([$response, $staff] as $page) {
        $academic = (new Crawler($page->getContent()))->filter('[data-academic-detail]');
        expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Terdaftar saat pengajuan');
        expect($academic->text(null, true))->not->toContain('HIDDEN-')->not->toContain('Live student changed');
        expect($academic->filter('[data-academic-group="lecturers"], [data-academic-group="schedule"]')->count())->toBe(0);
        expect($academic->filter('[data-snapshot-field="ta2_krs_status"]')->count())->toBe(0);
        expect($academic->text(null, true))->not->toContain('Status BPP')->not->toContain('Sidang tingkat 3');
    }
    expect($registration->fresh()->academic_snapshot)->toEqual($snapshot);
});

test('meet_legacy_snapshot_without_krs_label_does_not_derive_one_from_live_data_or_raw_status', function () {
    $snapshot = array_replace($this->snapshot, ['ta2_krs_status' => 'eligible']);
    unset($snapshot['ta2_krs_label']);
    $registration = ($this->makeRegistration)(['academic_snapshot' => $snapshot]);
    $academic = (new Crawler($this->get(route('sidang-ta-registrations.show', $registration))
        ->assertOk()->getContent()))->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="ta2_krs_label"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->text(null, true))->not->toContain('eligible');
    expect($registration->fresh()->academic_snapshot)->not->toHaveKey('ta2_krs_label');
});
```

Jalankan sebelum source diubah; ekspektasi baru harus gagal:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php
```

## 3. Partial lengkap

Ganti seluruh isi resources/views/sidang_ta_registrations/partials/detail-academic.blade.php dengan:

```blade
@php
    $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
    $display = static function ($value): string {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) {
            return 'Belum tersedia';
        }
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    };
    $number = static fn ($value, int $digits = 0) => is_numeric($value)
        ? number_format((float) $value, $digits, ',', '.') : $display($value);
    $identity = ['Nama mahasiswa' => 'student_name', 'NIM' => 'student_number',
        'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'];
    $metrics = ['IPK' => ['gpa', 2], 'SKS lulus' => ['passed_credits', 0],
        'TAK' => ['tak_score', 0], 'EPrT' => ['eprt_score', 0]];
    $statuses = ['Sidang tingkat 2' => 'sidang_tingkat_2', 'Status SKPI' => 'skpi_status',
        'KRS Sidang TA 2' => 'ta2_krs_label'];
    $titles = ['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'];
    // Legacy values stay in the snapshot but are retired from this presentation.
    $retired = ['level_3_status', 'bpp_status', 'ibasila_status', 'skpi_tak',
        'advisor_1', 'advisor_2', 'examiner_1', 'examiner_2', 'examiner_3',
        'exam_date', 'exam_time', 'exam_location'];
    $known = array_merge(array_values($identity), array_column($metrics, 0), array_values($statuses),
        array_values($titles), $retired, ['academic_source_updated_at', 'ta2_krs_status']);
    $extra = array_diff_key($snapshot, array_flip($known));
    $statusLabel = static fn ($value) => match (strtolower(trim(is_scalar($value) ? (string) $value : ''))) {
        'approved' => 'Disetujui', 'paid' => 'Lunas', 'pending' => 'Menunggu',
        'rejected' => 'Ditolak', default => $display($value),
    };
    $statusTone = static fn ($value) => match (strtolower(trim(is_scalar($value) ? (string) $value : ''))) {
        'approved', 'disetujui', 'paid', 'lunas', 'lulus' => 'bg-emerald-50 text-emerald-800',
        'pending', 'menunggu', 'needs_review', 'perlu revisi' => 'bg-amber-50 text-amber-800',
        'rejected', 'ditolak', 'tidak lulus' => 'bg-rose-50 text-rose-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<section data-academic-detail class="campus-card overflow-hidden">
    <header class="space-y-1.5 border-b border-slate-100 p-5 sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">Data akademik saat pengajuan</h2>
        <p class="text-sm leading-relaxed text-slate-500">Data direkam saat pengajuan dikirim. Perubahan data akademik
            setelahnya tidak mengubah ringkasan tersimpan ini.</p>
    </header>
    <div class="space-y-6 p-5 sm:p-6">
        <section data-academic-group="identity">
            <h3 class="text-sm font-bold text-slate-800">Identitas akademik</h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach($identity as $label => $key)
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="mt-1 break-words text-sm font-medium text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="metrics">
            <h3 class="text-sm font-bold text-slate-800">Ringkasan akademik</h3>
            <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach($metrics as $label => [$key, $digits])
                    <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="mt-2 break-words text-xl font-bold text-slate-900">{{ $number($snapshot[$key] ?? null, $digits) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="statuses">
            <h3 class="text-sm font-bold text-slate-800">Status akademik</h3>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach($statuses as $label => $key)
                    @php($value = $snapshot[$key] ?? null)
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 p-3">
                        <dt class="text-sm text-slate-600">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="max-w-full break-words rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($value) }}">{{ $statusLabel($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="titles">
            <h3 class="text-sm font-bold text-slate-800">Judul Tugas Akhir</h3>
            <dl class="mt-3 space-y-3">
                @foreach($titles as $label => $key)
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="mt-1.5 break-words rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($extra !== [])
            <section data-academic-group="extra">
                <h3 class="text-sm font-bold text-slate-800">Informasi tambahan</h3>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                    @foreach($extra as $key => $value)
                        <div class="min-w-0">
                            <dt class="break-words text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">{{ $display($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
        <p class="border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
            Waktu sumber akademik:
            <span data-snapshot-field="academic_source_updated_at">{{ $display($snapshot['academic_source_updated_at'] ?? null) }}</span>.
            Waktu ini menunjukkan pembaruan sumber data, bukan waktu kirim pendaftaran.
        </p>
    </div>
</section>

```

Jangan memodifikasi academic_snapshot DB supaya testlulus. NullsnapshotdanlabelKRSyangbelumdirekam tetapBelumtersedia; rawstatus eligible tidak diubah menjadi klaim labelhistoris. Fieldtambahan yangbukanretired tetapditampilkan escaped seperti sebelumnya. Judul, IPK0/null dan sumberwaktu tetap. LabelKRSbaru berasal dari47 saatpengajuanbaru dikirim, bukan backfillotomatis.

## 4. Verifikasi

```bash
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaReviewExportTest.php
```

Target DetailUi10test (8existing+2new), suiteTAmulai darihasilaktualcheckpoint47bertambah2 (190jika47sebelumnya188). Node12tetap. Asisten tidak menjalankan FeatureDB/browser; target bukan klaimlulus.

Manual:

1. Student membuka detailpengajuanbaru yang dibuat setelah47: pembimbing/penguji,jadwal,tingkat3,BPP,iBasila,ketersediaanTAK tidakada. KRS TA2 sesuai snapshot, metrik/title tetap.
2. Petugas membuka Tinjau pengajuan yang sama: bagian Dataakademiksaatpengajuan memakai bidangyangsama. Riwayattetapcollapsed46, dokumen/preview/reviewtidakberubah.
3. Buka pengajuanlama/nullsnapshot: KRS belumtersedia, bukan diisi dari data mahasiswa terbaru. Informasitambahan tidak membocorkankembali fieldyangsudahdihapus.
4. Sinkronisasi data akademik kemudian tidakmengubah detailpengajuanyangsudahtersimpan. Jangan migratefresh untuk menguji histori ini.

Tidak perlu imporJSON untuk48. Setelahlulus,salin2file48 ke dadu-test untuk rescan. Fiturmeetbelumselesai: checkpointberikutnya registryupload/dataotomatis/EPRT/fivegates, targetedrevision/reviewerlock/deadline,grid2kolomdaniconexport. KRSgenerik/SKPIapproval/EPRTattemptmappingtetapmenunggukonfirmasi; datahilang tidakdianggaplulus.
