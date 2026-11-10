<x-modal name="sync-issues" maxWidth="2xl" focusable>
    <section role="dialog" aria-modal="true" aria-labelledby="sync-issues-title" class="p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 id="sync-issues-title" class="text-base font-bold text-slate-800">Catatan sinkronisasi</h2>
            <button type="button" class="btn btn-secondary text-xs" x-on:click="$dispatch('close-modal', 'sync-issues')">Tutup</button>
        </div>
        <p class="text-xs text-slate-500 mb-3">Record dihitung mulai 1. Baris JSON menunjukkan awal record pada berkas unggahan.</p>
        <div id="sync-issues-content" class="max-h-[65vh] overflow-y-auto space-y-3" aria-live="polite"></div>
    </section>
</x-modal>
<script>
let syncIssuesRequest = 0;
async function openSyncIssues(button) {
    const request = ++syncIssuesRequest;
    const content = document.getElementById('sync-issues-content');
    content.replaceChildren();
    content.textContent = 'Memuat catatan…';
    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'sync-issues' }));
    try {
        const response = await fetch(button.dataset.syncIssuesUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('request failed');
        const payload = await response.json();
        if (request !== syncIssuesRequest) return;
        content.replaceChildren();
        const fragment = document.createDocumentFragment();
        for (const issue of payload.issues) {
            const card = document.createElement('article');
            card.className = 'border border-slate-200 rounded-lg p-3';
            const identity = document.createElement('p');
            identity.className = 'font-semibold text-sm text-slate-800 break-words';
            identity.textContent = [issue.nim ? 'NIM ' + issue.nim : 'NIM tidak tersedia', issue.name].filter(Boolean).join(' · ');
            const position = document.createElement('p');
            position.className = 'text-xs text-slate-500 mt-1 break-words';
            position.textContent = [issue.record ? 'Record ' + issue.record : null, issue.path,
                issue.line ? 'Baris JSON ' + issue.line : 'Baris fisik tidak tersedia'].filter(Boolean).join(' · ');
            const message = document.createElement('p');
            message.className = 'text-sm text-slate-700 mt-2 whitespace-pre-wrap break-words';
            message.textContent = issue.message;
            card.append(identity, position, message);
            fragment.append(card);
        }
        content.append(fragment);
        if (payload.issues.length === 0) content.textContent = 'Selesai tanpa catatan.';
    } catch (_) {
        if (request === syncIssuesRequest) content.textContent = 'Catatan belum dapat dimuat. Tutup lalu coba lagi.';
    }
}
</script>
