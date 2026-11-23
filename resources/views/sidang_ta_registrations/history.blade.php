<x-app-layout>
    <x-slot name="header">Riwayat Pengajuan Sidang TA</x-slot>
    <section class="campus-card mx-auto max-w-4xl space-y-5 p-5 sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">Riwayat Pengajuan — {{ $registration->period?->name }}</h2>
        <x-sidang-ta.status-timeline :events="$events" empty-message="Belum ada riwayat pengajuan." data-student-timeline />
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
    </section>
</x-app-layout>
