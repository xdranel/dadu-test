@php($label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type)
@php($existing = $registration?->documents?->firstWhere('document_type', $type))
@php($pdfOnly = in_array($type, ['diploma_statement', 'diploma_biodata_form', 'pdm_submission_proof', 'eprt_lac_certificate', 'thesis_draft', 'transcript_khs', 'ta_course_ksm'], true))
<div x-data="{ fileName: '', clearFile() { this.fileName = ''; if (this.$refs.fileInput) this.$refs.fileInput.value = ''; } }">
    <div class="mb-1.5 flex items-center gap-1.5">
        <label class="campus-label !mb-0">{{ $label }} <span class="text-rose-500">*</span></label>
        <svg class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Informasi persyaratan"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    @if(!empty(\App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type]))
        <p class="mb-2 text-[11px] leading-relaxed text-slate-500">{{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] }}</p>
    @endif
    @if($existing)
        <a href="{{ route('sidang-ta-registration-documents.download', $existing) }}" class="mb-2 block truncate text-[11px] font-semibold text-emerald-700 underline">Berkas tersimpan: {{ $existing->file_name }}</a>
        <p class="mb-2 text-[10px] text-slate-400">Pilih file baru hanya jika ingin mengganti berkas.</p>
    @endif
    <div class="upload-zone relative" :class="fileName ? 'has-file' : ''">
        <input type="file" name="documents[{{ $type }}]" accept="{{ $pdfOnly ? '.pdf' : '.pdf,.jpg,.jpeg,.png' }}"
               x-ref="fileInput" @change="fileName = $event.target.files[0]?.name ?? ''"
               @if(isset($conditional)) x-bind:required="accurate === '{{ $conditional }}' && {{ $existing ? 'false' : 'true' }}" @elseif(!$existing) required @endif>
        <div x-show="!fileName" class="py-2 text-center">
            <svg class="mx-auto mb-1 h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg>
            <p class="text-xs font-medium text-slate-500">Klik untuk memilih file</p>
            <p class="mt-0.5 text-[10px] text-slate-400">{{ $pdfOnly ? 'PDF' : 'PDF, JPG, PNG' }} · Maks. 10 MB</p>
        </div>
        <div x-show="fileName" class="relative z-30 flex items-center justify-between gap-2 px-2 py-2 text-left" style="display:none;">
            <div class="flex min-w-0 items-center gap-2">
                <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="truncate text-xs font-medium text-emerald-700" x-text="fileName"></p>
            </div>
            <button type="button" @click.stop.prevent="clearFile()" class="pointer-events-auto rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Hapus file terpilih">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    @error("documents.{$type}")<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
</div>
