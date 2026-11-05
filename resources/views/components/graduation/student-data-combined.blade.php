{{-- Gabungan Data Mahasiswa: hasil grab + data wisuda (auto-fill editable) --}}
@props(['campusData' => [], 'registration' => null])

<div class="space-y-4">
    {{-- Info Box dengan caption sumber data --}}
    <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg flex items-start gap-3">
        <svg class="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1">
            <p class="text-xs font-semibold text-rose-800">Data Mahasiswa</p>
            <p class="text-xs text-rose-700 mt-1">
                Sebagian data <span class="font-medium">diambil otomatis dari iGracias</span>
            </p>
        </div>
    </div>

    {{-- BAGIAN 1: Data Identitas (dari grab, editable) --}}
    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- NIM --}}
            <div>
                <label class="campus-label !mb-0">NIM</label>
                <input type="text" name="student_number" value="{{ old('student_number', $campusData['student_number'] ?? '') }}"
                       placeholder="NIM" class="campus-input">
            </div>

            {{-- Nama Mahasiswa --}}
            <div>
                <label class="campus-label !mb-0">Nama Mahasiswa</label>
                <input type="text" name="student_name" value="{{ old('student_name', $campusData['student_name'] ?? '') }}"
                       placeholder="Nama lengkap" class="campus-input">
            </div>

            {{-- Program Studi --}}
            <div>
                <label class="campus-label !mb-0">Program Studi</label>
                <input type="text" name="study_program" value="{{ old('study_program', $campusData['study_program'] ?? '') }}"
                       placeholder="Program studi" class="campus-input">
            </div>

            {{-- Email Kampus --}}
            <div>
                <label class="campus-label !mb-0">Email Kampus</label>
                <input type="email" name="student_email" value="{{ old('student_email', $campusData['student_email'] ?? '') }}"
                       placeholder="email@kampus.ac.id" class="campus-input">
            </div>

            {{-- Dosen Wali (Kode + Nama) --}}
            <div>
                <label class="campus-label !mb-0">Dosen Wali (Kode)</label>
                <input type="text" name="academic_advisor_code" value="{{ old('academic_advisor_code', $campusData['academic_advisor_code'] ?? '') }}"
                       placeholder="KDD" class="campus-input">
            </div>

            <div>
                <label class="campus-label">Dosen Wali (Nama)</label>
                <input type="text" name="academic_advisor_name" value="{{ old('academic_advisor_name', $campusData['academic_advisor_name'] ?? '') }}"
                       placeholder="Nama dosen" class="campus-input">
            </div>
        </div>
    </div>

    {{-- BAGIAN 2: Judul TA (dari grab, editable) --}}
    <div class="space-y-3 pt-4 border-t border-slate-200">
        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="campus-label !mb-0">Judul TA (Bahasa Indonesia) <span class="text-rose-500">*</span></label>
                <button type="button"
                        @click="$dispatch('open-info-modal', { key: 'thesis_title_id' })"
                        class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                        title="Lihat Ketentuan & Contoh" aria-label="Lihat Ketentuan & Contoh">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
            </div>
            <textarea name="thesis_title_id" rows="2" placeholder="Masukkan judul tugas akhir dalam Bahasa Indonesia" class="campus-input">{{ old('thesis_title_id', $campusData['thesis_title_id'] ?? '') }}</textarea>
            @error('thesis_title_id') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center gap-1.5 mb-1">
                <label class="campus-label !mb-0">Judul TA (Bahasa Inggris) <span class="text-rose-500">*</span></label>
                <button type="button"
                        @click="$dispatch('open-info-modal', { key: 'thesis_title_en' })"
                        class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                        title="Lihat Ketentuan & Contoh" aria-label="Lihat Ketentuan & Contoh">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
            </div>
            <textarea name="thesis_title_en" rows="2" placeholder="Enter your thesis title in English" class="campus-input">{{ old('thesis_title_en', $campusData['thesis_title_en'] ?? '') }}</textarea>
            @error('thesis_title_en') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- BAGIAN 3: Data Akademik (dari grab, editable) --}}
    <div class="space-y-3 pt-4 border-t border-slate-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Skor TAK --}}
            <div>
                <label class="campus-label !mb-0">Skor TAK</label>
                <input type="text" name="tak_score" value="{{ old('tak_score', $campusData['tak_score'] ?? '') }}"
                       placeholder="85" class="campus-input">
            </div>

            {{-- Skor EPRT --}}
            <div>
                <label class="campus-label !mb-0">Skor EPRT</label>
                <input type="text" name="eprt_score" value="{{ old('eprt_score', $campusData['eprt_score'] ?? '') }}"
                       placeholder="420" class="campus-input">
            </div>

            {{-- Tanggal Sidang --}}
            <div>
                <label class="campus-label !mb-0">Tanggal Sidang</label>
                <input type="date" name="exam_date" value="{{ old('exam_date', isset($campusData['exam_date']) ? \Carbon\Carbon::parse($campusData['exam_date'])->format('Y-m-d') : '') }}"
                       class="campus-input">
            </div>

            {{-- Status Sidang Tingkat 3 --}}
            <div>
                <label class="campus-label !mb-0">Status Sidang Tingkat 3</label>
                <input type="text" name="level_3_exam_status" value="{{ old('level_3_exam_status', isset($campusData['level_3_exam_status']) ? ($campusData['level_3_exam_status'] === 'passed' ? 'Lulus' : 'Belum Lulus') : '') }}"
                       class="campus-input">
            </div>

            {{-- Status Approval Foto i-Basila --}}
            <div>
                <label class="campus-label !mb-0">Status Approval Foto i-Basila</label>
                <input type="text" name="ibasila_photo_approval_status" value="{{ old('ibasila_photo_approval_status', isset($campusData['ibasila_photo_approval_status']) ? ucfirst($campusData['ibasila_photo_approval_status']) : '') }}"
                       class="campus-input">
            </div>
        </div>
    </div>

    {{-- BAGIAN 4: Data Wisuda (manual input, fully editable) --}}
    <div class="space-y-3 pt-4 border-t border-slate-200">
        <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Data untuk Wisuda</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- No. HP Mahasiswa --}}
            <div>
                <label class="campus-label">No. HP Mahasiswa <span class="text-rose-500">*</span></label>
                <input type="text" name="student_phone" value="{{ old('student_phone', optional($registration)->student_phone ?? '') }}"
                       placeholder="08xxxxxxxxxx" class="campus-input" required>
                @error('student_phone') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- No. HP Orang Tua --}}
            <div>
                <label class="campus-label">No. HP Orang Tua <span class="text-rose-500">*</span></label>
                <input type="text" name="parent_phone" value="{{ old('parent_phone', optional($registration)->parent_phone ?? '') }}"
                       placeholder="08xxxxxxxxxx" class="campus-input" required>
                @error('parent_phone') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Email Pribadi --}}
            <div>
                <label class="campus-label">Email Pribadi (Gmail) <span class="text-rose-500">*</span></label>
                <input type="email" name="personal_email" value="{{ old('personal_email', optional($registration)->personal_email ?? '') }}"
                       placeholder="nama@gmail.com" class="campus-input" required>
                <p class="text-xs text-slate-500 mt-1">Harus menggunakan domain @gmail.com</p>
                @error('personal_email') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Tanggal Selesai Revisi --}}
            <div>
                <label class="campus-label">Tanggal Selesai Revisi</label>
                <input type="date" name="revision_completion_date" 
                       value="{{ old('revision_completion_date', optional($registration)->revision_completion_date?->format('Y-m-d') ?? '') }}"
                       class="campus-input">
                @error('revision_completion_date') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Mata Kuliah Belum Lulus --}}
    @if(isset($campusData['failed_courses']) && !empty($campusData['failed_courses']))
    <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg mt-4">
        <label class="campus-label text-rose-800">Mata Kuliah Belum Lulus</label>
        <ul class="mt-2 space-y-1 text-xs text-rose-700">
            @foreach($campusData['failed_courses'] as $course)
            <li class="flex items-start gap-2">
                <span class="font-mono bg-rose-100 px-2 py-0.5 rounded">{{ $course['course_code'] }}</span>
                <span>{{ $course['course_name'] }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Mata Kuliah Mengulang (opsional) --}}
    @if(isset($campusData['retaken_courses']) && !empty($campusData['retaken_courses']))
    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <label class="campus-label text-amber-800">Mata Kuliah Mengulang</label>
        <ul class="mt-2 space-y-1 text-xs text-amber-700">
            @foreach($campusData['retaken_courses'] as $course)
            <li class="flex items-start gap-2">
                <span class="font-mono bg-amber-100 px-2 py-0.5 rounded">{{ $course['course_code'] }}</span>
                <span>{{ $course['course_name'] }} (Attempt: {{ $course['attempt'] }})</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
