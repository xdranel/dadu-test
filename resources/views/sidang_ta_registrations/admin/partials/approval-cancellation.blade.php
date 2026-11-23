<section class="campus-card space-y-4 p-5">
    <h3 class="font-bold text-slate-800">Keputusan petugas</h3>
    <p class="text-sm text-slate-600">Pengajuan telah disetujui. Persetujuan dapat dibatalkan selama jadwal Verifikasi Berkas masih aktif.</p>
    @if($approvalCancellationBlocker !== null)
        <p data-ta-cancel-blocker role="status" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $approvalCancellationBlocker }}</p>
    @endif
    @if($errors->has('registration_version'))
        <a data-ta-reload href="{{ route('admin.sidang-ta-registrations.show', $registration) }}" class="btn btn-secondary">Muat ulang pengajuan</a>
    @endif
    <div class="flex justify-end">
        <button data-ta-cancel-approval type="button" @disabled($approvalCancellationBlocker !== null)
                x-on:click="$dispatch('open-modal', 'cancel-ta-approval')"
                class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50">Batalkan Persetujuan</button>
    </div>
</section>
<x-modal name="cancel-ta-approval" maxWidth="md" focusable>
    <div role="dialog" aria-modal="true" aria-labelledby="cancel-ta-approval-title">
        <form data-ta-cancel-form method="POST" action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="space-y-4 p-6">
            @csrf
            <input type="hidden" name="action" value="cancel_approval">
            <input type="hidden" name="registration_version" value="{{ $registration->lock_version }}">
            <h3 id="cancel-ta-approval-title" class="text-lg font-bold text-slate-800">Batalkan persetujuan?</h3>
            <p class="text-sm text-slate-600">Pengajuan akan kembali menunggu peninjauan. Status verifikasi berkas, dokumen, dan catatan tetap dipertahankan.</p>
            @php($linkedBooking=$registration->roomSchedule?->booking)
            @if($linkedBooking && in_array($linkedBooking->status,['pending','approved']))
                <div class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">Booking ruangan tertaut akan ikut dibatalkan dan slotnya kembali tersedia.</p>
                    <p>Booking #{{ $linkedBooking->id }} · {{ $linkedBooking->room?->room_code }}</p>
                    @foreach($linkedBooking->occurrences as $occurrence)<p>{{ $occurrence->usage_date->format('d/m/Y') }} · {{ substr($occurrence->start_time,0,5) }}–{{ substr($occurrence->end_time,0,5) }} WIB</p>@endforeach
                    <p>Penjadwalan pengganti harus ditangani SSC; riwayat tetap disimpan.</p>
                </div>
            @endif
            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'cancel-ta-approval')" class="btn btn-secondary">Kembali</button>
                <button data-ta-cancel-confirm type="submit" @disabled($approvalCancellationBlocker !== null)
                        class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50">Ya, batalkan persetujuan</button>
            </div>
        </form>
    </div>
</x-modal>
