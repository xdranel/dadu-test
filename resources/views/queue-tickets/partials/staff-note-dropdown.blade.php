@php
    $type = $type ?? 'finished';
    $isCalled = $type === 'called';
    $initialNotes = $ticket->staff_notes ?? '';
    $initialSavedAt = ($ticket->staff_notes_updated_at ? $ticket->staff_notes_updated_at->timezone('Asia/Jakarta')->format('H:i') : '');
@endphp

@if($isCalled)
    <div x-data="calledStaffNotes({{ $ticket->id }}, @js($initialNotes), @js($initialSavedAt))"
         :data-open="open ? 'true' : 'false'"
         :data-status="status"
         class="pt-1 border-t border-red-100/50 staff-notes-container">
        <!-- Header satu baris (Button) -->
        <button type="button"
                @click="toggle()"
                :aria-expanded="open"
                class="flex items-center justify-between w-full py-1 text-left select-none text-[10px] font-semibold text-slate-600 hover:text-amber-800 transition-colors focus:outline-none focus:ring-1 focus:ring-amber-400 rounded">
            <span class="inline-flex items-center gap-1.5 min-w-0">
                <span class="truncate">Catatan Petugas</span>
                <span x-show="value || lastSaved" class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block flex-shrink-0" title="Ada catatan petugas"></span>
                <span x-show="!value && !lastSaved && !open" class="text-[10px] font-normal text-amber-600 hover:underline flex-shrink-0">+ tambah</span>
                <template x-if="!open && (status === 'dirty' || status === 'saving' || status === 'error')">
                    <span class="inline-flex items-center gap-1 text-[9px] font-medium px-1.5 py-0.5 rounded"
                          :class="status === 'error' ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                        <span class="w-1.5 h-1.5 rounded-full animate-pulse" :class="status === 'error' ? 'bg-red-500' : 'bg-amber-500'"></span>
                        <span x-text="status === 'error' ? 'Gagal' : (status === 'saving' ? 'Menyimpan...' : 'Belum tersimpan')"></span>
                    </span>
                </template>
            </span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 flex-shrink-0"
                 :class="open ? 'rotate-180 text-amber-600' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Isi Dropdown (Collapsible) -->
        <div x-show="open" x-collapse x-cloak class="mt-1.5 space-y-1.5 bg-amber-50/40 p-2 rounded-lg border border-amber-200/60">
            <div class="flex items-center justify-between text-[10px]">
                <label :for="'called-note-' + ticketId" class="font-bold text-amber-900">Catatan Petugas</label>
                <span class="text-slate-400 font-mono text-[9px]" x-text="`${(value || '').length}/500`"></span>
            </div>
            <textarea :id="'called-note-' + ticketId"
                      x-ref="textarea"
                      x-model="value"
                      @focus="onFocus()"
                      @blur="onBlur()"
                      data-ticket-id="{{ $ticket->id }}"
                      maxlength="500"
                      aria-label="Catatan Petugas"
                      placeholder="Tulis catatan petugas di sini (otomatis tersimpan)..."
                      class="w-full h-20 resize-none overflow-y-auto overscroll-contain text-base md:text-[11px] p-2 border border-amber-200 rounded-lg focus:ring-1 focus:ring-amber-500 focus:outline-none bg-white text-slate-800 placeholder:text-slate-300"></textarea>
            <div class="flex items-center justify-between text-[10px] pt-0.5">
                <div class="min-h-[16px] flex items-center">
                    <template x-if="status === 'saving'">
                        <span class="inline-flex items-center gap-1 text-slate-500 font-medium">
                            <svg class="animate-spin w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            <span>Menyimpan...</span>
                        </span>
                    </template>
                    <template x-if="status === 'saved'">
                        <span class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Tersimpan <span x-text="savedAt"></span></span>
                        </span>
                    </template>
                    <template x-if="status === 'error'">
                        <span class="inline-flex items-center gap-1 text-red-600 font-medium">
                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <template x-if="retryCount < maxRetries">
                                <span>Gagal menyimpan, mencoba lagi (<span x-text="retryCount"></span>/3)...</span>
                            </template>
                            <template x-if="retryCount >= maxRetries">
                                <span>Gagal menyimpan. <button type="button" @click="manualRetry()" class="underline font-bold text-red-700 hover:text-red-800">Coba lagi</button></span>
                            </template>
                        </span>
                    </template>
                    <template x-if="status === 'dirty'">
                        <span class="text-amber-600 italic">Belum tersimpan</span>
                    </template>
                </div>
                <div class="flex items-center justify-end gap-1.5">
                    <button type="button"
                            @click="cancel()"
                            :disabled="status === 'saving'"
                            class="py-1 px-2 text-xs md:text-[10px] font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 active:scale-95 transition-all">
                        Batal
                    </button>
                    <button type="button"
                            @click="saveManual()"
                            :disabled="status === 'saving' || (status === 'saved' && value === lastSaved)"
                            class="py-1 px-2 text-xs md:text-[10px] font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50 active:scale-95 transition-all inline-flex items-center gap-1">
                        <span x-text="status === 'saving' ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@else
    <div x-data="staffNotesCard(@js($initialNotes), {{ $ticket->id }})"
         :data-open="open ? 'true' : 'false'"
         :data-editing="editing ? 'true' : 'false'"
         class="pt-1 border-t border-emerald-100/30 staff-notes-container">
        <!-- Header satu baris (Button) -->
        <button type="button"
                @click="toggle()"
                :aria-expanded="open"
                class="flex items-center justify-between w-full py-1 text-left select-none text-[10px] font-semibold text-slate-600 hover:text-amber-800 transition-colors focus:outline-none focus:ring-1 focus:ring-amber-400 rounded">
            <span class="inline-flex items-center gap-1.5 min-w-0">
                <span class="truncate">Catatan Petugas</span>
                <span x-show="saved" class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block flex-shrink-0" title="Ada catatan petugas"></span>
                <span x-show="!saved && !open" class="text-[10px] font-normal text-amber-600 hover:underline flex-shrink-0">+ tambah</span>
            </span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 flex-shrink-0"
                 :class="open ? 'rotate-180 text-amber-600' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Isi Dropdown (Collapsible) -->
        <div x-show="open" x-collapse x-cloak class="mt-1.5 space-y-1.5">
            <!-- Mode Lihat -->
            <div x-show="!editing" class="space-y-1.5">
                <template x-if="saved">
                    <div class="bg-amber-50/70 border border-amber-200/60 rounded-lg p-2 text-amber-950 space-y-1.5">
                        <div class="text-[11px] leading-relaxed whitespace-pre-line break-words max-h-32 overflow-y-auto overscroll-contain pr-1"
                             x-text="saved">
                            {{ $ticket->staff_notes }}
                        </div>
                        <div class="flex justify-end pt-0.5 border-t border-amber-200/40">
                            <button type="button"
                                    @click="startEdit()"
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-800 hover:text-amber-950 hover:underline py-1 px-2 rounded active:scale-95 transition-all">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="!saved">
                    <div class="p-2.5 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-center">
                        <p class="text-[10px] text-slate-400 mb-1.5">Belum ada catatan untuk tiket ini</p>
                        <button type="button"
                                @click="startEdit()"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800 hover:underline py-1.5 px-2.5 rounded bg-amber-50 hover:bg-amber-100 transition-colors">
                            Tambah catatan
                        </button>
                    </div>
                </template>
            </div>

            <!-- Mode Edit -->
            <div x-show="editing" class="space-y-1.5 bg-amber-50/40 p-2 rounded-lg border border-amber-200/60">
                <div class="flex items-center justify-between text-[10px]">
                    <label :for="'staff-note-' + ticketId" class="font-bold text-amber-900">Catatan Petugas</label>
                    <span class="text-slate-400 font-mono text-[9px]" x-text="`${(value || '').length}/500`"></span>
                </div>
                <textarea :id="'staff-note-' + ticketId"
                          x-ref="textarea"
                          x-model="value"
                          maxlength="500"
                          aria-label="Catatan Petugas"
                          placeholder="Tulis catatan petugas di sini..."
                          class="w-full h-20 resize-none overflow-y-auto overscroll-contain text-base md:text-[11px] p-2 border border-amber-200 rounded-lg focus:ring-1 focus:ring-amber-500 focus:outline-none bg-white text-slate-800"></textarea>
                <template x-if="error">
                    <p class="text-[10px] text-red-600 font-medium" x-text="error"></p>
                </template>
                <div class="flex items-center justify-end gap-1.5 pt-0.5">
                    <button type="button"
                            @click="cancelEdit()"
                            :disabled="loading"
                            class="py-1.5 px-2.5 text-xs md:text-[10px] font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 active:scale-95 transition-all">
                        Batal
                    </button>
                    <button type="button"
                            @click="saveNote()"
                            :disabled="loading"
                            class="py-1.5 px-2.5 text-xs md:text-[10px] font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50 active:scale-95 transition-all inline-flex items-center gap-1">
                        <span x-text="loading ? 'Menyimpan...' : 'Simpan'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
