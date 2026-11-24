@php
    $lecturer = $identity->lecturer($booking->user->email, $booking->user->employee_id);
    $program = trim((string) $lecturer?->study_program);
    $affiliation = $program !== '' && $program !== '-' ? $program : ($booking->user->unit?->name ?? '—');
    $schedules = $booking->occurrences->groupBy(fn ($usage) => substr($usage->start_time, 0, 5).'–'.substr($usage->end_time, 0, 5));
    $canReviewBooking = !$history && (!$booking->sidang_ta_registration_id || auth()->user()->can('sidang_ta_registration_review'));
@endphp
<article class="campus-card space-y-4 p-4 sm:p-5" x-data>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="break-words font-semibold text-slate-900">{{ $booking->user->name }}</h3>
                @if($booking->sidang_ta_registration_id || $booking->booking_origin === 'sidang_ta_automatic')
                    <span class="rounded border border-sky-300 bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-sky-800">Sidang TA</span>
                @else
                    <span class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">Peminjaman</span>
                @endif
            </div>
            <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm text-slate-700 sm:grid-cols-2">
                <div><dt class="inline font-semibold">Prodi/Unit</dt><dd class="inline"> · {{ $affiliation }}</dd></div>
                <div><dt class="inline font-semibold">Kode Ruangan</dt><dd class="inline"> · {{ $booking->room->room_code }}</dd></div>
            </dl>
        </div>
        <span class="badge {{ match($booking->status) {
            'pending' => 'badge-warning',
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-secondary',
        } }} text-xs">{{ ucfirst($booking->status) }}</span>
    </div>

    <p class="break-words text-sm text-slate-700"><span class="font-semibold">Keperluan</span> · {{ $booking->purpose }}</p>

    <div>
        <h4 class="mb-2 text-sm font-semibold text-slate-800">Jadwal Penggunaan</h4>
        <div class="flex flex-wrap gap-2">
            @forelse($schedules as $hours => $uses)
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <ul class="space-y-0.5">
                        @foreach($uses as $usage)
                            <li>{{ $usage->usage_date->copy()->locale('id')->translatedFormat('l, d-M-Y') }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-1 font-semibold">{{ $hours }} WIB</p>
                </div>
            @empty
                <p class="text-sm text-slate-600">{{ $booking->start_date->copy()->locale('id')->translatedFormat('l, d-M-Y') }}@if(!$booking->start_date->isSameDay($booking->end_date)) s.d. {{ $booking->end_date->copy()->locale('id')->translatedFormat('l, d-M-Y') }}@endif · {{ substr($booking->start_time, 0, 5) }}–{{ substr($booking->end_time, 0, 5) }} WIB</p>
            @endforelse
        </div>
    </div>

    @if($booking->admin_note)
        <div class="rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-sm text-sky-950">
            <p class="font-semibold">Catatan admin</p>
            <p class="whitespace-pre-wrap break-words">{{ $booking->admin_note }}</p>
        </div>
    @endif
    @if($booking->cancellation_reason && $booking->cancellation_reason !== $booking->admin_note)
        <p class="text-sm text-rose-800"><span class="font-semibold">Alasan pembatalan</span> · {{ $booking->cancellation_reason }}</p>
    @endif

    @if($canReviewBooking && $booking->status === 'pending')
        <form method="POST" action="{{ route('admin.room-bookings.decision', $booking) }}"
              onsubmit="if (this.dataset.submitting) return false; this.dataset.submitting = 'true';"
              class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            @csrf
            <input name="note" aria-label="Catatan admin" placeholder="Catatan (opsional)" class="campus-input basis-full" maxlength="1000">
            <button type="submit" name="decision" value="approved" class="btn btn-success px-4">Setujui</button>
            <button type="submit" name="decision" value="rejected" class="btn btn-danger-outline px-4">Tolak</button>
        </form>
    @elseif($canReviewBooking && $booking->status === 'approved')
        <button type="button" class="btn btn-primary" aria-haspopup="dialog" @click="$refs.cancelDialog.showModal()">Batalkan Peminjaman</button>
        <dialog x-ref="cancelDialog" aria-labelledby="cancel-booking-{{ $booking->id }}" @click.self="$refs.cancelDialog.close()"
                class="fixed inset-0 m-auto h-fit max-h-[calc(100dvh_-_2rem)] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-2xl p-0 shadow-xl backdrop:bg-slate-900/55">
            <form method="POST" action="{{ route('admin.room-bookings.cancel', $booking) }}"
                  onsubmit="if (this.dataset.submitting) return false; this.dataset.submitting = 'true';"
                  class="space-y-4 p-5 sm:p-6">
                @csrf
                <h4 id="cancel-booking-{{ $booking->id }}" class="text-lg font-bold text-slate-900">Konfirmasi pembatalan</h4>
                <p class="text-sm text-slate-700">Semua jadwal dalam pengajuan {{ $booking->room->room_code }} untuk {{ $booking->user->name }} akan dibatalkan dan slotnya kembali tersedia.</p>
                <label class="campus-label" for="reason-{{ $booking->id }}">Alasan pembatalan <span class="text-rose-600">*</span>
                    <textarea id="reason-{{ $booking->id }}" name="reason" required maxlength="1000" rows="3" class="campus-input mt-1"></textarea>
                </label>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" autofocus class="btn btn-secondary" @click="$refs.cancelDialog.close()">Kembali</button>
                    <button type="submit" class="btn btn-primary">Ya, Batalkan Peminjaman</button>
                </div>
            </form>
        </dialog>
    @endif
</article>
