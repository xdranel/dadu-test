@php
    [$roomState, $tileColor] = $roomColors[$roomStates[$room->id]];
@endphp
<button type="button" data-room-code="{{ $room->room_code }}"
        data-detail-url="{{ route('room-bookings.calendar.detail', ['date' => $selectedDate, 'room_id' => $room->id]) }}"
        aria-haspopup="dialog" aria-label="{{ $room->room_code }} · {{ $roomState }}"
        title="{{ $room->room_code }} · {{ $roomState }}"
        @click="openRoom($el)"
        style="left: {{ $tile['left'] }}%; top: {{ $tile['top'] }}%; width: {{ $tile['width'] }}%; height: {{ $tile['height'] }}%"
        class="absolute inline-flex items-center justify-center rounded-md border px-1.5 font-mono text-[11px] font-semibold hover:brightness-95 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#DA2D3B] {{ $tileColor }}">
    {{ $tile['label'] }}
</button>
