@php
    [$roomState, $tileColor] = $roomColors[$roomStates[$room->id]];
@endphp
<button type="button" data-room-code="{{ $room->room_code }}"
        data-detail-url="{{ route('room-bookings.calendar.detail', ['date' => $selectedDate, 'room_id' => $room->id]) }}"
        aria-haspopup="dialog" aria-label="{{ $room->room_code }} · {{ $roomState }}"
        title="{{ $room->room_code }} · {{ $roomState }}"
        @click="openRoom($el)"
        @isset($tile)
            style="left: {{ $tile['left'] }}%; top: {{ $tile['top'] }}%; width: {{ $tile['width'] }}%; height: {{ $tile['height'] }}%"
        @endisset
        class="inline-flex items-center justify-center rounded-md border px-1.5 font-semibold hover:brightness-95 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#DA2D3B] {{ $tileColor }} {{ isset($tile) ? 'absolute font-mono text-[11px]' : 'min-h-[40px] px-3 text-xs' }}">
    {{ $tile['label'] ?? $room->room_code }}
</button>
