<div data-called-ticket-id="{{ $ticket->id }}" id="called-ticket-{{ $ticket->id }}" class="p-3 bg-red-50/10 border border-red-100/30 rounded-xl space-y-1">
    <div class="flex items-center justify-between">
        <span class="font-bold text-red-600 font-mono text-sm">{{ $ticket->code }}</span>
        <div class="flex items-center gap-1.5">
            @if($ticket->counter)
                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-red-100/70 text-red-800">{{ $ticket->counter->name }}</span>
            @endif
            <span class="text-[9px] text-slate-400 font-medium">Ambil: {{ $ticket->created_at ? $ticket->created_at->timezone('Asia/Jakarta')->format('H:i') : '-' }}</span>
        </div>
    </div>

    @if($ticket->name)
        <div class="flex items-center justify-between text-xs pt-1 border-t border-red-100/40">
            <span class="font-semibold text-slate-800 truncate max-w-[150px]">{{ $ticket->name }}</span>
            <span class="text-[10px] font-mono text-slate-500">{{ $ticket->nim }}</span>
        </div>
        <div class="flex items-center justify-between text-[11px] text-slate-600">
            <span class="truncate max-w-[140px] text-slate-500">{{ $ticket->study_program }}</span>
            <span class="font-semibold text-[#DA2D3B]">{{ $ticket->service_category }}</span>
        </div>
    @endif

    @if($ticket->calledByUser)
        <div class="text-[10px] text-slate-500 pt-0.5">
            Petugas: <span class="font-medium text-slate-700">{{ $ticket->calledByUser->name }}</span>
        </div>
    @endif

    @if($ticket->notes)
        <div class="text-[10px] text-slate-500 italic bg-white p-1.5 rounded border border-slate-100 break-words">
            {{ $ticket->notes }}
        </div>
    @endif

    <!-- Catatan Petugas (Dropdown dengan Auto-Save + Simpan Manual) -->
    @include('queue-tickets.partials.staff-note-dropdown', ['type' => 'called', 'ticket' => $ticket])

    @if(isset($selectedCounter) && $selectedCounter && (int) $ticket->counter_id === (int) $selectedCounter->id)
        <div class="pt-1.5 flex justify-end">
            <button type="button"
                    onclick="confirmSkip('{{ $ticket->code }}', {{ $ticket->id }})"
                    class="btn-skip-card inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 rounded-lg transition-all active:scale-[0.97]">
                Lewati
            </button>
        </div>
    @endif
</div>
