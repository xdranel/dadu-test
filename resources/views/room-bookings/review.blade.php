<x-app-layout>
    <x-slot name="header">Review Peminjaman Ruangan</x-slot>
    <div class="space-y-6 p-4 sm:p-6">

        @if(session('success'))
            <div class="p-3 bg-green-50 text-green-700 rounded">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="p-3 bg-red-50 text-red-700 rounded">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

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

        <section class="space-y-4" aria-labelledby="booking-requests-heading">
            <h2 id="booking-requests-heading" class="text-lg font-bold text-slate-800">Daftar Pengajuan</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                @forelse($bookings as $booking)
                    <article class="campus-card min-w-0 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="break-words font-semibold text-slate-800">{{ $booking->user->name }}</p>
                                    @if($booking->sidang_ta_registration_id || $booking->booking_origin === 'sidang_ta_automatic')
                                        <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-sky-100 text-sky-800 border border-sky-300">Sidang TA</span>
                                    @else
                                        <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700">Peminjaman</span>
                                    @endif
                                </div>
                                @php($lecturer = ($booking->user->employee_id && isset($lecturersByNip) ? $lecturersByNip->get(trim($booking->user->employee_id)) : null) ?? $lecturers->get(mb_strtolower(trim($booking->user->name))))
                                <p class="text-xs text-slate-500">NIP: {{ $lecturer->nip_ypt ?? $booking->user->employee_id ?? '-' }} · Prodi: {{ $lecturer->study_program ?? '-' }}</p>
                                <p class="text-sm text-slate-500">{{ $booking->room->room_code }} · {{ $booking->room->building ?: 'Lokasi belum diisi' }} · Kapasitas {{ $booking->room->capacity ?: '—' }}</p>
                            </div>
                            <span class="badge {{ match($booking->status) {
                                'pending'   => 'badge-warning',
                                'approved'  => 'badge-success',
                                'rejected'  => 'badge-danger',
                                'cancelled' => 'badge-secondary',
                                default     => 'badge-secondary'
                            } }} text-xs">{{ ucfirst($booking->status) }}</span>
                        </div>

                        <p class="mt-3 break-words text-sm text-slate-700"><span class="font-medium">Keperluan:</span> {{ $booking->purpose }}</p>

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

                        @php($canReviewBooking = !$booking->sidang_ta_registration_id || auth()->user()->can('sidang_ta_registration_review'))
                        @if($canReviewBooking && $booking->status === 'pending')
                            <form method="POST" action="{{ route('admin.room-bookings.decision', $booking) }}"
                                  onsubmit="if (this.dataset.submitting) return false; this.dataset.submitting = 'true';"
                                  class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                                @csrf
                                <input name="note" aria-label="Catatan admin" placeholder="Catatan (opsional)"
                                       class="campus-input basis-full">
                                <button type="submit" name="decision" value="approved"
                                        class="btn btn-success px-4">Setujui</button>
                                <button type="submit" name="decision" value="rejected"
                                        class="btn btn-danger px-4">Tolak</button>
                            </form>
                        @elseif($canReviewBooking && $booking->status === 'approved')
                            <form method="POST" action="{{ route('admin.room-bookings.cancel', $booking) }}"
                                  onsubmit="if(confirm('Batalkan seluruh jadwal dalam pengajuan ini? Pastikan alasan pembatalan sudah benar.')){ this.querySelector('button').disabled = true; return true; } return false;"
                                  class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                                @csrf
                                <input name="reason" required aria-label="Alasan pembatalan" placeholder="Alasan pembatalan"
                                       class="campus-input basis-full">
                                <button class="btn btn-danger px-4">Batalkan Peminjaman</button>
                            </form>
                        @endif

                        @if($booking->admin_note)
                            <div class="mt-4 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-sm text-sky-950">
                                <p class="font-semibold">Catatan admin</p>
                                <p class="whitespace-pre-wrap break-words">{{ $booking->admin_note }}</p>
                            </div>
                        @endif
                        @if($booking->cancellation_reason)
                            <p class="mt-2 text-xs text-red-500">Alasan pembatalan: {{ $booking->cancellation_reason }}</p>
                        @endif
                    </article>
                @empty
                    <div class="campus-card empty-state col-span-full py-10">
                        <p class="empty-state-text">Belum ada pengajuan peminjaman.</p>
                    </div>
                @endforelse
            </div>

            {{ $bookings->links() }}
        </section>
    </div>
</x-app-layout>
