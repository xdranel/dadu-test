# 49 — Meet D1: evaluator lima syarat akademik dan kebijakan EPRT

**Goal:** menguji inti aturan baru sebelum menghubungkannya ke sinkronisasi, upload dan finalisasi.
**Architecture:** satu class policy murni menerima fakta ternormalisasi. Lima gate akademik terpisah dari mode dokumen EPRT. Tidak membaca DB/JSON/HTTP, tidak mengubah draft atau status pengajuan.
**Spec:**45 dan persetujuan melanjutkan dengan data sintetis yang jelas sambil menunggu konfirmasi sumberJSON. Checkpoint48 sudah dilaporkan unit/manual lulus dan2file tersalin identik.

## 1. Batas checkpoint

Ini prasyarat backend yang bisa diuji sendiri, **belum memasang blocking pada aplikasi**. Jangan menyisipkan class ini ke finalizer/controller sendiri sebelum panduan integrasi berikutnya: required upload, review, replay dan revision harus diubah bersama dengan fixture yang sesuai. Existingalur/upload15/14 masih berlaku sementara. Tidak ada perubahanUI, schema, asset, atau dataimport.

Dua filebaru: app/Support/SidangTaAdmissionPolicy.php dan tests/Unit/SidangTaAdmissionPolicyTest.php. Nama tidak menggantikan SidangTaReviewEligibility yang memeriksa dokumen petugas.

Kontrak input akademik: level_2_passed,ta2_registered,skpi_approved harus booleantrue/false atau null; tak_score danpassed_credits angka/nonnegative atau stringnumerik dariDB. Policy menolakstringtruthy agar N/false/rejected tidaklolos; adapterimportyangakanmenormalisasi fieldresmi.

Kontrak EPRT: skor efektif dan jumlahtesyangtelahdikonfirmasi, bukan countbaris kategori/attempt_numberterbesar. Adapterbelumdibuat di49. Pemilihan skormaksimum/terakhir dan masa berlaku menunggu sumberresmi; jangan menganggap nilai tunggal sebagai bukti3tes. >=450langsungautomatic; <450dan>=3waiver; <450dan<3retest; missing/malformedunknown. Numeric0dibedakan darimissing. Waiver berarti wajibuploadsurat, **bukan sudahbolehkirim**.

## 2. Buat file dan isi test

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Support/SidangTaAdmissionPolicy
php artisan make:test SidangTaAdmissionPolicyTest --unit --pest
```

Ganti seluruh tests/Unit/SidangTaAdmissionPolicyTest.php dengan kode lengkap berikut. Test Unit sengaja tidak memakai RefreshDatabase karena fungsiini tidakmengaksesDB; ini tidakmengubahphpunit.xml atau memaksaSQLite.

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
    foreach ([[null, 3], [449, null], [449, 2.5], [true, 3], [-1, 3], [449, -1], [NAN, 3], [449, INF], [[], 3]] as [$score, $attempts]) {
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

```

Jalankan sebelum mengisi class; methodbelumada harusmenyebabkan testgagal:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/SidangTaAdmissionPolicyTest.php
```

## 3. Isi class lengkap

Ganti seluruh app/Support/SidangTaAdmissionPolicy.php dengan:

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
        $score = self::number($score);
        if ($score !== null && $score >= 450) {
            return ['mode' => 'automatic', 'requires_waiver' => false,
                'message' => 'Nilai EPRT memenuhi syarat.'];
        }
        $attempts = self::number($confirmedAttempts);
        if ($score === null || $attempts === null || floor($attempts) !== $attempts) {
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

academic_passed hanya menjawab5gate. Itu bukan can_submit: kelengkapanupload/scalar/periode/ownership/versi/reviewdanmodeEPRTtetapdiperlukan saat integrasi. Tidak ada jaluroverridebyHTTPatau defaulttrueuntukJSONkosong.

## 4. Test sesudah implementasi

```bash
php -l app/Support/SidangTaAdmissionPolicy.php
php -l tests/Unit/SidangTaAdmissionPolicyTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/SidangTaAdmissionPolicyTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDetailUiTest.php tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaReviewFeedbackTest.php
```

Target10testUnitbaru. SuiteFeatureTAjumlahnyatetapsebelumnya (190jika48terakhir190); jika menggabungkanUnitpolicy denganFeatureTAjumlahbertambah10. Node12tidakberubah. Tidakmenambahunitfilekecommandtests/Featurekarenaalamatnya tests/Unit.

Manualbrowserbaru tidakdiperlukan untukclassyangbelumterhubungUI. Pastikan halamanexistingtetapberjalan; jangan mengharapkanblockingbarumunculsetelah49. Tidak perlu imporJSON. Setelahlulus,salin2filebaru ke dadu-test danlaporkan.

## 5. Kelanjutan integrasi

Berikutnya adapterfaktadariDBsinkronisasi+requiredsetbaru, lalu guardfinalsubmitdansnapshotsertaUI. DataSKPI/approvalsidangiGracias/riwayatEPRTmasihbelumtersedia dibackup; sementaraunknown, tidakmerekayasadata. KRSgenericTUGASAKHIRmasihbelumdipetakan. Saatdataresmisudahadakonfirmasi, kirimfield/sampelnya sebelumimportagaradapterbenar.

Lima gate nanti hanya menolakfinalsubmit, tidakmembatasisimpandraft/Tinjau. Untuk modeEPRTretest/unknown rekomendasimenolakkirimdanmembiarkandraft/tinjauakanmenjadiasumsitercatatpada checkpointintegrasi; jikakebijakanberbeda konfirmasisebelumdipasang. LinkSuratPermakluman dan uploadPDFconditional dipasangbersamaregistrybaru, bukandalamclassini.

Sebelummenyatakanmeetfiturselesai, wajibadaintegrationtestserverforgedrequest/missingfile/partialdraft/periodclose/races/idempotentreplay/revisiterarah. Unitpolicy10tidakmenggantikanbuktitersebut. Tidakcommitpetugassampai seluruhscope danrescanakhir.
