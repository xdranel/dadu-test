<div data-student-language class="campus-card space-y-5 p-6">
    <h3 class="text-base font-semibold text-slate-800">Riwayat Tes EPRT</h3>
    <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
        <p class="text-sm text-slate-600">Nilai Bahasa / EPRT {{ $detail['language']['type'] ? '('.$detail['language']['type'].')' : '' }}</p>
        <p data-language-score class="mt-1 text-2xl font-bold text-violet-700">{{ $detail['language']['score'] ?? 'Belum tersedia' }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ $detail['language']['confirmed_attempts'] === null ? 'Jumlah tes belum terkonfirmasi' : $detail['language']['confirmed_attempts'].' tes tercatat' }}</p>
    </div>
    @include('sidang_ta_registrations.partials.language-history', ['history' => $detail['language']['history']])
</div>
