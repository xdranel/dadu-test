@props(['label' => 'Menu aksi'])
<div class="relative" @keydown.escape.window="open = false">
    <button type="button" @click.stop.prevent="open = !open"
            aria-label="{{ $label }}" title="{{ $label }}" aria-haspopup="menu" :aria-expanded="open"
            :class="open ? 'opacity-100 bg-slate-100' : 'opacity-0 group-hover:opacity-100 focus:opacity-100 [@media(hover:none)]:opacity-100'"
            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 transition-opacity">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false" @click.stop role="menu"
         class="absolute right-0 top-9 z-30 w-56 bg-white rounded-xl border border-slate-200 shadow-lg py-1.5">
        {{ $slot ?? '' }}
    </div>
</div>
