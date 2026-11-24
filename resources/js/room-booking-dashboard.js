export default function roomBookingDashboard() {
    let activeRequest;

    return {
        roomCode: '',
        roomDescription: '',
        loading: false,
        error: '',
        availabilityNotice: '',
        busy: [],
        free: [],

        async openRoom(trigger) {
            activeRequest?.abort();
            const request = new AbortController();
            activeRequest = request;

            this.roomCode = trigger.dataset.roomCode;
            this.roomDescription = '';
            this.loading = true;
            this.error = '';
            this.availabilityNotice = '';
            this.busy = [];
            this.free = [];
            if (!this.$refs.dialog.open) {
                this.$refs.dialog.showModal();
            }

            try {
                const response = await fetch(trigger.dataset.detailUrl, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });
                if (!response.ok) {
                    throw new Error('Rincian jadwal ruangan gagal dimuat.');
                }
                const details = await response.json();
                if (request.signal.aborted) return;

                const { room, schedules, bookings, available_slots: slots, source_ready: sourceReady } = details;
                this.roomDescription = [room.building, room.capacity ? `Kapasitas ${room.capacity} orang` : '']
                    .filter(Boolean).join(' · ');
                this.busy = [
                    ...schedules.map(item => ({ ...item, kind: 'Perkuliahan' })),
                    ...bookings.map(item => ({
                        ...item,
                        kind: item.status === 'pending' ? 'Peminjaman · Menunggu persetujuan' : 'Peminjaman · Disetujui',
                    })),
                ].sort((first, second) => first.start_time.localeCompare(second.start_time));
                this.free = slots[0]?.free_slots ?? [];

                if (room.status !== 'Dapat Dipakai') {
                    this.availabilityNotice = 'Ruangan berstatus Tidak Dapat Dipakai.';
                } else if (!sourceReady) {
                    this.availabilityNotice = 'Sumber jadwal belum lengkap untuk tanggal ini.';
                } else if (this.free.length === 0) {
                    this.availabilityNotice = 'Tidak ada slot kosong minimal 1 jam.';
                }
            } catch {
                if (!request.signal.aborted) this.error = 'Rincian jadwal ruangan gagal dimuat.';
            } finally {
                if (!request.signal.aborted) this.loading = false;
            }
        },

        closeRoom() {
            if (!this.$refs.dialog.open) activeRequest?.abort();
        },
    };
}
