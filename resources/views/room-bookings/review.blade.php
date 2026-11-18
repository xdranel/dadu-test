<x-app-layout>
    <x-slot name="header">Review Peminjaman Ruangan</x-slot>
    <div class="p-6 space-y-6">

        @if(session('success'))
            <div class="p-3 bg-green-50 text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-50 text-red-700 rounded">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        {{-- Info jadwal iGracias — dipindah ke Sinkronisasi --}}
        <div class="campus-card p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-sky-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="text-sm text-slate-600">
                <span class="font-semibold text-slate-700">Import jadwal iGracias</span>
                telah dipindahkan ke menu
                <a href="{{ route('sync.index') }}" class="text-[#DA2D3B] font-semibold hover:underline">Sinkronisasi → Jenis Data: Jadwal Ruangan iGracias</a>.
                @if($lastImport)
                    <span class="ml-2 text-slate-400 text-xs">Import terakhir: {{ $lastImport->filename }} ·
                        {{ $lastImport->rows_imported }} baris ·
                        {{ \Carbon\Carbon::parse($lastImport->created_at)->diffForHumans() }}</span>
                @else
                    <span class="ml-2 text-amber-600 text-xs font-medium">⚠️ Belum ada import jadwal iGracias.</span>
                @endif
            </div>
        </div>

        <section class="campus-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-bold text-lg text-slate-800">Kalender Ketersediaan Ruangan · {{ $month->translatedFormat('F Y') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Rentang jam operasional: 06.30 – 21.30 WIB. Klik tanggal untuk melihat rincian peminjaman dan slot kosong.</p>
                </div>
                <nav class="flex items-center gap-2" aria-label="Navigasi bulan kalender">
                    <a href="{{ route('admin.room-bookings.review', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn btn-secondary px-3 py-1.5" aria-label="Bulan sebelumnya">‹ Sebelumnya</a>
                    <a href="{{ route('admin.room-bookings.review', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn btn-secondary px-3 py-1.5" aria-label="Bulan berikutnya">Berikutnya ›</a>
                </nav>
            </div>
            <div class="grid grid-cols-7 gap-2 text-center text-xs">
                @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $weekday)<div class="font-semibold p-2 text-slate-600">{{ $weekday }}</div>@endforeach
                @for($blank=1;$blank<$month->copy()->startOfMonth()->dayOfWeekIso;$blank++)<div></div>@endfor
                @for($d=$month->copy()->startOfMonth();$d->lte($month->copy()->endOfMonth());$d->addDay())
                    @php($dateStr=$d->toDateString())
                    @php($dayStatus=$calendar[$dateStr] ?? 'available')
                    @php($isToday=$dateStr === $today)
                    @php($isPast = $dateStr < $today)
                    <button type="button"
                        onclick="openDayDetailModal('{{ $dateStr }}')"
                        class="rounded-lg border p-2.5 transition-all text-left flex flex-col justify-between min-h-[58px] group cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-slate-400
                        {{ $isPast
                            ? 'bg-slate-100/70 border-slate-200 text-slate-400 hover:bg-slate-200/80'
                            : ($dayStatus === 'available'
                                ? 'bg-emerald-50 border-emerald-300 text-emerald-900 hover:bg-emerald-100 hover:shadow-sm'
                                : ($dayStatus === 'unknown' ? 'bg-slate-50 border-slate-200 text-slate-500' : 'bg-rose-50 border-rose-300 text-rose-900 hover:bg-rose-100 hover:shadow-sm')) }}
                        {{ $isToday ? 'ring-2 ring-emerald-600 ring-offset-2' : '' }}"
                        title="Klik untuk rincian {{ $dateStr }}">
                        <div class="flex items-center justify-between w-full">
                            <span class="font-bold text-sm leading-none">{{ $d->day }}</span>
                            @if($isToday)
                                <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-600 text-white leading-none">Hari Ini</span>
                            @endif
                        </div>
                        <div class="mt-1 text-[10px] font-medium leading-tight">
                            @if($isPast)
                                <span class="text-slate-400">Lewat</span>
                            @elseif($dayStatus === 'available')
                                <span class="text-emerald-700 font-semibold">Ada Slot</span>
                            @elseif($dayStatus === 'unknown')
                                <span class="text-slate-500 font-semibold">Jadwal belum tersedia</span>
                            @else
                                <span class="text-rose-700 font-semibold">Penuh</span>
                            @endif
                        </div>
                    </button>
                @endfor
            </div>
            <div class="flex flex-wrap items-center gap-4 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-300 inline-block"></span> Hijau: Ada slot kosong ≥ 1 jam</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-100 border border-rose-300 inline-block"></span> Merah: Ruangan penuh</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-100 border border-slate-200 inline-block"></span> Abu-abu: Tanggal lampau</span>
            </div>
        </section>

        {{-- Daftar Pengajuan --}}
        <section>
            <h2 class="font-bold text-lg mb-3">Daftar Pengajuan</h2>
            @forelse($bookings as $booking)
                <article class="campus-card p-5 mb-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-slate-800">{{ $booking->user->name }}</p>
                                @if($booking->sidang_ta_registration_id || $booking->booking_origin === 'sidang_ta_automatic')
                                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-sky-100 text-sky-800 border border-sky-300">Sidang TA</span>
                                @else
                                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700">Dosen</span>
                                @endif
                            </div>
                            @php($lecturer = ($booking->user->employee_id && isset($lecturersByNip) ? $lecturersByNip->get(trim($booking->user->employee_id)) : null) ?? $lecturers->get(mb_strtolower(trim($booking->user->name))))
                            <p class="text-xs text-slate-500">NIP: {{ $lecturer->nip_ypt ?? $booking->user->employee_id ?? '-' }} · Prodi: {{ $lecturer->study_program ?? '-' }}</p>
                            <p class="text-sm text-slate-500">{{ $booking->room->name }} &middot; Kapasitas {{ $booking->room->capacity }}</p>
                        </div>
                        <span class="badge {{ match($booking->status) {
                            'pending'   => 'badge-warning',
                            'approved'  => 'badge-success',
                            'rejected'  => 'badge-danger',
                            'cancelled' => 'badge-secondary',
                            default     => 'badge-secondary'
                        } }} text-xs">{{ ucfirst($booking->status) }}</span>
                    </div>

                    {{-- Jenis & waktu --}}
                    <div class="mt-3 text-sm text-slate-600 space-y-1">
                        <p><span class="font-medium">Keperluan:</span> {{ $booking->purpose }}</p>
                    </div>

                    {{-- Occurrences --}}
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1.5">Jadwal Penggunaan:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($booking->occurrences as $occ)
                                <span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-700 rounded-full px-2.5 py-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $occ->usage_date->format('d/m/Y') }}
                                    {{ substr($occ->start_time, 0, 5) }}–{{ substr($occ->end_time, 0, 5) }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Aksi admin --}}
                    @if($booking->status === 'pending')
                        <form method="POST" action="{{ route('admin.room-bookings.decision', $booking) }}"
                              onsubmit="this.querySelectorAll('button').forEach(b => b.disabled = true);"
                              class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                            @csrf
                            <input name="note" placeholder="Catatan (opsional)"
                                   class="campus-input flex-1 min-w-[200px]">
                            <button name="decision" value="approved"
                                    class="btn btn-success px-4">Setujui</button>
                            <button name="decision" value="rejected"
                                    class="btn btn-danger px-4">Tolak</button>
                        </form>
                    @elseif($booking->status === 'approved')
                        <form method="POST" action="{{ route('admin.room-bookings.cancel', $booking) }}"
                              onsubmit="if(confirm('Batalkan seluruh jadwal dalam pengajuan ini? Pastikan alasan pembatalan sudah benar.')){ this.querySelector('button').disabled = true; return true; } return false;"
                              class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                            @csrf
                            <input name="reason" required placeholder="Alasan pembatalan"
                                   class="campus-input flex-1 min-w-[200px]">
                            <button class="btn btn-danger px-4">Batalkan Peminjaman</button>
                        </form>
                    @endif

                    @if($booking->admin_note)
                        <p class="mt-2 text-xs text-slate-400">Catatan admin: {{ $booking->admin_note }}</p>
                    @endif
                    @if($booking->cancellation_reason)
                        <p class="mt-2 text-xs text-red-500">Alasan pembatalan: {{ $booking->cancellation_reason }}</p>
                    @endif
                </article>
            @empty
                <div class="empty-state py-10">
                    <p class="empty-state-text">Belum ada pengajuan peminjaman.</p>
                </div>
            @endforelse

            {{ $bookings->links() }}
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
    const detailApiUrl = '{{ route("admin.room-bookings.calendar.detail") }}';

    function openDayDetailModal(date) {
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
        .then(res => res.json())
        .then(data => {
            renderModalContent(data);
        })
        .catch(err => {
            document.getElementById('modalBodyContent').innerHTML = `
                <div class="text-center py-6 text-rose-600 text-sm">
                    Gagal mengambil rincian ruangan. Silakan coba lagi.
                </div>`;
        });
    }

    function closeDayDetailModal() {
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
