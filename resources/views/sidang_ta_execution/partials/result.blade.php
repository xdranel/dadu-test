@php
    $executionCancelled = in_array($info['scheduling_state'] ?? null, ['cancelled', 'rejected'], true);
    $outcome = $executionCancelled ? 'pending' : ($info['result_status'] ?? 'pending');
@endphp
<span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold {{ match($outcome) { 'passed' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-rose-100 text-rose-800', default => 'bg-slate-100 text-slate-600' } }}">
    @if(($showIcon ?? false) && $outcome === 'passed')
        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
    @elseif(($showIcon ?? false) && $outcome === 'failed')
        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
    @endif
    {{ $executionCancelled ? 'Dibatalkan' : ($info['result_label'] ?? 'Belum ada hasil') }}
</span>
