@php
    $snapshot = is_array($registration->academic_snapshot) ? $registration->academic_snapshot : [];
    $display = static function ($value): string {
        if ($value === null || (is_string($value) && in_array(trim($value), ['', '-'], true))) {
            return 'Belum tersedia';
        }
        if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
        if (is_scalar($value)) return (string) $value;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ?: 'Belum tersedia';
    };
    $number = static fn ($value, int $digits = 0) => is_numeric($value)
        ? number_format((float) $value, $digits, ',', '.') : $display($value);
    $identity = ['Nama mahasiswa' => 'student_name', 'NIM' => 'student_number',
        'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'];
    $metrics = ['IPK' => ['gpa', 2], 'SKS lulus' => ['passed_credits', 0],
        'TAK' => ['tak_score', 0], 'EPrT' => ['eprt_score', 0]];
    $statuses = ['Sidang tingkat 2' => 'sidang_tingkat_2', 'Status SKPI' => 'skpi_status',
        'KRS Sidang TA 2' => 'ta2_krs_label'];
    $titles = ['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'];
    // Fields outside the academic summary remain stored without appearing in the detail panel.
    $omitted = ['level_3_status', 'bpp_status', 'ibasila_status', 'skpi_tak',
        'advisor_1', 'advisor_2', 'examiner_1', 'examiner_2', 'examiner_3',
        'exam_date', 'exam_time', 'exam_location'];
    $known = array_merge(array_values($identity), array_column($metrics, 0), array_values($statuses),
        array_values($titles), $omitted, ['academic_source_updated_at', 'ta2_krs_status', 'admission']);
    $extra = array_diff_key($snapshot, array_flip($known));
    $statusLabel = static fn ($value) => match (strtolower(trim(is_scalar($value) ? (string) $value : ''))) {
        'approved' => 'Disetujui', 'paid' => 'Lunas', 'pending' => 'Menunggu',
        'rejected' => 'Ditolak', default => $display($value),
    };
    $statusTone = static fn ($value) => match (strtolower(trim(is_scalar($value) ? (string) $value : ''))) {
        'approved', 'disetujui', 'paid', 'lunas', 'lulus' => 'bg-emerald-50 text-emerald-800',
        'pending', 'menunggu', 'needs_review', 'perlu revisi' => 'bg-amber-50 text-amber-800',
        'rejected', 'ditolak', 'tidak lulus' => 'bg-rose-50 text-rose-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<section data-academic-detail class="campus-card overflow-hidden">
    <header class="space-y-1.5 border-b border-slate-100 p-5 sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">
            Data akademik saat pengajuan
        </h2>
        <p class="text-sm leading-relaxed text-slate-500">
            Data direkam saat pengajuan dikirim. Perubahan data akademik setelahnya tidak mengubah ringkasan tersimpan ini.
        </p>
    </header>
    <div class="space-y-6 p-5 sm:p-6">
        <section data-academic-group="identity">
            <h3 class="text-sm font-bold text-slate-800">
                Identitas akademik
            </h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach($identity as $label => $key)
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-1 break-words text-sm font-medium text-slate-800">
                            {{ $display($snapshot[$key] ?? null) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="metrics">
            <h3 class="text-sm font-bold text-slate-800">
                Ringkasan akademik
            </h3>
            <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach($metrics as $label => [$key, $digits])
                    <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}" class="mt-2 break-words text-xl font-bold text-slate-900">
                            {{ $number($snapshot[$key] ?? null, $digits) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="statuses">
            <h3 class="text-sm font-bold text-slate-800">Status akademik</h3>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach($statuses as $label => $key)
                    @php($value = $snapshot[$key] ?? null)
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-100 p-3">
                        <dt class="text-sm text-slate-600">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="max-w-full break-words rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone($value) }}">
                            {{ $statusLabel($value) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <section data-academic-group="titles">
            <h3 class="text-sm font-bold text-slate-800">Judul Tugas Akhir</h3>
            <dl class="mt-3 space-y-3">
                @foreach($titles as $label => $key)
                    <div>
                        <dt class="text-xs font-semibold text-slate-500">{{ $label }}</dt>
                        <dd data-snapshot-field="{{ $key }}"
                            class="mt-1.5 break-words rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">
                            {{ $display($snapshot[$key] ?? null) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($extra !== [])
            <section data-academic-group="extra">
                <h3 class="text-sm font-bold text-slate-800">Informasi tambahan</h3>
                <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                    @foreach($extra as $key => $value)
                        <div class="min-w-0">
                            <dt class="break-words text-xs text-slate-500">
                                {{ ucfirst(str_replace('_', ' ', (string) $key)) }}
                            </dt>
                            <dd class="mt-1 break-words text-sm text-slate-800">
                                {{ $display($value) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
        <p class="border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
            Waktu sumber akademik:
            <span data-snapshot-field="academic_source_updated_at">{{ $display($snapshot['academic_source_updated_at'] ?? null) }}</span>.
            Waktu ini menunjukkan pembaruan sumber data, bukan waktu kirim pendaftaran.
        </p>
    </div>
</section>
