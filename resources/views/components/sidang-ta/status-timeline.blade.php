@props(['events', 'emptyMessage' => 'Belum ada riwayat status.'])

<ol {{ $attributes->class('ml-3 border-l border-slate-200') }}>
    @forelse($events as $event)
        @php
            $action = $event['action'];
            $negative = in_array($action, ['rejected', 'document_rejected', 'cancelled', 'failed'], true);
            $changed = in_array($action, ['approval_cancelled', 'reopened', 'rescheduled', 'schedule_updated', 'result_updated'], true);
            $color = $negative ? 'bg-rose-100 text-rose-800' : (($changed || $action === 'needs_review') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');
        @endphp
        <li class="relative pb-7 pl-7 last:pb-0">
            <span aria-hidden="true" class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full {{ $color }}">{{ $negative ? '×' : ($changed ? '↺' : '✓') }}</span>
            <p class="text-sm font-semibold text-slate-800">
                {{ $event['label'] }} <span class="font-normal text-slate-500">— {{ $event['time'] }}</span>
            </p>
            @if(filled($event['actor'] ?? null))
                <p class="mt-1 text-xs text-slate-500">{{ $event['actor'] }}</p>
            @endif
            @if(filled($event['notes'] ?? null))
                <p class="mt-2 whitespace-pre-line break-words text-sm text-slate-700">{{ $event['notes'] }}</p>
            @endif
        </li>
    @empty
        <li class="pl-7 text-sm text-slate-500">{{ $emptyMessage }}</li>
    @endforelse
</ol>
