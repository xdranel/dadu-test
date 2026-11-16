<x-app-layout>
    <x-slot name="header">Peminjaman Ruangan</x-slot>

    <div class="p-6 space-y-6">
        @if(session('success'))
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-800">Riwayat Peminjaman Ruangan</h3>
                <p class="text-xs text-slate-400 mt-0.5">Daftar pengajuan peminjaman ruangan yang telah Anda buat</p>
            </div>
            @can('room_booking_create')
                <a class="btn btn-primary" href="{{ route('room-bookings.create') }}">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Ajukan Peminjaman
                </a>
            @endcan
        </div>

        <div class="space-y-4">
            @forelse($bookings as $booking)
                <article class="campus-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-base text-slate-800">{{ $booking->room->name }}</h4>
                                <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">{{ $booking->room->room_code }}</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Gedung: {{ $booking->room->building ?? '-' }} · Kapasitas: {{ $booking->room->capacity }} orang
                            </p>
                        </div>

                        <span class="badge {{ match($booking->status) {
                            'pending'   => 'badge-warning',
                            'approved'  => 'badge-success',
                            'rejected'  => 'badge-danger',
                            'cancelled' => 'badge-secondary',
                            default     => 'badge-secondary'
                        } }} text-xs">
                            {{ match($booking->status) {
                                'pending'   => 'Menunggu Persetujuan',
                                'approved'  => 'Disetujui',
                                'rejected'  => 'Ditolak',
                                'cancelled' => 'Dibatalkan',
                                default     => ucfirst($booking->status)
                            } }}
                        </span>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 text-sm text-slate-600 space-y-1.5">
                        <p><span class="font-medium text-slate-700">Keperluan:</span> {{ $booking->purpose }}</p>
                        @if($booking->admin_note)
                            <p class="text-xs text-slate-500 italic"><span class="font-medium">Catatan Admin:</span> {{ $booking->admin_note }}</p>
                        @endif
                        @if($booking->cancellation_reason)
                            <p class="text-xs text-rose-500"><span class="font-medium">Alasan Pembatalan:</span> {{ $booking->cancellation_reason }}</p>
                        @endif
                    </div>

                    <!-- Jadwal Penggunaan / Occurrences -->
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-500 mb-1.5">Jadwal Sesi Penggunaan:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($booking->occurrences as $occ)
                                <span class="inline-flex items-center gap-1.5 text-xs bg-slate-100 text-slate-700 rounded-lg px-2.5 py-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <strong>{{ $occ->usage_date->format('d/m/Y') }}</strong>
                                    <span>{{ substr($occ->start_time, 0, 5) }}–{{ substr($occ->end_time, 0, 5) }} WIB</span>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <!-- Pembatalan oleh Dosen: Hanya diperbolehkan saat Menunggu Persetujuan (pending) -->
                    @if($booking->status === 'pending' && $booking->user_id === auth()->id())
                        @can('room_booking_update')
                            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-end">
                                <form method="POST" action="{{ route('room-bookings.cancel', $booking) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pengajuan peminjaman ruangan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary text-rose-600 hover:text-rose-700 hover:bg-rose-50 border-rose-200">
                                        Batalkan Pengajuan
                                    </button>
                                </form>
                            </div>
                        @endcan
                    @elseif($booking->status === 'approved' && $booking->user_id === auth()->id())
                        <div class="mt-3 text-xs text-slate-400">
                            * Pengajuan telah disetujui. Untuk pembatalan peminjaman, silakan hubungi Admin Akademik.
                        </div>
                    @endif
                </article>
            @empty
                <div class="campus-card empty-state py-12 text-center">
                    <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <p class="empty-state-text font-medium text-slate-600">Belum ada pengajuan peminjaman ruangan.</p>
                    <p class="text-xs text-slate-400 mt-1">Gunakan tombol "Ajukan Peminjaman" untuk membuat pengajuan baru.</p>
                </div>
            @endforelse

            {{ $bookings->links() }}
        </div>
    </div>
</x-app-layout>
