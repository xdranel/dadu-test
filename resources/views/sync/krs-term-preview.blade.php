<x-app-layout>
    <x-slot name="header">Pratinjau KRS</x-slot>
    <div class="campus-card p-5 mb-5 space-y-3">
        <p class="text-sm text-slate-700">Periksa {{ count($plans) }} baris sebelum menyimpan. Semester pada sumber adalah term Ganjil/Genap; semester studi memakai profil mahasiswa. Perbaikan tautan mempertahankan baris KRS lama yang cocok. Data periode lain dan mata kuliah yang tidak diunggah tetap tersimpan.</p>
        <p class="text-xs text-slate-500">Pratinjau berlaku 30 menit. Jika data berubah, unggah ulang untuk meninjau kembali.</p>
        <form action="{{ route('sync.krs-term-apply') }}" method="POST" class="space-y-3">
            @csrf
            <input type="hidden" name="preview_token" value="{{ $token }}">
            <label class="flex gap-2 items-start text-sm"><input type="checkbox" name="confirmed" value="1" required> Saya telah meninjau baris yang akan disimpan dan perbaikan tautan KRS yang ditampilkan.</label>
            <button class="btn btn-primary" type="submit">Simpan hasil tinjauan</button>
            <a href="{{ route('sync.index') }}" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
    <div class="campus-card overflow-x-auto">
        <table class="campus-table">
            <thead><tr><th>Baris</th><th>NIM</th><th>Mata Kuliah</th><th>Periode</th><th>Semester Studi</th><th>Tindakan</th></tr></thead>
            <tbody>@foreach ($plans as $plan)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $plan['student_number'] ?? '-' }}</td>
                    <td>{{ $plan['values']['course_code'] ?? '-' }}<br>{{ $plan['values']['course_class'] ?? '' }}</td>
                    <td>{{ $plan['period'] ?? '-' }}</td><td>{{ $plan['values']['semester'] ?? '-' }}</td>
                    <td>{{ ['create'=>'Tambah KRS','update'=>'Perbarui KRS','repair'=>'Perbaiki tautan','skip'=>'Dilewati'][$plan['action']] }}
                        @if(isset($plan['message']))<p class="text-xs text-rose-700">{{ $plan['message'] }}</p>@endif
                        @if($plan['action'] === 'repair')<p class="text-xs text-slate-500">Baris lama #{{ $plan['id'] }} · periode belum tertaut · semester lama {{ $plan['previous_semester'] ?? '-' }}</p>@endif
                    </td></tr>
            @endforeach</tbody>
        </table>
    </div>
</x-app-layout>
