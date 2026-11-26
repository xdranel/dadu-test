@php
    $editingPlotting = old('editing_plotting') === '1';
    $assigned = $booking?->status === 'approved' && $currentOccurrence !== null;
    $plottingValue = fn (string $field, mixed $fallback) => $editingPlotting ? (is_scalar(old($field)) ? old($field) : null) : $fallback;
    $taType = $plottingValue('ta_type', $info['ta_type'] ?? 'Individu');
    $selectedRoomId = $plottingValue('room_id', $assigned ? $booking->room_id : $context?->preferred_room_id);
    $assignedSlot = $assigned ? [
        'date' => $currentOccurrence->usage_date->toDateString(),
        'start_time' => substr($currentOccurrence->start_time, 0, 5),
        'end_time' => substr($currentOccurrence->end_time, 0, 5),
    ] : null;
@endphp

<x-modal name="edit-ta-plotting" :show="$editingPlotting" focusable>
    <form method="POST" action="{{ route('admin.sidang-ta-execution.plotting', $registration) }}"
          x-data="sidangTaPlottingRooms(@js(route('admin.sidang-ta-execution.plotting-rooms', $registration)), @js($selectedRoomId), @js($assignedSlot))"
          x-on:open-modal.window="if ($event.detail === 'edit-ta-plotting') loadRooms()"
          x-init="if (@js($editingPlotting)) loadRooms()"
          x-on:submit="if (loading) $event.preventDefault()"
          aria-labelledby="edit-ta-plotting-title" class="space-y-4 p-5 sm:p-6">
        @csrf
        <input type="hidden" name="editing_plotting" value="1">
        <input type="hidden" name="context_version" value="{{ $context?->lock_version ?? 0 }}">

        <h2 id="edit-ta-plotting-title" class="text-lg font-bold text-slate-800">Edit Data Plotting Jadwal</h2>
        <p class="text-sm text-slate-600">Hari mengikuti tanggal yang dipilih. Semua jam menggunakan WIB.</p>
        @if($editingPlotting && $errors->any())
            <ul role="alert" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <label class="campus-label">Tipe TA <span class="text-rose-600">*</span>
            <select name="ta_type" required class="campus-select mt-1" x-on:change="loadRooms()">
                <option value="Individu" @selected($taType !== 'Kelompok')>Individu</option>
                <option value="Kelompok" @selected($taType === 'Kelompok')>Kelompok</option>
            </select>
        </label>
        <label class="campus-label">Tanggal <span class="text-rose-600">*</span>
            <input type="date" name="date" required min="{{ $minimumDate }}" max="{{ $maximumDate }}" class="campus-input mt-1" x-on:change="loadRooms()"
                   value="{{ $plottingValue('date', $assigned ? $currentOccurrence->usage_date->toDateString() : $context?->preferred_date?->toDateString()) }}">
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="campus-label">Jam mulai <span class="text-rose-600">*</span>
                <input type="time" name="start_time" required min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1" x-on:change="loadRooms()"
                       value="{{ $plottingValue('start_time', substr(($assigned ? $currentOccurrence->start_time : $context?->preferred_start_time) ?? '', 0, 5)) }}">
            </label>
            <label class="campus-label">Jam selesai <span class="text-rose-600">*</span>
                <input type="time" name="end_time" required min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1" x-on:change="loadRooms()"
                       value="{{ $plottingValue('end_time', substr(($assigned ? $currentOccurrence->end_time : $context?->preferred_end_time) ?? '', 0, 5)) }}">
            </label>
            @foreach([1, 2] as $number)
                @php
                    $field = 'examiner_'.$number.'_code';
                    $selectedCode = (string) $plottingValue($field, $info[$field]);
                @endphp
                <label class="campus-label">Kode penguji {{ $number }}
                    <select name="{{ $field }}" class="campus-select mt-1">
                        <option value="">Belum dipilih</option>
                        @if($selectedCode !== '' && !$examiners->has($selectedCode))
                            <option value="{{ $selectedCode }}" selected disabled>{{ $selectedCode }} · Belum terdaftar</option>
                        @endif
                        @foreach($examiners as $code => $name)
                            <option value="{{ $code }}" @selected((string) $code === $selectedCode)>{{ $code }} · {{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
        <label class="campus-label">Ruang @if($assigned)<span class="text-rose-600">*</span>@endif
            <select name="room_id" aria-describedby="plotting-room-availability" x-model="roomId" x-bind:disabled="loading" x-bind:required="@js($assigned) && !keepAssignedRoom" class="campus-select mt-1">
                <option value="" x-text="keepAssignedRoom ? 'Pertahankan ruang yang sudah ditetapkan' : '{{ $assigned ? 'Pilih ruang' : 'Belum dipilih' }}'">{{ $assigned ? 'Pilih ruang' : 'Belum dipilih' }}</option>
                <template x-for="room in rooms" :key="room.id">
                    <option x-bind:value="room.id" x-text="`${room.room_code} · ${room.building || 'Lokasi belum diisi'} · kapasitas ${room.capacity || '—'}`"></option>
                </template>
            </select>
            <span id="plotting-room-availability" class="mt-1 block text-xs text-slate-600" role="status" x-text="message"></span>
        </label>
        @if($assigned)
            <label class="campus-label">Alasan perubahan tanggal, jam, atau ruang
                <textarea name="reason" maxlength="2000" rows="2" class="campus-input mt-1">{{ $plottingValue('reason', '') }}</textarea>
            </label>
        @else
            <p class="text-sm text-slate-600">Ruang dipesan setelah jadwal ditetapkan.</p>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="btn btn-secondary" x-on:click="$dispatch('close-modal', 'edit-ta-plotting')">Batal</button>
            <button type="submit" class="btn btn-primary" x-bind:disabled="loading">Simpan perubahan</button>
        </div>
    </form>
</x-modal>
