<x-app-layout>
    <x-slot name="header">Ajukan Peminjaman Ruangan</x-slot>
    @php
        $todayDate = now('Asia/Jakarta')->toDateString();
        $oldStartAt = old('start_at');
        $oldEndAt = old('end_at');
        $oldRoom = old('room_id');
        $oldPurpose = old('purpose');
        $initialMode = old('booking_mode') === 'recurring' ? 'recurring' : 'consecutive';
        $selectedWeekdays = array_map('strval', array_filter((array) old('weekdays'), 'is_scalar'));
        $initialStartDate = is_string($oldStartAt) ? substr($oldStartAt, 0, 10) : $todayDate;
        $initialEndDate = is_string($oldEndAt) ? substr($oldEndAt, 0, 10) : $todayDate;
        $initialStartTime = is_string($oldStartAt) ? substr($oldStartAt, 11, 5) : '08:00';
        $initialEndTime = is_string($oldEndAt) ? substr($oldEndAt, 11, 5) : '09:00';
        $weekdays = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $timeSlots = [];

        for ($minute = 390; $minute <= 1290; $minute += 30) {
            $timeSlots[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        }
    @endphp

    <div class="mx-auto grid max-w-6xl items-start gap-4 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(14rem,1fr)_minmax(0,2.5fr)]">
        <details open class="campus-card group p-4">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-800">
                Panduan peminjaman Ruangan
                <svg class="size-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none"
                     aria-hidden="true">
                    <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
            </summary>
            <div class="mt-4 space-y-4 border-t border-slate-200 pt-4 text-sm text-slate-700">
                <div>
                    <h2 class="font-semibold text-slate-800">Ketentuan Umum</h2>
                    <ul class="mt-2 ml-5 list-disc space-y-3">
                        <li>
                            Peminjaman ruangan melalui aplikasi DADU hanya berlaku pada jam dan masa perkuliahan aktif.
                            Peminjaman di luar ketentuan tersebut, termasuk libur semester, hari libur nasional, dan akhir
                            pekan, diajukan melalui Unit Logistik.
                        </li>
                        <li>
                            Fitur peminjaman ruangan pada aplikasi DADU hanya diperuntukkan bagi dosen. Peminjaman oleh
                            mahasiswa, Unit Kegiatan Mahasiswa (UKM), dan Organisasi Mahasiswa (Ormawa) diajukan melalui
                            Unit Logistik.
                        </li>
                    </ul>
                </div>
                <div>
                    <h2 class="font-semibold text-slate-800 underline">Opsi Peminjaman</h2>
                </div>
                <div>
                    <h2 class="font-semibold text-slate-800">1. Berurutan</h2>
                    <p class="mt-1">Digunakan untuk peminjaman ruangan pada beberapa tanggal secara berturut-turut dengan jam penggunaan yang sama.</p>
                    <p class="mt-1">Pilih tanggal mulai dan tanggal selesai dalam rentang semester aktif.</p>
                    <p class="mt-1 text-slate-500">Contoh: Peminjaman pada 1–3 Februari, maka ruangan akan digunakan pada tanggal 1, 2, dan 3 Februari dengan jam yang sama.</p>
                </div>
                <div>
                    <h2 class="font-semibold text-slate-800">2. Berulang</h2>
                    <p class="mt-1">
                        Digunakan untuk peminjaman ruangan pada hari-hari tertentu secara berkala dalam rentang waktu yang ditentukan.
                        Pilih tanggal mulai dan tanggal selesai, kemudian centang hari penggunaan ruangan yang diinginkan.
                    </p>
                    <p class="mt-1">Contoh penggunaan:</p>
                    <ul class="mt-2 ml-5 list-disc space-y-2">
                        <li>Setiap Senin dan Rabu selama satu semester.</li>
                        <li>Setiap Senin hingga pelaksanaan UTS.</li>
                        <li>Setiap Jumat selama dua minggu.</li>
                    </ul>
                    <p class="mt-1 text-slate-500">Catatan: Batas akhir tanggal peminjaman untuk kedua opsi tersebut adalah tanggal berakhirnya semester berjalan.</p>
                </div>
            </div>
        </details>

        <form id="bookingForm" method="POST" action="{{ route('room-bookings.store') }}"
              data-availability-url="{{ route('room-bookings.availability') }}" data-today="{{ $todayDate }}"
              data-old-room="{{ is_scalar($oldRoom) ? $oldRoom : '' }}"
              class="campus-card min-w-0 space-y-5 p-4 sm:p-6">
            @csrf
            @unless($hasImported ?? false)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Jadwal iGracias belum berhasil disinkronisasi. Cek ketersediaan dan pengajuan akan aktif setelah sinkronisasi.
                </div>
            @endunless

            <div>
                <label class="campus-label" for="booking_mode">Jenis Peminjaman <span class="text-rose-500">*</span></label>
                <select id="booking_mode" name="booking_mode" class="campus-select w-full" required>
                    <option value="consecutive" @selected($initialMode === 'consecutive')>Berurutan</option>
                    <option value="recurring" @selected($initialMode === 'recurring')>Berulang</option>
                </select>
                @error('booking_mode') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="campus-label" for="start_date">Tanggal Mulai <span class="text-rose-500">*</span></label>
                    <input type="date" id="start_date" value="{{ $initialStartDate }}" min="{{ $todayDate }}" class="campus-input w-full" required>
                    @error('start_at') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="campus-label" for="end_date">Tanggal Selesai <span class="text-rose-500">*</span></label>
                    <input type="date" id="end_date" value="{{ $initialEndDate }}" min="{{ $todayDate }}" class="campus-input w-full" required>
                    @error('end_at') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="campus-label" for="start_time_select">Waktu Mulai <span class="text-rose-500">*</span></label>
                    <select id="start_time_select" class="campus-select w-full" aria-describedby="booking-hours" required>
                        @foreach($timeSlots as $slot)
                            <option value="{{ $slot }}" @selected($slot === $initialStartTime)>{{ $slot }} WIB</option>
                        @endforeach
                    </select>
                    @error('start_time') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="campus-label" for="end_time_select">Waktu Selesai <span class="text-rose-500">*</span></label>
                    <select id="end_time_select" class="campus-select w-full" aria-describedby="booking-hours" required>
                        @foreach($timeSlots as $slot)
                            <option value="{{ $slot }}" @selected($slot === $initialEndTime)>{{ $slot }} WIB</option>
                        @endforeach
                    </select>
                    @error('end_time') <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <p id="booking-hours" class="text-xs text-slate-600">Jam peminjaman 06.30–21.30 WIB. Jam yang sama berlaku pada setiap tanggal pemakaian.</p>

            <fieldset id="weekdayField" aria-describedby="booking-weekdays-hint" @if($initialMode !== 'recurring') hidden @endif>
                <legend class="campus-label">Hari pemakaian <span class="text-rose-500">*</span></legend>
                <p id="booking-weekdays-hint" class="mb-3 text-xs text-slate-600">Pilih minimal satu hari dalam rentang tanggal yang diisi.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($weekdays as $value => $label)
                        <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 has-checked:border-sky-500 has-checked:bg-sky-50">
                            <input type="checkbox" name="weekdays[]" value="{{ $value }}" class="size-4 rounded border-slate-300 text-sky-700 focus:ring-sky-600"
                                   @checked(in_array((string) $value, $selectedWeekdays, true)) @disabled($initialMode !== 'recurring')>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @if($weekdayError = $errors->first('weekdays') ?: $errors->first('weekdays.*'))
                    <p class="mt-2 text-xs text-rose-700">{{ $weekdayError }}</p>
                @endif
            </fieldset>

            <input type="hidden" id="start_at" name="start_at">
            <input type="hidden" id="end_at" name="end_at">

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="checkAvailability" class="btn btn-secondary" @disabled(!($hasImported ?? false))>Cek Ketersediaan</button>
                <p id="availabilityMessage" class="text-sm text-slate-600" aria-live="polite"></p>
            </div>
            @error('availability') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror

            <div id="roomField" hidden>
                <label class="campus-label" for="room_id">Pilih Ruangan <span class="text-rose-500">*</span></label>
                <select id="room_id" name="room_id" class="campus-select w-full" required disabled>
                    <option value="">Pilih ruangan</option>
                </select>
            </div>
            @error('room_id') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror

            <div id="purposeField" hidden>
                <label class="campus-label" for="purpose">Keperluan / Agenda Peminjaman <span class="text-rose-500">*</span></label>
                <textarea id="purpose" name="purpose" rows="3" maxlength="255" class="campus-input w-full" required disabled>{{ is_string($oldPurpose) ? $oldPurpose : '' }}</textarea>
            </div>
            @error('purpose') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('room-bookings.index') }}" class="btn btn-secondary">Kembali</a>
                <button id="submitBooking" type="submit" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" disabled>Ajukan Peminjaman</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('bookingForm');
        const mode = document.getElementById('booking_mode');
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        const startTime = document.getElementById('start_time_select');
        const endTime = document.getElementById('end_time_select');
        const weekdayField = document.getElementById('weekdayField');
        const weekdays = [...form.querySelectorAll('input[name="weekdays[]"]')];
        const roomField = document.getElementById('roomField');
        const room = document.getElementById('room_id');
        const purposeField = document.getElementById('purposeField');
        const purpose = document.getElementById('purpose');
        const submit = document.getElementById('submitBooking');
        const checkButton = document.getElementById('checkAvailability');
        const message = document.getElementById('availabilityMessage');

        let pendingCheck = null;
        let checkedSchedule = null;
        let previousStartDate = startDate.value;

        const schedule = () => {
            const values = {
                booking_mode: mode.value,
                start_at: startDate.value && startTime.value ? startDate.value + 'T' + startTime.value : '',
                end_at: endDate.value && endTime.value ? endDate.value + 'T' + endTime.value : ''
            };
            if (mode.value === 'recurring') {
                values.weekdays = weekdays.filter(day => day.checked).map(day => Number(day.value));
            }
            return values;
        };
        const initialSchedule = JSON.stringify(schedule());

        const setMessage = (text, isError = false) => {
            message.textContent = text;
            message.classList.toggle('text-rose-700', isError);
            message.classList.toggle('text-slate-600', !isError);
        };

        const canSubmit = () => checkedSchedule === JSON.stringify(schedule()) && room.value && purpose.value.trim();

        const updateRoom = () => {
            purposeField.hidden = !room.value;
            purpose.disabled = !room.value;
            submit.disabled = !canSubmit();
        };

        const resetAvailability = () => {
            pendingCheck?.abort();
            pendingCheck = null;
            checkedSchedule = null;
            roomField.hidden = true;
            room.disabled = true;
            room.replaceChildren(new Option('Pilih ruangan', ''));
            updateRoom();
        };

        const updateWeekdays = () => {
            const isRecurring = mode.value === 'recurring';
            weekdayField.hidden = !isRecurring;
            weekdays.forEach(day => { day.disabled = !isRecurring; });
        };

        const validateSchedule = values => {
            if (!values.start_at || !values.end_at) return 'Isi tanggal dan waktu mulai serta selesai.';
            if (startDate.value < form.dataset.today) return 'Tanggal mulai tidak boleh berada di masa lalu.';
            if (endDate.value < startDate.value) return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            if (startTime.value < '06:30' || endTime.value > '21:30' || startTime.value >= endTime.value) {
                return 'Waktu selesai harus setelah waktu mulai dalam rentang 06.30–21.30 WIB.';
            }
            if (values.weekdays?.length === 0) return 'Pilih minimal satu hari pemakaian untuk mode Berulang.';
            return '';
        };

        const scheduleChanged = () => {
            resetAvailability();
            setMessage('');
        };

        endDate.min = startDate.value || form.dataset.today;
        updateWeekdays();
        startDate.addEventListener('change', () => {
            if (endDate.value === previousStartDate) {
                endDate.value = startDate.value;
            }
            previousStartDate = startDate.value;
            endDate.min = startDate.value || form.dataset.today;
            scheduleChanged();
        });
        mode.addEventListener('change', () => {
            updateWeekdays();
            scheduleChanged();
        });
        [endDate, startTime, endTime, ...weekdays].forEach(control => control.addEventListener('change', scheduleChanged));

        checkButton.addEventListener('click', async () => {
            resetAvailability();
            const values = schedule();
            const error = validateSchedule(values);
            if (error) {
                setMessage(error, true);
                return;
            }

            const controller = new AbortController();
            const requestKey = JSON.stringify(values);
            pendingCheck = controller;
            setMessage('Memeriksa jadwal…');
            try {
                const response = await fetch(form.dataset.availabilityUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.elements._token.value
                    },
                    signal: controller.signal,
                    body: requestKey
                });
                const result = await response.json().catch(() => null);
                if (pendingCheck !== controller || requestKey !== JSON.stringify(schedule())) return;
                const validation = Object.values(result?.errors || {}).flat().join(' ');
                if (!response.ok || !Array.isArray(result?.rooms)) {
                    setMessage(validation || result?.message || 'Jadwal belum bisa diperiksa. Coba lagi.', true);
                    return;
                }
                if (result.rooms.length === 0) {
                    setMessage('Tidak ada satu ruangan yang tersedia pada semua tanggal dan jam yang dipilih.', true);
                    return;
                }

                result.rooms.forEach(item => {
                    const label = [item.code, item.building || 'Lokasi belum diisi', 'Kapasitas ' + (item.capacity || '—')].join(' · ');
                    room.append(new Option(label, item.id));
                });
                if (requestKey === initialSchedule && [...room.options].some(option => option.value === form.dataset.oldRoom)) {
                    room.value = form.dataset.oldRoom;
                }
                checkedSchedule = requestKey;
                room.disabled = false;
                roomField.hidden = false;
                updateRoom();
                const dateCount = result.dates?.length;
                setMessage(dateCount ? 'Pilih ruangan yang tersedia untuk ' + dateCount + ' tanggal pemakaian.' : 'Pilih ruangan yang tersedia untuk melanjutkan.');
            } catch {
                if (pendingCheck === controller) setMessage('Jadwal belum bisa diperiksa. Coba lagi.', true);
            } finally {
                if (pendingCheck === controller) pendingCheck = null;
            }
        });

        room.addEventListener('change', updateRoom);
        purpose.addEventListener('input', updateRoom);
        form.addEventListener('submit', event => {
            if (!canSubmit()) {
                event.preventDefault();
                setMessage('Cek ketersediaan, pilih ruangan, dan isi agenda terlebih dahulu.', true);
                return;
            }
            const values = schedule();
            document.getElementById('start_at').value = values.start_at;
            document.getElementById('end_at').value = values.end_at;
            submit.disabled = true;
            submit.textContent = 'Mengirim…';
        });
    });
    </script>
</x-app-layout>
