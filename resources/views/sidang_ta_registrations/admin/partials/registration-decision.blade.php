@php
    $revisionBlockers = $revisionBlockers ?? ['Data kelengkapan revisi belum tersedia. Muat ulang halaman.'];
    $oldForm = old('_review_form') === 'decision';
    $rawVersion = $oldForm ? old('registration_version') : $registration->lock_version;
    $versionValue = is_scalar($rawVersion) ? (string) $rawVersion : '';
    $rawNotes = $oldForm ? old('review_notes') : $registration->review_notes;
    $notesValue = is_string($rawNotes) ? $rawNotes : '';
@endphp
<form data-ta-decision method="POST" novalidate
      action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="campus-card space-y-4 p-5">
    @csrf
    <input type="hidden" name="_review_form" value="decision">
    <input type="hidden" name="registration_version" value="{{ $versionValue }}">
    <h3 class="font-bold text-slate-800">
        Keputusan petugas
    </h3>
    @if($revisionBlockers !== [])
        <div data-ta-revision-blockers role="status" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Selesaikan pemeriksaan dokumen sebelum meminta revisi.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($revisionBlockers as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if($approvalBlockers !== [])
        <div data-ta-approval-blockers
             class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">
                Setujui tersedia setelah seluruh dokumen wajib terverifikasi dan berkasnya tersedia.
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($approvalBlockers as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">
            Muat ulang pengajuan
        </a>
    @endif
    <label class="campus-label">Catatan untuk mahasiswa (wajib untuk revisi atau penolakan)
        <textarea name="review_notes" maxlength="2000" class="campus-input min-h-24">{{ $notesValue }}</textarea>
    </label>
    @if($oldForm && $errors->has('review_notes'))
        <p class="text-xs text-rose-700">
            {{ $errors->first('review_notes') }}
        </p>
    @endif
    <div class="flex flex-wrap justify-end gap-2">
        <button data-ta-request-revision type="submit"
                class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="needs_review" @disabled($revisionBlockers !== [])>
            Minta Revisi
        </button>
        <button type="submit" class="btn btn-secondary" name="action" value="rejected">
            Tolak
        </button>
        <button data-ta-approve type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                name="action" value="approved" @disabled($approvalBlockers !== [])>
            Setujui
        </button>
    </div>
</form>
