<details data-language-history class="rounded-xl border border-slate-200 p-4">
    <summary class="cursor-pointer text-sm font-bold text-slate-800">Riwayat Tes Bahasa</summary>
    <ul class="mt-3 space-y-3 text-sm">
        @forelse($history as $row)
            <li class="rounded-lg bg-slate-50 p-3">
                <p class="font-semibold">{{ $row['type'] ?: 'Jenis tes belum tersedia' }}: {{ $row['score'] ?? (is_scalar($row['source']['nilai'] ?? null) ? $row['source']['nilai'] : 'Nilai belum tersedia') }}</p>
                <p class="mt-1 text-xs">{{ $row['test_date'] ? 'Tanggal tes '.\Carbon\Carbon::parse($row['test_date'])->format('d/m/Y') : 'Tanggal tes belum tersedia' }}</p>
                <p class="mt-1 text-xs">{{ $row['expires_at'] ? 'Kedaluwarsa '.\Carbon\Carbon::parse($row['expires_at'])->format('d/m/Y') : 'Tanggal kedaluwarsa belum tersedia' }}</p>
                <p class="mt-1 text-xs font-semibold {{ $row['validity']==='valid' ? 'text-emerald-800' : ($row['validity']==='expired' ? 'text-rose-800' : 'text-slate-600') }}">{{ match($row['validity']) {'valid'=>'Berlaku','expired'=>'Tidak berlaku',default=>'Masa berlaku belum terkonfirmasi'} }}</p>
            </li>
        @empty
            <li class="text-slate-500">Riwayat tes belum tersedia.</li>
        @endforelse
    </ul>
</details>
