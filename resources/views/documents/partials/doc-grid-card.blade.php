{{--
    Partial: doc-grid-card.blade.php
    Usage: @include('documents.partials.doc-grid-card', ['dok' => $dok, 'mode' => 'admin'|'public'])
    Variables:
        $dok  — Document model
        $mode — 'admin' | 'public'
--}}
@php
    $isAdmin  = ($mode ?? 'public') === 'admin';
    $ext      = strtolower($dok->file_type ?? '');

    // Icon color + label per tipe file
    $iconData = match(true) {
        in_array($ext, ['pdf'])                     => ['color' => '#E53935', 'bg' => '#FEE2E2', 'label' => 'PDF'],
        in_array($ext, ['doc', 'docx'])             => ['color' => '#1565C0', 'bg' => '#DBEAFE', 'label' => strtoupper($ext)],
        in_array($ext, ['xls', 'xlsx'])             => ['color' => '#1B5E20', 'bg' => '#DCFCE7', 'label' => strtoupper($ext)],
        in_array($ext, ['ppt', 'pptx'])             => ['color' => '#D84315', 'bg' => '#FEE8D6', 'label' => strtoupper($ext)],
        in_array($ext, ['zip', 'rar', '7z'])        => ['color' => '#546E7A', 'bg' => '#F1F5F9', 'label' => strtoupper($ext)],
        default                                      => ['color' => '#64748B', 'bg' => '#F1F5F9', 'label' => strtoupper($ext) ?: 'FILE'],
    };
    $iconColor = $iconData['color'];
    $iconBg    = $iconData['bg'];
    $iconLabel = $iconData['label'];
@endphp

<div class="doc-tile group relative bg-white rounded-xl border border-slate-200/70 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 flex flex-col overflow-hidden cursor-pointer"
     :class="{ 'ring-2 ring-[#DA2D3B] border-[#DA2D3B]/60': isSelected('doc', {{ $dok->id }}) }">

    {{-- Checkbox (admin only) — pojok kiri-atas, nyaman diklik dan kontras jelas --}}
    @if($isAdmin)
    <label class="absolute top-1.5 left-1.5 z-10 flex items-center justify-center p-1.5 cursor-pointer rounded-lg
                  transition-opacity duration-150"
           :class="selectionActive || isSelected('doc', {{ $dok->id }}) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
           @click.stop>
        <input type="checkbox"
               class="sr-only peer"
               :checked="isSelected('doc', {{ $dok->id }})"
               @change="toggleItem({
                   type: 'doc',
                   id: {{ $dok->id }},
                   name: @js($dok->title),
                   updateUrl: @js(route('documents.update', $dok)),
                   showUrl: @js(route('documents.show', $dok)),
                   downloadUrl: @js(route('documents.download', $dok)),
                   abbreviation: @js($dok->abbreviation),
                   description: @js($dok->description),
                   visibility: @js($dok->visibility),
                   categoryId: {{ $dok->document_category_id ?? 'null' }},
                   fileType: @js(strtoupper($dok->file_type)),
                   fileSize: @js($dok->file_size_formatted),
                   createdAt: @js($dok->created_at->translatedFormat('d M Y')),
                   downloadCount: {{ $dok->download_count }}
               })"
               aria-label="Pilih dokumen {{ $dok->title }}">
        <span class="w-5 h-5 flex items-center justify-center rounded-md border-2 shadow-sm transition-all duration-150"
              :class="isSelected('doc', {{ $dok->id }}) ? 'bg-[#DA2D3B] border-[#DA2D3B]' : 'bg-white border-slate-300 hover:border-[#DA2D3B]'">
            <svg class="w-3.5 h-3.5 transition-all duration-150"
                 :class="isSelected('doc', {{ $dok->id }}) ? 'text-white opacity-100 scale-100' : 'text-slate-300 hover:text-slate-400 opacity-90 scale-95'"
                 viewBox="0 0 12 10" fill="none" aria-hidden="true">
                <path d="M1.5 5.2l3 3 6-6.5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </label>
    @endif

    {{-- Thumbnail / Icon Area --}}
    <a href="{{ $isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok) }}"
       class="block flex-shrink-0 w-full flex items-center justify-center py-5 transition-colors duration-200"
       style="background-color: {{ $iconBg }};"
       title="{{ $dok->title }}"
       tabindex="-1"
       @click.prevent="selectionActive
           ? toggleItem({
                 type: 'doc',
                 id: {{ $dok->id }},
                 name: @js($dok->title),
                 updateUrl: @js($isAdmin ? route('documents.update', $dok) : '#'),
                 showUrl: @js($isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok)),
                 downloadUrl: @js($isAdmin ? route('documents.download', $dok) : '#'),
                 abbreviation: @js($dok->abbreviation),
                 description: @js($dok->description),
                 visibility: @js($dok->visibility),
                 categoryId: {{ $dok->document_category_id ?? 'null' }},
                 fileType: @js(strtoupper($dok->file_type)),
                 fileSize: @js($dok->file_size_formatted),
                 createdAt: @js($dok->created_at->translatedFormat('d M Y')),
                 downloadCount: {{ $dok->download_count }}
             })
           : (window.location.href = @js($isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok)))">

        {{-- File type icon (SVG) --}}
        @if(in_array($ext, ['pdf']))
            {{-- PDF icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1v12h12" fill="{{ $iconColor }}" opacity="0.15"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="4" y="41" fill="white" font-size="8.5" font-weight="700" font-family="sans-serif" letter-spacing="0.3">PDF</text>
            </svg>
        @elseif(in_array($ext, ['doc', 'docx']))
            {{-- Word icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1l12 12H27V1Z" fill="{{ $iconColor }}" opacity="0.35"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="2" y="41" fill="white" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.2">DOCX</text>
            </svg>
        @elseif(in_array($ext, ['xls', 'xlsx']))
            {{-- Excel icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1l12 12H27V1Z" fill="{{ $iconColor }}" opacity="0.35"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="2" y="41" fill="white" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.2">XLSX</text>
            </svg>
        @elseif(in_array($ext, ['ppt', 'pptx']))
            {{-- PowerPoint icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1l12 12H27V1Z" fill="{{ $iconColor }}" opacity="0.35"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="2" y="41" fill="white" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.2">PPTX</text>
            </svg>
        @elseif(in_array($ext, ['zip', 'rar', '7z']))
            {{-- Archive icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1l12 12H27V1Z" fill="{{ $iconColor }}" opacity="0.35"/>
                {{-- Zipper lines --}}
                <rect x="16" y="8" width="8" height="3" rx="1" fill="{{ $iconColor }}" opacity="0.4"/>
                <rect x="16" y="13" width="8" height="3" rx="1" fill="{{ $iconColor }}" opacity="0.4"/>
                <rect x="16" y="18" width="8" height="3" rx="1" fill="{{ $iconColor }}" opacity="0.4"/>
                <rect x="16" y="23" width="8" height="3" rx="1" fill="{{ $iconColor }}" opacity="0.6"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="5" y="41" fill="white" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.2">ZIP</text>
            </svg>
        @else
            {{-- Generic file icon --}}
            <svg width="48" height="56" viewBox="0 0 48 56" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="38" height="48" rx="4" fill="white" stroke="{{ $iconColor }}" stroke-width="2"/>
                <path d="M27 1l12 12H27V1Z" fill="{{ $iconColor }}" opacity="0.35"/>
                <rect x="0" y="30" width="40" height="14" rx="2" fill="{{ $iconColor }}"/>
                <text x="4" y="41" fill="white" font-size="8" font-weight="700" font-family="sans-serif" letter-spacing="0.2">{{ $iconLabel }}</text>
            </svg>
        @endif
    </a>

    {{-- Info area --}}
    <div class="px-2.5 py-2 flex flex-col gap-0.5 flex-1">
        <a href="{{ $isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok) }}"
           class="block text-xs font-semibold text-slate-800 leading-snug truncate hover:text-[#DA2D3B] transition-colors"
           title="{{ $dok->title }}"
           @click.prevent="selectionActive
               ? toggleItem({
                     type: 'doc',
                     id: {{ $dok->id }},
                     name: @js($dok->title),
                     updateUrl: @js($isAdmin ? route('documents.update', $dok) : '#'),
                     showUrl: @js($isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok)),
                     downloadUrl: @js($isAdmin ? route('documents.download', $dok) : '#'),
                     abbreviation: @js($dok->abbreviation),
                     description: @js($dok->description),
                     visibility: @js($dok->visibility),
                     categoryId: {{ $dok->document_category_id ?? 'null' }},
                     fileType: @js(strtoupper($dok->file_type)),
                     fileSize: @js($dok->file_size_formatted),
                     createdAt: @js($dok->created_at->translatedFormat('d M Y')),
                     downloadCount: {{ $dok->download_count }}
                 })
               : (window.location.href = @js($isAdmin ? route('documents.show', $dok) : route('public_documents.show', $dok)))">
            {{ $dok->title }}
        </a>
        @if(isset($showCategory) && $showCategory && $dok->category)
            <span class="text-[10px] text-slate-400 font-medium truncate">{{ $dok->category->name }}</span>
        @endif
        <span class="text-[10px] text-slate-400 mt-0.5">{{ $dok->created_at->translatedFormat('d M Y') }}</span>
    </div>

</div>
