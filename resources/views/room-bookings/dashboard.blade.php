<x-app-layout>
    <x-slot name="header">Dashboard Peminjaman Ruangan</x-slot>

    @php
        $monthKey = $month->format('Y-m');
        $dateLabel = \Carbon\Carbon::parse($selectedDate, 'Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y');
        $days = [
            'past' => ['Lewat', 'border-slate-200 bg-slate-100 text-slate-600'],
            'available' => ['Ada slot', 'border-emerald-200 bg-emerald-50 text-emerald-900'],
            'unavailable' => ['Penuh', 'border-rose-200 bg-rose-50 text-rose-900'],
            'unknown' => ['Jadwal belum tersedia', 'border-slate-200 bg-slate-50 text-slate-600'],
        ];
        $roomColors = [
            'available' => ['Kosong', 'border-emerald-300 bg-emerald-50 text-emerald-900'],
            'teaching' => ['Perkuliahan', 'border-sky-300 bg-sky-50 text-sky-900'],
            'booked' => ['Peminjaman', 'border-amber-400 bg-amber-100 text-amber-950'],
            'full' => ['Penuh', 'border-rose-300 bg-rose-100 text-rose-950'],
            'unknown' => ['Jadwal belum tersedia', 'border-slate-300 bg-slate-100 text-slate-700'],
            'inactive' => ['Tidak dapat dipakai', 'border-slate-300 bg-white text-slate-500'],
        ];
    @endphp

    <div x-data="roomBookingDashboard" class="space-y-5">
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error') || $errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                {{ session('error') ?? $errors->first() }}
            </div>
        @endif

        <section class="campus-card space-y-4 p-4 sm:p-5" aria-labelledby="room-calendar-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="room-calendar-title" class="font-bold text-slate-900">
                    Kalender Ketersediaan Ruangan · {{ $month->copy()->locale('id')->translatedFormat('F Y') }}
                </h2>
                <nav aria-label="Navigasi bulan kalender" class="flex flex-wrap gap-2">
                    <a class="btn btn-secondary" href="{{ route('room-bookings.index', ['month' => $month->copy()->subMonthNoOverflow()->format('Y-m')] + $roomFilters) }}">‹ Sebelumnya</a>
                    <a class="btn btn-secondary" href="{{ route('room-bookings.index', ['month' => $month->copy()->addMonthNoOverflow()->format('Y-m')] + $roomFilters) }}">Berikutnya ›</a>
                </nav>
            </div>

            <div class="grid grid-cols-7 gap-1 text-xs sm:gap-2">
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                    <div class="py-1 text-center font-semibold text-slate-600">{{ $weekday }}</div>
                @endforeach
                @for($blank = 1; $blank < $month->dayOfWeekIso; $blank++)
                    <span aria-hidden="true"></span>
                @endfor
                @foreach($calendar as $date => $availability)
                    @php([$dayLabel, $dayColor] = $days[$date < $today ? 'past' : $availability])
                    <a href="{{ route('room-bookings.index', ['month' => $monthKey, 'date' => $date] + $roomFilters) }}"
                       data-calendar-date="{{ $date }}" @if($date === $selectedDate) aria-current="true" @endif
                       title="{{ $date }} · {{ $dayLabel }}"
                       class="flex min-h-[44px] flex-col items-center justify-center gap-0.5 rounded-lg border px-1 py-1 font-semibold hover:brightness-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#DA2D3B] sm:min-h-[52px] {{ $dayColor }} {{ $date === $selectedDate ? 'ring-2 ring-[#DA2D3B]' : '' }}">
                        <span class="text-sm">{{ (int) substr($date, 8, 2) }}</span>
                        <span class="sr-only sm:not-sr-only sm:text-[10px]">{{ $date === $today ? 'Hari ini' : $dayLabel }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <form method="GET" action="{{ route('room-bookings.index') }}" class="campus-card grid items-end gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.7fr)_auto]" aria-label="Filter ruang">
            <input type="hidden" name="month" value="{{ $monthKey }}">
            <input type="hidden" name="date" value="{{ $selectedDate }}">
            <label class="campus-label !mb-0">Kode Ruang
                <input name="search" class="campus-input mt-1" placeholder="Cari kode ruang" value="{{ $roomFilters['search'] ?? '' }}">
            </label>
            <label class="campus-label !mb-0">Gedung
                <select name="building" class="campus-select mt-1">
                    <option value="">Semua gedung</option>
                    @foreach($buildings as $building)
                        <option value="{{ $building }}" @selected(($roomFilters['building'] ?? '') === $building)>{{ $building }}</option>
                    @endforeach
                </select>
            </label>
            <label class="campus-label !mb-0">Lantai
                <select name="floor" class="campus-select mt-1">
                    <option value="">Semua lantai</option>
                    @foreach($floors as $floor)
                        <option value="{{ $floor }}" @selected(($roomFilters['floor'] ?? '') == $floor)>Lantai {{ $floor }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Terapkan</button>
                <a class="btn btn-secondary" href="{{ route('room-bookings.index', ['month' => $monthKey, 'date' => $selectedDate]) }}">Reset</a>
            </div>
        </form>

        <section class="campus-card space-y-5 p-4 sm:p-5" aria-labelledby="floor-plan-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="floor-plan-title" class="font-bold text-slate-900">Denah Ruangan</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $dateLabel }}</p>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs font-medium text-slate-700">
                    @foreach($roomColors as [$label, $color])
                        <span class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="h-3 w-3 rounded border {{ $color }}"></span>{{ $label }}</span>
                    @endforeach
                </div>
            </div>

            @forelse($plans as $plan)
                <section aria-labelledby="plan-{{ $plan['key'] }}" class="space-y-2">
                    <h3 id="plan-{{ $plan['key'] }}" class="text-sm font-semibold text-slate-800">{{ $plan['label'] }}</h3>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-2 sm:p-3">
                        <div class="relative min-w-[680px]" style="aspect-ratio: {{ $plan['width'] }} / {{ $plan['height'] }}">
                            @foreach($plan['tiles'] as $tile)
                                @include('room-bookings.partials.room-tile', ['room' => $tile['room']])
                            @endforeach
                        </div>
                    </div>
                </section>
            @empty
                <p class="rounded-lg border border-dashed border-slate-200 p-4 text-sm text-slate-600">Tidak ada ruangan yang cocok dengan filter.</p>
            @endforelse
        </section>

        @include('room-bookings.partials.room-day-dialog')
    </div>
</x-app-layout>
