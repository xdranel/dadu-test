<div data-study-records class="campus-card overflow-hidden">
    <details data-study-section="active-courses" class="group border-b border-slate-100 last:border-0" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
            <div><h3 class="text-base font-semibold text-slate-800">Mata Kuliah Semester Ini (KRS Aktif)</h3><p class="mt-1 text-xs text-slate-500">Periode akademik {{ $krsLabel ?: 'Belum ditentukan' }} · Semester studi {{ $krsStudySemester ?: '-' }}</p></div>
            <svg aria-hidden="true" class="h-5 w-5 shrink-0 self-end text-slate-400 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
        </summary>
        <div class="px-6 pb-6">
@if($krsAktif->isNotEmpty())
    <div class="overflow-x-auto"><table class="campus-table">
        <thead><tr><th>Kode MK</th><th>Nama Mata Kuliah</th><th>SKS</th><th>Kelas</th><th>Dosen</th></tr></thead>
        <tbody>@foreach($krsAktif as $course)
            <tr><td class="font-mono">{{ $course->course_code }}</td><td>{{ $course->course_name }}</td><td>{{ $course->credits ?? '-' }}</td><td>{{ $course->course_class ?? '-' }}</td><td>{{ $course->lecturer_name ?? $course->lecturer_code ?? '-' }}</td></tr>
        @endforeach</tbody>
    </table></div>
@else
    <p class="text-sm text-slate-500">{{ $activeAcademicPeriod ? 'Belum ada data KRS yang tertaut ke periode akademik aktif ('.$activeAcademicPeriod->label.').' : 'Periode akademik aktif belum ditentukan oleh admin.' }}</p>
@endif
</div>
    </details>
    <details data-study-section="all-courses" class="group border-b border-slate-100 last:border-0" >
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
            <div><h3 class="text-base font-semibold text-slate-800">Mata Kuliah Semua Semester</h3></div>
            <svg aria-hidden="true" class="h-5 w-5 shrink-0 self-end text-slate-400 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
        </summary>
        <div class="px-6 pb-6">
@if($student->courses->isNotEmpty())
    <div class="overflow-x-auto"><table class="campus-table">
        <thead><tr><th>Periode akademik</th><th>Semester studi</th><th>Kode MK</th><th>Mata Kuliah</th><th>SKS</th><th>Kelas</th><th>Dosen</th></tr></thead>
        <tbody>@foreach($student->courses as $course)
            <tr><td>{{ $course->academicPeriod?->label ?? $course->academic_year ?? 'Periode belum tersedia' }}</td><td>{{ $course->semester ?? '-' }}</td><td class="font-mono">{{ $course->course_code }}</td><td>{{ $course->course_name }}</td><td>{{ $course->credits ?? '-' }}</td><td>{{ $course->course_class ?? '-' }}</td><td>{{ $course->lecturer_name ?? $course->lecturer_code ?? '-' }}</td></tr>
        @endforeach</tbody>
    </table></div>
@else
    <p class="text-sm text-slate-500">Belum ada data mata kuliah.</p>
@endif
</div>
    </details>
    <details data-study-section="all-grades" class="group border-b border-slate-100 last:border-0" >
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
            <div><h3 class="text-base font-semibold text-slate-800">Histori Nilai</h3></div>
            <svg aria-hidden="true" class="h-5 w-5 shrink-0 self-end text-slate-400 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
        </summary>
        <div class="px-6 pb-6">@php($gradeRows = $student->grades)

@if($gradeRows->isNotEmpty())
    <div class="overflow-x-auto"><table class="campus-table">
        <thead><tr><th>Kode MK</th><th>Mata Kuliah</th><th>SKS</th><th>Jenis</th><th>Tingkat</th><th>Tahun akademik</th><th>Nilai</th></tr></thead>
        <tbody>@foreach($gradeRows as $grade)
            <tr><td class="font-mono">{{ $grade->course_code }}</td><td>{{ $grade->course_name }}</td><td>{{ $grade->credits ?? '-' }}</td><td>{{ $grade->type ?? '-' }}</td><td>{{ $grade->level ?? '-' }}</td><td>{{ $grade->academic_year ?? 'Belum tersedia' }}</td>
                <td><span class="badge {{ in_array($grade->grade,['A','AB']) ? 'badge-success' : (in_array($grade->grade,['B','BC']) ? 'badge-info' : ($grade->grade === 'C' ? 'badge-warning' : ($grade->grade ? 'badge-danger' : 'bg-slate-100 text-slate-600'))) }}">{{ $grade->grade ?? 'Belum ada nilai' }}</span></td></tr>
        @endforeach</tbody>
    </table></div>
@else
    <p class="text-sm text-slate-500">Belum ada data nilai.</p>
@endif
</div>
    </details>
</div>
