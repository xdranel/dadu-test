# 61c — View current Sidang TA

Isi lengkap8 Blade view current-only, tanpa upgrade prompt atau legacy fallback.

### `resources/views/sidang_ta_registrations/admin/index.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Review Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $groupTitles = ['iGracias & Akademik', 'Pembayaran & Dokumen', 'Biodata'];
        $at = now();
    @endphp
    <div data-ta-review-hub class="space-y-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-800">
                    Review Sidang TA
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Tinjau pengajuan dan kelengkapan berkas mahasiswa.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canReview)
                    @if($counts['total'] > 0)
                        <a data-ta-export href="{{ route('admin.sidang-ta-registrations.export', $filters) }}" class="btn btn-success">
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
                            Ekspor Excel
                        </a>
                    @else
                        <button data-ta-export-empty type="button" disabled class="btn btn-success cursor-not-allowed opacity-50" title="Tidak ada pengajuan untuk diekspor">Ekspor Excel</button>
                    @endif
                @endif
                @can('sidang_ta_period_read')
                    <a data-manage-ta-periods href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-secondary">Kelola Periode</a>
                @endcan
            </div>
        </header>
        @if(session('success'))
            <div role="status"
                 class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if($canReview)
            <div data-ta-review-data class="space-y-5">
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">{{ isset($filters['period']) ? 'Periode terpilih' : 'Periode aktif' }}</h3>
                    <div class="grid gap-3 lg:grid-cols-2">
                        @forelse($periodCards as $period)
                            @php
                                $state = match (true) { !$period->is_active => 'Tidak aktif', $at->lt($period->open_at) => 'Belum dibuka', $at->gt($period->close_at) => 'Ditutup', default => 'Sedang terbuka' };
                            @endphp
                            <article data-ta-period-banner="{{ $period->id }}"
                                     class="rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <h4 class="break-words font-bold text-slate-800">
                                        {{ $period->name }}
                                    </h4>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                        {{ $state }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                                    Dibuka {{ $period->open_at->format('d/m/Y H:i') }} ·
                                    Tenggat {{ $period->close_at->format('d/m/Y H:i') }}
                                </p>
                            </article>
                        @empty
                            <p data-ta-no-period
                               class="rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                                {{ isset($filters['period']) ? 'Periode terpilih tidak tersedia.' : 'Belum ada periode aktif. Riwayat pengajuan tetap dapat ditinjau.' }}
                            </p>
                        @endforelse
                    </div>
                </section>
                <section class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-800">
                        Ringkasan hasil filter
                    </h3>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                        @foreach(['total' => 'Total'] + $statusLabels as $key => $label)
                            <article class="rounded-xl border border-slate-200 bg-white p-4">
                                <p class="text-xs text-slate-500">
                                    {{ $label }}
                                </p>
                                <p data-ta-stat="{{ $key }}"
                                   class="mt-2 text-2xl font-bold {{ $key === 'total' ? 'text-[#DA2D3B]' : 'text-slate-800' }}">
                                    {{ $counts[$key] }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </section>
                <form data-ta-filters method="GET" action="{{ route('admin.sidang-ta-registrations.index') }}"
                      class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-5">
                    <label class="text-xs font-semibold text-slate-600">Nama atau NIM
                        <input class="campus-input mt-1" name="search" value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari nama atau NIM">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Periode
                        <select class="campus-select mt-1" name="period">
                            <option value="">Semua periode</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected(($filters['period'] ?? null) == $period->id)>
                                    {{ $period->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Status
                        <select class="campus-select mt-1" name="status">
                            <option value="">Semua status</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Program studi
                        <select class="campus-select mt-1" name="study_program">
                            <option value="">Semua prodi</option>
                            @if(isset($filters['study_program']) && !$studyPrograms->contains($filters['study_program']))
                                <option value="{{ $filters['study_program'] }}" selected>{{ $filters['study_program'] }}
                                </option>
                            @endif
                            @foreach($studyPrograms as $program)
                                <option value="{{ $program }}" @selected(($filters['study_program'] ?? '') === $program)>
                                    {{ $program }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex flex-wrap items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            Terapkan
                        </button>
                        <a data-ta-reset href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">
                            Reset
                        </a>
                    </div>
                </form>
                <p class="text-xs leading-relaxed text-slate-500">
                    Ikon berkas: kuning menunggu verifikasi, hijau terverifikasi, merah perlu perbaikan, tanda ! belum ada/tidak ditemukan, tanda – tidak berlaku.
                    Arahkan kursor atau fokuskan ikon untuk melihat nama dan status.
                </p>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Mahasiswa</th>
                                <th class="px-4 py-3">Prodi</th>
                                <th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3">Dikirim</th>
                                @foreach($groupTitles as $title)
                                    <th class="px-4 py-3">{{ $title }}</th>
                                @endforeach
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Aksi</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($registrations as $registration)
                                @php
                                    $requiredTypes = \App\Support\SidangTaReviewEligibility::requiredTypes($registration);
                                    $rowGroups = \App\Support\SidangTaReviewEligibility::groups($registration);
                                @endphp
                                <tr data-ta-row="{{ $registration->id }}" class="hover:bg-slate-50">
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-800">{{ $registration->student_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $registration->student_number }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->study_program ?: 'Belum tersedia' }}
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->period?->name ?? 'Periode tidak tersedia' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-600">
                                        {{ $registration->submitted_at?->format('d/m/Y H:i') ?? '-' }}
                                    </td>
                                    @foreach($rowGroups as $types)
                                        <td class="px-4 py-4">
                                            <div class="flex max-w-32 flex-wrap gap-1.5">@include('sidang_ta_registrations.admin.partials.document-icons')</div>
                                        </td>
                                    @endforeach
                                    <td class="px-4 py-4">
                                        <x-status-badge :status="$registration->status"
                                                        :label="$statusLabels[$registration->status] ?? $registration->status"/>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a data-ta-detail href="{{ route('admin.sidang-ta-registrations.show', $registration) }}"
                                           class="btn btn-secondary" aria-label="Tinjau pengajuan {{ $registration->student_name }}"
                                           title="Tinjau pengajuan">
                                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor">
                                                <path stroke-width="1.7"
                                                      d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                                                <circle cx="12" cy="12" r="3" stroke-width="1.7"/>
                                            </svg>
                                            <span>Tinjau</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" data-ta-empty class="px-4 py-12 text-center text-sm text-slate-500">
                                        @if($registrations->total() > 0)
                                            <p>Halaman ini tidak berisi pengajuan.</p>
                                            <a data-ta-first-page
                                               href="{{ route('admin.sidang-ta-registrations.index', $filters) }}"
                                               class="mt-3 inline-flex font-semibold text-[#DA2D3B] hover:underline">Kembali
                                                ke halaman pertama
                                            </a>
                                        @else
                                            {{ $filters !== [] ? 'Tidak ada pengajuan yang cocok dengan filter.' : 'Belum ada pengajuan Sidang TA.' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-3">{{ $registrations->links() }}</div>
                </section>
            </div>
        @else
            <section data-ta-period-only class="campus-card space-y-2 p-5">
                <h3 class="font-bold text-slate-800">
                    Pengaturan periode pendaftaran
                </h3>
                <p class="text-sm text-slate-500">
                    Gunakan Kelola Periode untuk melihat jadwal pendaftaran sidang tugas akhir.
                </p>
            </section>
        @endif
    </div>
    @if($canReview)
        @include('sidang_ta_registrations.partials.document-preview-modal')
    @endif
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/admin/partials/document-review-form.blade.php`

```blade
@if($reviewable && in_array($document->document_type, $requiredTypes, true))
    @php
        $oldForm = old('_review_form') === ('document:'.$document->id);
        $rawNotes = $oldForm ? old('notes') : $document->notes;
        $notesValue = is_string($rawNotes) ? $rawNotes : '';
        $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
        $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    @endphp
    <form data-doc-review-form="{{ $document->id }}" method="POST" novalidate
          action="{{ route('admin.sidang-ta-registrations.documents.review', ['sidang_ta_registration' => $registration, 'document' => $document]) }}"
          class="w-full space-y-3 border-t border-slate-100 pt-3">
        @csrf
        <input type="hidden" name="_review_form" value="document:{{ $document->id }}">
        <input type="hidden" name="registration_version" value="{{ $versionValue }}">
        <label class="block text-xs font-semibold text-slate-600" for="document-notes-{{ $document->id }}">
            Catatan dokumen (wajib jika meminta perbaikan)
        </label>
        <textarea id="document-notes-{{ $document->id }}" name="notes" maxlength="2000" class="campus-input min-h-20">{{ $notesValue }}</textarea>
        @if($oldForm && $errors->has('notes'))
            <p role="alert" class="text-xs text-rose-700">
                {{ $errors->first('notes') }}
            </p>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <button data-doc-reject type="submit" name="status" value="rejected" class="btn btn-secondary">
                Minta perbaikan
            </button>
            <button data-doc-verify type="submit" name="status" value="verified"
                    @disabled(!$available || $document->status === 'verified')
                    class="btn btn-success disabled:cursor-not-allowed disabled:opacity-50">
                Verifikasi
            </button>
        </div>
    </form>
@endif
```

### `resources/views/sidang_ta_registrations/admin/partials/registration-decision.blade.php`

```blade
@php
    $revisionBlockers = $revisionBlockers ?? ['Data kelengkapan revisi belum tersedia. Muat ulang halaman.'];
    $oldForm = old('_review_form') === 'decision';
    $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
    $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    $rawNotes = $oldForm ? old('review_notes') : $registration->review_notes;
    $notesValue = is_string($rawNotes) ? $rawNotes : '';
@endphp
<form data-ta-decision method="POST" novalidate
      action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
    @csrf
    <input type="hidden" name="_review_form" value="decision">
    <input type="hidden" name="registration_version" value="{{ $versionValue }}">
    <h3 class="font-bold text-slate-800">
        Keputusan petugas
    </h3>
    @if($revisionBlockers !== [])
        <div data-ta-revision-blockers role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Selesaikan pemeriksaan dokumen sebelum meminta revisi.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($revisionBlockers as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if($approvalBlockers !== [])
        <div data-ta-approval-blockers
             class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">
                Setujui tersedia setelah seluruh dokumen wajib terverifikasi dan berkasnya tersedia.
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($approvalBlockers as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">
            Muat ulang pengajuan
        </a>
    @endif
    <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)
        <textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea>
    </label>
    @if($oldForm && $errors->has('review_notes'))
        <p class="text-xs text-rose-700">
            {{ $errors->first('review_notes') }}
        </p>
    @endif
    <div class="flex flex-wrap justify-end gap-2">
        <button data-ta-request-revision type="submit"
                class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="needs_review" @disabled($revisionBlockers !== [])>
            Minta Revisi
        </button>
        <button type="submit" class="btn btn-secondary" name="action" value="rejected">
            Tolak
        </button>
        <button data-ta-approve type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="approved" @disabled($approvalBlockers !== [])>
            Setujui
        </button>
    </div>
</form>
```

### `resources/views/sidang_ta_registrations/admin/show.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Peninjauan Pendaftaran Sidang TA</x-slot>
    @php
        $statusLabels = \App\Services\SidangTaReviewQuery::STATUSES;
        $documentLabels = [
            'pending' => 'Menunggu verifikasi',
            'verified' => 'Terverifikasi',
            'rejected' => 'Perlu perbaikan'
            ];
        $actionLabels = [
            'submitted' => 'Pendaftaran dikirim',
            'resubmitted' => 'Revisi dikirim',
            'needs_review' => 'Diminta revisi',
            'approved' => 'Pengajuan disetujui',
            'rejected' => 'Pengajuan ditolak',
            'document_verified' => 'Dokumen diverifikasi',
            'document_rejected' => 'Dokumen diminta perbaikan',
            ];
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-[#DA2D3B]">
                    {{ $registration->period?->name ?? 'Periode tidak tersedia' }}
                </p>
                <p data-ta-review-window class="mt-1 text-xs text-slate-500">
                    Dibuka {{ $registration->period?->open_at?->format('d/m/Y H:i') ?? '-' }} ·
                    Tenggat {{ $registration->period?->close_at?->format('d/m/Y H:i') ?? '-' }}
                </p>
                <h2 class="mt-1 break-words text-xl font-bold text-slate-900">
                    {{ $registration->student_name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $registration->student_number }} · {{ $registration->study_program ?: 'Program studi belum tersedia' }}
                </p>
            </div>
            <a href="{{ route('admin.sidang-ta-registrations.index') }}" class="btn btn-secondary">
                Kembali ke Review Sidang TA
            </a>
        </header>
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        <section class="campus-card space-y-4 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold text-slate-800">
                    Data pendaftaran
                </h3>
                <x-status-badge :status="$registration->status"
                                :label="$statusLabels[$registration->status] ?? $registration->status"/>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach(['HP mahasiswa' => $registration->student_phone, 'HP orang tua/wali' => $registration->parent_phone, 'Pembayaran wisuda' => $registration->graduation_payment_date?->format('d/m/Y'), 'Biodata ijazah' => $registration->diploma_biodata_accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan'] as $label => $value)
                    <div>
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-medium text-slate-800">{{ $value ?? 'Belum tersedia' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($readonlyNeedsReview)
            <p data-meet-review-locked role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                Pengajuan sedang menunggu revisi mahasiswa. Berkas dapat dilihat; perubahan peninjauan dikunci.
            </p>
        @endif
        @include('sidang_ta_registrations.partials.detail-academic')
        @include('sidang_ta_registrations.admin.partials.meet-document-groups')
        @if($reviewable)
            @include('sidang_ta_registrations.admin.partials.registration-decision')
        @endif
        @if($registration->review_notes)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h3 class="text-sm font-bold text-amber-900">
                    Catatan petugas
                </h3>
                <p class="mt-1 whitespace-pre-line break-words text-sm text-amber-800">
                    {{ $registration->review_notes }}
                </p>
            </section>
        @endif
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
                        <p class="text-sm font-semibold text-slate-800">
                            {{ $actionLabels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $log->performed_at?->format('d/m/Y H:i') }}
                        </p>
                        @if(filled($log->notes))
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">
                                {{ $log->notes }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-500">
                        Belum ada riwayat status.
                    </li>
                @endforelse
            </ol>
        </details>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php`

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
    // Fields outside the academic summary remain stored without appearing in the detail panel.
    $omitted = ['level_3_status', 'bpp_status', 'ibasila_status', 'skpi_tak',
        'advisor_1', 'advisor_2', 'examiner_1', 'examiner_2', 'examiner_3',
        'exam_date', 'exam_time', 'exam_location'];
    $known = array_merge(array_values($identity), array_column($metrics, 0), array_values($statuses),
        array_values($titles), $omitted, ['academic_source_updated_at', 'ta2_krs_status', 'admission']);
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
        <h2 class="text-lg font-bold text-slate-900">
            Data akademik saat pengajuan
        </h2>
        <p class="text-sm leading-relaxed text-slate-500">
            Data direkam saat pengajuan dikirim. Perubahan data akademik setelahnya tidak mengubah ringkasan tersimpan ini.
        </p>
    </header>
    <div class="space-y-6 p-5 sm:p-6">
        <section data-academic-group="identity">
            <h3 class="text-sm font-bold text-slate-800">
                Identitas akademik
            </h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach($identity as $label => $key)
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-1 break-words text-sm font-medium text-slate-800">
                            {{ $display($snapshot[$key] ?? null) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="metrics">
            <h3 class="text-sm font-bold text-slate-800">
                Ringkasan akademik
            </h3>
            <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach($metrics as $label => [$key, $digits])
                    <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-2 break-words text-xl font-bold text-slate-900">
                            {{ $number($snapshot[$key] ?? null, $digits) }}
                        </dd>
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
                            class="max-w-full break-words rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($value) }}">
                            {{ $statusLabel($value) }}
                        </dd>
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
                            class="mt-1.5 break-words rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">
                            {{ $display($snapshot[$key] ?? null) }}
                        </dd>
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
                            <dt class="break-words text-xs text-slate-500">
                                {{ ucfirst(str_replace('_', ' ', (string) $key)) }}
                            </dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">
                                {{ $display($value) }}
                            </dd>
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

### `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
    $lockedUpload = $revisionExpired || ((bool) ($meetContext['targeted'] ?? false)
        && !in_array($type, $meetContext['editable_types'] ?? [], true));
    $replacementNeeded = (bool) ($meetContext['targeted'] ?? false) && !$lockedUpload
        && $existing && $existing->source_document_id !== null;
    $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, \App\Support\SidangTaMeetRequirements::CAPTURES, true);
    $guide = match ($type) {
        'ta_data_capture' => 'assets/images/forms/sidang-ta/ta-data.png',
        'online_supervision_capture' => 'assets/images/forms/sidang-ta/bimbingan-online.png',
        default => null,
    };
    $guideAvailable = $guide && is_file(public_path($guide));
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" data-upload-locked="{{ $lockedUpload ? 'true' : 'false' }}"
     class="min-w-0 rounded-xl border border-slate-200 p-4 {{ $lockedUpload ? 'bg-slate-100 text-slate-500' : 'bg-white' }}"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} @unless($lockedUpload)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        @unless($guideAvailable)
            {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        @endunless
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($guideAvailable)
        <button data-upload-guide data-document-preview type="button"
                class="mt-3 block w-full rounded-lg text-left focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2"
                data-preview-url="{{ asset($guide) }}" data-download-url=""
                data-file-name="Contoh {{ $label }}" data-file-type="png"
                aria-label="Lihat contoh {{ $label }}" aria-haspopup="dialog"
                x-on:click="$dispatch('sidang-ta-preview', $el.dataset)">
            <img loading="lazy" src="{{ asset($guide) }}" alt="Panduan {{ $label }}"
                 class="w-full rounded-lg border border-slate-200 object-contain">
            <span class="mt-1 block text-xs font-medium text-[#DA2D3B]">Lihat contoh</span>
        </button>

    @endif
    @if($type === \App\Support\SidangTaMeetRequirements::WAIVER)
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            Unduh template, lengkapi, lalu unggah kembali dalam format PDF.
            <a data-waiver-template href="https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/EYC5y40Ky_1PhVWh4_LswJoBtC8yKT6PuTEAwUTDiFY-2A?e=qRenjo"
               target="_blank" rel="noopener" class="font-semibold text-[#DA2D3B] underline">Link Surat Permakluman</a>
        </p>
    @endif
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · {{ $lockedUpload ? 'Berkas ini tetap digunakan.' : 'Pilih berkas baru hanya untuk mengganti.' }}
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            {{ $revisionExpired ? 'Berkas tersimpan tidak ditemukan. Batas waktu unggah pengganti telah berakhir.'
                : 'Berkas tersimpan tidak ditemukan. Unggah kembali.' }}
        </p>
    @endif
    @if($replacementNeeded)
        <p data-replacement-needed class="mt-3 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-900">
            Unggah berkas pengganti sesuai catatan petugas. Berkas pengajuan sebelumnya belum dihitung sebagai pengganti.
        </p>
    @endif
    @if($lockedUpload)
        <p data-upload-preserved class="mt-3 rounded-lg bg-slate-200/60 p-3 text-xs text-slate-600">
            {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Unggah pengganti sudah ditutup.'
                : 'Dokumen terverifikasi tetap digunakan. Pengunggahan hanya tersedia untuk berkas yang diminta diperbaiki.' }}
        </p>
    @else
        <div
            class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
            :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
            @dragenter.prevent.stop="enter()" @dragover.prevent.stop
            @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
            <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
                   accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                   aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
                   aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                   :aria-invalid="hasError() ? 'true' : 'false'"
                   @change="picked()"
                   @if($conditional !== null)
                       @disabled($accurate !== $conditional)
                       :disabled="accurate !== '{{ $conditional }}'"
                   :required="accurate === '{{ $conditional }}' && {{ $available && !$replacementNeeded ? 'false' : 'true' }}"
            @else
                @required(!$available || $replacementNeeded)
                @endif>
            <div class="pointer-events-none py-3 text-center">
                <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                          d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
                </svg>
                <p class="mt-2 text-sm font-medium text-slate-700">
                    Klik untuk memilih atau seret file ke sini
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas
                </p>
            </div>
        </div>
        <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p class="text-xs font-semibold text-amber-800">
                Pilihan baru · Belum disimpan
            </p>
            <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
            <p class="text-xs text-slate-500" x-text="fileSize"></p>
            <button type="button" class="btn btn-secondary text-xs" @click="clear()">
                Batalkan pilihan baru
            </button>
        </div>
        <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
           class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
            <span x-show="localError" x-text="localError"></span>
            <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
        </p>
    @endif
</div>
```

### `resources/views/sidang_ta_registrations/wizard-review.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Tinjau Pendaftaran Sidang TA</x-slot>
    @php
        $payload = $draft->payload;
        $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
        $sendBlocked = !($meetContext['can_submit'] ?? false);
        $accurate = (bool) $payload['diploma_biodata_accurate'];
        $isRevision = $draft->source_registration_id !== null;
        $permission = $isRevision ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $groups = [2 => 'Verifikasi iGracias & akademik', 3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
        $documentCount = array_sum(array_map('count', $documentGroups));
        $confirmLabel = $isRevision ? 'Ya, kirim revisi' : 'Ya, kirim pendaftaran';
    @endphp
    <div class="mx-auto max-w-6xl space-y-5">
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Pendaftaran belum dapat dikirim.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section data-review-summary class="campus-card overflow-hidden">
            <header class="border-b border-rose-100 bg-rose-50/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#DA2D3B]">Pemeriksaan
                            akhir{{ $isRevision ? ' · Revisi' : '' }}</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Tinjau sebelum kirim</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Periksa kembali data dan
                            seluruh berkas sebelum mengirim pendaftaran kepada petugas.</p>
                    </div>
                    <span
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Belum dikirim</span>
                </div>
            </header>
            <div class="space-y-5 p-5 sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Mahasiswa</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $student->name }}</p>
                        <p class="mt-1 break-words text-sm text-slate-600">{{ $student->student_number }}
                            · {{ $student->study_program ?: 'Program studi belum tersedia' }}</p>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold text-slate-500">Periode Sidang TA</p>
                        <p class="mt-1 break-words text-base font-bold text-slate-900">{{ $draft->period->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $documentCount }} dokumen dalam
                            tinjauan{{ $isRevision ? ' · Periode pengajuan asal' : '' }}</p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP mahasiswa
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['student_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Nomor HP orang tua/wali
                        </dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-slate-800">
                            {{ $payload['parent_phone'] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Tanggal pembayaran wisuda
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ \Illuminate\Support\Carbon::parse($payload['graduation_payment_date'])->translatedFormat('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Biodata dummy ijazah
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">
                            {{ $accurate ? 'Sudah sesuai' : 'Belum sesuai, bukti PDM dilampirkan' }}
                        </dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Data di bawah berasal dari draft yang tersimpan. Pendaftaran belum dikirim.
                    </p>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Ubah data kontak
                    </a>
                </div>
            </div>
        </section>

        @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission' => $meetContext['admission']])

        @foreach($groups as $number => $title)
            @php
                $types = $documentGroups[$number];
            @endphp
            <section data-review-group="{{ $number }}" class="campus-card overflow-hidden">
                <header
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">
                                {{ $title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Tahap {{ $number }} · {{ count($types) }} berkas tersimpan
                            </p>
                        </div>
                    </div>
                    <a class="text-sm font-semibold text-[#DA2D3B] hover:underline"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}">Ubah
                        tahap {{ $number }}</a>
                </header>
                @if(isset($meetContext['automatic'][$number]))
                    <div class="px-5 pt-4 sm:px-6">
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$number]])
                    </div>
                @endif
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($types as $type)
                        @php
                            $document = $draft->documents->firstWhere('document_type', $type);
                            $documentLabel = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
                        @endphp
                        <div data-review-document="{{ $type }}"
                             class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ $documentLabel }}</p>
                                <p class="mt-1 break-words text-xs leading-relaxed text-slate-500">{{ $document->file_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format(($document->file_size ?? 0) / 1024) }}
                                    KB</p>
                            </div>
                            <a data-document-preview
                               class="btn btn-secondary shrink-0 self-start whitespace-nowrap !text-xs no-underline sm:self-auto"
                               href="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-preview-url="{{ route('sidang-ta-draft-documents.preview', $document) }}"
                               data-download-url="{{ route('sidang-ta-draft-documents.download', $document) }}"
                               data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
                               target="_blank" rel="noopener" aria-haspopup="dialog"
                               aria-label="Lihat {{ $documentLabel }}"
                               x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)">
                                Lihat berkas
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section data-review-actions class="campus-card space-y-4 p-5 sm:p-6"
                 x-data="{ sending: false }" @pageshow.window="sending = false">
            <div>
                <h2 class="text-base font-bold text-slate-800">
                    Sudah memeriksa semua data?
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    Pastikan periode, kontak, pembayaran, biodata, dan setiap berkas sudah benar. Anda masih dapat
                    kembali ke form untuk memperbaikinya.
                </p>
            </div>
            @if($sendBlocked)
                <p id="registration-send-readiness" data-meet-send-held @if($revisionExpired) data-revision-deadline-expired @endif role="{{ $revisionExpired ? 'alert' : 'status' }}" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Pengiriman revisi sudah ditutup. Data dan berkas tetap tersimpan untuk dilihat.'
                        : ($isRevision ? 'Penuhi persyaratan akademik sebelum mengirim revisi. Data dan berkas tetap tersimpan.'
                            : 'Penuhi persyaratan akademik dan EPRT sebelum mengirim. Draft dan berkas tetap tersimpan.') }}
                </p>
            @endif
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary"
                       href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 1]) }}">
                        Kembali ke form
                    </a>
                    <a class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">
                        Kembali ke pendaftaran
                    </a>
                </div>
                @can($permission)
                    <button data-open-registration type="button"
                            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                            :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                            @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                            @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran
                    </button>

                @endcan
            </div>
            @can($permission)
                <dialog id="confirm-send-registration" aria-labelledby="confirm-send-title"
                        aria-describedby="confirm-send-description"
                        @cancel="if (sending) $event.preventDefault()"
                        class="m-auto w-11/12 max-w-lg rounded-2xl border border-slate-200 bg-white p-5 text-slate-800 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
                    <h2 id="confirm-send-title" class="text-lg font-bold">
                        {{ $isRevision ? 'Kirim revisi pendaftaran?' : 'Kirim pendaftaran sekarang?' }}
                    </h2>
                    <div id="confirm-send-description" class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                        <p>Periksa kembali data dan seluruh dokumen. Setelah dikirim, pengajuan akan ditinjau oleh
                            petugas.</p>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Pastikan kembali:</p>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                <li>Periode Sidang TA dan nomor kontak sudah benar.</li>
                                <li>Tanggal pembayaran wisuda serta pilihan biodata sudah sesuai.</li>
                                <li>Seluruh berkas lengkap, benar, dan dapat dibaca.</li>
                            </ul>
                        </div>
                        <p>Jika masih ragu, pilih Periksa kembali. Jika sudah yakin, lanjutkan pengiriman. Perubahan
                            setelah pengiriman mengikuti proses revisi yang tersedia.</p>
                    </div>
                    <form data-final-registration-form method="POST"
                          class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                          action="{{ $isRevision ? route('sidang-ta-registrations.update', $draft->source_registration_id) : route('sidang-ta-registrations.store') }}"
                          @submit="if (sending || {{ $sendBlocked ? 'true' : 'false' }}) { $event.preventDefault(); } else { sending = true; }">
                        @csrf
                        @if($isRevision)
                            @method('PUT')
                        @endif
                        <input type="hidden" name="draft_id" value="{{ $draft->id }}">
                        <input type="hidden" name="version" value="{{ $draft->version }}">
                        <input type="hidden" name="action" value="submit">
                        <button data-cancel-registration type="button" autofocus class="btn btn-secondary"
                                :disabled="sending"
                                onclick="this.closest('dialog').close()">Periksa kembali
                        </button>
                        <button data-confirm-registration type="submit"
                                class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                                :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                                @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                                @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}
                        </button>
                    </form>
                </dialog>
            @endcan
        </section>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```

### `resources/views/sidang_ta_registrations/wizard.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">
        {{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}
    </x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $targetedRevision = (bool) ($meetContext['targeted'] ?? false);
        $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
        $rawAccurate = $targetedRevision ? ($payload['diploma_biodata_accurate'] ?? null)
            : old('diploma_biodata_accurate', $payload['diploma_biodata_accurate'] ?? null);
        $accurate = match ($rawAccurate) {
            true, 1, '1' => '1', false, 0, '0' => '0', default => '',
        };
        $stepProgress = $stepProgress ?? ['complete' => false, 'stored_documents' => 0,
            'required_documents' => 0, 'missing' => []];
        $selectedPeriod = $targetedRevision ? (string) $draft->sidang_ta_period_id
            : (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $reviewReady = $draft && $completedThrough === 4 && !$revisionExpired;
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert"
                 class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">
                    Periksa isian berikut:
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">
                    Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.
                </p>
            </div>
        @endif
        @if(session('success'))
            <p role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">
                                        Tahap {{ $number }}
                                    </span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">
                                        {{ $title }}
                                    </span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status
                 class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">
                    {{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}
                </p>
                <p class="text-slate-500">
                    Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.
                </p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        @if($revisionExpired)
            <div data-revision-deadline-expired role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Batas waktu revisi telah berakhir. Draft dan berkas tetap tersimpan untuk dilihat, tetapi penyimpanan,
                unggah pengganti, tinjauan baru, dan pengiriman revisi sudah ditutup.
                Anda tetap dapat kembali ke daftar pendaftaran untuk menghapus draft.
            </div>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">
                            Catatan petugas
                        </h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">
                            {{ $draft->sourceRegistration->review_notes }}
                        </p>
                    </section>
                @endif
                @if($draft?->sourceRegistration)
                    @include('sidang_ta_registrations.partials.revision-feedback', ['registration' => $draft->sourceRegistration])
                @endif

                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy || {{ $revisionExpired ? 'true' : 'false' }}) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)
                        <input type="hidden" name="version" value="{{ old('version', $draft->version) }}">
                    @endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">
                            Tanda * menunjukkan unggahan wajib. Draft boleh disimpan sebelum lengkap.
                        </p>
                    </header>
                    @if($draft && !$stepProgress['complete'])
                        <section data-step-progress role="status"
                                 class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">
                                Lengkapi tahap ini sebelum melanjutkan.
                            </p>
                            @if($stepProgress['required_documents'] !== null && $stepProgress['required_documents'] > 0)
                                <p>
                                    {{ $stepProgress['stored_documents'] }}
                                    dari {{ $stepProgress['required_documents'] }} dokumen tersimpan.
                                </p>
                            @endif
                            <p class="text-xs">
                                Masih perlu dilengkapi:
                            </p>
                            <ul class="list-inside list-disc space-y-1 text-xs leading-relaxed">
                                @foreach($stepProgress['missing'] as $missing)
                                    <li>{{ $missing }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                Periode Sidang TA 
                            </label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id"
                                       value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1"
                                   class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">
                                    {{ $draft->period->name }}(periode pengajuan asal)
                                </p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}"
                                                data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>
                                            {{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP mahasiswa 
                                </label>
                                <input id="student-phone"
                                       class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-student_phone"
                                       value="{{ $targetedRevision ? ($payload['student_phone'] ?? '') : old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Nomor HP orang tua/wali 
                                </label>
                                <input id="parent-phone"
                                       class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" @readonly($targetedRevision) inputmode="tel" maxlength="30" required
                                       placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}"
                                       aria-describedby="error-parent_phone"
                                       value="{{ $targetedRevision ? ($payload['parent_phone'] ?? '') : old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date"
                                       class="mb-1.5 block text-sm font-semibold text-slate-700">
                                    Tanggal pembayaran wisuda 
                                </label>
                                <input id="graduation-payment-date"
                                       class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" @readonly($targetedRevision) required
                                       max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}"
                                       aria-describedby="error-graduation_payment_date"
                                       value="{{ $targetedRevision ? ($payload['graduation_payment_date'] ?? '') : old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        @include('sidang_ta_registrations.partials.meet-automatic-cards', ['items' => $meetContext['automatic'][$step] ?? []])
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach($meetContext['types'][$step] as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        @if($targetedRevision)
                            <input type="hidden" name="diploma_biodata_accurate" value="{{ $accurate }}">
                        @endif
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">
                                Apakah biodata dummy ijazah sudah sesuai? 
                            </legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                        :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" @disabled($targetedRevision) value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Menyimpan pilihan biodata yang berbeda akan mengganti lampiran cabang draft sebelumnya.
                            Berkas pengajuan final tetap tersimpan.
                        </p>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary"
                           href="{{ route('sidang-ta-registrations.index') }}">
                            Kembali ke pendaftaran
                        </a>
                        @can($permission)
                            <div class="flex flex-wrap items-center gap-2">
                                <button data-save-draft type="submit" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                                        @disabled($revisionExpired) :disabled="busy || {{ $revisionExpired ? 'true' : 'false' }}"
                                        x-text="busy ? 'Sedang menyimpan…' : 'Simpan draft'">
                                    Simpan draft
                                </button>
                                @if($step < 4)
                                    @if($draft && $stepProgress['complete'])
                                        <a data-next-step class="btn btn-primary" aria-disabled="false"
                                           href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step + 1]) }}"
                                           :aria-disabled="dirty || busy ? 'true' : 'false'"
                                           :tabindex="dirty || busy ? -1 : 0"
                                           :class="dirty || busy ? 'pointer-events-none opacity-50' : ''"
                                           @click="if (dirty || busy) $event.preventDefault()">
                                            Lanjutkan
                                        </a>
                                    @else
                                        <button data-next-step type="button" disabled
                                                class="btn btn-primary cursor-not-allowed opacity-50">
                                            Lanjutkan
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form data-review-draft method="POST" class="campus-card space-y-3 p-5"
                              action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              x-on:submit="if (dirty || busy || {{ $reviewReady ? 'false' : 'true' }}) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">
                                Draft harus tersimpan dan seluruh tahap lengkap sebelum ditinjau. Pendaftaran baru
                                dikirim setelah konfirmasi pada halaman tinjau.
                            </p>
                            <p id="review-readiness" class="text-xs text-slate-500">
                                {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Tinjauan baru ditutup.'
                                    : 'Lengkapi seluruh dokumen dan isian, lalu tekan Simpan draft untuk mengaktifkan tinjauan.' }}
                            </p>
                            <button data-review-submit type="submit" aria-describedby="review-readiness"
                                    aria-disabled="{{ $reviewReady ? 'false' : 'true' }}"
                                    :aria-disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }} ? 'true' : 'false'"
                                    class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                    @disabled(!$reviewReady)
                                    :disabled="dirty || busy || {{ $reviewReady ? 'false' : 'true' }}">
                                Tinjau Sebelum Kirim
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
    @include('sidang_ta_registrations.partials.document-preview-modal')
</x-app-layout>
```
