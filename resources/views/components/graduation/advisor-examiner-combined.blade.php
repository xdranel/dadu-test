{{-- Gabungan Pembimbing & Penguji (auto-fill editable) --}}
@props(['campusData' => [], 'registration' => null])

<div class="space-y-4">
    {{-- Pembimbing --}}
    <div>
        <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wider mb-3">Pembimbing</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Pembimbing 1 --}}
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="campus-label !mb-0 text-xs">Kode Dosen 1</label>
                    <input type="text" name="advisor_1_code" value="{{ old('advisor_1_code', $campusData['advisor_1_code'] ?? optional($registration)->advisor_1_code ?? '') }}"
                           placeholder="KDD" class="campus-input text-xs">
                </div>
                <div class="col-span-2">
                    <label class="campus-label !mb-0 text-xs">Nama Dosen Pembimbing 1</label>
                    <input type="text" name="advisor_1_name" value="{{ old('advisor_1_name', $campusData['advisor_1_name'] ?? optional($registration)->advisor_1_name ?? '') }}"
                           placeholder="Nama lengkap" class="campus-input text-xs">
                </div>
            </div>

            {{-- Pembimbing 2 --}}
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="campus-label !mb-0 text-xs">Kode Dosen 2</label>
                    <input type="text" name="advisor_2_code" value="{{ old('advisor_2_code', $campusData['advisor_2_code'] ?? optional($registration)->advisor_2_code ?? '') }}"
                           placeholder="KDD" class="campus-input text-xs">
                </div>
                <div class="col-span-2">
                    <label class="campus-label !mb-0 text-xs">Nama Dosen Pembimbing 2</label>
                    <input type="text" name="advisor_2_name" value="{{ old('advisor_2_name', $campusData['advisor_2_name'] ?? optional($registration)->advisor_2_name ?? '') }}"
                           placeholder="Nama lengkap" class="campus-input text-xs">
                </div>
            </div>
        </div>
    </div>

    {{-- Penguji --}}
    <div class="pt-4 border-t border-slate-200">
        <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wider mb-3">Penguji</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach([1, 2, 3] as $i)
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="campus-label !mb-0 text-xs">Kode {{ $i }}</label>
                    <input type="text" name="examiner_{{ $i }}_code" value="{{ old("examiner_{$i}_code", $campusData["examiner_{$i}_code"] ?? optional($registration)->{"examiner_{$i}_code"} ?? '') }}"
                           placeholder="KDD" class="campus-input text-xs">
                </div>
                <div class="col-span-2">
                    <label class="campus-label !mb-0 text-xs">Nama Penguji {{ $i }}</label>
                    <input type="text" name="examiner_{{ $i }}_name" value="{{ old("examiner_{$i}_name", $campusData["examiner_{$i}_name"] ?? optional($registration)->{"examiner_{$i}_name"} ?? '') }}"
                           placeholder="Nama lengkap" class="campus-input text-xs">
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
