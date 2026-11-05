# 67 — Fakta resmi, blocking pengiriman, dan JSON akun demo

> Kerjakan bagian 1–7 pada checkpoint ini. Asisten hanya menulis dokumentasi dan empat JSON dummy yang diminta; source aplikasi kedua repo tetap dibaca saja. Pengguna menerapkan kode, menjalankan test, lalu menyalinnya. Tidak ada operasi SSH/database/deploy oleh asisten.

**Goal:** memakai profil hasil impor untuk lima syarat akademik dan keputusan bahasa saat kirim TA, serta menyediakan berkas impor tetap untuk akun demo lokal/Docker.

**Architecture:** AdmissionFacts selalu membaca ulang Student dan StudentAcademicProfile. Jika profil impor tersedia, semua fakta kelayakan berasal dari profil itu; field/grup kosong tidak memakai fallback manual. Profil lama tanpa record impor tetap dapat dibaca melalui kompatibilitas fixture nested. Policy memakai skor resmi dan jumlah tes resmi, termasuk nilai null dengan jumlah minimal 3. KRS tetap dari tabel hasil impor terpisah pada satu periode akademik aktif.

**Tech Stack:** PHP sesuai proyek `^8.4.27`, Laravel 13, Pest 4, MySQL Podman. Tidak ada dependency/migration baru, perubahan `.env`/`phpunit.xml`, atau seed ulang akun.

**Spec:** [65 keputusan JSON](65-kontrak-json-resmi-dan-normalizer-profil-akademik.md), [66 penyimpanan impor](66-integrasi-impor-json-profil-akademik.md). Pengguna melaporkan seluruh test lulus setelah koreksi fixture dan 7 file sudah tersalin. Rescan memastikan 7 file 66 dan seluruh 509 file source aplikasi yang dibandingkan identik di kedua folder.

## 1. Mengapa query Tinker belum menemukan profil?

`ModelNotFoundException` untuk StudentAcademicProfile berarti tidak ada record NIM itu pada koneksi database yang sedang dibuka. Table/class sudah bisa diakses; pesan ini tidak berarti class/migration harus dibuat ulang.

- Migration membuat tabel; impor **Profil Akademik** membuat isi tabel.
- Test memakai dadu_testing dan RefreshDatabase. Data selama test tidak otomatis menjadi data database aplikasi lokal atau Docker.
- Mengimpor tipe Mahasiswa saja mengisi Student, bukan StudentAcademicProfile.
- Impor Profil Akademik dapat melewati mahasiswa jika NIM/nama tidak cocok. Database Tinker juga dapat berbeda dengan aplikasi yang tadi diimpor.

Mulai dengan pemeriksaan baca saja di Tinker aplikasi yang ingin kamu uji:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan tinker
```

```php
dump(['database' => Illuminate\Support\Facades\DB::connection()->getDatabaseName(), 'student' => App\Models\Student::where('student_number', '1202299002')->first(['student_number', 'name']), 'profile_exists' => App\Models\StudentAcademicProfile::where('student_number', '1202299002')->exists()]);
```

```php
dump(App\Models\SyncLog::where('module', 'academic_profile')->latest('id')->first(['status', 'processed_records', 'skipped_records', 'error_message']));
```

Gunakan database aplikasi, umumnya dadu untuk setup ini, bukan dadu_testing. Bila profile_exists=false, lanjut impor berkas di bagian 2. Jangan mengubah query menjadi create paksa atau menjalankan migrate:fresh untuk mengisi record.

## 2. Berkas tetap untuk tiga akun Docker

Empat file berikut **sudah dibuat langsung di dadu-test**, bukan /tmp. Semua isinya sintetis:

| Berkas | Pilihan tipe pada Sinkronisasi Data |
| --- | --- |
| [67-01-mahasiswa-akun-demo.json](fixtures/sidang-ta/67-01-mahasiswa-akun-demo.json) | Mahasiswa |
| [67-02-krs-akun-demo.json](fixtures/sidang-ta/67-02-krs-akun-demo.json) | KRS |
| [67-03-profil-akademik-akun-demo.json](fixtures/sidang-ta/67-03-profil-akademik-akun-demo.json) | Profil Akademik |
| [67-04-profil-ecct-dummy002.json](fixtures/sidang-ta/67-04-profil-ecct-dummy002.json) | Profil Akademik, opsional skenario ECCT |

Lokasi laptop:

```text
/home/xdranel/Code/Project/secret/dadu-test/docs/fixtures/sidang-ta/
```

Tiga akun mengikuti setup Docker kita:

| Akun login | Fungsi | Data mahasiswa |
| --- | --- | --- |
| superadmin@example.test | Petugas pengimpor melalui IT Panel | Tidak dibuatkan mahasiswa/NIM |
| krisna@mahasiswa.test | Mahasiswa/Petugas | NIM 1202200001, Krisna Mahasiswa |
| dummy-ta-2@mahasiswa.test | Mahasiswa | NIM 1202299002, Mahasiswa Dummy TA 2 |

Jadi ada **tiga akun login dan dua profil mahasiswa**. JSON tidak membuat User, mengganti password, memberi role, atau mengubah keputusan pengajuan TA. Akun yang diperlukan sudah disiapkan sebelumnya. File Mahasiswa memperbarui nama data Student dummy supaya nama pada JSON Profil Akademik cocok di lokal dan Docker; nama User login dan snapshot pengajuan final tidak ditulis ulang.

### 2.1 Pastikan periode akademik untuk KRS

File KRS memakai 2026/2027 GANJIL. Periksa dahulu:

```php
dump(App\Models\AcademicPeriod::where('is_active', true)->get(['id', 'year', 'semester', 'is_active'])->toArray());
```

Harus ada tepat satu periode aktif 2026/2027 semester 1. Jika sudah sesuai, tidak mengubah apa pun. Jika belum ada periode aktif sama sekali, kamu boleh menjalankan satu statement penjagaan ini untuk data demo:

```php
$period = Illuminate\Support\Facades\DB::transaction(function () { if (App\Models\AcademicPeriod::where('is_active', true)->exists()) throw new RuntimeException('Sudah ada periode aktif; periksa dahulu.'); return App\Models\AcademicPeriod::updateOrCreate(['year' => '2026/2027', 'semester' => 1], ['label' => 'DUMMY Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]); });
```

Jika periode aktif berbeda atau lebih dari satu, jangan menjalankan statement itu untuk menimpanya. Selaraskan pengaturan periode lewat alur aplikasi yang ada. Bila perlu memakai tahun/term lain untuk manual, buat salinan JSON di subfolder `docs/fixtures/sidang-ta/local/`, lalu sesuaikan tahun/term KRS dan kode periode BPP secara konsisten. Tidak menyimpan berkas impor pengguna di /tmp.

Periode akademik KRS berbeda dengan periode **pendaftaran Sidang TA**. Untuk mencoba wizard/pengiriman, petugas juga perlu memastikan ada periode Sidang TA yang terbuka melalui Kelola Periode.

### 2.2 Urutan impor melalui browser

1. Login superadmin pada aplikasi lokal yang DB-nya tadi diperiksa.
2. Buka IT Panel → Sinkronisasi Data.
3. Impor file 67-01 dengan tipe Mahasiswa. Ini memastikan NIM/nama/prodi dummy tersedia.
4. Impor file 67-02 dengan tipe KRS, setelah periode akademik sesuai.
5. Impor file 67-03 dengan tipe Profil Akademik. Harus ada 2 baris diproses dan tidak ada mismatch nama.
6. Periksa SyncLog lalu query profil lagi. Baru setelah itu gunakan firstOrFail.

```php
dump(App\Models\StudentAcademicProfile::where('student_number', '1202299002')->firstOrFail()->only(['student_number', 'groups', 'bpp_periods']));
```

Hasil berkas dasar:

| Mahasiswa | Skor/jumlah tes | Lima syarat akademik | Mode setelah kode 67 diterapkan |
| --- | --- | --- | --- |
| Krisna | 470/1 | Memenuhi; KRS TUGAS AKHIR | automatic |
| Dummy 002 |449/3 | Memenuhi; KRS TUGAS AKHIR2 | waiver/Surat Permakluman |

Keduanya mempunyai SKPI Disetujui, tingkat 2 Y, SKS 130, dan TAK minimal 60. BPP sintetis LUNAS dengan konteks eksplisit 2627/1. Ini data uji yang disengaja, bukan periode yang disimpulkan dari file nyata sebelumnya.

File67-04 hanya memperbarui menu bahasa Dummy 002: score=null, jenis Online ECCT, jumlah 3. Setelah kode 67 diterapkan hasilnya tetap waiver; null tidak menjadi 0. Impor file 67-03 lagi untuk memulihkan kedua akun ke data dasar 449/470.

Sebelum kode 67 diterapkan, record profil sudah bisa terbentuk dengan importer 66, tetapi pembaca syarat lama belum memakai ringkasan itu. Jadi perbaikan record Tinker dan perubahan perilaku wizard adalah dua pemeriksaan berbeda.

### 2.3 Menyalin JSON ke folder development asli

Opsional, karena browser juga bisa memilih langsung file dari dadu-test. Jalankan sendiri pada laptop:

```bash
bash <<'BASH'
set -euo pipefail
task_source=/home/xdranel/Code/Project/secret/dadu-test/docs/fixtures/sidang-ta
task_target=/home/xdranel/Code/Project/php-laravel/dadu/docs/fixtures/sidang-ta
task_files=(
  67-01-mahasiswa-akun-demo.json
  67-02-krs-akun-demo.json
  67-03-profil-akademik-akun-demo.json
  67-04-profil-ecct-dummy002.json
)
for task_file in "${task_files[@]}"; do test -f "$task_source/$task_file"; done
mkdir -p "$task_target"
for task_file in "${task_files[@]}"; do
  cp -- "$task_source/$task_file" "$task_target/$task_file"
  cmp -- "$task_source/$task_file" "$task_target/$task_file"
done
BASH
```

### 2.4 Menggunakan berkas yang sama untuk Docker

Buka situs Docker dari browser laptop, login superadmin, dan upload file yang sama dari laptop sesuai urutan 2.2. Tidak perlu memasukkan berkas ke image/container: folder docs memang tidak dibundel ke image, tetapi upload browser tetap bekerja.

Docker harus sudah memakai kode/migration66 agar record StudentAcademicProfile terbentuk; kode 67 diperlukan untuk perilaku syarat terbaru. Jika server belum diperbarui, lakukan update sesuai 64 ketika rangkaian kode sudah siap. Jangan seed/reset akun atau mengimpor ke dadu_testing pada server. Tidak perlu commit JSON manual jika kamu ingin menyimpannya lokal; test baru di bawah memakai payload sintetis mandiri dan tidak bergantung pada file manual yang tidak kamu commit.

## 3. Cakupan kode 67 dan aturan pembacaan

Tujuh file aplikasi/test:

- `app/Services/SidangTaAdmissionFacts.php`
- `app/Support/SidangTaAdmissionPolicy.php`
- `app/Services/SidangTaAcademicSnapshot.php` —hanya method ta2KrsStatus
- `tests/Feature/SidangTaOfficialAdmissionTest.php` —baru, 24 test
- `tests/Feature/SidangTaAdmissionFactsTest.php` —ganti satu test
- `tests/Feature/SidangTaWizardUiTest.php` —ganti satu test
- `tests/Unit/SidangTaAdmissionPolicyTest.php` —11 test lengkap, sebelumnya 10

Aturan pokok:

- Jika record profil impor ada, lima syarat memakai groups.academic/skpi/tak serta KRS DB. Grup belum pernah diimpor →unknown dan tidak meloloskan kirim. Jangan meminjam nilai manual lama, sekalipun lebih tinggi.
- Impor sebagian setelah impor lengkap tetap mempertahankan grup lama karena service 66 sudah melakukan merge. Error sumber juga mempertahankan grup terakhir yang berhasil. Ini berbeda dengan grup yang belum pernah tersedia.
- SKPI hanya status Disetujui. Boolean cached approved tidak menjadi acuan jika bertentangan.
- Bahasa memakai score dan attempts dari ringkasan, bukan jumlah history/nomor tes/tanggal/kategori. History tetap tersimpan tanpa dihitung ulang. Ringkasan malformed/incomplete menghasilkan unknown.
- Score≥450 →automatic. Selain itu, count ≥3 →waiver, termasuk score null ECCT. Count0–2 →retest. Count belum tersedia →unknown. Nilai invalid seperti boolean/negatif bukan null sah dan tidak membuka waiver.
- Lima syarat akademik tetap harus memenuhi semuanya. Persetujuan sidang dan BPP bukan tambahan syarat pemblokir.
- KRS menerima whole normalized name TUGAS AKHIR/TUGAS AKHIR2 dan alias TA2 yang sebelumnya diterima. Tidak menerima PROPOSAL/TUGAS AKHIR 1/TUGAS AKHIR 20. Link ke satu periode akademik aktif tetap wajib.
- Finalizer yang ada sudah memanggil AdmissionFacts kembali saat kirim dan menyimpan hasilnya dalam snapshot. Tidak perlu mengubah finalizer untuk memasang pembaca ini. Snapshot final/revisi historis tidak dihitung ulang dari data live.

Panel persetujuan sidang/BPP, label bahasa, dan pembaca data kampus yang dipakai modul lain diselaraskan pada checkpoint berikutnya. Halaman TA mulai mengikuti blocking/mode bahasa baru melalui AdmissionFacts yang sudah dipakai wizard/finalizer, tetapi kartu persetujuan lama masih placeholder sampai penyesuaian tampilan berikutnya. Jangan klaim seluruh tampilan sudah selesai pada 67.

## 4. Test dahulu

Pada repo development asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test SidangTaOfficialAdmissionTest --pest
```

Isi seluruh `tests/Feature/SidangTaOfficialAdmissionTest.php`:

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentCourse;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use App\Services\StudentAcademicProfileImportService;
use App\Support\SidangTaMeetRequirements as Meet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function officialAdmission67Row(array $overrides = []): array
{
    return array_replace([
        'nim' => '1202267001', 'nama' => 'Mahasiswa Sintetis Admission 67',
        'sidang_tingkat_2' => 'Y', 'sks_lulus' => '130', 'ipk' => '3.50',
        'status_skpi' => 'Disetujui', 'persetujuan_maju_sidang' => 'APPROVED',
        'total_tak' => '60', 'nilai_bahasa' => '470', 'jenis_tes_bahasa' => 'Online EPrT',
        'jumlah_tes_bahasa' => 1,
        'riwayat_nilai_bahasa' => [['jenis_tes' => 'Online EPrT', 'nilai' => '470',
            'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']],
    ], $overrides);
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-06 10:00:00', config('app.timezone')));
    Storage::fake('local');
    $this->user = User::factory()->create(['username' => '1202267001', 'email' => 'official67@test.local',
        'status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    foreach (['sync_execute', 'sidang_ta_registration_read', 'sidang_ta_registration_create', 'sidang_ta_registration_update'] as $name) {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user);
    $this->student = Student::create(['student_number' => $this->user->username, 'name' => 'Mahasiswa Sintetis Admission 67',
        'email' => $this->user->email, 'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->academicPeriod = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Official 67',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'OFFICIAL67', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'O67', 'semester' => 8, 'credits' => 4]);
    $this->krs = StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'OFFICIAL67',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'O67', 'academic_year' => '2026/2027',
        'semester' => 8, 'academic_period_id' => $this->academicPeriod->id]);
    $this->period = SidangTaPeriod::create(['name' => 'Official JSON 67', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDays(3)]);
    $this->read67 = fn () => app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->profile67 = fn () => StudentAcademicProfile::where('student_number', $this->student->student_number)->firstOrFail();
    $this->import67 = function (array $overrides = [], ?array $menus = null) {
        $result = app(StudentAcademicProfileImportService::class)->import([officialAdmission67Row($overrides)], $menus);
        expect($result['created'] + $result['updated'])->toBe(1);
        expect($result['skipped'])->toBe(0);
        return $result;
    };
    $this->finish67 = function (string $mode = 'automatic') {
        $id = $this->postJson(route('sidang-ta-drafts.store'), ['sidang_ta_period_id' => $this->period->id,
            'student_phone' => '081234567890', 'parent_phone' => '081234567891'])->assertCreated()->json('data.id');
        $draft = SidangTaRegistrationDraft::findOrFail($id);
        foreach ([2, 3, 4] as $step) {
            $files = [];
            foreach (Meet::typesForStep($step, true, $mode) as $type) {
                $files[$type] = UploadedFile::fake()->create($type.'.pdf', 100, 'application/pdf');
            }
            $extra = match ($step) {
                3 => ['graduation_payment_date' => today()->toDateString()],
                4 => ['diploma_biodata_accurate' => true], default => [],
            };
            $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $draft, 'step' => $step]),
                array_replace(['version' => $draft->fresh()->version, 'documents' => $files], $extra))->assertOk();
        }
        $this->postJson(route('sidang-ta-drafts.review', $draft), ['version' => $draft->fresh()->version])->assertOk();
        return $draft->fresh();
    };
    $this->submit67 = fn ($draft, array $extra = []) => $this->postJson(route('sidang-ta-registrations.store'),
        array_replace(['draft_id' => $draft->id, 'version' => $draft->fresh()->version, 'action' => 'submit'], $extra));
});

afterEach(function () { $this->travelBack(); });

test('official67_full_import_supplies_five_gates_and_authoritative_language_summary', function () {
    $result = ($this->import67)();
    expect($result['errors'])->toBe([]);
    $facts = ($this->read67)();
    expect($facts['academic']['academic_passed'])->toBeTrue();
    expect(count($facts['academic']['gates']))->toBe(5);
    expect((float) $facts['eprt_facts']['score'])->toBe(470.0);
    expect($facts['eprt_facts']['confirmed_attempts'])->toBe(1);
    expect($facts['eprt']['mode'])->toBe('automatic');
});

test('official67_uses_official_count_even_when_history_has_one_or_no_entries', function () {
    foreach ([[], [['jenis_tes' => 'Online EPrT', 'nilai' => '440', 'status_berlaku' => 'Berlaku']]] as $history) {
        ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => $history]);
        expect(($this->read67)()['eprt_facts']['confirmed_attempts'])->toBe(3);
        expect(($this->read67)()['eprt']['mode'])->toBe('waiver');
    }
});

test('official67_ecct_null_score_with_three_tests_requires_waiver_without_fabricating_zero', function () {
    ($this->import67)(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3]);
    $facts = ($this->read67)();
    expect($facts['eprt_facts']['score'])->toBeNull();
    expect($facts['eprt_facts']['type'])->toBe('Online ECCT');
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect($facts['eprt']['requires_waiver'])->toBeTrue();
});

test('official67_below_threshold_or_null_before_three_tests_requires_retest', function () {
    foreach ([null, '440'] as $score) {
        ($this->import67)(['nilai_bahasa' => $score, 'jumlah_tes_bahasa' => 2]);
        expect(($this->read67)()['eprt']['mode'])->toBe('retest');
    }
});

test('official67_qualifying_score_needs_no_count_and_unknown_count_cannot_create_waiver', function () {
    ($this->import67)(['nilai_bahasa' => '450', 'jumlah_tes_bahasa' => null]);
    expect(($this->read67)()['eprt']['mode'])->toBe('automatic');
    ($this->import67)(['nilai_bahasa' => null, 'jumlah_tes_bahasa' => null]);
    expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
});

test('official67_canonical_language_does_not_borrow_high_manual_scores', function () {
    StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 650]);
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2]);
    expect(($this->read67)()['eprt']['mode'])->toBe('retest');
    expect((float) ($this->read67)()['eprt_facts']['score'])->toBe(440.0);
    ($this->import67)(['nilai_bahasa' => null, 'jumlah_tes_bahasa' => 3]);
    expect(($this->read67)()['eprt']['mode'])->toBe('waiver');
});

test('official67_status_skpi_is_the_only_approval_authority_even_when_cached_flags_conflict', function () {
    ($this->import67)();
    foreach (['Belum Disetujui', 'APPROVED', 'disetujui', null] as $status) {
        $profile = ($this->profile67)();
        $groups = $profile->groups;
        $groups['skpi'] = ['status' => $status, 'approved' => true];
        $profile->update(['groups' => $groups]);
        expect(($this->read67)()['facts']['skpi_approved'])->toBeFalse();
    }
});

test('official67_first_partial_profile_does_not_fallback_to_unimported_manual_gate_values', function () {
    $nim = $this->student->student_number;
    $this->student->update(['total_credits' => 150]);
    StudentSidangTingkat::create(['student_number' => $nim, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $nim, 'raw_data' => ['status' => 'approved']]);
    StudentEprt::create(['student_number' => $nim, 'total_score' => 550]);
    ($this->import67)([], ['tak']);
    $facts = ($this->read67)();
    expect(array_keys($facts['academic']['blockers']))->toBe(['level_2_passed', 'skpi_approved', 'passed_credits']);
    expect($facts['eprt']['mode'])->toBe('unknown');
});

test('official67_explicit_null_credits_do_not_fallback_to_student_or_course_issue_values', function () {
    ($this->import67)(['sks_lulus' => null]);
    $this->student->update(['total_credits' => 150]);
    StudentCourseIssue::where('student_number', $this->student->student_number)->update(['credits_passed' => 150]);
    expect(($this->read67)()['facts']['passed_credits'])->toBeNull();
    expect(($this->read67)()['academic']['gates']['passed_credits']['state'])->toBe('unknown');
});

test('official67_current_tak_below_threshold_is_not_replaced_by_an_older_higher_score', function () {
    StudentTak::create(['student_number' => $this->student->student_number, 'total_points' => 100, 'test_date' => today()->subDay()]);
    ($this->import67)(['total_tak' => '59']);
    expect((float) ($this->read67)()['facts']['tak_score'])->toBe(59.0);
    expect(($this->read67)()['academic']['gates']['tak_score']['state'])->toBe('failed');
});

test('official67_reads_fresh_profile_after_reimport_even_if_student_relations_were_loaded', function () {
    ($this->import67)();
    $this->student->load(['eprts', 'tak', 'skpi', 'sidangTingkat', 'courseIssues']);
    expect(($this->read67)()['eprt']['mode'])->toBe('automatic');
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2]);
    expect(($this->read67)()['eprt']['mode'])->toBe('retest');
});

test('official67_does_not_borrow_another_students_canonical_profile', function () {
    $other = Student::create(['student_number' => '1202267002', 'name' => 'Mahasiswa Lain 67',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif']);
    StudentAcademicProfile::create(['student_number' => $other->student_number, 'groups' => [
        'academic' => ['level_2_passed' => true, 'passed_credits' => 150],
        'skpi' => ['status' => 'Disetujui', 'approved' => true], 'tak' => ['score' => 100],
        'language' => ['score' => 550, 'attempts' => 1, 'type' => 'Online EPrT', 'history' => []],
    ]]);
    expect(count(($this->read67)()['academic']['blockers']))->toBe(4);
    expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
});

test('official67_reimport_error_preserves_successful_old_group_and_updates_other_groups', function () {
    ($this->import67)();
    ($this->import67)(['total_tak' => null, 'status_skpi' => 'Belum Disetujui', 'error' => 'tak: source failed']);
    expect((float) ($this->read67)()['facts']['tak_score'])->toBe(60.0);
    expect(($this->read67)()['facts']['skpi_approved'])->toBeFalse();
});

test('official67_malformed_or_incomplete_language_group_fails_closed_without_waiver', function () {
    ($this->import67)();
    $base = ($this->profile67)()->groups;
    foreach ([['score' => null, 'attempts' => 3],
        ['score' => -1, 'attempts' => 3, 'type' => null, 'history' => []],
        ['score' => true, 'attempts' => 3, 'type' => null, 'history' => []],
        ['score' => null, 'attempts' => 3.5, 'type' => null, 'history' => []],
        ['score' => null, 'attempts' => 3, 'type' => null, 'history' => [123]],
    ] as $language) {
        ($this->profile67)()->update(['groups' => array_replace($base, ['language' => $language])]);
        expect(($this->read67)()['eprt']['mode'])->toBe('unknown');
    }
});

test('official67_canonical_string_boolean_is_unknown_instead_of_truthy_pass', function () {
    ($this->import67)();
    $profile = ($this->profile67)();
    $groups = $profile->groups;
    $groups['academic']['level_2_passed'] = 'Y';
    $profile->update(['groups' => $groups]);
    expect(($this->read67)()['academic']['gates']['level_2_passed']['state'])->toBe('unknown');
});

test('official67_accepts_whole_krs_names_and_rejects_proposal_ta1_and_ta20', function () {
    ($this->import67)();
    foreach (['TUGAS AKHIR', 'TUGAS AKHIR 2', '  tugas  akhir  '] as $name) {
        $this->krs->update(['course_name' => $name]);
        expect(($this->read67)()['academic']['academic_passed'])->toBeTrue();
    }
    foreach (['TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR', 'TUGAS AKHIR LANJUTAN'] as $name) {
        $this->krs->update(['course_name' => $name]);
        expect(($this->read67)()['academic']['academic_passed'])->toBeFalse();
    }
});

test('official67_krs_still_requires_link_to_exactly_one_active_academic_period', function () {
    ($this->import67)();
    $other = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 2, 'label' => 'Other 67',
        'start_date' => '2027-02-01', 'end_date' => '2027-07-31', 'is_active' => false]);
    foreach ([$other->id, null] as $id) {
        $this->krs->update(['academic_period_id' => $id]);
        expect(($this->read67)()['facts']['ta2_registered'])->toBeNull();
    }
    $this->krs->update(['academic_period_id' => $this->academicPeriod->id]);
    $other->update(['is_active' => true]);
    expect(($this->read67)()['krs_status'])->toBe('unknown');
});

test('official67_each_single_failed_academic_requirement_still_blocks_registration', function () {
    foreach ([['sidang_tingkat_2' => 'N'], ['status_skpi' => 'Belum Disetujui'],
        ['total_tak' => '59'], ['sks_lulus' => '119']] as $overrides) {
        ($this->import67)($overrides);
        expect(count(($this->read67)()['academic']['blockers']))->toBe(1);
    }
    ($this->import67)();
    $this->krs->update(['course_name' => 'TUGAS AKHIR 1']);
    expect(array_keys(($this->read67)()['academic']['blockers']))->toBe(['ta2_registered']);
});

test('official67_automatic_submission_saves_official_score_and_seven_documents', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(7);
    expect((float) $registration->academic_snapshot['eprt_score'])->toBe(470.0);
    expect($registration->academic_snapshot['admission']['eprt_facts']['confirmed_attempts'])->toBe(1);
});

test('official67_ecct_null_score_submits_with_waiver_and_eight_documents', function () {
    StudentEprt::create(['student_number' => $this->student->student_number, 'total_score' => 650]);
    ($this->import67)(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3]);
    $draft = ($this->finish67)('waiver');
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    expect($registration->documents()->count())->toBe(8);
    expect($registration->documents()->where('document_type', Meet::WAIVER)->exists())->toBeTrue();
    expect($registration->academic_snapshot['eprt_score'])->toBeNull();
    expect($registration->academic_snapshot['admission']['eprt_facts']['type'])->toBe('Online ECCT');
});

test('official67_submit_rechecks_new_skpi_and_keeps_draft_files_when_blocked', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    ($this->import67)(['status_skpi' => 'Belum Disetujui']);
    ($this->submit67)($draft, ['skpi_approved' => true])->assertUnprocessable()->assertJsonValidationErrors('eligibility.skpi_approved');
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(7);
    expect(count(Storage::disk('local')->allFiles()))->toBe(7);
    expect(SidangTaRegistration::where('student_number', $this->student->student_number)->count())->toBe(0);
});

test('official67_submit_rechecks_official_count_after_review_without_consuming_draft', function () {
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3]);
    $draft = ($this->finish67)('waiver');
    ($this->import67)(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 2]);
    ($this->submit67)($draft)->assertUnprocessable()->assertJsonValidationErrors('eligibility.eprt');
    expect($draft->fresh()->status)->toBe('active');
    expect($draft->documents()->count())->toBe(8);
});

test('official67_later_import_does_not_change_existing_final_academic_snapshot', function () {
    ($this->import67)();
    $draft = ($this->finish67)();
    $id = ($this->submit67)($draft)->assertOk()->json('data.id');
    $registration = SidangTaRegistration::findOrFail($id);
    $before = $registration->academic_snapshot;
    ($this->import67)(['status_skpi' => 'Belum Disetujui', 'total_tak' => '0', 'nilai_bahasa' => null, 'jumlah_tes_bahasa' => null]);
    expect($registration->fresh()->academic_snapshot)->toEqual($before);
    expect($registration->fresh()->status)->toBe('submitted');
});

test('official67_demo_payload_imports_two_student_profiles_for_three_login_accounts', function () {
    $usersBefore = User::count();
    $students = $courses = $profiles = [];
    foreach ([['1202200001', 'Krisna Mahasiswa', 'krisna@mahasiswa.test', '470', 1, 'TUGAS AKHIR'],
        ['1202299002', 'Mahasiswa Dummy TA 2', 'dummy-ta-2@mahasiswa.test', '449', 3, 'TUGAS AKHIR 2'],
    ] as [$nim, $name, $email, $score, $count, $courseName]) {
        $students[] = ['nim' => $nim, 'nama_siswa' => $name, 'prodi' => 'Informatika',
            'academic_year' => 2022, 'active_semester' => 8, 'email' => $email, 'kelas' => 'DEMO67'];
        $courses[] = ['nim' => $nim, 'kode_matakuliah' => 'DEMO67-'.$nim,
            'nama_matakuliah' => $courseName, 'kelas_matakuliah' => 'DEMO67', 'sks' => 4,
            'tahun_akademik' => '2026/2027', 'semester_term' => 'GANJIL', 'semester' => 8, 'prodi' => 'Informatika'];
        $profiles[] = officialAdmission67Row(['nim' => $nim, 'nama' => $name, 'nilai_bahasa' => $score,
            'jumlah_tes_bahasa' => $count, 'riwayat_nilai_bahasa' => [],
            'status_keuangan' => 'LUNAS', 'status_keuangan_asli' => 'SUDAH LUNAS', 'semester' => '8']);
    }
    $upload = function (array $payload, string $type) {
        $this->post(route('sync.import-json'), ['type' => $type,
            'json_file' => UploadedFile::fake()->createWithContent('demo67.json', json_encode($payload, JSON_THROW_ON_ERROR)),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    };
    $upload(['data' => $students], 'student');
    $upload(['data' => $courses], 'krs');
    $upload(['selected_menus' => ['akademik', 'bahasa', 'tak', 'skpi', 'maju_sidang', 'keuangan'],
        'finance_period' => '2627/1', 'data' => $profiles], 'academic_profile');
    foreach ([['1202200001', 'automatic'], ['1202299002', 'waiver']] as [$nim, $mode]) {
        $student = Student::where('student_number', $nim)->firstOrFail();
        expect(StudentAcademicProfile::where('student_number', $nim)->exists())->toBeTrue();
        $facts = app(SidangTaAdmissionFacts::class)->forStudent($student);
        expect($facts['academic']['academic_passed'])->toBeTrue();
        expect($facts['eprt']['mode'])->toBe($mode);
    }
    $upload(['selected_menus' => ['bahasa'], 'data' => [[
        'nim' => '1202299002', 'nama' => 'Mahasiswa Dummy TA 2', 'nilai_bahasa' => null,
        'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => [],
    ]]], 'academic_profile');
    $facts = app(SidangTaAdmissionFacts::class)->forStudent(Student::where('student_number', '1202299002')->firstOrFail());
    expect($facts['eprt_facts']['score'])->toBeNull();
    expect($facts['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect(User::count())->toBe($usersBefore);
});
```

Ganti seluruh `tests/Unit/SidangTaAdmissionPolicyTest.php` dengan 11 test berikut. Case lama yang menyebut null+3 → unknown diganti sesuai keputusan terbaru; invalid score/count tetap diperiksa tidak meloloskan.

```php
<?php

use App\Support\SidangTaAdmissionPolicy as Policy;

function meetAdmissionFacts(): array
{
    return ['level_2_passed' => true, 'ta2_registered' => true, 'skpi_approved' => true,
        'tak_score' => 60, 'passed_credits' => 120];
}

test('meet_policy_accepts_exact_five_academic_boundaries', function () {
    $result = Policy::academic(meetAdmissionFacts());
    expect($result['academic_passed'])->toBeTrue();
    expect($result['blockers'])->toBe([]);
    expect(count($result['gates']))->toBe(5);
    foreach ($result['gates'] as $gate) expect($gate['state'])->toBe('passed');
});

test('meet_policy_each_single_failure_blocks_academic_eligibility', function () {
    foreach (['level_2_passed' => false, 'ta2_registered' => false, 'skpi_approved' => false,
                 'tak_score' => 59.99, 'passed_credits' => 119] as $key => $value) {
        $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $value]));
        expect($result['academic_passed'])->toBeFalse();
        expect(array_keys($result['blockers']))->toBe([$key]);
        expect($result['gates'][$key]['state'])->toBe('failed');
    }
});

test('meet_policy_missing_or_unconfirmed_facts_never_pass', function () {
    foreach (array_keys(meetAdmissionFacts()) as $key) {
        foreach ([null, '', [], 'unconfirmed'] as $value) {
            $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $value]));
            expect($result['gates'][$key]['state'])->toBe('unknown');
            expect($result['academic_passed'])->toBeFalse();
            expect(array_keys($result['blockers']))->toBe([$key]);
        }
        $facts = meetAdmissionFacts();
        unset($facts[$key]);
        expect(Policy::academic($facts)['gates'][$key]['state'])->toBe('unknown');
    }
    expect(count(Policy::academic([])['blockers']))->toBe(5);
});

test('meet_policy_requires_normalized_booleans_instead_of_truthy_source_strings', function () {
    foreach (['level_2_passed', 'ta2_registered', 'skpi_approved'] as $key) {
        foreach (['N', 'Y', 'false', 'true', 'approved', 'rejected', 0, 1] as $raw) {
            $result = Policy::academic(array_replace(meetAdmissionFacts(), [$key => $raw]));
            expect($result['gates'][$key]['state'])->toBe('unknown');
        }
    }
});

test('meet_policy_numeric_strings_zero_and_invalid_values_are_distinguished', function () {
    $result = Policy::academic(array_replace(meetAdmissionFacts(), ['tak_score' => '60.00', 'passed_credits' => '120']));
    expect($result['academic_passed'])->toBeTrue();
    foreach (['tak_score', 'passed_credits'] as $key) {
        expect(Policy::academic(array_replace(meetAdmissionFacts(), [$key => 0]))['gates'][$key]['state'])->toBe('failed');
        foreach ([true, false, -1, 'invalid', INF, NAN] as $invalid) {
            expect(Policy::academic(array_replace(meetAdmissionFacts(), [$key => $invalid]))['gates'][$key]['state'])->toBe('unknown');
        }
    }
});

test('meet_policy_eprt_450_or_higher_is_automatic_without_upload_or_attempt_requirement', function () {
    foreach ([450, 500, '450'] as $score) {
        $result = Policy::eprt($score, null);
        expect($result['mode'])->toBe('automatic');
        expect($result['requires_waiver'])->toBeFalse();
    }
});

test('meet_policy_eprt_below_450_after_three_confirmed_tests_requires_waiver', function () {
    foreach ([[449, 3], [400, 4], ['449', '3'], [0, 3]] as [$score, $attempts]) {
        $result = Policy::eprt($score, $attempts);
        expect($result['mode'])->toBe('waiver');
        expect($result['requires_waiver'])->toBeTrue();
    }
});

test('meet_policy_eprt_below_450_before_three_tests_requires_retest', function () {
    foreach ([0, 1, 2] as $attempts) {
        $result = Policy::eprt(449, $attempts);
        expect($result['mode'])->toBe('retest');
        expect($result['requires_waiver'])->toBeFalse();
        expect($result['message'])->toBe('Ikuti Test EPRT lagi');
    }
});

test('meet_policy_eprt_unknown_data_never_activates_waiver_or_automatic_approval', function () {
    foreach ([[null, null], ['invalid', 3], [449, null], [449, 2.5], [true, 3], [-1, 3], [449, -1], [NAN, 3], [449, INF], [[], 3]] as [$score, $attempts]) {
        $result = Policy::eprt($score, $attempts);
        expect($result['mode'])->toBe('unknown');
        expect($result['requires_waiver'])->toBeFalse();
    }
});

test('meet_policy_eprt_is_not_a_sixth_academic_gate_and_waiver_is_not_final_approval', function () {
    $facts = array_replace(meetAdmissionFacts(), ['eprt_score' => 0, 'eprt_attempts' => 0]);
    $academic = Policy::academic($facts);
    expect($academic['academic_passed'])->toBeTrue();
    expect(count($academic['gates']))->toBe(5);
    expect(Policy::eprt(0, 0)['mode'])->toBe('retest');
    expect(Policy::eprt(449, 3)['mode'])->toBe('waiver');
    expect(Policy::eprt(449, 3))->not->toHaveKey('can_submit');
});



test('meet_policy_null_language_score_uses_official_count_without_inventing_zero', function () {
    expect(Policy::eprt(null, 3)['mode'])->toBe('waiver');
    expect(Policy::eprt(null, 3)['requires_waiver'])->toBeTrue();
    expect(Policy::eprt(null, 4)['mode'])->toBe('waiver');
    foreach ([0, 1, 2] as $count) {
        expect(Policy::eprt(null, $count)['mode'])->toBe('retest');
    }
    expect(Policy::eprt(null, null)['mode'])->toBe('unknown');
    expect(Policy::eprt('invalid', 3)['mode'])->toBe('unknown');
});
```

Jalankan sebelum implementasi berikutnya; kegagalan expected karena pembaca/policy lama belum memakai kontrak ini:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaOfficialAdmissionTest.php tests/Unit/SidangTaAdmissionPolicyTest.php --stop-on-failure
```

Jika kamu sudah menerapkan kode dahulu, tidak perlu menghapusnya untuk mengulang kegagalan awal. Tidak menjalankan migrate:fresh/seed atau menambah migration untuk67.

## 5. Implementasi tiga file production

### 5.1 Policy bahasa

Isi seluruh `app/Support/SidangTaAdmissionPolicy.php`:

```php
<?php

namespace App\Support;

class SidangTaAdmissionPolicy
{
    /** Facts must be normalized by the synchronization adapter, never by HTTP input. */
    public static function academic(array $facts): array
    {
        $gates = [];
        foreach (['level_2_passed' => 'Sidang tingkat 2', 'ta2_registered' => 'KRS Sidang TA 2',
                     'skpi_approved' => 'SKPI'] as $key => $label) {
            $value = $facts[$key] ?? null;
            $gates[$key] = ['label' => $label,
                'state' => $value === true ? 'passed' : ($value === false ? 'failed' : 'unknown')];
        }
        foreach (['tak_score' => ['TAK minimal 60', 60], 'passed_credits' => ['SKS lulus minimal 120', 120]] as $key => [$label, $minimum]) {
            $value = self::number($facts[$key] ?? null);
            $gates[$key] = ['label' => $label,
                'state' => $value === null ? 'unknown' : ($value >= $minimum ? 'passed' : 'failed')];
        }
        $blockers = [];
        foreach ($gates as $key => $gate) {
            if ($gate['state'] !== 'passed') {
                $blockers[$key] = $gate['label'].($gate['state'] === 'unknown'
                        ? ': data belum tersedia atau belum terkonfirmasi.' : ': belum memenuhi syarat.');
            }
        }
        return ['gates' => $gates, 'blockers' => $blockers, 'academic_passed' => $blockers === []];
    }

    /** EPRT is separate from the five academic gates; waiver still needs a PDF. */
    public static function eprt(mixed $score, mixed $confirmedAttempts): array
    {
        $numericScore = self::number($score);
        if ($score !== null && $numericScore === null) {
            return ['mode' => 'unknown', 'requires_waiver' => false,
                'message' => 'Data nilai atau riwayat tes EPRT belum lengkap.'];
        }
        if ($numericScore !== null && $numericScore >= 450) {
            return ['mode' => 'automatic', 'requires_waiver' => false,
                'message' => 'Nilai EPRT memenuhi syarat.'];
        }
        $attempts = self::number($confirmedAttempts);
        if ($attempts === null || floor($attempts) !== $attempts) {
            return ['mode' => 'unknown', 'requires_waiver' => false,
                'message' => 'Data nilai atau riwayat tes EPRT belum lengkap.'];
        }
        if ($attempts >= 3) {
            return ['mode' => 'waiver', 'requires_waiver' => true,
                'message' => 'Unggah Surat Permakluman.'];
        }
        return ['mode' => 'retest', 'requires_waiver' => false, 'message' => 'Ikuti Test EPRT lagi'];
    }

    private static function number(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) && $number >= 0 ? $number : null;
    }
}
```

Nama API `eprt` dan key internal dipertahankan agar kontrak wizard/revisi/ekspor tetap tersambung. Nilainya kini dapat berasal dari jenis bahasa lain sesuai keputusan pengguna. Perubahan label tampilan tidak dicampur ke checkpoint ini.

### 5.2 Pembaca fakta resmi

Isi seluruh `app/Services/SidangTaAdmissionFacts.php`:

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicProfile;
use App\Models\StudentEprt;
use App\Support\SidangTaAdmissionPolicy as Policy;

class SidangTaAdmissionFacts
{
    public function __construct(private SidangTaAcademicSnapshot $snapshots) {}

    public function forStudent(Student $student): array
    {
        $student = Student::whereKey($student->id)->firstOrFail();
        $profile = StudentAcademicProfile::where('student_number', $student->student_number)->first();
        $krs = $this->snapshots->ta2KrsStatus($student);

        if ($profile !== null) {
            // Once an official profile exists, missing/null facts never borrow manual values.
            $groups = is_array($profile->groups) ? $profile->groups : [];
            $academic = is_array($groups['academic'] ?? null) ? $groups['academic'] : [];
            $skpi = is_array($groups['skpi'] ?? null) ? $groups['skpi'] : [];
            $tak = is_array($groups['tak'] ?? null) ? $groups['tak'] : [];
            $level = $academic['level_2_passed'] ?? null;
            $facts = [
                'level_2_passed' => is_bool($level) ? $level : null,
                'ta2_registered' => $krs === 'eligible' ? true : null,
                'skpi_approved' => array_key_exists('skpi', $groups) ? ($skpi['status'] ?? null) === 'Disetujui' : null,
                'tak_score' => $this->number($tak['score'] ?? null),
                'passed_credits' => $this->number($academic['passed_credits'] ?? null),
            ];
            $eprt = $this->officialLanguage($groups['language'] ?? null);
        } else {
            // Compatibility for existing nested-import fixtures and pre-import local data.
            $facts = $this->legacyFacts($student, $krs);
            $eprt = $this->legacyEprtFacts($student) + ['type' => null];
        }

        return ['facts' => $facts, 'academic' => Policy::academic($facts),
            'krs_status' => $krs, 'eprt_facts' => $eprt,
            'eprt' => Policy::eprt($eprt['score'], $eprt['confirmed_attempts'])];
    }

    private function officialLanguage(mixed $language): array
    {
        $unknown = ['score' => null, 'confirmed_attempts' => null, 'type' => null];
        if (!is_array($language)) return $unknown;
        foreach (['score', 'attempts', 'type', 'history'] as $key) {
            if (!array_key_exists($key, $language)) return $unknown;
        }
        $score = $this->number($language['score']);
        if ($language['score'] !== null && $score === null) return $unknown;
        $attempts = $language['attempts'];
        if ($attempts !== null && (!is_int($attempts) || $attempts < 0)) return $unknown;
        if ($language['type'] !== null && !is_string($language['type'])) return $unknown;
        if (!is_array($language['history']) || !array_is_list($language['history'])) return $unknown;
        foreach ($language['history'] as $entry) {
            if (!is_array($entry) || array_is_list($entry)) return $unknown;
        }

        // The exporter owns filtering/counting; history length is not a test count.
        return ['score' => $score, 'confirmed_attempts' => $attempts, 'type' => $language['type']];
    }

    private function number(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) return null;
        return (float) $value;
    }

    private function legacyFacts(Student $student, string $krs): array
    {
        $level = $student->sidangTingkat()->first();
        $skpi = $student->skpi()->first();
        $issues = $student->courseIssues()->first();
        $tak = $student->tak()->where(function ($query) {
            $query->whereNull('test_date')->orWhereDate('test_date', '<=', today());
        })->first();
        return [
            'level_2_passed' => $level?->tingkat_2,
            'ta2_registered' => $krs === 'eligible' ? true : null,
            'skpi_approved' => $this->skpiApproval($skpi?->raw_data ?? []),
            'tak_score' => $tak?->total_points,
            'passed_credits' => $issues?->credits_passed ?? $student->total_credits,
        ];
    }

    private function skpiApproval(array $raw): ?bool
    {
        $values = [];
        foreach (['status', 'approval_status', 'is_approved'] as $key) {
            if (!array_key_exists($key, $raw) || $raw[$key] === null) continue;
            $value = $raw[$key];
            if (is_bool($value)) {
                $values[] = $value;
                continue;
            }
            if (!is_string($value) && !is_int($value)) return null;
            $value = strtolower(trim((string) $value));
            if (in_array($value, ['1', 'true', 'approved', 'disetujui', 'lulus'], true)) {
                $values[] = true;
            } elseif (in_array($value, ['0', 'false', 'rejected', 'ditolak', 'pending', 'menunggu'], true)) {
                $values[] = false;
            } else {
                return null;
            }
        }
        if ($values === [] || in_array(true, $values, true) && in_array(false, $values, true)) return null;
        return $values[0];
    }

    private function legacyEprtFacts(Student $student): array
    {
        $rows = StudentEprt::where('student_number', $student->student_number)
            ->where(function ($query) { $query->whereNull('category')->orWhere('category', ''); })
            ->whereNotNull('total_score')->where('total_score', '>=', 0)
            ->where(function ($query) { $query->whereNull('test_date')->orWhereDate('test_date', '<=', today()); })
            ->get();
        $score = $rows->max('total_score');
        $byAttempt = [];
        $byDate = [];
        $complete = $rows->isNotEmpty();
        foreach ($rows as $row) {
            $attempt = $row->attempt_number;
            $date = $row->test_date?->toDateString();
            if (!is_int($attempt) || $attempt < 1 || $date === null) {
                $complete = false;
                continue;
            }
            if ((isset($byAttempt[$attempt]) && $byAttempt[$attempt] !== $date)
                || (isset($byDate[$date]) && $byDate[$date] !== $attempt)) {
                $complete = false;
            }
            $byAttempt[$attempt] = $date;
            $byDate[$date] = $attempt;
        }
        return ['score' => $score, 'confirmed_attempts' => $complete ? count($byAttempt) : null];
    }
}
```

Kompatibilitas legacy hanya berlaku bila record StudentAcademicProfile sama sekali belum ada. Ini menjaga fixture nested yang sekarang dan tidak menjadi fallback per field ketika data resmi kosong. Tidak ada versi persyaratan TA tambahan; semua akun tetap current marker 2 yang sudah digunakan.

### 5.3 KRS

Pada `app/Services/SidangTaAcademicSnapshot.php`, ganti **seluruh method ta2KrsStatus(Student $student)** berikut. Method forStudent dan bagian lain dipertahankan:

```php
    public function ta2KrsStatus(Student $student): string
    {
        $periods = AcademicPeriod::query()->where('is_active', true)->pluck('id');
        if ($periods->count() !== 1) return 'unknown';
        $names = StudentCourse::query()
            ->where('student_number', $student->student_number)
            ->where('academic_period_id', $periods->first())->pluck('course_name');
        if ($names->isEmpty()) return 'unknown';
        foreach ($names as $name) {
            $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
            if (in_array($normalized, ['TUGAS AKHIR', 'TUGAS AKHIR 2', 'TUGAS AKHIR II', 'SIDANG TA 2', 'TA 2', 'TA-2', 'FINAL THESIS 2'], true)) {
                return 'eligible';
            }
        }
        // Match the whole normalized name; proposals/TA1/TA20 are not accepted.
        return 'unconfirmed';
    }
```

## 6. Sesuaikan dua test KRS lama

Di `tests/Feature/SidangTaAdmissionFactsTest.php`, ganti seluruh test bernama `meet_facts_ta2_uses_linked_active_period_and_can_complete_all_five_gates`:

```php
test('meet_facts_ta2_uses_linked_active_period_and_can_complete_all_five_gates', function () {
    AcademicPeriod::query()->update(['is_active' => false]);
    $period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1, 'label' => 'Meet facts',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    Course::create(['course_code' => 'MEET-FACTS', 'name' => 'TUGAS AKHIR 2', 'academic_year' => '2026/2027',
        'semester_term' => 'GANJIL', 'study_program' => 'Informatika', 'class' => 'A', 'semester' => 8, 'credits' => 4]);
    $krs = StudentCourse::create(['student_number' => $this->student->student_number, 'course_code' => 'MEET-FACTS',
        'course_name' => 'TUGAS AKHIR 2', 'course_class' => 'A', 'academic_year' => '2026/2027', 'semester' => 8,
        'academic_period_id' => $period->id]);
    StudentSidangTingkat::create(['student_number' => $this->student->student_number, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $this->student->student_number, 'raw_data' => ['status' => 'approved']]);
    StudentTak::create(['student_number' => $this->student->student_number, 'total_points' => 60]);
    StudentCourseIssue::create(['student_number' => $this->student->student_number, 'credits_passed' => 120]);
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeTrue();
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
    $krs->update(['course_name' => 'TUGAS AKHIR']);
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeTrue();
    $krs->update(['course_name' => 'TUGAS AKHIR 1']);
    expect(($this->readFacts)()['facts']['ta2_registered'])->toBeNull();
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeFalse();
});
```

Di `tests/Feature/SidangTaWizardUiTest.php`, ganti seluruh test lama `meet_krs_requires_one_active_period_and_explicit_ta2_name` dengan test baru berikut. Test lain tetap dipertahankan:

```php
test('meet_krs_requires_one_active_period_and_accepted_ta_names', function () {
    \App\Models\AcademicPeriod::query()->update(['is_active' => false]);
    $service = app(\App\Services\SidangTaAcademicSnapshot::class);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $period = \App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Meet KRS', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $course = \App\Models\Course::create(['course_code' => 'MEET-TA2', 'name' => 'TUGAS AKHIR 2',
        'academic_year' => '2026/2027', 'semester_term' => 'GANJIL', 'study_program' => 'Informatika',
        'class' => 'A', 'semester' => 8, 'credits' => 4, 'type' => 'Wajib', 'status' => 'Aktif']);
    $row = \App\Models\StudentCourse::create(['student_number' => $this->student->student_number,
        'course_code' => $course->course_code, 'course_name' => 'TUGAS AKHIR', 'course_class' => 'A',
        'academic_year' => '2026/2027', 'semester' => 8, 'academic_period_id' => $period->id]);
    foreach (['TUGAS AKHIR 1', 'TUGAS AKHIR 20', 'PROPOSAL TUGAS AKHIR'] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('unconfirmed');
    }
    foreach (['TUGAS AKHIR', '  tugas akhir  2  '] as $name) {
        $row->update(['course_name' => $name]);
        expect($service->ta2KrsStatus($this->student))->toBe('eligible');
    }
    $snapshot = $service->forStudent($this->student->fresh());
    expect($snapshot['ta2_krs_status'])->toBe('eligible');
    expect($snapshot['ta2_krs_label'])->toBe('Terdaftar');
    $row->update(['academic_period_id' => null]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
    $row->update(['academic_period_id' => $period->id]);
    $period->update(['is_active' => false]);
    expect($service->ta2KrsStatus($this->student))->toBe('unknown');
});
```

Assertion penolakan nama tidak relevan tetap ada; hanya TUGAS AKHIR dipindah menjadi nama yang diterima sesuai keputusan terbaru.

## 7. Lint, test, manual, lalu salin

### 7.1 Lint dan unit/feature

```bash
php -l app/Services/SidangTaAdmissionFacts.php
php -l app/Support/SidangTaAdmissionPolicy.php
php -l app/Services/SidangTaAcademicSnapshot.php
php -l tests/Feature/SidangTaOfficialAdmissionTest.php
php -l tests/Feature/SidangTaAdmissionFactsTest.php
php -l tests/Feature/SidangTaWizardUiTest.php
php -l tests/Unit/SidangTaAdmissionPolicyTest.php
```

Fokus baru:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaOfficialAdmissionTest.php tests/Unit/SidangTaAdmissionPolicyTest.php
```

Target **35 passed: 24 feature + 11 unit**. Regresi pembaca/KRS/fixture lama:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaAdmissionFactsTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDummyImportTest.php tests/Feature/AcademicProfileJsonImportTest.php
```

Seluruh TA berikut importer dan unit pembaca:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Feature/AcademicProfileJsonImportTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/AcademicProfileJsonNormalizerTest.php
```

Tidak memaksakan jumlah total jika ada penambahan test lain; hasil harus semua passed. Full suite non-TA masih mempunyai temuan Auth61f yang terpisah. JS/Blade tidak berubah sehingga tidak perlu Node/Vite build pada checkpoint 67.

### 7.2 Verifikasi data lokal sesudah impor

Di Tinker aplikasi lokal, bukan proses phpunit:

```php
$student = App\Models\Student::where('student_number', '1202299002')->firstOrFail();
```

```php
dump(App\Models\StudentAcademicProfile::where('student_number', $student->student_number)->firstOrFail()->only(['student_number', 'groups', 'bpp_periods']));
```

```php
dump(app(App\Services\SidangTaAdmissionFacts::class)->forStudent($student));
```

Setelah file 67-03: academic_passed=true, krs_status=eligible, score449/count3/modewaiver. Setelah file 67-04: score null/count3/typeOnlineECCT/modewaiver; lima syarat tetap lulus. Untuk Krisna, file 67-03 menghasilkan automatic dengan score 470 dan KRS generik diterima.

Jika record masih tidak ada, baca hasil database dan SyncLog pada bagian 1. Pastikan tipe upload **Profil Akademik**, nama Student sudah sesuai file 67-01, kode66 sudah dimuat, dan browser/Tinker memakai database yang sama. Menjalankan unit test lagi tidak membuat record di database aplikasi.

### 7.3 Browser dan pengiriman

1. Pastikan periode Sidang TA terbuka dan tidak ada pengajuan aktif lain yang memang menghalangi pendaftaran baru. Jangan mengubah status final hanya untuk membuka tombol.
2. Login Dummy 002 sebagai mahasiswa setelah impor file 67-03. Lengkapi draft sampai tahap3: Surat Permakluman wajib karena449/jumlah 3.
3. Impor file 67-04 melalui superadmin. Buka ulang draft Dummy 002: Surat Permakluman tetap wajib meskipun nilai numerik kosong. Simpan, tinjau, kirim setelah seluruh upload/scalar lengkap. Unit test membuktikan 8 berkas pada cabang biodata sesuai dan snapshot nilai tetap null.
4. Krisna memakai file 67-03: mode automatic470, tidak memerlukan Surat Permakluman. Jika memakai pengajuan baru dan biodata sesuai, total 7 berkas.
5. Uji penolakan setelah tinjau: buat salinan file 67-03 di `docs/fixtures/sidang-ta/local/`, ubah hanya status_skpi mahasiswa yang sedang diuji menjadi Belum Disetujui, lalu impor sebagai Profil Akademik. Kirim dari tinjauan lama harus ditolak; draft/file tidak dikonsumsi/dihapus. Pulihkan dengan mengimpor file 67-03 lagi.
6. Jika akun sudah memiliki pengajuan final/aktif dari test sebelumnya, cukup uji pembacaan fakta dan draft yang memang dapat dibuka. Tidak melakukan reset seluruh data TA tanpa kebutuhan. Uji pengiriman fresh dari test otomatis tetap mencakup kedua mode.

Perubahan kartu persetujuan/BPP/label bahasa dan konsistensi pembaca modul terkait menyusul. Pendaftaran tetap memerlukan semua dokumen wajib; JSON kelayakan tidak menggantikan upload yang memang masih diwajibkan.

### 7.4 Salin 7 file kode dan 4 JSON sesudah test/manual

Jika JSON belum disalin ke original, jalankan bagian 2.3 dahulu agar salinan tetap tersedia di dua folder. Test tidak bergantung berkas manual tersebut; salinan ini untuk kebutuhan impor browser lokal/Docker.

```bash
bash <<'BASH'
set -euo pipefail
task_source=/home/xdranel/Code/Project/php-laravel/dadu
task_target=/home/xdranel/Code/Project/secret/dadu-test
task_files=(
  app/Services/SidangTaAdmissionFacts.php
  app/Support/SidangTaAdmissionPolicy.php
  app/Services/SidangTaAcademicSnapshot.php
  tests/Feature/SidangTaOfficialAdmissionTest.php
  tests/Feature/SidangTaAdmissionFactsTest.php
  tests/Feature/SidangTaWizardUiTest.php
  tests/Unit/SidangTaAdmissionPolicyTest.php
  docs/fixtures/sidang-ta/67-01-mahasiswa-akun-demo.json
  docs/fixtures/sidang-ta/67-02-krs-akun-demo.json
  docs/fixtures/sidang-ta/67-03-profil-akademik-akun-demo.json
  docs/fixtures/sidang-ta/67-04-profil-ecct-dummy002.json
)
for task_file in "${task_files[@]}"; do test -f "$task_source/$task_file"; done
for task_file in "${task_files[@]}"; do
  mkdir -p "$(dirname "$task_target/$task_file")"
  cp -- "$task_source/$task_file" "$task_target/$task_file"
  cmp -- "$task_source/$task_file" "$task_target/$task_file"
done
BASH
```

Tidak membuat migration baru. Laporkan hasil test/manual dan salinan untuk rescan sebelum checkpoint panel/snapshot/kartu berikutnya. Belum melakukan commit/push/deploy otomatis. File JSON sintetis ini tidak mengandung password dan tidak mengharuskan kamu meng-commit folder fixture manual.

## 8. Verifikasi panduan dan batas pemeriksaan asisten

- Rescan 66: 7 file identik, seluruh 509file source aplikasi identik. Koreksi fixture mahasiswa kedua sudah tersalin dengan study_program.
- Empat JSON baru valid, pasangan NIM/nama cocok antar berkas, file lama 01–03 dan contoh v6.9 dipertahankan. Tidak ada salinan JSON untuk pengguna di /tmp.
- Semua snippet PHP dan Bash dilint.22 closure unit aktual policy+requirements dijalankan secara standalone dengan assertion Pest/PHPUnit vendor tanpa bootstrap Laravel/DB dan lulus.
- 24 pemeriksaan service fakta/KRS/importer aktual dengan ORM sementara di memori lulus, termasuk kedua profil JSON demo dan varian ECCT. Ini bukan pengganti pengujian MySQL.
- Feature 24 test belum dijalankan asisten pada database. User yang menjalankan command di atas. Source aplikasi/deployment kedua repo tidak diubah oleh asisten; hanya docs dan JSON dummy berizin.

Referensi diagnosis database testing: [Laravel testing environment](https://laravel.com/docs/13.x/testing#environment) dan [RefreshDatabase](https://laravel.com/docs/13.x/database-testing#resetting-the-database-after-each-test), diperiksa melalui Context7. Database pengujian terpisah dan rollback test menjelaskan mengapa record test tidak otomatis tersedia di Tinker aplikasi.
