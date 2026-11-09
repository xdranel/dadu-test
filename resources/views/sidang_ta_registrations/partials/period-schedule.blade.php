@if($schedulePeriod)
    <section data-period-schedule="{{ $schedulePeriod->id }}" class="space-y-3"
             @if($dynamicSchedule ?? false) x-show="selectedPeriodId === @js((string) $schedulePeriod->id)" @if((string) $schedulePeriod->id !== $selectedPeriod) style="display: none;" @endif @endif>
        <h3 class="break-words text-sm font-bold text-slate-800">{{ $schedulePeriod->name }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
            @include('sidang_ta_registrations.partials.period-countdown', [
                'scheduleEnabled' => (bool) $schedulePeriod->is_active, 'scheduleKind' => 'registration', 'scheduleTitle' => 'Pendaftaran Sidang TA',
                'scheduleOpen' => $schedulePeriod->open_at, 'scheduleClose' => $schedulePeriod->close_at])
            @include('sidang_ta_registrations.partials.period-countdown', [
                'scheduleEnabled' => true, 'scheduleKind' => 'revision', 'scheduleTitle' => 'Tanggal Verifikasi Berkas',
                'scheduleOpen' => $schedulePeriod->revision_open_at, 'scheduleClose' => $schedulePeriod->revision_close_at])
        </div>
        <p class="text-xs text-slate-500">Pendaftaran baru {{ $schedulePeriod->is_active ? 'diaktifkan' : 'dinonaktifkan' }}. Jadwal verifikasi berkas berlaku untuk revisi pengajuan.</p>
    </section>
@endif
