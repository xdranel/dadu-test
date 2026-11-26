export default function sidangTaPlottingRooms(endpoint, roomId, assignedSlot) {
    let activeRequest;

    return {
        rooms: [],
        roomId: String(roomId ?? ''),
        message: '',
        loading: false,
        keepAssignedRoom: assignedSlot !== null,

        async loadRooms() {
            activeRequest?.abort();

            const slot = Object.fromEntries(['date', 'start_time', 'end_time'].map((field) => [
                field, this.$el.elements.namedItem(field).value,
            ]));
            const taType = this.$el.elements.namedItem('ta_type').value;
            const selectedRoom = this.roomId;
            this.rooms = [];
            this.keepAssignedRoom = assignedSlot !== null && Object.keys(slot).every((field) => slot[field] === assignedSlot[field]);
            this.loading = false;

            if (!slot.date || !slot.start_time || !slot.end_time || slot.start_time >= slot.end_time) {
                this.message = 'Pilih tanggal serta jam mulai dan selesai untuk melihat ruang yang tersedia.';
                return;
            }

            const request = new AbortController();
            activeRequest = request;
            this.loading = true;
            this.message = 'Memeriksa ketersediaan ruang…';

            try {
                const response = await fetch(`${endpoint}?${new URLSearchParams({...slot, ta_type: taType})}`, {
                    signal: request.signal,
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                if (activeRequest !== request || request.signal.aborted) return;
                if (!response.ok) {
                    this.roomId = '';
                    this.message = response.status === 422 ? data.message : 'Ketersediaan ruang gagal dimuat.';
                    return;
                }

                this.rooms = data.rooms;
                this.message = data.message ?? '';
                await this.$nextTick();
                if (activeRequest !== request || request.signal.aborted) return;
                this.roomId = this.rooms.some((room) => String(room.id) === selectedRoom) ? selectedRoom : '';
            } catch {
                if (!request.signal.aborted) {
                    this.roomId = '';
                    this.message = 'Ketersediaan ruang gagal dimuat.';
                }
            } finally {
                if (activeRequest === request && !request.signal.aborted) this.loading = false;
            }
        },
    };
}
