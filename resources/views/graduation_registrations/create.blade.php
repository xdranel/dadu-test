<x-app-layout>
    <x-slot name="header">Pendaftaran Yudisium</x-slot>

    <!-- Notice Banner if Closed -->
    @if(!$activePeriod || !$activePeriod->isOpen())
        <div class="mb-6 campus-card border-l-4 border-amber-500 p-5 bg-gradient-to-r from-amber-50/50 via-white to-white">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 text-amber-700 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-bold text-slate-800">Formulir Pendaftaran Yudisium Ditutup</h3>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                        Formulir ini tidak lagi menerima pengajuan pendaftaran yudisium baru saat ini.
                        @if($activePeriod)
                            Periode <strong>{{ $activePeriod->name }}</strong> telah ditutup pada <strong>{{ $activePeriod->close_at?->translatedFormat('d F Y, H:i') }} WIB</strong>.
                        @else
                            Belum ada periode pendaftaran yang dibuka oleh admin. Silakan periksa kembali jadwal resmi dari fakultas/prodi.
                        @endif
                    </p>
                    <div class="mt-3">
                        <a href="{{ route('graduation-registrations.index') }}" class="btn btn-secondary text-xs">
                            Kembali ke Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($activePeriod && $activePeriod->isOpen())
    <form id="graduation-submit-form" action="{{ route('graduation-registrations.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="graduation_period" value="{{ $activePeriod->name ?? '' }}">

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

        <!-- Eligibility Warning Banner -->
        @if(isset($eligibility) && !$eligibility['eligible'])
        <div class="mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl shadow-sm">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div class="space-y-1">
                <p class="text-xs sm:text-sm font-bold text-rose-900">Pendaftaran Yudisium Terkunci / Belum Memenuhi Syarat Akademik:</p>
                <ul class="text-xs list-disc list-inside space-y-0.5 text-rose-700">
                    @foreach($eligibility['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
                <p class="text-[11px] text-rose-600 italic pt-1">Silakan selesaikan persyaratan di atas terlebih dahulu untuk dapat mengajukan pendaftaran yudisium.</p>
            </div>
        </div>
        @endif

        <!-- PANEL LAYOUT CONTAINER -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            
            <!-- PANEL KIRI (COL-SPAN 7): FORM INPUT MANUAL & UPLOAD MAHASISWA -->
            <div class="lg:col-span-7 space-y-5">

                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#DA2D3B]"></span>
                            Data Wisuda & Kontak (Manual)
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="form-section-body" x-show="open" x-transition>
                        <x-graduation.manual-input-fields :registration="null" />
                    </div>
                </div>

                <!-- Judul Pasca Sidang -->
                <div class="form-section" x-data="{
                    hasTitleChange: '{{ old('has_title_change', 'tidak') }}',
                    skStatus: '{{ old('sk_update_status', '') }}'
                }">
                    <div class="form-section-header">
                        <span class="form-section-title text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#DA2D3B]"></span>
                            Perubahan Judul Pasca Sidang
                        </span>
                    </div>
                    <div class="form-section-body space-y-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2">
                                Apakah Terdapat Perubahan Judul Pasca Sidang?
                            </label>
                            <div class="flex items-center gap-6">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs sm:text-sm text-slate-700">
                                    <input type="radio" name="has_title_change" value="ya" x-model="hasTitleChange" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Ya</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs sm:text-sm text-slate-700">
                                    <input type="radio" name="has_title_change" value="tidak" x-model="hasTitleChange" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Tidak</span>
                                </label>
                            </div>
                        </div>

                        <div x-show="hasTitleChange === 'ya'" x-transition class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2">
                                    Apakah Sudah Melakukan Pembaruan SK TA?
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                        <input type="radio" name="sk_update_status" value="not_processed_laa" x-model="skStatus" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                        <span>Sudah Mengajukan, Belum Diproses LAA</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                        <input type="radio" name="sk_update_status" value="processed_laa" x-model="skStatus" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                        <span>Sudah Mengajukan dan Sudah Diproses LAA</span>
                                    </label>
                                </div>
                            </div>

                            <div x-show="skStatus === 'not_processed_laa'" x-transition class="pt-2">
                                <label class="campus-label">Kapan Submit Pembaruan SK TA di forms?</label>
                                <input type="date" name="sk_update_submission_date" value="{{ old('sk_update_submission_date') }}" class="campus-input max-w-xs">
                            </div>
                        </div>
                    </div>
                </div>

                <!--  Unggah Dokumen Pendukung -->
                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#DA2D3B]"></span>
                            Unggah Dokumen Pendukung
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="form-section-body" x-show="open" x-transition>
                        <p class="text-xs text-slate-500 mb-4">Format file: <strong>PDF, JPG, PNG</strong>. Ukuran maksimal per file: <strong>10 MB</strong>.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($documentTypes as $type => $label)
                            <div x-data="{
                                fileName: '',
                                type: '{{ $type }}',
                                name: '{{ addslashes($label) }}',
                                clearFile() {
                                    this.fileName = '';
                                    if (this.$refs.fileInput) {
                                        this.$refs.fileInput.value = '';
                                    }
                                }
                            }"
                            @confirm-delete-doc.window="if ($event.detail.type === type) clearFile()">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <label class="campus-label !mb-0">{{ $label }}</label>
                                    <button type="button"
                                            @click="$dispatch('open-info-modal', { key: type })"
                                            class="inline-flex items-center justify-center text-slate-400 hover:text-[#DA2D3B] transition-colors cursor-pointer"
                                            title="Lihat Ketentuan & Contoh Dokumen" aria-label="Lihat Ketentuan & Contoh Dokumen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="upload-zone relative" :class="fileName ? 'has-file' : ''">
                                    <input type="file" name="documents[{{ $type }}]" accept=".pdf,.jpg,.jpeg,.png"
                                           x-ref="fileInput"
                                           @change="fileName = $event.target.files[0]?.name ?? ''">
                                    
                                    <div x-show="!fileName" class="py-2 text-center">
                                        <p class="text-xs text-slate-400">Klik untuk memilih file</p>
                                    </div>

                                    <div x-show="fileName" class="py-2 px-3 flex items-center justify-between gap-2 relative z-30 pointer-events-auto" style="display: none;">
                                        <p class="text-xs text-emerald-700 font-medium truncate flex-1" x-text="fileName"></p>
                                        <button type="button" 
                                                @click.stop.prevent="$dispatch('open-doc-delete-modal', { type: type, name: name })"
                                                class="relative z-30 pointer-events-auto p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-100 rounded-lg transition-colors cursor-pointer"
                                                title="Hapus file ini" aria-label="Hapus file ini">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                @error("documents.{$type}") <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Predikat Kelulusan (Cumlaude/Summa Cumlaude) -->
                <div class="form-section" x-data="{
                    open: true,
                    honors_predicate: '{{ old('honors_predicate', 'none') }}',
                    predicate_criteria: '{{ old('predicate_criteria', '') }}',
                    predicate_umkm_partner: '{{ old('predicate_umkm_partner', '') }}',
                    predicate_competition_level: '{{ old('predicate_competition_level', '') }}',
                    predicate_journal_index: '{{ old('predicate_journal_index', '') }}',
                    sendEmailReceipt: false,
                    fileName: '',
                    step: {{ old('honors_predicate') && old('honors_predicate') !== 'none' ? 2 : 1 }},
                    selectPredicate(val) {
                        this.honors_predicate = val;
                        if (val === 'none') {
                            this.step = 1;
                            this.predicate_criteria = '';
                        } else {
                            this.step = 2;
                        }
                    },
                    selectCriteria(val) {
                        this.predicate_criteria = val;
                        this.step = 3;
                    },
                    goBackStep() {
                        if (this.step === 3) {
                            this.step = 2;
                        } else if (this.step === 2) {
                            this.step = 1;
                            this.honors_predicate = 'none';
                            this.predicate_criteria = '';
                        }
                    }
                }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#DA2D3B]"></span>
                            Predikat Kelulusan (CUMLAUDE / SUMMA CUMLAUDE)
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    <div class="form-section-body space-y-4" x-show="open" x-transition>

                        <!-- Pertanyaan Awal -->
                        <div>
                            <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2">
                                Apakah akan mengajukan predikat kelulusan (CUMLAUDE/SUMMA CUMLAUDE)? <span class="text-rose-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                    <input type="radio" name="honors_predicate" value="cumlaude"
                                           x-model="honors_predicate"
                                           @change="selectPredicate('cumlaude')"
                                           class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Ya, akan mengajukan CUMLAUDE</span>
                                </label>

                                <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                    <input type="radio" name="honors_predicate" value="summa_cumlaude"
                                           x-model="honors_predicate"
                                           @change="selectPredicate('summa_cumlaude')"
                                           class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Ya, akan mengajukan SUMMA CUMLAUDE</span>
                                </label>

                                <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                    <input type="radio" name="honors_predicate" value="none"
                                           x-model="honors_predicate"
                                           @change="selectPredicate('none')"
                                           class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Tidak</span>
                                </label>
                            </div>
                            @error('honors_predicate') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Kriteria Tambahan Predikat  -->
                        <div x-show="honors_predicate !== 'none'" x-transition class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-semibold text-slate-700 mb-2">
                                    Kriteria Tambahan Predikat <span class="text-rose-500">*</span>
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                        <input type="radio" name="predicate_criteria" value="hki"
                                               x-model="predicate_criteria"
                                               @change="selectCriteria('hki')"
                                               class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                        <span>Hak Cipta</span>
                                    </label>

                                    <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                        <input type="radio" name="predicate_criteria" value="lomba"
                                               x-model="predicate_criteria"
                                               @change="selectCriteria('lomba')"
                                               class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                        <span>Lomba</span>
                                    </label>

                                    <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-sm text-slate-700">
                                        <input type="radio" name="predicate_criteria" value="publikasi"
                                               x-model="predicate_criteria"
                                               @change="selectCriteria('publikasi')"
                                               class="text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                        <span>Publikasi Ilmiah</span>
                                    </label>
                                </div>
                                @error('predicate_criteria') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <!-- Conditional Fields & Upload -->
                            <div x-show="predicate_criteria !== ''" x-transition class="space-y-4 pt-2 border-t border-slate-200">
                                
                                <!-- Field A: Hak Cipta -->
                                <div x-show="predicate_criteria === 'hki'" x-transition>
                                    <label class="campus-label">Nama Mitra UMKM Minimal Usaha Menengah <span class="text-rose-500">*</span></label>
                                    <input type="text" name="predicate_umkm_partner" x-model="predicate_umkm_partner"
                                           placeholder="Contoh: PT Kerajinan Nusantara Jaya" class="campus-input">
                                    @error('predicate_umkm_partner') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Field B: Lomba -->
                                <div x-show="predicate_criteria === 'lomba'" x-transition>
                                    <label class="campus-label">Capaian Tingkat Lomba <span class="text-rose-500">*</span></label>
                                    <select name="predicate_competition_level" x-model="predicate_competition_level" class="campus-select">
                                        <option value="">-- Pilih Capaian Tingkat Lomba --</option>
                                        <option value="juara_nasional_1">Juara Nasional Peringkat 1</option>
                                        <option value="juara_nasional_2">Juara Nasional Peringkat 2</option>
                                        <option value="juara_nasional_3">Juara Nasional Peringkat 3</option>
                                        <option value="juara_internasional_1">Juara Internasional Peringkat 1</option>
                                        <option value="juara_internasional_2">Juara Internasional Peringkat 2</option>
                                        <option value="juara_internasional_3">Juara Internasional Peringkat 3</option>
                                    </select>
                                    @error('predicate_competition_level') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Field C: Publikasi Ilmiah -->
                                <div x-show="predicate_criteria === 'publikasi'" x-transition>
                                    <label class="campus-label">Indeks Jurnal <span class="text-rose-500">*</span></label>
                                    <select name="predicate_journal_index" x-model="predicate_journal_index" class="campus-select">
                                        <option value="">-- Pilih Indeks Jurnal --</option>
                                        <option value="s2">S2</option>
                                        <option value="s1">S1</option>
                                        <option value="scopus_conference">Conference International Terindeks Scopus</option>
                                        <option value="q4">Q4</option>
                                        <option value="q3">Q3</option>
                                        <option value="q2">Q2</option>
                                        <option value="q1">Q1</option>
                                    </select>
                                    @error('predicate_journal_index') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <!-- Info Box Panduan Persyaratan -->
                                <div class="p-3.5 bg-slate-100/70 border border-slate-200 rounded-xl text-xs text-slate-700 space-y-1.5">
                                    <p class="font-semibold text-slate-800 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Panduan Upload Persyaratan Predikat:
                                    </p>
                                    <ul class="list-disc pl-5 space-y-1 text-slate-600 leading-relaxed">
                                        <li><strong>Summa-Cumlaude:</strong> Memenuhi kriteria utama & rekomendasi tim penguji sidang.</li>
                                        <li><strong>Cumlaude dengan Publikasi Ilmiah:</strong> Melampirkan bukti publikasi terindeks/reputasi.</li>
                                        <li><strong>Cumlaude sebagai Penyaji Karya:</strong> Melampirkan sertifikat/surat keterangan penyaji karya.</li>
                                        <li><strong>Cumlaude dengan Lomba:</strong> Melampirkan sertifikat juara lomba nasional/internasional.</li>
                                        <li><strong>Cumlaude dengan HKI:</strong> Melampirkan sertifikat HKI & bukti kerjasama Mitra UMKM Menengah.</li>
                                    </ul>
                                </div>

                                <!-- Field Upload File Dokumen Predikat -->
                                <div>
                                    <label class="campus-label">Upload Seluruh Persyaratan Cumlaude atau Summa-Cumlaude <span class="text-rose-500">*</span></label>
                                    <div class="upload-zone relative" :class="fileName ? 'has-file' : ''">
                                        <input type="file" name="predicate_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.mp4,.avi,.mp3,.wav"
                                               @change="fileName = $event.target.files[0]?.name ?? ''">
                                        <div x-show="!fileName" class="py-3 text-center">
                                            <p class="text-xs font-semibold text-slate-700">Unggah file</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Jumlah file maks. 1, Ukuran maks. 100MB. Tipe file: Word, Excel, PPT, PDF, Gambar, Video, Audio.</p>
                                        </div>
                                        <div x-show="fileName" class="py-2 px-3 flex items-center justify-between gap-2" style="display: none;">
                                            <p class="text-xs text-emerald-700 font-semibold truncate flex-1" x-text="fileName"></p>
                                            <button type="button" @click="fileName = ''" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    @error('predicate_document') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <!-- Tombol Navigasi Step & Checkbox -->
                            <div class="pt-3 flex items-center justify-between gap-3 border-t border-slate-200">
                                <div>
                                    <button type="button" @click="goBackStep()" x-show="step > 1" class="btn btn-secondary !px-3.5 !py-1 text-xs">
                                        Kembali
                                    </button>
                                </div>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-slate-600">
                                    <input type="checkbox" x-model="sendEmailReceipt" class="rounded border-slate-300 text-[#DA2D3B] focus:ring-[#DA2D3B]">
                                    <span>Kirimkan saya tanda terima email dari tanggapan saya</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!--  Catatan Tambahan -->
                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title text-sm font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#DA2D3B]"></span>
                            Catatan Tambahan
                        </span>
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="form-section-body" x-show="open" x-transition>
                        <div>
                            <label class="campus-label">Catatan Koreksi Biodata Ijazah (Opsional)</label>
                            <textarea name="biodata_correction_note" rows="3" class="campus-input !h-auto"
                                      placeholder="Jika biodata tidak sesuai, tuliskan kapan Anda mengajukan permohonan koreksi...">{{ old('biodata_correction_note') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-5 border-t border-slate-200">
                    <a href="{{ route('graduation-registrations.index') }}" class="btn btn-secondary">
                        Kembali
                    </a>
                    @if(isset($eligibility) && !$eligibility['eligible'])
                    <button type="button" disabled title="Pendaftaran terkunci karena syarat belum terpenuhi"
                            class="btn btn-primary opacity-50 cursor-not-allowed pointer-events-none flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Ajukan Pendaftaran (Terkunci)
                    </button>
                    @else
                    <button type="button" 
                            @click="$dispatch('open-submit-modal')"
                            class="btn btn-primary">
                        Ajukan Pendaftaran
                    </button>
                    @endif
                </div>
            </div>

            
            <!-- PANEL KANAN DISPLAY DATA AKADEMIK OTOMATIS KAMPUS -->
            
            <div class="lg:col-span-5 sticky top-6 space-y-5">
                <x-graduation.campus-data-display :campusData="$campusData ?? []" />
            </div>

        </div>
    </form>
    @endif

    <!-- Modal Konfirmasi Pengajuan Yudisium (dengan Checkbox Pernyataan) -->
    <div x-data="{ openSubmitModal: false, declarationChecked: false }"
         x-show="openSubmitModal"
         @open-submit-modal.window="openSubmitModal = true; declarationChecked = false;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
         style="display: none;"
         x-transition>
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md border border-slate-200"
             @click.away="openSubmitModal = false">
            <div class="text-center">
                <!-- Icon Header Red Accent -->
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 text-[#DA2D3B] mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Pengajuan Yudisium</h3>
            </div>

            <!-- Checkbox Pernyataan / Declaration -->
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl mb-6 text-left">
                <label class="flex items-start gap-3 cursor-pointer select-none">
                    <input type="checkbox" x-model="declarationChecked" 
                           class="mt-0.5 rounded border-slate-300 text-[#DA2D3B] focus:ring-[#DA2D3B] flex-shrink-0 cursor-pointer">
                    <span class="text-[11px] sm:text-xs text-slate-700 leading-relaxed">
                        Saya <strong>MENYATAKAN</strong> bahwa saya telah memastikan dan memeriksa kembali seluruh berkas telah lengkap dan sesuai dengan ketentuan pada form dan contoh yang tertera pada surat edaran. Jika berkas tidak lengkap, maka saya siap tidak disertakan pada sidang yudisium periode terdekat.
                    </span>
                </label>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3">
                <button type="button" @click="openSubmitModal = false"
                        class="btn btn-secondary w-full">
                    Batal
                </button>
                <button type="button" 
                        @click="if (declarationChecked) { document.getElementById('graduation-submit-form').submit(); }"
                        :disabled="!declarationChecked"
                        :class="declarationChecked ? 'bg-[#DA2D3B] hover:bg-[#b92532] text-white cursor-pointer' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                        class="btn w-full font-semibold transition-all">
                    Ya, Ajukan Pendaftaran
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Dokumen -->
    <div x-data="{ openDelete: false, targetType: '', targetName: '' }"
         x-show="openDelete"
         @open-doc-delete-modal.window="openDelete = true; targetType = $event.detail.type; targetName = $event.detail.name;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
         style="display: none;"
         x-transition>
        <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4 border border-slate-200"
             @click.away="openDelete = false">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 text-rose-600 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Konfirmasi Hapus</h3>
                <p class="text-sm text-slate-500 mb-6 font-normal">
                    Apakah Anda yakin ingin menghapus file untuk <span class="font-semibold text-slate-800" x-text="targetName"></span>?
                </p>
            </div>
            <div class="flex items-center justify-end gap-3">
                <button type="button" @click="openDelete = false"
                        class="btn btn-secondary w-full">Batal</button>
                <button type="button" 
                        @click="$dispatch('confirm-delete-doc', { type: targetType }); openDelete = false;" 
                        class="btn btn-primary bg-rose-600 hover:bg-rose-700 w-full text-white">
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
    <!-- Modal Info Ketentuan Field & Dokumen -->
    @include('graduation_registrations.partials.info_modal')
</x-app-layout>
