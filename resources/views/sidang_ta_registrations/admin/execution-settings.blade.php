<x-app-layout>
    <x-slot name="header">Kelola Pelaksanaan Sidang TA</x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        <header>
            <h2 class="text-xl font-bold text-slate-800">Jadwal Pelaksanaan Sidang TA</h2>
            <p class="mt-1 text-sm text-slate-500">Atur tanggal, hari, dan jam pelaksanaan setiap periode. Semua jam menggunakan WIB.</p>
        </header>
        @include('sidang_ta_execution.partials.notices')

        @foreach($periods as $period)
            @php
                $setting = $period->executionSetting;
                $historical = $setting && (!$setting->is_active || $setting->end_date->lt(today('Asia/Jakarta')));
                $allowedWeekdays = array_map('intval', $setting?->allowed_weekdays ?? [1, 2, 3, 4, 5]);
            @endphp
            <details class="campus-card group p-5" @if(!$historical) open @endif>
                <summary class="flex cursor-pointer items-center justify-between font-bold text-slate-800">
                    <span>{{ $period->name }}
                        @if($historical)<span class="ml-2 text-xs font-normal text-slate-500">Riwayat pelaksanaan</span>@endif
                    </span>
                    <span aria-hidden="true">⌄</span>
                </summary>
                <form method="POST" action="{{ route('admin.sidang-ta-execution.update', $period) }}" class="mt-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="lock_version" value="{{ $setting?->lock_version ?? 0 }}">
                    <input type="hidden" name="break_start_time" value="">
                    <input type="hidden" name="break_end_time" value="">
                    <input type="hidden" name="is_active" value="0">

                    <fieldset class="space-y-4" @cannot('sidang_ta_period_update') disabled @endcannot>
                        <label class="campus-label block">Semester sumber jadwal <span class="text-rose-600">*</span>
                            <select required name="academic_period_id" class="campus-select mt-1">
                                <option value="">Pilih semester</option>
                                @foreach($academicPeriods as $academicPeriod)
                                    <option value="{{ $academicPeriod->id }}" @selected($setting?->academic_period_id === $academicPeriod->id)>{{ $academicPeriod->label }} · {{ $academicPeriod->year }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="campus-label">Tanggal mulai <span class="text-rose-600">*</span>
                                <input required type="date" name="start_date" value="{{ $setting?->start_date?->format('Y-m-d') }}" class="campus-input mt-1">
                            </label>
                            <label class="campus-label">Jam mulai <span class="text-rose-600">*</span>
                                <input required type="time" name="start_time" value="{{ $setting ? substr($setting->start_time, 0, 5) : '08:00' }}" class="campus-input mt-1">
                            </label>
                            <label class="campus-label">Tanggal selesai <span class="text-rose-600">*</span>
                                <input required type="date" name="end_date" value="{{ $setting?->end_date?->format('Y-m-d') }}" class="campus-input mt-1">
                            </label>
                            <label class="campus-label">Jam selesai <span class="text-rose-600">*</span>
                                <input required type="time" name="end_time" value="{{ $setting ? substr($setting->end_time, 0, 5) : '16:00' }}" class="campus-input mt-1">
                            </label>
                        </div>
                        <fieldset>
                            <legend class="campus-label mb-2">Hari pelaksanaan <span class="text-rose-600">*</span></legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach([1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $number => $day)
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 has-checked:border-sky-500 has-checked:bg-sky-50">
                                        <input type="checkbox" name="allowed_weekdays[]" value="{{ $number }}" class="size-4 rounded border-slate-300 text-sky-700 focus:ring-sky-600" @checked(in_array($number, $allowedWeekdays, true))>
                                        {{ $day }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div>
                            <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 has-checked:border-sky-500 has-checked:bg-sky-50">
                                <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-slate-300 text-sky-700 focus:ring-sky-600" @checked($setting?->is_active ?? true)>
                                Aktifkan pencarian jadwal periode ini
                            </label>
                        </div>
                        @can('sidang_ta_period_update')
                            <button class="btn btn-primary" type="submit">Simpan pelaksanaan</button>
                        @endcan
                    </fieldset>
                </form>
            </details>
        @endforeach

        <a class="btn btn-secondary" href="{{ route(auth()->user()->can('sidang_ta_registration_review') || auth()->user()->can('sidang_ta_registration_view_program') ? 'admin.sidang-ta-execution.index' : 'admin.sidang-ta-registrations.index') }}">Kembali</a>
    </div>
</x-app-layout>
