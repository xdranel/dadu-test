<x-app-layout>
    <x-slot name="header">Pengaturan Periode Sidang TA</x-slot>
    @php
        $at = now();
    @endphp
    <div class="mx-auto max-w-5xl space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
                <h2 class="text-xl font-bold text-slate-800">
                    Periode & Jadwal Sidang TA
                </h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">
                    Beberapa periode dapat aktif bersamaan. Mahasiswa memilih periode aktif yang sedang berada dalam rentang pendaftaran.
                </p>
            </div>
            <a data-back-to-ta-review href="{{ route('admin.sidang-ta-registrations.index') }}"
               class="btn btn-secondary">
                Kembali ke Review Sidang TA
            </a>
        </header>
        @if(session('success'))
            <div role="status"
                 class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-bold">Periksa data periode:</p>
                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach</ul>
            </div>
        @endif
        @can('sidang_ta_period_create')
            <form data-ta-period-create method="POST" action="{{ route('admin.sidang-ta-periods.store') }}"
                  class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
                @csrf
                <h3 class="font-bold md:col-span-2">Tambah periode</h3>
                <label class="campus-label">Nama periode
                    <input class="campus-input mt-1" name="name" value="{{ old('name') }}" required maxlength="255">
                </label>
                <label class="campus-label">Keterangan
                    <input class="campus-input mt-1" name="description" value="{{ old('description') }}" maxlength="2000">
                </label>
                <h4 class="font-semibold text-slate-800 md:col-span-2">Pendaftaran Sidang TA</h4>
                <label class="campus-label">Dibuka
                    <input class="campus-input mt-1" type="datetime-local" name="open_at" value="{{ old('open_at') }}" required>
                </label>
                <label class="campus-label">Tenggat
                    <input class="campus-input mt-1" type="datetime-local" name="close_at" value="{{ old('close_at') }}" required>
                </label>
                <h4 class="border-t border-slate-200 pt-3 font-semibold text-slate-800 md:col-span-2">Tanggal Verifikasi Berkas</h4>
                <label class="campus-label">Mulai verifikasi
                    <input class="campus-input mt-1" type="datetime-local" name="revision_open_at" value="{{ old('revision_open_at') }}" required>
                </label>
                <label class="campus-label">Selesai verifikasi
                    <input class="campus-input mt-1" type="datetime-local" name="revision_close_at" value="{{ old('revision_close_at') }}" required>
                </label>
                <p class="text-xs text-slate-500 md:col-span-2">Verifikasi boleh dimulai selama pendaftaran. Waktu selesai harus setelah mulai verifikasi dan tidak lebih awal dari tenggat pendaftaran.</p>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active'))>
                    Pendaftaran aktif
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_revision_active" value="1" @checked(old('is_revision_active'))>
                    Revisi aktif
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="show_to_students" value="1" @checked(old('show_to_students'))>
                    Tampilkan jadwal ke mahasiswa
                </label>
                <p class="text-xs text-slate-500 md:col-span-2">Tampilan jadwal tidak mengubah izin pendaftaran atau revisi. Kartu otomatis hilang setelah kedua jadwal berakhir.</p>
                <div class="md:col-span-2">
                    <button type="submit" class="btn btn-primary">Simpan periode</button>
                </div>
            </form>
        @endcan
        <div class="space-y-4">
            @forelse($periods as $period)
                @php
                    $state = match (true) {
                        !$period->is_active => 'inactive',
                        $at->lt($period->open_at) => 'upcoming',
                        $at->gt($period->close_at) => 'closed',
                        default => 'open',
                    };
                    $stateLabel = ['inactive' => 'Tidak aktif', 'upcoming' => 'Belum dibuka', 'closed' => 'Ditutup', 'open' => 'Sedang terbuka'][$state];
                    $stateClass = ['inactive' => 'bg-slate-100 text-slate-700', 'upcoming' => 'bg-sky-100 text-sky-800', 'closed' => 'bg-rose-100 text-rose-800', 'open' => 'bg-emerald-100 text-emerald-800'][$state];
                @endphp
                <article data-ta-period-id="{{ $period->id }}"
                         class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words font-bold text-slate-800">
                                {{ $period->name }}
                            </h3>
                            <p class="mt-1 break-words text-xs text-slate-500">
                                {{ $period->description ?: 'Tidak ada keterangan.' }}
                            </p>
                        </div>
                        <span data-period-state="{{ $state }}"
                              class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $stateClass }}">
                            {{ $stateLabel }}
                        </span>
                    </header>
                    @include('sidang_ta_registrations.partials.period-schedule', ['schedulePeriod' => $period])
                    @can('sidang_ta_period_update')
                        <form data-ta-period-update method="POST"
                              action="{{ route('admin.sidang-ta-periods.update', $period) }}"
                              class="grid gap-3 md:grid-cols-2">
                            @csrf @method('PUT')
                            <label class="campus-label">Nama periode
                                <input class="campus-input mt-1" name="name" value="{{ $period->name }}" required maxlength="255">
                            </label>
                            <label class="campus-label">Keterangan
                                <input class="campus-input mt-1" name="description" value="{{ $period->description }}" maxlength="2000">
                            </label>
                            <h4 class="font-semibold text-slate-800 md:col-span-2">Pendaftaran Sidang TA</h4>
                            <label class="campus-label">Dibuka
                                <input class="campus-input mt-1" type="datetime-local" name="open_at"
                                       value="{{ $period->open_at->format('Y-m-d\TH:i') }}" required>
                            </label>
                            <label class="campus-label">Tenggat
                                <input class="campus-input mt-1" type="datetime-local" name="close_at"
                                       value="{{ $period->close_at->format('Y-m-d\TH:i') }}" required>
                            </label>
                            <h4 class="border-t border-slate-200 pt-3 font-semibold text-slate-800 md:col-span-2">Tanggal Verifikasi Berkas</h4>
                            <label class="campus-label">Mulai verifikasi
                                <input class="campus-input mt-1" type="datetime-local" name="revision_open_at" value="{{ $period->revision_open_at?->format('Y-m-d\TH:i') }}" required>
                            </label>
                            <label class="campus-label">Selesai verifikasi
                                <input class="campus-input mt-1" type="datetime-local" name="revision_close_at" value="{{ $period->revision_close_at?->format('Y-m-d\TH:i') }}" required>
                            </label>
                            <p class="text-xs text-slate-500 md:col-span-2">Verifikasi boleh dimulai selama pendaftaran. Waktu selesai harus setelah mulai verifikasi dan tidak lebih awal dari tenggat pendaftaran.</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" @checked($period->is_active)>
                                Pendaftaran aktif
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="is_revision_active" value="1" @checked($period->is_revision_active)>
                                Revisi aktif
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="show_to_students" value="1" @checked($period->show_to_students)>
                                Tampilkan jadwal ke mahasiswa
                            </label>
                            <p class="text-xs text-slate-500 md:col-span-2">Tampilan jadwal tidak mengubah izin pendaftaran atau revisi. Kartu otomatis hilang setelah kedua jadwal berakhir.</p>
                            <div class="md:col-span-2">
                                <button type="submit" class="btn btn-primary">Simpan perubahan</button>
                            </div>
                        </form>
                    @else
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-slate-500">Dibuka</dt>
                                <dd class="mt-1 font-medium text-slate-800">
                                    {{ $period->open_at->format('d/m/Y H:i') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Tenggat</dt>
                                <dd class="mt-1 font-medium text-slate-800">
                                    {{ $period->close_at->format('d/m/Y H:i') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Aktivasi</dt>
                                <dd class="mt-1 font-medium text-slate-800">
                                    {{ $period->is_active ? 'Aktif' : 'Tidak aktif' }}
                                </dd>
                            </div>
                        </dl>
                    @endcan
                </article>
            @empty
                <div
                    class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    Belum ada periode pendaftaran.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
