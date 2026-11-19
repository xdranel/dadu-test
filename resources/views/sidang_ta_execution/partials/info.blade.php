<section class="campus-card w-full space-y-4 p-5" data-execution-info>
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-bold text-slate-800">Info Sidang</h2>@include('sidang_ta_execution.partials.result')</div>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 p-4"><h3 class="mb-4 font-bold">Peserta TA</h3><dl class="space-y-3 text-sm">
            @foreach(['period'=>'Periode Sidang TA','student_number'=>'NIM','student_name'=>'Nama Mahasiswa','study_program'=>'Program Studi','registration_status'=>'Status Pengajuan','expertise_group'=>'Kelompok Keahlian','title'=>'Judul TA','advisor_1_code'=>'Kode Dosen Pembimbing 1','advisor_2_code'=>'Kode Dosen Pembimbing 2'] as $key=>$label)
                <div class="grid gap-1 sm:grid-cols-2"><dt class="text-slate-500">{{ $label }}</dt><dd class="break-words font-medium text-slate-800">{{ $key === 'registration_status' ? (\App\Services\SidangTaReviewQuery::STATUSES[$info[$key]] ?? '—') : ($info[$key] ?: '—') }}</dd></div>
            @endforeach
        </dl></section>
        <section class="rounded-xl border border-slate-200 p-4"><h3 class="mb-4 font-bold">Plotting Jadwal</h3><dl class="space-y-3 text-sm">
            @foreach(['weekday'=>'Hari','date'=>'Tanggal','start_time'=>'Jam Mulai (WIB)','end_time'=>'Jam Selesai (WIB)','examiner_1_code'=>'Kode Penguji 1','examiner_2_code'=>'Kode Penguji 2'] as $key=>$label)
                <div class="grid gap-1 sm:grid-cols-2"><dt class="text-slate-500">{{ $label }}</dt><dd class="font-medium text-slate-800">{{ $info[$key] ?: '—' }}</dd></div>
            @endforeach
            <div class="grid gap-1 sm:grid-cols-2"><dt class="text-slate-500">Ruang</dt><dd class="font-medium text-slate-800">{{ $info['is_confirmed'] ? ($info['room'] ?: '—') : 'Belum ditetapkan' }}</dd></div>
        </dl>@unless($info['is_confirmed'])<p class="mt-4 text-xs text-slate-500">Tanggal dan jam plotting merupakan rencana. Ruang ditampilkan setelah jadwal ditetapkan oleh petugas.</p>@endunless</section>
    </div>
    <p class="mt-4 text-xs text-slate-500">Perubahan jadwal ditangani admin/petugas SSC.</p>
</section>
