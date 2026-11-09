@php
    $countdownConfigured = $scheduleOpen && $scheduleClose;
    $scheduleEnabled = $scheduleEnabled ?? true;
    $countdownStatus = !$scheduleEnabled ? 'Tidak aktif' : (!$countdownConfigured ? 'Belum diatur'
        : (now()->lt($scheduleOpen) ? 'Belum dibuka' : (now()->gt($scheduleClose) ? 'Ditutup' : 'Aktif')));
@endphp
<div data-schedule="{{ $scheduleKind }}" class="min-w-0 rounded-xl border border-rose-100 bg-rose-50/50 p-4"
     @if($countdownConfigured && $scheduleEnabled) x-data="sidangTaPeriodCountdown(@js($scheduleOpen->getTimestampMs()), @js($scheduleClose->getTimestampMs()))" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 class="text-sm font-bold text-slate-800">{{ $scheduleTitle }}</h4>
        <span data-period-status @if($countdownConfigured && $scheduleEnabled) x-text="status" @endif
              class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-700">{{ $countdownStatus }}</span>
    </div>
    @if($countdownConfigured)
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            Mulai: {{ $scheduleOpen->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB<br>
            Selesai: {{ $scheduleClose->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
        </p>
        @if($scheduleEnabled)
        <p class="mt-2 text-xs text-slate-500" x-text="status === 'Belum dibuka' ? 'Menuju pembukaan' : (status === 'Ditutup' ? 'Jadwal telah berakhir' : 'Sisa waktu')">{{ $countdownStatus === 'Belum dibuka' ? 'Menuju pembukaan' : ($countdownStatus === 'Ditutup' ? 'Jadwal telah berakhir' : 'Sisa waktu') }}</p>
        <div data-period-countdown class="mt-3 grid grid-cols-4 gap-2" aria-label="Hitung mundur {{ $scheduleTitle }}" aria-live="off">
            @foreach(['days' => 'Hari', 'hours' => 'Jam', 'minutes' => 'Menit', 'seconds' => 'Detik'] as $unit => $unitLabel)
                <div class="min-w-0 rounded-lg border border-rose-100 bg-white px-1 py-2 text-center shadow-sm">
                    <span data-countdown-unit="{{ $unit }}" x-text="String({{ $unit }}).padStart(2, '0')" class="block text-xl font-bold tabular-nums text-[#DA2D3B]">00</span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $unitLabel }}</span>
                </div>
            @endforeach
        </div>
        @else
            <p class="mt-2 text-xs text-slate-500">Pendaftaran baru dinonaktifkan.</p>
        @endif
    @else
        <p data-schedule-unconfigured class="mt-2 text-xs leading-relaxed text-slate-600">Jadwal belum diatur. Revisi tetap dapat dikerjakan tanpa tenggat otomatis sampai jadwal ditetapkan petugas.</p>
    @endif
</div>
