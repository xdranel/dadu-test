<x-app-layout>
    <x-slot name="header">Ajukan Peminjaman Ruangan</x-slot>
    <div class="max-w-4xl mx-auto p-6 space-y-6">
        @if ($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl"><p class="font-bold mb-2">Terjadi kesalahan dalam pengajuan:</p><ul class="list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @unless($hasImported ?? false)
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">Jadwal iGracias belum berhasil disinkronisasi. Anda tetap dapat mengisi waktu, tetapi cek ketersediaan dan pengajuan akan aktif setelah sinkronisasi.</div>
        @endunless
        @php
            $todayDate = now('Asia/Jakarta')->toDateString();
            $oldStartAt = old('start_at');
            $oldEndAt = old('end_at');
            $initialStartDate = $oldStartAt ? substr($oldStartAt, 0, 10) : old('start_date', $todayDate);
            $initialStartTime = $oldStartAt ? substr($oldStartAt, 11, 5) : '08:00';
            $initialEndDate = $oldEndAt ? substr($oldEndAt, 0, 10) : old('end_date', $todayDate);
            $initialEndTime = $oldEndAt ? substr($oldEndAt, 11, 5) : '09:00';

            $timeSlots = [];
            for ($m = 6 * 60 + 30; $m <= 21 * 60 + 30; $m += 30) {
                $timeSlots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            }
        @endphp
        <section class="campus-card p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Mulai --}}
                <div class="space-y-3">
                    <div>
                        <label class="campus-label" for="start_date">
                            Tanggal Mulai <span class="text-rose-400">*</span>
                            <span id="startTodayBadge" class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Hari ini</span>
                        </label>
                        <input type="date" id="start_date"
                               value="{{ $initialStartDate }}"
                               min="{{ $todayDate }}"
                               class="campus-input w-full ring-1 ring-indigo-200" required>
                    </div>
                    <div>
                        <label class="campus-label" for="start_time_select">
                            Waktu Mulai <span class="text-rose-400">*</span>
                            <span class="text-xs text-slate-400 font-normal">(Tersedia 06.30 – 21.30 WIB)</span>
                        </label>
                        <select id="start_time_select" class="campus-select w-full" required>
                            @foreach($timeSlots as $slot)
                                <option value="{{ $slot }}" {{ $slot === $initialStartTime ? 'selected' : '' }}>{{ $slot }} WIB</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Selesai --}}
                <div class="space-y-3">
                    <div>
                        <label class="campus-label" for="end_date">
                            Tanggal Selesai <span class="text-rose-400">*</span>
                            <span id="endTodayBadge" class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">Hari ini</span>
                        </label>
                        <input type="date" id="end_date"
                               value="{{ $initialEndDate }}"
                               min="{{ $todayDate }}"
                               class="campus-input w-full ring-1 ring-indigo-200" required>
                    </div>
                    <div>
                        <label class="campus-label" for="end_time_select">
                            Waktu Selesai <span class="text-rose-400">*</span>
                            <span class="text-xs text-slate-400 font-normal">(Tersedia 06.30 – 21.30 WIB)</span>
                        </label>
                        <select id="end_time_select" class="campus-select w-full" required>
                            @foreach($timeSlots as $slot)
                                <option value="{{ $slot }}" {{ $slot === $initialEndTime ? 'selected' : '' }}>{{ $slot }} WIB</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-500 italic">* Untuk peminjaman berulang (multi-hari), jam harian yang dipilih akan diterapkan pada setiap hari dalam rentang tanggal.</p>

            <input type="hidden" id="start_at">
            <input type="hidden" id="end_at">
            <p id="timeWarning" class="text-xs text-rose-500 font-medium hidden">Waktu selesai harus lebih lambat dari waktu mulai.</p>

            <div class="flex flex-wrap items-center gap-3"><button type="button" id="checkAvailability" class="btn btn-secondary" @unless($hasImported ?? false) disabled @endunless>Cek Ketersediaan</button><span id="availabilityMessage" class="text-sm text-slate-500" aria-live="polite"></span></div>
            <div id="roomField" class="hidden"><label class="campus-label" for="room_id">Pilih Ruangan <span class="text-rose-400">*</span></label><select id="room_id" class="campus-select w-full" disabled><option value="">-- Pilih Ruangan --</option></select></div>
            <form id="bookingForm" method="POST" action="{{ route('room-bookings.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="start_at" id="start_at_submit"><input type="hidden" name="end_at" id="end_at_submit"><input type="hidden" name="room_id" id="room_id_submit">
                <div id="purposeField" class="hidden"><label class="campus-label" for="purpose">Keperluan / Agenda Peminjaman <span class="text-rose-400">*</span></label><textarea id="purpose" name="purpose" rows="3" maxlength="255" placeholder="Jelaskan tujuan peminjaman ruangan..." class="campus-input w-full" required>{{ old('purpose') }}</textarea></div>
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between"><a href="{{ route('room-bookings.index') }}" class="btn btn-secondary">Kembali</a><button id="submitBooking" type="submit" class="btn btn-primary px-6 py-2.5" disabled>Ajukan Peminjaman</button></div>
            </form>
        </section>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const startDate = document.getElementById('start_date');
        const startTime = document.getElementById('start_time_select');
        const endDate = document.getElementById('end_date');
        const endTime = document.getElementById('end_time_select');
        const start = document.getElementById('start_at');
        const end = document.getElementById('end_at');
        const timeWarning = document.getElementById('timeWarning');

        const roomField = document.getElementById('roomField');
        const room = document.getElementById('room_id');
        const purposeField = document.getElementById('purposeField');
        const purpose = document.getElementById('purpose');
        const submit = document.getElementById('submitBooking');
        const message = document.getElementById('availabilityMessage');
        const startSubmit = document.getElementById('start_at_submit');
        const endSubmit = document.getElementById('end_at_submit');
        const roomSubmit = document.getElementById('room_id_submit');

        let checkedRange = null;
        let availabilityVersion = 0;
        let lastStartDate = startDate.value;
        const today = '{{ now("Asia/Jakarta")->toDateString() }}';

        const syncDateTime = () => {
            start.value = startDate.value && startTime.value ? `${startDate.value}T${startTime.value}` : '';
            end.value = endDate.value && endTime.value ? `${endDate.value}T${endTime.value}` : '';

            if (start.value && end.value && start.value >= end.value) {
                timeWarning.classList.remove('hidden');
            } else {
                timeWarning.classList.add('hidden');
            }
        };

        const updateTodayBadges = () => {
            const startBadge = document.getElementById('startTodayBadge');
            const endBadge = document.getElementById('endTodayBadge');
            if (startBadge) startBadge.textContent = startDate.value === today ? 'Hari ini' : 'Tanggal dipilih';
            if (endBadge) endBadge.textContent = endDate.value === today ? 'Hari ini' : 'Tanggal dipilih';
        };

        const validPeriod = () => {
            if (!start.value || !end.value || start.value >= end.value) return false;
            const sTime = start.value.slice(11, 16);
            const eTime = end.value.slice(11, 16);
            return sTime >= '06:30' && sTime < eTime && eTime <= '21:30';
        };

        const matchesRange = range => range?.start_at === start.value && range?.end_at === end.value;
        const canSubmit = () => matchesRange(checkedRange) && validPeriod() && room.value && purpose.value.trim();
        const updateSubmit = () => { submit.disabled = !canSubmit(); };

        const reset = () => {
            availabilityVersion++;
            checkedRange = null;
            submit.disabled = true;
            roomField.classList.add('hidden');
            purposeField.classList.add('hidden');
            room.disabled = true;
            room.innerHTML = '<option value="">-- Pilih Ruangan --</option>';
            roomSubmit.value = '';
        };

        // Inisialisasi
        syncDateTime();
        updateTodayBadges();

        startDate.addEventListener('change', () => {
            if (endDate.value === lastStartDate) {
                endDate.value = startDate.value;
            }
            lastStartDate = startDate.value;
            syncDateTime();
            reset();
            message.textContent = '';
            updateTodayBadges();
        });

        endDate.addEventListener('change', () => {
            syncDateTime();
            reset();
            message.textContent = '';
            updateTodayBadges();
        });

        [startTime, endTime].forEach(sel => {
            sel.addEventListener('change', () => {
                syncDateTime();
                reset();
                message.textContent = '';
            });
        });

        document.getElementById('checkAvailability').addEventListener('click', async () => {
            reset();
            syncDateTime();
            message.textContent = 'Memeriksa jadwal…';
            if (!start.value || !end.value) {
                message.textContent = 'Tentukan waktu mulai dan selesai terlebih dahulu.';
                return;
            }
            if (!validPeriod()) {
                message.textContent = 'Periksa rentang tanggal dan jam. Waktu selesai harus setelah waktu mulai (06.30–21.30 WIB).';
                return;
            }
            const version = availabilityVersion;
            const range = { start_at: start.value, end_at: end.value };
            const isCurrent = () => version === availabilityVersion && matchesRange(range);
            try {
                const response = await fetch('{{ route('room-bookings.availability') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify(range)
                });
                const result = await response.json();
                if (!isCurrent()) return;
                if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' '));
                if (!result.rooms.length) {
                    message.textContent = 'Tidak ada ruangan tersedia pada rentang waktu tersebut.';
                    return;
                }
                result.rooms.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name + ' · ' + item.code + ' · ' + (item.building || 'Lokasi belum diisi') + ' · kapasitas ' + item.capacity;
                    room.append(option);
                });
                checkedRange = range;
                room.disabled = false;
                roomField.classList.remove('hidden');
                message.textContent = 'Pilih ruangan yang tersedia untuk melanjutkan.';
            } catch (error) {
                if (isCurrent()) message.textContent = error.message;
            }
        });

        room.addEventListener('change', () => {
            roomSubmit.value = room.value;
            if (room.value) purposeField.classList.remove('hidden');
            else purposeField.classList.add('hidden');
            updateSubmit();
        });

        purpose.addEventListener('input', updateSubmit);

        document.getElementById('bookingForm').addEventListener('submit', event => {
            syncDateTime();
            if (!canSubmit()) {
                event.preventDefault();
                updateSubmit();
                message.textContent = 'Cek ketersediaan, pilih ruangan, dan isi agenda terlebih dahulu.';
                return;
            }
            startSubmit.value = start.value;
            endSubmit.value = end.value;
            submit.disabled = true;
            submit.textContent = 'Mengirim...';
        });
    });
    </script>
</x-app-layout>

