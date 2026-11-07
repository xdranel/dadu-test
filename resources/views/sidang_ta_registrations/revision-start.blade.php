<x-app-layout>
    <x-slot name="header">
        Revisi Pendaftaran Sidang TA
    </x-slot>
    @php
        $revisionExpired = \App\Support\SidangTaRevisionDeadline::hasExpired($registration);
    @endphp
    <section class="campus-card space-y-4 p-5">
        <h2 class="font-bold">
            Perbaikan Pengajuan Anda
        </h2>
        <p>
            {{ $registration->review_notes ?: 'Periksa kembali data dan dokumen pengajuan.' }}
        </p>
        <p>
            Pengajuan yang sudah dikirim tetap tersimpan selama Anda mengerjakan draft revisi
        </p>
        <h3 class="text-base font-bold text-slate-800">Detail Revisi</h3>
        @include('sidang_ta_registrations.partials.revision-feedback')
        @if($revisionExpired)
            <p data-revision-deadline-expired role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                Batas waktu revisi telah berakhir. Memulai dan melanjutkan pengerjaan revisi sudah ditutup.
                Draft yang sudah tersimpan tetap tersedia di daftar pendaftaran untuk dilihat atau dihapus.
            </p>
        @endif
        <form method="POST" action="{{ route('sidang-ta-drafts.revision', $registration) }}">
            @csrf
            <button type="submit" @disabled($revisionExpired)
            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                Mulai atau lanjutkan draft revisi
            </button>
        </form>
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">
            Kembali
        </a>
    </section>
</x-app-layout>
