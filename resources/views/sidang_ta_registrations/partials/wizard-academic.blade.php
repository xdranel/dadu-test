@php
    $display = static fn ($value) => is_scalar($value) && (string) $value !== '' ? (string) $value : 'Belum tersedia';
@endphp
<aside data-academic-panel class="min-w-0 space-y-4 xl:col-span-4" aria-label="Informasi akademik mahasiswa">
    @unless($hideIdentity ?? false)
        <section class="campus-card space-y-4 p-5">
            <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Data mahasiswa</h2>
            <p class="break-words text-base font-semibold text-slate-900">{{ $display($academic['student_name']) }}</p>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                @foreach(['NIM'=>'student_number','Program studi'=>'study_program','Semester aktif'=>'semester','Email kampus'=>'student_email'] as $label=>$key)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-slate-800">{{ $display($academic[$key] ?? null) }}</dd></div>
                @endforeach
            </dl>
            <p class="text-xs text-slate-500">Sumber data diperbarui: {{ $display($academic['academic_source_updated_at']) }}</p>
        </section>
    @endunless
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Detail Tugas Akhir</h2>
        <dl class="space-y-3">
            @foreach(['Judul Bahasa Indonesia'=>'ta_title','Judul Bahasa Inggris'=>'ta_title_en','Pembimbing 1'=>'advisor_1','Pembimbing 2'=>'advisor_2'] as $label=>$key)
                <div><dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words text-sm text-slate-800">{{ $display($academic[$key] ?? null) }}</dd></div>
            @endforeach
            <div><dt class="text-xs text-slate-500">IPK</dt><dd class="text-lg font-bold">{{ is_numeric($academic['gpa']) ? number_format((float) $academic['gpa'],2,',','.') : $display($academic['gpa']) }}</dd></div>
        </dl>
    </section>
    @include('sidang_ta_registrations.partials.meet-admission-summary', ['admission'=>$meetContext['admission']])
</aside>
