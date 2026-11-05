<x-app-layout>
    <x-slot name="header">{{ $registration ? 'Revisi Pendaftaran Sidang TA' : 'Form Pendaftaran Sidang TA' }}</x-slot>

    <form id="sidang-ta-submit-form" method="POST" enctype="multipart/form-data"
          action="{{ $registration ? route('sidang-ta-registrations.update', $registration) : route('sidang-ta-registrations.store') }}"
          x-data="{ reviewing: false, accurate: '{{ old('diploma_biodata_accurate', $registration ? ($registration->diploma_biodata_accurate ? '1' : '0') : '') }}' }">
        @csrf
        @if($registration) @method('PUT') @endif
        <input type="hidden" name="action" value="submit">

        @if($errors->any())
            <div class="mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-rose-800">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-.77-2.694-.77-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div><p class="text-sm font-bold">Terdapat kesalahan pada formulir:</p><ul class="mt-1 list-inside list-disc text-xs">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif
        @if($registration?->review_notes)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-sm font-bold text-amber-900">Catatan petugas</p><p class="mt-1 text-sm text-amber-800">{{ $registration->review_notes }}</p></div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            {{-- PANEL KIRI: INPUT MAHASISWA DAN UNGGAH DOKUMEN --}}
            <div class="space-y-5 lg:col-span-7">
                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title flex items-center gap-2 text-sm font-bold text-slate-800"><span class="h-2 w-2 rounded-full bg-[#DA2D3B]"></span>Data Dasar & Informasi Kontak</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="form-section-body space-y-4" x-show="open" x-transition>
                        <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-3">
                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div><p class="text-xs font-semibold text-rose-800">Data mahasiswa</p><p class="mt-0.5 text-xs text-rose-700">Identitas diambil dari data akademik. Isi atau perbarui nomor kontak yang dapat dihubungi.</p></div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <div><label class="campus-label !mb-0">No. HP Mahasiswa <span class="text-rose-500">*</span></label><input class="campus-input" name="student_phone" value="{{ old('student_phone', $registration->student_phone ?? $student?->phone_number) }}" placeholder="08xxxxxxxxxx" required maxlength="30" inputmode="tel">@error('student_phone')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror</div>
                            <div><label class="campus-label !mb-0">No. HP Orang Tua/Wali <span class="text-rose-500">*</span></label><input class="campus-input" name="parent_phone" value="{{ old('parent_phone', $registration->parent_phone ?? $student?->parent_phone_number) }}" placeholder="08xxxxxxxxxx" required maxlength="30" inputmode="tel">@error('parent_phone')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror</div>
                        </div>
                    </div>
                </div>

                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title flex items-center gap-2 text-sm font-bold text-slate-800"><span class="h-2 w-2 rounded-full bg-[#DA2D3B]"></span>Verifikasi iGracias & Akademik</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="form-section-body" x-show="open" x-transition>
                        <p class="mb-4 text-xs text-slate-500">Unggah capture dari iGracias untuk mendukung verifikasi data yang ditampilkan di panel kanan. Format PDF/JPG/PNG, maksimal 10 MB per file.</p>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach(['level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture', 'ta_data_capture', 'online_supervision_capture', 'skpi_tak_capture'] as $type)
                                @include('sidang_ta_registrations.partials.upload', ['type' => $type, 'registration' => $registration])
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title flex items-center gap-2 text-sm font-bold text-slate-800"><span class="h-2 w-2 rounded-full bg-[#DA2D3B]"></span>Pembayaran & Persyaratan Dokumen</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="form-section-body" x-show="open" x-transition>
                        <p class="mb-4 text-xs text-slate-500">Format PDF/JPG/PNG, ukuran maksimal 10 MB per file. Ketentuan khusus ditampilkan pada masing-masing dokumen.</p>
                        <div class="mb-4 max-w-sm">
                            <label class="campus-label">Tanggal Pembayaran Wisuda <span class="text-rose-500">*</span></label>
                            <input class="campus-input" type="date" name="graduation_payment_date" max="{{ now()->toDateString() }}" value="{{ old('graduation_payment_date', $registration?->graduation_payment_date?->format('Y-m-d')) }}" required>
                            @error('graduation_payment_date')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach(['graduation_payment_proof', 'bpp_payment_proof', 'ta_supervisor_sk', 'eprt_lac_certificate', 'thesis_draft', 'transcript_khs', 'ta_course_ksm'] as $type)
                                @include('sidang_ta_registrations.partials.upload', ['type' => $type, 'registration' => $registration])
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="form-section" x-data="{ open: true }">
                    <div class="form-section-header" @click="open = !open">
                        <span class="form-section-title flex items-center gap-2 text-sm font-bold text-slate-800"><span class="h-2 w-2 rounded-full bg-[#DA2D3B]"></span>Verifikasi Biodata Ijazah</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                    <div class="form-section-body space-y-4" x-show="open" x-transition>
                        <p class="text-xs font-semibold text-slate-700">Apakah form biodata dummy ijazah sudah sesuai?</p>
                        <div class="flex flex-wrap gap-6 text-xs text-slate-700">
                            <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name="diploma_biodata_accurate" value="1" x-model="accurate" required class="text-[#DA2D3B] focus:ring-[#DA2D3B]">Sudah sesuai</label>
                            <label class="inline-flex cursor-pointer items-center gap-2"><input type="radio" name="diploma_biodata_accurate" value="0" x-model="accurate" class="text-[#DA2D3B] focus:ring-[#DA2D3B]">Belum sesuai, sudah mengajukan PDM</label>
                        </div>
                        <div x-show="accurate === '1'" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @include('sidang_ta_registrations.partials.upload', ['type' => 'diploma_statement', 'registration' => $registration, 'conditional' => '1'])
                            @include('sidang_ta_registrations.partials.upload', ['type' => 'diploma_biodata_form', 'registration' => $registration, 'conditional' => '1'])
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.upload', ['type' => 'pdm_submission_proof', 'registration' => $registration, 'conditional' => '0'])
                        </div>
                    </div>
                </div>

                <div x-show="!reviewing" class="flex flex-col-reverse justify-between gap-3 border-t border-slate-200 bg-white/95 py-4 sm:flex-row sm:items-center">
                    <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
                    <button type="button" @click="if ($el.closest('form').reportValidity()) reviewing = true" class="btn btn-primary">Tinjau Sebelum Kirim</button>
                </div>
                <section x-show="reviewing" x-cloak class="campus-card border-[#DA2D3B]/20 p-5 shadow-md">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#DA2D3B]">Konfirmasi Pengajuan</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-800">Review & Kirim Pendaftaran</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Pastikan data dan dokumen sudah sesuai. Setelah dikirim, petugas akan meninjau pengajuan Anda.</p>
                    <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs sm:grid-cols-2">
                        <p>Nama: <strong>{{ $academic['student_name'] }}</strong></p><p>NIM: <strong>{{ $academic['student_number'] }}</strong></p>
                        <p>Periode: <strong>{{ $period->name }}</strong></p><p>Program studi: <strong>{{ $academic['study_program'] }}</strong></p>
                        <p>Kontak mahasiswa: <strong x-text="$el.closest('form').querySelector('[name=student_phone]').value"></strong></p>
                        <p>Kontak orang tua/wali: <strong x-text="$el.closest('form').querySelector('[name=parent_phone]').value"></strong></p>
                    </div>
                    <div class="mt-5 flex flex-col-reverse justify-between gap-3 sm:flex-row"><button type="button" @click="reviewing = false" class="btn btn-secondary">Kembali ke Form</button><button type="submit" class="btn btn-primary">Kirim Pendaftaran</button></div>
                </section>
            </div>

            {{-- PANEL KANAN: DATA OTOMATIS, MENIRU PANEL FORM YUDISIUM --}}
            <aside class="space-y-5 lg:sticky lg:top-6 lg:col-span-5">
                <section class="campus-card space-y-3 p-4">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Data Mahasiswa</h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach(['NIM' => $academic['student_number'] ?? '-', 'Program Studi' => $academic['study_program'] ?? '-', 'Semester Aktif' => $academic['semester'] ?? '-', 'Email Kampus' => $academic['student_email'] ?? '-'] as $label => $value)
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-2.5"><span class="block text-[10px] font-semibold uppercase text-slate-500">{{ $label }}</span><span class="mt-1 block break-words font-medium text-slate-800">{{ $value ?: '-' }}</span></div>
                        @endforeach
                    </div>
                    <p class="text-[10px] text-slate-400">Sumber data terakhir diperbarui: {{ $academic['academic_source_updated_at'] ?? 'Belum tersedia' }}</p>
                </section>

                <section class="campus-card space-y-3 p-4">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Judul Tugas Akhir (Resmi)</h4>
                    <div class="space-y-2.5 text-xs">
                        <div><span class="mb-1 block text-[11px] font-semibold text-slate-500">Bahasa Indonesia:</span><div class="rounded-lg border border-slate-200/80 bg-slate-50 p-2.5 font-medium leading-relaxed text-slate-800">{{ $academic['ta_title'] ?: '-' }}</div></div>
                        <div><span class="mb-1 block text-[11px] font-semibold text-slate-500">Bahasa Inggris:</span><div class="rounded-lg border border-slate-200/80 bg-slate-50 p-2.5 italic leading-relaxed text-slate-700">{{ $academic['ta_title_en'] ?: '-' }}</div></div>
                    </div>
                </section>

                <section class="campus-card space-y-3 p-4">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Pembimbing & Penguji</h4>
                    <div class="space-y-3 text-xs">
                        <div><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Dosen Pembimbing</span><div class="space-y-1">@foreach(['Pembimbing 1' => $academic['advisor_1'], 'Pembimbing 2' => $academic['advisor_2']] as $label => $value)<div class="flex justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"><span class="text-slate-500">{{ $label }}</span><span class="text-right font-medium text-slate-700">{{ $value }}</span></div>@endforeach</div></div>
                        <div><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Dosen Penguji</span><div class="space-y-1">@foreach(['Penguji 1' => $academic['examiner_1'], 'Penguji 2' => $academic['examiner_2'], 'Penguji 3' => $academic['examiner_3']] as $label => $value)<div class="flex justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"><span class="text-slate-500">{{ $label }}</span><span class="text-right font-medium text-slate-700">{{ $value }}</span></div>@endforeach</div></div>
                    </div>
                </section>

                <section class="campus-card space-y-3 p-4">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Ringkasan Akademik</h4>
                    <div class="grid grid-cols-2 gap-2 text-center text-xs">
                        @foreach(['IPK' => $academic['gpa'] ?? 'Belum tersedia', 'SKS Lulus' => $academic['passed_credits'] ?? 'Belum tersedia', 'TAK' => $academic['tak_score'] ?? 'Belum tersedia', 'EPrT' => $academic['eprt_score'] ?? 'Belum tersedia', 'Sidang Tingkat 2' => $academic['sidang_tingkat_2'], 'Sidang Tingkat 3' => $academic['level_3_status'], 'Status BPP' => $academic['bpp_status'], 'Status SKPI' => $academic['skpi_status'], 'Status i-Basila' => $academic['ibasila_status']] as $label => $value)
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5"><span class="block text-[10px] text-slate-500">{{ $label }}</span><span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ in_array(strtolower((string) $value), ['lulus', 'disetujui', 'approved', 'lunas', 'paid'], true) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $value }}</span></div>
                        @endforeach
                    </div>
                </section>

                <section class="campus-card space-y-3 p-4">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Jadwal Sidang TA</h4>
                    <div class="space-y-1 text-xs">
                        @foreach(['Tanggal' => $academic['exam_date'] ?? '-', 'Waktu' => $academic['exam_time'] ?? '-', 'Lokasi' => $academic['exam_location'] ?? '-'] as $label => $value)
                            <div class="flex justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"><span class="text-slate-500">{{ $label }}</span><span class="text-right font-medium text-slate-700">{{ $value ?: '-' }}</span></div>
                        @endforeach
                    </div>
                </section>

            </aside>
        </div>
    </form>
</x-app-layout>
