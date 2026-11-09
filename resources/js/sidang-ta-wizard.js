export const validWhatsapp = (value) => typeof value === 'string' && value.length >= 8 && value.length <= 15
    && (value.startsWith('08') || value.startsWith('628')) && !/[^0-9]/.test(value);

export const sidangTaConfirmation = (blocked = false) => ({
    blocked, accepted: false, sending: false,
    get canConfirm() { return this.accepted && !this.blocked && !this.sending; },
    submit(event) {
        if (!this.canConfirm) { event.preventDefault(); return; }
        this.sending = true;
    },
});

export const periodCountdown = (openAt, closeAt, at = Date.now()) => {
    if (openAt === null || closeAt === null || !Number.isFinite(openAt) || !Number.isFinite(closeAt)) return null;
    if (at < openAt) return { status: 'Belum dibuka', seconds: Math.ceil((openAt - at) / 1000) };
    if (at > closeAt) return { status: 'Ditutup', seconds: 0 };
    return { status: 'Aktif', seconds: Math.max(0, Math.ceil((closeAt - at) / 1000)) };
};

export const sidangTaPeriodCountdown = (openAt, closeAt) => ({
    status: '', label: '', timer: null, days: 0, hours: 0, minutes: 0, seconds: 0,
    init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
    destroy() { clearInterval(this.timer); },
    tick() {
        const countdown = periodCountdown(openAt, closeAt);
        if (!countdown) {
            this.status = 'Belum diatur'; this.label = 'Belum diatur';
            this.days = this.hours = this.minutes = this.seconds = 0;
            return;
        }
        this.status = countdown.status;
        const seconds = countdown.seconds;
        this.days = Math.floor(seconds / 86400);
        this.hours = Math.floor(seconds % 86400 / 3600);
        this.minutes = Math.floor(seconds % 3600 / 60);
        this.seconds = seconds % 60;
        this.label = countdown.status === 'Ditutup' ? 'Ditutup' : `${countdown.status} · ${Math.floor(seconds / 86400)} hari ${Math.floor(seconds % 86400 / 3600)} jam ${Math.floor(seconds % 3600 / 60)} menit ${seconds % 60} detik`;
    },
});

export default (initial = {}) => ({
    accurate: initial.accurate ?? '',
    dirty: initial.dirty ?? false,
    busy: false,
    periodHint: initial.periodHint ?? '',
    selectedPeriodId: initial.selectedPeriodId ?? '',
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
    validateWhatsapp(input) {
        input.setCustomValidity(validWhatsapp(input.value) ? '' : 'Nomor WA harus diawali 08 atau 628 dan berisi 8–15 digit angka (0–9).');
    },
    validateContacts(form) {
        const contacts = [...form.querySelectorAll('input[name="student_phone"], input[name="parent_phone"]')];
        for (const input of contacts) this.validateWhatsapp(input);
        const invalid = contacts.find((input) => !validWhatsapp(input.value));
        invalid?.reportValidity();
        return !invalid;
    },
    updatePeriodHint(select) {
        this.periodHint = select.selectedOptions[0]?.dataset.window ?? '';
        this.selectedPeriodId = select.selectedOptions[0]?.dataset.periodId ?? '';
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
        if (file && (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf') || file.size > 10 * 1024 * 1024)) {
            this.$refs.file.value = '';
            this.fileName = '';
            this.fileSize = '';
            this.localError = 'Gunakan file PDF maksimal 10 MB.';
            this.selectionChanged = true;
            this.$dispatch('sidang-ta-file-changed');
            return;
        }
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
