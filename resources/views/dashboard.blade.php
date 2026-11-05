<x-app-layout>
    <x-slot name="header">Dashboard Utama</x-slot>

    <div class="space-y-6">
        <!-- Header Welcome Banner -->
        <div
            class="w-full box-border p-5 sm:p-6 bg-gradient-to-r from-slate-900 via-slate-800 to-red-900 text-white rounded-2xl shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4 overflow-hidden">
            <div class="min-w-0 max-w-full">
                <h2 class="text-lg sm:text-xl font-extrabold tracking-tight break-words">
                    Selamat datang kembali, {{ auth()->user()->name }}!
                </h2>
                <p class="text-slate-300 mt-1 text-xs sm:text-sm leading-relaxed">
                    Dashboard Akademik Terpadu.
                </p>
            </div>
        </div>

        <!-- 1. CARD SHORTCUT YUDISIUM MAHASISWA - Style iBasila -->
        @if($roles['mahasiswa'])
            <div class="space-y-3">
                <h2 class="text-base font-bold text-slate-800">
                    Layanan Akademik
                </h2>
                <p class="text-xs text-slate-500">
                    Pilih layanan yang ingin Anda akses.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 items-start">
                    {{-- Card: Pendaftaran Yudisium --}}
                    <div
                        class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col">
                        {{-- Area Ilustrasi Atas (merah ala iBasila) --}}
                        <div
                            class="relative bg-gradient-to-br from-[#DA2D3B] to-[#b91c2c] h-24 flex items-center justify-center overflow-hidden">
                            {{-- Dekorasi lingkaran --}}
                            <div class="absolute -top-4 -left-4 w-20 h-20 bg-white/10 rounded-full"></div>
                            <div class="absolute -bottom-6 -right-3 w-24 h-24 bg-white/10 rounded-full"></div>
                            {{-- Icon wisuda --}}
                            <svg class="w-10 h-10 text-white relative z-10 drop-shadow" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 14l9-5-9-5-9 5 9 5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                            </svg>
                            {{-- Badge status registrasi (jika ada) --}}
                            @if($myGraduationRegistration)
                                <span class="absolute top-2.5 right-2.5 px-2.5 py-0.5 rounded-full text-[9px] font-bold tracking-wide
                                {{ $myGraduationRegistration->status === 'approved'      ? 'bg-emerald-500 text-white' : '' }}
                                {{ $myGraduationRegistration->status === 'submitted'     ? 'bg-blue-500 text-white' : '' }}
                                {{ $myGraduationRegistration->status === 'needs_review'  ? 'bg-amber-400 text-white' : '' }}
                                {{ $myGraduationRegistration->status === 'rejected'      ? 'bg-rose-600 text-white' : '' }}">
                                Status: {{ strtoupper($myGraduationRegistration->status_label) }}
                            </span>
                            @endif
                        </div>

                        {{-- Konten Bawah --}}
                        <div class="p-4 flex flex-col">
                            {{-- Judul & Deskripsi --}}
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 leading-snug">
                                    Pendaftaran Yudisium
                                </h3>
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                    Pengajuan berkas kelulusan dan pengecekan syarat akademik.
                                </p>
                            </div>

                            {{-- Kotak Informasi Periode Aktif --}}
                            @if($activePeriod)
                                <div class="mt-3 p-2.5 bg-slate-50 border border-slate-100 rounded-xl space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#DA2D3B]"></span>
                                        <span>Periode aktif: <strong
                                                class="text-[#DA2D3B] font-semibold">{{ $activePeriod->name }}</strong></span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 pl-3">
                                        {{ $activePeriod->open_at?->translatedFormat('d M Y') }}
                                        s.d. {{ $activePeriod->close_at?->translatedFormat('d M Y') }}
                                    </p>
                                </div>
                            @endif

                            {{-- Tombol Aksi --}}
                            <div class="mt-auto pt-3 flex flex-col sm:flex-row gap-2">
                                @if($myGraduationRegistration)
                                    <a href="{{ route('graduation-registrations.show', $myGraduationRegistration->id) }}"
                                       class="btn btn-secondary text-xs py-2 w-full flex-1 justify-center rounded-xl font-medium shadow-none hover:bg-slate-100 transition-all">
                                        Lihat Detail
                                    </a>
                                    @if($myGraduationRegistration->isEditable())
                                        <a href="{{ route('graduation-registrations.edit', $myGraduationRegistration->id) }}"
                                           class="btn btn-primary text-xs py-2 flex-1 justify-center rounded-xl font-medium">
                                            Edit Pendaftaran
                                        </a>
                                    @endif
                                @elseif($activePeriod && $activePeriod->isOpen())
                                    <a href="{{ route('graduation-registrations.create') }}"
                                       class="btn btn-primary text-xs py-2 w-full justify-center font-bold rounded-xl shadow-sm hover:shadow-md transition-all">
                                        Daftar Sekarang
                                    </a>
                                @elseif($activePeriod && $activePeriod->isUpcoming())
                                    <button disabled
                                            class="btn btn-secondary text-xs py-2 w-full justify-center opacity-70 cursor-not-allowed rounded-xl">
                                        Akan Datang
                                    </button>
                                @else
                                    <button disabled
                                            class="btn btn-secondary text-xs py-2 w-full justify-center opacity-60 cursor-not-allowed rounded-xl">
                                        Belum Ada Periode
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>

                    {{-- Card: Pendaftaran Sidang TA --}}
                    @can('sidang_ta_registration_read')
                        <section data-ta-dashboard-card
                                 class="flex flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md">
                            <div class="relative flex h-24 items-center justify-center overflow-hidden bg-gradient-to-br from-[#DA2D3B] to-[#b91c2c]">
                                <div class="absolute -left-4 -top-4 h-20 w-20 rounded-full bg-white/10"></div>
                                <div class="absolute -bottom-6 -right-3 h-24 w-24 rounded-full bg-white/10"></div>
                                <svg aria-hidden="true" class="relative z-10 h-10 w-10 text-white drop-shadow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-2H4V4Zm16 0h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-2h3V4Z"/>
                                </svg>
                                <span class="absolute right-2.5 top-2.5 rounded-full bg-white/20 px-2.5 py-0.5 text-[9px] font-bold tracking-wide text-white">
                                    {{ $taOpenPeriods->isNotEmpty() ? 'TERBUKA' : ($taUpcomingPeriod ? 'AKAN DATANG' : 'BELUM DIBUKA') }}
                                </span>
                            </div>
                            <div class="flex flex-col p-4">
                                <h3 class="text-sm font-bold leading-snug text-slate-800">Pendaftaran Sidang TA</h3>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                    Pengajuan sidang tugas akhir dan pemeriksaan kelengkapan berkas.
                                </p>
                                <div class="mt-3 space-y-2">
                                    @foreach($taOpenPeriods as $period)
                                        <div data-ta-dashboard-period="{{ $period->id }}" class="space-y-1 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                                            <p class="flex items-start gap-1.5 text-xs font-medium text-slate-600">
                                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-[#DA2D3B]"></span>
                                                <span>Periode terbuka: <strong class="font-semibold text-[#DA2D3B]">{{ $period->name }}</strong></span>
                                            </p>
                                            <p class="pl-3 text-[11px] text-slate-500">
                                                <time data-ta-open-at datetime="{{ $period->open_at->toIso8601String() }}">{{ $period->open_at->translatedFormat('d M Y H:i') }}</time>
                                                s.d. <time data-ta-close-at datetime="{{ $period->close_at->toIso8601String() }}">{{ $period->close_at->translatedFormat('d M Y H:i') }}</time>
                                            </p>
                                        </div>
                                    @endforeach
                                    @if($taOpenPeriods->isEmpty() && $taUpcomingPeriod)
                                        <div data-ta-dashboard-upcoming="{{ $taUpcomingPeriod->id }}" class="space-y-1 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                                            <p class="text-xs font-medium text-slate-600">Periode mendatang: <strong class="font-semibold text-[#DA2D3B]">{{ $taUpcomingPeriod->name }}</strong></p>
                                            <p class="text-[11px] text-slate-500">
                                                <time data-ta-open-at datetime="{{ $taUpcomingPeriod->open_at->toIso8601String() }}">{{ $taUpcomingPeriod->open_at->translatedFormat('d M Y H:i') }}</time>
                                                s.d. <time data-ta-close-at datetime="{{ $taUpcomingPeriod->close_at->toIso8601String() }}">{{ $taUpcomingPeriod->close_at->translatedFormat('d M Y H:i') }}</time>
                                            </p>
                                        </div>
                                    @elseif($taOpenPeriods->isEmpty())
                                        <p data-ta-dashboard-empty class="rounded-xl border border-slate-100 bg-slate-50 p-2.5 text-xs text-slate-500">Belum ada periode Sidang TA terbuka.</p>
                                    @endif
                                </div>
                                <div class="mt-auto pt-3">
                                    <a data-ta-dashboard-link href="{{ route('sidang-ta-registrations.index') }}"
                                       class="btn btn-primary w-full justify-center rounded-xl py-2 text-xs font-bold shadow-sm transition-all hover:shadow-md">
                                        Buka Pendaftaran Sidang TA
                                    </a>
                                </div>
                            </div>
                        </section>
                    @endcan

                </div>
            </div>
        @endif

    </div>

</x-app-layout>
