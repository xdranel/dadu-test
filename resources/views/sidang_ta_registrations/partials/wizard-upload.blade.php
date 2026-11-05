@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} <span class="text-rose-600" aria-hidden="true">*</span>
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · Pilih berkas baru hanya untuk mengganti.
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            Berkas tersimpan tidak ditemukan. Unggah kembali.
        </p>
    @endif
    <div
        class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
        :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
        @dragenter.prevent.stop="enter()" @dragover.prevent.stop
        @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
        <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
               accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
               class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
               aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
               aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
               :aria-invalid="hasError() ? 'true' : 'false'"
               @change="picked()"
               @if($conditional !== null)
                   @disabled($accurate !== $conditional)
                   :disabled="accurate !== '{{ $conditional }}'"
               :required="accurate === '{{ $conditional }}' && {{ $available ? 'false' : 'true' }}"
        @else
            @required(!$available)
            @endif>
        <div class="pointer-events-none py-3 text-center">
            <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                      d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
            </svg>
            <p class="mt-2 text-sm font-medium text-slate-700">
                Klik untuk memilih atau seret file ke sini
            </p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas
            </p>
        </div>
    </div>
    <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs font-semibold text-amber-800">
            Pilihan baru · Belum disimpan
        </p>
        <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
        <p class="text-xs text-slate-500" x-text="fileSize"></p>
        <button type="button" class="btn btn-secondary text-xs" @click="clear()">
            Batalkan pilihan baru
        </button>
    </div>
    <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
       class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
        <span x-show="localError" x-text="localError"></span>
        <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
    </p>
</div>
