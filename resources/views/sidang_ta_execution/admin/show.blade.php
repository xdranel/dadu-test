<x-app-layout>
    <x-slot name="header">Detail Pelaksanaan Sidang TA</x-slot>

    <div class="space-y-5">
        @include('sidang_ta_execution.partials.notices')

        @php
            $context = $registration->roomSchedule;
            $executionWindow = $registration->period?->executionSetting;
            $minimumDate = $executionWindow?->start_date?->format('Y-m-d');
            $maximumDate = $executionWindow?->end_date?->format('Y-m-d');
            $minimumTime = substr($executionWindow?->start_time ?? '', 0, 5);
            $maximumTime = substr($executionWindow?->end_time ?? '', 0, 5);
            $canChooseDate = $executionWindow?->is_active && $minimumDate && $maximumDate >= $minimumDate;
            $booking = $context?->booking;
            $currentOccurrence = $booking?->occurrences->first();
            $canEditPlotting = auth()->user()->can('sidang_ta_registration_review')
                && $registration->status === 'approved'
                && ($context?->result_status ?? 'pending') === 'pending'
                && $canChooseDate
                && $booking?->status !== 'pending'
                && ($booking?->status !== 'approved' || $booking->occurrences->count() === 1);
            $searchValue = fn (string $field, ?string $fallback) => old('editing_plotting') !== '1' && is_string(old($field))
                ? old($field) : $fallback;
        @endphp
        @include('sidang_ta_execution.partials.info', ['canEditPlotting' => $canEditPlotting])

        @can('sidang_ta_registration_review')
            @php
                $hasFinalOutcome = in_array($context?->result_status, ['passed', 'failed'], true);
                $roomsById = $rooms->keyBy('id');
                $initialDate = $preview['slot']['date'] ?? $context?->preferred_date?->format('Y-m-d') ?? $currentOccurrence?->usage_date?->format('Y-m-d');
                $initialStartTime = substr($preview['slot']['start_time'] ?? $context?->preferred_start_time ?? $currentOccurrence?->start_time ?? '', 0, 5);
                $initialEndTime = substr($preview['slot']['end_time'] ?? $context?->preferred_end_time ?? $currentOccurrence?->end_time ?? '', 0, 5);
            @endphp

            @if($registration->status === 'approved' && !$hasFinalOutcome)
                <section class="campus-card space-y-4 p-5">
                    <h2 class="font-bold">{{ $context?->was_confirmed ? 'Cari jadwal pengganti' : 'Tetapkan jadwal sidang' }}</h2>
                    <p class="text-sm text-slate-500">Cari ruang yang tersedia pada tanggal dan jam yang dipilih. Semua jam menggunakan WIB.</p>
                    <p class="text-xs text-slate-500">Ketersediaan ruangan belum memastikan kehadiran pembimbing dan penguji.</p>

                    <form method="POST" action="{{ route('admin.sidang-ta-execution.preview', $registration) }}">
                        @csrf
                        <fieldset class="flex flex-wrap items-end gap-3" @disabled(!$canChooseDate)>
                            <label class="campus-label">Tanggal <span class="text-rose-600">*</span>
                                <input required type="date" name="date" min="{{ $minimumDate }}" max="{{ $maximumDate }}"
                                       aria-describedby="execution-date-help" class="campus-input mt-1"
                                       value="{{ $searchValue('date', $initialDate) }}">
                            </label>
                            <label class="campus-label">Jam mulai <span class="text-rose-600">*</span>
                                <input required type="time" name="start_time" min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1"
                                       value="{{ $searchValue('start_time', $initialStartTime) }}">
                            </label>
                            <label class="campus-label">Jam selesai <span class="text-rose-600">*</span>
                                <input required type="time" name="end_time" min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1"
                                       value="{{ $searchValue('end_time', $initialEndTime) }}">
                            </label>
                            <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50">Cari ketersediaan</button>
                        </fieldset>
                    </form>

                    <p id="execution-date-help" class="text-xs text-slate-500">
                        @if($canChooseDate)
                            Pilih tanggal {{ $executionWindow->start_date->format('d/m/Y') }}–{{ $executionWindow->end_date->format('d/m/Y') }} sesuai hari pelaksanaan yang diaktifkan.
                        @else
                            Pencarian belum tersedia. Atur atau aktifkan rentang Pelaksanaan Sidang TA terlebih dahulu.
                        @endif
                    </p>

                    @if($preview !== null)
                        @if(count($preview['options']))
                            <form method="POST" action="{{ route('admin.sidang-ta-execution.confirm', $registration) }}" class="space-y-3">
                                @csrf
                                <label class="campus-label">Pilihan ruang dan waktu
                                    <select required name="preview_token" class="campus-select mt-1">
                                        @foreach($preview['options'] as $option)
                                            @php
                                                $candidate = $option['candidate'];
                                                $room = $roomsById->get($candidate['room_id']);
                                            @endphp
                                            <option value="{{ $option['token'] }}" @selected($option['recommended'])>
                                                {{ $option['recommended'] ? 'Rekomendasi · ' : '' }}{{ $candidate['room_code'] }}
                                                · {{ $room?->building ?: 'Lokasi belum diisi' }} · kapasitas {{ $room?->capacity ?: '—' }}
                                                · {{ \Carbon\Carbon::parse($candidate['date'])->format('d/m/Y') }} · {{ substr($candidate['start_time'], 0, 5) }}–{{ substr($candidate['end_time'], 0, 5) }} WIB
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <p class="text-xs text-slate-500">Ketersediaan diperiksa kembali saat disimpan.</p>
                                <button class="btn btn-primary">Tetapkan Jadwal</button>
                            </form>
                        @else
                            <p role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">{{ $preview['reason'] ?: 'Belum ada ruang yang tersedia pada tanggal dan jam ini.' }}</p>
                        @endif
                    @endif
                </section>

                @if($context?->was_confirmed)
                    <div @class(['grid gap-4', 'lg:grid-cols-2' => !$canEditPlotting])>
                        @unless($canEditPlotting)
                        <form class="campus-card p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.reschedule', $registration) }}">
                            @csrf
                            <input type="hidden" name="context_version" value="{{ $context->lock_version }}">
                            <fieldset class="space-y-3" @disabled(!$canChooseDate)>
                                <h2 class="font-bold">Ubah jadwal</h2>
                                <label class="campus-label">Tanggal
                                    <input required type="date" name="date" min="{{ $minimumDate }}" max="{{ $maximumDate }}" class="campus-input mt-1">
                                </label>
                                <label class="campus-label">Ruang kelas
                                    <select name="room_id" required class="campus-select mt-1">
                                        @foreach($rooms->filter(fn ($room) => $room->isBookableClassroom()) as $room)
                                            <option value="{{ $room->id }}">{{ $room->room_code }} · {{ $room->building ?: 'Lokasi belum diisi' }} · kapasitas {{ $room->capacity ?: '—' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="campus-label">Jam mulai
                                        <input required type="time" name="start_time" min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1">
                                    </label>
                                    <label class="campus-label">Jam selesai
                                        <input required type="time" name="end_time" min="{{ $minimumTime }}" max="{{ $maximumTime }}" class="campus-input mt-1">
                                    </label>
                                </div>
                                <label class="campus-label">Alasan perubahan
                                    <textarea required maxlength="2000" name="reason" class="campus-input mt-1"></textarea>
                                </label>
                                <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50">Simpan perubahan jadwal</button>
                            </fieldset>
                        </form>
                        @endunless
                        @if(in_array($booking?->status, ['pending', 'approved'], true))
                            <form class="campus-card space-y-3 p-5" method="POST" action="{{ route('admin.sidang-ta-bookings.cancel', $registration) }}">
                                @csrf
                                <input type="hidden" name="context_version" value="{{ $context->lock_version }}">
                                <h2 class="font-bold">Batalkan jadwal</h2>
                                <p class="text-sm text-slate-500">Ruangan akan kembali tersedia. Riwayat pelaksanaan tetap tersimpan.</p>
                                <label class="campus-label">Alasan pembatalan
                                    <textarea required maxlength="2000" name="reason" class="campus-input mt-1"></textarea>
                                </label>
                                <button class="btn btn-secondary">Batalkan jadwal</button>
                            </form>
                        @endif
                    </div>
                @endif
            @endif

            @if($context && ($hasFinalOutcome || app(\App\Services\SidangTaExecutionOutcomeService::class)->canRecord($registration, $context)))
                <form class="campus-card space-y-4 p-5" method="POST" action="{{ route('admin.sidang-ta-execution.outcome', $registration) }}">
                    @csrf
                    <input type="hidden" name="context_version" value="{{ $context->lock_version }}">
                    <h2 class="font-bold">Hasil sidang</h2>
                    <label class="campus-label">Catatan
                        <textarea name="notes" maxlength="2000" class="campus-input mt-1"></textarea>
                    </label>
                    @if(!$hasFinalOutcome)
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" name="status" value="passed" class="btn btn-success">Lulus</button>
                            <button type="submit" name="status" value="failed" class="btn btn-danger-outline">Gagal</button>
                        </div>
                    @else
                        <p class="text-sm">Keputusan tersimpan <span class="font-semibold">{{ $context->result_status === 'passed' ? 'Lulus' : 'Gagal' }}</span></p>
                        <button type="submit" name="status" value="pending" class="btn btn-secondary">{{ $context->result_status === 'passed' ? 'Batalkan Lulus' : 'Batalkan Gagal' }}</button>
                    @endif
                </form>
            @endif
        @endcan

        @include('sidang_ta_execution.partials.history')
        <a class="btn btn-secondary" href="{{ route('admin.sidang-ta-execution.index') }}">Kembali</a>
    </div>
</x-app-layout>
