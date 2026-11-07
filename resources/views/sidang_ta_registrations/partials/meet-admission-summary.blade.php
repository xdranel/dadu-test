@php
    $values = ['level_2_passed'=>$academic['sidang_tingkat_2'], 'ta2_registered'=>$academic['ta2_krs_label'],
        'skpi_approved'=>$academic['skpi_status'], 'gpa'=>is_numeric($academic['gpa'] ?? null) ? number_format((float)$academic['gpa'],2,',','.') : 'Belum tersedia', 'tak_score'=>$academic['tak_score'], 'passed_credits'=>$academic['passed_credits']];
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
        @php
            $approvalState = match ($academic['sidang_approval_approved'] ?? null) { true => 'passed', false => 'failed', default => 'unknown' };
            $bppState = !$academic['bpp_period'] ? 'unknown' : match (strtoupper(trim($academic['bpp_status']))) { 'LUNAS', 'PAID' => 'passed', 'KREDIT', 'UNPAID', 'BELUM LUNAS', 'PERLU VERIFIKASI' => 'failed', default => 'unknown' };
        @endphp
        <div data-information-card="sidang_approval" data-state="{{ $approvalState }}" class="rounded-xl border p-3 {{ $tones[$approvalState] }}">
            <dt class="text-sm font-semibold">Persetujuan sidang iGracias</dt>
            <dd class="mt-1 text-base font-bold">{{ $academic['sidang_approval_status'] }}</dd>
        </div>
        <div data-information-card="bpp" data-state="{{ $bppState }}" class="rounded-xl border p-3 {{ $tones[$bppState] }}">
            <dt class="text-sm font-semibold">Status BPP</dt>
            <dd data-academic-field="bpp_status" class="mt-1 text-base font-bold">{{ $academic['bpp_status'] }}</dd>
            <dd class="mt-1 text-xs">{{ $academic['bpp_period'] ? 'Periode pembayaran '.$academic['bpp_period'].'. Status ini hanya berlaku pada periode tersebut.' : 'Periode pembayaran belum tersedia pada data sinkronisasi.' }}</dd>
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
</section>
