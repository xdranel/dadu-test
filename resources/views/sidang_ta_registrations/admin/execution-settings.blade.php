<x-app-layout><x-slot name="header">Kelola Pelaksanaan Sidang TA</x-slot>
<div class="mx-auto max-w-5xl space-y-5">
<header><h2 class="text-xl font-bold text-slate-800">Jadwal Pelaksanaan Sidang TA</h2><p class="mt-1 text-sm text-slate-500">Atur rentang dan jam pelaksanaan setiap periode. Durasi sidang 90 menit; ruang kosong saat istirahat tetap dapat digunakan. Semua jam menggunakan WIB.</p></header>
@include('sidang_ta_execution.partials.notices')
@foreach($periods as $period)
@php($s=$period->executionSetting)
@php($historical=$s && (!$s->is_active || $s->end_date->lt(today())))
<details class="campus-card group p-5" @if(!$historical) open @endif><summary class="flex cursor-pointer items-center justify-between font-bold text-slate-800"><span>{{ $period->name }} @if($historical)<span class="ml-2 text-xs font-normal text-slate-500">Riwayat pelaksanaan</span>@endif</span><span aria-hidden="true">⌄</span></summary>
<form method="POST" action="{{ route('admin.sidang-ta-execution.update',$period) }}" class="mt-5 space-y-4">@csrf @method('PUT')
<input type="hidden" name="lock_version" value="{{ $s?->lock_version??0 }}"><input type="hidden" name="break_start_time" value=""><input type="hidden" name="break_end_time" value=""><input type="hidden" name="is_active" value="0">
<fieldset class="space-y-4" @cannot('sidang_ta_period_update') disabled @endcannot>
<label class="campus-label block">Semester sumber jadwal <span class="text-rose-600">*</span><select required name="academic_period_id" class="campus-select mt-1"><option value="">Pilih semester</option>@foreach($academicPeriods as $ap)<option value="{{ $ap->id }}" @selected($s?->academic_period_id===$ap->id)>{{ $ap->label }} · {{ $ap->year }}</option>@endforeach</select></label>
<div class="grid gap-4 md:grid-cols-2"><label class="campus-label">Tanggal mulai <span class="text-rose-600">*</span><input required type="date" name="start_date" value="{{ $s?->start_date?->format('Y-m-d') }}" class="campus-input mt-1"></label><label class="campus-label">Jam mulai <span class="text-rose-600">*</span><input required type="time" name="start_time" value="{{ $s ? substr($s->start_time,0,5) : '08:00' }}" class="campus-input mt-1"></label>
<label class="campus-label">Tanggal selesai <span class="text-rose-600">*</span><input required type="date" name="end_date" value="{{ $s?->end_date?->format('Y-m-d') }}" class="campus-input mt-1"></label><label class="campus-label">Jam selesai <span class="text-rose-600">*</span><input required type="time" name="end_time" value="{{ $s ? substr($s->end_time,0,5) : '16:00' }}" class="campus-input mt-1"></label></div>
<label class="campus-label block">Durasi sidang (menit)<input type="number" name="duration_minutes" value="90" readonly class="campus-input mt-1 bg-slate-50"></label>
<fieldset><legend class="campus-label mb-2">Hari pelaksanaan</legend><div class="flex flex-wrap gap-4">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $n=>$day)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allowed_weekdays[]" value="{{ $n }}" @checked(in_array($n,$s?->allowed_weekdays??[1,2,3,4,5]))> {{ $day }}</label>@endforeach</div></fieldset>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($s?->is_active??true)> Aktifkan pencarian jadwal periode ini</label>
@can('sidang_ta_period_update')<button class="btn btn-primary" type="submit">Simpan pelaksanaan</button>@endcan
</fieldset></form></details>
@endforeach
<a class="btn btn-secondary" href="{{ route(auth()->user()->can('sidang_ta_registration_review') || auth()->user()->can('sidang_ta_registration_view_program') ? 'admin.sidang-ta-execution.index' : 'admin.sidang-ta-registrations.index') }}">Kembali</a>
</div></x-app-layout>
