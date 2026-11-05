@foreach($types as $type)
    @php
        $applicable = in_array($type, $requiredTypes, true);
        $document = $registration->documents->firstWhere('document_type', $type);
        $available = $applicable && $document && \Illuminate\Support\Facades\Storage::disk('local')->exists($document->file_path);
        $state = !$applicable ? 'na' : (!$document ? 'missing' : (!$available ? 'unavailable' : $document->status));
        $labels = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan',
            'missing' => 'Belum ada dokumen', 'unavailable' => 'Berkas tidak ditemukan', 'na' => 'Tidak berlaku'];
        $classes = ['pending' => 'border-amber-200 bg-amber-50 text-amber-700', 'verified' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'rejected' => 'border-rose-200 bg-rose-50 text-rose-700', 'missing' => 'border-dashed border-slate-300 text-slate-400',
            'unavailable' => 'border-orange-300 bg-orange-50 text-orange-700', 'na' => 'border-slate-100 bg-slate-50 text-slate-300'];
        $stateLabel = $labels[$state] ?? 'Status belum dikenal';
        $class = $classes[$state] ?? $classes['pending'];
        $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
        $tooltip = $label.' — '.$stateLabel.($available ? ' · '.$document->file_name : '');
    @endphp
    @if($available)
        <a data-ta-doc="{{ $type }}" data-doc-state="{{ $state }}"
           href="{{ route('sidang-ta-registration-documents.preview', $document) }}"
           data-preview-url="{{ route('sidang-ta-registration-documents.preview', $document) }}"
           data-download-url="{{ route('sidang-ta-registration-documents.download', $document) }}"
           data-file-name="{{ $document->file_name }}" data-file-type="{{ $document->file_type }}"
           x-on:click.prevent="$dispatch('sidang-ta-preview', $el.dataset)" target="_blank" rel="noopener"
           aria-haspopup="dialog"
           title="{{ $tooltip }}" aria-label="{{ $tooltip }}"
           class="inline-flex h-8 w-8 items-center justify-center rounded-lg border focus:outline-none focus:ring-2 focus:ring-rose-500 {{ $class }}">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
                      d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm0 0v6h6M8 13h8M8 17h5"/>
            </svg>
        </a>
    @else
        <span data-ta-doc="{{ $type }}" data-doc-state="{{ $state }}" tabindex="0" role="img" title="{{ $tooltip }}"
              aria-label="{{ $tooltip }}"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-semibold {{ $class }}">{{ $state === 'na' ? '–' : '!' }}</span>
    @endif
@endforeach
