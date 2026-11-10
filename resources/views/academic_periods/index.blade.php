<x-app-layout>
    <x-slot name="header">Pengaturan Periode Akademik KRS</x-slot>
    @if(session('success'))<div role="status" class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div role="alert" class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">Periksa pengaturan periode:</p>
            <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <a href="{{ route('student_courses.index') }}" class="inline-block mb-4 text-sm text-blue-600 underline">Kembali ke KRS Mahasiswa</a>
    <div class="campus-card p-5 mb-5">
        <p class="text-sm text-slate-700">Daftarkan tahun, term, dan tanggal resmi. Mengaktifkan periode menentukan KRS aktif yang ditampilkan pada profil mahasiswa. KRS lama yang belum tertaut perlu ditinjau melalui impor ulang mode Term akademik.</p>
        @if($periods->where('is_active', true)->count() !== 1)
            <p class="mt-2 text-sm text-amber-700">Pilih tepat satu periode aktif agar KRS aktif dapat ditentukan.</p>
        @endif
    </div>
    @can('student_update')
    <div class="campus-card p-5 mb-5">
        <h2 class="font-semibold mb-3">Tambah Periode Akademik</h2>
        <form action="{{ route('academic_periods.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @csrf
            <label class="campus-label">Tahun akademik<input name="year" value="{{ old('year') }}" placeholder="2026/2027" class="campus-input w-full" required pattern="20[0-9]{2}/20[0-9]{2}"></label>
            <label class="campus-label">Term<select name="semester" class="campus-select w-full" required><option value="1">Ganjil</option><option value="2" @selected(old('semester') == 2)>Genap</option></select></label>
            <label class="campus-label">Label<input name="label" value="{{ old('label') }}" maxlength="50" class="campus-input w-full" required></label>
            <label class="campus-label">Tanggal mulai resmi<input name="start_date" value="{{ old('start_date') }}" type="date" class="campus-input w-full" required></label>
            <label class="campus-label">Tanggal selesai resmi<input name="end_date" value="{{ old('end_date') }}" type="date" class="campus-input w-full" required></label>
            <div class="flex items-end"><button class="btn btn-primary">Tambah tanpa mengaktifkan</button></div>
        </form>
    </div>
    @endcan
    <div class="space-y-4">
    @forelse($periods as $period)
        <div class="campus-card p-5">
            <div class="flex justify-between flex-wrap gap-3 mb-3"><h2 class="font-semibold">{{ $period->label }} · {{ $period->is_active ? 'Aktif' : 'Tidak aktif' }}</h2>
                @can('student_update')
                @if(!$period->is_active)<form action="{{ route('academic_periods.activate', $period) }}" method="POST">@csrf<button class="btn btn-primary">Aktifkan periode ini</button></form>@endif
                @endcan
            </div>
            @can('student_update')
            <form action="{{ route('academic_periods.update', $period) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf @method('PUT')
                <label class="campus-label">Tahun akademik<input name="year" value="{{ $period->year }}" class="campus-input w-full" required></label>
                <label class="campus-label">Term<select name="semester" class="campus-select w-full"><option value="1" @selected($period->semester === 1)>Ganjil</option><option value="2" @selected($period->semester === 2)>Genap</option></select></label>
                <label class="campus-label">Label<input name="label" value="{{ $period->label }}" maxlength="50" class="campus-input w-full" required></label>
                <label class="campus-label">Tanggal mulai resmi<input name="start_date" type="date" value="{{ $period->start_date->format('Y-m-d') }}" class="campus-input w-full" required></label>
                <label class="campus-label">Tanggal selesai resmi<input name="end_date" type="date" value="{{ $period->end_date->format('Y-m-d') }}" class="campus-input w-full" required></label>
                <div class="flex items-end"><button class="btn btn-secondary">Simpan perubahan</button></div>
            </form>
            @else
                <p class="text-sm">{{ $period->year }} · {{ $period->semester_label }} · {{ $period->start_date->format('d/m/Y') }} – {{ $period->end_date->format('d/m/Y') }}</p>
            @endcan
        </div>
    @empty
        <div class="campus-card p-5 text-sm text-slate-500">Belum ada periode akademik.</div>
    @endforelse
    </div>
</x-app-layout>
