@php
    $sourceDocuments = $registration->relationLoaded('documents') ? $registration->documents : $registration->documents()->get();
    $feedback = [];
    foreach(\App\Support\SidangTaReviewEligibility::requiredTypes($registration) as $type) {
        $sourceDocument = $sourceDocuments->firstWhere('document_type', $type);
        $missingFile = $sourceDocument && !\Illuminate\Support\Facades\Storage::disk('local')->exists($sourceDocument->file_path);
        if (!$sourceDocument || $missingFile || $sourceDocument->status === 'rejected' || filled($sourceDocument->notes)) {
            $feedback[$type] = ['document' => $sourceDocument, 'missing_file' => $missingFile];
        }
    }
@endphp
@if($feedback !== [])
    <section data-review-feedback class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="text-sm font-bold text-amber-900">
            Catatan dokumen pengajuan sebelumnya
        </h2>
        <p class="text-xs leading-relaxed text-amber-800">
            Gunakan catatan ini untuk memperbaiki berkas. Berkas yang diganti akan ditinjau kembali setelah revisi dikirim.
        </p>
        <ul class="space-y-3">
            @foreach($feedback as $type => $item)
                @php
                    $sourceDocument = $item['document'];
                    $label = !$sourceDocument ? 'Belum ada dokumen' : ($item['missing_file'] ? 'Berkas tidak ditemukan' : (['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$sourceDocument->status] ?? $sourceDocument->status));
                    $stepNumber = 4;
                    foreach (array_values(\App\Support\SidangTaReviewEligibility::groups($registration)) as $index => $groupTypes) {
                        if (in_array($type, $groupTypes, true)) $stepNumber = $index + 2;
                    }
                @endphp
                <li data-feedback-type="{{ $type }}" class="border-t border-amber-200 pt-3 text-xs text-amber-900">
                    <p class="font-semibold">
                        {{ \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] }} · Tahap {{ $stepNumber }}
                    </p>
                    <p class="mt-1">{{ $label }}</p>
                    @if(filled($sourceDocument?->notes))
                        <p class="mt-1 whitespace-pre-line break-words">
                            {{ $sourceDocument->notes }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
