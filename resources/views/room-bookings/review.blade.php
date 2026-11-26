<x-app-layout>
    <x-slot name="header">{{ isset($month) ? 'Riwayat Peminjaman Ruangan' : 'Review Peminjaman Ruangan' }}</x-slot>

    <div class="space-y-6 p-4 sm:p-6">
        @if(session('success'))
            <div role="status" class="rounded bg-green-50 p-3 text-green-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded bg-red-50 p-3 text-red-700">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @php($bookingFilters = \Illuminate\Support\Arr::only($filters, ['room_code', 'date']))
        <section class="space-y-4" aria-labelledby="booking-requests-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="booking-requests-heading" class="text-lg font-bold text-slate-800">
                    {{ isset($month) ? 'Riwayat Peminjaman' : 'Daftar Pengajuan' }}
                </h2>
                @if(isset($month))
                    <a class="btn btn-secondary" href="{{ route('admin.room-bookings.review', $bookingFilters) }}">Kembali ke Daftar Pengajuan</a>
                @else
                    <a class="btn btn-secondary" href="{{ route('admin.room-bookings.history', $bookingFilters) }}">Riwayat</a>
                @endif
            </div>

            @php($filterRoute = isset($month) ? 'admin.room-bookings.history' : 'admin.room-bookings.review')
            <form method="GET" action="{{ route($filterRoute) }}" class="campus-card flex flex-wrap items-end gap-3 p-4">
                <label class="campus-label !mb-0 w-full sm:w-64" for="booking-room-code">Kode Ruangan
                    <input id="booking-room-code" name="room_code" value="{{ $filters['room_code'] ?? '' }}" maxlength="50"
                           class="campus-input mt-1" placeholder="Cari kode ruangan">
                </label>
                <label class="campus-label !mb-0" for="booking-usage-date">Tanggal Penggunaan
                    <input id="booking-usage-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                           class="campus-input mt-1">
                </label>
                @if(isset($month))
                    <label class="campus-label !mb-0" for="booking-history-month">Bulan Arsip
                        <select id="booking-history-month" name="month" class="campus-select mt-1 min-w-48">
                            @foreach($months as $value => $label)
                                <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <a class="btn btn-secondary" href="{{ route($filterRoute, isset($month) ? ['month' => $month] : []) }}">Reset</a>
                </div>
            </form>

            @forelse($bookings as $booking)
                @include('room-bookings.partials.review-card', ['history' => isset($month)])
            @empty
                <div class="campus-card empty-state py-10">
                    <p class="empty-state-text">{{ filled($filters['room_code'] ?? null) || filled($filters['date'] ?? null) ? 'Tidak ada peminjaman yang cocok dengan filter.' : (isset($month) ? 'Belum ada riwayat peminjaman pada bulan ini.' : 'Belum ada pengajuan peminjaman.') }}</p>
                </div>
            @endforelse

            {{ $bookings->links() }}
        </section>
    </div>
</x-app-layout>
