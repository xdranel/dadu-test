export default (initial = {}) => ({
    accurate: initial.accurate ?? '',
    dirty: initial.dirty ?? false,
    busy: false,
    periodHint: initial.periodHint ?? '',
    init() {
        this.$nextTick(() => {
            const viewport = this.$root.querySelector('[data-progress-scroll]');
            const active = this.$root.querySelector('[aria-current="step"]');
            if (viewport && active) {
                viewport.scrollLeft = Math.max(0,
                    viewport.scrollLeft + active.getBoundingClientRect().left
                    - viewport.getBoundingClientRect().left
                    - (viewport.clientWidth - active.offsetWidth) / 2);
            }
            if (!initial.hasErrors) return;
            const field = [...this.$root.querySelectorAll('[aria-invalid="true"]')]
                .find((element) => !element.disabled && element.getClientRects().length > 0);
            const target = field ?? this.$root.querySelector('[data-error-summary]');
            target?.focus();
        });
    },
    updatePeriodHint(select) {
        this.periodHint = select.selectedOptions[0]?.dataset.window ?? '';
    },
    leave(event) {
        if (!this.dirty) return;
        if (!window.confirm('Perubahan belum disimpan. Tetap tinggalkan halaman?')) {
            event.preventDefault();
            return;
        }
        this.dirty = false;
    },
});

export const sidangTaUpload = (initial = {}) => ({
    fileName: '',
    fileSize: '',
    dragDepth: 0,
    localError: '',
    serverError: initial.serverError ?? false,
    selectionChanged: false,
    hasError() {
        return this.localError !== '' || (this.serverError && !this.selectionChanged);
    },
    enter() {
        if (!this.$refs.file.disabled) this.dragDepth++;
    },
    leaveDrop() {
        this.dragDepth = Math.max(0, this.dragDepth - 1);
    },
    picked() {
        if (this.$refs.file.disabled) return;
        const file = this.$refs.file.files[0];
        this.fileName = file?.name ?? '';
        this.fileSize = file ? `${(file.size / 1024 / 1024).toFixed(2)} MB` : '';
        this.localError = '';
        this.selectionChanged = true;
        this.$dispatch('sidang-ta-file-changed');
    },
    drop(event) {
        this.dragDepth = 0;
        if (this.$refs.file.disabled) return;
        const files = event.dataTransfer?.files;
        if (!files || files.length !== 1) {
            this.localError = 'Seret tepat satu berkas. Pilihan sebelumnya tetap dipertahankan.';
            return;
        }
        try {
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            this.$refs.file.files = transfer.files;
        } catch {
            this.localError = 'Drag & drop belum didukung. Klik kotak untuk memilih berkas.';
            return;
        }
        this.picked();
    },
    clear() {
        if (this.$refs.file.disabled) return;
        this.$refs.file.value = '';
        this.fileName = '';
        this.fileSize = '';
        this.localError = '';
        this.selectionChanged = true;
        this.$dispatch('sidang-ta-file-changed');
    },
});

export const sidangTaDocumentPreview = () => ({
    previewUrl: '',
    downloadUrl: '',
    fileName: '',
    isPdf: false,
    open(data) {
        this.previewUrl = data.previewUrl;
        this.downloadUrl = data.downloadUrl;
        this.fileName = data.fileName;
        this.isPdf = String(data.fileType).toLowerCase() === 'pdf';
        if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
    },
    close() {
        this.$refs.dialog.close();
    },
    reset() {
        this.previewUrl = '';
        this.downloadUrl = '';
        this.fileName = '';
        this.isPdf = false;
    },
});
