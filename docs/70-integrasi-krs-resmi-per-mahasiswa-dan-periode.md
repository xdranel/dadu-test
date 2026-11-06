# 70 — Dukungan impor KRS resmi dan penggantian daftar per periode

> Ikuti urutan pada dokumen ini di repo development asli. Asisten hanya menulis panduan Markdown; pengguna menerapkan kode → menjalankan test → menyalin enam file ke dadu-test → rescan. Tidak ada operasi database, source aplikasi, atau deployment oleh asisten.

**Goal:** menerima format KRS bertingkat dari exporter resmi dan menyelaraskan daftar mahasiswa/periode dengan ekspor terbaru yang lengkap.

**Architecture:** normalizer murni membaca wrapper/baris dan memetakan field. Service terpisah mencocokkan Student, mengaitkan periode eksplisit, lalu memperbarui dan memangkas KRS dalam satu transaksi per mahasiswa. Controller mendeteksi format resmi sebelum jalur impor lama; format datar/array indeks tetap memakai kode lama.

**Tech Stack:** PHP proyek `^8.4.27`, Laravel13, Pest4, MySQL Podman untuk test lokal. Tidak menambahkan dependency atau mengubah `.env`/`phpunit.xml`.

**Spec:** [69 — contoh resmi](69-contoh-krs-resmi-dan-json-demo.md). Pengguna menegaskan `krs_mata_kuliah` **selalu lengkap** dan memilih penggantian daftar pada periode yang sama. Contoh sumber KRS sudah tersedia; kejelasan periode BPP tetap ditunggu.

## 1. Keputusan dan batas perubahan

Pendekatan yang dipakai adalah normalizer+service khusus, karena `SyncController` sudah besar dan jalur lama masih dipakai fixture regresi. Memperbesar cabang lama berisiko mencampur dua makna semester; mengubah JSON secara manual ke format datar tidak memberi dukungan langsung pada ekspor asli.

- `tahun_ajaran="2627"` → `2026/2027`; `semester="1"`/`"2"` pada item KRS → term Ganjil/Genap. Periode harus sudah terdaftar; importer tidak membuat atau mengaktifkannya otomatis.
- Semester studi berasal dari Student yang sudah tersedia dan valid. Untuk enrollment yang sudah tertaut ke periode yang sama, semester studi lama dipertahankan saat impor ulang, sehingga perubahan `active_semester` tidak menduplikasi baris.
- NIM/nama harus cocok, dengan trim dan pencocokan nama tanpa membedakan huruf besar/kecil. Importer tidak membuat Student/User atau menimpa identitas mahasiswa.
- Setelah semua item mahasiswa valid dan berhasil disimpan, hapus KRS yang sudah tidak tercantum **hanya untuk NIM tersebut dan setiap periode yang benar-benar diwakili item sumber**. Mahasiswa yang tidak diekspor, periode lain, dan snapshot pengajuan final tetap dipertahankan.
- Validasi/pemetaan/penyimpanan yang gagal membatalkan seluruh perubahan mahasiswa itu, termasuk pemangkasan yang sudah terjadi. Mahasiswa valid lain dalam file tetap diproses.
- Daftar kosong tidak memberikan periode karena konteks periode hanya tersedia pada item. Untuk kondisi ini, skip dengan catatan dan pertahankan data lama; jangan menyimpulkan periode dari mahasiswa lain atau periode aktif.
- KRS lama dengan `academic_period_id=null` tidak otomatis dipetakan berdasarkan tahun/semester studi. Jika menghalangi identitas course/year yang baru, mahasiswa dilewati untuk pemeriksaan periode riwayat. Ini mencegah KRS Ganjil lama berubah menjadi Genap ketika mahasiswa mengulang mata kuliah.
- Exporter tidak memberi SKS atau semester kurikulum mata kuliah. Jangan membuat Course stub dengan angka3 atau memakai term1 sebagai semester kurikulum. SKS enrollment lama dipertahankan; enrollment baru hanya mengambil SKS dari master dengan kode/tahun/term yang sama, atau null jika belum ada. Schema saat ini sudah mengizinkan SKS null dan tidak lagi mempunyai FK `student_courses.course_code` ke master tunggal sejak migration September.
- Nama dosen lama dikosongkan jika kode dosen berubah agar tidak menampilkan nama dosen sebelumnya bersama kode baru.
- Format KRS datar/array indeks dan kebijakan lama tetap tersedia. Penggantian daftar pada checkpoint ini hanya berlaku untuk format resmi bertingkat.

### Hasil scan yang menentukan migration

File asli memuat4.277 mahasiswa dan30.590 mata kuliah. Ada239 entri dengan nama kelas melebihi20 karakter; panjang maksimum23. Kolom `student_courses.course_class` sekarang varchar20 sehingga perlu diperlebar ke255, tanpa memotong data sumber.

Lokasi awal file sudah berpindah ke `/home/xdranel/Downloads/data_dadu/rekap_igracias_2026-11-06 KRS.json` saat pemeriksaan ulang. File tetap dibaca saja dan tidak disalin ke repo.

## 2. Enam file yang diterapkan pengguna

| File | Perubahan |
| --- | --- |
| `app/Support/OfficialKrsJsonNormalizer.php` | Baru; deteksi format, validasi wrapper/baris, pemetaan field |
| `app/Services/OfficialKrsJsonImportService.php` | Baru; transaksi sinkronisasi KRS per mahasiswa/periode |
| `app/Http/Controllers/SyncController.php` | Tambah deteksi sebelum unwrap serta satu helper; method impor lama dipertahankan |
| `database/migrations/*_expand_student_course_class_for_official_krs.php` | Baru; varchar20 menjadi255, timestamp mengikuti Artisan |
| `tests/Unit/OfficialKrsJsonNormalizerTest.php` | Baru;17 test murni |
| `tests/Feature/OfficialKrsJsonImportTest.php` | Baru;34 test HTTP/database |

### Fokus review

- [x] Pembatalan TUGAS AKHIR pada sumber lengkap harus menghilangkan kelayakan KRS TA pada periode itu.
- [x] Baris gagal atau pemangkasan yang gagal setelah sebagian periode diproses harus memulihkan transaksi mahasiswa.
- [x] Nama kelas panjang disimpan utuh; rollback migration tidak memotong nama yang sudah panjang.
- [x] Periode eksplisit dan semester studi tidak tercampur; periode tidak aktif boleh disimpan sebagai riwayat, tetapi tidak otomatis memenuhi syarat periode aktif.
- [x] Catatan lama tanpa periode/kunci yang bentrok tidak dipetakan dengan tebakan; format lama dan data mahasiswa/periode lain dipertahankan.

Checklist di atas adalah cakupan yang dimasukkan dalam snippet test, bukan klaim bahwa test MySQL sudah dijalankan asisten.

## 3. Tahap A — test normalizer dahulu

Pada laptop, dari repo development asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Support/OfficialKrsJsonNormalizer
php artisan make:test OfficialKrsJsonNormalizerTest --unit --pest
```

- [ ] Isi seluruh `tests/Unit/OfficialKrsJsonNormalizerTest.php` berikut:

```php
<?php

use App\Support\OfficialKrsJsonNormalizer as Normalizer;

function krs70Course(array $overrides = []): array
{
    return array_replace(['kode_mk' => 'KRS70TA', 'nama_mk' => 'TUGAS AKHIR',
        'kelas' => 'IF-DEMO-70', 'kode_dosen' => 'D70', 'tahun_ajaran' => '2627',
        'semester' => '1', 'program_studi' => 'Informatika'], $overrides);
}

function krs70Student(array $courses = [], array $overrides = []): array
{
    $courses = $courses === [] ? [krs70Course()] : $courses;
    return array_replace(['no' => 1, 'nim' => '1202270001', 'nama' => 'Mahasiswa KRS 70',
        'jumlah_mk_krs' => count($courses), 'krs_mata_kuliah' => $courses], $overrides);
}

test('krs70_detects_wrapped_bare_and_single_official_rows_without_claiming_legacy_formats', function () {
    foreach ([['selected_menus' => ['krs_mk'], 'data' => []], ['data' => [krs70Student()]],
        [krs70Student()], krs70Student()] as $payload) {
        expect(Normalizer::supports($payload))->toBeTrue();
    }
    foreach ([['data' => [['nim' => '1202270001', 'kode_matakuliah' => 'OLD']]],
        [['1202270001', 'Nama']], ['data' => [['status_skpi' => 'Disetujui']]]] as $payload) {
        expect(Normalizer::supports($payload))->toBeFalse();
    }
});

test('krs70_extracts_official_rows_and_checks_wrapper_total_and_menu', function () {
    $row = krs70Student();
    $n = new Normalizer;
    expect($n->rows(['selected_menus' => ['krs_mk'], 'total' => 1, 'data' => [$row]]))->toBe([$row]);
    expect($n->rows([$row]))->toBe([$row]);
    expect($n->rows($row))->toBe([$row]);
    expect($n->rows(['selected_menus' => ['krs_mk'], 'total' => 0, 'data' => []]))->toBe([]);
});

test('krs70_rejects_invalid_wrapper_context_before_any_import', function () {
    foreach ([['total' => 2], ['total' => '1'], ['selected_menus' => ['bahasa']],
        ['selected_menus' => 'krs_mk'], ['selected_menus' => ['krs_mk' => true]],
        ['selected_menus' => ['krs_mk', false]], ['data' => 'broken'], ['data' => krs70Student()]] as $patch) {
        expect(fn () => (new Normalizer)->rows(array_replace(
            ['selected_menus' => ['krs_mk'], 'data' => [krs70Student()]], $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_duplicate_student_rows_in_the_same_batch', function () {
    expect(fn () => (new Normalizer)->rows([krs70Student(), krs70Student()]))
        ->toThrow(InvalidArgumentException::class);
});

test('krs70_maps_source_year_and_term_without_creating_a_study_semester_or_credits', function () {
    $r = (new Normalizer)->normalize(krs70Student());
    expect($r['student_number'])->toBe('1202270001');
    expect($r['courses'][0])->toBe(['course_code' => 'KRS70TA', 'course_name' => 'TUGAS AKHIR',
        'course_class' => 'IF-DEMO-70', 'lecturer_code' => 'D70', 'study_program' => 'Informatika',
        'academic_year' => '2026/2027', 'term' => 1]);
    expect($r['courses'][0])->not->toHaveKey('semester')->not->toHaveKey('credits');
});

test('krs70_supports_explicit_even_term_and_other_consecutive_years', function () {
    $course = (new Normalizer)->normalize(krs70Student([krs70Course(['tahun_ajaran' => '2728', 'semester' => '2'])]))['courses'][0];
    expect($course['academic_year'])->toBe('2027/2028');
    expect($course['term'])->toBe(2);
});

test('krs70_preserves_class_names_longer_than_the_old_twenty_character_column', function () {
    $class = 'KELAS-DEMO-PANJANG-12345';
    expect(mb_strlen($class))->toBeGreaterThan(20);
    expect((new Normalizer)->normalize(krs70Student([krs70Course(['kelas' => $class])]))['courses'][0]['course_class'])->toBe($class);
});

test('krs70_trims_text_and_treats_an_empty_lecturer_code_as_unknown', function () {
    $row = krs70Student([krs70Course(['kode_mk' => ' KRS70TA ', 'nama_mk' => ' TUGAS AKHIR ', 'kode_dosen' => ' '])],
        ['nim' => ' 1202270001 ', 'nama' => ' Mahasiswa KRS 70 ']);
    $r = (new Normalizer)->normalize($row);
    expect($r['name'])->toBe('Mahasiswa KRS 70');
    expect($r['courses'][0]['course_code'])->toBe('KRS70TA');
    expect($r['courses'][0]['lecturer_code'])->toBeNull();
});

test('krs70_rejects_invalid_or_nonconsecutive_academic_years', function () {
    foreach (['2628', '26/27', '2026/2027', '', null, 2627, true] as $year) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(['tahun_ajaran' => $year])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_never_reads_a_study_semester_as_the_academic_term', function () {
    foreach (['0', '3', '8', null, 1, true] as $term) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(['semester' => $term])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_incomplete_or_miscounted_course_lists', function () {
    foreach ([['jumlah_mk_krs' => 2], ['jumlah_mk_krs' => '1'], ['jumlah_mk_krs' => -1],
        ['krs_mata_kuliah' => null], ['krs_mata_kuliah' => ['course' => krs70Course()]],
        ['krs_mata_kuliah' => [false]]] as $patch) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_keeps_a_genuine_empty_list_distinct_from_an_invalid_missing_list', function () {
    $row = krs70Student(overrides: ['jumlah_mk_krs' => 0, 'krs_mata_kuliah' => []]);
    expect((new Normalizer)->normalize($row)['courses'])->toBe([]);
    unset($row['krs_mata_kuliah']);
    expect(fn () => (new Normalizer)->normalize($row))->toThrow(InvalidArgumentException::class);
});

test('krs70_rejects_repeated_course_keys_in_one_students_period', function () {
    expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course(), krs70Course(['kelas' => 'OTHER'])])))
        ->toThrow(InvalidArgumentException::class);
});

test('krs70_requires_every_official_course_field', function () {
    foreach (array_keys(krs70Course()) as $key) {
        $course = krs70Course(); unset($course[$key]);
        expect(fn () => (new Normalizer)->normalize(krs70Student([$course])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_rejects_invalid_identifiers_empty_names_and_oversized_fields', function () {
    foreach ([['nim' => true], ['nim' => ''], ['nim' => str_repeat('1', 16)], ['nama' => null], ['nama' => ' ']] as $patch) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: $patch)))
            ->toThrow(InvalidArgumentException::class);
    }
    foreach (['kode_mk' => 21, 'nama_mk' => 256, 'kelas' => 256, 'kode_dosen' => 21, 'program_studi' => 256] as $field => $length) {
        expect(fn () => (new Normalizer)->normalize(krs70Student([krs70Course([$field => str_repeat('X', $length)])])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_source_errors_prevent_a_row_from_overwriting_existing_krs', function () {
    foreach (['krs_mk: sumber gagal', ['error'], false] as $error) {
        expect(fn () => (new Normalizer)->normalize(krs70Student(overrides: ['error' => $error])))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('krs70_does_not_mutate_input_or_guess_from_the_current_date', function () {
    $row = krs70Student([krs70Course(['tahun_ajaran' => '2425'])]);
    $copy = $row;
    expect((new Normalizer)->normalize($row)['courses'][0]['academic_year'])->toBe('2024/2025');
    expect($row)->toBe($copy);
});
```

- [ ] Jalankan sebelum mengisi class; expected gagal karena method/implementasi belum tersedia:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/OfficialKrsJsonNormalizerTest.php --stop-on-failure
```

- [ ] Isi seluruh `app/Support/OfficialKrsJsonNormalizer.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

class OfficialKrsJsonNormalizer
{
    public static function supports(array $payload): bool
    {
        if (is_array($payload['selected_menus'] ?? null)
            && in_array('krs_mk', $payload['selected_menus'], true)) return true;
        if (array_key_exists('krs_mata_kuliah', $payload)) return true;
        $rows = $payload['data'] ?? $payload;
        if (!is_array($rows)) return false;
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists('krs_mata_kuliah', $row)) return true;
        }
        return false;
    }

    public function rows(array $payload): array
    {
        $wrapped = array_key_exists('data', $payload);
        $rows = $wrapped ? $payload['data']
            : (array_key_exists('krs_mata_kuliah', $payload) ? [$payload] : $payload);
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('Data KRS harus berupa daftar mahasiswa.');
        }
        if ($wrapped && array_key_exists('selected_menus', $payload)) {
            $menus = $payload['selected_menus'];
            if (!is_array($menus) || !array_is_list($menus)
                || array_filter($menus, fn ($v) => !is_string($v)) !== []
                || !in_array('krs_mk', $menus, true)) {
                throw new InvalidArgumentException('Menu ekspor harus memuat krs_mk.');
            }
        }
        if ($wrapped && array_key_exists('total', $payload)
            && (!is_int($payload['total']) || $payload['total'] !== count($rows))) {
            throw new InvalidArgumentException('Total mahasiswa tidak cocok dengan isi ekspor.');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['nim'] ?? null)) continue;
            $nim = trim($row['nim']);
            if (isset($seen[$nim])) throw new InvalidArgumentException('NIM berulang dalam satu ekspor.');
            $seen[$nim] = true;
        }
        return $rows;
    }

    public function normalize(array $row): array
    {
        if (array_key_exists('error', $row) && $row['error'] !== null && $row['error'] !== '') {
            throw new InvalidArgumentException('Pengambilan KRS sumber gagal; data lama dipertahankan.');
        }
        $nim = $this->text($row['nim'] ?? null, 15);
        if (preg_match('/^\d{1,15}$/D', $nim) !== 1) {
            throw new InvalidArgumentException('NIM KRS tidak valid.');
        }
        $name = $this->text($row['nama'] ?? null, 255);
        $courses = $row['krs_mata_kuliah'] ?? null;
        $count = $row['jumlah_mk_krs'] ?? null;
        if (!is_array($courses) || !array_is_list($courses)
            || !is_int($count) || $count < 0 || $count !== count($courses)) {
            throw new InvalidArgumentException('Daftar atau jumlah mata kuliah KRS tidak valid.');
        }
        $result = $seen = [];
        foreach ($courses as $course) {
            if (!is_array($course)) throw new InvalidArgumentException('Item KRS harus berupa objek.');
            foreach (['kode_mk', 'nama_mk', 'kelas', 'kode_dosen', 'tahun_ajaran', 'semester', 'program_studi'] as $key) {
                if (!array_key_exists($key, $course)) throw new InvalidArgumentException('Field KRS sumber belum lengkap.');
            }
            $rawYear = $this->text($course['tahun_ajaran'], 4);
            if (preg_match('/^(\d{2})(\d{2})$/D', $rawYear, $parts) !== 1
                || (((int) $parts[1] + 1) % 100) !== (int) $parts[2]) {
                throw new InvalidArgumentException('Tahun ajaran KRS harus berupa dua tahun berurutan, misalnya 2627.');
            }
            if (!in_array($course['semester'], ['1', '2'], true)) {
                throw new InvalidArgumentException('Term KRS harus string 1 atau 2, bukan semester studi.');
            }
            $start = 2000 + (int) $parts[1];
            $item = ['course_code' => $this->text($course['kode_mk'], 20),
                'course_name' => $this->text($course['nama_mk'], 255),
                'course_class' => $this->text($course['kelas'], 255),
                'lecturer_code' => $this->text($course['kode_dosen'], 20, true),
                'study_program' => $this->text($course['program_studi'], 255),
                'academic_year' => $start.'/'.($start + 1), 'term' => (int) $course['semester']];
            $key = json_encode([$item['academic_year'], $item['term'], $item['course_code']], JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) throw new InvalidArgumentException('Mata kuliah berulang pada periode yang sama.');
            $seen[$key] = true;
            $result[] = $item;
        }
        return ['student_number' => $nim, 'name' => $name, 'courses' => $result];
    }

    private function text(mixed $value, int $max, bool $nullable = false): ?string
    {
        if ($nullable && $value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Field teks KRS tidak valid.');
        $value = trim($value);
        if ($nullable && $value === '') return null;
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Field teks KRS kosong atau melebihi panjang kolom.');
        }
        return $value;
    }
}
```

- [ ] Ulangi command unit tadi. Target17 passed. Normalizer tidak memakai database, tanggal berjalan, atau keputusan kelayakan TA.

## 4. Tahap B — test integrasi sebelum memasang service/controller

```bash
php artisan make:class Services/OfficialKrsJsonImportService
php artisan make:test OfficialKrsJsonImportTest --pest
php artisan make:migration expand_student_course_class_for_official_krs --table=student_courses
```

Jalankan make:migration sekali dan catat nama timestamp-nya. Jangan membuat migration bernama sama dua kali.

- [ ] Isi seluruh `tests/Feature/OfficialKrsJsonImportTest.php`. Test menggunakan payload sintetis mandiri; tidak bergantung pada fixture JSON manual atau data nyata Downloads.

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentCourse;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\OfficialKrsJsonImportService;
use App\Services\SidangTaAcademicSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function officialKrs70Course(array $overrides = []): array
{
    return array_replace(['kode_mk' => 'KRS70TA', 'nama_mk' => 'TUGAS AKHIR',
        'kelas' => 'KELAS-DEMO-PANJANG-12345', 'kode_dosen' => 'D70',
        'tahun_ajaran' => '2627', 'semester' => '1', 'program_studi' => 'Informatika'], $overrides);
}

function officialKrs70Row(array $courses = [], array $overrides = []): array
{
    $courses = $courses === [] ? [officialKrs70Course()] : $courses;
    return array_replace(['no' => 1, 'nim' => '1202270001', 'nama' => 'Mahasiswa KRS 70',
        'jumlah_mk_krs' => count($courses), 'krs_mata_kuliah' => $courses], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->student = Student::create(['student_number' => '1202270001', 'name' => 'Mahasiswa KRS 70',
        'email' => 'krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'KRS 70', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $this->run70 = fn (array $rows) => app(OfficialKrsJsonImportService::class)->import($rows);
    $this->upload70 = function (array $payload) {
        return $this->from(route('sync.index'))->post(route('sync.import-json'), ['type' => 'krs',
            'json_file' => UploadedFile::fake()->createWithContent('krs70.json', json_encode($payload, JSON_THROW_ON_ERROR))]);
    };
    $this->enrollment70 = fn () => StudentCourse::where('student_number', '1202270001')->where('course_code', 'KRS70TA')->firstOrFail();
});

test('official_krs70_upload_reads_nested_courses_and_logs_students_and_course_counts', function () {
    $second = officialKrs70Course(['kode_mk' => 'KRS70OTHER', 'nama_mk' => 'MATA KULIAH DEMO']);
    ($this->upload70)(['selected_menus' => ['krs_mk'], 'total' => 1,
        'data' => [officialKrs70Row([officialKrs70Course(), $second])]])
        ->assertRedirect(route('sync.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
    $log = SyncLog::where('module', 'krs')->latest('id')->firstOrFail();
    expect($log->status)->toBe('completed');
    expect((int) $log->total_records)->toBe(1);
    expect((int) $log->processed_records)->toBe(1);
    expect((int) $log->skipped_records)->toBe(0);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(2);
});

test('official_krs70_preserves_study_semester_and_long_class_without_inventing_master_data', function () {
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    $krs = ($this->enrollment70)();
    expect((int) $krs->academic_period_id)->toBe($this->period->id);
    expect((int) $krs->semester)->toBe(8);
    expect($krs->academic_year)->toBe('2026/2027');
    expect($krs->course_class)->toBe('KELAS-DEMO-PANJANG-12345');
    expect($krs->credits)->toBeNull();
    expect(Course::where('course_code', 'KRS70TA')->exists())->toBeFalse();
    expect((int) $this->student->fresh()->active_semester)->toBe(8);
    expect($this->student->fresh()->email)->toBe('krs70@example.test');
});

test('official_krs70_reimport_updates_the_same_record_after_the_students_study_semester_changes', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    $this->student->update(['active_semester' => 9]);
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kelas' => 'NEW70'])])]);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($id);
    expect(($this->enrollment70)()->course_class)->toBe('NEW70');
    expect((int) ($this->enrollment70)()->semester)->toBe(8);
});

test('official_krs70_skips_unknown_students_without_creating_accounts_or_student_rows', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nim' => '1202270999'])]);
    expect($result['skipped'])->toBe(1);
    expect(Student::where('student_number', '1202270999')->exists())->toBeFalse();
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_rejects_name_mismatch_without_overwriting_student_identity', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nama' => 'Nama Berbeda'])]);
    expect($result['mismatched'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
    expect($this->student->fresh()->name)->toBe('Mahasiswa KRS 70');
});

test('official_krs70_accepts_name_case_and_surrounding_whitespace', function () {
    $result = ($this->run70)([officialKrs70Row(overrides: ['nama' => ' MAHASISWA KRS 70 '])]);
    expect($result['processed'])->toBe(1);
});

test('official_krs70_missing_period_skips_the_student_without_guessing_or_creating_a_period', function () {
    $this->period->delete();
    $result = ($this->run70)([officialKrs70Row()]);
    expect($result['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
    expect(AcademicPeriod::count())->toBe(0);
});

test('official_krs70_two_active_periods_do_not_change_the_explicit_import_period', function () {
    AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Other active 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true]);
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($this->period->id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unknown');
});

test('official_krs70_uses_the_explicit_term_even_when_another_term_is_active', function () {
    $this->period->update(['is_active' => false]);
    AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Even 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => true]);
    expect(($this->run70)([officialKrs70Row()])['processed'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($this->period->id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unknown');
});

test('official_krs70_malformed_second_course_does_not_partially_import_the_first', function () {
    $bad = officialKrs70Course(['kode_mk' => 'BAD70', 'semester' => '8']);
    expect(($this->run70)([officialKrs70Row([officialKrs70Course(), $bad])])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_missing_period_later_in_the_row_leaves_no_partial_import', function () {
    $other = officialKrs70Course(['kode_mk' => 'LATER70', 'tahun_ajaran' => '2728']);
    expect(($this->run70)([officialKrs70Row([officialKrs70Course(), $other])])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_a_failed_student_does_not_prevent_another_valid_student_from_importing', function () {
    $other = Student::create(['student_number' => '1202270002', 'name' => 'Mahasiswa Kedua KRS 70',
        'email' => 'second-krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    $rows = [officialKrs70Row(overrides: ['nama' => 'Mismatch']),
        officialKrs70Row(overrides: ['nim' => $other->student_number, 'nama' => $other->name])];
    $result = ($this->run70)($rows);
    expect($result['processed'])->toBe(1);
    expect($result['mismatched'])->toBe(1);
    expect(StudentCourse::where('student_number', $other->student_number)->count())->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(0);
});

test('official_krs70_credits_use_only_an_existing_master_for_the_same_year_and_term', function () {
    Course::create(['course_code' => 'KRS70TA', 'name' => 'Master KRS 70', 'study_program' => 'Informatika',
        'class' => 'M70', 'semester' => 8, 'credits' => 4, 'academic_year' => '2026/2027', 'semester_term' => 'GANJIL']);
    ($this->run70)([officialKrs70Row()]);
    expect((int) ($this->enrollment70)()->credits)->toBe(4);
    ($this->enrollment70)()->update(['credits' => 6]);
    ($this->run70)([officialKrs70Row()]);
    expect((int) ($this->enrollment70)()->credits)->toBe(6);
});

test('official_krs70_never_borrows_master_credits_from_another_period', function () {
    Course::create(['course_code' => 'KRS70TA', 'name' => 'Master Old KRS 70', 'study_program' => 'Informatika',
        'class' => 'M70', 'semester' => 8, 'credits' => 4, 'academic_year' => '2025/2026', 'semester_term' => 'GENAP']);
    ($this->run70)([officialKrs70Row()]);
    expect(($this->enrollment70)()->credits)->toBeNull();
});

test('official_krs70_empty_lists_and_source_errors_preserve_existing_enrollments', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    foreach ([['jumlah_mk_krs' => 0, 'krs_mata_kuliah' => []], ['error' => 'krs_mk: gagal']] as $patch) {
        expect(($this->run70)([officialKrs70Row(overrides: $patch)])['skipped'])->toBe(1);
        expect(($this->enrollment70)()->id)->toBe($id);
    }
});

test('official_krs70_invalid_study_semester_does_not_use_term_one_as_a_replacement', function () {
    $this->student->update(['active_semester' => 0]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_legacy_flat_krs_and_new_format_share_the_existing_record', function () {
    ($this->upload70)(['data' => [['nim' => '1202270001', 'kode_matakuliah' => 'KRS70TA',
        'nama_matakuliah' => 'TUGAS AKHIR', 'kelas_matakuliah' => 'OLD70', 'sks' => 4,
        'tahun_akademik' => '2026/2027', 'semester_term' => 'GANJIL', 'semester' => 8]]])
        ->assertSessionHas('success');
    $id = ($this->enrollment70)()->id;
    ($this->run70)([officialKrs70Row()]);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($id);
});

test('official_krs70_rejects_wrapper_count_mismatch_before_creating_a_sync_log', function () {
    ($this->upload70)(['selected_menus' => ['krs_mk'], 'total' => 2, 'data' => [officialKrs70Row()]])
        ->assertSessionHasErrors('json_file');
    expect(SyncLog::where('module', 'krs')->count())->toBe(0);
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_duplicate_student_rows_are_rejected_before_writing', function () {
    ($this->upload70)(['data' => [officialKrs70Row(), officialKrs70Row()]])
        ->assertSessionHasErrors('json_file');
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_failed_rows_are_visible_in_sync_log_counts_and_notes', function () {
    ($this->upload70)(['data' => [officialKrs70Row(overrides: ['nama' => 'Mismatch'])]])
        ->assertSessionHas('success');
    $log = SyncLog::where('module', 'krs')->latest('id')->firstOrFail();
    expect((int) $log->processed_records)->toBe(0);
    expect((int) $log->skipped_records)->toBe(1);
    expect($log->error_message)->not->toBeNull();
});

test('official_krs70_requires_the_existing_import_permission', function () {
    $this->user->revokePermissionTo('sync_execute');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    ($this->upload70)(['data' => [officialKrs70Row()]])->assertForbidden();
    expect(StudentCourse::count())->toBe(0);
});

test('official_krs70_accepts_whole_ta_names_and_keeps_proposals_and_ta_one_ineligible', function () {
    foreach (['TUGAS AKHIR' => 'eligible', 'TUGAS AKHIR 2' => 'eligible',
        'TUGAS AKHIR 1' => 'unconfirmed', 'PROPOSAL TUGAS AKHIR' => 'unconfirmed'] as $name => $expected) {
        ($this->run70)([officialKrs70Row([officialKrs70Course(['nama_mk' => $name])])]);
        expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe($expected);
    }
});

test('official_krs70_does_not_modify_the_official_academic_profile', function () {
    $profile = StudentAcademicProfile::create(['student_number' => '1202270001',
        'groups' => ['bpp' => ['status' => 'LUNAS', 'period' => null], 'skpi' => ['status' => 'Disetujui']]]);
    $before = $profile->fresh()->groups;
    ($this->run70)([officialKrs70Row()]);
    expect($profile->fresh()->groups)->toBe($before);
});

test('official_krs70_unlinked_legacy_rows_need_period_evidence_even_after_study_semester_changes', function () {
    ($this->run70)([officialKrs70Row()]);
    $old = ($this->enrollment70)();
    $old->update(['academic_period_id' => null]);
    $this->student->update(['active_semester' => 9]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(($this->enrollment70)()->id)->toBe($old->id);
    expect((int) ($this->enrollment70)()->semester)->toBe(8);
    expect(($this->enrollment70)()->academic_period_id)->toBeNull();
});

test('official_krs70_conflicting_legacy_key_does_not_steal_another_periods_enrollment', function () {
    ($this->run70)([officialKrs70Row()]);
    $other = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Conflict 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    ($this->enrollment70)()->update(['academic_period_id' => $other->id]);
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect((int) ($this->enrollment70)()->academic_period_id)->toBe($other->id);
});

test('official_krs70_a_late_write_failure_rolls_back_the_students_entire_row', function () {
    $dispatcher = clone StudentCourse::getEventDispatcher();
    try {
        StudentCourse::saving(function ($row) {
            if ($row->course_code === 'ROLLBACK70') throw new RuntimeException('Simulasi gagal simpan kedua.');
        });
        $result = ($this->run70)([officialKrs70Row([officialKrs70Course(), officialKrs70Course(['kode_mk' => 'ROLLBACK70'])])]);
        expect($result['skipped'])->toBe(1);
        expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(0);
    } finally {
        StudentCourse::setEventDispatcher($dispatcher);
    }
});

test('official_krs70_replaces_the_same_period_and_a_withdrawn_ta_course_stops_qualifying', function () {
    $other = officialKrs70Course(['kode_mk' => 'KEEP70', 'nama_mk' => 'MATA KULIAH LAMA']);
    ($this->run70)([officialKrs70Row([officialKrs70Course(), $other])]);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('eligible');
    $result = ($this->run70)([officialKrs70Row([$other])]);
    expect($result['removed'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->count())->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->where('course_code', 'KEEP70')->exists())->toBeTrue();
    expect(StudentCourse::where('student_number', '1202270001')->where('course_code', 'KRS70TA')->exists())->toBeFalse();
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('unconfirmed');
});

test('official_krs70_changing_lecturer_code_does_not_keep_the_previous_lecturers_name', function () {
    ($this->run70)([officialKrs70Row()]);
    ($this->enrollment70)()->update(['lecturer_name' => 'Dosen Lama Sintetis']);
    ($this->run70)([officialKrs70Row()]);
    expect(($this->enrollment70)()->lecturer_name)->toBe('Dosen Lama Sintetis');
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_dosen' => 'NEW70'])])]);
    expect(($this->enrollment70)()->lecturer_code)->toBe('NEW70');
    expect(($this->enrollment70)()->lecturer_name)->toBeNull();
});

test('official_krs70_ambiguous_unlinked_legacy_rows_are_not_guessed', function () {
    ($this->run70)([officialKrs70Row()]);
    $old = ($this->enrollment70)();
    $old->update(['academic_period_id' => null]);
    $copy = $old->replicate();
    $copy->semester = 7;
    $copy->save();
    expect(($this->run70)([officialKrs70Row()])['skipped'])->toBe(1);
    expect(StudentCourse::where('student_number', '1202270001')->whereNotNull('academic_period_id')->count())->toBe(0);
});

test('official_krs70_migration_rollback_refuses_to_truncate_long_class_names', function () {
    ($this->run70)([officialKrs70Row()]);
    $files = glob(database_path('migrations/*_expand_student_course_class_for_official_krs.php'));
    expect($files)->toHaveCount(1);
    $migration = require $files[0];
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback ditolak');
    expect(($this->enrollment70)()->course_class)->toBe('KELAS-DEMO-PANJANG-12345');
});

test('official_krs70_replacement_preserves_other_students_and_historical_periods', function () {
    ($this->run70)([officialKrs70Row()]);
    $history = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 1, 'label' => 'History 70',
        'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    $other = Student::create(['student_number' => '1202270002', 'name' => 'Mahasiswa Kedua KRS 70',
        'email' => 'other-krs70@example.test', 'academic_year' => 2022, 'active_semester' => 8,
        'study_program' => 'Informatika', 'status' => 'Aktif']);
    foreach ([['1202270001', $history->id, '2025/2026', 6], [$other->student_number, $this->period->id, '2026/2027', 8]] as [$nim, $period, $year, $semester]) {
        StudentCourse::create(['student_number' => $nim, 'course_code' => 'HISTORY70', 'course_name' => 'RIWAYAT',
            'course_class' => 'H70', 'academic_year' => $year, 'semester' => $semester, 'academic_period_id' => $period]);
    }
    ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_mk' => 'NEW70', 'nama_mk' => 'MATA KULIAH BARU'])])]);
    expect(StudentCourse::where('course_code', 'HISTORY70')->count())->toBe(2);
    expect(StudentCourse::where('student_number', '1202270001')->where('academic_period_id', $this->period->id)->pluck('course_code')->all())->toBe(['NEW70']);
});

test('official_krs70_invalid_replacement_preserves_the_previous_valid_ta_enrollment', function () {
    ($this->run70)([officialKrs70Row()]);
    $id = ($this->enrollment70)()->id;
    $result = ($this->run70)([officialKrs70Row([officialKrs70Course(['kode_mk' => 'NEW70', 'semester' => '8'])])]);
    expect($result['skipped'])->toBe(1);
    expect($result['removed'])->toBe(0);
    expect(($this->enrollment70)()->id)->toBe($id);
    expect(app(SidangTaAcademicSnapshot::class)->ta2KrsStatus($this->student))->toBe('eligible');
});

test('official_krs70_unlinked_odd_term_history_is_not_adopted_by_an_even_term_retake', function () {
    ($this->run70)([officialKrs70Row()]);
    ($this->enrollment70)()->update(['academic_period_id' => null, 'semester' => 7]);
    $even = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Retake 70',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    $result = ($this->run70)([officialKrs70Row([officialKrs70Course(['semester' => '2'])])]);
    expect($result['skipped'])->toBe(1);
    expect((int) ($this->enrollment70)()->semester)->toBe(7);
    expect(($this->enrollment70)()->academic_period_id)->toBeNull();
    expect(StudentCourse::where('academic_period_id', $even->id)->count())->toBe(0);
});

test('official_krs70_failure_during_second_period_pruning_restores_all_deletions_and_updates', function () {
    ($this->run70)([officialKrs70Row()]);
    $originalClass = ($this->enrollment70)()->course_class;
    $drop = ($this->enrollment70)()->replicate();
    $drop->course_code = 'DROP70';
    $drop->save();
    $history = AcademicPeriod::create(['year' => '2025/2026', 'semester' => 1, 'label' => 'Rollback history 70',
        'start_date' => '2025-08-01', 'end_date' => '2026-01-31', 'is_active' => false]);
    StudentCourse::create(['student_number' => '1202270001', 'course_code' => 'HISTORY70', 'course_name' => 'RIWAYAT',
        'course_class' => 'H70', 'academic_year' => '2025/2026', 'semester' => 6, 'academic_period_id' => $history->id]);
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    $deletes = 0;
    try {
        $connection->listen(function ($query) use (&$deletes) {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'delete')
                && str_contains($query->sql, 'student_courses') && ++$deletes === 2) {
                throw new RuntimeException('Simulasi gagal setelah pemangkasan periode kedua.');
            }
        });
        $result = ($this->run70)([officialKrs70Row([
            officialKrs70Course(['kelas' => 'CHANGED70']),
            officialKrs70Course(['kode_mk' => 'NEW70B', 'tahun_ajaran' => '2526']),
        ])]);
        expect($deletes)->toBe(2);
        expect($result['skipped'])->toBe(1);
        expect($result['removed'])->toBe(0);
        expect(($this->enrollment70)()->course_class)->toBe($originalClass);
        expect(StudentCourse::where('student_number', '1202270001')->orderBy('course_code')->pluck('course_code')->all())
            ->toBe(['DROP70', 'HISTORY70', 'KRS70TA']);
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
});
```

- [ ] Jalankan sebelum menerapkan isi migration/service/controller. Expected gagal karena importer resmi belum terhubung:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/OfficialKrsJsonImportTest.php --stop-on-failure
```

RefreshDatabase di repo mengisolasi test Feature pada dadu_testing. Tetap gunakan environment MySQL di atas, bukan database aplikasi/dadu Docker atau SQLite default phpunit.xml.

**Koreksi baseline JSON setelah hasil test pengguna:** pada test `official_krs70_does_not_modify_the_official_academic_profile`, nilai sebelum impor harus diambil dengan `$profile->fresh()->groups`. Sesaat setelah create, instance PHP masih dapat mempunyai urutan key input, sedangkan MySQL menormalisasi urutan key JSON yang tersimpan. Diff pengguna hanya memperlihatkan `period` berpindah posisi terhadap `status`, tanpa perubahan nilai. Membaca ulang baseline membuat perbandingan berlangsung antara dua hasil baca database. Assertion `toBe` tetap dipertahankan agar perubahan nilai/tipe masih terdeteksi. Referensi: [normalisasi JSON MySQL](https://dev.mysql.com/doc/refman/8.4/en/json.html#json-normalization), diperiksa melalui Context7.

## 5. Tahap C — migration dan service

- [ ] Isi seluruh migration baru `*_expand_student_course_class_for_official_krs.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->string('course_class', 255)->change();
        });
    }

    public function down(): void
    {
        $length = DB::getDriverName() === 'sqlite' ? 'LENGTH(course_class)' : 'CHAR_LENGTH(course_class)';
        if (DB::table('student_courses')->whereRaw($length.' > 20')->exists()) {
            throw new RuntimeException('Rollback ditolak: ada nama kelas melebihi 20 karakter.');
        }
        Schema::table('student_courses', function (Blueprint $table) {
            $table->string('course_class', 20)->change();
        });
    }
};
```

Guard down menolak pengecilan jika ada nama kelas melebihi20 karakter. Pengujian guard tidak menjalankan DDL down; exception terjadi sebelum kolom diubah, sehingga tidak merusak transaksi RefreshDatabase MySQL.

- [ ] Isi seluruh `app/Services/OfficialKrsJsonImportService.php`:

```php
<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Support\OfficialKrsJsonNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class OfficialKrsJsonImportService
{
    public function import(array $rows): array
    {
        $normalizer = new OfficialKrsJsonNormalizer;
        // Apply batch-level duplicate checks to direct callers too.
        $rows = $normalizer->rows($rows);
        $summary = ['processed' => 0, 'skipped' => 0, 'mismatched' => 0, 'courses' => 0, 'removed' => 0, 'errors' => []];
        $periods = AcademicPeriod::query()->get()->keyBy(fn ($p) => $p->year.'|'.$p->semester);
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row)) throw new InvalidArgumentException('Baris mahasiswa harus berupa objek.');
                $input = $normalizer->normalize($row);
                if ($input['courses'] === []) {
                    throw new InvalidArgumentException('Daftar KRS kosong belum mempunyai konteks periode; data lama dipertahankan.');
                }
                $outcome = DB::transaction(function () use ($input, $periods) {
                    $student = Student::where('student_number', $input['student_number'])->lockForUpdate()->first();
                    if (!$student) return ['kind' => 'skipped', 'count' => 0, 'removed' => 0, 'note' => 'Mahasiswa belum terdaftar.'];
                    if (mb_strtolower(trim($student->name)) !== mb_strtolower($input['name'])) {
                        return ['kind' => 'mismatched', 'count' => 0, 'removed' => 0, 'note' => 'NIM dan nama tidak cocok.'];
                    }
                    $studySemester = filter_var($student->active_semester, FILTER_VALIDATE_INT);
                    if ($studySemester === false || $studySemester < 1 || $studySemester > 127) {
                        throw new InvalidArgumentException('Semester studi mahasiswa belum valid; term KRS tidak dipakai sebagai pengganti.');
                    }
                    // Resolve every source period before writing the student's enrollments.
                    $codesByPeriod = [];
                    foreach ($input['courses'] as $course) {
                        if (!$periods->has($course['academic_year'].'|'.$course['term'])) {
                            throw new InvalidArgumentException('Periode KRS belum terdaftar; buat/periksa periode lalu impor ulang.');
                        }
                        $period = $periods->get($course['academic_year'].'|'.$course['term']);
                        $codesByPeriod[$period->id][] = $course['course_code'];
                    }
                    foreach ($input['courses'] as $course) {
                        $period = $periods->get($course['academic_year'].'|'.$course['term']);
                        $existing = StudentCourse::where('student_number', $input['student_number'])
                            ->where('course_code', $course['course_code'])
                            ->where('academic_period_id', $period->id)->limit(2)->get();
                        if ($existing->count() > 1) {
                            throw new InvalidArgumentException('Ada beberapa KRS lama untuk mata kuliah/periode yang sama; periksa dahulu.');
                        }
                        $enrollment = $existing->first();
                        if (!$enrollment) {
                            $hasUnlinked = StudentCourse::where('student_number', $input['student_number'])
                                ->where('course_code', $course['course_code'])->where('academic_year', $course['academic_year'])
                                ->whereNull('academic_period_id')->exists();
                            if ($hasUnlinked) {
                                throw new InvalidArgumentException('KRS lama belum tertaut periode; pastikan periode riwayat dahulu tanpa menebak term.');
                            }
                            $enrollment = StudentCourse::where('student_number', $input['student_number'])
                                ->where('course_code', $course['course_code'])->where('academic_year', $course['academic_year'])
                                ->where('semester', $studySemester)->first();
                        }
                        if ($enrollment && $enrollment->academic_period_id !== null
                            && (int) $enrollment->academic_period_id !== (int) $period->id) {
                            throw new InvalidArgumentException('Kunci KRS lama dipakai periode lain; periksa semester studi tanpa menimpa riwayat.');
                        }
                        $enrollment ??= new StudentCourse;
                        $master = Course::where('course_code', $course['course_code'])
                            ->where('academic_year', $course['academic_year'])
                            ->where('semester_term', $course['term'] === 1 ? 'GANJIL' : 'GENAP')->first();
                        $enrollment->fill([
                            'student_number' => $input['student_number'], 'course_code' => $course['course_code'],
                            'course_name' => $course['course_name'], 'course_class' => $course['course_class'],
                            'lecturer_code' => $course['lecturer_code'], 'study_program' => $course['study_program'],
                            'lecturer_name' => !$enrollment->exists || $enrollment->lecturer_code !== $course['lecturer_code']
                                ? null : $enrollment->lecturer_name,
                            'academic_year' => $course['academic_year'], 'academic_period_id' => $period->id,
                            'semester' => $enrollment->exists ? $enrollment->semester : $studySemester,
                            'credits' => $enrollment->exists ? $enrollment->credits : $master?->credits,
                        ])->save();
                    }
                    $removed = 0;
                    foreach ($codesByPeriod as $periodId => $codes) {
                        $removed += StudentCourse::where('student_number', $input['student_number'])
                            ->where('academic_period_id', $periodId)->whereNotIn('course_code', $codes)->delete();
                    }
                    return ['kind' => 'processed', 'count' => count($input['courses']), 'removed' => $removed, 'note' => null];
                }, 3);
            } catch (InvalidArgumentException $exception) {
                $summary['skipped']++;
                $summary['errors'][] = 'Baris #'.$index.': '.$exception->getMessage();
                continue;
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = 'Baris #'.$index.': penyimpanan KRS gagal; perubahan mahasiswa dibatalkan.';
                Log::warning('Official KRS row rolled back', ['row' => $index, 'exception' => $exception::class]);
                continue;
            }
            $summary[$outcome['kind']]++;
            $summary['courses'] += $outcome['count'];
            $summary['removed'] += $outcome['removed'];
            if ($outcome['note'] !== null) $summary['errors'][] = 'Baris #'.$index.': '.$outcome['note'];
            if ($outcome['kind'] === 'processed') {
                try { Cache::forget('campus:'.$input['student_number']); }
                catch (Throwable $exception) {
                    $summary['errors'][] = 'Baris #'.$index.': KRS tersimpan, penyegaran cache belum berhasil.';
                    Log::warning('Official KRS cache refresh failed', ['row' => $index, 'exception' => $exception::class]);
                }
            }
        }
        return $summary;
    }
}
```

`processed`/`skipped` menghitung mahasiswa; `courses` menghitung item mata kuliah yang disimpan dan `removed` menghitung KRS lama yang dipangkas. Semua perubahan mahasiswa berada pada satu transaksi; cache dibersihkan sesudah commit. Kegagalan cache dicatat tanpa menyatakan data yang sudah tersimpan dibatalkan.

## 6. Tahap D — hubungkan controller tanpa mengganti importer lama

- [ ] Di `app/Http/Controllers/SyncController.php`, pada method `importJson`, cari blok setelah `json_decode` dan pemeriksaan `json_last_error`, sebelum komentar `Handle case where root JSON contains the DataTables "aaData" key`. Tambahkan:

```php
        if ($request->type === 'krs' && \App\Support\OfficialKrsJsonNormalizer::supports($data)) {
            return $this->importOfficialKrs($data);
        }
```

Posisinya harus sebelum `$data = $data['data']`; wrapper `selected_menus` dan `total` masih diperlukan untuk validasi. Cabang academic_profile yang sudah ada tetap dipertahankan.

- [ ] Tambahkan method berikut pada class SyncController, tepat sebelum `private function importStudentRow(array $row): bool`. Method ini menggunakan import SyncLog/Log yang sudah ada di file; tidak perlu menambahkan ulang import yang sama.

```php
    private function importOfficialKrs(array $payload)
    {
        try {
            $rows = (new \App\Support\OfficialKrsJsonNormalizer)->rows($payload);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['json_file' => $exception->getMessage()]);
        }
        $log = SyncLog::create([
            'module' => 'krs', 'status' => 'processing', 'total_records' => count($rows),
            'triggered_by' => auth()->user()->email, 'started_at' => now(),
        ]);
        try {
            $result = app(\App\Services\OfficialKrsJsonImportService::class)->import($rows);
            $skipped = $result['skipped'] + $result['mismatched'];
            $log->update([
                'status' => 'completed', 'processed_records' => $result['processed'],
                'skipped_records' => $skipped,
                'error_message' => $result['errors'] === [] ? null : implode(' | ', array_slice($result['errors'], 0, 10)),
                'completed_at' => now(),
            ]);
            $message = "Import KRS resmi selesai: {$result['processed']} mahasiswa diproses, "
                ."{$result['courses']} entri mata kuliah disimpan, {$result['skipped']} dilewati, "
                ."{$result['mismatched']} NIM-nama mismatch, {$result['removed']} KRS lama pada periode terkait dihapus.";
            if ($result['errors'] !== []) $message .= ' Ada catatan pada log sinkronisasi.';
            return redirect()->route('sync.index')->with('success', $message);
        } catch (\Throwable $exception) {
            Log::warning('Official KRS import failed', ['exception' => $exception::class]);
            $log->update(['status' => 'failed', 'error_message' => 'Import KRS resmi gagal.', 'completed_at' => now()]);
            return back()->with('error', 'Import KRS resmi gagal. Periksa log aplikasi.');
        }
    }
```

Method `importKrsRow`, `resolveKrsAcademicPeriodId`, serta cabang student/course/attendance tetap dipertahankan. Route/permission/UI impor menggunakan tipe KRS yang sudah ada. Branch resmi tidak menjalankan sinkronisasi master Course global karena sumber KRS ini tidak menyediakan data master lengkap.

## 7. Tahap E — lint dan regresi MySQL

- [ ] Jalankan lint. Untuk migration, ganti NAMA_FILE_MIGRATION dengan timestamp sebenarnya yang dibuat Artisan:

```bash
php -l app/Support/OfficialKrsJsonNormalizer.php
php -l app/Services/OfficialKrsJsonImportService.php
php -l app/Http/Controllers/SyncController.php
php -l tests/Unit/OfficialKrsJsonNormalizerTest.php
php -l tests/Feature/OfficialKrsJsonImportTest.php
php -l database/migrations/NAMA_FILE_MIGRATION.php
```

- [ ] Jalankan kedua test baru:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/OfficialKrsJsonNormalizerTest.php tests/Feature/OfficialKrsJsonImportTest.php
```

Target51 test baru passed:17 unit+34 feature. Jika ada failure, simpan error pertama; jangan mengubah assertion untuk meloloskan kode yang masih mencampur term/semester studi atau memangkas data di luar periode.

- [ ] Jalankan regresi TA beserta importer profil yang tersambung:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Feature/AcademicProfileJsonImportTest.php tests/Feature/OfficialKrsJsonImportTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/OfficialKrsJsonNormalizerTest.php
```

Seluruh test pada lingkup ini harus passed; total mengikuti file yang benar-benar tersedia. Tidak mengubah test non-TA/Auth61f atau memaksakan jumlah full suite di luar cakupan. Blade/JS tidak berubah pada checkpoint ini, sehingga tidak ada build Vite yang perlu dilakukan untuk uji lokal kode PHP ini.

## 8. Tahap F — migration lokal dan uji browser dengan dua akun demo

- [ ] Setelah test lulus, dari repo development asli jalankan migration pada database aplikasi lokal yang memang ingin dipakai:

```bash
php artisan migrate
```

Tidak memakai migrate:fresh/seed. Ini tahap pengguna, bukan operasi yang dijalankan asisten. Test tidak otomatis menerapkan schema pada database aplikasi lokal atau Docker.

- [ ] Pastikan Student Krisna/Dummy002 sudah ada dengan NIM/nama sesuai67-01 dan semester studi8. Pastikan periode2026/2027 Ganjil sudah terdaftar dan menjadi satu-satunya periode aktif untuk uji kelayakan TA. Periode Sidang TA tetap pengaturan terpisah.
- [ ] Login superadmin → IT Panel → Sinkronisasi Data → tipe **KRS** → pilih [69-01-krs-format-resmi-akun-demo.json](fixtures/sidang-ta/69-01-krs-format-resmi-akun-demo.json). Expected2 mahasiswa diproses,2 entri mata kuliah disimpan,0 skipped/mismatch. Jangan pilih Profil Akademik untuk file ini.
- [ ] Buka ulang form TA Krisna dan Dummy002. KRS keduanya Terdaftar. Impor ulang file yang sama tidak menggandakan enrollment.
- [ ] Untuk mencoba pembatalan TA, buat salinan fixture di `docs/fixtures/sidang-ta/local/`, lalu ganti kode/nama mata kuliah Krisna menjadi mata kuliah sintetis biasa pada periode yang sama. Jumlah tetap sesuai isi. Impor sebagai KRS: KRS TA Krisna menjadi belum terkonfirmasi, Dummy002 tetap Terdaftar. Pulihkan dengan mengimpor69-01 lagi. Jangan memakai data nyata untuk percobaan penghapusan.
- [ ] Jika ada log riwayat KRS tanpa periode atau bentrok semester, simpan hasil pemeriksaan sebelum memperbaiki datanya; importer sengaja tidak menebak/menghapus riwayat yang belum jelas. Untuk snapshot yang sudah final, verifikasi detailnya tetap historis.

Berkas sumber asli kini dapat dibaca oleh normalizer, tetapi impor4.277 mahasiswa pada MySQL belum dijalankan/diukur asisten. Mulai uji dari dua akun sintetis. Kelengkapan Student/periode, hasil pencocokan nama, serta waktu proses database tetap menentukan hasil impor nyata.

## 9. Tahap G — salin enam file setelah test/manual lulus

- [ ] Jalankan pada laptop:

```bash
bash <<'BASH'
set -euo pipefail
shopt -s nullglob
task_source=/home/xdranel/Code/Project/php-laravel/dadu
task_target=/home/xdranel/Code/Project/secret/dadu-test
task_migrations=("$task_source"/database/migrations/*_expand_student_course_class_for_official_krs.php)
if [ "${#task_migrations[@]}" -ne 1 ]; then
    echo 'Harus ada tepat satu migration expand_student_course_class_for_official_krs.' >&2
    exit 1
fi
task_migration_relative=${task_migrations[0]#"$task_source"/}
task_files=(
  app/Support/OfficialKrsJsonNormalizer.php
  app/Services/OfficialKrsJsonImportService.php
  app/Http/Controllers/SyncController.php
  tests/Unit/OfficialKrsJsonNormalizerTest.php
  tests/Feature/OfficialKrsJsonImportTest.php
  "$task_migration_relative"
)
for task_file in "${task_files[@]}"; do test -f "$task_source/$task_file"; done
for task_file in "${task_files[@]}"; do
    mkdir -p "$(dirname "$task_target/$task_file")"
    cp -- "$task_source/$task_file" "$task_target/$task_file"
    cmp -- "$task_source/$task_file" "$task_target/$task_file"
done
BASH
```

Laporkan hasil test/manual dan salinan untuk rescan. Setelah source disalin dan siap dipasang, update Docker memakai64 bagian12, termasuk migration dan pemeriksaan kode host/container; jangan hanya restart atau artisan up. Belum melakukan deploy/commit/push otomatis pada checkpoint ini.

## 10. Verifikasi panduan oleh asisten

- Snippet PHP normalizer, service, migration, kedua test, dan controller hasil penyisipan dilint dari direktori sementara; tidak dipasang ke source repo.
- 17 unit test normalizer dijalankan dengan Pest/vendor aktual, tanpa bootstrap aplikasi/DB:17 passed,75 assertions. Sebelum snippet class diisi, test gagal karena implementasi belum ada.
- Normalizer aktual membaca ulang4.277 baris/30.590 item berkas asli tanpa error;239 nama kelas panjang dipertahankan. Ini hanya normalisasi di memori, bukan impor database.
- Review independen memeriksa schema, pemetaan riwayat tanpa periode, perubahan kode/nama dosen, dan batas transaksi pemangkasan. Temuan yang memengaruhi snippet sudah ditangani.
- 34 feature test MySQL dan migration up pada database **belum dijalankan asisten**. Pengguna menjalankannya sesuai tahap E; tidak mengklaim seluruh51 test atau fitur runtime sudah lulus.
- Source/deployment kedua repo dan isi KRS asli diperiksa dengan hash sebelum/sesudah. Perubahan hanya Markdown70 dan tautan lanjutan dokumentasi; tidak ada akun, database, JSON nyata, source aplikasi, atau file Docker yang diubah.

Referensi yang diperiksa melalui Context7: [transaksi dan deadlock retry](https://laravel.com/docs/13.x/database#database-transactions), [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking), dan [HTTP testing](https://laravel.com/docs/13.x/http-tests). Struktur dan constraint schema juga diperiksa langsung pada migration/model lokal.

## 11. Hasil setelah pengguna menerapkan dan menyalin kode

Pengguna melaporkan semua test passed setelah koreksi baseline JSON dan enam file sudah disalin. [Rescan71](71-rescan-krs-resmi-dan-status-bpp.md) mengonfirmasi enam file identik, seluruh515 file source yang dibandingkan identik, dan64 unit aktual passed. Uji database pengguna, verifikasi browser, dan penerapan migration/deployment dibedakan dalam laporan tersebut. Periode BPP belum menjadi syarat pemblokir TA.
