<x-modal name="ta-verification-window-warning" maxWidth="md" focusable>
    <section data-ta-verification-warning role="dialog" aria-modal="true" aria-labelledby="ta-window-warning-title" class="space-y-4 p-6">
        <h3 id="ta-window-warning-title" class="text-lg font-bold text-slate-800">Perbarui jadwal Verifikasi Berkas</h3>
        <p class="text-sm text-slate-600">{{ $verificationWindowWarning }}</p>
        <p class="text-sm text-slate-600">Perpanjang atau sesuaikan tanggal verifikasi terlebih dahulu sebelum membuka kembali pengajuan atau meminta revisi. Perubahan tanggal berlaku untuk seluruh pengajuan pada periode tersebut.</p>
        <p class="text-sm text-slate-600">Setelah jadwal diperbarui, muat ulang halaman peninjauan.</p>
        @can('sidang_ta_period_read')
            <a data-extend-verification href="{{ route('admin.sidang-ta-periods.index') }}" class="btn btn-primary">Kelola Periode</a>
        @else
            <p class="text-sm text-slate-600">Hubungi petugas yang memiliki hak Kelola Periode untuk memperbarui jadwal.</p>
        @endcan
        <div class="flex justify-end"><button type="button" class="btn btn-secondary" x-on:click="$dispatch('close-modal', 'ta-verification-window-warning')">Tutup</button></div>
    </section>
</x-modal>
