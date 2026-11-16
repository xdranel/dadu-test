<x-app-layout>
<x-slot name="header"><h2 class="text-xl font-semibold">Pelaksanaan Sidang TA</h2></x-slot>
<div class="mx-auto max-w-5xl space-y-4 p-6">
<p>Tanggal pelaksanaan terpisah dari pendaftaran dan verifikasi berkas. Jam menggunakan WIB. Perubahan harus mempertahankan jadwal yang telah dipesan.</p>
@if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<ul class="rounded bg-red-50 p-3 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@foreach($periods as $period)
@php($s=$period->executionSetting)
<form method="POST" action="{{ route('admin.sidang-ta-execution.update',$period) }}" class="campus-card space-y-4 p-5">
@csrf @method('PUT')
<h3 class="font-semibold">{{ $period->name }}</h3>
<input type="hidden" name="lock_version" value="{{ $s?->lock_version??0 }}">
<div class="grid gap-3 md:grid-cols-2">
<label>Semester sumber jadwal <span class="text-red-600">*</span><select required name="academic_period_id" class="w-full rounded border-slate-300">@foreach($academicPeriods as $ap)<option value="{{ $ap->id }}" @selected(($s?->academic_period_id)===$ap->id)>{{ $ap->label }} · {{ $ap->year }}</option>@endforeach</select></label>
@foreach(['start_date'=>'Tanggal mulai','end_date'=>'Tanggal selesai'] as $key=>$label)<label>{{ $label }} <span class="text-red-600">*</span><input required type="date" name="{{ $key }}" value="{{ $s?->$key?->format('Y-m-d') }}" class="w-full rounded border-slate-300"></label>@endforeach
@foreach(['start_time'=>['Jam mulai','08:00'],'end_time'=>['Jam selesai','16:00'],'break_start_time'=>['Mulai istirahat','12:00'],'break_end_time'=>['Selesai istirahat','13:00']] as $key=>$info)<label>{{ $info[0] }}<input type="time" name="{{ $key }}" value="{{ $s ? substr($s->$key??'',0,5) : $info[1] }}" class="w-full rounded border-slate-300"></label>@endforeach
<label>Durasi (menit) <span class="text-red-600">*</span><input required type="number" min="1" max="900" name="duration_minutes" value="{{ $s?->duration_minutes??120 }}" class="w-full rounded border-slate-300"></label>
</div>
<fieldset><legend>Hari pelaksanaan</legend><div class="flex flex-wrap gap-3">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $n=>$day)<label><input type="checkbox" name="allowed_weekdays[]" value="{{ $n }}" @checked(in_array($n,$s?->allowed_weekdays??[1,2,3,4,5]))> {{ $day }}</label>@endforeach</div></fieldset>
@can('sidang_ta_period_update')<button class="campus-btn campus-btn-primary" type="submit">Simpan pelaksanaan</button>@endcan
</form>
@endforeach</div></x-app-layout>
