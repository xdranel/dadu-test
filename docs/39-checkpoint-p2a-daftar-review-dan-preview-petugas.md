# 39 — P2a: daftar review, filter, statistik dan preview petugas TA

**Goal:** halaman Review TA mengikuti pola Yudisium dengan ringkasan periode, filter/pencarian, statistik hasil filter dan matriks ikon dokumen yang dapat dipreview.

**Architecture:** satu service query dipakai tabel/statistik dan nanti ekspor P2b. Dokumen dieager-load; partial ikon membedakan cabang aktif, metadata/status dan file aktual. Modal preview student dipakai kembali tanpa membuka storage publik.

**Tech stack:** Laravel13, Eloquent/MySQL Podman, Blade/Tailwind, Alpine preview existing, Pest. Tidak menambah migrasi/dependency/permission atau route baru.

**Spec:** rancangan31 bagian6, disetujui. P1 sudah dilaporkan lulus semua unit/manual dan disalin. Rescan app/resources/routes/tests/config terkait kedua folder identik,138 definisi Feature TA, tidak ditemukan blocker P1.

## 1. Cakupan dan kontrak

- Reviewer melihat periode/waktu/status, empat filter (nama/NIM, periode, status, prodi), lima kartu statistik dan tabel dengan tiga kelompok ikon (6+7+3 posisi).
- Total dan kartu status memakai seluruh filter yang sama; bukan total halaman pagination. Snapshot nama/NIM/prodi bersumber dari registration, bukan data Student terkini.
- Jika semua periode dipilih, banner menampilkan seluruh periode aktif termasuk yang belum dibuka/sudah tutup; tidak memakai first() sebagai satu periode resmi. Memilih periode tertentu menampilkan periode tersebut termasuk yang nonaktif.
- Dokumen pending/verified/rejected memakai status sebenarnya, walaupun registration approved. Wajib tanpa metadata = missing; metadata tetapi file tidak ada = unavailable. Cabang biodata tidak berlaku = N/A.
- Klik ikon tersedia membuka preview privat dan unduh sekunder dalam modal. Detail petugas memakai modal yang sama dan snapshot akademik historis.
- P1 tetap: period-only tidak query pengajuan/statistik/pilihan prodi dan tidak memperoleh tabel/preview. Reviewer-only tetap berfungsi tanpa permission read mahasiswa.
- **Ekspor Excel belum ditambahkan** (P2b berikutnya); tidak memasang tombol ekspor yang belum bekerja. Verifikasi/ubah status tiap dokumen masih P3; keputusan pengajuan existing tidak diubah pada P2a. Tidak ada hapus pengajuan final.

Banner dibuat statis dengan status dan tanggal/jam server. Countdown/minimize tidak diperlukan untuk perubahan ini; tidak menyalin state localStorage Yudisium.

## 2. File dan persiapan

Edit existing:

```text
app/Http/Controllers/SidangTaRegistrationController.php (adminIndex saja)
resources/views/sidang_ta_registrations/admin/index.blade.php
resources/views/sidang_ta_registrations/admin/show.blade.php
```

Buat baru:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Services/SidangTaReviewQuery
php artisan make:view sidang_ta_registrations.admin.partials.document-icons
php artisan make:test SidangTaReviewListTest --pest
```

- [ ] Isi test bagian7 dahulu, jalankan fokus bagian8 untuk memastikan perilaku baru belum tersedia.
- [ ] Isi service dan view lengkap serta ganti satu method controller pada bagian3–6.
- [ ] Jalankan suite/manual, salin6file (3existing+3baru) ke dadu-test, lalu rescan sebelum P2b.

## 3. Service query lengkap

Isi app/Services/SidangTaReviewQuery.php:

```php
<?php

namespace App\Services;

use App\Models\SidangTaRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SidangTaReviewQuery
{
    public const STATUSES = [
        'submitted' => 'Menunggu peninjauan', 'needs_review' => 'Perlu revisi',
        'approved' => 'Disetujui', 'rejected' => 'Ditolak',
    ];

    public function filters(Request $request): array
    {
        $values = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'integer', 'exists:sidang_ta_periods,id'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'study_program' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        unset($values['page']);
        foreach (['search', 'study_program'] as $key) {
            if (isset($values[$key])) $values[$key] = trim($values[$key]);
        }
        if (isset($values['period'])) $values['period'] = (int) $values['period'];
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    public function query(array $filters): Builder
    {
        $query = SidangTaRegistration::query();
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn (Builder $q) => $q->where('student_name', 'like', "%{$term}%")
                ->orWhere('student_number', 'like', "%{$term}%"));
        }
        if (isset($filters['period'])) $query->where('sidang_ta_period_id', $filters['period']);
        if (isset($filters['status'])) $query->where('status', $filters['status']);
        if (isset($filters['study_program'])) $query->where('study_program', $filters['study_program']);
        return $query;
    }

    public function counts(array $filters): array
    {
        $rows = $this->query($filters)->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $counts = ['total' => 0] + array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($rows as $status => $count) {
            $counts[$status] = (int) $count;
            $counts['total'] += (int) $count;
        }
        return $counts;
    }
}
```

Search memakai LIKE sebagaimana pencarian existing (karakter wildcard SQL tetap mengikuti semantik LIKE). Filter lain exact match. Group OR nama/NIM tetap dibungkus supaya tidak menembus filter periode/prodi/status. Nilai search '0' tidak dibuang. Prodi memakai string historis registration; nilai valid tetapi belum ada menghasilkan kosong, bukan mengganti data mahasiswa.

## 4. Controller pengganti

Ganti hanya adminIndex pada SidangTaRegistrationController. Tidak mengubah adminShow, keputusan, periode, preview atau finalisasi:

```php
public function adminIndex(Request $request)
{
    $canReview = $request->user()->can('sidang_ta_registration_review');
    $periods = SidangTaPeriod::orderByDesc('open_at')->orderByDesc('id')->get();
    $registrations = null;
    $filters = [];
    $counts = null;
    $studyPrograms = collect();
    $periodCards = collect();
    if ($canReview) {
        $reviewQuery = app(\App\Services\SidangTaReviewQuery::class);
        $filters = $reviewQuery->filters($request);
        $counts = $reviewQuery->counts($filters);
        $studyPrograms = SidangTaRegistration::query()->whereNotNull('study_program')
            ->where('study_program', '!=', '')->distinct()->orderBy('study_program')->pluck('study_program');
        $registrations = $reviewQuery->query($filters)->with(['period', 'documents'])
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->appends($filters);
        $periodCards = isset($filters['period'])
            ? $periods->where('id', $filters['period'])->values()
            : $periods->where('is_active', true)->values();
    }
    return view('sidang_ta_registrations.admin.index', compact(
        'canReview', 'registrations', 'periods', 'filters', 'counts', 'studyPrograms', 'periodCards'
    ));
}
```

Seluruh query registration termasuk distinct prodi dan statistik berada di cabang reviewer. Tidak mengubah middleware P1. Service query ini menjadi sumber filter ekspor nanti; jangan menulis ulang filter berbeda pada P2b.

## 5. Partial ikon dokumen lengkap

Isi resources/views/sidang_ta_registrations/admin/partials/document-icons.blade.php. Pemanggil menyediakan registration, types kelompok dan requiredTypes cabang aktif:

```blade
@foreach($types as $type)
    @php
        $applicable = in_array($type, $requiredTypes, true);
        $document = $registration->documents->firstWhere('document_type', $type);
        $available = $applicable && $document && \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path);
        $state = !$applicable ? 'na' : (!$document ? 'missing' : (!$available ? 'unavailable' : $document->status));
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan',
            'missing' => 'Belum ada dokumen', 'unavailable' => 'Berkas tidak ditemukan', 'na' => 'Tidak berlaku'];
        $classes = ['pending' => 'border-amber-200 bg-amber-50 text-amber-700', 'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'rejected' => 'border-rose-200 bg-rose-50 text-rose-700', 'missing' => 'border-dashed border-slate-300 text-slate-400',
            'unavailable' => 'border-orange-300 bg-orange-50 text-orange-700', 'na' => 'border-slate-100 bg-slate-50 text-slate-300'];
        $stateLabel = $labels[$state] ?? 'Status belum dikenal';
        $class = $classes[$state] ?? $classes['pending'];
        $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
        $tooltip = $label.' — '.$stateLabel.($available ? ' · '.$document->file_name : '');
    @endphp
    @if($available)
        <a data-ta-doc="{{ $type }}" data-doc-state="{{ $state }}" href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
           data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
           data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
           data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
           x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank" rel="noopener" aria-haspopup="dialog"
           title="{{ $tooltip }}" aria-label="{{ $tooltip }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border focus:outline-none focus:ring-2 focus:ring-rose-500 {{ $class }}">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 13h8M8 17h5"/></svg>
        </a>
    @else
        <span data-ta-doc="{{ $type }}" data-doc-state="{{ $state }}" tabindex="0" role="img" title="{{ $tooltip }}" aria-label="{{ $tooltip }}"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-semibold {{ $class }}">{{ $state === 'na' ? '–' : '!' }}</span>
    @endif
@endforeach
```

N/A tidak menjadi link sekalipun metadata cabang lama masih ada. Missing/unavailable juga tidak menjadi link. Tidak memutasi status dokumen untuk menggambar ikon.

## 6. Dua view petugas

### Index lengkap

Ganti seluruh resources/views/sidang_ta_registrations/admin/index.blade.php:

```blade
<x-app-layout>
    <x-slot name="header">Review Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $groups = ['iGracias & Akademik' => \App\Support\SidangTaDocumentRequirements::CAPTURES,
            'Pembayaran & Dokumen' => \App\Support\SidangTaDocumentRequirements::PAYMENT,
            'Biodata' => array_merge(\App\Support\SidangTaDocumentRequirements::DIPLOMA, \App\Support\SidangTaDocumentRequirements::PDM)];
        $at = now();
    @endphp
    <div data-ta-review-hub class="space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="text-xl font-bold text-slate-800">Review Sidang TA</h2><p class="mt-1 text-sm text-slate-500">Tinjau pengajuan dan kelengkapan berkas mahasiswa.</p></div>
            @can('sidang_ta_period_read')
                <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
            @endcan
        </header>
        @if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        @if($canReview)
            <div data-ta-review-data class="space-y-5">
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">{{ isset($filters['period']) ? 'Periode terpilih' : 'Periode aktif' }}</h3>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse($periodCards as $period)
                            @php
                                $state = match (true) { !$period->is_active => 'Tidak aktif', $at->lt($period->open_at) => 'Belum dibuka', $at->gt($period->close_at) => 'Ditutup', default => 'Sedang terbuka' };
                            @endphp
                            <article data-ta-period-banner="{{ $period->id }}" class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2"><h4 class="break-words font-bold text-slate-800">{{ $period->name }}</h4><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $state }}</span></div>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500">Dibuka {{ $period->open_at->format('d/m/Y H:i') }} · Tenggat {{ $period->close_at->format('d/m/Y H:i') }}</p>
                            </article>
                        @empty
                            <p data-ta-no-period class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">{{ isset($filters['period']) ? 'Periode terpilih tidak tersedia.' : 'Belum ada periode aktif. Riwayat pengajuan tetap dapat ditinjau.' }}</p>
                        @endforelse
                    </div>
                </section>
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">Ringkasan hasil filter</h3>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        @foreach(['total' => 'Total'] + $statusLabels as $key => $label)
                            <article class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p data-ta-stat="{{ $key }}" class="mt-2 text-2xl font-bold {{ $key === 'total' ? 'text-[#DA2D3B]' : 'text-slate-800' }}">{{ $counts[$key] }}</p></article>
                        @endforeach
                    </div>
                </section>
                <form data-ta-filters method="GET" action="{{ route('admin.sidang-ta-registrations.index') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5">
                    <label class="text-xs font-semibold text-slate-600">Nama atau NIM<input class="campus-input mt-1" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama atau NIM"></label>
                    <label class="text-xs font-semibold text-slate-600">Periode<select class="campus-select mt-1" name="period"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(($filters['period'] ?? null) == $period->id)>{{ $period->name }}</option>@endforeach</select></label>
                    <label class="text-xs font-semibold text-slate-600">Status<select class="campus-select mt-1" name="status"><option value="">Semua status</option>@foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-xs font-semibold text-slate-600">Program studi<select class="campus-select mt-1" name="study_program"><option value="">Semua prodi</option>
                        @if(isset($filters['study_program']) && !$studyPrograms->contains($filters['study_program']))<option value="{{ $filters['study_program'] }}" selected>{{ $filters['study_program'] }}</option>@endif
                        @foreach($studyPrograms as $program)<option value="{{ $program }}" @selected(($filters['study_program'] ?? '') === $program)>{{ $program }}</option>@endforeach
                    </select></label>
                    <div class="flex flex-wrap items-end gap-2"><button type="submit" class="btn btn-primary">Terapkan</button><a data-ta-reset href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">Reset</a></div>
                </form>
                <p class="text-xs leading-relaxed text-slate-500">Ikon berkas: kuning menunggu verifikasi, hijau terverifikasi, merah perlu perbaikan, tanda ! belum ada/tidak ditemukan, tanda – tidak berlaku. Arahkan kursor atau fokuskan ikon untuk melihat nama dan status.</p>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>
                            <th class="px-4 py-3">Mahasiswa</th><th class="px-4 py-3">Prodi</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Dikirim</th>
                            @foreach($groups as $title => $types)<th class="px-4 py-3">{{ $title }}</th>@endforeach
                            <th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($registrations as $registration)
                                @php
                                    $requiredTypes = array_merge(\App\Support\SidangTaDocumentRequirements::CAPTURES, \App\Support\SidangTaDocumentRequirements::PAYMENT, \App\Support\SidangTaDocumentRequirements::typesForStep(4, $registration->diploma_biodata_accurate));
                                @endphp
                                <tr data-ta-row="{{ $registration->id }}" class="hover:bg-slate-50">
                                    <td class="px-4 py-4"><p class="font-semibold text-slate-800">{{ $registration->student_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $registration->student_number }}</p></td>
                                    <td class="px-4 py-4 text-xs text-slate-600">{{ $registration->study_program ?: 'Belum tersedia' }}</td>
                                    <td class="px-4 py-4 text-xs text-slate-600">{{ $registration->period?->name ?? 'Periode tidak tersedia' }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-600">{{ $registration->submitted_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    @foreach($groups as $types)<td class="px-4 py-4"><div class="flex max-w-32 flex-wrap gap-1.5">@include('sidang_ta_registrations.admin.partials.document-icons')</div></td>@endforeach
                                    <td class="px-4 py-4"><x-status-badge :status="$registration->status" :label="$statusLabels[$registration->status] ?? $registration->status" /></td>
                                    <td class="px-4 py-4"><a data-ta-detail href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary" aria-label="Tinjau pengajuan {{ $registration->student_name }}" title="Tinjau pengajuan"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.7"/></svg><span>Tinjau</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" data-ta-empty class="px-4 py-12 text-center text-sm text-slate-500">
                                    @if($registrations->total() > 0)
                                        <p>Halaman ini tidak berisi pengajuan.</p>
                                        <a data-ta-first-page href="{{ route('admin.sidang-ta-registrations.index', $filters) }}" class="mt-3 inline-flex font-semibold text-[#DA2D3B] hover:underline">Kembali ke halaman pertama</a>
                                    @else
                                        {{ $filters !== [] ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Belum ada pengajuan Sidang TA.' }}
                                    @endif
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table></div>
                    <div class="border-t border-slate-100 px-4 py-3">{{ $registrations->links() }}</div>
                </section>
            </div>
        @else
            <section data-ta-period-only class="campus-card space-y-2 p-5"><h3 class="font-bold text-slate-800">Pengaturan periode pendaftaran</h3><p class="text-sm text-slate-500">Gunakan Kelola Periode untuk melihat jadwal pendaftaran sidang tugas akhir.</p></section>
        @endif
    </div>
    @if($canReview)
        @include('sidang_ta_registrations.partials.document-preview-modal')
    @endif
</x-app-layout>
```

### Detail petugas lengkap

Ganti **resources/views/sidang_ta_registrations/admin/show.blade.php**, bukan view show student:

```blade
<x-app-layout>
    <x-slot name="header">Peninjauan Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $documentLabels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'];
        $actionLabels = ['submitted' => 'Pendaftaran dikirim', 'resubmitted' => 'Revisi dikirim', 'needs_review' => 'Diminta revisi', 'approved' => 'Pengajuan disetujui', 'rejected' => 'Pengajuan ditolak'];
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="text-xs font-semibold text-[#DA2D3B]">{{ $registration->period?->name ?? 'Periode tidak tersedia' }}</p><h2 class="mt-1 break-words text-xl font-bold text-slate-900">{{ $registration->student_name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $registration->student_number }} · {{ $registration->study_program ?: 'Program studi belum tersedia' }}</p></div>
            <a href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali ke Review Sidang TA</a>
        </header>
        @if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <section class="campus-card space-y-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold text-slate-800">Data pendaftaran</h3><x-status-badge :status="$registration->status" :label="$statusLabels[$registration->status] ?? $registration->status" /></div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach(['HP mahasiswa' => $registration->student_phone, 'HP orang tua/wali' => $registration->parent_phone, 'Pembayaran wisuda' => $registration->graduation_payment_date?->format('d/m/Y'), 'Biodata ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan'] as $label => $value)
                    <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-slate-800">{{ $value ?? 'Belum tersedia' }}</dd></div>
                @endforeach
            </dl>
        </section>
        @include('sidang_ta_registrations.partials.detail-academic')
        <section class="campus-card space-y-4 p-5">
            <h3 class="font-bold text-slate-800">Dokumen pengajuan</h3>
            <div class="divide-y divide-slate-100">
                @forelse($registration->documents as $document)
                    @php($available = \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path))
                    <article data-ta-detail-document="{{ $document->id }}" class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-800">{{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type }}</p><p class="mt-1 break-words text-xs text-slate-500">{{ $document->file_name }} · {{ number_format(($document->file_size ?? 0) / 1024) }} KB</p><p class="mt-1 text-xs text-slate-600">{{ $documentLabels[$document->status] ?? $document->status }}</p>
                            @if($document->notes)<p class="mt-2 whitespace-pre-line break-words text-xs text-rose-700">{{ $document->notes }}</p>@endif
                        </div>
                        @if($available)
                            <a data-document-preview href="{{ route('sidang-ta-registration-documents.preview', $document) }}" data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}" data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}" data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}" x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank" rel="noopener" aria-haspopup="dialog" class="btn btn-secondary">Lihat berkas</a>
                        @else
                            <p class="text-xs font-medium text-orange-700">Berkas tidak ditemukan.</p>
                        @endif
                    </article>
                @empty
                    <p class="py-4 text-sm text-slate-500">Belum ada dokumen tersimpan.</p>
                @endforelse
            </div>
        </section>
        @if(in_array($registration->status, ['submitted', 'needs_review'], true))
            <form data-ta-decision method="POST" action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
                @csrf
                <input type="hidden" name="registration_version" value="{{ $registration->lock_version }}">
                <h3 class="font-bold text-slate-800">Keputusan petugas</h3>
                <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)<textarea class="campus-input min-h-24" name="review_notes" maxlength="2000">{{ old('review_notes') }}</textarea></label>
                <div class="flex flex-wrap justify-end gap-2"><button class="btn btn-secondary" name="action" value="needs_review">Minta Revisi</button><button class="btn btn-secondary" name="action" value="rejected">Tolak</button><button class="btn btn-primary" name="action" value="approved">Setujui</button></div>
            </form>
        @endif
        @if($registration->review_notes)<section class="rounded-xl border border-amber-200 bg-amber-50 p-4"><h3 class="text-sm font-bold text-amber-900">Catatan petugas</h3><p class="mt-1 whitespace-pre-line break-words text-sm text-amber-800">{{ $registration->review_notes }}</p></section>@endif
        <section class="campus-card p-5"><h3 class="font-bold text-slate-800">Riwayat status</h3><ol class="mt-4 space-y-3">
            @forelse($registration->logs as $log)<li class="border-l-2 border-rose-200 pl-3"><p class="text-sm font-semibold text-slate-800">{{ $actionLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}</p><p class="mt-1 text-xs text-slate-500">{{ $log->performed_at?->format('d/m/Y H:i') }}</p>@if($log->notes)<p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">{{ $log->notes }}</p>@endif</li>@empty<li class="text-sm text-slate-500">Belum ada riwayat status.</li>@endforelse
        </ol></section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

Snapshot partial student hanya dibaca kembali, tidak diubah. Status/catatan dokumen ditampilkan read-only; form keputusan tetap membawa lock_version existing. Tidak ada perubahan service persetujuan atau status dokumen sampai P3.

## 7. Feature test baru lengkap

Isi tests/Feature/SidangTaReviewListTest.php:

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    Storage::fake('local');
    foreach (['sidang_ta_registration_review', 'sidang_ta_period_read'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->reviewer = User::factory()->create(['username' => 'p2a-reviewer', 'email' => 'p2a-reviewer@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->reviewer->givePermissionTo('sidang_ta_registration_review');
    $this->actingAs($this->reviewer);
    $this->a = SidangTaPeriod::create(['name' => 'P2a A', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $this->b = SidangTaPeriod::create(['name' => 'P2a B', 'is_active' => true,
        'open_at' => now()->addHour(), 'close_at' => now()->addDays(2)]);
    $this->sequence = 0;
    $this->makeRegistration = function (array $overrides = []) {
        $this->sequence++;
        return SidangTaRegistration::create(array_replace([
            'sidang_ta_period_id' => $this->a->id, 'student_number' => 'P2A'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'student_name' => 'Mahasiswa '.$this->sequence, 'study_program' => 'Informatika',
            'student_phone' => '081234567890', 'parent_phone' => '081234567891',
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
            'status' => 'submitted', 'submitted_at' => now(),
        ], $overrides))->fresh();
    };
    $this->attach = function ($registration, string $type, string $status = 'pending', bool $exists = true) {
        $path = "sidang_ta/p2a/{$registration->id}/{$type}.pdf";
        if ($exists) Storage::disk('local')->put($path, '%PDF-1.4 preview');
        return $registration->documents()->create(['document_type' => $type, 'file_name' => $type.'.pdf',
            'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 16, 'status' => $status]);
    };
    $this->listing = function (array $filters = []) {
        return $this->get(route('admin.sidang-ta-registrations.index', $filters))->assertOk();
    };
});

afterEach(function () { $this->travelBack(); });

test('p2a_all_status_cards_match_full_unfiltered_result', function () {
    foreach (['submitted', 'needs_review', 'approved', 'rejected'] as $status) {
        ($this->makeRegistration)(['status' => $status]);
    }
    $response = ($this->listing)();
    $response->assertViewHas('counts', ['total' => 4, 'submitted' => 1, 'needs_review' => 1, 'approved' => 1, 'rejected' => 1]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-stat]')->count())->toBe(5);
    expect($crawler->filter('[data-ta-stat="total"]')->text())->toBe('4');
    expect($crawler->filter('[data-ta-row]')->count())->toBe(4);
});

test('p2a_combined_filters_group_name_nim_search_without_leaking_other_matches', function () {
    $target = ($this->makeRegistration)(['student_name' => 'Target Cocok', 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-B', 'sidang_ta_period_id' => $this->b->id, 'status' => 'approved']);
    ($this->makeRegistration)(['student_number' => 'Target-Prodi', 'study_program' => 'Sistem Informasi', 'status' => 'approved']);
    ($this->makeRegistration)(['student_name' => 'Target Pending']);
    $response = ($this->listing)(['search' => 'Target', 'period' => $this->a->id, 'status' => 'approved', 'study_program' => 'Informatika']);
    $response->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id)
        ->assertViewHas('counts', ['total' => 1, 'submitted' => 0, 'needs_review' => 0, 'approved' => 1, 'rejected' => 0]);
});

test('p2a_nim_search_and_zero_are_preserved', function () {
    $target = ($this->makeRegistration)(['student_number' => '123450', 'student_name' => 'Nama khusus']);
    ($this->makeRegistration)(['student_number' => '777777', 'student_name' => 'Nama lain']);
    ($this->listing)(['search' => '12345'])->assertViewHas('registrations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $target->id);
    ($this->listing)(['search' => '0'])->assertViewHas('filters', fn ($filters) => $filters['search'] === '0')
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 1);
});

test('p2a_invalid_filter_values_return_validation_errors', function () {
    foreach ([[['period' => 999999], 'period'], [['status' => 'unknown'], 'status'],
        [['search' => ['bad']], 'search'], [['study_program' => ['bad']], 'study_program'],
        [['search' => str_repeat('x', 101)], 'search'], [['page' => 0], 'page']] as [$filters, $field]) {
        $this->getJson(route('admin.sidang-ta-registrations.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('p2a_pagination_keeps_all_filters_and_counts_all_matching_rows', function () {
    foreach (range(1, 20) as $i) ($this->makeRegistration)(['student_name' => 'Pagination '.$i]);
    ($this->makeRegistration)(['student_name' => 'Pagination lain', 'study_program' => 'Sistem Informasi']);
    $filters = ['search' => 'Pagination', 'period' => $this->a->id, 'status' => 'submitted', 'study_program' => 'Informatika'];
    $response = ($this->listing)($filters)->assertViewHas('counts', fn ($counts) => $counts['total'] === 20)
        ->assertViewHas('registrations', fn ($rows) => $rows->count() === 15 && $rows->total() === 20);
    $rows = $response->viewData('registrations');
    parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $query);
    foreach ($filters as $key => $value) expect((string) $query[$key])->toBe((string) $value);
    expect((string) $query['page'])->toBe('2');
    ($this->listing)($filters + ['page' => 2])->assertViewHas('registrations', fn ($page) => $page->count() === 5)
        ->assertViewHas('counts', fn ($counts) => $counts['total'] === 20);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-ta-reset]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index'));
    $crawler = new Crawler(($this->listing)($filters + ['page' => 999])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toContain('Halaman ini tidak berisi pengajuan.');
    expect($crawler->filter('[data-ta-first-page]')->attr('href'))->toBe(route('admin.sidang-ta-registrations.index', $filters));
});

test('p2a_banner_lists_all_active_periods_and_selected_inactive_period', function () {
    $inactive = SidangTaPeriod::create(['name' => 'Nonaktif', 'is_active' => false,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay()]);
    $closed = SidangTaPeriod::create(['name' => 'Aktif tutup', 'is_active' => true,
        'open_at' => now()->subDays(2), 'close_at' => now()->subDay()]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(3);
    expect($crawler->filter('[data-ta-period-banner="'.$this->a->id.'"]')->text(null, true))->toContain('Sedang terbuka');
    expect($crawler->filter('[data-ta-period-banner="'.$this->b->id.'"]')->text(null, true))->toContain('Belum dibuka');
    expect($crawler->filter('[data-ta-period-banner="'.$closed->id.'"]')->text(null, true))->toContain('Ditutup');
    $crawler = new Crawler(($this->listing)(['period' => $inactive->id])->getContent());
    expect($crawler->filter('[data-ta-period-banner]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-period-banner="'.$inactive->id.'"]')->text(null, true))->toContain('Tidak aktif');
});

test('p2a_no_active_period_and_empty_vs_filtered_empty_are_distinct', function () {
    SidangTaPeriod::query()->update(['is_active' => false]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-no-period]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Belum ada pengajuan Sidang TA.');
    $crawler = new Crawler(($this->listing)(['search' => 'Tidak Ada'])->getContent());
    expect($crawler->filter('[data-ta-empty]')->text(null, true))->toBe('Tidak ada pengajuan yang cocok dengan filter.');
});

test('p2a_document_matrix_distinguishes_status_missing_unavailable_and_biodata_na', function () {
    $accurate = ($this->makeRegistration)();
    $pdm = ($this->makeRegistration)(['diploma_biodata_accurate' => false]);
    ($this->attach)($accurate, Requirements::CAPTURES[0], 'verified');
    ($this->attach)($accurate, Requirements::CAPTURES[1], 'rejected');
    ($this->attach)($accurate, Requirements::CAPTURES[2]);
    ($this->attach)($accurate, Requirements::CAPTURES[3], 'verified', false);
    ($this->attach)($accurate, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::PDM[0], 'verified');
    ($this->attach)($pdm, Requirements::DIPLOMA[0], 'verified');
    $crawler = new Crawler(($this->listing)()->getContent());
    $row = $crawler->filter('[data-ta-row="'.$accurate->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(16);
    foreach ([Requirements::CAPTURES[0] => 'verified', Requirements::CAPTURES[1] => 'rejected',
        Requirements::CAPTURES[2] => 'pending', Requirements::CAPTURES[3] => 'unavailable',
        Requirements::CAPTURES[4] => 'missing', Requirements::PDM[0] => 'na'] as $type => $state) {
        expect($row->filter('[data-ta-doc="'.$type.'"]')->attr('data-doc-state'))->toBe($state);
    }
    expect($row->filter('a[data-ta-doc="'.Requirements::CAPTURES[3].'"]')->count())->toBe(0);
    expect($row->filter('a[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(0);
    $row = $crawler->filter('[data-ta-row="'.$pdm->id.'"]');
    expect($row->filter('[data-ta-doc]')->count())->toBe(16);
    expect($row->filter('[data-doc-state="na"]')->count())->toBe(2);
    expect($row->filter('a[data-ta-doc="'.Requirements::PDM[0].'"]')->count())->toBe(1);
});

test('p2a_approved_registration_does_not_turn_pending_document_green', function () {
    $registration = ($this->makeRegistration)(['status' => 'approved']);
    ($this->attach)($registration, Requirements::CAPTURES[0]);
    $crawler = new Crawler(($this->listing)()->getContent());
    expect($crawler->filter('[data-ta-row="'.$registration->id.'"] [data-ta-doc="'.Requirements::CAPTURES[0].'"]')->attr('data-doc-state'))->toBe('pending');
});

test('p2a_reviewer_without_student_read_uses_private_modal_links_and_detail', function () {
    $registration = ($this->makeRegistration)();
    $document = ($this->attach)($registration, Requirements::CAPTURES[0]);
    $document->update(['file_name' => 'nama " <img src=x>.pdf']);
    $crawler = new Crawler(($this->listing)()->getContent());
    $link = $crawler->filter('a[data-ta-doc="'.Requirements::CAPTURES[0].'"]');
    expect($link->attr('href'))->toBe(route('sidang-ta-registration-documents.preview', $document));
    expect($link->attr('data-file-name'))->toBe('nama " <img src=x>.pdf');
    expect($crawler->filter('[data-ta-row] img')->count())->toBe(0);
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(1);
    $this->get($link->attr('href'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk();
    expect($this->reviewer->can('sidang_ta_registration_read'))->toBeFalse();
});

test('p2a_period_only_still_has_no_registration_stats_program_query_or_preview', function () {
    ($this->makeRegistration)(['student_name' => 'Rahasia P2a']);
    $user = User::factory()->create(['username' => 'period-p2a', 'email' => 'period-p2a@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo('sidang_ta_period_read');
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registrations')) $queries[] = $event->sql;
    });
    $response = $this->actingAs($user)->get(route('admin.sidang-ta-registrations.index', ['status' => 'invalid']))
        ->assertOk()->assertViewHas('registrations', fn ($value) => $value === null)
        ->assertViewHas('counts', fn ($value) => $value === null)
        ->assertViewHas('studyPrograms', fn ($value) => $value->isEmpty())
        ->assertDontSee('Rahasia P2a')->assertDontSee('Ringkasan hasil filter');
    expect($queries)->toBe([]);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('[data-document-preview-modal]')->count())->toBe(0);
    expect($crawler->filter('[data-ta-period-only]')->count())->toBe(1);
});

test('p2a_detail_uses_historical_snapshot_and_handles_missing_file_safely', function () {
    $registration = ($this->makeRegistration)(['academic_snapshot' => ['student_name' => 'Nama Historis', 'gpa' => 0,
        'custom_field' => '<b>literal</b>']]);
    $document = ($this->attach)($registration, Requirements::CAPTURES[0], 'rejected', false);
    $document->update(['notes' => '<img src=x> perbaiki']);
    $html = $this->get(route('admin.sidang-ta-registrations.show', $registration))->assertOk()->getContent();
    expect(strtolower(ltrim($html)))->toStartWith('<!doctype html>');
    $crawler = new Crawler($html);
    expect($crawler->filter('[data-academic-detail]')->count())->toBe(1);
    expect($crawler->filter('[data-academic-detail]')->text(null, true))->toContain('Nama Historis')->toContain('0,00');
    $card = $crawler->filter('[data-ta-detail-document="'.$document->id.'"]');
    expect($card->text(null, true))->toContain('Berkas tidak ditemukan.')->toContain('<img src=x> perbaiki');
    expect($card->filter('img')->count())->toBe(0);
    expect($card->filter('a[data-document-preview]')->count())->toBe(0);
    expect($crawler->filter('body [data-document-preview-modal]')->count())->toBe(1);
    expect($crawler->filter('[data-ta-decision] input[name="registration_version"]')->attr('value'))->toBe((string) $registration->lock_version);
});

test('p2a_list_eager_loads_all_page_documents_in_one_query', function () {
    foreach (range(1, 15) as $i) {
        $registration = ($this->makeRegistration)();
        ($this->attach)($registration, Requirements::CAPTURES[0]);
    }
    $queries = [];
    DB::listen(function (QueryExecuted $event) use (&$queries) {
        if (str_contains(strtolower($event->sql), 'sidang_ta_registration_documents')) $queries[] = $event->sql;
    });
    ($this->listing)()->assertViewHas('registrations', fn ($rows) => $rows->every(fn ($row) => $row->relationLoaded('documents')));
    expect($queries)->toHaveCount(1);
});

test('p2a_unknown_program_filter_remains_visible_and_escaped_with_zero_results', function () {
    ($this->makeRegistration)(['study_program' => 'Prodi Historis']);
    $value = '<b>Prodi tidak tersedia</b>';
    $response = ($this->listing)(['study_program' => $value])->assertViewHas('counts', fn ($counts) => $counts['total'] === 0);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('select[name="study_program"] option[selected]')->attr('value'))->toBe($value);
    expect($crawler->filter('select[name="study_program"] b')->count())->toBe(0);
    expect($crawler->filter('select[name="study_program"]')->text(null, true))->toContain('Prodi Historis');
});

```

## 8. Command test dan manual

```bash
php artisan view:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaReviewListTest.php tests/Feature/SidangTaStaffNavigationTest.php tests/Feature/SidangTaDocumentPreviewTest.php
```

Target fokus **36 passed** =14P2a+12P1+10Preview. Suite TA lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDraftCardTest.php tests/Feature/SidangTaReviewUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaPartialDraftTest.php tests/Feature/SidangTaStudentPolishTest.php tests/Feature/SidangTaDocumentPreviewTest.php tests/Feature/SidangTaStaffNavigationTest.php tests/Feature/SidangTaReviewListTest.php
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
npm run build
```

Target **152 PHP, 12 Node**, build berhasil. Tidak ada perubahan test existing atau konfigurasi test. Tetap MySQL Podman schema dadu_testing, bukan dadu.

Manual:

- [ ] Reviewer/petugas melihat seluruh periode aktif beserta status dan waktu. Pilih satu periode nonaktif di filter: banner menampilkan periode itu. Tidak ada asumsi satu periode aktif.
- [ ] Kombinasikan pencarian, periode, status dan prodi. Tabel dan kelima statistik sesuai hasil kombinasi; status lain dapat0 jika status tertentu difilter. Reset menghapus filter.
- [ ] Data lebih15: pagination mempertahankan empat filter, statistik tetap total seluruh hasil, bukan hanya halaman aktif.
- [ ] Tiap baris memiliki 6+7+3 posisi ikon. Cabang Sesuai: PDM N/A; cabang PDM: dua dokumen ijazah N/A. Tidak menghitung N/A sebagai berkas hilang.
- [ ] Pending/verified/rejected berbeda warna. Berkas wajib tanpa metadata dan file hilang mempunyai keterangan berbeda. Pengajuan approved tidak membuat dokumen pending tampak verified.
- [ ] Klik ikon PDF/gambar: modal preview, Unduh, tab baru, Tutup/Escape berfungsi. Tooltip dan aria-label berisi jenis dokumen/status/nama; keyboard dapat fokus ke ikon.
- [ ] Klik Tinjau: snapshot historis dan kontak terlihat, berkas dapat dipreview dengan modal yang sama. Catatan/status dokumen read-only; belum ada tombol verifikasi individual.
- [ ] Akun review-only tanpa read mahasiswa tetap bisa daftar/detail/preview; period-only hanya hub Kelola Periode, tanpa nama/NIM/statistik/pilihan prodi/modal.
- [ ] Layar sempit: filter membungkus rapi, tabel dapat digeser horizontal, modal dapat dibuka/ditutup tanpa keluar dari halaman.
- [ ] Pendaftaran/draft/revisi student dan permission sidebar P1 tetap bekerja.

## 9. Handoff berikutnya

Salin enam file pada bagian2 (termasuk service dan partial/test baru) sesudah hasil test/manual lulus. Asisten akan rescan sebelum **P2b ekspor Excel**. Belum membuat export route/button atau perubahan verifikasi/keputusan P3, dan tidak melakukan commit/push.

Baseline student sudah selesai; P1/P2a merupakan perubahan petugas. Jangan ikutkan .env, phpunit.xml sementara, database/cache/build/dependencies ketika menyalin atau menyiapkan commit nantinya.

## 10. Pemeriksaan panduan oleh asisten

Tiga blok PHP dan tiga blok Blade lulus pemeriksaan sintaks; Blade dikompilasi di memori tanpa component tags. Probe query memakai connection resolver MySQL tanpa membuka PDO memverifikasi pengelompokan OR nama/NIM dan binding empat filter. Probe validasi dengan presence stub memverifikasi normalisasi, search0, page tidak menjadi filter, serta penolakan nilai invalid. Probe render partial ikon dengan storage stub memverifikasi16posisi, cabang15/14, status aktual walau registration approved, missing/unavailable, dan escaping filename. Semua probe lulus tanpa bootstrap aplikasi atau database.

Review independen tidak menemukan blocker akses/query/fixture. Kasus page di luar rentang ditemukan saat review dan diperbaiki menjadi pesan halaman kosong serta link halaman pertama, dengan tambahan assertion pada test pagination existing. Dua belas test Node existing dijalankan ulang dan lulus. Source kedua folder dipindai sebelum/sesudah penulisan dan tidak diubah.

Feature152, build dan manual P2a belum dijalankan pengguna; hasil138 P1 tidak menjadi bukti P2a lulus. Asisten tidak menjalankan aplikasi/DB/suite Feature/build atau mengubah konfigurasi maupun source.

Referensi Context7: [Eloquent eager loading Laravel13](https://github.com/laravel/docs/blob/13.x/eloquent-relationships.md), [pagination appends](https://github.com/laravel/docs/blob/13.x/pagination.md). Query/filter memakai binding Eloquent dan validasi Laravel existing.
