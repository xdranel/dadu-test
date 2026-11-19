<x-app-layout><x-slot name="header">Pelaksanaan Sidang TA</x-slot>
<div class="space-y-5">
@include('sidang_ta_execution.partials.notices')
@can('sidang_ta_period_read')<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Pelaksanaan Sidang TA</h2><p class="text-sm text-slate-500">Atur periode pelaksanaan, hari dan jam pencarian ruang kelas.</p><a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.settings') }}">Kelola Pelaksanaan</a></section>@endcan
@if($scopedProgram)<p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Akses baca Program Studi {{ $scopedProgram }}.</p>@endif
<section class="space-y-3"><h2 class="text-sm font-bold">Ringkasan hasil filter</h2><div class="grid grid-cols-2 gap-3 lg:grid-cols-6">@foreach(['total'=>'Total']+\App\Services\SidangTaExecutionQuery::STATUSES as $key=>$label)<article class="campus-card p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold {{ $key==='total' ? 'text-[#DA2D3B]' : 'text-slate-800' }}" data-execution-count="{{ $key }}">{{ $counts[$key] }}</p></article>@endforeach</div></section>
<form method="GET" class="campus-card grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
<label class="campus-label">Nama atau NIM<input name="search" class="campus-input mt-1" value="{{ $filters['search']??'' }}" placeholder="Cari nama atau NIM"></label>
<label class="campus-label">Periode<select name="period" class="campus-select mt-1"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->getRouteKey() }}" @selected(($filters['period']??null)===$period->getRouteKey())>{{ $period->name }}</option>@endforeach</select></label>
@if($canReview)<label class="campus-label">Program Studi<select name="study_program" class="campus-select mt-1"><option value="">Semua prodi</option>@foreach($studyPrograms as $program)<option @selected(($filters['study_program']??null)===$program)>{{ $program }}</option>@endforeach</select></label>@endif
<label class="campus-label">Status<select name="status" class="campus-select mt-1"><option value="">Semua status</option>@foreach(\App\Services\SidangTaExecutionQuery::STATUSES as $key=>$label)<option value="{{ $key }}" @selected(($filters['status']??null)===$key)>{{ $label }}</option>@endforeach</select></label>
<div class="flex flex-wrap items-center gap-2 md:col-span-2 xl:col-span-4">
<button class="btn btn-primary">Terapkan</button>
<a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.index') }}">Reset</a>
@if($canReview)
<a class="btn btn-success" href="{{ route('admin.sidang-ta-execution.export',$filters) }}">
<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.7" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 12l5 6m0-6l-5 6"/></svg>
Export Excel
</a>
@endif
</div>
</form>
@if($canReview)
<details class="campus-card p-5"><summary class="cursor-pointer font-bold">Impor plotting</summary>
<p class="my-3 text-sm text-slate-500">Pilih periode dan file Excel plotting peserta. Tanggal, jam dan ruang menjadi pilihan awal; periksa ketersediaan lalu tekan Tetapkan Jadwal untuk memesan ruang. Penjadwalan dari sistem juga dapat dilakukan tanpa impor.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.sidang-ta-execution.import') }}" class="grid gap-3 md:grid-cols-3">@csrf
<label class="campus-label">Periode<select name="period" required class="campus-select mt-1"><option value="">Pilih periode</option>@foreach($periods as $period)<option value="{{ $period->getRouteKey() }}" @selected(($filters['period']??null)===$period->getRouteKey())>{{ $period->name }}</option>@endforeach</select></label>
<div class="space-y-2 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/50 p-4">
<label for="plotting-file" class="campus-label flex items-center gap-2">
<svg aria-hidden="true" class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4"/></svg>
File Excel <span class="text-rose-600">*</span>
</label>
<input id="plotting-file" type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required aria-describedby="plotting-file-help" class="block w-full rounded-lg border border-slate-200 bg-white p-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600">
<p id="plotting-file-help" class="text-xs text-slate-500">Format .xlsx, maksimal 10 MB. Gunakan template plotting dengan dua baris header dan 16 kolom.</p>
</div>
<div class="flex items-end"><button class="btn btn-primary">Impor plotting</button></div></form></details>
@endif
@if($result=session('import_result'))<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Hasil impor</h2><p class="text-sm">Diproses: {{ count($result['processed']) }} · Dilewati: {{ count($result['skipped']) }} · Perlu diperiksa: {{ count($result['errors']) }}</p>@foreach(['skipped','errors'] as $type)@foreach($result[$type] as $entry)<p class="text-sm text-slate-600">Baris {{ $entry['row']??'—' }} · NIM {{ $entry['student_number']??$entry['nim']??'—' }} · {{ $entry['message']??$entry['reason']??'' }}</p>@endforeach @endforeach</section>@endif
<section class="campus-card overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr>@foreach(['Mahasiswa','Program Studi','Periode','Status','Hasil Sidang','Tinjau'] as $label)<th class="p-4">{{ $label }}</th>@endforeach</tr></thead><tbody>
@forelse($registrations as $registration)@php($info=$information[$registration->id])
<tr class="border-t border-slate-100"><td class="p-4"><p class="font-semibold">{{ $registration->student_name }}</p><p class="text-slate-500">{{ $registration->student_number }}</p></td><td class="p-4">{{ $registration->study_program }}</td><td class="p-4">{{ $registration->period?->name }}</td><td class="p-4">{{ \App\Services\SidangTaExecutionQuery::STATUSES[match(true){in_array($registration->roomSchedule?->state,['cancelled','rejected'])=>'cancelled',in_array($info['result_status'],['passed','failed'])=>$info['result_status'],in_array($registration->roomSchedule?->state,['confirmed','completed'])=>'scheduled',default=>'unassigned'}] }}</td><td class="p-4">@include('sidang_ta_execution.partials.result')</td><td class="p-4"><a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.show',$registration) }}">Tinjau</a></td></tr>
@empty<tr><td class="p-5 text-slate-500" colspan="6">Tidak ada peserta sesuai filter.</td></tr>@endforelse
</tbody></table></div><div class="p-4">{{ $registrations->links() }}</div></section>
</div></x-app-layout>
