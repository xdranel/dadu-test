<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Panduan Form Sidang TA</h2></x-slot>
    <div class="py-8 max-w-6xl mx-auto px-4 space-y-5">
        @if(session('success'))<div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div>@endif
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-slate-600">Kelola contoh berkas, petunjuk, dan tautan pada form Sidang TA.</p>
            <a href="{{ route('admin.sidang-ta-guides.create') }}" class="rounded-lg bg-red-600 px-4 py-2 text-white">Tambah panduan</a>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50"><tr><th class="p-4">Tahap / dokumen</th><th class="p-4">Panduan</th><th class="p-4">Urutan / status</th><th class="p-4">Aksi</th></tr></thead>
                <tbody>
                @forelse($guides as $guide)
                    <tr class="border-t border-slate-200">
                        <td class="p-4">{{ $slots[$guide->slot] ?? $guide->slot }}</td>
                        <td class="p-4 space-y-2">
                            <p class="font-semibold">{{ $guide->title }}</p>
                            @if($guide->description)<p class="whitespace-pre-line text-slate-600">{{ $guide->description }}</p>@endif
                            @if($guide->file_path)<a class="block text-red-700 underline" href="{{ route('sidang-ta-guides.media', $guide) }}" target="_blank" rel="noopener">Lihat {{ $guide->file_name }}</a>@endif
                            @if($guide->url)<a class="block text-red-700 underline" href="{{ $guide->url }}" target="_blank" rel="noopener noreferrer">{{ $guide->link_label ?: 'Buka tautan' }}</a>@endif
                        </td>
                        <td class="p-4">{{ $guide->sort_order }} / {{ $guide->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td class="p-4 space-y-3">
                            <a class="text-red-700 underline" href="{{ route('admin.sidang-ta-guides.edit', $guide) }}">Ubah</a>
                            <form method="POST" action="{{ route('admin.sidang-ta-guides.destroy', $guide) }}" onsubmit="return confirm('Hapus panduan ini?')">
                                @csrf @method('DELETE')<button class="text-red-700 underline" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty<tr><td colspan="4" class="p-6 text-slate-500">Belum ada panduan.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $guides->links() }}
    </div>
</x-app-layout>
