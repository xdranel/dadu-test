@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $revisionExpired = (bool) ($meetContext['revision_expired'] ?? false);
    $lockedUpload = $revisionExpired || ((bool) ($meetContext['targeted'] ?? false)
        && !in_array($type, $meetContext['editable_types'] ?? [], true));
    $replacementNeeded = (bool) ($meetContext['targeted'] ?? false) && !$lockedUpload
        && $existing && $existing->source_document_id !== null;
    $label = \App\Support\SidangTaMeetRequirements::LABELS[$type] ?? $type;
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" data-upload-locked="{{ $lockedUpload ? 'true' : 'false' }}"
     class="min-w-0 rounded-xl border border-slate-200 p-4 {{ $lockedUpload ? 'bg-slate-100 text-slate-500' : 'bg-white' }}"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} @unless($lockedUpload)<span class="text-rose-600" aria-hidden="true">*</span>@endunless
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        PDF, maksimal 10 MB per file.
    </p>
    @include('sidang_ta_guides.partials.guides', ['guides' => $guides, 'slot' => $type, 'preview' => true])
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">
                {{ number_format(($existing->file_size ?? 0) / 1024) }} KB · {{ $lockedUpload ? 'Berkas ini tetap digunakan.' : 'Pilih berkas baru hanya untuk mengganti.' }}
            </p>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">
            {{ $revisionExpired ? 'Berkas tersimpan tidak ditemukan. Batas waktu unggah pengganti telah berakhir.'
                : 'Berkas tersimpan tidak ditemukan. Unggah kembali.' }}
        </p>
    @endif
    @if($replacementNeeded)
        <p data-replacement-needed class="mt-3 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-900">
            Unggah berkas pengganti sesuai catatan petugas. Berkas pengajuan sebelumnya belum dihitung sebagai pengganti.
        </p>
    @endif
    @if($lockedUpload)
        <p data-upload-preserved class="mt-3 rounded-lg bg-slate-200/60 p-3 text-xs text-slate-600">
            {{ $revisionExpired ? 'Batas waktu revisi telah berakhir. Unggah pengganti sudah ditutup.'
                : 'Dokumen terverifikasi tetap digunakan. Pengunggahan hanya tersedia untuk berkas yang diminta diperbaiki.' }}
        </p>
    @else
        <div
            class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
            :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
            @dragenter.prevent.stop="enter()" @dragover.prevent.stop
            @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
            <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
                   accept=".pdf"
                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                   aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
                   aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
                   :aria-invalid="hasError() ? 'true' : 'false'"
                   @change="picked()"
                   @if($conditional !== null)
                       @disabled($accurate !== $conditional)
                       :disabled="accurate !== '{{ $conditional }}'"
                   :required="accurate === '{{ $conditional }}' && {{ $available && !$replacementNeeded ? 'false' : 'true' }}"
            @else
                @required(!$available || $replacementNeeded)
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
                    PDF · Maksimal 10 MB · Satu berkas
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
    @endif
</div>
