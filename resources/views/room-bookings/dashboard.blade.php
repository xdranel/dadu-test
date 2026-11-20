<x-app-layout>
    <x-slot name="header">Dashboard Peminjaman Ruangan</x-slot>
    <div class="p-6 space-y-6">
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="font-medium text-sm">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span class="font-medium text-sm">{{ session('error') }}</span>
            </div>
        @endif
        @if($errors->any())
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl">
                <ul class="list-disc pl-5 text-sm space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800">Peminjaman Ruangan</h3>
                <p class="text-xs text-slate-400 mt-0.5">Informasi ketersediaan ruangan perkuliahan dan peminjaman</p>
            </div>
            <div class="flex items-center gap-2">
                <a class="btn btn-secondary text-xs" href="{{ route('room-bookings.my') }}">
                    <svg class="w-4 h-4 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Riwayat Peminjaman Saya
                </a>
                @can('room_booking_create')
                    <a class="btn btn-primary text-xs" href="{{ route('room-bookings.create') }}">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajukan Peminjaman
                    </a>
                @endcan
            </div>
        </div>

        @unless($hasImported)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Jadwal belum tersedia untuk pencarian. Lengkapi sinkronisasi pola semester terlebih dahulu.
            </div>
        @else
            <section class="campus-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="font-bold text-lg text-slate-800">Kalender Ketersediaan Ruangan · {{ $month->translatedFormat('F Y') }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Rentang jam operasional: 06.30 – 21.30 WIB. Klik tanggal untuk melihat rincian peminjaman dan slot kosong.</p>
                    </div>
                    <nav class="flex items-center gap-2" aria-label="Navigasi bulan kalender">
                        @if($month->copy()->startOfMonth()->gt(now('Asia/Jakarta')->startOfMonth()))
                            <a class="btn btn-secondary px-3 py-1.5" href="{{ route('room-bookings.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">‹ Sebelumnya</a>
                        @endif
                        <a class="btn btn-secondary px-3 py-1.5" href="{{ route('room-bookings.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">Berikutnya ›</a>
                    </nav>
                </div>
                <div class="grid grid-cols-7 gap-2 text-center text-xs">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $weekday)
                        <div class="font-semibold p-2 text-slate-600">{{ $weekday }}</div>
                    @endforeach
                    @for($blank = 1; $blank < $month->copy()->startOfMonth()->dayOfWeekIso; $blank++)
                        <div></div>
                    @endfor
                    @foreach($calendar as $date => $availability)
                        @php($isPast = $date < $today)
                        <button type="button"
                            onclick="openDayDetailModal('{{ $date }}')"
                            class="rounded-lg border p-2.5 transition-all text-left flex flex-col justify-between min-h-[58px] group cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-slate-400
                            {{ $isPast
                                ? 'bg-slate-100/70 border-slate-200 text-slate-400 hover:bg-slate-200/80'
                                : ($availability === 'available'
                                    ? 'bg-emerald-50 border-emerald-300 text-emerald-900 hover:bg-emerald-100 hover:shadow-sm'
                                    : ($availability === 'unknown' ? 'bg-slate-50 border-slate-200 text-slate-500' : 'bg-rose-50 border-rose-300 text-rose-900 hover:bg-rose-100 hover:shadow-sm')) }}
                            {{ $date === $today ? 'ring-2 ring-emerald-600 ring-offset-2' : '' }}"
                            title="Klik untuk rincian {{ $date }}">
                            <div class="flex items-center justify-between w-full">
                                <span class="font-bold text-sm leading-none">{{ (int) substr($date, 8, 2) }}</span>
                                @if($date === $today)
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-600 text-white leading-none">Hari Ini</span>
                                @endif
                            </div>
                            <div class="mt-1 text-[10px] font-medium leading-tight">
                                @if($isPast)
                                    <span class="text-slate-400">Lewat</span>
                                @elseif($availability === 'available')
                                    <span class="text-emerald-700 font-semibold">Ada Slot</span>
                                @elseif($availability === 'unknown')
                                    <span class="text-slate-500 font-semibold">Jadwal belum tersedia</span>
                                @else
                                    <span class="text-rose-700 font-semibold">Penuh</span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-4 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-300 inline-block"></span> Hijau: Ada slot kosong ≥ 1 jam</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-300 inline-block"></span> Merah: Ruangan penuh</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-100 border border-slate-200 inline-block"></span> Abu-abu: Tanggal lampau</span>
                </div>
            </section>
        @endunless

        <section class="campus-card p-5">
            <h2 class="font-bold text-base mb-3 text-slate-800">Daftar Peminjaman Ruangan</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-slate-200 text-xs font-semibold text-slate-500">
                            <th class="p-2">Peminjam</th>
                            <th>Ruangan</th>
                            <th>Keperluan</th>
                            <th>Waktu Pinjam</th>
                            <th>Waktu Selesai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                                @php($isMultiDay = $booking->start_date->ne($booking->end_date))
                                <td class="p-2 font-medium text-slate-700">{{ $booking->user->name }}</td>
                                <td class="text-slate-600">{{ $booking->room->name }}</td>
                                <td class="text-slate-600">{{ $booking->purpose }}</td>
                                <td class="text-slate-600">
                                    {{ $booking->start_date->format('d/m/Y') }}
                                    <span class="text-xs text-slate-400 font-mono">{{ substr($booking->start_time,0,5) }} WIB</span>
                                </td>
                                <td class="text-slate-600">
                                    {{ $booking->end_date->format('d/m/Y') }}
                                    <span class="text-xs text-slate-400 font-mono">{{ substr($booking->end_time,0,5) }} WIB</span>
                                    @if($isMultiDay)
                                        <span class="block text-[11px] text-indigo-600">({{ $booking->occurrences->count() }} hari sesi)</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ match($booking->status) {
                                        'pending' => 'badge-warning',
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        'cancelled' => 'badge-secondary',
                                        default => 'badge-secondary'
                                    } }} text-xs">{{ ucfirst($booking->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-4 text-center text-slate-400">Belum ada peminjaman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $bookings->links() }}</div>
        </section>
    </div>

    {{-- MODAL DETAIL KALENDER --}}
    <div id="calendarDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalDateTitle" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeDayDetailModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200">
                <div class="flex items-center justify-between px-6 py-4 bg-slate-50 border-b border-slate-200">
                    <div>
                        <h3 class="text-base font-bold text-slate-800" id="modalDateTitle">Rincian Penggunaan Ruangan</h3>
                        <p class="text-xs text-slate-500" id="modalDateSubtitle">Memuat data...</p>
                    </div>
                    <button type="button" onclick="closeDayDetailModal()" class="text-slate-400 hover:text-slate-600 transition-colors rounded-lg p-1 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto" id="modalBodyContent">
                    <div class="flex justify-center items-center py-10">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-700"></div>
                    </div>
                </div>
                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="button" onclick="closeDayDetailModal()" class="btn btn-secondary px-4 py-1.5 text-xs">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const detailApiUrl = '{{ route("room-bookings.calendar.detail") }}';

    let dayDetailVersion = 0;

    function openDayDetailModal(date) {
        const version = ++dayDetailVersion;
        const modal = document.getElementById('calendarDetailModal');
        modal.classList.remove('hidden');
        document.getElementById('modalDateSubtitle').textContent = 'Tanggal: ' + date;
        document.getElementById('modalBodyContent').innerHTML = `
            <div class="flex justify-center items-center py-10">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-700"></div>
            </div>`;

        fetch(`${detailApiUrl}?date=${date}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Gagal mengambil rincian ruangan.');
            return res.json();
        })
        .then(data => {
            if (version === dayDetailVersion) renderModalContent(data);
        })
        .catch(() => {
            if (version !== dayDetailVersion) return;
            document.getElementById('modalBodyContent').innerHTML = `
                <div class="text-center py-6 text-rose-600 text-sm">
                    Gagal mengambil rincian ruangan. Silakan coba lagi.
                </div>`;
        });
    }

    function closeDayDetailModal() {
        dayDetailVersion++;
        document.getElementById('calendarDetailModal').classList.add('hidden');
    }

    function renderModalContent(data) {
        const escapeDisplay = value => {
            const span = document.createElement('span');
            span.textContent = String(value);
            return span.innerHTML;
        };
        const displayRow = row => Object.fromEntries(Object.entries(row).map(([key, value]) => [key,
            typeof value === 'string' ? escapeDisplay(value) : (Array.isArray(value) ? value.map(escapeDisplay) : value)
        ]));
        data = {...data, schedules: (data.schedules || []).map(displayRow),
            bookings: (data.bookings || []).map(displayRow), available_slots: (data.available_slots || []).map(displayRow)};
        let html = data.source_ready === false
            ? '<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Sumber jadwal belum lengkap; ketersediaan ruang belum dapat dipastikan.</p>' : '';

        // Format tanggal judul
        const dateObj = new Date(data.date + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('modalDateTitle').textContent = 'Rincian Penggunaan Ruangan';
        document.getElementById('modalDateSubtitle').textContent = dateObj.toLocaleDateString('id-ID', options);

        // Gabungkan jadwal terpakai (iGracias + internal)
        const bookedList = [...(data.schedules || []), ...(data.bookings || [])];

        html += `<div>
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span> Ruangan yang Terpakai / Terpinjam (${bookedList.length})
            </h4>`;

        if (bookedList.length === 0) {
            html += `<p class="text-xs text-slate-400 italic bg-slate-50 p-3 rounded-lg border border-slate-200">Tidak ada jadwal kuliah maupun peminjaman pada tanggal ini.</p>`;
        } else {
            html += `<div class="space-y-2">`;
            bookedList.forEach(item => {
                const isInternal = item.source === 'internal';
                html += `
                <div class="p-3 rounded-xl border ${isInternal ? 'border-amber-200 bg-amber-50/50' : 'border-sky-200 bg-sky-50/50'} text-xs">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                        <span class="font-bold text-slate-800 text-sm">${item.room_name} <span class="text-slate-400 font-normal">(${item.room_code})</span></span>
                        <span class="font-mono font-semibold px-2 py-0.5 rounded ${isInternal ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800'}">
                            ${item.start_time} – ${item.end_time} WIB
                        </span>
                    </div>
                    <div class="space-y-1.5 text-slate-600 mt-2">
                        <div>
                            <strong class="text-slate-700">Peminjam / Dosen:</strong> 
                            ${isInternal 
                                ? `${item.user_name} ${item.lecturer_code && item.lecturer_code !== '-' ? `(${item.lecturer_code})` : ''}`
                                : (item.lecturer_code && item.lecturer_code !== '-' ? item.lecturer_code : 'Dosen Pengampu')}
                        </div>
                        <div>
                            <strong class="text-slate-700">Keperluan:</strong> ${item.purpose}
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">
                        Sumber: <span class="capitalize font-medium">${item.source}</span> ${item.status ? '· Status: ' + item.status : ''}
                    </div>
                </div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;

        // Slot yang masih tersedia
        html += `<div class="pt-4 border-t border-slate-200">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Jam yang Masih Tersedia (Slot Kosong ≥ 1 Jam, 06.30–21.30 WIB)
            </h4>`;

        if (!data.available_slots || data.available_slots.length === 0) {
            html += `<p class="text-xs text-slate-400 italic bg-slate-50 p-3 rounded-lg border border-slate-200">Tidak ada data ruangan aktif.</p>`;
        } else {
            html += `<div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">`;
            data.available_slots.forEach(room => {
                const hasSlots = room.free_slots && room.free_slots.length > 0;
                html += `
                <div class="p-3 rounded-xl border ${hasSlots ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/60'} text-xs">
                    <div class="font-bold text-slate-800 mb-1 flex items-center justify-between">
                        <span>${room.room_name}</span>
                        <span class="text-[10px] text-slate-400 font-normal">Kapasitas: ${room.capacity || '-'}</span>
                    </div>
                    ${hasSlots
                        ? `<div class="flex flex-wrap gap-1 mt-1.5">
                            ${room.free_slots.map(s => `<span class="bg-white border border-emerald-300 text-emerald-800 px-2 py-0.5 rounded text-[11px] font-mono font-medium">${s}</span>`).join('')}
                           </div>`
                        : `<p class="text-slate-400 italic text-[11px] mt-1">Penuh / Tidak ada slot ≥ 1 jam</p>`
                    }
                </div>`;
            });
            html += `</div>`;
        }
        html += `</div>`;

        document.getElementById('modalBodyContent').innerHTML = html;
    }
    </script>
</x-app-layout>
