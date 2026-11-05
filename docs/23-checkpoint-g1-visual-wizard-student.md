# 23 — Checkpoint G1: visual wizard student

> Panduan implementasi untuk pengguna. Asisten hanya menulis `docs/*.md`; jangan menjalankan implementasi otomatis pada kedua folder aplikasi. Rancangan [22](22-rancangan-visual-student-sidang-ta.md) disetujui melalui jawaban **lanjutkan**.

**Goal:** merapikan empat tahap, panel akademik, upload dan validasi inline tanpa mengganti kontrak draft/finalisasi.

**Architecture:** Blade tetap mengirim multipart per tahap. Dua factory Alpine mengurus status halaman dan pilihan file. Controller hanya menambahkan data tampilan; service penyimpanan/finalizer tidak diubah.

**Tech stack:** Laravel 13, Blade, Alpine 3, Tailwind 3, Pest 4, Node built-in test runner; tanpa dependency baru.

**Spec:** [22 — rancangan visual student](22-rancangan-visual-student-sidang-ta.md).

**Koreksi hasil implementasi pengguna:** jika klik file tidak menampilkan nama pilihan dan drag/drop tidak bekerja, baca [24](24-koreksi-g1-upload-dan-card-draft.md). Scan folder asli menemukan langkah registrasi upload pada app.js belum diterapkan. Dokumen 24 juga menambah test entry point serta perapian card draft/warning hapus. Jangan melewatkan app.js meskipun test factory di bagian 9 lulus.

## Batas dan hasil yang diharapkan

- Kerjakan di folder development asli. File yang sudah ada diedit, tidak dibuat ulang memakai `--force`.
- G1 mencakup halaman Mulai Pendaftaran tahap 1–4. Tombol Unduh pada halaman tinjau dan pengelompokan detail historis menjadi **G2 sesudah G1 lulus**.
- Pertahankan nama route, field, permission, CSRF, old version, status draft, pembatas tahap, finalisasi dan penyimpanan privat.
- Informasi simpan berada pada footer card tahapan. Panel akademik kanan tampil setiap tahap, read-only dan bersumber dari data TA. Ini bukan autosave.
- Test PHP tidak membuktikan warna/fokus/drag-drop browser. Test JavaScript di bawah memeriksa pemilihan file, bukan pengganti checklist browser.

## Fokus review

1. Tanpa draft, tahap 1 harus aktif dan tahap 2–4 tetap tidak menjadi link; diuji pada test PHP pertama.
2. Error POST tidak boleh menghilangkan old input/version atau berkas tersimpan; diuji di test PHP dan suite wizard sebelumnya.
3. Drop beberapa file/non-file tidak mengganti pilihan valid sebelumnya; diuji di test JavaScript.
4. Cabang tahap 4 tersembunyi tidak ikut POST; atribut awal/binding diuji PHP, interaksi diuji browser.
5. File pengganti belum disimpan tidak boleh disebut berhasil diunggah; batal pilihan hanya membersihkan input browser; diuji JavaScript dan browser.

## 1. Berkas dan command persiapan

| Berkas | Tindakan |
| --- | --- |
| `app/Http/Controllers/SidangTaRegistrationController.php` | Ganti method `create()` saja |
| `app/Http/Controllers/SidangTaDraftController.php` | Ganti method `edit()` saja |
| `app/Http/Requests/SaveSidangTaDraftStepRequest.php` | Ganti `messages()`, tambah `attributes()` |
| `resources/js/sidang-ta-wizard.js` | Ganti seluruh isi |
| `resources/js/app.js` | Ganti isi pendek seperti contoh |
| `resources/views/sidang_ta_registrations/wizard.blade.php` | Ganti seluruh isi |
| `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php` | Ganti seluruh isi |
| `resources/views/sidang_ta_registrations/partials/wizard-error.blade.php` | Baru: error field umum |
| `resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php` | Baru: panel akademik kanan |
| `tests/Feature/SidangTaWizardUiTest.php` | Baru: test render/validasi |
| `tests/Js/sidang-ta-wizard.test.mjs` | Baru: test perilaku JavaScript |

Jalankan dari folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:view sidang_ta_registrations.partials.wizard-error
php artisan make:view sidang_ta_registrations.partials.wizard-academic
php artisan make:test SidangTaWizardUiTest --pest
mkdir -p tests/Js
touch tests/Js/sidang-ta-wizard.test.mjs
```

Tidak ada migrasi, seed, paket npm atau CSS global baru. Jika file baru ternyata sudah ada, periksa dahulu sebelum mengganti isinya. Jangan menghapus test checkpoint sebelumnya.

- [ ] Salin test lengkap pada bagian 8–9 dahulu. Jalankan command test G1 pada bagian 10 sebelum mengubah source. Regresi UI semestinya belum lulus; test JS juga belum bisa mengimpor export upload yang baru. Kegagalan koneksi database/dependency bukan bukti regresi yang dimaksud.
- [ ] Terapkan bagian 2–7, lalu jalankan ulang test dan checklist browser.

## 2. Data tampilan controller

### `SidangTaRegistrationController::create()`

Ganti hanya method ini. Semua method final store/update dan guard lain tetap seperti hasil F. Model yang dipakai di sini sudah di-import pada controller sekarang; service snapshot ditulis dengan nama lengkap agar tidak perlu menebak import.

```php
public function create()
{
    $student = app(SidangTaStudentContext::class)->studentFor(auth()->user());
    $active = SidangTaRegistration::where('student_number', $student->student_number)
        ->whereIn('status', ['submitted', 'needs_review'])->latest()->first();
    if ($active) {
        return redirect()->route($active->status === 'needs_review'
            ? 'sidang-ta-registrations.edit' : 'sidang-ta-registrations.show', $active);
    }
    $existing = SidangTaRegistrationDraft::query()->ownedBy(auth()->user())
        ->where('student_number', $student->student_number)->where('status', 'active')
        ->whereNull('source_registration_id')->first();
    if ($existing) {
        return redirect()->route('sidang-ta-drafts.edit', $existing);
    }
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    $academic = app(\App\Services\SidangTaAcademicSnapshot::class)->forStudent($student);
    $draft = null;
    $step = $maxStep = 1;
    $completedThrough = 0;
    return view('sidang_ta_registrations.wizard', compact(
        'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic'
    ));
}
```

### `SidangTaDraftController::edit()`

`$firstIncomplete` dihitung sekali untuk batas akses dan indikator tersimpan. Jika semua lengkap, batas akses tetap 4 dan jumlah tahap lengkap 4. Penambahan snapshot hanya pada respons HTML, sesudah pengecekan JSON/owner.

```php
public function edit(Request $request, SidangTaRegistrationDraft $draft): JsonResponse|View|RedirectResponse
{
    $this->service->owned($request->user(), $draft);
    if ($request->expectsJson()) {
        return $this->respond($draft);
    }
    $query = $request->validate(['step' => ['nullable', 'integer', 'between:1,4']]);
    $firstIncomplete = SidangTaDocumentRequirements::firstIncompleteStep($draft);
    $maxStep = $firstIncomplete ?? 4;
    $completedThrough = ($firstIncomplete ?? 5) - 1;
    $step = (int) ($query['step'] ?? $maxStep);
    if ($step > $maxStep) {
        return redirect()->route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $maxStep]);
    }
    $draft->load(['period', 'documents', 'sourceRegistration']);
    $student = app(SidangTaStudentContext::class)->studentFor($request->user());
    $openPeriods = SidangTaPeriod::query()->openForRegistration()->get();
    $academic = app(\App\Services\SidangTaAcademicSnapshot::class)->forStudent($student);
    return view('sidang_ta_registrations.wizard', compact(
        'draft', 'student', 'step', 'maxStep', 'completedThrough', 'openPeriods', 'academic'
    ));
}
```

## 3. Pesan validasi bahasa Indonesia

Pada `SaveSidangTaDraftStepRequest.php`, pertahankan `authorize()` dan `rules()`. Ganti `messages()` dan tambahkan `attributes()` berikut di dalam class, bukan sesudah penutup class:

```php
public function messages(): array
{
    return [
        'sidang_ta_period_id.required' => 'Pilih periode sidang TA.',
        'sidang_ta_period_id.integer' => 'Pilih periode sidang TA yang valid.',
        'sidang_ta_period_id.exists' => 'Periode sidang TA tidak ditemukan.',
        'student_phone.required' => 'Nomor HP mahasiswa wajib diisi.',
        'parent_phone.required' => 'Nomor HP orang tua/wali wajib diisi.',
        'student_phone.string' => 'Nomor HP mahasiswa harus berupa teks.',
        'parent_phone.string' => 'Nomor HP orang tua/wali harus berupa teks.',
        'student_phone.max' => 'Nomor HP mahasiswa maksimal 30 karakter.',
        'parent_phone.max' => 'Nomor HP orang tua/wali maksimal 30 karakter.',
        'graduation_payment_date.required' => 'Tanggal pembayaran wisuda wajib diisi.',
        'graduation_payment_date.date' => 'Tanggal pembayaran wisuda tidak valid.',
        'graduation_payment_date.before_or_equal' => 'Tanggal pembayaran wisuda tidak boleh melewati hari ini.',
        'diploma_biodata_accurate.required' => 'Pilih kesesuaian biodata dummy ijazah.',
        'diploma_biodata_accurate.boolean' => 'Pilihan kesesuaian biodata tidak valid.',
        'version.required' => 'Versi draft tidak tersedia. Buka ulang draft terbaru.',
        'version.integer' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
        'version.min' => 'Versi draft tidak valid. Buka ulang draft terbaru.',
        'documents.array' => 'Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.',
        'documents.*.file' => 'Pilih berkas yang valid.',
        'documents.*.uploaded' => 'Berkas gagal diunggah. Periksa ukuran berkas dan coba lagi.',
        'documents.*.max' => 'Ukuran setiap file maksimal 10 MB.',
        'documents.*.mimes' => 'Format dokumen tidak sesuai persyaratan tahap ini.',
        'prohibited' => 'Isian :attribute tidak diperbolehkan pada tahap ini.',
    ];
}

public function attributes(): array
{
    $attributes = [
        'sidang_ta_period_id' => 'periode sidang TA',
        'student_phone' => 'nomor HP mahasiswa',
        'parent_phone' => 'nomor HP orang tua/wali',
        'graduation_payment_date' => 'tanggal pembayaran wisuda',
        'diploma_biodata_accurate' => 'kesesuaian biodata ijazah',
        'documents' => 'dokumen', 'version' => 'versi draft',
    ];
    foreach (\App\Models\SidangTaRegistration::DOCUMENT_TYPES as $type => $label) {
        $attributes["documents.{$type}"] = $label;
    }
    return $attributes;
}
```

Dokumen wajib yang belum tersimpan tetap divalidasi service dengan pesan per dokumen seperti sebelumnya. Jangan mengganti seluruh fileRules menjadi `required`: berkas yang sudah ada tidak perlu diunggah ulang.

## 4. JavaScript wizard dan upload

Ganti seluruh `resources/js/sidang-ta-wizard.js`:

```javascript
export default (initial = {}) => ({
    accurate: initial.accurate ?? '',
    dirty: initial.dirty ?? false,
    busy: false,
    periodHint: initial.periodHint ?? '',
    init() {
        this.$nextTick(() => {
            const viewport = this.$root.querySelector('[data-progress-scroll]');
            const active = this.$root.querySelector('[aria-current="step"]');
            if (viewport && active) {
                viewport.scrollLeft = Math.max(0,
                    viewport.scrollLeft + active.getBoundingClientRect().left
                    - viewport.getBoundingClientRect().left
                    - (viewport.clientWidth - active.offsetWidth) / 2);
            }
            if (!initial.hasErrors) return;
            const field = [...this.$root.querySelectorAll('[aria-invalid="true"]')]
                .find((element) => !element.disabled && element.getClientRects().length > 0);
            const target = field ?? this.$root.querySelector('[data-error-summary]');
            target?.focus();
        });
    },
    updatePeriodHint(select) {
        this.periodHint = select.selectedOptions[0]?.dataset.window ?? '';
    },
    leave(event) {
        if (!this.dirty) return;
        if (!window.confirm('Perubahan belum disimpan. Tetap tinggalkan halaman?')) {
            event.preventDefault();
            return;
        }
        this.dirty = false;
    },
});

export const sidangTaUpload = (initial = {}) => ({
    fileName: '',
    fileSize: '',
    dragDepth: 0,
    localError: '',
    serverError: initial.serverError ?? false,
    selectionChanged: false,
    hasError() {
        return this.localError !== '' || (this.serverError && !this.selectionChanged);
    },
    enter() {
        if (!this.$refs.file.disabled) this.dragDepth++;
    },
    leaveDrop() {
        this.dragDepth = Math.max(0, this.dragDepth - 1);
    },
    picked() {
        if (this.$refs.file.disabled) return;
        const file = this.$refs.file.files[0];
        this.fileName = file?.name ?? '';
        this.fileSize = file ? `${(file.size / 1024 / 1024).toFixed(2)} MB` : '';
        this.localError = '';
        this.selectionChanged = true;
        this.$dispatch('sidang-ta-file-changed');
    },
    drop(event) {
        this.dragDepth = 0;
        if (this.$refs.file.disabled) return;
        const files = event.dataTransfer?.files;
        if (!files || files.length !== 1) {
            this.localError = 'Seret tepat satu berkas. Pilihan sebelumnya tetap dipertahankan.';
            return;
        }
        try {
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            this.$refs.file.files = transfer.files;
        } catch {
            this.localError = 'Drag & drop belum didukung. Klik kotak untuk memilih berkas.';
            return;
        }
        this.picked();
    },
    clear() {
        if (this.$refs.file.disabled) return;
        this.$refs.file.value = '';
        this.fileName = '';
        this.fileSize = '';
        this.localError = '';
        this.selectionChanged = true;
        this.$dispatch('sidang-ta-file-changed');
    },
});
```

Ganti isi pendek `resources/js/app.js` berikut. Pada source hasil scan file ini hanya mengimpor bootstrap, Alpine dan wizard; jika kamu sudah menambah registrasi lain setelah scan, pertahankan registrasi tersebut.

```javascript
import './bootstrap';
import Alpine from 'alpinejs';
import sidangTaWizard, { sidangTaUpload } from './sidang-ta-wizard';

window.Alpine = Alpine;
Alpine.data('sidangTaWizard', sidangTaWizard);
Alpine.data('sidangTaUpload', sidangTaUpload);
Alpine.start();
```

Format/ukuran tetap diperiksa server saat save. Pesan lokal di atas khusus drop yang tidak valid atau tidak didukung. Tidak ada request upload otomatis atau penghapusan file dari JavaScript.

## 5. Partial error dan panel kanan

### Isi lengkap `partials/wizard-error.blade.php`

ID dipakai bersama oleh `aria-describedby` pada field. Jangan mengganti titik menjadi underscore pada satu sisi saja.

```blade
@error($field)
    <p id="error-{{ str_replace('.', '-', $field) }}" class="mt-1.5 text-xs leading-relaxed text-rose-700">
        {{ $message }}
    </p>
@enderror
```

### Isi lengkap `partials/wizard-academic.blade.php`

Panel menggunakan `$academic` yang dikirim controller. Ini data saat membuka wizard, bukan snapshot final historis. Detail pengajuan lama tidak memakai partial ini dengan data mahasiswa terbaru.

```blade
@php
    $display = static fn ($value) => is_scalar($value) && (string) $value !== ''
        ? (string) $value : 'Belum tersedia';
@endphp
<aside data-academic-panel class="min-w-0 space-y-4 xl:col-span-4" aria-label="Informasi akademik mahasiswa">
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Data mahasiswa</h2>
        <p class="break-words text-base font-semibold text-slate-900">{{ $display($academic['student_name'] ?? null) }}</p>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            @foreach(['NIM' => 'student_number', 'Program studi' => 'study_program', 'Semester aktif' => 'semester', 'Email kampus' => 'student_email'] as $label => $key)
                <div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 break-words font-medium text-slate-800">{{ $display($academic[$key] ?? null) }}</dd></div>
            @endforeach
        </dl>
        <p class="text-xs leading-relaxed text-slate-500">Sumber data diperbarui: {{ $display($academic['academic_source_updated_at'] ?? null) }}</p>
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Judul Tugas Akhir</h2>
        @foreach(['Bahasa Indonesia' => 'ta_title', 'Bahasa Inggris' => 'ta_title_en'] as $label => $key)
            <div><h3 class="text-xs font-semibold text-slate-500">{{ $label }}</h3>
                <p class="mt-1.5 break-words rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-800">{{ $display($academic[$key] ?? null) }}</p></div>
        @endforeach
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Pembimbing & Penguji</h2>
        @foreach(['Pembimbing' => ['Pembimbing 1' => 'advisor_1', 'Pembimbing 2' => 'advisor_2'], 'Penguji' => ['Penguji 1' => 'examiner_1', 'Penguji 2' => 'examiner_2', 'Penguji 3' => 'examiner_3']] as $group => $fields)
            <div><h3 class="mb-2 text-xs font-semibold text-slate-500">{{ $group }}</h3>
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach($fields as $label => $key)
                        <div class="grid grid-cols-[6rem_minmax(0,1fr)] gap-3 py-2">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="break-words font-medium text-slate-800">{{ $display($academic[$key] ?? null) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Ringkasan akademik</h2>
        <dl class="grid grid-cols-2 gap-3">
            @foreach(['IPK' => 'gpa', 'SKS lulus' => 'passed_credits', 'TAK' => 'tak_score', 'EPrT' => 'eprt_score'] as $label => $key)
                @php($value = $academic[$key] ?? null)
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 text-lg font-bold text-slate-800">{{ is_numeric($value) ? number_format((float) $value, $key === 'gpa' ? 2 : 0, ',', '.') : $display($value) }}</dd>
                </div>
            @endforeach
        </dl>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach(['Sidang tingkat 2' => 'sidang_tingkat_2', 'Sidang tingkat 3' => 'level_3_status', 'Status BPP' => 'bpp_status', 'Status SKPI' => 'skpi_status', 'Status i-Basila' => 'ibasila_status', 'Ketersediaan TAK' => 'skpi_tak'] as $label => $key)
                <div class="grid grid-cols-2 gap-3 py-2"><dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="break-words font-medium text-slate-800">{{ $display($academic[$key] ?? null) }}</dd></div>
            @endforeach
        </dl>
        <p class="text-xs leading-relaxed text-slate-500">Informasi akademik bersifat baca saja. Lengkapi dokumen sesuai persyaratan pada form.</p>
    </section>
    <section class="campus-card space-y-4 p-5">
        <h2 class="border-b border-slate-100 pb-3 text-base font-bold text-slate-800">Jadwal sidang TA</h2>
        <dl class="space-y-3 text-sm">
            @foreach(['Tanggal' => 'exam_date', 'Waktu' => 'exam_time', 'Lokasi' => 'exam_location'] as $label => $key)
                <div class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3"><dt class="text-slate-500">{{ $label }}</dt>
                    <dd class="break-words font-medium text-slate-800">{{ $display($academic[$key] ?? null) }}</dd></div>
            @endforeach
        </dl>
    </section>
</aside>
```

## 6. Partial upload baru

Ganti seluruh `resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php`. Berkas tersimpan hanya ditawarkan untuk unduh bila path masih ada. Bintang berarti dokumen wajib dipenuhi, bukan harus selalu memilih pengganti.

```blade
@php
    $existing = $draft?->documents?->firstWhere('document_type', $type);
    $available = $existing && \Illuminate\Support\Facades\Storage::disk('local')->exists($existing->file_path);
    $label = \App\Models\SidangTaRegistration::DOCUMENT_TYPES[$type] ?? $type;
    $capture = in_array($type, \App\Support\SidangTaDocumentRequirements::CAPTURES, true);
    $conditional = $conditional ?? null;
    $errorKey = "documents.{$type}";
    $uploadInitial = ['serverError' => $errors->has($errorKey)];
@endphp
<div data-upload-type="{{ $type }}" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4"
     x-data="sidangTaUpload(@js($uploadInitial))">
    <label class="block text-sm font-semibold leading-relaxed text-slate-800" for="file-{{ $type }}">
        {{ $label }} <span class="text-rose-600" aria-hidden="true">*</span>
    </label>
    <p id="help-{{ $type }}" class="mt-1.5 text-xs leading-relaxed text-slate-500">
        {{ \App\Models\SidangTaRegistration::DOCUMENT_DESCRIPTIONS[$type] ?? '' }}
        {{ $capture ? 'PDF/JPG/JPEG/PNG' : 'PDF' }}, maksimal 10 MB per file.
    </p>
    @if($available)
        <div class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <p class="text-xs font-semibold text-emerald-800">Berkas tersimpan</p>
            <p class="break-words text-sm text-slate-800">{{ $existing->file_name }}</p>
            <p class="text-xs text-slate-500">{{ number_format(($existing->file_size ?? 0) / 1024) }} KB · Pilih berkas baru hanya untuk mengganti.</p>
            <a class="btn btn-secondary text-xs" href="{{ route('sidang-ta-draft-documents.download', $existing) }}">
                Unduh berkas
            </a>
        </div>
    @elseif($existing)
        <p class="mt-3 text-sm text-rose-700">Berkas tersimpan tidak ditemukan. Unggah kembali.</p>
    @endif
    <div class="relative mt-3 rounded-xl border-2 border-dashed p-4 transition-colors focus-within:ring-2 focus-within:ring-rose-500 focus-within:ring-offset-2"
         :class="hasError() ? 'border-rose-500 bg-rose-50' : (dragDepth > 0 ? 'border-rose-500 bg-rose-50' : 'border-slate-300 bg-slate-50 hover:border-rose-400')"
         @dragenter.prevent.stop="enter()" @dragover.prevent.stop
         @dragleave.prevent.stop="leaveDrop()" @drop.prevent.stop="drop($event)">
        <input id="file-{{ $type }}" x-ref="file" type="file" name="documents[{{ $type }}]"
               accept="{{ $capture ? '.pdf,.jpg,.jpeg,.png' : '.pdf' }}"
               class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
               aria-describedby="help-{{ $type }} error-documents-{{ $type }}"
               aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
               :aria-invalid="hasError() ? 'true' : 'false'"
               @change="picked()"
               @if($conditional !== null)
                   @disabled($accurate !== $conditional)
                   :disabled="accurate !== '{{ $conditional }}'"
                   :required="accurate === '{{ $conditional }}' && {{ $available ? 'false' : 'true' }}"
               @else
                   @required(!$available)
               @endif>
        <div class="pointer-events-none py-3 text-center">
            <svg aria-hidden="true" class="mx-auto h-7 w-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 16V4m0 0L7 9m5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
            </svg>
            <p class="mt-2 text-sm font-medium text-slate-700">Klik untuk memilih atau seret file ke sini</p>
            <p class="mt-1 text-xs text-slate-500">{{ $capture ? 'PDF/JPG/PNG' : 'PDF' }} · Maksimal 10 MB · Satu berkas</p>
        </div>
    </div>
    <div x-show="fileName" x-cloak class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs font-semibold text-amber-800">Pilihan baru · Belum disimpan</p>
        <p class="break-words text-sm text-slate-800" x-text="fileName"></p>
        <p class="text-xs text-slate-500" x-text="fileSize"></p>
        <button type="button" class="btn btn-secondary text-xs" @click="clear()">Batalkan pilihan baru</button>
    </div>
    <p id="error-documents-{{ $type }}" role="status" aria-live="polite"
       class="mt-1.5 text-xs leading-relaxed text-rose-700" x-show="hasError()">
        <span x-show="localError" x-text="localError"></span>
        <span x-show="!localError && !selectionChanged">{{ $errors->first($errorKey) }}</span>
    </p>
</div>
```

Tombol unduh/batal sengaja berada di luar area input transparan. Link unduh tidak mengubah `dirty`; memilih atau membatalkan pilihan baru mengubah `dirty` melalui event yang diterima root wizard.

## 7. Isi lengkap wizard

Ganti seluruh `resources/views/sidang_ta_registrations/wizard.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">{{ $draft?->source_registration_id ? 'Draft Revisi Sidang TA' : 'Pendaftaran Sidang TA' }}</x-slot>
    @php
        $payload = $draft?->payload ?? [];
        $accurate = (string) old('diploma_biodata_accurate', array_key_exists('diploma_biodata_accurate', $payload)
            ? ($payload['diploma_biodata_accurate'] ? '1' : '0') : '');
        $selectedPeriod = (string) old('sidang_ta_period_id', $draft?->sidang_ta_period_id ?? '');
        $periods = $openPeriods;
        if ($draft && !$periods->contains('id', $draft->sidang_ta_period_id)) {
            $periods = $periods->prepend($draft->period);
        }
        $selected = $periods->first(fn ($period) => (string) $period->id === $selectedPeriod);
        $windowFor = static fn ($period) => $period->open_at->format('d/m/Y H:i').' – '
            .$period->close_at->format('d/m/Y H:i').($period->isOpen() ? '' : ' (ditutup)');
        $initial = ['accurate' => $accurate, 'dirty' => $errors->any(), 'hasErrors' => $errors->any(),
            'periodHint' => $selected ? $windowFor($selected) : ''];
        $permission = $draft?->source_registration_id ? 'sidang_ta_registration_update' : 'sidang_ta_registration_create';
        $titles = [1 => 'Data dasar & kontak', 2 => 'Verifikasi iGracias & akademik',
            3 => 'Pembayaran & dokumen', 4 => 'Biodata ijazah'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-5" data-current-step="{{ $step }}"
         x-data="sidangTaWizard(@js($initial))" @sidang-ta-file-changed="dirty = true"
         @pageshow.window="busy = false"
         @beforeunload.window="if (dirty && !busy) { $event.preventDefault(); $event.returnValue = ''; }">
        @if($errors->any())
            <div data-error-summary tabindex="-1" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <p class="text-sm font-bold">Periksa isian berikut:</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
                <p class="mt-2 text-xs leading-relaxed">Data yang sudah tersimpan tetap tersedia. File pilihan baru yang ditolak perlu dipilih ulang.</p>
            </div>
        @endif
        @if(session('success'))
            <p role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>
        @endif

        <section class="campus-card overflow-hidden" aria-label="Progres pendaftaran">
            <nav aria-label="Tahap pendaftaran" data-progress-scroll class="overflow-x-auto p-4 sm:p-5">
                <ol class="flex min-w-max items-stretch gap-2">
                    @foreach($titles as $number => $title)
                        @php
                            $reachable = $number <= $maxStep;
                            $state = $number === $step ? 'current' : ($number < $step && $number <= $completedThrough
                                ? 'complete' : ($reachable ? 'available' : 'locked'));
                            $tone = match ($state) {
                                'current' => 'border-[#DA2D3B] bg-[#DA2D3B] text-white',
                                'complete' => 'border-rose-200 bg-rose-50 text-rose-800',
                                'available' => 'border-slate-200 bg-white text-slate-700',
                                default => 'border-slate-200 bg-slate-100 text-slate-400',
                            };
                        @endphp
                        <li class="flex items-center gap-2">
                            @if($draft && $reachable)
                                <a data-step="{{ $number }}" data-state="{{ $state }}" @click="leave($event)"
                                   href="{{ route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $number]) }}"
                                   @if($number === $step) aria-current="step" @endif
                                   class="block w-40 rounded-xl border px-3 py-3 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 {{ $tone }}">
                                    <span class="text-sm font-bold">Tahap {{ $number }}{{ $state === 'complete' ? ' ✓' : '' }}</span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">{{ $title }}</span>
                                </a>
                            @else
                                <span data-step="{{ $number }}" data-state="{{ $state }}"
                                      @if($number === $step) aria-current="step" @else aria-disabled="true" @endif
                                      class="block w-40 rounded-xl border px-3 py-3 {{ $tone }}">
                                    <span class="text-sm font-bold">Tahap {{ $number }}</span>
                                    <span class="mt-1 block whitespace-normal text-xs leading-relaxed">{{ $title }}</span>
                                </span>
                            @endif
                            @if($number < 4)
                                <span data-chevron="{{ $number }}" aria-hidden="true"
                                      class="px-1 text-xl font-bold {{ $number <= $step ? 'text-[#DA2D3B]' : 'text-slate-300' }}">&raquo;</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <div data-save-status class="space-y-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs leading-relaxed sm:px-5">
                <p class="font-medium text-slate-700">{{ $draft ? 'Terakhir disimpan '.$draft->updated_at->format('d/m/Y H:i').'.' : 'Belum ada data yang disimpan.' }}</p>
                <p class="text-slate-500">Tekan Simpan agar data dan file dapat dilanjutkan setelah halaman ditutup.</p>
                <p role="status" aria-live="polite" class="font-medium text-amber-800"
                   x-text="busy ? 'Sedang menyimpan…' : (dirty ? 'Ada perubahan yang belum disimpan.' : '')"></p>
            </div>
        </section>

        <div class="grid items-start gap-6 xl:grid-cols-12">
            <div class="min-w-0 space-y-5 xl:col-span-8">
                @if($draft?->sourceRegistration?->review_notes)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <h2 class="text-sm font-bold text-amber-900">Catatan petugas</h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-amber-800">{{ $draft->sourceRegistration->review_notes }}</p>
                    </section>
                @endif
                <form id="sidang-ta-step-form" method="POST" novalidate enctype="multipart/form-data"
                      class="campus-card space-y-5 p-5 sm:p-6"
                      action="{{ $draft ? route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]) : route('sidang-ta-drafts.store') }}"
                      @input="dirty = true" @change="dirty = true"
                      @submit="if (busy) { $event.preventDefault(); } else { busy = true; }">
                    @csrf
                    @if($draft)<input type="hidden" name="version" value="{{ old('version', $draft->version) }}">@endif
                    <header class="space-y-1.5 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-slate-900">Tahap {{ $step }} — {{ $titles[$step] }}</h2>
                        <p class="text-xs text-slate-500">Isian bertanda <span class="font-bold text-rose-600">*</span> wajib diisi.</p>
                    </header>
                    @if($step === 1)
                        <div class="max-w-lg">
                            <label for="sidang-ta-period-id" class="mb-1.5 block text-sm font-semibold text-slate-700">Periode Sidang TA <span class="text-rose-600" aria-hidden="true">*</span></label>
                            @if($draft?->source_registration_id)
                                <input type="hidden" name="sidang_ta_period_id" value="{{ $draft->sidang_ta_period_id }}">
                                <p id="sidang-ta-period-id" tabindex="-1" class="rounded-lg bg-slate-50 p-3 text-sm font-medium text-slate-800"
                                   aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                   aria-describedby="period-window error-sidang_ta_period_id">{{ $draft->period->name }} (periode pengajuan asal)</p>
                            @else
                                <select id="sidang-ta-period-id" name="sidang_ta_period_id" required
                                        class="campus-input w-full {{ $errors->has('sidang_ta_period_id') ? '!border-rose-500' : '' }}"
                                        aria-invalid="{{ $errors->has('sidang_ta_period_id') ? 'true' : 'false' }}"
                                        aria-describedby="period-window error-sidang_ta_period_id"
                                        @change="updatePeriodHint($event.target)">
                                    <option value="">Pilih periode terbuka</option>
                                    @foreach($periods as $period)
                                        <option value="{{ $period->id }}" data-window="{{ $windowFor($period) }}" @selected($selectedPeriod === (string) $period->id)>{{ $period->name }}{{ $period->isOpen() ? '' : ' (ditutup)' }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <p id="period-window" class="mt-1.5 text-xs leading-relaxed text-slate-500"
                               x-text="periodHint || 'Pilih periode yang masih terbuka.'">{{ $initial['periodHint'] ?: 'Pilih periode yang masih terbuka.' }}</p>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'sidang_ta_period_id'])
                        </div>
                        <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label for="student-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor HP mahasiswa <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="student-phone" class="campus-input w-full {{ $errors->has('student_phone') ? '!border-rose-500' : '' }}"
                                       name="student_phone" inputmode="tel" maxlength="30" required placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('student_phone') ? 'true' : 'false' }}" aria-describedby="error-student_phone"
                                       value="{{ old('student_phone', $payload['student_phone'] ?? $student->phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'student_phone'])
                            </div>
                            <div class="min-w-0">
                                <label for="parent-phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Nomor HP orang tua/wali <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="parent-phone" class="campus-input w-full {{ $errors->has('parent_phone') ? '!border-rose-500' : '' }}"
                                       name="parent_phone" inputmode="tel" maxlength="30" required placeholder="08xxxxxxxxxx"
                                       aria-invalid="{{ $errors->has('parent_phone') ? 'true' : 'false' }}" aria-describedby="error-parent_phone"
                                       value="{{ old('parent_phone', $payload['parent_phone'] ?? $student->parent_phone_number) }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'parent_phone'])
                            </div>
                        </div>
                    @elseif($step === 2 || $step === 3)
                        @if($step === 3)
                            <div class="max-w-xs">
                                <label for="graduation-payment-date" class="mb-1.5 block text-sm font-semibold text-slate-700">Tanggal pembayaran wisuda <span class="text-rose-600" aria-hidden="true">*</span></label>
                                <input id="graduation-payment-date" class="campus-input w-full {{ $errors->has('graduation_payment_date') ? '!border-rose-500' : '' }}"
                                       type="date" name="graduation_payment_date" required max="{{ today()->toDateString() }}"
                                       aria-invalid="{{ $errors->has('graduation_payment_date') ? 'true' : 'false' }}" aria-describedby="error-graduation_payment_date"
                                       value="{{ old('graduation_payment_date', $payload['graduation_payment_date'] ?? '') }}">
                                @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'graduation_payment_date'])
                            </div>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::typesForStep($step) as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => null])
                            @endforeach
                        </div>
                    @else
                        <fieldset id="diploma-biodata-group" tabindex="-1" aria-required="true"
                                  aria-invalid="{{ $errors->has('diploma_biodata_accurate') ? 'true' : 'false' }}"
                                  aria-describedby="error-diploma_biodata_accurate">
                            <legend class="text-sm font-semibold text-slate-700">Apakah biodata dummy ijazah sudah sesuai? <span class="text-rose-600" aria-hidden="true">*</span></legend>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['1' => 'Sudah sesuai', '0' => 'Belum sesuai, sudah mengajukan PDM'] as $value => $label)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm leading-relaxed"
                                           :class="accurate === '{{ $value }}' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-slate-200 text-slate-700'">
                                        <input type="radio" name="diploma_biodata_accurate" value="{{ $value }}"
                                               x-model="accurate" @checked($accurate === (string) $value) required
                                               class="mt-0.5 border-slate-300 text-rose-600 focus:ring-rose-500">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @include('sidang_ta_registrations.partials.wizard-error', ['field' => 'diploma_biodata_accurate'])
                        </fieldset>
                        <div x-show="accurate === '1'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            @foreach(\App\Support\SidangTaDocumentRequirements::DIPLOMA as $type)
                                @include('sidang_ta_registrations.partials.wizard-upload', ['type' => $type, 'conditional' => '1'])
                            @endforeach
                        </div>
                        <div x-show="accurate === '0'" x-cloak>
                            @include('sidang_ta_registrations.partials.wizard-upload', ['type' => 'pdm_submission_proof', 'conditional' => '0'])
                        </div>
                    @endif
                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <a @click="leave($event)" class="btn btn-secondary" href="{{ route('sidang-ta-registrations.index') }}">Kembali ke pendaftaran</a>
                        @can($permission)
                            <button type="submit" class="btn btn-primary" :disabled="busy"
                                    x-text="busy ? 'Sedang menyimpan…' : '{{ $step === 4 ? 'Simpan tahap 4' : 'Simpan & Lanjutkan' }}'">
                                {{ $step === 4 ? 'Simpan tahap 4' : 'Simpan & Lanjutkan' }}
                            </button>
                        @endcan
                    </div>
                </form>
                @if($draft && $step === 4)
                    @can($permission)
                        <form method="POST" class="campus-card space-y-3 p-5" action="{{ route('sidang-ta-drafts.review', $draft) }}"
                              @submit="if (dirty || busy) { $event.preventDefault(); } else { busy = true; }">
                            @csrf <input type="hidden" name="version" value="{{ $draft->version }}">
                            <p class="text-sm leading-relaxed text-slate-600">Simpan perubahan tahap 4 dahulu sebelum meninjau. Pendaftaran baru dikirim setelah konfirmasi pada halaman tinjau.</p>
                            <button type="submit" class="btn btn-primary" :disabled="dirty || busy">Tinjau Sebelum Kirim</button>
                        </form>
                    @endcan
                @endif
            </div>
            @include('sidang_ta_registrations.partials.wizard-academic')
        </div>
    </div>
</x-app-layout>
```

`&raquo;` adalah chevron ganda yang terlihat seperti `>>`. Warna tahap 1 tidak lagi bergantung pada `$draft`; hanya apakah boleh berupa link yang bergantung pada draft. Tidak menggunakan `reportValidity()` dan form save memiliki `novalidate`, sehingga error wajib berasal dari server dan ditampilkan di bawah field.

Pastikan aturan global `[x-cloak] { display: none !important; }` yang sudah ada tetap dipertahankan. Tidak perlu menambahnya dua kali.

## 8. Kode lengkap test PHP G1

Isi `tests/Feature/SidangTaWizardUiTest.php` dengan berikut. `RefreshDatabase` sudah dipasang pada semua Feature melalui `tests/Pest.php`, tidak perlu trait tambahan. Fixture berkas sederhana hanya untuk render/keberadaan; validasi MIME nyata sudah diuji pada upload suite sebelumnya.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\User;
use App\Support\SidangTaDocumentRequirements as Requirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create([
        'username' => '1202200001', 'email' => 'wizard-ui@mahasiswa.test',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false,
    ]);
    $this->student = Student::create([
        'student_number' => '1202200001', 'name' => 'Wizard UI Test',
        'email' => $this->user->email, 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'gpa' => 0,
    ]);
    foreach (['read', 'create', 'update'] as $action) {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => "sidang_ta_registration_{$action}", 'guard_name' => 'web',
        ]));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode G1', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->data = ['sidang_ta_period_id' => $this->period->id,
        'student_phone' => '081234567890', 'parent_phone' => '081234567891'];
    $this->actingAs($this->user);
    $this->completeDraft = function () {
        $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)
            ->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        $draft->payload = array_replace($draft->payload, [
            'graduation_payment_date' => today()->toDateString(), 'diploma_biodata_accurate' => true,
        ]);
        $draft->save();
        foreach (array_merge(Requirements::CAPTURES, Requirements::PAYMENT, Requirements::DIPLOMA) as $type) {
            $path = "sidang_ta/drafts/{$draft->id}/{$type}.pdf";
            Storage::disk('local')->put($path, '%PDF-ui');
            $draft->documents()->create([
                'document_type' => $type, 'file_name' => $type.'.pdf',
                'file_path' => $path, 'file_size' => 7, 'file_type' => 'pdf',
            ]);
        }
        return $draft;
    };
});

function sidangTaUiXpath(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new DOMXPath($document);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

test('g1_fresh_step_one_is_active_without_creating_draft_or_unlocking_future_steps', function () {
    $response = $this->get(route('sidang-ta-registrations.create'))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $active = $xpath->query('//*[@data-step="1" and @aria-current="step"]')->item(0);
    expect($active)->not->toBeNull();
    expect($active->getAttribute('class'))->toContain('bg-[#DA2D3B]');
    expect($xpath->query('//*[@aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="2" or @data-step="3" or @data-step="4"]')->length)->toBe(0);
    expect($xpath->query('//*[@data-step="2" and @aria-disabled="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-chevron="1"]')->item(0)->getAttribute('class'))->toContain('text-[#DA2D3B]');
    expect($xpath->query('//section[@aria-label="Progres pendaftaran"]//*[@data-save-status]')->length)->toBe(1);
    $response->assertSee('Belum ada data yang disimpan.');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_step_one_errors_are_inline_and_preserve_valid_contact_input', function () {
    $url = route('sidang-ta-registrations.create');
    $this->from($url)->post(route('sidang-ta-drafts.store'), [
        'sidang_ta_period_id' => '', 'student_phone' => '', 'parent_phone' => '089123456789',
    ])->assertRedirect($url)->assertSessionHasErrors(['sidang_ta_period_id', 'student_phone']);
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//form[@id="sidang-ta-step-form" and @novalidate]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-sidang_ta_period_id"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-student_phone"]')->item(0)->textContent)->toContain('Nomor HP mahasiswa wajib diisi.');
    expect($xpath->query('//input[@name="student_phone" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//input[@name="parent_phone"]')->item(0)->getAttribute('value'))->toBe('089123456789');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('g1_academic_panel_is_present_on_all_four_steps_and_keeps_zero_gpa', function () {
    $this->student->finalProject()->create([
        'thesis_title_id' => 'Judul akademik untuk G1',
        'thesis_title_en' => 'Academic title for G1', 'advisor_1' => 'Dosen Pembimbing G1',
    ]);
    $draft = ($this->completeDraft)();
    foreach ([1, 2, 3, 4] as $step) {
        $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->assertSee('Judul akademik untuk G1')->assertSee('Academic title for G1')
            ->assertSee('Dosen Pembimbing G1')->assertSee('0,00');
        $xpath = sidangTaUiXpath($response->getContent());
        expect($xpath->query('//aside[@data-academic-panel]')->length)->toBe(1);
        expect($xpath->query('//aside[@data-academic-panel]//*[@data-save-status]')->length)->toBe(0);
    }
    expect(SidangTaRegistration::count())->toBe(0);
});

test('g1_saved_draft_still_cannot_navigate_to_unfinished_future_step', function () {
    $id = $this->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//a[@data-step="1"]')->length)->toBe(1);
    expect($xpath->query('//*[@data-step="2" and @aria-current="step"]')->length)->toBe(1);
    expect($xpath->query('//a[@data-step="3" or @data-step="4"]')->length)->toBe(0);
    $this->get(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 4]))
        ->assertRedirect(route('sidang-ta-drafts.edit', ['draft' => $id, 'step' => 2]));
});

test('g1_payment_date_error_is_inline_and_does_not_change_saved_documents', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 3]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 3]), [
        'version' => 1, 'graduation_payment_date' => today()->addDay()->toDateString(),
    ])->assertRedirect($url)->assertSessionHasErrors('graduation_payment_date');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//*[@id="error-graduation_payment_date"]')->item(0)->textContent)
        ->toContain('Tanggal pembayaran wisuda tidak boleh melewati hari ini.');
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(15);
});

test('g1_biodata_error_is_attached_to_radio_group', function () {
    $draft = ($this->completeDraft)();
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 4]), [
        'version' => 1,
    ])->assertRedirect($url)->assertSessionHasErrors('diploma_biodata_accurate');
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    expect($xpath->query('//fieldset[@id="diploma-biodata-group" and @aria-invalid="true"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="error-diploma_biodata_accurate"]')->item(0)->textContent)
        ->toContain('Pilih kesesuaian biodata dummy ijazah.');
    expect($draft->fresh()->version)->toBe(1);
});

test('g1_inactive_biodata_upload_is_initially_disabled_and_has_dynamic_binding', function () {
    $draft = ($this->completeDraft)();
    $response = $this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 4]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $inactive = $xpath->query('//input[@id="file-pdm_submission_proof"]')->item(0);
    expect($inactive->hasAttribute('disabled'))->toBeTrue();
    expect($inactive->getAttribute(':disabled'))->toBe("accurate !== '0'");
    expect($xpath->query('//input[@id="file-diploma_statement" and @disabled]')->length)->toBe(0);
});

test('g1_missing_saved_file_shows_its_inline_error_and_preserves_other_saved_files', function () {
    $draft = ($this->completeDraft)();
    $document = $draft->documents()->where('document_type', Requirements::CAPTURES[0])->firstOrFail();
    $other = $draft->documents()->where('document_type', Requirements::CAPTURES[1])->firstOrFail();
    Storage::disk('local')->delete($document->file_path);
    $url = route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]);
    $this->from($url)->post(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => 2]), [
        'version' => 1,
    ])->assertRedirect($url)->assertSessionHasErrors('documents.'.$document->document_type);
    $response = $this->get($url)->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $error = $xpath->query('//*[@id="error-documents-'.$document->document_type.'"]')->item(0);
    expect($error->textContent)->toContain('Unggah dokumen yang diwajibkan dan belum tersimpan.');
    $response->assertSee(route('sidang-ta-draft-documents.download', $other), false)
        ->assertDontSee(route('sidang-ta-draft-documents.download', $document), false);
    expect(Storage::disk('local')->exists($other->file_path))->toBeTrue();
    expect($draft->fresh()->version)->toBe(1);
    expect($draft->documents()->count())->toBe(15);
});

test('g1_non_field_errors_remain_visible_in_summary', function () {
    $draft = ($this->completeDraft)();
    $errors = (new ViewErrorBag())->put('default', new MessageBag([
        'version' => ['Versi draft tidak valid. Buka ulang draft terbaru.'],
        'documents' => ['Jenis dokumen tidak sesuai tahap atau cabang yang dipilih.'],
    ]));
    $response = $this->withSession(['errors' => $errors])
        ->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))->assertOk();
    $xpath = sidangTaUiXpath($response->getContent());
    $summary = $xpath->query('//*[@data-error-summary]')->item(0);
    expect($summary->textContent)->toContain('Versi draft tidak valid.')
        ->toContain('Jenis dokumen tidak sesuai tahap');
});
```

Ada sembilan test baru. Tidak menggantikan empat belas test wizard lama; regresi old version, query step kosong, dua periode, snapshot dan permission tetap dijalankan.

## 9. Kode lengkap test JavaScript G1

Isi `tests/Js/sidang-ta-wizard.test.mjs`. Tidak perlu menambah npm script atau dependency. Stub input/DataTransfer di sini memeriksa file yang dipasang ke input; drag/drop native dan file picker tetap diuji pada browser.

```javascript
import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import sidangTaWizard, { sidangTaUpload } from '../../resources/js/sidang-ta-wizard.js';

const originalDataTransfer = globalThis.DataTransfer;
before(() => {
    globalThis.DataTransfer = class {
        constructor() {
            this.files = [];
            this.items = { add: (file) => this.files.push(file) };
        }
    };
});
after(() => {
    if (originalDataTransfer === undefined) delete globalThis.DataTransfer;
    else globalThis.DataTransfer = originalDataTransfer;
});

function uploadState() {
    const events = [];
    const input = {
        files: [], disabled: false,
        set value(value) { if (value === '') this.files = []; },
    };
    const state = sidangTaUpload();
    state.$refs = { file: input };
    state.$dispatch = (name) => events.push(name);
    return { state, input, events };
}

test('one dropped file reaches multipart input and marks the draft dirty', () => {
    const { state, input, events } = uploadState();
    const file = { name: 'capture.pdf', size: 1024 * 1024 };
    state.drop({ dataTransfer: { files: [file] } });
    assert.equal(input.files[0], file);
    assert.equal(state.fileName, 'capture.pdf');
    assert.equal(state.fileSize, '1.00 MB');
    assert.equal(state.localError, '');
    assert.deepEqual(events, ['sidang-ta-file-changed']);
});

test('multiple dropped files preserve the previous valid selection', () => {
    const { state, input, events } = uploadState();
    const first = { name: 'previous.pdf', size: 50 };
    input.files = [first];
    state.picked();
    state.drop({ dataTransfer: { files: [{ name: 'a.pdf' }, { name: 'b.pdf' }] } });
    assert.equal(input.files[0], first);
    assert.equal(state.fileName, 'previous.pdf');
    assert.match(state.localError, /tepat satu berkas/);
    assert.equal(events.length, 1);
});

test('non-file drop does not erase the previous selection', () => {
    const { state, input } = uploadState();
    const first = { name: 'previous.pdf', size: 50 };
    input.files = [first];
    state.picked();
    state.drop({ dataTransfer: { files: [] } });
    assert.equal(input.files[0], first);
    assert.equal(state.fileName, 'previous.pdf');
    assert.equal(state.hasError(), true);
});

test('cancel clears only the browser selection and dispatches dirty state', () => {
    const { state, input, events } = uploadState();
    input.files = [{ name: 'replacement.pdf', size: 100 }];
    state.picked();
    state.clear();
    assert.equal(input.files.length, 0);
    assert.equal(state.fileName, '');
    assert.equal(state.fileSize, '');
    assert.deepEqual(events, ['sidang-ta-file-changed', 'sidang-ta-file-changed']);
});

test('inactive branch ignores a drop and does not mark dirty', () => {
    const { state, input, events } = uploadState();
    input.disabled = true;
    state.drop({ dataTransfer: { files: [{ name: 'hidden.pdf', size: 100 }] } });
    state.clear();
    assert.equal(input.files.length, 0);
    assert.equal(state.fileName, '');
    assert.deepEqual(events, []);
});

test('unsupported DataTransfer retains the previous selection and offers file picker', () => {
    const { state, input, events } = uploadState();
    const first = { name: 'previous.pdf', size: 100 };
    input.files = [first];
    state.picked();
    const previous = globalThis.DataTransfer;
    try {
        globalThis.DataTransfer = class { constructor() { throw new Error('Unsupported'); } };
        state.drop({ dataTransfer: { files: [{ name: 'replacement.pdf', size: 100 }] } });
        assert.equal(input.files[0], first);
        assert.equal(state.fileName, 'previous.pdf');
        assert.match(state.localError, /Klik kotak/);
        assert.equal(events.length, 1);
    } finally {
        globalThis.DataTransfer = previous;
    }
});

test('file picker updates filename without claiming that the file is saved', () => {
    const { state, input, events } = uploadState();
    input.files = [{ name: 'picked.pdf', size: 1024 }];
    state.serverError = true;
    state.picked();
    assert.equal(state.fileName, 'picked.pdf');
    assert.equal(state.selectionChanged, true);
    assert.equal(state.hasError(), false);
    assert.deepEqual(events, ['sidang-ta-file-changed']);
});

test('leaving unsaved changes requires one confirmation before navigation', () => {
    const originalWindow = globalThis.window;
    try {
        let confirmations = 0;
        let prevented = 0;
        const state = sidangTaWizard({ dirty: true });
        globalThis.window = { confirm: () => { confirmations++; return false; } };
        state.leave({ preventDefault: () => prevented++ });
        assert.equal(state.dirty, true);
        assert.equal(prevented, 1);
        globalThis.window.confirm = () => { confirmations++; return true; };
        state.leave({ preventDefault: () => prevented++ });
        state.leave({ preventDefault: () => prevented++ });
        assert.equal(state.dirty, false);
        assert.equal(confirmations, 2);
        assert.equal(prevented, 1);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});
```

Ada delapan test JavaScript. Tidak mengakses Laravel, database atau jaringan. Test Node ini dapat dijalankan dari folder development setelah source JavaScript G1 dipasang.

## 10. Jalankan verifikasi

Gunakan parameter DB eksplisit meskipun phpunit.xml lokal masih sementara atau sudah dipulihkan. `dadu_testing` adalah schema testing terpisah pada Podman; jangan arahkan `RefreshDatabase` ke `dadu` aplikasi.

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php
node --test tests/Js/sidang-ta-wizard.test.mjs
```

Sesudah G1 lulus, jalankan suite TA dan build satu kali:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDraftSchemaTest.php tests/Feature/SidangTaPeriodTest.php tests/Feature/SidangTaDraftTest.php tests/Feature/SidangTaDraftUploadTest.php tests/Feature/SidangTaRevisionDraftTest.php tests/Feature/SidangTaWizardTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaWizardUiTest.php
npm run build
```

Hasil yang diharapkan: 9 test G1 PHP, 8 test JavaScript, suite PHP TA lama tetap lulus (total definisi PHP TA menjadi 82), dan build sukses. Angka ini bukan klaim bahwa asisten telah menjalankan suite Laravel/build.

## 11. Checklist browser G1

Jalankan aplikasi dan Vite seperti biasa. Login `krisna@mahasiswa.test` / `password`, lalu pilih role Mahasiswa pada sidebar. Gunakan data lokal seperti pengujian sebelumnya.

- [ ] Mulai pendaftaran tanpa draft aktif: Tahap 1 **dan chevron setelahnya** merah; tahap 2–4 abu-abu dan tidak bisa diklik. Membuka halaman ini belum membuat draft.
- [ ] Simpan tahap 1: tahap 2 menjadi aktif, tahap 1 dapat dikunjungi lagi. Membuka tahap sebelumnya tidak membuat tahap berikutnya yang belum memenuhi prasyarat dapat diakses.
- [ ] Footer card tahapan menampilkan status/waktu terakhir simpan; informasi ini tidak berada pada panel akademik. Ubah isian: muncul belum disimpan. Jangan menganggap perubahan tersimpan sebelum save berhasil.
- [ ] Pada keempat tahap ada panel akademik kanan di desktop. Pada mobile panel berada setelah form, semua informasi tetap dapat dibaca. Judul panjang/nama dosen/email tidak memecah lebar halaman.
- [ ] Dropdown periode satu baris, kedua HP bersebelahan bila cukup ruang, tanggal tahap 3 pendek. Rentang periode berubah pada keterangan saat memilih periode. Kedua periode terbuka tetap muncul.
- [ ] Kosongkan isian wajib dan simpan: tidak ada popup required HTML; pesan berada di bawah field, border merah, fokus menuju field invalid pertama. Error umum seperti version/documents parent tetap terlihat pada ringkasan.
- [ ] Upload melalui klik dan melalui drag/drop masing-masing satu file; pilihan terlihat **Belum disimpan**. Setelah save dan buka kembali, nama/ukuran benar dan tombol Unduh berkas berfungsi.
- [ ] Dengan pilihan valid yang belum disimpan, drop dua file atau teks/link: pesan muncul, pilihan valid sebelumnya tetap ada. Klik kotak tetap membuka file picker.
- [ ] Pilih file pengganti pada dokumen yang sudah tersimpan, tekan Batalkan pilihan baru: nama pilihan baru hilang, berkas tersimpan tetap dapat diunduh dan tidak ada request hapus. Navigasi masih memperingatkan perubahan yang belum disimpan secara konservatif.
- [ ] Pilih format/ukuran salah lalu save: server menolak dan error muncul di bawah dokumen; file tersimpan tidak hilang. Pilihan baru perlu dipilih ulang setelah redirect.
- [ ] Tahap 4: pindah cabang sesuai/belum sesuai. Hanya input cabang aktif enabled dan ikut request. Simpan cabang yang dipilih, lalu tinjau; tidak ada field tersembunyi yang memunculkan popup browser atau salah ikut upload.
- [ ] Simpan tahap 4, tinjau, kembali melalui tombol Back browser: status busy pulih dan tombol dapat digunakan. Perubahan belum disimpan tetap memblokir Tinjau.
- [ ] Gunakan Tab/Shift+Tab/Enter/Space pada input file, radio, tombol dan navigasi. Fokus pada kotak upload terlihat. Navigasi tahapan pada mobile scroll hanya di card, tanpa halaman melebar; tahap aktif terlihat saat dimuat.
- [ ] Uji ulang revisi milik sendiri: periode asal tidak dapat diganti, catatan petugas tampil terpisah, file pinjaman final dapat diunduh, alur save/tinjau/finalisasi tetap bekerja.

## 12. Berkas untuk rescan dan langkah berikutnya

Sesudah test dan browser G1 lulus, salin berkas pada tabel bagian 1 ke dadu-test. Jangan menyalin `.env`, data database, vendor, node_modules, hasil build/cache atau phpunit.xml sementara. Pengguna tetap memulihkan phpunit.xml sebelum commit/push sebagaimana keputusan sebelumnya.

Minta rescan G1 sebelum G2. G2 akan mengubah tombol download pada tinjau, tata letak snapshot detail dan visibilitas Perbaiki Pengajuan. Jangan mengaktifkan kembali `form.blade.php`/partial upload legacy untuk mendapatkan tampilan lama; wizard baru tetap satu-satunya alur draft.

Referensi yang diperiksa melalui Context7: [validasi Laravel 13](https://github.com/laravel/docs/blob/13.x/validation.md), [Blade error](https://github.com/laravel/docs/blob/13.x/blade.md), [Alpine scope x-data](https://alpinejs.dev/directives/data), [Alpine nextTick](https://alpinejs.dev/magics/nextTick), [Alpine dispatch](https://alpinejs.dev/magics/dispatch).

## Catatan verifikasi panduan oleh asisten

Seluruh blok PHP pada dokumen lolos lint; empat blok Blade dikompilasi di memori dengan resolusi component tag dimatikan, lalu hasil PHP lolos lint. Seluruh blok JavaScript lolos syntax check dan delapan test JavaScript lulus ketika dijalankan dari isi Markdown melalui stdin. Pemeriksaan ini tidak menjalankan aplikasi atau menggambar hasil UI. Sembilan test Feature Laravel, suite database dan build belum dijalankan asisten. Hash source kedua folder sebelum/sesudah penulisan tidak berubah; hanya dokumentasi yang diubah.
