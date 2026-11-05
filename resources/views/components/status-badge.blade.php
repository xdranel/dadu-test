@props(['status', 'label' => null])

@php
    // Map status to design tokens
    $statusConfig = match($status) {
        'submitted' => [
            'class' => 'bg-status-submitted-bg text-status-submitted-text ring-1 ring-status-submitted-border',
            'label' => $label ?? 'Diajukan'
        ],
        'needs_review' => [
            'class' => 'bg-status-needsReview-bg text-status-needsReview-text ring-1 ring-status-needsReview-border',
            'label' => $label ?? 'Perlu Revisi'
        ],
        'approved' => [
            'class' => 'bg-status-approved-bg text-status-approved-text ring-1 ring-status-approved-border',
            'label' => $label ?? 'Disetujui'
        ],
        'rejected' => [
            'class' => 'bg-status-rejected-bg text-status-rejected-text ring-1 ring-status-rejected-border',
            'label' => $label ?? 'Ditolak'
        ],
        'cancelled' => [
            'class' => 'bg-status-cancelled-bg text-status-cancelled-text ring-1 ring-status-cancelled-border',
            'label' => $label ?? 'Dibatalkan'
        ],
        default => [
            'class' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-300',
            'label' => $label ?? ucfirst($status)
        ]
    };
@endphp

<span {{ $attributes->merge(['class' => 'px-2.5 py-1 inline-flex items-center text-xs font-semibold rounded-md ' . $statusConfig['class']]) }}>
    {{ $statusConfig['label'] }}
</span>
