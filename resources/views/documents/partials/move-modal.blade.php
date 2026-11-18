<div x-show="showMove" x-cloak x-transition
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4"
     @keydown.escape.window="showMove = false"
     style="display: none;">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md border border-slate-200"
         @click.away="showMove = false"
         role="dialog" aria-modal="true" aria-labelledby="move-title">
        <div class="p-6 pb-3">
            <h3 id="move-title" class="text-lg font-bold text-slate-800">
                Pindahkan <span class="text-[#DA2D3B]" x-text="moveItems.length > 1 ? moveItems.length + ' item' : (moveItems[0]?.name || '')"></span>
            </h3>
            <input type="text"
                   x-model.debounce.300ms="moveQuery"
                   @input.debounce.300ms="searchTargets()"
                   placeholder="Cari folder..."
                   class="campus-input w-full mt-3">
        </div>

        <div class="px-3 max-h-80 overflow-y-auto" role="tree">
            {{-- Mode pencarian: daftar datar dengan path lengkap --}}
            <template x-if="moveQuery.trim()">
                <div>
                    <template x-for="t in searchResults" :key="t.id">
                        <button type="button"
                                @click="!t.disabled && (moveTarget = t.id)"
                                :disabled="t.disabled"
                                :title="t.reason || t.path"
                                :class="moveTarget === t.id ? 'bg-[#DA2D3B]/10 text-[#DA2D3B] font-semibold' : (t.disabled ? 'text-slate-400' : 'hover:bg-slate-50 text-slate-700')"
                                class="w-full text-left px-3 py-1.5 rounded-lg text-sm truncate disabled:cursor-not-allowed"
                                x-text="t.path"></button>
                    </template>
                    <p x-show="!searchResults.length" class="px-3 py-3 text-xs text-slate-400">Folder tidak ditemukan.</p>
                </div>
            </template>

            {{-- Mode pohon --}}
            <template x-if="!moveQuery.trim()">
                <div>
                    <template x-for="t in rows" :key="t.id">
                        <div class="flex items-center" :style="`padding-left: ${Math.min(t.depth, 10) * 1.1}rem`">
                            <button type="button"
                                    class="w-6 h-8 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors"
                                    x-show="t.has_children"
                                    @click="toggleExpand(t.id)"
                                    :aria-label="'Buka/tutup ' + t.name">
                                <svg class="w-3.5 h-3.5 transition-transform duration-150"
                                     :class="expanded.has(t.id) ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <span x-show="!t.has_children" class="w-6"></span>
                            <button type="button"
                                    @click="!t.disabled && (moveTarget = t.id)"
                                    :disabled="t.disabled"
                                    :title="t.reason || t.name"
                                    role="treeitem"
                                    :class="moveTarget === t.id ? 'bg-[#DA2D3B]/10 text-[#DA2D3B] font-semibold' : (t.disabled ? 'text-slate-400' : 'hover:bg-slate-50 text-slate-700')"
                                    class="flex-1 min-w-0 flex items-center gap-2 px-2.5 py-1.5 rounded-lg text-sm text-left disabled:cursor-not-allowed">
                                <svg class="w-4 h-4 text-[#DA2D3B] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                </svg>
                                <span class="truncate" x-text="t.name"></span>
                                <span x-show="t.disabled" class="ml-auto text-[10px] text-slate-400 flex-shrink-0">tidak tersedia</span>
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <p x-show="moveError" x-cloak class="px-6 pt-3 text-xs text-rose-600 font-medium" x-text="moveError"></p>
        <div class="flex justify-end gap-3 p-6 pt-4">
            <button type="button" @click="showMove = false" class="btn btn-secondary">Batal</button>
            <button type="button"
                    @click="submitMove()"
                    :disabled="moveLoading || moveTarget === undefined"
                    class="btn btn-primary disabled:opacity-50">
                <span x-text="moveLoading ? 'Memindahkan...' : 'Pindahkan ke sini'"></span>
            </button>
        </div>
    </div>
</div>
