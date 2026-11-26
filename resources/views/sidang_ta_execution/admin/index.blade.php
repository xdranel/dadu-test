<x-app-layout><x-slot name="header">Review Pelaksanaan Sidang TA</x-slot>
<div class="space-y-5">
@include('sidang_ta_execution.partials.notices')
@can('sidang_ta_period_read')<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Pelaksanaan Sidang TA</h2><p class="text-sm text-slate-500">Atur periode pelaksanaan, hari dan jam pencarian ruang kelas.</p><a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.settings') }}">Kelola Pelaksanaan</a></section>@endcan
@php
    $statusLabels = \App\Services\SidangTaExecutionQuery::STATUSES;
@endphp
<form data-execution-filters method="GET" action="{{ route('admin.sidang-ta-execution.index') }}"
      class="filter-card grid gap-3 md:grid-cols-2 {{ $canReview ? 'xl:grid-cols-4' : 'lg:grid-cols-3' }}">
    <label class="campus-label">Nama atau NIM
        <input name="search" class="campus-input mt-1" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama atau NIM">
    </label>
    <label class="campus-label">Periode
        <select name="period" class="campus-select mt-1">
            <option value="">Semua periode</option>
            @foreach($periods as $period)
                <option value="{{ $period->getRouteKey() }}" @selected(($filters['period'] ?? null) === $period->getRouteKey())>{{ $period->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="campus-label">Status
        <select name="status" class="campus-select mt-1">
            <option value="">Semua status</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @if($canReview)
        <label class="campus-label">Program Studi
            <select name="study_program" class="campus-select mt-1">
                <option value="">Semua prodi</option>
                @foreach($studyPrograms as $program)
                    <option value="{{ $program }}" @selected(($filters['study_program'] ?? null) === $program)>{{ $program }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <div class="col-span-full flex flex-wrap items-center gap-2">
        <button type="submit" class="btn btn-primary">Terapkan</button>
        <a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.index') }}">Reset</a>
        @if($canReview)
            <a class="btn btn-success" href="{{ route('admin.sidang-ta-execution.export', $filters) }}">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
                Export Excel
            </a>
        @endif
    </div>
</form>
<x-review-counts :labels="['total' => 'Total'] + $statusLabels" :counts="$counts" count-attribute="data-execution-count" />

@if($canReview)
    <details class="campus-card p-5">
        <summary class="cursor-pointer font-bold">Impor plotting</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.sidang-ta-execution.import') }}"
              class="mt-4 grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            @csrf
            <label class="campus-label !mb-0">Periode <span class="text-rose-600">*</span>
                <select name="period" required class="campus-select mt-1">
                    <option value="">Pilih periode</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->getRouteKey() }}" @selected(($filters['period'] ?? null) === $period->getRouteKey())>{{ $period->name }}</option>
                    @endforeach
                </select>
            </label>
            <label for="plotting-file" class="campus-label !mb-0 min-w-0">File Excel <span class="text-rose-600">*</span>
                <input id="plotting-file" type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                       required class="campus-input mt-1 block min-w-0 !py-1 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-slate-700">
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Impor plotting</button>
                <a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.template') }}">Unduh Template</a>
            </div>
            <p class="text-xs text-slate-600 sm:col-span-full">Pertahankan dua baris header dan ganti baris contoh. Gunakan tanggal dd/mm/yyyy. Isi kode ruang jika kelas atau LAB sudah ditentukan.</p>
        </form>
    </details>
@endif
@if($result=session('import_result'))<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Hasil impor</h2><p class="text-sm">Diproses: {{ count($result['processed']) }} · Dilewati: {{ count($result['skipped']) }} · Perlu diperiksa: {{ count($result['errors']) }}</p>@foreach(['skipped','errors'] as $type)@foreach($result[$type] as $entry)<p class="text-sm text-slate-600">Baris {{ $entry['row']??'—' }} · NIM {{ $entry['student_number']??$entry['nim']??'—' }} · {{ $entry['message']??$entry['reason']??'' }}</p>@endforeach @endforeach</section>@endif
@php
    $outcomes = app(\App\Services\SidangTaExecutionOutcomeService::class);
    $selectable = $canReview
        ? $registrations->getCollection()->filter(fn ($registration) => $outcomes->canRecord($registration, $registration->roomSchedule))->modelKeys()
        : [];
@endphp
<section class="campus-card overflow-hidden">
    @if($selectable !== [])
        <form id="execution-bulk-outcomes" method="POST" action="{{ route('admin.sidang-ta-execution.bulk-outcome') }}"
              class="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
            @csrf
            <button class="btn btn-success" type="submit" name="status" value="passed">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                Lulus terpilih
            </button>
            <button class="btn bg-rose-600 text-white hover:bg-rose-700" type="submit" name="status" value="failed">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                Gagal terpilih
            </button>
        </form>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    @foreach(['Mahasiswa', 'Program Studi', 'Periode', 'Ruangan', 'Hasil Sidang'] as $label)
                        <th class="p-4" scope="col">{{ $label }}</th>
                    @endforeach
                    @if($selectable !== [])<th class="p-4 text-center" scope="col">Pilih</th>@endif
                    <th class="p-4" scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registrations as $registration)
                    @php
                        $info = $information[$registration->id];
                        $context = $registration->roomSchedule;
                        $room = $context?->booking?->status === 'approved' && in_array($context->state, ['confirmed', 'completed'], true)
                            ? $context->booking->room?->room_code : null;
                    @endphp
                    <tr class="border-t border-slate-100">
                        <td class="p-4"><p class="font-semibold">{{ $registration->student_name }}</p><p class="text-slate-500">{{ $registration->student_number }}</p></td>
                        <td class="p-4">{{ $registration->study_program }}</td>
                        <td class="p-4">{{ $registration->period?->name }}</td>
                        <td class="p-4">{{ $room ?? '—' }}</td>
                        <td class="p-4">@include('sidang_ta_execution.partials.result', ['showIcon' => true])</td>
                        @if($selectable !== [])
                            <td class="p-4 text-center">
                                @if(in_array($registration->id, $selectable, true))
                                    <input type="checkbox" form="execution-bulk-outcomes" name="participants[{{ $registration->getRouteKey() }}]"
                                           value="{{ $context->lock_version }}" aria-label="Pilih {{ $registration->student_name }}"
                                           class="h-4 w-4 cursor-pointer accent-[#DA2D3B]">
                                @endif
                            </td>
                        @endif
                        <td class="p-4"><a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.show', $registration) }}">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td class="p-5 text-slate-500" colspan="6">Tidak ada peserta sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $registrations->links() }}</div>
</section>
</div></x-app-layout>
