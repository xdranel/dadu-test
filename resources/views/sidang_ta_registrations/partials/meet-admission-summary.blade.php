@php
    $values = ['level_2_passed'=>$academic['sidang_tingkat_2'], 'ta2_registered'=>$academic['ta2_krs_label'],
        'skpi_approved'=>$academic['skpi_status'], 'tak_score'=>$academic['tak_score'], 'passed_credits'=>$academic['passed_credits']];
    $otherBpp = collect($academic['bpp_periods'] ?? [])->except([$academic['bpp_period']])->all();
    $mode=$admission['eprt']['mode'];
    $waiverReady=(bool) ($meetContext['waiver_ready'] ?? false);
    $eprtState=$mode==='automatic' || ($mode==='waiver' && $waiverReady) ? 'passed' : ($mode==='unknown' ? 'unknown' : 'failed');
    $tones=['passed'=>'border-emerald-200 bg-emerald-50 text-emerald-900','failed'=>'border-rose-200 bg-rose-50 text-rose-900','unknown'=>'border-slate-200 bg-slate-100 text-slate-700'];
@endphp
<section data-meet-admission class="campus-card space-y-4 p-5">
    <h2 class="text-base font-bold text-slate-900">Pemeriksaan persyaratan</h2>
    <dl class="grid gap-3 sm:grid-cols-2">
        @foreach($admission['academic']['gates'] as $key=>$gate)
            <div data-admission-gate="{{ $key }}" data-gate-state="{{ $gate['state'] }}" class="rounded-xl border p-3 {{ $tones[$gate['state']] }}">
                <dt class="text-sm font-semibold">{{ $gate['label'] }}</dt>
                <dd class="mt-1 text-base font-bold">{{ $values[$key] ?? 'Belum tersedia' }}</dd>
                <dd class="mt-1 text-xs">{{ match($gate['state']) {'passed'=>'Memenuhi','failed'=>'Belum memenuhi',default=>'Belum tersedia / belum terkonfirmasi'} }}</dd>
            </div>
        @endforeach
        <div data-eprt-mode="{{ $mode }}" data-gate-state="{{ $eprtState }}" class="rounded-xl border p-3 {{ $tones[$eprtState] }}">
            <dt class="text-sm font-semibold">Nilai Bahasa / EPRT {{ ($admission['eprt_facts']['type'] ?? null) ? '('.$admission['eprt_facts']['type'].')' : '' }}</dt>
            <dd class="mt-1 text-base font-bold">{{ $admission['eprt_facts']['score'] ?? 'Belum tersedia' }}</dd>
            <dd class="mt-1 text-xs">{{ $waiverReady ? 'Surat permakluman terlampir' : $admission['eprt']['message'] }}</dd>
            <dd class="mt-1 text-xs">{{ $admission['eprt_facts']['confirmed_attempts'] === null ? 'Jumlah tes belum terkonfirmasi' : $admission['eprt_facts']['confirmed_attempts'].' tes tercatat' }}</dd>
        </div>
    </dl>
    <dl class="space-y-3 text-sm">
        <div><dt class="text-slate-500">Persetujuan sidang iGracias</dt><dd class="font-medium">{{ $academic['sidang_approval_status'] }}</dd></div>
        <div><dt class="text-slate-500">Status BPP</dt><dd data-academic-field="bpp_status" class="font-medium">{{ $academic['bpp_status'] }}</dd>
            <dd class="mt-1 text-xs text-slate-500">{{ $academic['bpp_period'] ? 'Periode pembayaran '.$academic['bpp_period'].'. Status ini hanya berlaku pada periode tersebut.' : 'Periode pembayaran belum terkonfirmasi.' }}</dd>
        </div>
    </dl>
    @if($otherBpp !== [])
        <details class="text-xs text-slate-600"><summary class="cursor-pointer font-semibold">Pembayaran periode lain</summary>
            <ul class="mt-2 space-y-1">
                @foreach($otherBpp as $period=>$payment)
                    @if((string) $period !== (string) $academic['bpp_period'])
                        <li>{{ $period }}: {{ is_scalar($payment['status'] ?? null) ? $payment['status'] : 'Belum tersedia' }} (hanya untuk periode ini)</li>
                    @endif
                @endforeach
            </ul>
        </details>
    @endif
    @if($admission['academic']['blockers'] !== [])
        <ul data-academic-blockers class="list-inside list-disc space-y-1 text-xs text-rose-800">
            @foreach($admission['academic']['blockers'] as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif
</section>
