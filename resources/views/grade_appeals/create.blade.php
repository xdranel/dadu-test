<x-app-layout>
    <x-slot name="header">Form Banding Nilai</x-slot>

    <form id="grade-appeal-form"
          action="{{ route('grade-appeals.store') }}"
          method="POST"
          enctype="multipart/form-data"
          x-data="gradeAppealForm()"
          x-init="init()">
        @csrf

        <!-- Validation Errors Banner -->
        @if($errors->any())
        <div class="mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-xs sm:text-sm font-semibold mb-1">Terdapat kesalahan pada formulir:</p>
                <ul class="text-xs list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Card 1: Data Mahasiswa (Auto-fill / Read-only) -->
        <div class="form-section mb-5" x-data="{ open: true }">
            <div class="form-section-header" @click="open = !open">
                <span class="form-section-title">
                    <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Data Mahasiswa
                </span>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div class="form-section-body" x-show="open" x-transition>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Nama (read-only) -->
                    <div>
                        <label class="campus-label">Nama</label>
                        <input type="text"
                               value="{{ $student->name ?? auth()->user()->name }}"
                               class="campus-input bg-slate-50" readonly>
                    </div>

                    <!-- NIM (read-only) -->
                    <div>
                        <label class="campus-label">NIM</label>
                        <input type="text"
                               value="{{ $student->student_number ?? auth()->user()->employee_id ?? auth()->user()->username }}"
                               class="campus-input bg-slate-50" readonly>
                    </div>

                    <!-- Prodi (read-only, auto-fill) -->
                    <div>
                        <label class="campus-label">Program Studi</label>
                        <input type="text"
                               value="{{ $student->study_program ?? 'N/A' }}"
                               class="campus-input bg-slate-50" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Data Pengajuan -->
        <div class="form-section mb-5" x-data="{ open: true }">
            <div class="form-section-header" @click="open = !open">
                <span class="form-section-title">
                    <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Data Pengajuan Banding
                </span>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div class="form-section-body" x-show="open" x-transition>
                <!-- Mata Kuliah (Manual Input) -->
                <div>
                    <label class="campus-label">Mata Kuliah <span class="text-rose-500">*</span></label>
                    <input type="text"
                           name="course_name_manual"
                           value="{{ old('course_name_manual') }}"
                           class="campus-input"
                           placeholder="Masukkan nama mata kuliah"
                           required>
                    @error('course_name_manual') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Card 3: Upload Evidence -->
        <div class="form-section mb-5" x-data="{ open: true }">
            <div class="form-section-header" @click="open = !open">
                <span class="form-section-title">
                    <svg class="w-[18px] h-[18px] text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Form Pengajuan dan Evidence
                </span>
                <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
            <div class="form-section-body" x-show="open" x-transition>

                <!-- Download Template Link -->
                <div class="mb-4 flex items-start gap-2.5 border border-slate-200 text-slate-600 px-4 py-3 rounded-xl">
                    <svg class="w-[18px] h-[18px] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-xs leading-relaxed">
                        <p class="font-semibold mb-1">Petunjuk Pengisian</p>
                        <p>Unduh template form pengajuan banding nilai terlebih dahulu, isi, dan unggah kembali dalam format <strong>PDF</strong> (maks. 5MB).</p>
                        <a href="https://telkomuniversityofficial-my.sharepoint.com/:w:/g/personal/akademiksby_telkomuniversity_ac_id/IQA1ockMnndKQYL23XmBhV1WASY0zALtSwrUcw8Q_TaTo4o?e=TWLkF3"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 mt-2 text-[#DA2D3B] hover:text-[#B8232F] font-semibold underline underline-offset-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download Template Form Banding Nilai
                        </a>
                    </div>
                </div>

                <!-- Upload Zone -->
                <div>
                    <label class="campus-label">Upload Form Pengajuan dan Evidence <span class="text-rose-500">*</span></label>
                    <div class="upload-zone"
                         :class="fileName ? 'has-file' : ''"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event)">

                        <input type="file" name="evidence_file" accept=".pdf"
                               @change="handleFileSelect($event)" id="evidence-file-input">

                        <!-- Default state -->
                        <div x-show="!fileName" class="py-4">
                            <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-xs text-slate-500 font-medium">Klik atau seret file PDF ke sini</p>
                            <p class="text-[11px] text-slate-400 mt-1">Format: PDF · Maks: 5MB</p>
                        </div>

                        <!-- File selected state -->
                        <div x-show="fileName" class="py-3 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-medium text-slate-700 truncate max-w-xs" x-text="fileName"></span>
                            <button type="button" @click.stop="clearFile()" class="text-slate-400 hover:text-rose-500 ml-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- File error -->
                    <p x-show="fileError" x-text="fileError" class="text-xs text-rose-500 mt-1.5" x-cloak></p>
                    @error('evidence_file') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-between mt-6">
            <a href="{{ route('grade-appeals.index') }}" class="btn btn-secondary">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali
            </a>

            <button type="submit"
                    class="btn btn-primary px-5"
                    :disabled="isSubmitting"
                    @click.prevent="submitForm()">
                <template x-if="!isSubmitting">
                    <span class="flex items-center gap-1.5">
                        Ajukan Banding Nilai
                    </span>
                </template>
                <template x-if="isSubmitting">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Mengirim...
                    </span>
                </template>
            </button>
        </div>
    </form>

    <script>
    function gradeAppealForm() {
        return {
            fileName: '',
            fileError: '',
            isDragging: false,
            isSubmitting: false,

            handleFileSelect(event) {
                const file = event.target.files[0];
                this.validateFile(file);
            },

            handleDrop(event) {
                this.isDragging = false;
                const file = event.dataTransfer.files[0];
                if (file) {
                    // Set the file to the input
                    const input = document.getElementById('evidence-file-input');
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    this.validateFile(file);
                }
            },

            validateFile(file) {
                this.fileError = '';
                this.fileName = '';

                if (!file) return;

                // Check file type
                if (file.type !== 'application/pdf') {
                    this.fileError = 'File harus berformat PDF.';
                    this.clearFile();
                    return;
                }

                // Check file size (5MB = 5 * 1024 * 1024)
                if (file.size > 5 * 1024 * 1024) {
                    this.fileError = 'Ukuran file tidak boleh melebihi 5MB. Ukuran file Anda: ' + (file.size / 1024 / 1024).toFixed(2) + 'MB.';
                    this.clearFile();
                    return;
                }

                this.fileName = file.name;
            },

            clearFile() {
                this.fileName = '';
                this.fileError = '';
                const input = document.getElementById('evidence-file-input');
                if (input) input.value = '';
            },

            submitForm() {
                this.fileError = '';

                const input = document.getElementById('evidence-file-input');
                if (!input.files.length) {
                    this.fileError = 'File Form Pengajuan dan Evidence wajib diunggah.';
                    return;
                }

                this.isSubmitting = true;
                document.getElementById('grade-appeal-form').submit();
            }
        }
    }
    </script>
</x-app-layout>
