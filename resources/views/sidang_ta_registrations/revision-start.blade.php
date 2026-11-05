<x-app-layout>
    <x-slot name="header">
        Revisi Pendaftaran Sidang TA
    </x-slot>
    <section class="campus-card space-y-4 p-5">
        <h2 class="font-bold">
            Perbaiki pengajuan Anda
        </h2>
        <p>
            {{ $registration->review_notes ?: 'Periksa kembali data dan dokumen pengajuan.' }}
        </p>
        <p>
            Pengajuan yang sudah dikirim tetap tersimpan selama Anda mengerjakan draft revisi
        </p>
        @include('sidang_ta_registrations.partials.revision-feedback')
        <form method="POST" action="{{ route('sidang-ta-drafts.revision', $registration) }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                Mulai atau lanjutkan draft revisi
            </button>
        </form>
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">
            Kembali
        </a>
    </section>
</x-app-layout>
