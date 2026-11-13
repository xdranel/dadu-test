<section class="campus-card space-y-4 p-5">
    <h3 class="font-bold text-slate-800">Keputusan petugas</h3>
    <p class="text-sm text-slate-600">Pengajuan ditolak dapat ditinjau kembali selama jadwal Verifikasi Berkas aktif dan sedang berlangsung. Status verifikasi berkas tetap dipertahankan.</p>
    <div class="flex justify-end">
        <button data-ta-reopen type="button" class="btn btn-secondary"
                x-on:click="$dispatch('open-modal', '{{ $verificationWindowWarning !== null ? 'ta-verification-window-warning' : 'reopen-ta-registration' }}')">Buka Kembali Pengajuan</button>
    </div>
</section>
@if($verificationWindowWarning === null)
    <x-modal name="reopen-ta-registration" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-labelledby="reopen-ta-title">
            <form method="POST" action="{{ route('admin.sidang-ta-registrations.review', $registration) }}" class="space-y-4 p-6">
                @csrf
                <input type="hidden" name="action" value="reopen_registration">
                <input type="hidden" name="registration_version" value="{{ $registration->lock_version }}">
                <h3 id="reopen-ta-title" class="text-lg font-bold text-slate-800">Buka kembali pengajuan?</h3>
                <p class="text-sm text-slate-600">Pengajuan kembali Menunggu peninjauan. Berkas final, catatan, dan status verifikasinya tetap dipertahankan. Revisi tetap mengikuti jadwal Verifikasi Berkas.</p>
                <p class="text-sm text-slate-600">Draft revisi lama yang masih tersisa akan diganti dengan draft baru dari berkas pengajuan saat mahasiswa mulai merevisi.</p>
                <div class="flex flex-wrap justify-end gap-2"><button type="button" class="btn btn-secondary" x-on:click="$dispatch('close-modal', 'reopen-ta-registration')">Kembali</button><button type="submit" class="btn btn-primary">Ya, buka kembali</button></div>
            </form>
        </div>
    </x-modal>
@endif
