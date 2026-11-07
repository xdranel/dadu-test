<x-app-layout>
    <x-slot name="header">Riwayat Pengajuan Sidang TA</x-slot>
    <section class="campus-card mx-auto max-w-4xl space-y-5 p-5 sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">Riwayat Pengajuan — {{ $registration->period?->name }}</h2>
        <ol data-student-timeline class="ml-3 border-l border-slate-200">
            @forelse($events as $event)
                <li class="relative pb-7 pl-7">
                    <span aria-hidden="true" class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full {{ $event['action']==='needs_review' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">✓</span>
                    <p class="text-sm font-semibold text-slate-800">{{ $event['label'] }} <span class="font-normal text-slate-500">— {{ $event['time'] }}</span></p>
                    <p class="mt-2 text-sm text-slate-600">Pelaku: {{ $event['actor'] }}</p>
                    @if(filled($event['notes']))<p class="mt-2 whitespace-pre-line break-words text-sm text-slate-700">{{ $event['notes'] }}</p>@endif
                </li>
            @empty
                <li class="pl-7 text-sm text-slate-500">Belum ada riwayat pengajuan.</li>
            @endforelse
        </ol>
        <a href="{{ route('sidang-ta-registrations.index') }}" class="btn btn-secondary">Kembali</a>
    </section>
</x-app-layout>
