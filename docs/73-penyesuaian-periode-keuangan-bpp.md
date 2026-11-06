# 73 — Pembacaan periode BPP resmi pada impor profil akademik

> Terapkan tiga perubahan di repo development asli, jalankan test, lalu salin ke dadu-test untuk rescan. Asisten hanya menulis Markdown; source aplikasi kedua repo tidak diubah. Checkpoint ini mendahulukan penyesuaian pembacaan/penyimpanan periode BPP sebelum pekerjaan lain yang akan diminta pengguna.

**Goal:** membaca `periode_keuangan` dari ekspor resmi dan menyimpan status BPP pada periode pembayaran yang benar, tanpa mengubah syarat pengiriman TA.

**Architecture:** ganti satu method pada normalizer yang sudah ada. Controller tetap meneruskan konteks wrapper bila tersedia; importer yang sudah ada menyimpan hasil pada `groups.bpp` dan `bpp_periods`. Tidak membutuhkan service atau tabel baru.

**Tech Stack:** PHP proyek `^8.4.27`, Laravel13, Pest4, MySQL Podman untuk test lokal. Tidak menambahkan dependency, migration, atau perubahan environment/phpunit.xml.

**Spec:** [72 — kontrak finance update](72-periode-bpp-resmi-dan-json-demo.md), [71 — BPP bukan blocker TA](71-rescan-krs-resmi-dan-status-bpp.md). Pengguna mengizinkan penyesuaian BPP didahulukan. Format2627/1 sudah dikonfirmasi sebagai2026/2027 Ganjil.

## 1. Keputusan implementasi

Memetakan field di normalizer merupakan perubahan paling kecil karena penyimpanan per periode sudah tersedia dan sudah diuji pada66. Memaksa pengguna menambahkan finance_period pada wrapper tidak mencerminkan ekspor asli; mengubah controller untuk menetapkan satu periode global juga akan salah bila periode berbeda per mahasiswa.

- `periode_keuangan` valid pada setiap baris menjadi periode pembayaran. `semester` tetap konteks semester studi, misalnya7/13.
- Jika wrapper `finance_period` atau field semester lama juga memuat kode periode yang sah, semua sumber periode harus sama. Konflik melewati grup BPP dan mempertahankan data valid sebelumnya.
- `periode_keuangan` yang **ada tetapi null/kosong/malformed** tidak diabaikan atau diganti diam-diam dengan konteks lain; grup BPP dilewati dengan catatan.
- Jika field resmi tidak ada pada ekspor lama, kompatibilitas wrapper/periode pada semester tetap berjalan. Tanpa periode eksplisit, hasilnya null; tidak menebak dari waktu sekarang atau periode KRS aktif.
- KREDIT→LUNAS pada periode sama memperbarui satu entri; periode lain dipertahankan oleh importer yang sudah tersedia.
- Menu keuangan saja mempertahankan grup akademik/SKPI/TAK/bahasa yang sudah diimpor, serta tidak mengubah KRS atau semester studi Student.
- BPP tetap bukan blocker TA. Bukti dan tanggal pembayaran wisuda tetap merupakan persyaratan terpisah.

### Batas checkpoint

Penyesuaian ini memastikan data periode tersimpan dengan benar. **Panel/card/snapshot display belum diselaraskan pada checkpoint ini:** beberapa pembaca tampilan masih memakai tabel status lama dan teks periode belum terkonfirmasi. Jangan menganggap seluruh tampilan BPP selesai hanya dari test normalizer/importer. Tahap pembaca tampilan dapat dibangun di atas data yang sudah benar setelah checkpoint ini lulus/rescan; tidak dicampur dengan perubahan aturan kelayakan.

Untuk uji akun demo, gunakan profil lengkap67-03 dahulu sebelum file keuangan72. Bila keuangan adalah impor resmi pertama, profil dapat berisi grup BPP saja; syarat akademik yang belum pernah diimpor tetap unknown sesuai kontrak sebelumnya. Ini tidak menjadikan status BPP sebagai syarat tambahan.

## 2. Tiga file yang diperbarui pengguna

| File | Perubahan |
| --- | --- |
| `app/Support/AcademicProfileJsonNormalizer.php` | Ganti method bpp saja |
| `tests/Unit/AcademicProfileJsonNormalizerTest.php` | Tambah8 test;25 test lama tetap dipertahankan |
| `tests/Feature/AcademicProfileJsonImportTest.php` | Tambah helper sintetis dan9 test;29 test lama tetap dipertahankan |

Tidak perlu make:class/make:test karena ketiga file sudah ada. Jangan mengganti seluruh test lama dengan snippet tambahan di bawah.

### Fokus review

- [x] Sumber periode ganda yang bertentangan tidak menimpa BPP lama.
- [x] Input null/malformed yang eksplisit tidak berubah menjadi periode tebakan.
- [x] Impor ulang mempertahankan riwayat periode lain dan tidak membuat profil ganda.
- [x] Keuangan saja tidak mengubah grup di luar menu yang dipilih atau semester studi Student.
- [x] KREDIT/LUNAS tetap tidak mengubah kelayakan akademik/bahasa yang sebelumnya lengkap.

Checklist menunjukkan cakupan snippet test, bukan klaim bahwa test MySQL sudah dijalankan asisten.

## 3. Tahap A — tambahkan test dahulu

Dari repo development asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
```

- [ ] Tambahkan8 test berikut di akhir `tests/Unit/AcademicProfileJsonNormalizerTest.php`. File sudah mempunyai `use App\Support\AcademicProfileJsonNormalizer as Normalizer;`, sehingga tidak perlu mengulang import atau menambahkan pembuka PHP lagi.

```php
test('json73_bpp_reads_official_period_separately_from_the_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'KREDIT',
        'periode_keuangan' => '2627/1', 'semester' => '13'], ['keuangan'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/1');
    expect($bpp['source_semester'])->toBe('13');
    expect($bpp['status'])->toBe('KREDIT');
});

test('json73_bpp_reads_and_trims_official_even_term_without_a_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
        'periode_keuangan' => ' 2627/2 '], ['keuangan'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/2');
    expect($bpp['source_semester'])->toBeNull();
});

test('json73_malformed_explicit_period_skips_bpp_without_losing_successful_skpi', function () {
    foreach ([null, '', ' ', '7', '2026/2027', '2627/3', '2627/0', 26271, true, []] as $period) {
        $result = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
            'periode_keuangan' => $period, 'status_skpi' => 'Disetujui']);
        expect($result['updates'])->not->toHaveKey('bpp');
        expect($result['skipped'])->toHaveKey('bpp');
        expect($result['updates']['skpi']['approved'])->toBeTrue();
    }
});

test('json73_official_period_must_agree_with_confirmed_wrapper_context', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'semester' => '7'];
    expect((new Normalizer)->normalize($row, ['keuangan'], '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
    $result = (new Normalizer)->normalize($row, ['keuangan'], '2627/2');
    expect($result['updates'])->not->toHaveKey('bpp');
    expect($result['skipped'])->toHaveKey('bpp');
});

test('json73_official_period_cannot_conflict_with_a_legacy_semester_period', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'semester' => '2627/2'];
    expect((new Normalizer)->normalize($row)['skipped'])->toHaveKey('bpp');
    $row['semester'] = '2627/1';
    expect((new Normalizer)->normalize($row, null, '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
});

test('json73_keuangan_not_selected_or_source_error_preserves_the_bpp_group', function () {
    $row = ['status_keuangan' => 'LUNAS', 'periode_keuangan' => '2627/1', 'status_skpi' => 'Disetujui'];
    expect((new Normalizer)->normalize($row, ['skpi'])['updates'])->not->toHaveKey('bpp');
    $row['error'] = 'keuangan: gateway gagal';
    $result = (new Normalizer)->normalize($row);
    expect($result['skipped'])->toHaveKey('bpp');
    expect($result['updates']['skpi']['approved'])->toBeTrue();
});

test('json73_rows_can_have_different_confirmed_periods_without_shared_state', function () {
    $normalizer = new Normalizer;
    foreach (['2627/1', '2627/2', '2728/1'] as $period) {
        $result = $normalizer->normalize(['status_keuangan' => 'LUNAS', 'periode_keuangan' => $period]);
        expect($result['updates']['bpp']['period'])->toBe($period);
    }
});

test('json73_old_exports_remain_supported_without_inventing_a_finance_period', function () {
    $normalizer = new Normalizer;
    $row = ['status_keuangan' => 'LUNAS', 'semester' => 7];
    expect($normalizer->normalize($row)['updates']['bpp']['period'])->toBeNull();
    expect($normalizer->normalize($row, null, '2627/1')['updates']['bpp']['period'])->toBe('2627/1');
    $row['semester'] = '2627/2';
    expect($normalizer->normalize($row)['updates']['bpp']['period'])->toBe('2627/2');
});
```

- [ ] Tambahkan helper dan9 test berikut di akhir `tests/Feature/AcademicProfileJsonImportTest.php`, tanpa pembuka PHP tambahan. Snippet memakai helper/import/beforeEach yang sudah ada pada file66 dan seluruh test lama tetap dipertahankan.

```php
function financeJson73Row(array $overrides = []): array
{
    return array_replace(['no' => 1, 'nim' => '1202266001', 'nama' => 'Mahasiswa Sintetis JSON 66',
        'semester' => '7', 'periode_keuangan' => '2627/1', 'status_keuangan' => 'KREDIT'], $overrides);
}

test('json73_finance_only_source_stores_official_period_and_does_not_overwrite_study_semester', function () {
    $this->student->update(['active_semester' => 8]);
    ($this->import66)(financeJson73Row(['semester' => '13']), ['keuangan']);
    $profile = ($this->profile)();
    expect($profile->groups['bpp']['period'])->toBe('2627/1');
    expect($profile->groups['bpp']['source_semester'])->toBe('13');
    expect($profile->bpp_periods['2627/1']['status'])->toBe('KREDIT');
    expect((int) $this->student->fresh()->active_semester)->toBe(8);
    expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(1);
});

test('json73_credit_to_paid_updates_the_same_confirmed_period_without_duplicate_profile', function () {
    ($this->import66)(financeJson73Row(), ['keuangan']);
    $id = ($this->profile)()->id;
    ($this->import66)(financeJson73Row(['status_keuangan' => 'LUNAS']), ['keuangan']);
    $profile = ($this->profile)();
    expect($profile->id)->toBe($id);
    expect($profile->bpp_periods)->toHaveCount(1);
    expect($profile->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    expect(StudentAcademicProfile::where('student_number', $this->student->student_number)->count())->toBe(1);
    expect(StudentBppPayment::where('student_number', $this->student->student_number)->value('status'))->toBe('LUNAS');
});

test('json73_new_finance_period_preserves_the_previous_periods_history', function () {
    ($this->import66)(financeJson73Row(['status_keuangan' => 'LUNAS']), ['keuangan']);
    ($this->import66)(financeJson73Row(['periode_keuangan' => '2627/2']), ['keuangan']);
    $profile = ($this->profile)();
    expect($profile->bpp_periods)->toHaveCount(2);
    expect($profile->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    expect($profile->bpp_periods['2627/2']['status'])->toBe('KREDIT');
    expect($profile->groups['bpp']['period'])->toBe('2627/2');
});

test('json73_finance_only_update_preserves_all_previously_imported_non_finance_groups', function () {
    ($this->import66)(academicJson66Row(), null, '2627/1');
    $before = ($this->profile)()->groups;
    $studentBefore = $this->student->fresh()->only(['gpa', 'total_credits', 'active_semester']);
    ($this->import66)(financeJson73Row(['semester' => '13', 'status_skpi' => null,
        'nilai_bahasa' => null, 'total_tak' => 0]), ['keuangan']);
    $after = ($this->profile)()->groups;
    expect(array_diff_key($after, ['bpp' => true]))->toBe(array_diff_key($before, ['bpp' => true]));
    expect($this->student->fresh()->only(['gpa', 'total_credits', 'active_semester']))->toBe($studentBefore);
    expect($after['bpp']['status'])->toBe('KREDIT');
});

test('json73_invalid_official_period_preserves_bpp_while_other_selected_groups_can_update', function () {
    ($this->import66)(academicJson66Row(), null, '2627/1');
    $before = ($this->profile)();
    $bppBefore = $before->groups['bpp'];
    $periodsBefore = $before->bpp_periods;
    ($this->import66)(financeJson73Row(['periode_keuangan' => '7', 'status_skpi' => 'Belum Disetujui']), ['keuangan', 'skpi']);
    $after = ($this->profile)();
    expect($after->groups['bpp'])->toBe($bppBefore);
    expect($after->bpp_periods)->toBe($periodsBefore);
    expect($after->groups['skpi']['status'])->toBe('Belum Disetujui');
    expect(StudentBppPayment::where('student_number', $this->student->student_number)->value('status'))->toBe('LUNAS');
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('Periode BPP');
});

test('json73_conflicting_wrapper_period_does_not_replace_confirmed_bpp_data', function () {
    ($this->import66)(financeJson73Row(['status_keuangan' => 'LUNAS']), ['keuangan']);
    $before = ($this->profile)();
    $groupsBefore = $before->groups;
    $periodsBefore = $before->bpp_periods;
    ($this->import66)(financeJson73Row(['periode_keuangan' => '2627/2']), ['keuangan'], '2627/1');
    expect(($this->profile)()->groups)->toBe($groupsBefore);
    expect(($this->profile)()->bpp_periods)->toBe($periodsBefore);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->not->toBeNull();
});

test('json73_finance_source_error_preserves_the_last_confirmed_payment', function () {
    ($this->import66)(financeJson73Row(['status_keuangan' => 'LUNAS']), ['keuangan']);
    $before = ($this->profile)()->bpp_periods;
    ($this->import66)(financeJson73Row(['error' => 'keuangan: gagal mengambil data']), ['keuangan']);
    expect(($this->profile)()->bpp_periods)->toBe($before);
    expect(StudentBppPayment::where('student_number', $this->student->student_number)->value('status'))->toBe('LUNAS');
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json73_both_profile_upload_routes_read_native_per_row_finance_periods', function () {
    foreach (['sync.import-json', 'sync.import-academic-profile'] as $routeName) {
        ($this->postPayload)(['selected_menus' => ['keuangan'], 'total' => 1,
            'data' => [financeJson73Row()]], $routeName)
            ->assertRedirect(route('sync.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        expect(($this->profile)()->groups['bpp']['period'])->toBe('2627/1');
        expect(($this->profile)()->bpp_periods)->toHaveCount(1);
    }
});

test('json73_finance_status_does_not_change_existing_academic_or_language_eligibility', function () {
    ($this->import66)(academicJson66Row(), null, '2627/1');
    \App\Models\AcademicPeriod::query()->update(['is_active' => false]);
    $period = \App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'BPP 73',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    \App\Models\StudentCourse::create(['student_number' => $this->student->student_number,
        'course_code' => 'BPP73TA', 'course_name' => 'TUGAS AKHIR', 'course_class' => 'B73',
        'academic_year' => '2026/2027', 'semester' => 7, 'academic_period_id' => $period->id]);
    $facts = app(\App\Services\SidangTaAdmissionFacts::class);
    $before = $facts->forStudent($this->student);
    expect($before['academic']['academic_passed'])->toBeTrue();
    foreach (['KREDIT', 'LUNAS'] as $status) {
        ($this->import66)(financeJson73Row(['status_keuangan' => $status]), ['keuangan']);
        $after = $facts->forStudent($this->student);
        expect($after['academic'])->toBe($before['academic']);
        expect($after['eprt'])->toBe($before['eprt']);
        expect($after['krs_status'])->toBe('eligible');
    }
});
```

- [ ] Jalankan unit baru sebelum mengganti method bpp:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/AcademicProfileJsonNormalizerTest.php --filter=json73_bpp_reads_official_period
```

Expected fail: nilai period masih null, seharusnya2627/1. Ini reproduksi masalah yang ditemukan pada277 baris asli, bukan alasan mengubah assertion menjadi menerima null.

## 4. Tahap B — ganti satu method normalizer

- [ ] Pada `app/Support/AcademicProfileJsonNormalizer.php`, ganti **seluruh method** `private function bpp(array $row, ?string $financePeriod): array` dengan kode berikut. Method `text`, `number`, `count`, `failures`, dan `normalize` tetap dipertahankan.

```php
    private function bpp(array $row, ?string $financePeriod): array
    {
        $status = $this->text($row['status_keuangan']);
        $raw = $this->text($row['status_keuangan_asli'] ?? null);
        $semester = $row['semester'] ?? null;
        $semester = $this->text(is_int($semester) ? (string) $semester : $semester);

        $sources = [];
        if (array_key_exists('periode_keuangan', $row)) $sources[] = $row['periode_keuangan'];
        if ($financePeriod !== null) $sources[] = $financePeriod;
        $periods = [];
        foreach ($sources as $source) {
            if (!is_string($source) || preg_match('/^\d{4}\/[12]$/D', trim($source)) !== 1) {
                throw new InvalidArgumentException('Periode BPP harus berbentuk 2627/1 atau 2627/2.');
            }
            $periods[] = trim($source);
        }
        // Compatibility: old exports sometimes put an explicit period in semester.
        // A study semester such as 7 or 13 is never converted into a payment period.
        if ($semester !== null && preg_match('/^\d{4}\/[12]$/D', $semester) === 1) {
            $periods[] = $semester;
        }
        if (count(array_unique($periods)) > 1) {
            throw new InvalidArgumentException($financePeriod !== null
                ? 'Periode BPP pada baris berbeda dengan konteks impor.'
                : 'Sumber periode BPP saling bertentangan; data lama dipertahankan.');
        }
        return [
            'status' => $status === 'PERLU VERIFIKASI' && $raw === 'KREDIT' ? 'KREDIT' : $status,
            'source_status' => $status,
            'raw_status' => $raw,
            'source_semester' => $semester,
            'period' => $periods[0] ?? null,
        ];
    }
```

Penyimpanan sudah dilakukan oleh `AcademicProfileJsonImportService`: hasil bpp masuk ke groups, lalu `bpp_periods[period]` diperbarui bila period tidak null. Controller/service/model tidak perlu diganti untuk checkpoint ini.

**Koreksi kompatibilitas pesan setelah hasil test pengguna:** saat konflik melibatkan wrapper financePeriod, pertahankan pesan lama `Periode BPP pada baris berbeda dengan konteks impor.` karena test regresi66 memeriksa potongan teks tersebut. Pesan umum hanya dipakai bila sumber periode pada baris saling bertentangan tanpa wrapper. Perilaku melewati BPP dan melanjutkan grup valid lain tetap sama; assertion test lama tidak perlu diubah.

## 5. Tahap C — lint dan test MySQL

- [ ] Jalankan lint:

```bash
php -l app/Support/AcademicProfileJsonNormalizer.php
php -l tests/Unit/AcademicProfileJsonNormalizerTest.php
php -l tests/Feature/AcademicProfileJsonImportTest.php
```

- [ ] Jalankan kedua file test yang diperbarui:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Feature/AcademicProfileJsonImportTest.php
```

Target pada baseline yang discan: **33 unit+38 feature =71 passed**. Ini17 test tambahan. Jika ada test lain yang ditambahkan pengguna, total dapat berbeda; seluruh case harus passed. Feature memakai dadu_testing MySQL dengan RefreshDatabase; tidak menjalankan test pada database aplikasi/Docker.

- [ ] Jalankan regresi TA, KRS resmi, dan kedua importer:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Feature/AcademicProfileJsonImportTest.php tests/Feature/OfficialKrsJsonImportTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/OfficialKrsJsonNormalizerTest.php
```

Tidak memaksakan jumlah seluruh regresi bila ada penambahan file. Simpan failure pertama jika ada; jangan mengubah assertion atau melakukan migrate:fresh/seed sebagai langkah perbaikan parser.

## 6. Tahap D — uji impor JSON demo di aplikasi lokal

Tidak ada migration baru pada73. Pastikan schema/checkpoint sebelumnya, termasuk migration KRS70 bila digunakan, sudah diterapkan pada database aplikasi lokal.

- [ ] Pastikan Student dan profil akademik lengkap dua akun demo sudah tersedia sesuai67. Pilih file JSON dari dadu-test melalui browser laptop; tidak harus menyalin fixture ke repo original.
- [ ] Login superadmin → IT Panel → Sinkronisasi Data → tipe **Profil Akademik** → impor [72-01-bpp-periode-akun-demo.json](fixtures/sidang-ta/72-01-bpp-periode-akun-demo.json). Expected2 baris diproses, tanpa skipped/mismatch.
- [ ] Impor [72-02-bpp-lunas-dummy002.json](fixtures/sidang-ta/72-02-bpp-lunas-dummy002.json), tipe yang sama. Expected1 baris diproses. Dummy002 berubah KREDIT→LUNAS pada2627/1; riwayat tidak bertambah menjadi periode baru.
- [ ] Periksa hasil data lewat Tinker lokal, opsional:

```bash
php artisan tinker
```

```php
$profile = App\Models\StudentAcademicProfile::where('student_number', '1202299002')->firstOrFail();
```

```php
dump($profile->only(['groups', 'bpp_periods']));
```

Expected `groups.bpp.period = "2627/1"` dan `bpp_periods["2627/1"].status = "LUNAS"`, dengan groups akademik/bahasa sebelumnya tetap tersedia. Nilai source_semester8 berasal dari fixture, bukan kode periode pembayaran.

```php
exit;
```

Tinker tersedia pada lokal development; runtime Docker tidak membawa dependency dev tersebut. Tampilan periode yang masih memakai teks lama belum menjadi bukti bahwa penyimpanan gagal; baca data/log sesuai batas checkpoint di bagian1.

## 7. Tahap E — salin tiga file setelah test lulus

- [ ] Jalankan dari laptop setelah test/manual:

```bash
bash <<'BASH'
set -euo pipefail
task_source=/home/xdranel/Code/Project/php-laravel/dadu
task_target=/home/xdranel/Code/Project/secret/dadu-test
task_files=(
  app/Support/AcademicProfileJsonNormalizer.php
  tests/Unit/AcademicProfileJsonNormalizerTest.php
  tests/Feature/AcademicProfileJsonImportTest.php
)
for task_file in "${task_files[@]}"; do test -f "$task_source/$task_file"; done
for task_file in "${task_files[@]}"; do
    mkdir -p "$(dirname "$task_target/$task_file")"
    cp -- "$task_source/$task_file" "$task_target/$task_file"
    cmp -- "$task_source/$task_file" "$task_target/$task_file"
done
BASH
```

Laporkan hasil test dan salinan untuk rescan. Setelah kode siap dipasang ke Docker, gunakan64 bagian12 agar image runtime benar-benar memuat normalizer terbaru; tidak perlu seed akun atau reset database. Tidak ada commit/push/deploy otomatis oleh asisten.

## 8. Verifikasi panduan oleh asisten

- Test baru diperiksa terlebih dahulu terhadap normalizer lama: case native period gagal dengan actual null versus expected2627/1.
- Draft normalizer yang disiapkan di direktori sementara menjalankan25 unit lama+8 baru: **33 passed,163 assertions**, tanpa bootstrap aplikasi atau database.
- Pembacaan277 baris finance update memakai draft normalizer menghasilkan277 periode terkonfirmasi2627/1,0 skipped, dan seluruh input tetap tidak berubah. Ini verifikasi normalisasi di memori, bukan impor database.
- Snippet unit/feature digabung dengan file baseline di direktori sementara dan lulus php -l. Tambahan9 feature serta29 feature lama **belum dijalankan asisten pada MySQL**; hasil runtime menunggu pengguna.
- Source/deployment kedua repo dan berkas finance asli diverifikasi dengan hash sebelum/sesudah. Asisten hanya menulis Markdown73 dan tautan lanjutannya, tanpa mengubah source, test, JSON, environment, migration, atau Docker.

Referensi model fresh dan cast JSON diperiksa melalui Context7: [refreshing models](https://laravel.com/docs/13.x/eloquent#refreshing-models), [array and JSON casting](https://laravel.com/docs/13.x/eloquent-mutators#array-and-json-casting). Baseline perbandingan test selalu dibaca dari database agar urutan key JSON input tidak menimbulkan false failure MySQL.

## 9. Hasil setelah pengguna menerapkan dan menyalin kode

Pengguna melaporkan semua test berhasil setelah koreksi pesan log dan tiga file sudah disalin. [Rescan74](74-rescan-penyesuaian-bpp-dan-batas-commit.md) mengonfirmasi tiga file identik, seluruh515 source identik, dan72 unit aktual passed. Pembacaan/penyimpanan periode selesai pada cakupan ini; pembaca tampilan/card/snapshot yang masih lama dicatat terpisah dan tidak diklaim sudah diselaraskan.
