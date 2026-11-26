@php
    $editingPlotting = old('editing_plotting') === '1';
    $assigned = $booking?->status === 'approved' && $currentOccurrence !== null;
    $plottingValue = fn (string $field, mixed $fallback) => $editingPlotting ? old($field) : $fallback;
    $selectedRoomId = $plottingValue('room_id', $assigned ? $booking->room_id : $context?->preferred_room_id);
@endphp

<x-modal name="edit-ta-plotting" :show="$editingPlotting" focusable>
    <form method="POST" action="{{ route('admin.sidang-ta-execution.plotting', $registration) }}"
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

        <label class="campus-label">Tanggal <span class="text-rose-600">*</span>
            <input type="date" name="date" required min="{{ $minimumDate }}" max="{{ $maximumDate }}" class="campus-input mt-1"
                   value="{{ $plottingValue('date', $assigned ? $currentOccurrence->usage_date->toDateString() : $context?->preferred_date?->toDateString()) }}">
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="campus-label">Jam mulai <span class="text-rose-600">*</span>
                <input type="time" name="start_time" required min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1"
                       value="{{ $plottingValue('start_time', substr(($assigned ? $currentOccurrence->start_time : $context?->preferred_start_time) ?? '', 0, 5)) }}">
            </label>
            <label class="campus-label">Jam selesai <span class="text-rose-600">*</span>
                <input type="time" name="end_time" required min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1"
                       value="{{ $plottingValue('end_time', substr(($assigned ? $currentOccurrence->end_time : $context?->preferred_end_time) ?? '', 0, 5)) }}">
            </label>
            <label class="campus-label">Kode penguji 1
                <input name="examiner_1_code" type="text" maxlength="255" class="campus-input mt-1" value="{{ $plottingValue('examiner_1_code', $info['examiner_1_code']) }}">
            </label>
            <label class="campus-label">Kode penguji 2
                <input name="examiner_2_code" type="text" maxlength="255" class="campus-input mt-1" value="{{ $plottingValue('examiner_2_code', $info['examiner_2_code']) }}">
            </label>
        </div>
        <label class="campus-label">Ruang @if($assigned)<span class="text-rose-600">*</span>@endif
            <select name="room_id" @required($assigned) class="campus-select mt-1">
                <option value="">{{ $assigned ? 'Pilih ruang' : 'Belum dipilih' }}</option>
                @foreach($editRooms as $room)
                    <option value="{{ $room->id }}" @selected((string) $selectedRoomId === (string) $room->id)>
                        {{ $room->room_code }} · {{ $room->building ?: 'Lokasi belum diisi' }} · kapasitas {{ $room->capacity ?: '—' }}
                    </option>
                @endforeach
            </select>
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
            <button type="submit" class="btn btn-primary">Simpan perubahan</button>
        </div>
    </form>
</x-modal>
