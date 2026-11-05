# 50 — Meet D2: adapter fakta hasil sinkronisasi untuk evaluator

**Goal:** membaca fakta akademik per mahasiswa dari database hasil importITPanel dan meneruskannya ke policy49.
**Architecture:** service baru AdmissionFacts memakai query relasi baru, bukan relationcache/browserinput. Mengembalikan fakta dan hasilpolicy; belum dipasang pada form/finalizer agar perubahanrequiredset berikutnya dapat diuji sebagai satu integrasi.
**Spec:**45,47,49; pengguna menyetujui melanjutkan dengan data belumtersedia dan akan menaruhJSONbaru di folderbackupyangsama. Rescan49: class/testidentikduafolder; pengguna melaporkanunitlulus.

## 1. Batas dan keputusan pemetaan sementara

Dua filebaru: app/Services/SidangTaAdmissionFacts.php dan tests/Feature/SidangTaAdmissionFactsTest.php. Tidakada migrasi/perubahanpermission/mengubahserviceyudisium. Jangan menghubungkannya sendiri ke finalizer, snapshot, atau requiredset lama; wiringberikutnya akanmemperbaruitestfixturepengirimanbersama.

Fakta:

- Tingkat2 dari StudentSidangTingkat booleancast; rowtidakada=null. Existingimporter sudahmengubahmissing/Nke false; adapter tidakdapatmemulihkanbedanya tanpa rawsource. False tetap tidaklulus, bukan fallbacklulus.
- KRS memakai helper47 exactTA2singleactiveacademicperiod. eligible→true; unconfirmed/unknown→null. GenericTUGASAKHIRbelumlulusotomatis.
- SKPI memakai aliasesexistingraw_data status/approval_status/is_approved. Nilaitidakdikenal/kontradiktif=null; keberadaanraw_data bukanapproval. Ini konvensiexistingyangdilokalisasi diadapter, belumklaimformatJSONresmi; konfirmasisumberbaruakanmengubahpemetaanterpusatini. FotoBASILAtidakdipakai.
- TAK memakai relasi existing validexpiry>=today ataunull, prioritasskortertinggi, dan mengecualikantestdatefuture. Batas60tetapdipolicy.
- SKSlulus dari StudentCourseIssue.credits_passed, fallback Student.total_credits yang diimporter dipetakan dari sks_lulus. Angka0tidakdiganti fallback. Jika total_credits nantinyaberubaharti menjadiSKSditempuh, adapterharusdirevisi, jangandiam-diamdipakai.
- EPRT: hanyabaris total tanpa categoryyangterisi, total_score>=0,tanggaltesbukanmasadepan. Menggunakan skortotaltertinggi sebagai **konvensisementara mengikuti pembacaanexistingImportedCampusDataGrabService**, bukan klaimaturan resmi pemilihan skor/masaberlaku. Konfirmasiformatresmi tetapdiperlukansebelumfiturditutup.
- JumlahEPRT dihitung dari catatan nomorpercobaanpositif+tanggaltesyangkonsisten; pasanganduplikat tidakmenambahcount. Nomorpercobaan3sendiritidakberarti3tes. Nomorsama/tanggalberbeda atautanggalsama/nomorberbeda →unknown. Datatotalyangbelumada tanggal/nomorvalid membuatcountunknown. Nilai>=450masihautomatic tanpa metadataattempt sesuai49. Konvensisatutespertanggal disimpan konservatif; jika sumberresmimemungkinkanlebihdarisatutessehari, gunakanIDujianresmi padaadapterberikutnya.

TidakmengklaimcountbarisDB sebagai riwayattesresmi. Format sumberbaru harusdicocokkan ke konvensiini sebelummengaktifkan cabangsurat di alurproduksi. Defaultattempt1padamodel/migrasibukanbuktiautomatisidentitas3tes.

## 2. Buat file dan isi test dahulu

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Services/SidangTaAdmissionFacts
php artisan make:test SidangTaAdmissionFactsTest --pest
```

Isi seluruh tests/Feature/SidangTaAdmissionFactsTest.php berikut. Database hanya dadu_testing seperti suiteexisting. Tidakperluakunlogin untukserviceinternal; permissionpengirimanakan diuji pada wiringcontroller/finalizerberikutnya.

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentCourseIssue;
use App\Models\StudentEprt;
use App\Models\StudentSidangTingkat;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Services\SidangTaAdmissionFacts;

beforeEach(function () {
    $this->travelTo(now()->startOfDay());
    $this->student = Student::create(['student_number' => '1202200050', 'name' => 'Meet Facts',
        'academic_year' => 2022, 'study_program' => 'Informatika', 'status' => 'Aktif', 'total_credits' => null]);
    $this->readFacts = fn () => app(SidangTaAdmissionFacts::class)->forStudent($this->student);
    $this->eprtRow = function (array $values = []) {
        return StudentEprt::create(array_replace(['student_number' => $this->student->student_number,
            'total_score' => 400, 'attempt_number' => 1, 'test_date' => today()->subDays(3), 'category' => null], $values));
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_facts_empty_profile_has_five_unknown_gates_and_unknown_eprt', function () {
    $result = ($this->readFacts)();
    expect(count($result['academic']['blockers']))->toBe(5);
    foreach ($result['academic']['gates'] as $gate) expect($gate['state'])->toBe('unknown');
    expect($result['eprt']['mode'])->toBe('unknown');
    expect($result['eprt_facts']['confirmed_attempts'])->toBeNull();
});

test('meet_facts_level_two_is_boolean_and_reads_new_data_after_relation_was_loaded', function () {
    $row = StudentSidangTingkat::create(['student_number' => $this->student->student_number, 'tingkat_2' => true]);
    $this->student->load('sidangTingkat');
    expect(($this->readFacts)()['facts']['level_2_passed'])->toBeTrue();
    $row->update(['tingkat_2' => false]);
    expect(($this->readFacts)()['facts']['level_2_passed'])->toBeFalse();
});

test('meet_facts_skpi_requires_recognized_non_conflicting_status', function () {
    foreach ([
        [['status' => ' APPROVED '], true], [['is_approved' => true], true],
        [['approval_status' => 'pending'], false], [['is_approved' => 0], false],
        [[], null], [['exists' => true], null], [['status' => 'custom'], null],
        [['status' => 'approved', 'is_approved' => false], null], [['status' => []], null],
    ] as [$raw, $expected]) {
        StudentSkpi::updateOrCreate(['student_number' => $this->student->student_number], ['raw_data' => $raw]);
        expect(($this->readFacts)()['facts']['skpi_approved'])->toBe($expected);
    }
});

test('meet_facts_tak_uses_valid_highest_score_and_preserves_zero', function () {
    $nim = $this->student->student_number;
    StudentTak::create(['student_number' => $nim, 'total_points' => 99, 'test_date' => today()->subYear(), 'expiry_date' => today()->subDay()]);
    StudentTak::create(['student_number' => $nim, 'total_points' => 90, 'test_date' => today()->addDay(), 'expiry_date' => null]);
    $valid = StudentTak::create(['student_number' => $nim, 'total_points' => 0, 'test_date' => today(), 'expiry_date' => today()]);
    expect((float) ($this->readFacts)()['facts']['tak_score'])->toBe(0.0);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('failed');
    $valid->update(['total_points' => 60]);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('passed');
    $valid->delete();
    expect(($this->readFacts)()['facts']['tak_score'])->toBeNull();
});

test('meet_facts_passed_credits_prefers_imported_passed_credits_including_zero', function () {
    $this->student->update(['total_credits' => 150]);
    expect((int) ($this->readFacts)()['facts']['passed_credits'])->toBe(150);
    $row = StudentCourseIssue::create(['student_number' => $this->student->student_number, 'credits_passed' => 0]);
    expect(($this->readFacts)()['facts']['passed_credits'])->toBe(0);
    expect(($this->readFacts)()['academic']['gates']['passed_credits']['state'])->toBe('failed');
    $row->update(['credits_passed' => 120]);
    expect(($this->readFacts)()['academic']['gates']['passed_credits']['state'])->toBe('passed');
});

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
    expect(($this->readFacts)()['facts']['ta2_registered'])->toBeNull();
    expect(($this->readFacts)()['academic']['academic_passed'])->toBeFalse();
});

test('meet_facts_eprt_three_completed_unique_tests_require_waiver_and_duplicates_do_not_add_attempts', function () {
    foreach ([1, 2, 3] as $attempt) {
        ($this->eprtRow)(['attempt_number' => $attempt, 'test_date' => today()->subDays(4 - $attempt)]);
    }
    ($this->eprtRow)(); // exact same attempt/date as attempt one
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($result['eprt']['mode'])->toBe('waiver');
});

test('meet_facts_eprt_component_rows_do_not_count_as_three_tests', function () {
    foreach (['listening', 'structure', 'reading'] as $category) {
        ($this->eprtRow)(['category' => $category, 'total_score' => 500]);
    }
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['score'])->toBeNull();
    expect($result['eprt_facts']['confirmed_attempts'])->toBeNull();
    expect($result['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_eprt_high_attempt_number_is_not_a_test_count', function () {
    ($this->eprtRow)(['attempt_number' => 1]);
    ($this->eprtRow)(['attempt_number' => 3, 'test_date' => today()->subDay()]);
    $result = ($this->readFacts)();
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(2);
    expect($result['eprt']['mode'])->toBe('retest');
});

test('meet_facts_eprt_missing_metadata_or_conflicting_attempt_dates_are_unknown', function () {
    $first = ($this->eprtRow)(['test_date' => null]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
    $first->update(['test_date' => today()->subDays(3)]);
    ($this->eprtRow)(['attempt_number' => 1, 'test_date' => today()->subDay()]);
    expect(($this->readFacts)()['eprt_facts']['confirmed_attempts'])->toBeNull();
    StudentEprt::query()->delete();
    ($this->eprtRow)(['attempt_number' => 1]);
    ($this->eprtRow)(['attempt_number' => 2]); // same date, different attempt
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_eprt_qualifying_total_score_needs_no_attempt_metadata_but_future_tests_do_not_qualify', function () {
    $row = ($this->eprtRow)(['total_score' => 450, 'test_date' => null]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('automatic');
    $row->update(['test_date' => today()->addDay()]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_never_borrows_another_students_academic_records', function () {
    $other = Student::create(['student_number' => '1202200051', 'name' => 'Other', 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'email' => 'other-admission-facts@test.local']);
    StudentSidangTingkat::create(['student_number' => $other->student_number, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $other->student_number, 'raw_data' => ['status' => 'approved']]);
    StudentTak::create(['student_number' => $other->student_number, 'total_points' => 100]);
    StudentEprt::create(['student_number' => $other->student_number, 'total_score' => 550]);
    expect(count(($this->readFacts)()['academic']['blockers']))->toBe(5);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});

```

Jalankan sebelum mengisiclass, expectedFAIL methodforStudentbelumada:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaAdmissionFactsTest.php
```

## 3. Isi service lengkap

Ganti seluruh app/Services/SidangTaAdmissionFacts.php dengan:

```php
<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEprt;
use App\Support\SidangTaAdmissionPolicy as Policy;

class SidangTaAdmissionFacts
{
    public function __construct(private SidangTaAcademicSnapshot $snapshots) {}

    public function forStudent(Student $student): array
    {
        // Re-query related data: never trust a previously loaded relation cache.
        $student = Student::whereKey($student->id)->firstOrFail();
        $level = $student->sidangTingkat()->first();
        $skpi = $student->skpi()->first();
        $issues = $student->courseIssues()->first();
        $tak = $student->tak()->where(function ($query) {
            $query->whereNull('test_date')->orWhereDate('test_date', '<=', today());
        })->first();
        $krs = $this->snapshots->ta2KrsStatus($student);
        $facts = [
            'level_2_passed' => $level?->tingkat_2,
            'ta2_registered' => $krs === 'eligible' ? true : null,
            'skpi_approved' => $this->skpiApproval($skpi?->raw_data ?? []),
            'tak_score' => $tak?->total_points,
            'passed_credits' => $issues?->credits_passed ?? $student->total_credits,
        ];
        $eprt = $this->eprtFacts($student);
        return ['facts' => $facts, 'academic' => Policy::academic($facts),
            'krs_status' => $krs, 'eprt_facts' => $eprt,
            'eprt' => Policy::eprt($eprt['score'], $eprt['confirmed_attempts'])];
    }

    /** Existing raw-data aliases; unknown/conflicting values are not approval. */
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

    private function eprtFacts(Student $student): array
    {
        // Category rows are component scores, not completed tests.
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
        // A high attempt number alone is not evidence of its missing earlier tests.
        $attempts = $complete ? count($byAttempt) : null;
        return ['score' => $score, 'confirmed_attempts' => $attempts];
    }
}

```

Nilai academic_passed bukan can_submit. ApprovaliGracias dan BPP belumditambahkansebagai blockingkeenam/ketujuh; keduanyamemerlukan mappingfieldsemantikdansemesterresmi. TidakfallbackBASILAfotountukapprovalsidang. Belumada pemanggilanserviceini dariUIsehingga halamanexistingtidakberubah pada50.

## 4. Test dan manual pembacaan

```bash
php -l app/Services/SidangTaAdmissionFacts.php
php -l tests/Feature/SidangTaAdmissionFactsTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaAdmissionFactsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```

Target **12Featurebaru+10Unitpolicy=22passed** untukcommandpertama. FeatureTAbertambah12 darihasilaktual48 (202jika190sebelumnya),Unitpolicy10terpisah,Node12tetap. Fullregressionjalankansepertibiasa; janganubahfixtureFeaturelama untukmemaksaSDK/gateluluskarenaadapterbelumdiwire.

TidakperluimporJSONuntuktest: fixtures lengkapdisediakankode. JikainginmelihatdataexistingtanpamengubahDB, jalankantinkerdifolderasli (tanpaenvtestinguntukmelihatDBaplikasiyangbiasadipakai):

```bash
php artisan tinker
```

```php
$student = App\Models\Student::where('email', 'krisna@mahasiswa.test')->firstOrFail();
$result = app(App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
dump($result);
```

Jikaemaildummybelumterkaitdatamahasiswa setelahimport, gunakanNIMmilikakun sendiri yangbenar, bukanNIMfixturesintetis padaDBaplikasi. Jangan mengubahdataasliuntukmenjadikannilailulus. Blokdumpini hanyamembacafakta; fieldJSONmissingwajarmenghasilkanunknown/blockers. Perubahan datayangdiimportkemudianharuslangsungterbacapadapemanggilanberikutnya tanpame-restartsession; cacheservicelamajangandipakai.

Manualbrowserkhususbelumdibutuhkan karenabelumadaUIbaru. JanganmengujiKirimgatebaru pada50: belumterpasang. Setelahlulus,salin2filebaru ke dadu-test lalumintarescan.

## 5. Langkah sesudah checkpoint ini

Berikutnya ganti registryuploadstatis: tahap2hanyaDataTA/Bimbingan, tahap3Wisuda/SK/Draft+suratpermaklumanconditional, tahap4tetap. Perubahanituakanmencakupdraftvalidation/checklist/review/finalizer,guard5gatehanyasaatkirimdanEPRTconditional,fixtureyangsesuairegistrybaru,sertakompabilitaspengajuanlama. Akantetapstep-by-stepdengankode/testlengkap.

SourceJSONbaru jika sudahtersedia taruhdifolderbackupyangsama laluberi tahu. SebelumimporakandiscanagarfieldstatusSKPI,approvalsidang,EPRTattempt,danmappingKRSgenericbenar. TidakperlumengimporulangJSONyangmasihkosong; tidakmenjalankanmigratefreshuntukcheckpointini.


## 6. Koreksi tiga kegagalan fixture test

Pengguna melaporkan3failed/19passed pada Facts+Policy; Tinker belum dijalankan. Ada dua kekeliruan fixture pada versi awal panduan ini:

1. yesterday() dan tomorrow() bukan helperglobal yang tersedia di proyek ini. Probe vendorautoload aktual menunjukkan today tersedia, dua helperitu tidak. Gunakan today()->subDay() dan today()->addDay(). Masing-masing memanggil todaybaru sehingga tanggalpembanding tidak termutasi silang.
2. Fixture Other tidak mengisi study_program. Migrasi students mendefinisikannya NOTNULL tanpa default; email sudah nullable melalui migrasi lanjutan. Baris statement->execute yang dikirim belum memuat pesan SQL lengkap, tetapi kekurangan field wajib pada fixture ini terkonfirmasi dari skema. Lengkapi study_program; sertakan status/emailtestunik agar fixture eksplisit.

Ini koreksi test, bukan mengubah syarat TAK/EPRT atau melemahkan assertion. Tidak perlu perubahan service, migration, SQLite, freshdatabase, atau menjalankanTinker. Bagian2 canonical di atas sudah diperbaiki agar salinan berikutnya tidak mengulangkesalahan.

Pada tests/Feature/SidangTaAdmissionFactsTest.php ganti ketiga test berikut **berdasarkan nama**, jangan menambahkan duplikat. Imports/beforeEach/testlain tetap:

```php
test('meet_facts_tak_uses_valid_highest_score_and_preserves_zero', function () {
    $nim = $this->student->student_number;
    StudentTak::create(['student_number' => $nim, 'total_points' => 99, 'test_date' => today()->subYear(), 'expiry_date' => today()->subDay()]);
    StudentTak::create(['student_number' => $nim, 'total_points' => 90, 'test_date' => today()->addDay(), 'expiry_date' => null]);
    $valid = StudentTak::create(['student_number' => $nim, 'total_points' => 0, 'test_date' => today(), 'expiry_date' => today()]);
    expect((float) ($this->readFacts)()['facts']['tak_score'])->toBe(0.0);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('failed');
    $valid->update(['total_points' => 60]);
    expect(($this->readFacts)()['academic']['gates']['tak_score']['state'])->toBe('passed');
    $valid->delete();
    expect(($this->readFacts)()['facts']['tak_score'])->toBeNull();
});

test('meet_facts_eprt_qualifying_total_score_needs_no_attempt_metadata_but_future_tests_do_not_qualify', function () {
    $row = ($this->eprtRow)(['total_score' => 450, 'test_date' => null]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('automatic');
    $row->update(['test_date' => today()->addDay()]);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});

test('meet_facts_never_borrows_another_students_academic_records', function () {
    $other = Student::create(['student_number' => '1202200051', 'name' => 'Other', 'academic_year' => 2022,
        'study_program' => 'Informatika', 'status' => 'Aktif', 'email' => 'other-admission-facts@test.local']);
    StudentSidangTingkat::create(['student_number' => $other->student_number, 'tingkat_2' => true]);
    StudentSkpi::create(['student_number' => $other->student_number, 'raw_data' => ['status' => 'approved']]);
    StudentTak::create(['student_number' => $other->student_number, 'total_points' => 100]);
    StudentEprt::create(['student_number' => $other->student_number, 'total_score' => 550]);
    expect(count(($this->readFacts)()['academic']['blockers']))->toBe(5);
    expect(($this->readFacts)()['eprt']['mode'])->toBe('unknown');
});
```

Command test terarah dahulu:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaAdmissionFactsTest.php --filter='meet_facts_tak_uses_valid_highest_score_and_preserves_zero|meet_facts_eprt_qualifying_total_score_needs_no_attempt_metadata_but_future_tests_do_not_qualify|meet_facts_never_borrows_another_students_academic_records'
```

Target3passed. Lalu ulangi:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaAdmissionFactsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php
```

Targettetap22passed, tidak ada testbaru. Setelah lulus jalankan regression yang tertulis di bagian4. Tinker bersifatopsional untukmembacadataaplikasi, bukansyaratuntukmemperbaiki ketigatestini.

Asisten memeriksa sintaks snippet dan helper tersediadenganvendorautoload, serta skemamigrasi; tidak menjalankan test DB. Jika kegagalan SQL ketiga masih ada setelah fixturelengkap, kirim pesanSQLSTATEbesertanamacolumn karena barisstatementexecute sendiri tidakmengungkap constraintlain. Belumpindah/commit sebelumtestlulus, lalusalin2file50sesuaidaftar.
