<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">{{ $guide->exists ? 'Ubah' : 'Tambah' }} Panduan Form Sidang TA</h2></x-slot>
    <div class="py-8 max-w-3xl mx-auto px-4">
        <form method="POST" enctype="multipart/form-data" action="{{ $guide->exists ? route('admin.sidang-ta-guides.update', $guide) : route('admin.sidang-ta-guides.store') }}" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
            @csrf @if($guide->exists) @method('PUT') @endif
            @if($errors->any())<div role="alert" class="bg-red-50 p-4 rounded-lg text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div><label for="slot" class="block text-sm font-semibold mb-1">Dokumen / posisi panduan</label>
                <select id="slot" name="slot" required class="w-full rounded-lg border-slate-300">
                    @foreach($slots as $code => $label)<option value="{{ $code }}" @selected(old('slot', $guide->slot) === $code)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div><label for="title" class="block text-sm font-semibold mb-1">Judul</label><input id="title" name="title" value="{{ old('title', $guide->title) }}" maxlength="255" required class="w-full rounded-lg border-slate-300"></div>
            <div><label for="description" class="block text-sm font-semibold mb-1">Petunjuk</label><textarea id="description" name="description" rows="5" maxlength="20000" class="w-full rounded-lg border-slate-300">{{ old('description', $guide->description) }}</textarea></div>
            <div><label for="file" class="block text-sm font-semibold mb-1">Gambar / PDF bantuan (opsional)</label><input type="file" id="file" name="file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                <p class="text-sm text-slate-500 mt-1">JPG, PNG, atau PDF, maksimal 10 MB. Pilih berkas baru untuk mengganti contoh saat ini.</p>
                @if($guide->file_path)<a class="text-sm text-red-700 underline" href="{{ route('sidang-ta-guides.media', $guide) }}" target="_blank" rel="noopener">{{ $guide->file_name }}</a>@endif
            </div>
            <div><label for="link_label" class="block text-sm font-semibold mb-1">Label tautan</label><input id="link_label" name="link_label" value="{{ old('link_label', $guide->link_label) }}" maxlength="255" class="w-full rounded-lg border-slate-300"></div>
            <div><label for="url" class="block text-sm font-semibold mb-1">URL tautan (HTTP / HTTPS)</label><input type="url" id="url" name="url" value="{{ old('url', $guide->url) }}" maxlength="2048" class="w-full rounded-lg border-slate-300"></div>
            <div><label for="sort_order" class="block text-sm font-semibold mb-1">Urutan</label><input type="number" id="sort_order" name="sort_order" min="0" max="4294967295" required value="{{ old('sort_order', $guide->sort_order) }}" class="w-full rounded-lg border-slate-300"></div>
            <div><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $guide->is_active)) class="rounded border-slate-300"> Aktif</label></div>
            <div class="flex items-center gap-4"><button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg">Simpan panduan</button><a class="text-slate-600 underline" href="{{ route('admin.sidang-ta-guides.index') }}">Kembali</a></div>
        </form>
    </div>
</x-app-layout>
