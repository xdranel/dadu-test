@php
    $reviewLabel = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$document->status] ?? $document->status;
@endphp
<div data-student-document-state="{{ $document->id }}" class="mt-2 space-y-1 text-xs">
    <p class="font-medium {{ $document->status === 'rejected' ? 'text-rose-700' : 'text-slate-600' }}">
        {{ $reviewLabel }}
    </p>
    @if(filled($document->notes))
        <p class="whitespace-pre-line break-words text-rose-700">
            {{ $document->notes }}
        </p>
    @endif
</div>
