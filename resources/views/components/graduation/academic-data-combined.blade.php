{{-- Gabungan Data Akademik (auto-fill editable) --}}
@props(['campusData' => [], 'registration' => null])

<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Nilai TA Terbaru --}}
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="campus-label !mb-0">Nilai TA Terbaru</label>
                <button type="button"
                        @click="$dispatch('open-info-modal', { key: 'tak_score' })"
                        class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                        title="Lihat Ketentuan & Minimal Score" aria-label="Lihat Ketentuan & Minimal Score">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
            </div>
            <input type="number" step="1" min="0" max="100" name="tak_score" value="{{ old('tak_score', $campusData['tak_score'] ?? optional($registration)->tak_score ?? '') }}"
                   placeholder="85" class="campus-input">
            @error('tak_score') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Tanggal Sidang --}}
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="campus-label !mb-0">Tanggal Sidang</label>
                <button type="button"
                        @click="$dispatch('open-info-modal', { key: 'exam_date' })"
                        class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                        title="Lihat Ketentuan Pengisian" aria-label="Lihat Ketentuan Pengisian">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
            </div>
            <input type="date" name="exam_date" value="{{ old('exam_date', isset($campusData['exam_date']) ? \Carbon\Carbon::parse($campusData['exam_date'])->format('Y-m-d') : optional($registration)->exam_date?->format('Y-m-d') ?? '') }}"
                   class="campus-input">
        </div>

        {{-- Batas Revisi --}}
        <div>
            <label class="campus-label">Batas Revisi</label>
            <input type="date" name="revision_deadline" value="{{ old('revision_deadline', optional($registration)->revision_deadline?->format('Y-m-d') ?? '') }}"
                   class="campus-input">
        </div>

        {{-- Tanggal Selesai Revisi --}}
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="campus-label !mb-0">Tanggal Selesai Revisi</label>
                <button type="button"
                        @click="$dispatch('open-info-modal', { key: 'revision_completion_date' })"
                        class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                        title="Lihat Ketentuan Pengisian" aria-label="Lihat Ketentuan Pengisian">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
            </div>
            <input type="date" name="revision_completion_date" value="{{ old('revision_completion_date', optional($registration)->revision_completion_date?->format('Y-m-d') ?? '') }}"
                   class="campus-input">
        </div>
    </div>
</div>
