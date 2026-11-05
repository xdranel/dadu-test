{{-- Partial untuk input manual mahasiswa (field yang tetap editable) --}}

<div class="space-y-4">
    <div class="p-3 bg-green-50 border border-green-200 rounded-lg flex items-start gap-3">
        <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
        </svg>
        <p class="text-xs font-semibold text-green-800">Isi data di bawah ini untuk keperluan wisuda</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- No. HP Mahasiswa --}}
        <div>
            <label class="campus-label">No. HP Mahasiswa (untuk Wisuda) <span class="text-rose-500">*</span></label>
            <input type="text" name="student_phone" value="{{ old('student_phone', optional($registration)->student_phone ?? '') }}"
                   placeholder="08xxxxxxxxxx" class="campus-input" required>
            @error('student_phone') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- No. HP Orang Tua --}}
        <div>
            <label class="campus-label">No. HP Orang Tua (untuk Wisuda) <span class="text-rose-500">*</span></label>
            <input type="text" name="parent_phone" value="{{ old('parent_phone', optional($registration)->parent_phone ?? '') }}"
                   placeholder="08xxxxxxxxxx" class="campus-input" required>
            @error('parent_phone') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Email Pribadi (Gmail) --}}
        <div>
            <label class="campus-label">Email Pribadi (Gmail untuk Wisuda) <span class="text-rose-500">*</span></label>
            <input type="email" name="personal_email" value="{{ old('personal_email', optional($registration)->personal_email ?? '') }}"
                   placeholder="nama@gmail.com" class="campus-input" required>
            <p class="text-xs text-slate-500 mt-1">Harus menggunakan domain @gmail.com</p>
            @error('personal_email') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Tanggal Selesai Revisi --}}
        <div>
            <label class="campus-label">Batas Revisi <span class="text-rose-500">*</span></label>
            <input type="date" name="revision_deadline"
                   value="{{ old('revision_deadline', optional($registration)->revision_deadline?->format('Y-m-d') ?? '') }}"
                   class="campus-input" required>
            @error('revision_deadline') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="campus-label">Tanggal Selesai Menyelesaikan Revisi <span class="text-rose-500">*</span></label>
            <input type="date" name="revision_completion_date" 
                   value="{{ old('revision_completion_date', optional($registration)->revision_completion_date?->format('Y-m-d') ?? '') }}"
                   class="campus-input" required>
            @error('revision_completion_date') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
