@props([
    'columns' => [],
    'data' => [],
    'emptyMessage' => 'Belum Ada Data',
    'emptyDescription' => 'Tidak ada data untuk ditampilkan.',
    'statusColumn' => 'status',
    'rowAccent' => true,
])

@php
    // Helper function to get row accent color based on status
    function getRowAccent($status, $enabled) {
        if (!$enabled) return '';
        
        return match($status) {
            'needs_review' => 'border-l-4 border-status-needsReview-accent',
            default => ''
        };
    }
@endphp

<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-body border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-label">
                    @foreach($columns as $column)
                        <th scope="col" class="py-3.5 px-4">
                            {{ $column['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($data as $index => $row)
                    <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50' }} hover:bg-slate-50 transition-colors {{ getRowAccent($row[$statusColumn] ?? '', $rowAccent) }}">
                        @foreach($columns as $column)
                            <td class="py-3.5 px-4 {{ $column['class'] ?? '' }}">
                                @if(isset($column['render']))
                                    {!! $column['render']($row) !!}
                                @else
                                    {{ data_get($row, $column['field']) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="py-12 px-4 text-center">
                            <p class="text-body font-semibold text-slate-600 mb-1">{{ $emptyMessage }}</p>
                            <p class="text-caption text-slate-400">{{ $emptyDescription }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($pagination) && $pagination)
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-caption text-slate-500">
            {{ $pagination }}
        </div>
    @endif
</div>
