<div x-data="sidangTaDocumentPreview" x-on:sidang-ta-preview.window="open($event.detail)">
    <dialog data-document-preview-modal x-ref="dialog" x-on:close="reset()"
            aria-labelledby="ta-preview-title"
            class="m-auto max-h-[95vh] w-11/12 max-w-5xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl backdrop:bg-slate-900/50 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <h2 id="ta-preview-title" class="min-w-0 max-w-full break-words text-base font-bold text-slate-900" x-text="fileName">
                Preview berkas
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <a data-preview-download :href="downloadUrl" class="btn btn-secondary">
                    Unduh
                </a>
                <a data-preview-new-tab :href="previewUrl" target="_blank" rel="noopener" class="btn btn-secondary">
                    Buka di tab baru
                </a>
                <button data-preview-close type="button" autofocus x-on:click="close()" class="btn btn-primary">
                    Tutup
                </button>
            </div>
        </div>
        <div class="mt-4 min-h-40 rounded-xl bg-slate-50 p-2">
            <template x-if="previewUrl && isPdf">
                <iframe :src="previewUrl" title="Pratinjau dokumen PDF" class="h-[65vh] w-full rounded-lg border border-slate-200 bg-white"></iframe>
            </template>
            <template x-if="previewUrl && !isPdf">
                <img :src="previewUrl" :alt="fileName" class="mx-auto max-h-[65vh] max-w-full rounded-lg object-contain">
            </template>
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-500">Jika berkas tidak tampil, gunakan Buka di tab baru atau Unduh untuk memeriksanya.</p>
    </dialog>
</div>
