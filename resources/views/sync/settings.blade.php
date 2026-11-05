<x-app-layout>
    <x-slot name="header">Pengaturan URL Sinkronisasi</x-slot>

    @if($errors->any())
        <div class="mb-5 flash-message bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
            <div class="flex items-center gap-3 mb-2">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm font-bold">Gagal menyimpan pengaturan:</span>
            </div>
            <ul class="list-disc list-inside text-sm ml-8">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="campus-card p-6 mb-5">
        <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl mb-6">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-blue-800">Panduan Pengaturan</p>
                <p class="text-xs text-blue-600 mt-1">
                    Paste URL lengkap endpoint dari kampus pusat untuk setiap modul.
                    URL harus diawali <code class="bg-blue-100 px-1 rounded">https://</code>.
                    Kosongkan jika modul belum tersedia.
                </p>
            </div>
        </div>

        <form action="{{ route('sync.save-settings') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                @foreach ($settings as $setting)
                    <div class="flex items-center gap-4 p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">

                        {{-- Icon & Label --}}
                        <div class="flex items-center gap-3 w-56 flex-shrink-0">
                            <span class="text-2xl">{{ $setting->icon }}</span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $setting->label }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ $setting->module }}</p>
                            </div>
                        </div>

                        <!-- URL Input -->
                        <div class="flex-1">
                            <input type="url"
                                   name="endpoints[{{ $setting->module }}][url]"
                                   value="{{ $setting->endpoint_url }}"
                                   placeholder="https://kampus.ac.id/api/{{ $setting->module }}"
                                   class="campus-input w-full font-mono text-xs">
                        </div>

                        <!-- Toggle Aktif -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <label class="text-xs text-slate-500">Aktif</label>
                            <input type="checkbox"
                                   name="endpoints[{{ $setting->module }}][active]"
                                   value="1"
                                   {{ $setting->is_active ? 'checked' : '' }}
                                   class="w-4 h-4 text-[#DA2D3B] rounded cursor-pointer">
                        </div>

                        <!-- Status URL -->
                        <div class="flex-shrink-0">
                            @if($setting->endpoint_url)
                                <span class="badge badge-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Terkonfigurasi
                                </span>
                            @else
                                <span class="badge badge-warning">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Belum diisi
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end mt-6">
                <button type="submit" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Pengaturan URL
                </button>
            </div>
        </form>
    </div>

</x-app-layout>