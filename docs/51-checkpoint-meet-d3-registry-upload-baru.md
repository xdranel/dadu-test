# 51 — Meet D3: daftar upload baru dan Surat Permakluman bersyarat

**Goal:** menetapkan satu kontrak upload hasil meet yang dapat dipakai bersama oleh form, validasi, checklist, finalisasi, reviewer, ikon dan ekspor.
**Architecture:** registry sederhana berupa classconstant+method statis; tidak ada dependency atau aksesdatabase. Registry lama dibiarkan sementara sampai semua pemanggil dimigrasikan dalam checkpoint integrasi berikutnya.
**Spec:**45 dan49–50. Pengguna melaporkan semuaunit50lulusdantersalin; rescanclass+test50identikduafolder, termasukhelperdatefixdanstudy_programfixture.

## 1. Kenapa diperlukan dan batas checkpoint

Hasilscan: SidangTaDocumentRequirements::CAPTURES/PAYMENT dipakai checklist, SaveSidangTaDraftStepRequest, DraftDocumentService, DraftReviewService, DraftFinalizer, RevisionDraftService, ReviewEligibility, adminindex dan ReviewExport. Mengubahkonstanta lama sekarang tanpa seluruhpemanggil dapat membuat upload tersimpan tetapi ditolakfinalizer, ataumenghapusdokumenhistoris saat revisi.

Karena itu checkpoint51 memperkenalkan kontrakbaruyangteruji, **belum mengganti form atau upload aktif**. Ini melengkapi landasan49policy+50facts sebelum wiringbersama; jangan menghubungkan satu halaman lebihdahulu sendiri. Tidak menambah generalframework/schema/featureflag.

Dua filebaru: app/Support/SidangTaMeetRequirements.php dan tests/Unit/SidangTaMeetRequirementsTest.php. Registrylama tidakdihapus, modelDOCUMENT_TYPES belumditambah waiver pada51 karena ReviewExportmengiterasinyadanakanmengubahkolom workbookexistingsecara tidakterencana.

### Daftar final baru

| Tahap | Upload |
| --- | --- |
| 1 | Tidak ada; periode dan kontak tetapscalar |
| 2 | ta_data_capture, online_supervision_capture |
| 3 | graduation_payment_proof, ta_supervisor_sk, thesis_draft; tambah eprt_waiver_letter hanya saat modewaiver |
| 4 sesuai | diploma_statement dan diploma_biodata_form |
| 4 belum sesuai | pdm_submission_proof |

Jumlahfile7/6untukEPRTautomatic,8/7untukwaiver, tergantungcabangbiodata. Ini jumlahuploadbaru, bukan menghitung dataotomatis/fieldtanggal. EPRTretest/unknown tidakmendadakmeminta sertifikat/surat; statusituakanmenolakkirim saatwiring, bukan melarangsimpendraft. academic_passeddanrequiredset bukan keseluruhankelayakankirim.

Yangdihapussebagaiuploadbaru: capturesidang2/approvaliGracias/progress/SKPITAK, buktiBPP, sertifikatEPRTlama,KHS,KSM. Sidang2/approvaliGracias/BPPakantampilreadonlydaridata; SKPITAK/KRScukuppanel. Tidak ada asumsi approvalsidangsamadenganfotoBASILA.

## 2. Buat file dan tulis test dahulu

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:class Support/SidangTaMeetRequirements
php artisan make:test SidangTaMeetRequirementsTest --unit --pest
```

Isi seluruh tests/Unit/SidangTaMeetRequirementsTest.php:

```php
<?php

use App\Support\SidangTaAdmissionPolicy as Policy;
use App\Support\SidangTaMeetRequirements as Requirements;

// No database or Laravel application bootstrap is needed for this contract.

test('meet_requirements_stage_one_has_no_upload', function () {
    expect(Requirements::typesForStep(1, null, 'unknown'))->toBe([]);
});

test('meet_requirements_stage_two_only_requires_data_ta_and_approved_supervision', function () {
    foreach (['automatic', 'waiver', 'retest', 'unknown'] as $mode) {
        expect(Requirements::typesForStep(2, null, $mode))->toBe([
            'ta_data_capture', 'online_supervision_capture',
        ]);
    }
});

test('meet_requirements_stage_three_has_three_base_pdfs_and_no_certificate_for_automatic_eprt', function () {
    expect(Requirements::typesForStep(3, null, 'automatic'))->toBe([
        'graduation_payment_proof', 'ta_supervisor_sk', 'thesis_draft',
    ]);
});

test('meet_requirements_eprt_waiver_adds_exactly_one_required_pdf', function () {
    $types = Requirements::typesForStep(3, null, Policy::eprt(449, 3)['mode']);
    expect($types)->toBe(['graduation_payment_proof', 'ta_supervisor_sk', 'thesis_draft', 'eprt_waiver_letter']);
    expect(Requirements::fileRules('eprt_waiver_letter'))->toBe(['nullable', 'file', 'max:10240', 'mimes:pdf']);
});

test('meet_requirements_unknown_or_retest_does_not_invent_a_waiver_upload', function () {
    foreach ([Policy::eprt(null, null), Policy::eprt(449, 2)] as $eprt) {
        expect(Requirements::typesForStep(3, null, $eprt['mode']))->toBe(Requirements::PAYMENT);
        expect($eprt['requires_waiver'])->toBeFalse();
        expect($eprt['mode'])->not->toBe('automatic');
    }
});

test('meet_requirements_stage_four_keeps_both_biodata_branches_and_requires_a_scalar_choice', function () {
    expect(Requirements::typesForStep(4, true, 'automatic'))->toBe(['diploma_statement', 'diploma_biodata_form']);
    expect(Requirements::typesForStep(4, false, 'automatic'))->toBe(['pdm_submission_proof']);
    expect(Requirements::typesForStep(4, null, 'automatic'))->toBe([]);
});

test('meet_requirements_counts_are_seven_or_six_plus_one_for_waiver', function () {
    foreach ([[true, 'automatic', 7], [false, 'automatic', 6], [true, 'waiver', 8], [false, 'waiver', 7]] as [$accurate, $mode, $count]) {
        $types = Requirements::allTypes($accurate, $mode);
        expect(count($types))->toBe($count);
        expect(count(array_unique($types)))->toBe($count);
    }
});

test('meet_requirements_removed_captures_and_pdfs_are_not_new_uploads', function () {
    foreach (['level_2_result_capture', 'igracias_approval_capture', 'ta_progress_capture', 'skpi_tak_capture',
                 'bpp_payment_proof', 'eprt_lac_certificate', 'transcript_khs', 'ta_course_ksm'] as $obsolete) {
        expect(Requirements::allTypes(true, 'waiver'))->not->toContain($obsolete);
        expect(fn () => Requirements::fileRules($obsolete))->toThrow(InvalidArgumentException::class);
    }
});

test('meet_requirements_every_upload_has_a_label_and_expected_format', function () {
    $types = array_unique(array_merge(Requirements::allTypes(true, 'waiver'), Requirements::allTypes(false, 'waiver')));
    foreach ($types as $type) {
        expect(Requirements::LABELS)->toHaveKey($type);
        expect(Requirements::LABELS[$type])->not->toBe('');
        $rules = Requirements::fileRules($type);
        expect($rules)->toContain('nullable')->toContain('file')->toContain('max:10240');
        expect(end($rules))->toBe(in_array($type, Requirements::CAPTURES, true) ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf');
    }
});

test('meet_requirements_rejects_unknown_steps_modes_and_upload_types', function () {
    foreach ([0, 5, -1] as $step) {
        expect(fn () => Requirements::typesForStep($step, true, 'automatic'))->toThrow(InvalidArgumentException::class);
    }
    foreach (['approved', '', 'WAIVER'] as $mode) {
        expect(fn () => Requirements::typesForStep(2, null, $mode))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => Requirements::fileRules('custom_upload'))->toThrow(InvalidArgumentException::class);
});

test('meet_requirements_eprt_threshold_switches_required_set_and_does_not_include_payment_date_as_a_file', function () {
    $waiver = Requirements::typesForStep(3, null, Policy::eprt(449, 3)['mode']);
    $automatic = Requirements::typesForStep(3, null, Policy::eprt(450, 3)['mode']);
    expect(array_values(array_diff($waiver, $automatic)))->toBe(['eprt_waiver_letter']);
    expect($waiver)->not->toContain('graduation_payment_date');
    expect($automatic)->not->toContain('graduation_payment_date');
});

```

Jalankan sebelumclassdiisi; expectedFAILmethodsbelumada:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/SidangTaMeetRequirementsTest.php
```

## 3. Isi registry lengkap

Isi seluruh app/Support/SidangTaMeetRequirements.php:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

class SidangTaMeetRequirements
{
    public const CAPTURES = ['ta_data_capture', 'online_supervision_capture'];
    public const PAYMENT = ['graduation_payment_proof', 'ta_supervisor_sk', 'thesis_draft'];
    public const WAIVER = 'eprt_waiver_letter';
    public const DIPLOMA = ['diploma_statement', 'diploma_biodata_form'];
    public const PDM = ['pdm_submission_proof'];

    public const LABELS = [
        'ta_data_capture' => 'Capture Data TA (Menu TA/PA)',
        'online_supervision_capture' => 'Capture Bimbingan Online yang Disetujui',
        'graduation_payment_proof' => 'Bukti Pembayaran Wisuda',
        'ta_supervisor_sk' => 'SK Pembimbing TA Aktif',
        'thesis_draft' => 'Draft Buku TA (PDF)',
        'eprt_waiver_letter' => 'Surat Permakluman EPRT',
        'diploma_statement' => 'Surat Pernyataan Biodata Ijazah',
        'diploma_biodata_form' => 'Formulir Biodata Ijazah',
        'pdm_submission_proof' => 'Bukti Pengajuan PDM',
    ];

    public static function typesForStep(int $step, ?bool $accurate, string $eprtMode): array
    {
        self::assertMode($eprtMode);
        return match ($step) {
            1 => [],
            2 => self::CAPTURES,
            3 => $eprtMode === 'waiver' ? [...self::PAYMENT, self::WAIVER] : self::PAYMENT,
            4 => $accurate === null ? [] : ($accurate ? self::DIPLOMA : self::PDM),
            default => throw new InvalidArgumentException('Tahap sidang TA tidak valid.'),
        };
    }

    public static function allTypes(bool $accurate, string $eprtMode): array
    {
        return array_merge(self::typesForStep(2, $accurate, $eprtMode),
            self::typesForStep(3, $accurate, $eprtMode), self::typesForStep(4, $accurate, $eprtMode));
    }

    public static function fileRules(string $type): array
    {
        if (!array_key_exists($type, self::LABELS)) {
            throw new InvalidArgumentException('Jenis upload tidak termasuk persyaratan hasil meet.');
        }
        // Presence is checked by checklist/finalization; partial draft saves stay valid.
        return ['nullable', 'file', 'max:10240', in_array($type, self::CAPTURES, true)
            ? 'mimes:pdf,jpg,jpeg,png' : 'mimes:pdf'];
    }

    private static function assertMode(string $mode): void
    {
        if (!in_array($mode, ['automatic', 'waiver', 'retest', 'unknown'], true)) {
            throw new InvalidArgumentException('Mode EPRT tidak dikenal.');
        }
    }
}

```

fileRules nullable untukmendukung draftparsial, bukantandafilebolehkurangsaatfinal. Requiredpresence tetapakan dicekchecklist/finalizerberdasarkan typesForStep/allTypes. typesForStep4nullmenghasilkan[]sambilfieldscalarbelumdipilih; **bukan tahap4lengkap**. Scalarbooleanrequiredtetap harusdiperiksa. allTypes memerlukanboolpilihanbiodata yangsudahvalid. Modesberasal dari Policy49hasilFacts50, bukan requestmahasiswa.

## 4. Test ulang

```bash
php -l app/Support/SidangTaMeetRequirements.php
php -l tests/Unit/SidangTaMeetRequirementsTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/SidangTaMeetRequirementsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Feature/SidangTaAdmissionFactsTest.php
```

Target **11Unitregistry+10Unitpolicy+12Featurefacts=33passed**. SuiteFeatureTAjumlahnyatetapcheckpoint50; seluruhtestTAtermasukUnitbertambah11. Janganmengubahphpunit.xmlkeSQLite; UnitsengajatidakpakaiDBdanFeaturetetappakaiPodman dadu_testing. Fullregressionexistingbolehdijalankansepertibiasa.

TidakperluimportJSONataumanualUIbarupada51, karena belumadaUIyangdiganti. Testformatfiledi51memeriksakontrakrules; testuploadHTTPnyatasepertiPDFvalid,mimes/ukuran,partialsave,tampereddoctypeakanmengikutipada integrasi berikutnya. Setelahlulus,salin2filebaru untukrescan. Janganhapusdokumen/draftDBexistingataumigratefresh.

## 5. Integrasi yang harus dikerjakan bersama sesudah51

| Area | Perubahan wajib saat mengaktifkan aturan baru |
| --- | --- |
| Model label/metadata | Tambah eprt_waiver_letter; type lama tetap dikenali untuk histori |
| Draft creation/revision | Simpan penanda versi requirements server agar pengajuanlama tidakditafsirkan sebagaiflowbaru secara diam-diam |
| Request+save | Daftarkunci baru sesuai tahap/cabang; cegah uploadremovedtype; scalarpartialsave tetap |
| Checklist+review | RequiredsetdynamicdariEPRT,unknownacademic tidakmenguncidraft/tinjau; revisiterarahhanyatarget |
| Finalizer | BacaFactsfreshdi bawah kontraklock, fivegate+modeEPRTmenolakkirim, simpanfacts/versisnapshot, replayconsumedlebihdahulu, cleanupsetelahcommit |
| Revision finalizer | Pertahankan statusverified+fileyangtidakdirevisi, targetuploadbaru pending, approvednotesclear; janganresetsemuapending |
| UI | Dua captureupload, tigaPDFdasar+waiverconditional, cardsreadonlyredup, requiredmarkerhanyaupload, gambarforms/sidang-ta, LinkSuratPermakluman |
| Petugas/list/export | Griddokumen2kolom, iconuploadonlydandynamic, snapshotlabel, NA/missingfiletepat, tidakmengubahhistoris |

StatusJSONbaru tetapmenunggu pengguna menaruhnyadifolderyangsama. Scopeini dapatlanjutdenganfixtures; saatimportnyatadibutuhkanakandiberitahu. P3legacyyang202testtidakbolehdianggapbuktimeetwiringbaru; testintegrasiakanmenguji5gate,gagal1syarat,EPRTconditional,simpendraft/tinjauyangtetapboleh,versionconflictdanperiodclose.
