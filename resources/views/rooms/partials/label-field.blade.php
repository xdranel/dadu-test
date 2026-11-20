<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-1" for="label">Label</label>
    <select id="label" name="label" aria-describedby="room-label-help"
            class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-[#DA2D3B] focus:border-[#DA2D3B] @error('label') border-red-500 @enderror">
        <option value="">- Belum ditentukan -</option>
        @foreach (\App\Models\Room::LABELS as $label)
            <option value="{{ $label }}" @selected(old('label', $room->label ?? null) === $label)>{{ $label }}</option>
        @endforeach
    </select>
    <p id="room-label-help" class="mt-1 text-xs text-gray-500">LABEL DITULIS DENGAN HURUF BESAR. KOSONGKAN JIKA BELUM DITENTUKAN.</p>
    @error('label')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
