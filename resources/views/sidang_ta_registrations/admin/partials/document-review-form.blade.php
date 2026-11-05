@if($reviewable && in_array($document->document_type, $requiredTypes, true))
    @php
        $oldForm = old('_review_form') === ('document:'.$document->id);
        $rawNotes = $oldForm ? old('notes') : $document->notes;
        $notesValue = is_string($rawNotes) ? $rawNotes : '';
        $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
        $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    @endphp
    <form data-doc-review-form="{{ $document->id }}" method="POST" novalidate
          action="{{ route('admin.sidang-ta-registrations.documents.review', ['sidang_ta_registration' => $registration, 'document' => $document]) }}"
          class="w-full space-y-3 border-t border-slate-100 pt-3">
        @csrf
        <input type="hidden" name="_review_form" value="document:{{ $document->id }}">
        <input type="hidden" name="registration_version" value="{{ $versionValue }}">
        <label class="block text-xs font-semibold text-slate-600" for="document-notes-{{ $document->id }}">
            Catatan dokumen (wajib jika meminta perbaikan)
        </label>
        <textarea id="document-notes-{{ $document->id }}" name="notes" maxlength="2000" class="campus-input min-h-20">{{ $notesValue }}</textarea>
        @if($oldForm && $errors->has('notes'))
            <p role="alert" class="text-xs text-rose-700">
                {{ $errors->first('notes') }}
            </p>
        @endif
        <div class="flex flex-wrap justify-end gap-2">
            <button data-doc-reject type="submit" name="status" value="rejected" class="btn btn-secondary">
                Minta perbaikan
            </button>
            <button data-doc-verify type="submit" name="status" value="verified"
                    @disabled(!$available || $document->status === 'verified')
                    class="btn btn-success disabled:cursor-not-allowed disabled:opacity-50">
                Verifikasi
            </button>
        </div>
    </form>
@endif
