<dl class="grid gap-3 text-sm sm:grid-cols-2">
<div><dt class="text-slate-500">ID Pengajuan TA</dt><dd class="font-semibold">#{{ $identity['registration_id'] }}</dd></div>
<div><dt class="text-slate-500">NIM / Nama</dt><dd class="font-semibold">{{ $identity['student_number'] }} · {{ $identity['student_name'] }}</dd></div>
@foreach(['advisor_1'=>'Pembimbing 1','advisor_2'=>'Pembimbing 2','examiner_1'=>'Penguji 1','examiner_2'=>'Penguji 2'] as $key=>$label)
<div><dt class="text-slate-500">{{ $label }}</dt><dd>{{ $identity[$key]['code'] }} · {{ $identity[$key]['name'] }}</dd></div>
@endforeach
</dl>
