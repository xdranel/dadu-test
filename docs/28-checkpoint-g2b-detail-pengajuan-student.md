# 28 — G2b: detail pengajuan student

**Goal:** merapikan Data akademik saat pengajuan dan riwayat status, serta menampilkan Perbaiki Pengajuan hanya kepada pemilik yang berhak.

**Architecture:** controller menambahkan satu boolean untuk visibilitas tombol. Satu partial Blade mengelompokkan snapshot historis; view detail menata pendaftaran, dokumen dan riwayat. Backend draft/final/review tetap seperti hasil yang sudah diuji.

**Tech stack:** Laravel 13, Blade, Tailwind 3 dan Pest. Tidak menambah dependency.

**Spec:** rancangan [22](22-rancangan-visual-student-sidang-ta.md), hasil rescan [27](27-rescan-g1-g2a-setelah-tersalin.md), dan arahan pengguna untuk melanjutkan pekerjaan student yang tersisa.

**Perhatikan target view:** bagian 4 mengganti `resources/views/sidang_ta_registrations/show.blade.php`, **bukan** `resources/views/sidang_ta_registrations/admin/show.blade.php`. Diagnosis salah lokasi dan penguatan assertion setelah implementasi tersedia pada [29](29-koreksi-lokasi-view-detail-g2b.md).

## Batas dan hasil scan awal

- G1/G2a sudah tersalin dan 90 test PHP serta browser dilaporkan lulus. Source kedua folder diperiksa sebelum penulisan panduan ini.
- `show.blade.php` masih melakukan loop key snapshot mentah. Tombol Perbaiki masih status-only. `show()` sudah memiliki guard pemilik/reviewer; pertahankan guard tersebut.
- Data akademik harus berasal dari **academic_snapshot pada registration**, bukan memanggil SidangTaAcademicSnapshot atau mengambil nilai Student terbaru untuk menggambar isi detail.
- StudentContext boleh dipakai hanya untuk memeriksa pemilik tombol, sesuai backend yang sudah ada. Jangan membuat reviewer tanpa Student gagal membuka halaman hanya karena pemeriksaan tombol.
- Nilai nol tetap nol; null/kosong menjadi Belum tersedia. Key tambahan tidak dibuang, tetapi diletakkan pada Informasi tambahan dengan output yang di-escape.
- Tidak mengerjakan workflow petugas, menghapus view legacy, mengubah database, status, log, route atau finalizer. Asisten tetap hanya menulis docs.

## Fokus review

1. Student yang berubah sesudah submit tidak mengubah snapshot detail; test pertama memeriksa sumber historis.
2. Nol/null, snapshot lama kosong dan key tambahan tetap aman; test kedua/ketiga memeriksa fallback dan escaping.
3. Pemilik dengan email berubah masih dikenali melalui username; test tombol memakai kedua jalur.
4. Reviewer tanpa Student tidak gagal render dan tidak memperoleh tombol revisi milik mahasiswa; ada test terpisah.
5. Permission UI tidak menggantikan guard endpoint; test pemilik tanpa update dan mahasiswa lain tetap memeriksa penolakan akses.

## 1. File dan command

| File | Perubahan |
| --- | --- |
| `app/Http/Controllers/SidangTaRegistrationController.php` | Ganti method show() saja |
| `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php` | Baru: kelompok snapshot |
| `resources/views/sidang_ta_registrations/show.blade.php` | Ganti seluruh view detail |
| `tests/Feature/SidangTaDetailUiTest.php` | Baru: delapan test lengkap |

Jalankan dari folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:view sidang_ta_registrations.partials.detail-academic
php artisan make:test SidangTaDetailUiTest --pest
```

- [ ] Salin test bagian 5 terlebih dahulu dan jalankan command fokus bagian 6. Pemeriksaan kelompok/tombol baru semestinya belum lulus sebelum kode diterapkan. Kegagalan database/dependency bukan bukti regresi yang dimaksud.
- [ ] Terapkan bagian 2–4; jangan mengganti controller seluruhnya atau menimpa test sebelumnya.
- [ ] Jalankan ulang test, suite TA, build dan checklist browser.

## 2. Ganti hanya method show()

Controller saat ini sudah mengimpor SidangTaStudentContext dan ValidationException, sehingga tidak perlu import baru.

```php
public function show(SidangTaRegistration $sidang_ta_registration)
{
    $registration = $sidang_ta_registration->load(['period', 'documents', 'logs']);
    $this->authorizeStudentOrReviewer($registration);
    $user = auth()->user();
    $canRevise = false;
    if ($registration->status === 'needs_review' && $user->can('sidang_ta_registration_update')) {
        try {
            $student = app(SidangTaStudentContext::class)->studentFor($user);
            $canRevise = $registration->student_number === $student->student_number;
        } catch (ValidationException $exception) {
            // Reviewer dapat membaca detail walaupun tidak terhubung dengan Student.
            $canRevise = false;
        }
    }
    return view('sidang_ta_registrations.show', compact('registration', 'canRevise'));
}
```

Jangan menghapus authorizeStudentOrReviewer(), guard edit(), permission route atau pengecekan owner pada service. Boolean di atas hanya mengatur tampilan tombol. Tidak memodifikasi data atau menghitung ulang snapshot.

## 3. Isi lengkap partial detail-academic.blade.php

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
    $statuses = ['Sidang tingkat 2' => 'sidang_tingkat_2', 'Sidang tingkat 3' => 'level_3_status',
        'Status BPP' => 'bpp_status', 'Status SKPI' => 'skpi_status',
        'Status i-Basila' => 'ibasila_status', 'Ketersediaan TAK' => 'skpi_tak'];
    $titles = ['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'];
    $advisors = ['Pembimbing 1' => 'advisor_1', 'Pembimbing 2' => 'advisor_2'];
    $examiners = ['Penguji 1' => 'examiner_1', 'Penguji 2' => 'examiner_2', 'Penguji 3' => 'examiner_3'];
    $schedule = ['Tanggal' => 'exam_date', 'Waktu' => 'exam_time', 'Lokasi' => 'exam_location'];
    $known = array_merge(array_values($identity), array_column($metrics, 0), array_values($statuses),
        array_values($titles), array_values($advisors), array_values($examiners), array_values($schedule),
        ['academic_source_updated_at']);
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
        <p class="text-sm leading-relaxed text-slate-500">Data direkam saat pengajuan dikirim. Perubahan data akademik setelahnya tidak mengubah ringkasan tersimpan ini.</p>
    </header>
    <div class="space-y-6 p-5 sm:p-6">
        <section data-academic-group="identity">
            <h3 class="text-sm font-bold text-slate-800">Identitas akademik</h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach($identity as $label => $key)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-1 break-words text-sm font-medium text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="metrics">
            <h3 class="text-sm font-bold text-slate-800">Ringkasan akademik</h3>
            <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach($metrics as $label => [$key, $digits])
                    <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-2 break-words text-xl font-bold text-slate-900">{{ $number($snapshot[$key] ?? null, $digits) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="statuses">
            <h3 class="text-sm font-bold text-slate-800">Status akademik</h3>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach($statuses as $label => $key)
                    @php($value = $snapshot[$key] ?? null)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 p-3">
                        <dt class="text-sm text-slate-600">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="max-w-full break-words rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($value) }}">{{ $statusLabel($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="titles">
            <h3 class="text-sm font-bold text-slate-800">Judul Tugas Akhir</h3>
            <dl class="mt-3 space-y-3">
                @foreach($titles as $label => $key)
                    <div><dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-1.5 break-words rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="lecturers">
            <h3 class="text-sm font-bold text-slate-800">Pembimbing & Penguji</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                @foreach(['Dosen pembimbing' => $advisors, 'Dosen penguji' => $examiners] as $title => $fields)
                    <div class="rounded-xl border border-slate-100 p-4">
                        <h4 class="text-xs font-semibold text-slate-500">{{ $title }}</h4>
                        <dl class="mt-2 divide-y divide-slate-100">
                            @foreach($fields as $label => $key)
                                <div class="grid grid-cols-[6rem_minmax(0,1fr)] gap-3 py-2.5">
                                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                    <dd data-snapshot-field="{{ $key }}" class="break-words text-sm font-medium text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>
        <section data-academic-group="schedule">
            <h3 class="text-sm font-bold text-slate-800">Jadwal sidang TA</h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-3">
                @foreach($schedule as $label => $key)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-1 break-words text-sm font-medium text-slate-800">{{ $display($snapshot[$key] ?? null) }}</dd></div>
                @endforeach
            </dl>
        </section>
        @if($extra !== [])
            <section data-academic-group="extra">
                <h3 class="text-sm font-bold text-slate-800">Informasi tambahan</h3>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                    @foreach($extra as $key => $value)
                        <div class="min-w-0"><dt class="break-words text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">{{ $display($value) }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif
        <p class="border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">Waktu sumber akademik: <span data-snapshot-field="academic_source_updated_at">{{ $display($snapshot['academic_source_updated_at'] ?? null) }}</span>. Waktu ini menunjukkan pembaruan sumber data, bukan waktu kirim pendaftaran.</p>
    </div>
</section>
```

Tidak mem-parsing ulang waktu jadwal/sumber lama yang mungkin punya format berbeda. Skor hanya diformat untuk tampilan; warna status tidak menambah aturan kelulusan atau gate akademik baru. Key tambahan yang berupa array/object ditampilkan sebagai JSON teks yang tetap di-escape Blade, bukan HTML aktif.

## 4. Isi lengkap show.blade.php

```blade
<x-app-layout>
    <x-slot name="header">Detail Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabel = static fn ($status) => match ($status) {
            'submitted' => 'Menunggu peninjauan', 'needs_review' => 'Perlu revisi',
            'approved' => 'Disetujui', 'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
        $actionLabel = static fn ($action) => match ($action) {
            'submitted' => 'Pendaftaran dikirim', 'resubmitted' => 'Revisi dikirim',
            'needs_review' => 'Diminta revisi', 'approved' => 'Pengajuan disetujui',
            'rejected' => 'Pengajuan ditolak',
            default => ucfirst(str_replace('_', ' ', (string) $action)),
        };
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <section class="campus-card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="min-w-0">
                    <p class="break-words text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">{{ $registration->period?->name ?? 'Periode tidak tersedia' }}</p>
                    <h2 class="mt-1 break-words text-xl font-bold text-slate-900">{{ $registration->student_name }}</h2>
                    <p class="mt-1 break-words text-sm text-slate-600">{{ $registration->student_number }} · {{ $registration->study_program ?: 'Program studi belum tersedia' }}</p>
                </div>
                <x-status-badge :status="$registration->status" :label="$statusLabel($registration->status)" />
            </div>
            <p class="px-5 py-3 text-xs text-slate-500 sm:px-6">Dikirim: {{ $registration->submitted_at?->translatedFormat('d M Y H:i') ?? 'Belum tersedia' }}</p>
        </section>
        @if(filled($registration->review_notes))
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h2 class="text-sm font-bold text-amber-900">Catatan petugas</h2>
                <p class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-amber-800">{{ $registration->review_notes }}</p>
            </section>
        @endif
        <section class="campus-card p-5 sm:p-6">
            <h2 class="text-lg font-bold text-slate-900">Data pendaftaran</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">Nomor HP mahasiswa</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $registration->student_phone }}</dd></div>
                <div><dt class="text-xs text-slate-500">Nomor HP orang tua/wali</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-slate-800">{{ $registration->parent_phone }}</dd></div>
                <div><dt class="text-xs text-slate-500">Tanggal pembayaran wisuda</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $registration->graduation_payment_date?->translatedFormat('d M Y') ?? 'Belum tersedia' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Biodata ijazah</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-800">{{ $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti pengajuan PDM dilampirkan' }}</dd></div>
            </dl>
        </section>

        @include('sidang_ta_registrations.partials.detail-academic')

        <section class="campus-card overflow-hidden">
            <h2 class="border-b border-slate-100 px-5 py-4 text-lg font-bold text-slate-900 sm:px-6">Dokumen yang diunggah</h2>
            <div data-final-documents class="divide-y divide-slate-100 px-5 sm:px-6">
                @forelse($registration->documents as $document)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type }}</p>
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB</p>
                        </div>
                        <a data-final-download class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                           href="{{ route('sidang-ta-registration-documents.download', $document) }}">Unduh</a>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Belum ada dokumen tersimpan.</p>
                @endforelse
            </div>
        </section>

        <section data-registration-history class="campus-card p-5 sm:p-6">
            <h2 class="text-lg font-bold text-slate-900">Riwayat status</h2>
            <ol class="mt-4 space-y-4">
                @forelse($registration->logs as $log)
                    <li class="border-l-2 border-rose-200 pl-4">
                        <p class="text-sm font-semibold text-slate-800">{{ $actionLabel($log->action) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $log->performed_at?->translatedFormat('d M Y H:i') ?? 'Waktu belum tersedia' }}</p>
                        @if(filled($log->to_status))
                            <p class="mt-1 text-xs text-slate-600">{{ filled($log->from_status) ? $statusLabel($log->from_status).' → ' : '' }}{{ $statusLabel($log->to_status) }}</p>
                        @endif
                        @if(filled($log->notes))
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600">{{ $log->notes }}</p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Belum ada riwayat status.</li>
                @endforelse
            </ol>
        </section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
            @if($canRevise)
                <a data-revise-registration href="{{ route('sidang-ta-registrations.edit', $registration) }}" class="btn btn-primary">Perbaiki Pengajuan</a>
            @endif
        </div>
    </div>
</x-app-layout>
```

Label riwayat hanya presentasi; action/status/catatan di database tidak diubah. Nama file/judul/catatan tetap di-escape melalui `{{ }}`. Jangan menggantinya dengan `{!! !!}` agar catatan atau data tambahan tidak menjadi HTML aktif.

## 5. Kode lengkap test detail

Isi `tests/Feature/SidangTaDetailUiTest.php` berikut. Test menggunakan model final langsung sebagai fixture detail, bukan mengulang seluruh upload/finalisasi yang sudah diuji. Database tetap schema testing melalui command bagian 6.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'detail-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Nama Saat Diajukan',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 3.25,
    ]);
    foreach (['read', 'update', 'review'] as $action) {
        Permission::firstOrCreate(['name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode Detail UI', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->snapshot = [
        'student_name' => 'Nama Saat Diajukan', 'student_number' => '1202200001',
        'study_program' => 'Informatika Historis', 'student_email' => 'historis@mahasiswa.test',
        'semester' => 8, 'gpa' => 3.25, 'passed_credits' => 140,
        'tak_score' => 75, 'eprt_score' => 500, 'ta_title' => 'Judul TA Historis',
        'ta_title_en' => 'Historical Thesis Title', 'advisor_1' => 'Dosen Historis',
        'academic_source_updated_at' => '01/11/2026 10:00', 'bpp_status' => 'paid',
    ];
    $this->makeRegistration = function (array $overrides = []) {
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->period->id, 'student_number' => '1202200001',
            'student_name' => 'Nama Saat Diajukan', 'study_program' => 'Informatika Historis',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'needs_review', 'submitted_at' => now(), 'academic_snapshot' => $this->snapshot,
        ], $overrides))->fresh();
    };
    $this->actingAs($this->user);
});

test('detail_uses_saved_academic_snapshot_after_student_data_changes', function () {
    $registration = ($this->makeRegistration)();
    $this->student->update(['name' => 'Nama Terbaru Berbeda', 'gpa' => 3.99]);
    $this->student->finalProject()->create(['thesis_title_id' => 'Judul TA Terbaru Berbeda']);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->count())->toBe(1);
    expect($academic->text(null, true))->toContain('Nama Saat Diajukan')->toContain('Judul TA Historis')
        ->toContain('Historical Thesis Title')->toContain('Dosen Historis')
        ->not->toContain('Nama Terbaru Berbeda')->not->toContain('Judul TA Terbaru Berbeda');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('3,25');
    expect($academic->filter('[data-academic-group]')->count())->toBe(6);
    $stored = $registration->fresh()->academic_snapshot;
    $expected = $this->snapshot;
    ksort($stored);
    ksort($expected);
    expect($stored)->toBe($expected);
});

test('detail_preserves_zero_null_and_extra_snapshot_values_with_escaped_output', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => [
        'gpa' => 0, 'passed_credits' => 0, 'tak_score' => null,
        'ta_title' => '<script>alert(1)</script>',
        'legacy_flag' => false, 'legacy_meta' => ['catatan' => '<b>Tambahan</b>'],
    ]]);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('0,00');
    expect($academic->filter('[data-snapshot-field="passed_credits"]')->text(null, true))->toBe('0');
    expect($academic->filter('[data-snapshot-field="tak_score"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-academic-group="extra"]')->text(null, true))
        ->toContain('Legacy flag')->toContain('Tidak')->toContain('<b>Tambahan</b>');
    expect($academic->filter('[data-snapshot-field="ta_title"]')->text(null, true))->toBe('<script>alert(1)</script>');
    expect($academic->filter('script, b')->count())->toBe(0);
});

test('legacy_registration_with_null_snapshot_renders_fallback_without_live_student_values', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => null]);
    $crawler = new Crawler($this->get(route('sidang-ta-registrations.show', $registration))->assertOk()->getContent());
    $academic = $crawler->filter('[data-academic-detail]');
    expect($academic->filter('[data-snapshot-field="student_name"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-snapshot-field="gpa"]')->text(null, true))->toBe('Belum tersedia');
    expect($academic->filter('[data-academic-group="extra"]')->count())->toBe(0);
    expect($registration->fresh()->academic_snapshot)->toBeNull();
});

test('owner_can_see_revise_button_through_email_and_username_fallback', function () {
    $registration = ($this->makeRegistration)();
    $url = route('sidang-ta-registrations.show', $registration);
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->attr('href'))
        ->toBe(route('sidang-ta-registrations.edit', $registration));
    $this->user->update(['email' => 'alias-detail@mahasiswa.test']);
    $this->actingAs($this->user->fresh());
    $crawler = new Crawler($this->get($url)->assertOk()->getContent());
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(1);
    $this->get(route('sidang-ta-registrations.edit', $registration))->assertOk();
});

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

test('another_student_still_cannot_read_registration_detail', function () {
    $registration = ($this->makeRegistration)();
    $other = User::factory()->create([
        'username' => '1202200002', 'email' => 'other-detail@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    Student::create([
        'student_number' => '1202200002', 'name' => 'Mahasiswa Lain', 'email' => $other->email,
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif',
    ]);
    $other->givePermissionTo(['sidang_ta_registration_read', 'sidang_ta_registration_update']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($other)->get(route('sidang-ta-registrations.show', $registration))->assertForbidden();
});

test('detail_has_indonesian_history_private_download_and_no_revision_for_submitted_status', function () {
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
    $history = $crawler->filter('[data-registration-history]');
    expect($history->text(null, true))->toContain('Pendaftaran dikirim')->toContain('Diminta revisi')
        ->toContain('Revisi dikirim')->toContain('Menunggu peninjauan')->toContain('<b>Catatan literal</b>');
    expect($history->filter('b')->count())->toBe(0);
    expect($crawler->filter('[data-revise-registration]')->count())->toBe(0);
    $download = $crawler->filter('a[data-final-download]');
    expect($download->attr('href'))->toBe(route('sidang-ta-registration-documents.download', $document));
    expect(preg_split('/\s+/', trim($download->attr('class'))))->toContain('btn')->toContain('btn-secondary');
    $this->get($download->attr('href'))->assertDownload('berkas.pdf');
    expect($registration->logs()->where('action', 'resubmitted')->exists())->toBeTrue();
});
```

Delapan test ini menambah definisi test PHP TA dari 90 menjadi **98**. Test yang memakai teks DOM sudah menggunakan normalisasi whitespace, bukan raw assertSee untuk paragraf yang bisa dipecah formatter. Tidak menambah test Node karena JavaScript tidak diubah.

## 6. Command verifikasi

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php
```

Setelah test fokus lulus:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
npm run build
```

Hasil yang diharapkan: 98 definisi test PHP TA, 9 test Node dan build lulus. Tetap MySQL Podman pada schema **dadu_testing**, jangan memakai database aplikasi untuk RefreshDatabase. Tidak ada migrate/seed atau perubahan phpunit.xml dari asisten.

## 7. Checklist browser detail

- [ ] Login dummy dengan role Mahasiswa, buka Detail dari riwayat. Identitas, data pendaftaran, kelompok akademik, dokumen dan riwayat status terbaca rapi pada desktop/mobile.
- [ ] IPK/SKS/TAK/EPrT menjadi empat kotak angka ringkas; judul panjang dan nama dosen/email/nama file tidak keluar card. Informasi tambahan jika ada tetap terbaca sebagai teks.
- [ ] Data akademik detail sama dengan snapshot saat submit. Perubahan sumber akademik setelahnya tidak mengganti isi detail lama. Jangan menyunting data akademik nyata hanya untuk mencoba; gunakan fixture/data lokal yang memang untuk uji.
- [ ] Nilai tidak tersedia tidak diberi angka nol atau label gagal otomatis. Waktu sumber akademik dibedakan dari waktu kirim.
- [ ] Dokumen dapat diunduh melalui tombol. Catatan petugas dan riwayat menampilkan baris teks dengan aman; label action utama berbahasa Indonesia.
- [ ] Pengajuan submitted/approved/rejected tidak menampilkan Perbaiki. Pengajuan needs_review milik sendiri dengan permission update menampilkan tombol dan membuka halaman Mulai/lanjutkan draft revisi yang sudah ada.
- [ ] Sesudah revisi dikirim, detail memperlihatkan data terakhir yang dikirim, tombol Perbaiki hilang sampai memang diminta revisi lagi, dan riwayat sebelumnya tetap ada.

## 8. Berkas untuk rescan

Sesudah seluruh test dan browser lulus, pindahkan empat berkas pada tabel bagian 1 ke dadu-test. Jangan menyalin `.env`, database, phpunit.xml sementara, vendor/node_modules, cache atau hasil build. Minta rescan sebelum menyatakan seluruh daftar visual student selesai.

Jangan hapus form legacy pada checkpoint ini. Setelah G2b diuji dan direscan, cleanup tersebut dapat dibahas terpisah. Petugas tetap belum dikerjakan; tunggu instruksi pengguna.

Referensi Context7: [Blade includes dan escaping Laravel 13](https://github.com/laravel/docs/blob/13.x/blade.md). Data dan kontrol akses tetap mengikuti model/controller/service aktual yang dibaca saat scan.

## Catatan verifikasi panduan

Method controller dan file test PHP pada Markdown lolos lint. Kedua blok Blade dikompilasi di memori tanpa resolusi component tag/cache, lalu hasil PHP lolos lint. Partial akademik juga dirender di memori menggunakan model tanpa koneksi database: tujuh pemeriksaan untuk nol IPK, nol SKS, null, escaping tag, judul literal, informasi tambahan dan snapshot kosong lulus. Ini bukan eksekusi delapan Feature test atau seluruh aplikasi. Suite Laravel/database, browser dan build belum dijalankan asisten.

Assertion snapshot pada test pertama mengurutkan key sebelum perbandingan agar normalisasi urutan key JSON MySQL tidak menimbulkan kegagalan palsu. Hash source kedua folder diperiksa sebelum/sesudah penulisan dan tidak berubah. Belum ada kode G2b yang diterapkan oleh asisten ke aplikasi.
