# 47 — Meet B: panel akademik ringkas dan pembacaan KRS TA 2

**Goal:** menyederhanakan panel kanan semua tahap dan merekam status KRS TA2 berdasarkan data sinkronisasi pada satu periode akademik aktif.
**Spec:**45 dan persetujuan pengguna melanjutkan tanpa data yang baru bisa dikonfirmasi besok.
**Architecture:** tambah method pembaca KRS di service snapshot existing; data dibaca DB, bukan berkasJSON runtime. Snapshot baru mencatat hasil dan label. Ini belum mengubah required upload, gate kirim, snapshotdetailUI, export, atau alurrevisi; semua itu checkpoint sesudahnya. Helper ini sementara TA-specific agar tidak mengubah aturan yudisium.

## 1. Rescan dan file

Lima file46 identik di original/workspace. Unit/manual46 lulus menurut pengguna; asisten tidak menjalankan FeatureDB. Checkpoint47 mengubah tiga fileexisting, tidak ada migrasi/filebaru/make command:

- app/Services/SidangTaAcademicSnapshot.php
- resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php
- tests/Feature/SidangTaWizardUiTest.php

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
```

## 2. Test dahulu

Ganti test existing g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa dengan berikut. Data pembimbing masih boleh disimpan pada sumber/snapshotlegacy tetapi tidak tampil di panel. Jangan mengubah test detail snapshot historis pada checkpoint ini.

```php
test('g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa', function () {
    $this->student->finalProject()->create([
        'thesis_title_id' => 'Judul akademik untuk G1',
        'thesis_title_en' => 'Academic title for G1', 'advisor_1' => 'Dosen Pembimbing G1',
    ]);
    $draft = ($this->completeDraft)();
    foreach ([1, 2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->assertSee('Judul akademik untuk G1')->assertSee('Academic title for G1')
            ->assertDontSee('Dosen Pembimbing G1')->assertSee('0,00')
            ->assertSee('KRS Sidang TA 2')->assertDontSee('Pembimbing &amp; Penguji', false)
            ->assertDontSee('Jadwal sidang TA')->assertDontSee('Sidang tingkat 3')
            ->assertDontSee('Status BPP')->assertDontSee('Status i-Basila')->assertDontSee('Ketersediaan TAK');
        $xpath = sidangTaUiXpath($response->getContent());
        expect($xpath->query('//aside[@data-academic-panel]')->length)->toBe(1);
        expect($xpath->query('//aside[@data-academic-panel]//*[@data-save-status]')->length)->toBe(0);
    }
    expect(SidangTaRegistration::count())->toBe(0);
});
```

Tambahkan test berikut pada file yang sama. Memakai beforeEach dan $this->student existing. Fixture membuat Course dahulu untuk memenuhi foreignkey; tidak menonaktifkan constraints.

```php
test('meet_krs_requires_one_active_period_and_explicit_ta2_name', function () {
    \App\Models\AcademicPeriod::query()->update(['is_active' => false]);
    $service = app(\App\Services\SidangTaAcademicSnapshot::class);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $period = \App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Meet KRS', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = \App\Models\Course::create(['course_code' => 'MEET-TA2', 'name' => 'TUGAS AKHIR 2',
        'academic_year' => '2026/2027', 'semester_term' => 'GANJIL', 'study_program' => 'Informatika',
        'class' => 'A', 'semester' => 8, 'credits' => 4, 'type' => 'Wajib', 'status' => 'Aktif']);
    $row = \App\Models\StudentCourse::create(['student_number' => $this->student->student_number,
        'course_code' => $course->course_code, 'course_name' => 'TUGAS AKHIR', 'course_class' => 'A',
        'academic_year' => '2026/2027', 'semester' => 8, 'academic_period_id' => $period->id]);
    foreach (['TUGAS AKHIR', 'TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR'] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('unconfirmed');
    }
    $row->update(['course_name' => '  tugas akhir  2  ']);
    expect($service->ta2KrsStatus($this->student))->toBe('eligible');
    $snapshot = $service->forStudent($this->student->fresh());
    expect($snapshot['ta2_krs_status'])->toBe('eligible');
    expect($snapshot['ta2_krs_label'])->toBe('Terdaftar');
    $row->update(['academic_period_id' => null]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $row->update(['academic_period_id' => $period->id]);
    $period->update(['is_active' => false]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
});
```

Jalankan dahulu dan pastikan perilaku baru belum lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php
```

## 3. Service snapshot

Di dalam class SidangTaAcademicSnapshot, tambahkan method berikut setelah forStudent, sebelum penutup class. Tidak perlu import baru karena memakai nama lengkap.

```php
    public function ta2KrsStatus(Student $student): string
    {
        $periods = \App\Models\AcademicPeriod::query()->where('is_active', true)->pluck('id');
        if ($periods->count() !== 1) return 'unknown';
        $names = \App\Models\StudentCourse::query()
            ->where('student_number', $student->student_number)
            ->where('academic_period_id', $periods->first())->pluck('course_name');
        if ($names->isEmpty()) return 'unknown';
        foreach ($names as $name) {
            $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
            if (in_array($normalized, ['TUGAS AKHIR 2', 'TUGAS AKHIR II', 'SIDANG TA 2', 'TA 2', 'TA-2', 'FINAL THESIS 2'], true)) {
                return 'eligible';
            }
        }
        // Generic names await official mapping; do not report them as passed.
        return 'unconfirmed';
    }

```

Pada forStudent, setelah `$number = $student->student_number;` tambahkan:

```php
$ta2Status = $this->ta2KrsStatus($student);
```

Di return array, setelah student_number/student_name tambahkan dua key baru, jangan menghapus fieldlama pada checkpoint ini:

```php
 'ta2_krs_status' => $ta2Status,
 'ta2_krs_label' => match ($ta2Status) {
     'eligible' => 'Terdaftar',
     'unconfirmed' => 'Belum terkonfirmasi',
     default => 'Belum tersedia',
 },
```

Tanpa periodeaktif, lebihdarisatuperiodeaktif, tidakadaKRSperiodeterkait →unknown. BarisKRSada tetapi nama belumcocok →unconfirmed, bukan lulus. Exact whitelistterpusat di method memudahkan penyesuaianresmi besok; genericTUGASAKHIR tidakditerima dahulu. Semester8pada KRS bukan semester1/2periode; pencocokan memakai FK academic_period_id. Matchnama menghindari kodeprodi yangbelumdipetakan; jangan menggantinya dengan LIKE %TA2% yangbisa salahcocokTA20.

## 4. Ganti partial panel lengkap

Ganti isi resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php dengan:

```blade
@php
    $display = static fn ($value) => is_scalar($value) && (string) $value !== ''
        ? (string) $value : 'Belum tersedia';
@endphp
<aside data-academic-panel class="min-w-0 space-y-4 xl:col-span-4" aria-label="Informasi akademik mahasiswa">
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Data mahasiswa
        </h2>
        <p class="break-words text-base font-semibold text-slate-900">
            {{ $display($academic['student_name'] ?? null) }}
        </p>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            @foreach(['NIM' => 'student_number', 'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'] as $label => $key)
                <div class="min-w-0">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 break-words font-medium text-slate-800">
                        {{ $display($academic[$key] ?? null) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="text-xs leading-relaxed text-slate-500">
            Sumber data diperbarui: {{ $display($academic['academic_source_updated_at'] ?? null) }}
        </p>
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Judul Tugas Akhir
        </h2>
        @foreach(['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'] as $label => $key)
            <div>
                <h3 class="text-xs font-semibold text-slate-500">
                    {{ $label }}
                </h3>
                <p class="mt-1.5 break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">
                    {{ $display($academic[$key] ?? null) }}
                </p>
            </div>
        @endforeach
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Ringkasan akademik
        </h2>
        <dl class="grid grid-cols-2 gap-3">
            @foreach(['IPK' => 'gpa', 'SKS lulus' => 'passed_credits', 'TAK' => 'tak_score', 'EPrT' => 'eprt_score'] as $label => $key)
                @php($value = $academic[$key] ?? null)
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 text-lg font-bold text-slate-800">
                        {{ is_numeric($value) ? number_format((float) $value, $key === 'gpa' ? 2 : 0, ',', '.') : $display($value) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach(['Sidang tingkat 2' => 'sidang_tingkat_2', 'Status SKPI' => 'skpi_status', 'KRS Sidang TA 2' => 'ta2_krs_label'] as $label => $key)
                <div class="grid grid-cols-2 gap-3 py-2">
                    <dt class="text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="break-words font-medium text-slate-800">
                        {{ $display($academic[$key] ?? null) }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</aside>

```

Panel data/judul/ringkasan tetap tampilsetiaptahap. TAKnumerik tetap, hanya barisketersediaanTAK dihapus. NilaiSKPI/TAK/EPRTkosong tidakdirekayasa. Pada checkpointini detailakademikhistoris masihversilama; perubahanmenyeluruhnya ditulis sesudah kontrakdata/uploadbaruagar tidakmengubah2kali.

## 5. Test dan kebutuhan impor

```bash
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```

Ada satu testbaru; targetkeseluruhanTA bertambah1 dari baselineyangbenar-benar telahdijalankan pengguna (188 jika sebelumnya187), Node12tidakberubah. Tidak menambah testuntukmengejarangka. Jalankan suiteTAyangbiasadijalankan sesudahfokuslulus.

**Belum wajib impor JSON untuk test ini:** fixtures sintetis menyediakanCourse/KRS/periode. Untuk melihat KRS nyata pada panel, ITPanel→SinkronisasiData→KRS memakai JSONKRSyangsesuai prodi dari backup. Pastikan periodeakademikaktif dipilih/dihubungkan sesuai mekanismeITPanel existing; periodeakademik berbeda dari periodependaftaranTA. Jangan impor semua backup untuk sekadar membuatlabelTerdaftar. Jika sudahada dataKRS hasilsinkronisasi danperiodeterkait, tidakperluimporulang. NamaTUGASAKHIRgenerik tetapBelumterkonfirmasi sampaimappingresmi.

Manual: buka drafttahap1–4, paneltanpapembimbing/jadwal/tingkat3/BPP/iBasila/ketersediaanTAK; KRSbaristampil. Coba mahasiswa denganTA2aktif untukTerdaftar dan tanpaKRSuntukBelumtersedia. DataTA2periodeterdahulu tidakbolehmembuatTerdaftar. Periksa layar kecil dan desktop, judulTA/metric0 tetapbenar.

Setelahlulus,salin3file checkpoint47 ke dadu-test lalumintarescan. Belumcommitpetugas. Checkpointberikutnya menyiapkanregistryuploadbaru daneligibilitytanpamenganggapSKPI/TAK/EPRTmissinglulus, lalurevisiterarah/desain2kolom.
