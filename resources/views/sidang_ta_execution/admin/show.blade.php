<x-app-layout><x-slot name="header">Detail Pelaksanaan Sidang TA</x-slot>
<div class="space-y-5">
@include('sidang_ta_execution.partials.notices')
@include('sidang_ta_execution.partials.info')
@php
    $context = $registration->roomSchedule;
    $executionWindow = $registration->period?->executionSetting;
    $minimumDate = max($executionWindow?->start_date?->format('Y-m-d') ?? '', today('Asia/Jakarta')->format('Y-m-d'));
    $maximumDate = $executionWindow?->end_date?->format('Y-m-d');
    $canChooseDate = $executionWindow?->is_active && $maximumDate >= $minimumDate;
@endphp
@can('sidang_ta_registration_review')
@if($registration->status==='approved')
@if(($context?->result_status??'pending')==='pending')
<section class="campus-card space-y-4 p-5"><h2 class="font-bold">{{ $context?->was_confirmed ? 'Cari jadwal pengganti' : 'Tetapkan jadwal sidang' }}</h2><p class="text-sm text-slate-500">Cari ruang kelas dan waktu yang tersedia selama 90 menit pada tanggal pelaksanaan. Semua jam menggunakan WIB.</p>
<p class="text-xs text-slate-500">Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji.</p>

<form method="POST" action="{{ route('admin.sidang-ta-execution.preview',$registration) }}" class="flex flex-wrap items-end gap-3">
@csrf
<label class="campus-label">Tanggal
<input required type="date" name="date" min="{{ $minimumDate }}" max="{{ $maximumDate }}" @disabled(!$canChooseDate) aria-describedby="execution-date-help" class="campus-input mt-1" value="{{ old('date',request('date',$context?->preferred_date?->format('Y-m-d'))) }}">
</label>
<button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" @disabled(!$canChooseDate)>Cari ketersediaan</button>
</form>
<p id="execution-date-help" class="text-xs text-slate-500">
@if($canChooseDate)
Pilih tanggal {{ \Carbon\Carbon::parse($minimumDate)->format('d/m/Y') }}–{{ $executionWindow->end_date->format('d/m/Y') }} sesuai hari pelaksanaan yang diaktifkan.
@else
Pencarian belum tersedia. Atur atau aktifkan rentang Pelaksanaan Sidang TA terlebih dahulu.
@endif
</p>
@if($preview!==null)
@if(count($preview['options']))
<form method="POST" action="{{ route('admin.sidang-ta-execution.confirm',$registration) }}" class="space-y-3">@csrf<label class="campus-label">Pilihan ruang dan waktu<select required name="preview_token" class="campus-select mt-1">@foreach($preview['options'] as $option)@php($candidate=$option['candidate'])<option value="{{ $option['token'] }}" @selected($option['recommended'])>{{ $option['recommended']?'Rekomendasi · ':'' }}{{ $candidate['room_code'] }} · {{ $candidate['room_name'] }} · {{ $candidate['date'] }} · {{ substr($candidate['start_time'],0,5) }}–{{ substr($candidate['end_time'],0,5) }} WIB</option>@endforeach</select></label><p class="text-xs text-slate-500">Periksa ruang dan jam sebelum menetapkan. Ketersediaan diperiksa kembali saat disimpan.@if($context?->was_confirmed) Jadwal ini akan menggantikan jadwal sebelumnya; riwayat tetap tersimpan.@endif</p><button class="btn btn-primary">Tetapkan Jadwal</button></form>
@else<p role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{{ $preview['reason'] ?: 'Belum ada ruang dan waktu yang tersedia pada tanggal ini.' }}</p>@endif
@endif</section>
@endif
@if($context?->was_confirmed && ($context->result_status??'pending')==='pending')
<div class="grid gap-4 lg:grid-cols-2">
<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.reschedule',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $context->lock_version }}"><h2 class="font-bold">Ubah jadwal</h2><label class="campus-label">Tanggal<input required type="date" name="date" class="campus-input mt-1"></label><label class="campus-label">Ruang kelas<select name="room_id" required class="campus-select mt-1">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->room_code }} · {{ $room->name }}</option>@endforeach</select></label><div class="grid grid-cols-2 gap-3"><label class="campus-label">Jam mulai<input required type="time" name="start_time" class="campus-input mt-1"></label><label class="campus-label">Jam selesai<input required type="time" name="end_time" class="campus-input mt-1"></label></div><label class="campus-label">Alasan perubahan<textarea required maxlength="2000" name="reason" class="campus-input mt-1"></textarea></label><button class="btn btn-primary">Simpan perubahan jadwal</button></form>
@if(in_array($context->booking?->status,['pending','approved']))<form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.cancel',$registration) }}">@csrf<input type="hidden" name="context_version" value="{{ $context->lock_version }}"><h2 class="font-bold">Batalkan jadwal</h2><p class="text-sm text-slate-500">Ruangan akan kembali tersedia. Riwayat pelaksanaan tetap tersimpan.</p><label class="campus-label">Alasan pembatalan<textarea required maxlength="2000" name="reason" class="campus-input mt-1"></textarea></label><button class="btn btn-secondary">Batalkan jadwal</button></form>@endif
</div>
@endif
@endif
@if($context?->was_confirmed && (($context->result_status??'pending')!=='pending' || ($registration->status==='approved' && in_array($context->state,['confirmed','completed']) && $context->booking?->status==='approved')))
<form class="campus-card space-y-4 p-5" method="POST" action="{{ route('admin.sidang-ta-execution.outcome',$registration) }}">
@csrf
<input type="hidden" name="context_version" value="{{ $context->lock_version }}">
<h2 class="font-bold">Hasil sidang</h2>
<label class="campus-label">Catatan<textarea name="notes" maxlength="2000" class="campus-input mt-1"></textarea></label>
@if(($context->result_status??'pending')==='pending')
<div class="flex flex-wrap gap-3">
<button type="submit" name="status" value="passed" class="btn btn-primary">Lulus</button>
<button type="submit" name="status" value="failed" class="btn btn-secondary">Gagal</button>
</div>
@else
<p class="text-sm">Keputusan tersimpan: <span class="font-semibold">{{ $context->result_status==='passed' ? 'Lulus' : 'Gagal' }}</span></p>
<button type="submit" name="status" value="pending" class="btn btn-secondary">{{ $context->result_status==='passed' ? 'Batalkan Lulus' : 'Batalkan Gagal' }}</button>
@endif
</form>
@endif
@endcan
@if($context && $context->executionLogs->isNotEmpty())<section class="campus-card space-y-3 p-5"><h2 class="font-bold">Riwayat perubahan pelaksanaan</h2>@foreach($context->executionLogs->sortByDesc('performed_at') as $log)<article class="border-t border-slate-100 pt-3 text-sm"><p>{{ $log->performed_at?->format('d/m/Y H:i') }} WIB · {{ ['plotting_imported'=>'Impor plotting','scheduled'=>'Penetapan jadwal','result_updated'=>'Perubahan hasil','confirmed'=>'Penetapan jadwal','rescheduled'=>'Perubahan jadwal','cancelled'=>'Pembatalan jadwal'][$log->action]??'Pembaruan pelaksanaan' }}</p><p class="text-slate-500">{{ $log->actor?->name ?? 'Petugas' }}@if($log->action==='result_updated') · {{ \App\Services\SidangTaExecutionInfoService::RESULTS[$log->from_status] ?? '—' }} → {{ \App\Services\SidangTaExecutionInfoService::RESULTS[$log->to_status] ?? '—' }}@endif</p><p class="text-slate-500">{{ $log->notes }}</p></article>@endforeach</section>@endif
<a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.index') }}">Kembali</a>
</div></x-app-layout>
