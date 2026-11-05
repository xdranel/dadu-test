<section data-meet-admission class="campus-card space-y-4 p-5 sm:p-6">
    <header>
        <h2 class="text-lg font-bold text-slate-900">Pemeriksaan persyaratan</h2>
        <p class="mt-1 text-sm leading-relaxed text-slate-600">
            Draft dapat disimpan dan ditinjau. Pengiriman membutuhkan seluruh syarat akademik serta persyaratan EPRT terpenuhi.
        </p>
    </header>
    <dl class="grid gap-3 sm:grid-cols-2">
        @foreach($admission['academic']['gates'] as $key => $gate)
            @php
                $tone = match ($gate['state']) {
                    'passed' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    'failed' => 'border-amber-200 bg-amber-50 text-amber-900',
                    default => 'border-slate-200 bg-slate-100 text-slate-700',
                };
                $label = match ($gate['state']) { 'passed' => 'Memenuhi', 'failed' => 'Belum memenuhi', default => 'Belum tersedia' };
            @endphp
            <div data-admission-gate="{{ $key }}" data-gate-state="{{ $gate['state'] }}" class="rounded-xl border p-3 {{ $tone }}">
                <dt class="text-sm font-semibold">{{ $gate['label'] }}</dt>
                <dd class="mt-1 text-xs">{{ $label }}</dd>
            </div>
        @endforeach
    </dl>
    @if($admission['academic']['blockers'] !== [])
        <ul data-academic-blockers class="list-inside list-disc space-y-1 text-xs leading-relaxed text-amber-900">
            @foreach($admission['academic']['blockers'] as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <div data-eprt-mode="{{ $admission['eprt']['mode'] }}" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <p class="text-sm font-semibold text-slate-800">EPRT</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ $admission['eprt']['message'] }}</p>
    </div>
</section>
