# 66 — Integrasi impor JSON Profil Akademik

> Pengerjaan tetap oleh pengguna di repo development asli, lalu test dan salin ke dadu-test. Asisten hanya mengubah Markdown. Kerjakan tahap 1–6 pada dokumen ini. Tahap 7–8 adalah urutan checkpoint berikutnya dan belum dikerjakan sekarang.

**Goal:** menyimpan hasil ekspor Profil Akademik terbaru dengan jumlah tes resmi, persetujuan sidang, dan status BPP per periode, sambil mempertahankan data yang tidak ikut diambil atau gagal diambil.

**Architecture:** controller meneruskan konteks ekspor ke service importer yang sekarang. Format flat terbaru diarahkan ke service baru yang memakai normalizer 65. Ringkasan dan riwayat bahasa disimpan utuh dalam satu record profil per mahasiswa; data akademik yang sudah mempunyai tabel tetap diperbarui hanya ketika field/menu terkait tersedia. Setiap mahasiswa diproses dalam transaksi dengan lock pada record Student. Refresh modul terkait dilakukan setelah transaksi selesai.

**Tech Stack:** PHP `^8.4.27`, Laravel 13, Pest 4, MySQL Podman lokal. Tidak ada dependency baru atau perubahan `phpunit.xml`/`.env`.

**Spec:** [65 —kontrak JSON](65-kontrak-json-resmi-dan-normalizer-profil-akademik.md), keputusan terbaru pengguna tentang status_skpi/persetujuan_maju_sidang/nilai_bahasa/jumlah_tes_bahasa/KRS/BPP. User melaporkan seluruh unit 65 lulus dan dua file sudah tersalin. Rescan 66 memastikan dua file identik; seluruh 505 file source aplikasi yang dibandingkan identik kedua repo.

## 1. Yang berubah pada checkpoint ini

| File | Tindakan |
| --- | --- |
| `database/migrations/*_create_student_academic_profiles_table.php` | Baru; nama timestamp mengikuti Artisan di laptopmu |
| `app/Models/StudentAcademicProfile.php` | Baru |
| `app/Services/AcademicProfileJsonImportService.php` | Baru |
| `tests/Feature/AcademicProfileJsonImportTest.php` | Baru; 29 test lengkap |
| `app/Support/AcademicProfileJsonNormalizer.php` | Ubah akses satu method menjadi public, tanpa mengubah normalisasi |
| `app/Services/StudentAcademicProfileImportService.php` | Ganti method import saja; arahkan format flat ke service baru |
| `app/Http/Controllers/SyncController.php` | Ganti method importAcademicProfile saja; teruskan metadata ekspor |

**Total 7 file: 4 baru, 3 diperbarui.** Route, permission, Blade, JS, pengajuan/draft final TA dan foto contoh tidak berubah.

Ini integrasi **penyimpanan**, belum seluruh pembacaan syarat TA. Checkpoint berikutnya memperbarui AdmissionFacts/policy, helper KRS, snapshot/form/export, serta fixture lama secara bersama. ECCT null+3 sudah bisa disimpan pada 66, tetapi form lama belum memakai ringkasan ini. Jangan menganggap tombol kirim sudah mengikuti aturan baru setelah 66 saja. Belum perlu deploy server sebelum rangkaian integrasi selesai.

Penyimpanan baru:

- `groups`: grup skpi, sidang_approval, language, tak, bpp, dan fakta academic yang benar-benar ikut pembaruan.
- `groups.language`: score, type, attempts, history. Jumlah langsung dari `jumlah_tes_bahasa`; history diganti utuh per mahasiswa saat menu bahasa berhasil dibaca.
- `bpp_periods`: status BPP dengan kunci periode, misalnya 2627/1. Impor berikutnya pada periode sama memperbarui nilai itu; periode berbeda tetap tersimpan.
- `groups.bpp`: hasil BPP paling akhir dibaca, yang bisa saja belum mempunyai periode. Jangan memakai nilai ini sebagai bukti semester berjalan tanpa memeriksa periodenya.

Bahasa resmi disimpan di profil JSON, bukan dipaksakan ke tabel `student_eprts` yang memakai total_score integer dan dipakai monitoring lain. Impor JSON tidak menghapus baris manual/sumber lain dari tabel tersebut. Penggantian history yang diminta pengguna berlaku pada kumpulan history hasil impor dalam profil mahasiswa terkait. Pembaca data kampus/TA akan memakai ringkasan baru pada checkpoint berikutnya.

## 2. Hasil scan yang diperhitungkan

- Semua 260 baris dari tiga file nyata memakai `semester` studi 5/6/7/9/11/13. Tidak ada periode BPP pada file tersebut. Tanpa konteks resmi, simpan period null; jangan menebak dari semester studi/periode aktif/tanggal ekspor.
- Dukungan konteks impor opsional pada wrapper bernama `finance_period`, misalnya 2627/1. Ini field integrasi yang kita dukung, **bukan klaim bahwa tiga file nyata sudah mempunyai field tersebut**. Jika exporter nanti memakai nama lain, mapping controller disesuaikan. Tidak menambahkan input periode manual atau nilai default diam-diam.
- Jika baris sumber benar-benar memuat `semester=2627/1`, normalizer dapat membacanya. Jika kode baris bertentangan dengan konteks wrapper, hanya BPP yang dilewati dengan catatan.
- Daftar MK mengulang/belum lulus pada file nyata adalah teks beberapa baris. Pecah berdasarkan pergantian baris; koma pada `(D, Semester 3)` tetap menjadi bagian deskripsi. Tanda `-` berarti daftar kosong. Daftar tersebut tidak dipakai sebagai bukti pengambilan KRS.
- Enam tanggal sidang terisi memakai format d/m/Y, seperti 31/07/2026. Service menerima d/m/Y, d-m-Y, dan Y-m-d dengan validasi tanggal.
- Ada 101 error TAK: 100 token kedaluwarsa dan 1 koneksi. Pertahankan TAK lama saat error dan tetap proses menu lain yang berhasil.
- Menu tidak dipilih atau field tidak ada tidak ditulis menjadi null. Null eksplisit pada grup yang berhasil adalah data kosong sah, dibedakan dari angka 0. Bahasa tidak diperbarui jika empat field ringkasannya tidak lengkap/malformed.

Kompatibilitas input nested lama masih diperlukan oleh fixture yang sekarang. Ini kompatibilitas format impor, bukan dua versi persyaratan TA. Seluruh akun tetap memakai alur TA current yang sama. Batch nested lama tanpa metadata memakai jalur lama; batch flat/metadata memakai jalur baru. Jangan mencampur format nested dan flat dalam satu batch. Fixture lama akan diperbarui ketika pembaca fakta diintegrasikan.

## 3. Fokus pemeriksaan

1. Impor ulang/dua menu berbeda untuk mahasiswa sama: lock Student mencegah merge JSON saling menimpa. Field/grup hilang dipertahankan.
2. Bahasa: count resmi 3 dengan history satu baris tetap count 3; nilai ECCT null tetap null. Riwayat terimpor diganti, baris EPRT manual tidak disentuh.
3. BPP: KREDIT→LUNAS pada periode sama, periode berbeda tidak hilang, semester studi tidak menjadi periode pembayaran.
4. Error/data invalid: rollback satu mahasiswa saat SQL/save gagal; kegagalan refresh sesudah commit tidak dihitung sebagai rollback. Counter dipindah setelah transaksi berhasil.
5. Identitas/akses: NIM+nama tetap harus cocok, mahasiswa belum ada dilewati, permission sync_execute tetap pada kedua route; mahasiswa lain tidak ikut berubah.

## 4. Tahap 1 —buat file dan isi test dahulu

Pada laptop development:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:model StudentAcademicProfile
php artisan make:class Services/AcademicProfileJsonImportService
php artisan make:migration create_student_academic_profiles_table --create=student_academic_profiles
php artisan make:test AcademicProfileJsonImportTest --pest
```

Jangan menjalankan `make:migration` dua kali. Catat nama timestamp file yang dibuat. Belum menjalankan migrate pada DB development sampai isinya lengkap pada tahap 2.

Isi **seluruh** `tests/Feature/AcademicProfileJsonImportTest.php`:

```php
<?php

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentBasilaApproval;
use App\Models\StudentBppPayment;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentFinalProject;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\GraduationDataSyncService;
use App\Services\StudentAcademicProfileImportService;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function academicJson66Row(array $overrides = []): array
{
    return array_replace([
        'nim' => '1202266001', 'nama' => 'Mahasiswa Sintetis JSON 66',
        'sidang_tingkat_2' => 'Y', 'sks_lulus' => '130', 'sks_belum_lulus' => '0',
        'ipk' => '3.50', 'semester' => '7',
        'status_skpi' => 'Disetujui', 'skpi_status_1' => 'Y', 'skpi_status_2' => 'Y', 'skpi_status_3' => 'Y',
        'persetujuan_maju_sidang' => 'APPROVED', 'status_ta' => '1',
        'nilai_bahasa' => '440', 'jenis_tes_bahasa' => 'Online EPrT', 'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [['jenis_tes' => 'Online EPrT', 'nilai' => '440',
            'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']],
        'total_tak' => '80', 'status_keuangan' => 'LUNAS', 'status_keuangan_asli' => 'SUDAH LUNAS',
        'judul_tugas_akhir' => 'Judul Sintetis JSON 66', 'tanggal_sidang' => '20-11-2026',
    ], $overrides);
}

beforeEach(function () {
    $this->student = Student::create(['student_number' => '1202266001', 'name' => 'Mahasiswa Sintetis JSON 66',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    $this->menus = ['akademik', 'bahasa', 'tak', 'keuangan', 'mata_kuliah', 'jadwal_sidang', 'skpi', 'maju_sidang'];
    $this->profile = fn () => StudentAcademicProfile::where('student_number', '1202266001')->firstOrFail();
    $this->postPayload = function (array $payload, string $routeName = 'sync.import-json') {
        $input = ['json_file' => UploadedFile::fake()->createWithContent('sintetis-66.json', json_encode($payload, JSON_THROW_ON_ERROR))];
        if ($routeName === 'sync.import-json') $input['type'] = 'academic_profile';
        return $this->from(route('sync.index'))->post(route($routeName), $input);
    };
    $this->import66 = function (array $row, ?array $menus = null, ?string $period = null) {
        $payload = ['selected_menus' => $menus ?? $this->menus, 'data' => [$row]];
        if ($period !== null) $payload['finance_period'] = $period;
        return ($this->postPayload)($payload)->assertRedirect(route('sync.index'))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    };
});

test('json66_modern_import_stores_official_summary_and_updates_existing_academic_tables', function () {
    ($this->import66)(academicJson66Row());
    $profile = ($this->profile)();
    expect($profile->groups['skpi']['approved'])->toBeTrue();
    expect($profile->groups['sidang_approval']['approved'])->toBeTrue();
    expect((float) $profile->groups['language']['score'])->toBe(440.0);
    expect($profile->groups['language']['attempts'])->toBe(3);
    expect($profile->groups['language']['history'][0]['kedaluwarsa'])->toBe('19 Mei 2028');
    expect($profile->groups['bpp']['period'])->toBeNull();
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(StudentSidangTingkat::where('student_number', $this->student->student_number)->firstOrFail()->tingkat_2)->toBeTrue();
    expect((float) StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBe(80.0);
    expect(StudentSkpi::where('student_number', $this->student->student_number)->firstOrFail()->raw_data['status_skpi'])->toBe('Disetujui');
    expect(StudentFinalProject::where('student_number', $this->student->student_number)->value('thesis_title_id'))->toBe('Judul Sintetis JSON 66');
    // Official history stays in its JSON group; never overwrite manual EPRT rows.
    expect(StudentEprt::where('student_number', $this->student->student_number)->count())->toBe(0);
});

test('json66_reimport_replaces_official_language_history_without_duplicates_or_manual_row_deletion', function () {
    $manual = StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 500]);
    ($this->import66)(academicJson66Row());
    $history = [['jenis_tes' => 'ITP TOEFL', 'nilai' => '470', 'status_berlaku' => 'Berlaku']];
    ($this->import66)(academicJson66Row(['nilai_bahasa' => '470', 'jenis_tes_bahasa' => 'ITP TOEFL',
        'jumlah_tes_bahasa' => 1, 'riwayat_nilai_bahasa' => $history]), ['bahasa']);
    expect(StudentAcademicProfile::count())->toBe(1);
    $language = ($this->profile)()->groups['language'];
    expect((float) $language['score'])->toBe(470.0);
    expect($language['attempts'])->toBe(1);
    expect($language['history'])->toEqual($history);
    expect((int) $manual->fresh()->total_score)->toBe(500);
    expect(StudentEprt::count())->toBe(1);
});

test('json66_menu_selection_preserves_unselected_existing_data_even_if_unused_fields_are_null', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->import66)(academicJson66Row(['total_tak' => '60', 'status_skpi' => null, 'nilai_bahasa' => null,
        'sidang_tingkat_2' => null, 'sks_lulus' => null, 'status_keuangan' => null]), ['tak']);
    $after = ($this->profile)()->groups;
    expect((float) $after['tak']['score'])->toBe(60.0);
    foreach (['skpi', 'language', 'academic', 'bpp'] as $group) expect($after[$group])->toEqual($before[$group]);
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(StudentBppPayment::where('student_number', $this->student->student_number)->value('status'))->toBe('LUNAS');
});

test('json66_explicit_null_and_zero_are_not_treated_as_missing_data', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['total_tak' => '0', 'status_skpi' => 'Belum Disetujui']), ['tak', 'skpi']);
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(0.0);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    ($this->import66)(academicJson66Row(['total_tak' => null]), ['tak']);
    expect(($this->profile)()->groups['tak']['score'])->toBeNull();
    expect(StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBeNull();
});

test('json66_tak_gateway_error_preserves_old_tak_but_imports_successful_skpi', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['total_tak' => null, 'status_skpi' => 'Belum Disetujui',
        'error' => 'tak: Token gateway TAK sudah kedaluwarsa.']));
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(80.0);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect((float) StudentTak::where('student_number', $this->student->student_number)->value('total_points'))->toBe(80.0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('menu tak gagal');
});

test('json66_bpp_credit_to_paid_updates_same_period_and_keeps_other_periods', function () {
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan'], '2627/1');
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('KREDIT');
    ($this->import66)(academicJson66Row(), ['keuangan'], '2627/1');
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan'], '2627/2');
    $periods = ($this->profile)()->bpp_periods;
    expect(count($periods))->toBe(2);
    expect($periods['2627/1']['status'])->toBe('LUNAS');
    expect($periods['2627/2']['status'])->toBe('KREDIT');
});

test('json66_study_semester_never_becomes_an_invented_finance_period', function () {
    ($this->import66)(academicJson66Row(['semester' => '9']), ['keuangan']);
    expect(($this->profile)()->groups['bpp']['period'])->toBeNull();
    expect(($this->profile)()->bpp_periods)->toBe([]);
});

test('json66_unknown_period_import_does_not_destroy_confirmed_period_history', function () {
    ($this->import66)(academicJson66Row(), ['keuangan'], '2627/1');
    ($this->import66)(academicJson66Row(['status_keuangan' => 'PERLU VERIFIKASI', 'status_keuangan_asli' => 'KREDIT']), ['keuangan']);
    expect(($this->profile)()->groups['bpp']['period'])->toBeNull();
    expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
});

test('json66_period_conflict_skips_bpp_but_keeps_other_successful_groups', function () {
    ($this->import66)(academicJson66Row(['semester' => '2627/2']), null, '2627/1');
    expect(($this->profile)()->groups)->not->toHaveKey('bpp');
    expect(($this->profile)()->groups['skpi']['approved'])->toBeTrue();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('berbeda dengan konteks impor');
});

test('json66_explicit_empty_menu_list_keeps_all_old_data', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->import66)(academicJson66Row(['total_tak' => '0']), []);
    expect(($this->profile)()->groups)->toEqual($before);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_ecct_null_score_and_three_official_attempts_are_stored_without_fabricated_score', function () {
    ($this->import66)(academicJson66Row(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT',
        'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => []]), ['bahasa']);
    $language = ($this->profile)()->groups['language'];
    expect($language['score'])->toBeNull();
    expect($language['type'])->toBe('Online ECCT');
    expect($language['attempts'])->toBe(3);
});

test('json66_official_import_without_selected_menus_is_detected_from_new_field_names', function () {
    ($this->postPayload)(['data' => [academicJson66Row()]])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
});

test('json66_both_profile_import_routes_use_same_metadata_and_storage', function () {
    foreach (['sync.import-json', 'sync.import-academic-profile'] as $routeName) {
        ($this->postPayload)(['data' => [academicJson66Row()], 'selected_menus' => $this->menus,
            'finance_period' => '2627/1'], $routeName)->assertSessionHasNoErrors()->assertSessionHas('success');
        expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
        expect(($this->profile)()->bpp_periods['2627/1']['status'])->toBe('LUNAS');
    }
    expect(StudentAcademicProfile::count())->toBe(1);
});

test('json66_mismatched_name_does_not_write_any_academic_table', function () {
    ($this->import66)(academicJson66Row(['nama' => 'Nama Berbeda']));
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(StudentTak::count())->toBe(0);
    expect(StudentSkpi::count())->toBe(0);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_unknown_student_is_skipped_without_creating_student_or_profile', function () {
    ($this->import66)(academicJson66Row(['nim' => '1202266999']));
    expect(Student::count())->toBe(1);
    expect(StudentAcademicProfile::count())->toBe(0);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(1);
});

test('json66_import_never_changes_another_students_profile', function () {
    $other = Student::create(['student_number' => '1202266002', 'name' => 'Mahasiswa Lain Sintetis',
        'academic_year' => 2022, 'study_program' => 'Informatika']);
    $profile = StudentAcademicProfile::create(['student_number' => $other->student_number,
        'groups' => ['tak' => ['score' => 99]], 'bpp_periods' => []]);
    ($this->import66)(academicJson66Row());
    expect($profile->fresh()->groups)->toEqual(['tak' => ['score' => 99]]);
});

test('json66_failure_saving_summary_rolls_back_other_writes_for_the_same_row', function () {
    StudentAcademicProfile::saving(function () { throw new RuntimeException('Simulated save failure'); });
    try {
        ($this->import66)(academicJson66Row());
        expect(StudentAcademicProfile::count())->toBe(0);
        expect(StudentTak::count())->toBe(0);
        expect(StudentSkpi::count())->toBe(0);
        expect(StudentCourseIssue::count())->toBe(0);
        expect(StudentSidangTingkat::count())->toBe(0);
        expect($this->student->fresh()->total_credits)->toBeNull();
        expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(0);
    } finally {
        StudentAcademicProfile::flushEventListeners();
    }
});

test('json66_failed_refresh_after_commit_does_not_misreport_row_as_rolled_back', function () {
    $this->mock(GraduationDataSyncService::class)->shouldReceive('syncExistingRegistrations')->once()->andThrow(new RuntimeException('Refresh failed'));
    ($this->import66)(academicJson66Row());
    expect(StudentAcademicProfile::count())->toBe(1);
    expect((int) SyncLog::latest('id')->firstOrFail()->processed_records)->toBe(1);
    expect((int) SyncLog::latest('id')->firstOrFail()->skipped_records)->toBe(0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('data tersimpan');
});

test('json66_malformed_language_preserves_previous_language_while_valid_tak_changes', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['jumlah_tes_bahasa' => '3.5', 'total_tak' => '60']));
    expect(($this->profile)()->groups['language']['attempts'])->toBe(3);
    expect((float) ($this->profile)()->groups['tak']['score'])->toBe(60.0);
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('Jumlah tes');
});

test('json66_invalid_context_is_rejected_before_sync_log_or_data_changes', function () {
    foreach ([['selected_menus' => null], ['selected_menus' => ['bahasa' => true]], ['finance_period' => '7']] as $context) {
        ($this->postPayload)(array_replace(['data' => [academicJson66Row()]], $context))->assertSessionHasErrors();
    }
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(SyncLog::count())->toBe(0);
});

test('json66_empty_data_list_is_a_zero_row_import_not_a_fake_single_record', function () {
    ($this->postPayload)(['selected_menus' => ['tak'], 'data' => []])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect((int) SyncLog::latest('id')->firstOrFail()->total_records)->toBe(0);
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_legacy_nested_fixture_import_remains_compatible_until_facts_integration', function () {
    $row = ['nim' => $this->student->student_number, 'nama' => $this->student->name,
        'tak' => ['total_points' => 60], 'skpi' => ['approval_status' => 'approved'],
        'eprt' => [['total_score' => 449, 'attempt_number' => 1, 'test_date' => '2026-10-01']]];
    $result = app(StudentAcademicProfileImportService::class)->import([$row]);
    expect($result['skipped'])->toBe(0);
    expect(StudentAcademicProfile::count())->toBe(0);
    expect(StudentEprt::where('student_number', $this->student->student_number)->count())->toBe(1);
});

test('json66_profile_is_removed_when_its_student_is_deleted', function () {
    ($this->import66)(academicJson66Row());
    $this->student->delete();
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_import_still_requires_sync_permission_on_both_routes', function () {
    $this->actingAs(User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]));
    foreach (['sync.import-json', 'sync.import-academic-profile'] as $routeName) {
        ($this->postPayload)(['selected_menus' => $this->menus, 'data' => [academicJson66Row()]], $routeName)->assertForbidden();
    }
    expect(StudentAcademicProfile::count())->toBe(0);
});

test('json66_flat_partial_row_without_menu_metadata_preserves_missing_academic_fields', function () {
    ($this->import66)(academicJson66Row());
    $before = ($this->profile)()->groups;
    ($this->postPayload)(['data' => [['nim' => $this->student->student_number, 'nama' => $this->student->name,
        'total_tak' => '61']]])->assertSessionHasNoErrors()->assertSessionHas('success');
    $after = ($this->profile)()->groups;
    expect((float) $after['tak']['score'])->toBe(61.0);
    expect($after['academic'])->toEqual($before['academic']);
    expect($after['language'])->toEqual($before['language']);
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
});

test('json66_academic_error_preserves_previous_academic_fields_while_other_groups_update', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['sks_lulus' => null, 'sidang_tingkat_2' => null,
        'status_skpi' => 'Belum Disetujui', 'error' => 'akademik: gagal mengambil data.']));
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect(($this->profile)()->groups['academic']['level_2_passed'])->toBeTrue();
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('menu akademik gagal');
});

test('json66_invalid_academic_numbers_do_not_partially_change_academic_tables', function () {
    ($this->import66)(academicJson66Row());
    ($this->import66)(academicJson66Row(['sks_lulus' => '0', 'ipk' => true, 'status_skpi' => 'Belum Disetujui']));
    expect((int) $this->student->fresh()->total_credits)->toBe(130);
    expect((int) StudentCourseIssue::where('student_number', $this->student->student_number)->value('credits_passed'))->toBe(130);
    expect(($this->profile)()->groups['skpi']['approved'])->toBeFalse();
    expect(SyncLog::latest('id')->firstOrFail()->error_message)->toContain('Field angka tidak valid');
});

test('json66_real_multiline_courses_and_day_month_year_slash_dates_are_preserved', function () {
    ($this->import66)(academicJson66Row(['mk_mengulang' => "MK01 - CONTOH SATU (D, Semester 3)\nMK02 - CONTOH DUA (E, Semester 4)",
        'mk_belum_lulus' => '-', 'tanggal_sidang' => '31/07/2026']));
    $issues = StudentCourseIssue::where('student_number', $this->student->student_number)->firstOrFail();
    expect($issues->retaking_courses)->toBe(['MK01 - CONTOH SATU (D, Semester 3)', 'MK02 - CONTOH DUA (E, Semester 4)']);
    expect($issues->failed_courses)->toBe([]);
    expect(StudentFinalProject::where('student_number', $this->student->student_number)->firstOrFail()->exam_date->format('Y-m-d'))->toBe('2026-07-31');
});

test('json66_igracias_only_import_preserves_basila_until_photo_menu_is_explicitly_imported', function () {
    $approval = StudentBasilaApproval::create(['student_number' => $this->student->student_number,
        'photo_approval' => true, 'status' => 'approved']);
    ($this->import66)(academicJson66Row());
    expect($approval->fresh()->photo_approval)->toBeTrue();
    expect($approval->fresh()->status)->toBe('approved');
    ($this->import66)(academicJson66Row(['approval_foto_dosen_wali_basila' => 'N']), ['basila_foto']);
    expect($approval->fresh()->photo_approval)->toBeFalse();
    expect($approval->fresh()->status)->toBe('pending');
});
```

Jalankan sekali untuk memastikan test mendeteksi implementasi yang belum lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php --stop-on-failure
```

Pada tahap ini kegagalan diharapkan karena scaffold belum berisi schema/logic. Bila koneksi database gagal, selesaikan koneksi Podman dahulu; kegagalan koneksi bukan bukti pengujian perilaku. Jika sudah mengisi implementasi terlebih dahulu, tidak perlu menghapusnya untuk mengulang kegagalan awal.

## 5. Tahap 2 —isi migration, model, dan service

### 5.1 Migration

Isi seluruh file `database/migrations/<timestamp>_create_student_academic_profiles_table.php` yang baru dibuat:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 15)->unique();
            $table->json('groups')->nullable();
            $table->json('bpp_periods')->nullable();
            $table->timestamps();
            $table->foreign('student_number')->references('student_number')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_profiles');
    }
};
```

Tidak mengubah migration lama atau membackfill data resmi dari fixture sementara. Kolom student_number mengikuti ukuran15 dan default charset/collation koneksi yang sama dengan tabel students. Jika koneksi utama masih MariaDB bermasalah, tetap gunakan MySQL Podman yang sudah berhasil sebelumnya.

### 5.2 Model

Isi seluruh `app/Models/StudentAcademicProfile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicProfile extends Model
{
    protected $fillable = ['student_number', 'groups', 'bpp_periods'];

    protected $attributes = ['groups' => '{}', 'bpp_periods' => '{}'];

    protected $casts = ['groups' => 'array', 'bpp_periods' => 'array'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}
```

### 5.3 Akses pembaca error menu

Pada `app/Support/AcademicProfileJsonNormalizer.php`, cari tepat satu signature:

```text
private function failures(mixed $error): array
```

Ganti menjadi:

```text
public function failures(mixed $error): array
```

Isi method dan bagian lainnya tetap. Tujuannya agar service memakai klasifikasi error yang sama, tidak membuat regex kedua yang berbeda.

### 5.4 Service format JSON terbaru

Isi seluruh `app/Services/AcademicProfileJsonImportService.php`:

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentBasilaApproval;
use App\Models\StudentBppPayment;
use App\Models\StudentCourseIssue;
use App\Models\StudentFinalProject;
use App\Models\StudentFinalProjectGrade;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Support\AcademicProfileJsonNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/** Imports the current flat JSON format. Does not change final TA snapshots. */
class AcademicProfileJsonImportService
{
    public function import(array $rows, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        $normalizer = new AcademicProfileJsonNormalizer;
        // Validate menu context even for an empty batch, before any write.
        $normalizer->normalize([], $selectedMenus, $financePeriod);
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'mismatched' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            if (!is_array($row) || !is_string($row['nim'] ?? null) || !is_string($row['nama'] ?? null)
                || trim($row['nim']) === '' || trim($row['nama']) === '') {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM atau nama tidak valid.";
                continue;
            }
            $nim = trim($row['nim']);
            try {
                $outcome = DB::transaction(function () use ($row, $nim, $selectedMenus, $financePeriod, $normalizer) {
                    // The parent row serializes imports for the same student.
                    $student = Student::where('student_number', $nim)->lockForUpdate()->first();
                    if (!$student) return ['kind' => 'skipped', 'warnings' => ['Mahasiswa belum terdaftar.']];
                    if (mb_strtolower(trim($student->name)) !== mb_strtolower(trim($row['nama']))) {
                        return ['kind' => 'mismatched', 'warnings' => ['NIM dan nama tidak cocok.']];
                    }

                    $result = $normalizer->normalize($row, $selectedMenus, $financePeriod);
                    $updates = $result['updates'];
                    $warnings = array_values($result['skipped']);
                    $failed = $normalizer->failures($row['error'] ?? null);
                    foreach (array_intersect($failed, ['akademik', 'mata_kuliah', 'jadwal_sidang', 'basila_foto']) as $menu) {
                        $warnings[] = 'Pengambilan data menu '.$menu.' gagal.';
                    }
                    $allowed = fn (array $menus) => !in_array('*', $failed, true)
                        && array_intersect($failed, $menus) === []
                        && ($selectedMenus === null || array_intersect($selectedMenus, $menus) !== []);
                    $changed = false;

                    if ($allowed(['akademik', 'mata_kuliah'])) {
                        try {
                            $academic = DB::transaction(fn () => $this->academic($row, $student, $nim));
                            if ($academic !== []) {
                                $updates['academic'] = $academic;
                                $changed = true;
                            }
                        } catch (InvalidArgumentException $exception) {
                            // academic() validates its patches before writing any table.
                            $warnings[] = $exception->getMessage();
                        }
                    }
                    if ($allowed(['jadwal_sidang'])) {
                        try {
                            $changed = DB::transaction(fn () => $this->project($row, $nim)) || $changed;
                        } catch (InvalidArgumentException $exception) {
                            $warnings[] = $exception->getMessage();
                        }
                    }
                    if ($allowed(['basila_foto']) && array_key_exists('approval_foto_dosen_wali_basila', $row)) {
                        try {
                            $photo = $this->boolean($row['approval_foto_dosen_wali_basila']);
                            DB::transaction(fn () => $this->upsert(StudentBasilaApproval::class, $nim,
                                ['photo_approval' => $photo === true, 'status' => $photo === true ? 'approved' : 'pending']));
                            $changed = true;
                        } catch (InvalidArgumentException $exception) {
                            $warnings[] = $exception->getMessage();
                        }
                    }

                    if (isset($updates['skpi'])) {
                        $raw = array_intersect_key($row, array_flip(['status_skpi', 'skpi_status_1', 'skpi_status_2', 'skpi_status_3']));
                        // Keep the old table readable until the facts reader is updated.
                        $raw['approval_status'] = $updates['skpi']['approved'] ? 'approved' : 'rejected';
                        $this->upsert(StudentSkpi::class, $nim, ['raw_data' => $raw]);
                    }
                    if (isset($updates['tak'])) {
                        StudentTak::updateOrCreate(['student_number' => $nim, 'test_date' => null],
                            ['total_points' => $updates['tak']['score'], 'expiry_date' => null]);
                    }
                    if (isset($updates['bpp'])) {
                        $this->upsert(StudentBppPayment::class, $nim, ['status' => $updates['bpp']['status']]);
                    }

                    if ($updates === [] && !$changed) {
                        return ['kind' => 'skipped', 'warnings' => $warnings ?: ['Tidak ada data yang dapat diperbarui.']];
                    }

                    $profile = StudentAcademicProfile::where('student_number', $nim)->first();
                    $kind = $profile ? 'updated' : 'created';
                    $profile ??= new StudentAcademicProfile(['student_number' => $nim]);
                    $groups = $profile->groups ?? [];
                    foreach ($updates as $group => $values) {
                        // Academic fields may be individually omitted; language is atomic.
                        $groups[$group] = $group === 'academic'
                            ? array_replace($groups[$group] ?? [], $values) : $values;
                    }
                    $periods = $profile->bpp_periods ?? [];
                    if (isset($updates['bpp']['period'])) {
                        $periods[$updates['bpp']['period']] = $updates['bpp'];
                    }
                    $profile->fill(['groups' => $groups, 'bpp_periods' => $periods])->save();
                    return ['kind' => $kind, 'warnings' => $warnings];
                }, 3);
            } catch (Throwable $exception) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: penyimpanan gagal; perubahan baris dibatalkan.";
                Log::warning('Academic JSON row rolled back', ['row' => $index, 'exception' => $exception::class]);
                continue;
            }

            $summary[$outcome['kind']]++;
            foreach ($outcome['warnings'] as $warning) $summary['errors'][] = "Baris #{$index}: {$warning}";
            if (!in_array($outcome['kind'], ['created', 'updated'], true)) continue;
            // These side effects run only after the row's transaction committed.
            try {
                Cache::forget("campus:{$nim}");
                app(GraduationDataSyncService::class)->syncExistingRegistrations($nim);
            } catch (Throwable $exception) {
                $summary['errors'][] = "Baris #{$index}: data tersimpan, penyegaran tampilan terkait belum berhasil.";
                Log::warning('Academic JSON refresh failed', ['row' => $index, 'exception' => $exception::class]);
            }
        }
        return $summary;
    }

    private function academic(array $row, Student $student, string $nim): array
    {
        $studentPatch = $issues = $levels = $facts = [];
        foreach (['ipk' => ['gpa', 'gpa'], 'sks_lulus' => ['total_credits', 'passed_credits']] as $key => [$column, $fact]) {
            if (!array_key_exists($key, $row)) continue;
            $value = $this->number($row[$key]);
            if ($key === 'sks_lulus' && $value !== null && floor($value) !== $value) {
                throw new InvalidArgumentException('SKS harus berupa bilangan bulat.');
            }
            $studentPatch[$column] = $value;
            $facts[$fact] = $value;
            if ($key === 'sks_lulus') $issues['credits_passed'] = $value;
        }
        if (array_key_exists('dosen_wali', $row)) $studentPatch['academic_advisor'] = $this->text($row['dosen_wali']);
        if ((is_string($row['semester'] ?? null) || is_int($row['semester'] ?? null))
            && preg_match('/^\d+$/D', (string) $row['semester']) === 1) {
            $semester = (int) $row['semester'];
            if ($semester > 0 && $semester <= 127) $studentPatch['active_semester'] = $semester;
        }
        foreach (range(1, 4) as $level) {
            $key = 'sidang_tingkat_'.$level;
            if (!array_key_exists($key, $row)) continue;
            $value = $this->boolean($row[$key]);
            $levels['tingkat_'.$level] = $value === true;
            if ($level === 2) $facts['level_2_passed'] = $value;
        }
        if (array_key_exists('sks_belum_lulus', $row)) {
            $value = $this->number($row['sks_belum_lulus']);
            if ($value !== null && floor($value) !== $value) throw new InvalidArgumentException('SKS harus berupa bilangan bulat.');
            $issues['credits_remaining'] = $value;
        }
        foreach (['mk_mengulang' => 'retaking_courses', 'mk_belum_lulus' => 'failed_courses'] as $key => $column) {
            if (!array_key_exists($key, $row)) continue;
            $issues[$column] = $this->courseList($row[$key]);
        }
        $grade = array_key_exists('indeks_nilai_tugas_akhir', $row) ? $this->text($row['indeks_nilai_tugas_akhir']) : false;
        // All conversion above precedes the writes below.
        if ($studentPatch !== []) $student->fill($studentPatch)->save();
        $this->upsert(StudentCourseIssue::class, $nim, $issues);
        $this->upsert(StudentSidangTingkat::class, $nim, $levels);
        if ($grade !== false) $this->upsert(StudentFinalProjectGrade::class, $nim, ['grade' => $grade]);
        if ($facts === [] && ($studentPatch !== [] || $issues !== [] || $levels !== [] || $grade !== false)) {
            // Marks a successful partial academic update without inventing facts.
            $facts['profile_present'] = true;
        }
        return $facts;
    }

    private function project(array $row, string $nim): bool
    {
        $patch = [];
        foreach (['judul_tugas_akhir' => 'thesis_title_id', 'pembimbing_1' => 'advisor_1', 'pembimbing_2' => 'advisor_2',
            'penguji_1' => 'examiner_1', 'penguji_2' => 'examiner_2', 'waktu' => 'exam_time', 'tempat' => 'exam_location'] as $key => $column) {
            if (array_key_exists($key, $row)) $patch[$column] = $this->text($row[$key]);
        }
        if (array_key_exists('tanggal_sidang', $row)) {
            $date = $this->text($row['tanggal_sidang']);
            $patch['exam_date'] = null;
            if ($date !== null) {
                $format = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? 'Y-m-d'
                    : (str_contains($date, '/') ? 'd/m/Y' : 'd-m-Y');
                try {
                    $parsed = Carbon::createFromFormat('!'.$format, $date);
                    if ($parsed->format($format) !== $date) throw new InvalidArgumentException;
                    $patch['exam_date'] = $parsed->format('Y-m-d');
                } catch (Throwable) {
                    throw new InvalidArgumentException('Tanggal sidang harus berformat d/m/Y, d-m-Y atau Y-m-d yang valid.');
                }
            }
        }
        return $this->upsert(StudentFinalProject::class, $nim, $patch);
    }

    private function upsert(string $model, string $nim, array $patch): bool
    {
        if ($patch === []) return false;
        $model::updateOrCreate(['student_number' => $nim], $patch);
        return true;
    }

    private function courseList(mixed $value): ?array
    {
        if ($value === null) return null;
        if (is_array($value) && array_is_list($value)) return $value;
        if (!is_string($value)) throw new InvalidArgumentException('Daftar mata kuliah harus berupa teks, daftar atau null.');
        $value = trim($value);
        if ($value === '' || $value === '-') return [];
        // Keep commas inside descriptions such as "(D, Semester 3)" intact.
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value)), fn ($line) => $line !== ''));
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Field teks tidak valid.');
        return trim($value) === '' ? null : trim($value);
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) throw new InvalidArgumentException('Field angka tidak valid.');
        return (float) $value;
    }

    private function boolean(mixed $value): ?bool
    {
        if ($value === null) return null;
        if ($value === true || $value === 'Y') return true;
        if ($value === false || $value === 'N') return false;
        throw new InvalidArgumentException('Status sidang/foto harus Y, N, boolean, atau null.');
    }
}
```

Service juga memperbarui tabel SKPI/TAK/BPP lama agar informasi dasar masih terbaca selama transisi pembaca. SKPI menyimpan status resmi dan alias kompatibel. Data bahasa resmi tetap pada profilJSON. Tidak mengubah snapshot final TA atau file upload mahasiswa.

Academic/project mempunyai savepoint transaksi untuk menjaga perubahan grup tetap utuh saat konversi gagal. Kegagalan penyimpanan utama menggagalkan seluruh perubahan mahasiswa itu. Row berikutnya tetap bisa diproses. Pesan error sumber tidak disalin mentah ke log hasil; nama mahasiswa tidak dimasukkan ke pesan error baru.

## 6. Tahap 3 —hubungkan service dan controller

### 6.1 Method import pada service yang sekarang

Di `app/Services/StudentAcademicProfileImportService.php`, ganti **seluruh method `public function import(...)`** dengan kode berikut. Pertahankan private `upsertAllTables`, helper, import/use dan class lainnya.

```php
    public function import(array $data, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        // Nested fixtures/imports remain readable; current flat exports use the normalizer.
        $legacyContainers = ['tugas_akhir', 'tak', 'basila', 'sidang_tingkat', 'course_issues',
            'bpp', 'nilai_ta', 'eprt', 'skpi', 'pembayaran_wisuda'];
        $officialFields = ['status_skpi', 'persetujuan_maju_sidang', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa'];
        $legacyOnly = $selectedMenus === null && $financePeriod === null;
        foreach ($data as $row) {
            if (!is_array($row) || array_intersect(array_keys($row), $legacyContainers) === []
                || array_intersect(array_keys($row), $officialFields) !== []) {
                $legacyOnly = false;
                break;
            }
        }
        if (!$legacyOnly) {
            return app(AcademicProfileJsonImportService::class)->import($data, $selectedMenus, $financePeriod);
        }
        $summary = [
            'created'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'mismatched' => 0,
            'errors'     => [],
        ];

        foreach ($data as $index => $row) {
            if (!is_array($row)) {
                $summary['skipped']++;
                continue;
            }

            $nim  = trim($row['nim'] ?? '');
            $nama = trim($row['nama'] ?? '');

            // Field `no` dan `error` diabaikan sepenuhnya saat mapping
            // (tidak perlu unset, cukup tidak dipakai)

            if (empty($nim)) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM kosong, dilewati.";
                continue;
            }

            // ─── Validasi kecocokan NIM + Nama ──────────────────────────
            $student = Student::where('student_number', $nim)->first();

            if (!$student) {
                // NIM tidak ditemukan di database → skip.
                // Alasan: lebih aman daripada membuat Student minimal tanpa
                // data lengkap (prodi, kelas, dll). Admin harus import data
                // student terlebih dahulu via import profil mahasiswa.
                $summary['skipped']++;
                $summary['errors'][] = "Baris #{$index}: NIM {$nim} ({$nama}) tidak ditemukan di database, dilewati.";
                Log::warning('AcademicProfileImport: NIM tidak ditemukan', [
                    'nim'  => $nim,
                    'nama' => $nama,
                ]);
                continue;
            }

            // Cek kecocokan nama (case-insensitive, trim whitespace)
            if (strtolower(trim($student->name)) !== strtolower($nama)) {
                // NIM ada tapi nama beda — kemungkinan typo nama di JSON
                // atau data untuk mahasiswa yang berbeda.
                // Keputusan: skip + log warning, supaya bisa dicek manual.
                // Jangan diam-diam menimpa data mahasiswa lain.
                $summary['mismatched']++;
                $summary['errors'][] = "Baris #{$index}: NIM {$nim} ada di DB dengan nama \"{$student->name}\", tapi JSON nama \"{$nama}\" (beda). Dilewati — cek manual.";
                Log::warning('AcademicProfileImport: NIM cocok tapi nama beda', [
                    'nim'       => $nim,
                    'nama_json' => $nama,
                    'nama_db'   => $student->name,
                ]);
                continue;
            }

            // ─── NIM + Nama cocok → lanjut upsert ──────────────────────
            try {
                DB::transaction(function () use ($row, $nim, $student, &$summary) {
                    $isNew = $this->upsertAllTables($row, $nim, $student);
                    $isNew ? $summary['created']++ : $summary['updated']++;
                });
                app(GraduationDataSyncService::class)->syncExistingRegistrations($nim);
            } catch (\Exception $e) {
                $summary['skipped']++;
                $summary['errors'][] = "Baris NIM {$nim}: Error — {$e->getMessage()}";
                Log::error('AcademicProfileImport: Error saat upsert', [
                    'nim'   => $nim,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
```

Jangan menambahkan method import kedua. Jalur lama dipertahankan untuk format nested yang memang sedang dipakai fixture; format baru tidak melewati penulisan default/null dari jalur lama.

### 6.2 Method controller

Pada `app/Http/Controllers/SyncController.php`, ganti **seluruh method `importAcademicProfile(Request $request)`** dengan berikut. Jangan mengganti `importJson`, `importGraduationRow`, atau route.

```php
    public function importAcademicProfile(Request $request)
    {
        $request->validate([
            'json_file' => ['required', 'file', 'mimetypes:application/json,text/plain'],
        ]);
        $payload = json_decode(file_get_contents($request->file('json_file')->getRealPath()), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            return back()->withErrors(['json_file' => 'Format JSON tidak valid.']);
        }

        $wrapped = array_key_exists('data', $payload);
        $data = $wrapped ? $payload['data'] : $payload;
        if (!is_array($data)) return back()->withErrors(['json_file' => 'Data profil harus berupa array.']);
        if (!array_is_list($data)) $data = [$data];
        $context = $wrapped ? array_intersect_key($payload, array_flip(['selected_menus', 'finance_period'])) : [];
        $context = \Illuminate\Support\Facades\Validator::make($context, [
            'selected_menus' => ['sometimes', 'array', 'list'],
            'selected_menus.*' => ['required', 'string'],
            'finance_period' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}\/[12]$/D'],
        ])->validate();

        $log = SyncLog::create([
            'module' => 'academic_profile', 'status' => 'processing',
            'triggered_by' => auth()->user()->email, 'started_at' => now(),
        ]);
        try {
            $result = app(StudentAcademicProfileImportService::class)->import(
                $data, $context['selected_menus'] ?? null, $context['finance_period'] ?? null
            );
            $log->update([
                'status' => 'completed', 'total_records' => count($data),
                'processed_records' => $result['created'] + $result['updated'],
                'skipped_records' => $result['skipped'] + $result['mismatched'],
                'error_message' => $result['errors'] === [] ? null : implode(' | ', array_slice($result['errors'], 0, 10)),
                'completed_at' => now(),
            ]);
            $message = "Import Profil Akademik selesai: {$result['created']} baru, {$result['updated']} diperbarui, "
                ."{$result['skipped']} dilewati, {$result['mismatched']} NIM-nama mismatch.";
            if ($result['errors'] !== []) $message .= ' Ada catatan pada log sinkronisasi.';
            return redirect()->route('sync.index')->with('success', $message);
        } catch (\Throwable $exception) {
            Log::warning('ImportAcademicProfile failed', ['exception' => $exception::class]);
            $log->update(['status' => 'failed', 'error_message' => 'Import profil akademik gagal.', 'completed_at' => now()]);
            return back()->with('error', 'Import profil akademik gagal. Periksa format dan log aplikasi.');
        }
    }
```

`selected_menus=[]` berarti tidak ada menu dipilih; berbeda dengan metadata tidak disertakan. Wrapper dengan `data=[]` adalah impor 0 baris. Konteks malformed ditolak sebelum SyncLog/perubahan DB dibuat. Route umum sync.import-json tetap memvalidasi tipe sebelum mengarah ke method ini; route khusus tetap memakai permission yang sama.

## 7. Tahap 4 —lint, migrate, dan test

Lint file yang kamu isi:

```bash
php -l app/Models/StudentAcademicProfile.php
php -l app/Services/AcademicProfileJsonImportService.php
php -l app/Services/StudentAcademicProfileImportService.php
php -l app/Support/AcademicProfileJsonNormalizer.php
php -l app/Http/Controllers/SyncController.php
php -l tests/Feature/AcademicProfileJsonImportTest.php
```

Lint migration dengan filename aktual yang dihasilkan Artisan:

```bash
bash <<'BASH'
set -euo pipefail
task_migrations=(database/migrations/*_create_student_academic_profiles_table.php)
test "${#task_migrations[@]}" -eq 1
test -f "${task_migrations[0]}"
php -l "${task_migrations[0]}"
BASH
```

Migration lokal menambah satu tabel pada database development dadu. Konfigurasi ini memakai Podman yang sama seperti sebelumnya. Jalankan **hanya di laptop development**, bukan server staging:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan migrate
```

Tidak memakai migrate:fresh atau seed. Database pengujian tetap dadu_testing; RefreshDatabase menangani schema pengujian melalui migration baru.

Test baru:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php
```

Target **29 passed**. Fokus bersama unit pembaca 65:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php
```

Target **54 passed** jika 25 unit 65 belum berubah.

Regresi jalur fixture nested yang masih dipakai saat ini:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDummyImportTest.php tests/Feature/SidangTaAdmissionFactsTest.php
```

Target **16 passed** jika kedua file belum berubah. Setelah fokus di atas lulus, jalankan regresi TA karena controller/importer dibagi dengan alur pengujian TA:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php tests/Feature/SidangTa*Test.php tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```

Yang diperiksa adalah semua passed, bukan memaksakan jumlah total jika ada test lain yang ditambah. Tidak menjalankan full suite seluruh proyek yang masih mempunyai temuan Auth61f. Tidak perlu Node/Vite build karena JS/Blade belum berubah.

## 8. Tahap 5 —manual impor dari berkas tetap

**Pembaruan6 November2026:** pengguna meminta berkas JSON impor disimpan pada docs/fixtures/sidang-ta, bukan /tmp. Panduan pembuatan file sementara di bagian ini digantikan oleh [67 bagian1–2](67-fakta-resmi-blocking-dan-data-demo.md): diagnosis profil tidak ditemukan, empat JSON siap impor, dan urutan Mahasiswa →KRS →Profil Akademik untuk lokal/Docker.

Gunakan file67-01,67-02,67-03 sesuai tipe impor; file67-04 untuk skenario ECCT. Superadmin adalah pengimpor; Krisna dan Dummy002 adalah dua profil mahasiswa. Impor file67-03 lagi untuk memulihkan data dasar setelah varian. Tidak perlu membuat akun/password baru atau reset database.

Record StudentAcademicProfile terbentuk setelah impor Profil Akademik yang berhasil, bukan hanya setelah unit test. Pembacaan aturan baru dihubungkan pada67, sehingga66 sendiri belum mengubah mode bahasa pada wizard. Test29 pada66 dan kode importer tetap sama; hanya petunjuk manual/file impor dipindah ke lokasi tetap yang diminta pengguna.

## 9. Tahap 6 —salin 7 file setelah test/manual berhasil

Pada laptop:

```bash
bash <<'BASH'
set -euo pipefail
task_source=/home/xdranel/Code/Project/php-laravel/dadu
task_target=/home/xdranel/Code/Project/secret/dadu-test
task_migrations=("$task_source"/database/migrations/*_create_student_academic_profiles_table.php)
test "${#task_migrations[@]}" -eq 1
test -f "${task_migrations[0]}"
task_migration=${task_migrations[0]}
task_files=(
  app/Models/StudentAcademicProfile.php
  app/Services/AcademicProfileJsonImportService.php
  app/Support/AcademicProfileJsonNormalizer.php
  app/Services/StudentAcademicProfileImportService.php
  app/Http/Controllers/SyncController.php
  tests/Feature/AcademicProfileJsonImportTest.php
  "${task_migration#"$task_source"/}"
)
for task_file in "${task_files[@]}"; do
  test -f "$task_source/$task_file"
done
for task_file in "${task_files[@]}"; do
  mkdir -p "$(dirname "$task_target/$task_file")"
  cp -- "$task_source/$task_file" "$task_target/$task_file"
  cmp -- "$task_source/$task_file" "$task_target/$task_file"
done
BASH
```

Timestamp migration dipertahankan, bukan dibuat ulang di dadu-test. Jika clone dadu-test memakai database development yang sama, migration sudah tercatat. Jika databasenya terpisah, jalankan `php artisan migrate` memakai konfigurasi lokal clone tersebut juga. Tidak memakai fresh/seed.

Laporkan hasil test/manual dan file tersalin. Asisten akan rescan sebelum checkpoint pembacaan syarat TA/KRS/snapshot berikutnya. JSON nyata tetap berada di Downloads; tidak ditambahkan ke repo publik. Tidak ada command commit/push/deploy pada tahap ini.

## 10. Tahap 7–8 berikutnya

- **Tahap 7, checkpoint berikutnya:** gunakan profil resmi sebagai sumber AdmissionFacts/policy; count langsung dari JSON, nilai null ECCT+3 → Surat Permakluman, SKPI strict, KRS TUGAS AKHIR/TUGAS AKHIR 2 sesuai periode. Perbarui fixture/test lama bersama perubahan pembaca agar tidak ada fallback yang meloloskan data resmi yang sebenarnya kosong.
- **Tahap 8:** selaraskan panel, kartu otomatis, snapshot historis/detail/export dan pembaca data kampus terkait; periksa BPP menggunakan periode impor yang sesuai, lalu uji kirim/revisi dan staging Docker. Lima syarat akademik tetap diperiksa ulang server saat kirim.

## 11. Verifikasi asisten dan batasnya

Source/deployment kedua repo discan sebelum/sesudah setiap penulisan Markdown dan tetap tidak berubah. Class/test/controller hasil gabungan di `/tmp` lulus `php -l`; semua Bash block panduan diperiksa `bash -n` tanpa dieksekusi.

Probe service aktual yang ada pada snippet dengan ORM/transaksi sementara di memori: 27 pemeriksaan lulus, termasuk rollback, sesudah commit, pembaruan parsial, ketepatan jumlah dan konteks BPP. Probe memetakan 260 baris nyata di memori dengan 101 warning TAK yang memang ada pada sumber dan keenam tanggal sidang terbaca. Tidak ada data nyata ditulis ke database atau repo.

Migration dikompilasi menjadi SQL dengan MySQL grammar vendor Laravel tanpa koneksi DB; nullable JSON, unique NIM, foreign key cascade diverifikasi. Model JSON cast diverifikasi dengan Eloquent aktual tanpa query. Ini tidak menggantikan test MySQL 29 kasus yang harus dijalankan pengguna; asisten tidak menjalankan migration/Feature DB/browser/SSH.

Referensi: [Laravel migrations](https://laravel.com/docs/13.x/migrations), [JSON casts](https://laravel.com/docs/13.x/eloquent-mutators#array-and-json-casting), [transactions](https://laravel.com/docs/13.x/database#database-transactions), dan [pessimistic locking](https://laravel.com/docs/13.x/queries#pessimistic-locking), diperiksa melalui Context7.


## 12. Koreksi fixture mahasiswa kedua —6 November2026

Laporan pengguna: `json66_import_never_changes_another_students_profile` gagal saat membuat mahasiswa kedua, dengan SQL1364 `study_program` tidak mempunyai default. Ini kekurangan fixture pada panduan ini. Proses importer dan assertion isolasi mahasiswa belum sempat dijalankan dalam test tersebut.

Schema `students.study_program` wajib/non-null tanpa default. Fixture mahasiswa utama pada beforeEach sudah mengisinya; mahasiswa pembanding belum. Email sudah dibuat nullable oleh migration berikutnya, sehingga tidak perlu mengubah schema/email/factory.

Snippet lengkap pada tahap1 di atas sudah dikoreksi. Jika sudah menyalinnya ke repo development, ganti **seluruh test bernama** `json66_import_never_changes_another_students_profile` dengan kode berikut. Test lain dipertahankan.

```php
test('json66_import_never_changes_another_students_profile', function () {
    $other = Student::create(['student_number' => '1202266002', 'name' => 'Mahasiswa Lain Sintetis',
        'academic_year' => 2022, 'study_program' => 'Informatika']);
    $profile = StudentAcademicProfile::create(['student_number' => $other->student_number,
        'groups' => ['tak' => ['score' => 99]], 'bpp_periods' => []]);
    ($this->import66)(academicJson66Row());
    expect($profile->fresh()->groups)->toEqual(['tak' => ['score' => 99]]);
});
```

Jalankan test gagal itu dahulu:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php --filter=json66_import_never_changes_another_students_profile
```

Target1passed. Lalu ulang file lengkap:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/AcademicProfileJsonImportTest.php
```

Target29passed. Setelah itu ulang command regresi TA pada tahap4 yang sebelumnya kamu jalankan. Tidak perlu make:test ulang, migration baru, migrate:fresh, seed, atau perubahan importer. Setelah unit/manual lulus, gunakan command salin7file tahap6 yang sudah tersedia; file test yang disalin harus yang sudah dikoreksi.

Rescan seluruh fixture pada file ini menemukan dua pemanggilan Student::create: fixture utama sudah lengkap, fixture pembanding diperbaiki di sini. Assertion bahwa profil mahasiswa lain tetap sama dipertahankan. PHP snippet dikoreksi lulus lint; asisten tidak menjalankan FeatureDB dan belum mengklaim hasil rerun pengguna.
