@props(['type' => 'pdf', 'size' => 'md'])
@php
    $type = strtolower($type);
    $map = [
        'pdf'  => ['bg-red-50',     'text-red-600',     'PDF'],
        'doc'  => ['bg-blue-50',    'text-blue-600',    'DOC'],
        'docx' => ['bg-blue-50',    'text-blue-600',    'DOC'],
        'xls'  => ['bg-emerald-50', 'text-emerald-600', 'XLS'],
        'xlsx' => ['bg-emerald-50', 'text-emerald-600', 'XLS'],
        'ppt'  => ['bg-orange-50',  'text-orange-600',  'PPT'],
        'pptx' => ['bg-orange-50',  'text-orange-600',  'PPT'],
        'zip'  => ['bg-amber-50',   'text-amber-600',   'ZIP'],
        'rar'  => ['bg-amber-50',   'text-amber-600',   'RAR'],
    ];
    [$bg, $fg, $label] = $map[$type] ?? ['bg-slate-100', 'text-slate-500', strtoupper(substr($type, 0, 3))];
    $box = $size === 'lg' ? 'w-14 h-14 text-xs' : ($size === 'sm' ? 'w-8 h-8 text-[9px]' : 'w-11 h-11 text-[10px]');
@endphp
<div {{ $attributes->merge(['class' => "$box $bg $fg rounded-xl flex items-center justify-center font-extrabold tracking-wide flex-shrink-0"]) }}>
    {{ $label }}
</div>
