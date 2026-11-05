@php
    $display = static fn ($value) => is_scalar($value) && (string) $value !== ''
        ? (string) $value : 'Belum tersedia';
@endphp
<aside data-academic-panel class="min-w-0 space-y-4 xl:col-span-4" aria-label="Informasi akademik mahasiswa">
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Data mahasiswa
        </h2>
        <p class="break-words text-base font-semibold text-slate-900">
            {{ $display($academic['student_name'] ?? null) }}
        </p>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            @foreach(['NIM' => 'student_number', 'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'] as $label => $key)
                <div class="min-w-0">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 break-words font-medium text-slate-800">
                        {{ $display($academic[$key] ?? null) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="text-xs leading-relaxed text-slate-500">
            Sumber data diperbarui: {{ $display($academic['academic_source_updated_at'] ?? null) }}
        </p>
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Judul Tugas Akhir
        </h2>
        @foreach(['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'] as $label => $key)
            <div>
                <h3 class="text-xs font-semibold text-slate-500">
                    {{ $label }}
                </h3>
                <p class="mt-1.5 break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">
                    {{ $display($academic[$key] ?? null) }}
                </p>
            </div>
        @endforeach
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">
            Ringkasan akademik
        </h2>
        <dl class="grid grid-cols-2 gap-3">
            @foreach(['IPK' => 'gpa', 'SKS lulus' => 'passed_credits', 'TAK' => 'tak_score', 'EPrT' => 'eprt_score'] as $label => $key)
                @php($value = $academic[$key] ?? null)
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="mt-1 text-lg font-bold text-slate-800">
                        {{ is_numeric($value) ? number_format((float) $value, $key === 'gpa' ? 2 : 0, ',', '.') : $display($value) }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach(['Sidang tingkat 2' => 'sidang_tingkat_2', 'Status SKPI' => 'skpi_status', 'KRS Sidang TA 2' => 'ta2_krs_label'] as $label => $key)
                <div class="grid grid-cols-2 gap-3 py-2">
                    <dt class="text-slate-500">
                        {{ $label }}
                    </dt>
                    <dd class="break-words font-medium text-slate-800">
                        {{ $display($academic[$key] ?? null) }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
</aside>

