<dialog x-ref="dialog" @close="closeRoom()" @click.self="$refs.dialog.close()"
        aria-labelledby="room-day-title" class="m-auto w-[calc(100%_-_2rem)] max-w-4xl overflow-visible rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/55">
    <div class="max-h-[85dvh] overflow-auto rounded-2xl bg-white">
        <header class="flex items-start justify-between gap-3 border-b border-slate-200 p-4 sm:p-5">
            <div>
                <h2 id="room-day-title" class="text-lg font-bold text-slate-900" x-text="roomCode"></h2>
                <p class="text-sm text-slate-600">{{ $dateLabel }}</p>
                <p class="mt-1 text-xs text-slate-600" x-show="roomDescription" x-text="roomDescription"></p>
            </div>
            <button type="button" autofocus class="btn btn-secondary" @click="$refs.dialog.close()">Tutup</button>
        </header>

        <div class="p-4 sm:p-5">
            <p x-show="loading" role="status" class="text-sm text-slate-600">Memuat jadwal ruangan…</p>
            <p x-show="error" role="alert" x-text="error" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800"></p>
            <div x-show="!loading && !error" class="grid gap-4 sm:grid-cols-2">
                <section class="min-w-0 rounded-xl border border-slate-200 p-3 sm:p-4" aria-labelledby="room-used-title">
                    <h3 id="room-used-title" class="mb-3 text-sm font-bold text-slate-900">Waktu Terpakai</h3>
                    <p x-show="busy.length === 0" class="text-sm text-slate-600">Belum ada pemakaian tercatat.</p>
                    <ul class="max-h-64 space-y-2 overflow-y-auto">
                        <template x-for="(item, index) in busy" :key="index">
                            <li class="rounded-lg border px-3 py-2 text-sm"
                                :class="item.source === 'internal' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-950'">
                                <p class="font-semibold" x-text="item.start_time + '–' + item.end_time + ' WIB'"></p>
                                <p class="break-words" x-text="item.purpose"></p>
                                <p class="text-xs" x-text="item.kind"></p>
                                <p class="mt-1 break-words text-xs font-medium" x-text="item.identity"></p>
                            </li>
                        </template>
                    </ul>
                </section>
                <section class="min-w-0 rounded-xl border border-slate-200 p-3 sm:p-4" aria-labelledby="room-free-title">
                    <h3 id="room-free-title" class="text-sm font-bold text-slate-900">Waktu Tersedia</h3>
                    <p class="mb-3 text-xs text-slate-600">Slot minimal 1 jam · 06.30–21.30 WIB</p>
                    <p x-show="availabilityNotice" x-text="availabilityNotice" class="text-sm text-slate-600"></p>
                    <ul class="max-h-64 space-y-2 overflow-y-auto">
                        <template x-for="slot in free" :key="slot">
                            <li class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900" x-text="slot"></li>
                        </template>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</dialog>
