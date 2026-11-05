# 65 — Kontrak JSON resmi dan pembaca profil akademik

> Panduan ini dikerjakan pengguna secara berurutan di repo development asli. Asisten hanya menulis Markdown; tidak mengubah source aplikasi di kedua repo. Metode pengerjaan tetap: pengguna mengisi kode → test → salin ke dadu-test → rescan. Tidak perlu meminta pilihan metode lagi.

**Goal:** membuat pembaca format JSON terbaru yang membedakan data tersedia, menu tidak dipilih, dan kegagalan pengambilan data, sebelum dipasang ke importer dan aturan kirim TA.

**Architecture:** `AcademicProfileJsonNormalizer` membaca satu baris beserta konteks menu/periode dan menghasilkan `updates` serta `skipped`. Tidak ada akses database, HTTP, keputusan final TA, maupun perubahan tampilan. Importer berikutnya hanya boleh menulis grup yang ada dalam `updates`; grup yang tidak ada dipertahankan.

**Tech Stack:** PHP sesuai proyek `^8.4.27`, Laravel 13, Pest 4, MySQL Podman untuk konfigurasi pengujian lokal. Tidak ada dependency baru.

**Spec:** keputusan pengguna pada 5 November 2026 setelah membaca tiga JSON nyata dari Downloads; dokumen ini menggantikan asumsi format sementara di 45/49/50/53 untuk bagian yang disebutkan. Snapshot pengajuan final yang sudah ada tetap historis.

## 1. Ringkasan keputusan untuk laporan

| Bagian | Keputusan |
| --- | --- |
| SKPI | Acuan hanya `status_skpi = "Disetujui"`. Tiga status Y/N tidak menentukan kelayakan aplikasi. Nilai lain tidak meloloskan pengiriman. |
| Persetujuan sidang | Acuan `persetujuan_maju_sidang = "APPROVED"`, bukan `status_ta` yang pada JSON nyata berupa kode 1/4. Ditampilkan otomatis pada tahap 2. |
| Nilai bahasa | Gunakan `nilai_bahasa`, ringkasan nilai tertinggi yang masih berlaku. Jenis dapat berupa Online EPrT, ITP TOEFL, dan lainnya. Nilai numerik minimal 450 memenuhi otomatis. |
| Jumlah tes | Gunakan langsung `jumlah_tes_bahasa` dari exporter, tanpa menghitung ulang riwayat/tanggal/jenis tes. |
| Surat Permakluman | Jika belum mempunyai nilai numerik minimal 450 dan jumlah tes resmi minimal 3, gunakan Surat Permakluman; termasuk `nilai_bahasa=null` pada kondisi seperti ECCT. Ini interpretasi operasional jawaban terbaru pengguna dan harus tampak pada test integrasi berikutnya. Nilai null tidak diubah menjadi 0. |
| Jumlah belum memenuhi | Jika jumlah resmi 0–2 dan nilai belum mencapai 450, ikuti tes lagi. Jumlah belum tersedia tetap tidak meloloskan waiver. |
| TAK | `total_tak` adalah poin kumulatif sepanjang studi yang sudah disahkan; minimal 60. Error sumber tidak boleh menghapus nilai lama, dan null bukan0. |
| BPP | `status_keuangan` adalah BPP. Perubahan KREDIT→LUNAS memperbarui periode pembayaran yang sama. Periode berasal dari konteks impor resmi; jangan disimpulkan dari tanggal ekspor atau semester studi. BPP bukan syarat pemblokir tambahan. |
| KRS | JSON terpisah; nama TUGAS AKHIR atau TUGAS AKHIR 2 diterima setelah dikaitkan dengan periode akademik aktif. Jangan mencocokkan substring sembarang seperti PROPOSAL TUGAS AKHIR. |
| Impor ulang | Perbarui data mahasiswa yang bersangkutan. Riwayat bahasa hasil impor diganti sebagai satu kumpulan saat menu bahasa berhasil dibaca. Data menu lain dan mahasiswa yang tidak ikut ekspor dipertahankan. |
| Menu tidak dipilih/error | Menu yang tidak ikut ekspor tidak dikosongkan. Error pengambilan dipisahkan dari hasil kosong yang sah. |

Lima syarat akademik saat kirim tetap: tingkat 2 lulus, KRS yang sesuai pada periode aktif, SKPI disetujui, TAK≥60, SKS lulus≥120. Aturan bahasa terpisah memilih automatic/waiver/retest/unknown. Draft dan tinjau tetap bisa dipakai sesuai kelengkapan upload; keputusan akhir diperiksa ulang server. Aturan sudah cukup untuk melanjutkan pengerjaan. Integrasi kode baru belum selesai hanya karena keputusan ditetapkan.

## 2. Hasil rescan dan batas checkpoint

Ketiga file nyata berisi 60+100+100 baris. Tidak menyalin file nyata atau identitas mahasiswa ke repo publik. Fixture unit di bawah sepenuhnya sintetis.

- SKPI mempunyai Disetujui, Belum Disetujui, dan null.
- `persetujuan_maju_sidang` membedakan APPROVED/BELUM DISETUJUI/null; `status_ta` tidak bisa digunakan sebagai keputusan.
- Semua nilai bahasa numerik yang ditemukan cocok dengan nilai tertinggi dari riwayat berstatus Berlaku.
- Ada riwayat berstatus Tidak diketahui/Kedaluwarsa dan hitungan ringkasan berbeda dari jumlah riwayat Berlaku. Tetap gunakan jumlah resmi sesuai instruksi pengguna, bukan perhitungan aplikasi.
- File `(1)` memuat 100 error token TAK; file `(2)` memuat satu error koneksi TAK. Error TAK tidak membatalkan grup SKPI/bahasa yang berhasil.
- Semua 260 baris memakai `semester` studi seperti 7/9/11, bukan 2627/1. Belum ada tahun/term pembayaran pada file tersebut. BPP boleh dibaca sebagai status, tetapi belum boleh diklaim terkonfirmasi untuk semester berjalan tanpa konteks periode.
- Source aplikasi kedua repo identik pada rescan awal. Perbedaan deployment Docker tetap khusus dadu-test.

Checkpoint 65 hanya menambah dua file. Tidak membuat migration, tidak mengimpor data, tidak mengubah `phpunit.xml`/`.env`, dan tidak membuat akun baru. UI/hasil blocking lama belum berubah. Jangan memasang normalizer ke importer sendiri sebelum panduan integrasi berikutnya.

## 3. Kontrak pembaca dan perhatian review

File baru:

- `app/Support/AcademicProfileJsonNormalizer.php`
- `tests/Unit/AcademicProfileJsonNormalizerTest.php`

Interface:

```text
normalize(array $row, ?array $selectedMenus = null, ?string $financePeriod = null): array
```

Menghasilkan `['updates' => [...], 'skipped' => [...]]` dengan grup `skpi`, `sidang_approval`, `language`, `tak`, `bpp`. Tidak menyertakan grup berarti jangan mengubah penyimpanannya. `skipped` memberi alasan kegagalan tanpa menyalin error sumber yang mungkin berisi informasi sensitif.

- `selectedMenus=null`: baca field yang benar-benar ada. Daftar kosong: tidak ada menu yang dipilih. Tidak menyamakan kedua kondisi.
- SKPI/persetujuan yang secara eksplisit null menghasilkan status null/approved false. Field hilang tidak menghasilkan update. Nilai malformed menjadi skipped.
- Bahasa harus memiliki empat field ringkasan sekaligus. Jika hanya sebagian tersedia, jangan mencampur skor lama dan jumlah tes baru. Riwayat harus berbentuk daftar objek, tetapi isi/nilai/tanggalnya dipertahankan; tidak menghitung ulang nilai/jumlah.
- Null/angka 0 dibedakan. Boolean, nilai negatif, NaN, INF, jumlah pecahan, dan struktur salah tidak dikonversi menjadi nilai yang meloloskan syarat.
- BPP mengubah pasangan nyata PERLU VERIFIKASI+KREDIT menjadi label KREDIT, menyimpan status sumbernya juga. Parameter `financePeriod` hanya untuk konteks resmi yang diberikan importer berikutnya. Field semester 7/9 tidak menjadi periode pembayaran. Kode 2627/1 jika benar-benar tersedia bisa dibaca. Periode konteks yang bertentangan dengan baris menyebabkan grup BPP dilewati.
- Error dengan prefix menu seperti `tak:` hanya melewati grup itu. Error tanpa klasifikasi aman melewati seluruh grup yang hendak dibaca, tidak menulis null. Masalah menu yang tidak dipilih tidak menghapus apa pun.

Tidak memakai nilai BPP tanpa periode sebagai syarat tambahan, tidak memakai status approval foto BASILA sebagai persetujuan sidang, dan tidak memakai tanggal kedaluwarsa sebagai tanggal tes. Tidak membaca NIM/nama untuk menentukan kelayakan: pencocokan NIM+nama tetap tanggung jawab importer.

## 4. Tahap 1 —buat test dan lihat kegagalan awal

Jalankan di laptop development, bukan server Docker:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
php artisan make:test AcademicProfileJsonNormalizerTest --unit --pest
```

Isi **seluruh** `tests/Unit/AcademicProfileJsonNormalizerTest.php` berikut. Jangan mengganti file test TA lain.

```php
<?php

use App\Support\AcademicProfileJsonNormalizer as Normalizer;

function academicJson65Language(array $overrides = []): array
{
    return array_replace([
        'nilai_bahasa' => '430', 'jenis_tes_bahasa' => 'Online EPrT',
        'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [
            ['jenis_tes' => 'Online EPrT', 'nilai' => '430',
                'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku'],
        ],
    ], $overrides);
}

test('json65_missing_fields_mean_no_updates_instead_of_null_assignments', function () {
    expect((new Normalizer)->normalize(['nim' => '1202265001', 'nama' => 'Contoh Sintetis']))
        ->toBe(['updates' => [], 'skipped' => []]);
});

test('json65_skpi_uses_only_exact_status_skpi_and_ignores_three_flags', function () {
    $normalizer = new Normalizer;
    $approved = $normalizer->normalize(['status_skpi' => 'Disetujui',
        'skpi_status_1' => 'N', 'skpi_status_2' => 'N', 'skpi_status_3' => 'N']);
    expect($approved['updates']['skpi'])->toBe(['status' => 'Disetujui', 'approved' => true]);
    foreach (['Belum Disetujui', 'APPROVED', 'disetujui', null] as $status) {
        $result = $normalizer->normalize(['status_skpi' => $status,
            'skpi_status_1' => 'Y', 'skpi_status_2' => 'Y', 'skpi_status_3' => 'Y']);
        expect($result['updates']['skpi']['approved'])->toBeFalse();
    }
    expect($normalizer->normalize(['skpi_status_1' => 'Y'])['updates'])->toBe([]);
});

test('json65_sidang_approval_uses_explicit_summary_instead_of_numeric_status_ta', function () {
    $normalizer = new Normalizer;
    foreach (['1', '4'] as $raw) {
        $result = $normalizer->normalize(['status_ta' => $raw, 'persetujuan_maju_sidang' => 'APPROVED']);
        expect($result['updates']['sidang_approval']['approved'])->toBeTrue();
    }
    foreach (['BELUM DISETUJUI', null] as $status) {
        $result = $normalizer->normalize(['status_ta' => 'APPROVED', 'persetujuan_maju_sidang' => $status]);
        expect($result['updates']['sidang_approval']['approved'])->toBeFalse();
    }
    expect($normalizer->normalize(['status_ta' => 'APPROVED'])['updates'])->toBe([]);
});

test('json65_selected_menus_prevent_untouched_groups_from_being_overwritten', function () {
    $row = array_replace(academicJson65Language(), ['status_skpi' => null, 'total_tak' => null]);
    $result = (new Normalizer)->normalize($row, ['bahasa']);
    expect(array_keys($result['updates']))->toBe(['language']);
    expect((new Normalizer)->normalize($row, [])['updates'])->toBe([]);
});

test('json65_null_selected_menus_allow_present_fields_but_not_missing_ones', function () {
    $result = (new Normalizer)->normalize(['total_tak' => '80'], null);
    expect($result['updates'])->toBe(['tak' => ['score' => 80.0]]);
});

test('json65_rejects_malformed_selected_menus', function () {
    foreach ([['tak' => true], [true], [null]] as $menus) {
        expect(fn () => (new Normalizer)->normalize(['total_tak' => '80'], $menus))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('json65_language_uses_authoritative_score_and_count_without_recalculating_history', function () {
    $row = academicJson65Language(['nilai_bahasa' => '440', 'jumlah_tes_bahasa' => 3,
        'riwayat_nilai_bahasa' => [
            ['jenis_tes' => 'Online EPrT', 'nilai' => '600', 'status_berlaku' => 'Kedaluwarsa'],
        ]]);
    $language = (new Normalizer)->normalize($row)['updates']['language'];
    expect($language['score'])->toBe(440.0);
    expect($language['attempts'])->toBe(3);
    expect($language['history'])->toBe($row['riwayat_nilai_bahasa']);
});

test('json65_language_accepts_ecct_null_score_and_official_three_test_count', function () {
    $row = academicJson65Language(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT',
        'riwayat_nilai_bahasa' => []]);
    $language = (new Normalizer)->normalize($row)['updates']['language'];
    expect($language['score'])->toBeNull();
    expect($language['type'])->toBe('Online ECCT');
    expect($language['attempts'])->toBe(3);
});

test('json65_language_does_not_restrict_accepted_types_to_eprt', function () {
    foreach (['Online EPrT', 'ITP TOEFL', 'Online ECCT'] as $type) {
        $result = (new Normalizer)->normalize(academicJson65Language(['jenis_tes_bahasa' => $type]));
        expect($result['updates']['language']['type'])->toBe($type);
    }
});

test('json65_language_preserves_zero_and_distinguishes_missing_count', function () {
    $result = (new Normalizer)->normalize(academicJson65Language(['nilai_bahasa' => '0', 'jumlah_tes_bahasa' => '0']));
    expect($result['updates']['language']['score'])->toBe(0.0);
    expect($result['updates']['language']['attempts'])->toBe(0);
    $result = (new Normalizer)->normalize(academicJson65Language(['jumlah_tes_bahasa' => null]));
    expect($result['updates']['language']['attempts'])->toBeNull();
});

test('json65_language_is_atomic_when_one_summary_field_is_missing', function () {
    $row = academicJson65Language();
    unset($row['jumlah_tes_bahasa']);
    $result = (new Normalizer)->normalize($row);
    expect($result['updates'])->not->toHaveKey('language');
    expect($result['skipped'])->toHaveKey('language');
});

test('json65_language_rejects_invalid_counts_without_coercing_them', function () {
    foreach ([true, false, -1, 2.5, '2.5', [], 'tiga', INF] as $count) {
        $result = (new Normalizer)->normalize(academicJson65Language(['jumlah_tes_bahasa' => $count]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_invalid_score_does_not_silently_become_zero_or_null', function () {
    foreach ([true, false, -1, 'invalid', [], INF, NAN] as $score) {
        $result = (new Normalizer)->normalize(academicJson65Language(['nilai_bahasa' => $score]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_history_keeps_source_dates_and_ecct_values_without_treating_expiry_as_test_date', function () {
    $history = [['jenis_tes' => 'Online ECCT', 'nilai' => '3,5',
        'kedaluwarsa' => '19 Mei 2028', 'status_berlaku' => 'Berlaku']];
    $result = (new Normalizer)->normalize(academicJson65Language(['riwayat_nilai_bahasa' => $history]));
    expect($result['updates']['language']['history'])->toBe($history);
    expect($result['updates']['language']['history'][0])->not->toHaveKey('test_date');
});

test('json65_language_rejects_invalid_history_shape', function () {
    foreach ([null, 'invalid', ['nilai' => '430'], [430], [[]]] as $history) {
        $result = (new Normalizer)->normalize(academicJson65Language(['riwayat_nilai_bahasa' => $history]));
        expect($result['updates'])->not->toHaveKey('language');
        expect($result['skipped'])->toHaveKey('language');
    }
});

test('json65_tak_null_zero_and_confirmed_total_remain_distinct', function () {
    foreach ([[null, null], ['0', 0.0], ['60', 60.0], ['80', 80.0]] as [$raw, $expected]) {
        $result = (new Normalizer)->normalize(['total_tak' => $raw]);
        expect($result['updates']['tak']['score'])->toBe($expected);
    }
});

test('json65_tak_error_preserves_tak_and_keeps_other_successful_groups', function () {
    $row = ['total_tak' => null, 'status_skpi' => 'Disetujui',
        'error' => 'tak: Token gateway TAK sudah kedaluwarsa.'];
    $result = (new Normalizer)->normalize($row);
    expect($result['updates'])->not->toHaveKey('tak');
    expect($result['skipped'])->toHaveKey('tak');
    expect($result['updates']['skpi']['approved'])->toBeTrue();
});

test('json65_multiple_tagged_errors_skip_only_failed_groups', function () {
    $row = array_replace(academicJson65Language(), ['total_tak' => null, 'status_skpi' => null,
        'error' => "tak: gagal; skpi: gagal"]);
    $result = (new Normalizer)->normalize($row);
    expect(array_keys($result['updates']))->toBe(['language']);
    expect(array_keys($result['skipped']))->toBe(['skpi', 'tak']);
});

test('json65_unclassified_error_preserves_all_recognized_groups', function () {
    foreach (['Pengambilan data gagal.', ['tak' => 'gagal']] as $error) {
        $result = (new Normalizer)->normalize(['total_tak' => null, 'status_skpi' => null, 'error' => $error]);
        expect($result['updates'])->toBe([]);
        expect($result['skipped'])->toHaveKeys(['skpi', 'tak']);
    }
});

test('json65_bpp_maps_real_credit_status_and_does_not_invent_a_period_from_study_semester', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'PERLU VERIFIKASI',
        'status_keuangan_asli' => 'KREDIT', 'semester' => '7'])['updates']['bpp'];
    expect($bpp['status'])->toBe('KREDIT');
    expect($bpp['source_status'])->toBe('PERLU VERIFIKASI');
    expect($bpp['source_semester'])->toBe('7');
    expect($bpp['period'])->toBeNull();
});

test('json65_bpp_accepts_explicit_confirmed_import_period', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS',
        'status_keuangan_asli' => 'SUDAH LUNAS', 'semester' => '9'], ['keuangan'], '2627/1')['updates']['bpp'];
    expect($bpp['status'])->toBe('LUNAS');
    expect($bpp['period'])->toBe('2627/1');
    expect($bpp['source_semester'])->toBe('9');
});

test('json65_bpp_can_read_actual_period_code_if_it_is_present_in_source', function () {
    $bpp = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '2627/1'])['updates']['bpp'];
    expect($bpp['period'])->toBe('2627/1');
});

test('json65_bpp_rejects_invalid_or_conflicting_period_without_losing_skpi_update', function () {
    foreach (['7', '2026/2027', '2627/3', '2627/2'] as $period) {
        $result = (new Normalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '2627/1',
            'status_skpi' => 'Disetujui'], null, $period);
        expect($result['updates'])->not->toHaveKey('bpp');
        expect($result['skipped'])->toHaveKey('bpp');
        expect($result['updates']['skpi']['approved'])->toBeTrue();
    }
});

test('json65_does_not_mutate_source_input_or_leak_ignored_identifiers_into_updates', function () {
    $row = array_replace(academicJson65Language(), ['nim' => '1202265001', 'nama' => 'Contoh Sintetis']);
    $copy = $row;
    $result = (new Normalizer)->normalize($row);
    expect($row)->toBe($copy);
    expect($result['updates'])->not->toHaveKey('nim')->not->toHaveKey('nama');
});

test('json65_result_from_previous_call_is_not_kept_for_another_student', function () {
    $normalizer = new Normalizer;
    expect($normalizer->normalize(['status_skpi' => 'Disetujui'])['updates']['skpi']['approved'])->toBeTrue();
    expect($normalizer->normalize([]))->toBe(['updates' => [], 'skipped' => []]);
});
```

Jalankan test baru sebelum class dibuat:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/AcademicProfileJsonNormalizerTest.php --stop-on-failure
```

Hasil awal diharapkan gagal karena class belum ada. Bila class sudah terlanjur dibuat pada tahap berikutnya, tidak perlu menghapusnya untuk mengulang kegagalan ini.

## 5. Tahap 2 —buat class dan isi kode

```bash
php artisan make:class Support/AcademicProfileJsonNormalizer
```

Isi **seluruh** `app/Support/AcademicProfileJsonNormalizer.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

/** Reads one imported row. No database, HTTP, or eligibility decisions. */
class AcademicProfileJsonNormalizer
{
    private const GROUPS = [
        'skpi' => ['skpi', ['status_skpi']],
        'sidang_approval' => ['maju_sidang', ['persetujuan_maju_sidang']],
        'language' => ['bahasa', ['nilai_bahasa', 'jenis_tes_bahasa', 'jumlah_tes_bahasa', 'riwayat_nilai_bahasa']],
        'tak' => ['tak', ['total_tak']],
        'bpp' => ['keuangan', ['status_keuangan']],
    ];

    /**
     * Missing groups are intentionally absent from updates: preserve old data.
     * financePeriod comes from confirmed import context, never the current date.
     */
    public function normalize(array $row, ?array $selectedMenus = null, ?string $financePeriod = null): array
    {
        if ($selectedMenus !== null && (!array_is_list($selectedMenus)
            || array_filter($selectedMenus, fn ($menu) => !is_string($menu)) !== [])) {
            throw new InvalidArgumentException('selected_menus harus berupa daftar nama menu.');
        }

        $result = ['updates' => [], 'skipped' => []];
        $failures = $this->failures($row['error'] ?? null);
        foreach (self::GROUPS as $group => [$menu, $fields]) {
            if ($selectedMenus !== null && !in_array($menu, $selectedMenus, true)) continue;
            $present = array_filter($fields, fn ($key) => array_key_exists($key, $row));
            if ($present === []) continue;
            if (in_array('*', $failures, true) || in_array($menu, $failures, true)) {
                $result['skipped'][$group] = 'Pengambilan data menu '.$menu.' gagal.';
                continue;
            }

            try {
                $result['updates'][$group] = match ($group) {
                    'skpi' => $this->approval($row['status_skpi'], 'Disetujui'),
                    'sidang_approval' => $this->approval($row['persetujuan_maju_sidang'], 'APPROVED'),
                    'language' => $this->language($row),
                    'tak' => ['score' => $this->number($row['total_tak'])],
                    'bpp' => $this->bpp($row, $financePeriod),
                };
            } catch (InvalidArgumentException $exception) {
                $result['skipped'][$group] = $exception->getMessage();
            }
        }
        return $result;
    }

    private function approval(mixed $value, string $accepted): array
    {
        $status = $this->text($value);
        return ['status' => $status, 'approved' => $status === $accepted];
    }

    private function language(array $row): array
    {
        foreach (self::GROUPS['language'][1] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new InvalidArgumentException('Ringkasan bahasa tidak lengkap; pertahankan data sebelumnya.');
            }
        }
        $history = $row['riwayat_nilai_bahasa'];
        if (!is_array($history) || !array_is_list($history)) {
            throw new InvalidArgumentException('Riwayat bahasa harus berupa daftar.');
        }
        foreach ($history as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                throw new InvalidArgumentException('Setiap riwayat bahasa harus berupa objek.');
            }
        }
        return [
            'score' => $this->number($row['nilai_bahasa']),
            'type' => $this->text($row['jenis_tes_bahasa']),
            'attempts' => $this->count($row['jumlah_tes_bahasa']),
            'history' => $history,
        ];
    }

    private function bpp(array $row, ?string $financePeriod): array
    {
        $status = $this->text($row['status_keuangan']);
        $raw = $this->text($row['status_keuangan_asli'] ?? null);
        $semester = $row['semester'] ?? null;
        $semester = $this->text(is_int($semester) ? (string) $semester : $semester);
        $rowPeriod = $semester !== null && preg_match('/^\d{4}\/[12]$/D', $semester) === 1 ? $semester : null;
        if ($financePeriod !== null) {
            $financePeriod = trim($financePeriod);
            if (preg_match('/^\d{4}\/[12]$/D', $financePeriod) !== 1) {
                throw new InvalidArgumentException('Periode BPP harus berbentuk 2627/1 atau 2627/2.');
            }
            if ($rowPeriod !== null && $rowPeriod !== $financePeriod) {
                throw new InvalidArgumentException('Periode BPP pada baris berbeda dengan konteks impor.');
            }
        }
        return [
            'status' => $status === 'PERLU VERIFIKASI' && $raw === 'KREDIT' ? 'KREDIT' : $status,
            'source_status' => $status,
            'raw_status' => $raw,
            'source_semester' => $semester,
            'period' => $financePeriod ?? $rowPeriod,
        ];
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Status atau jenis tes harus berupa teks atau null.');
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || is_string($value) && trim($value) === '') return null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)
            || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException('Nilai numerik harus berupa angka nonnegatif atau null.');
        }
        return (float) $value;
    }

    private function count(mixed $value): ?int
    {
        if ($value === null || is_string($value) && trim($value) === '') return null;
        if ((!is_int($value) && !is_string($value))
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new InvalidArgumentException('Jumlah tes harus berupa bilangan bulat nonnegatif atau null.');
        }
        return (int) $value;
    }

    private function failures(mixed $error): array
    {
        if ($error === null || $error === '') return [];
        if (!is_string($error)) return ['*'];
        $error = trim($error);
        if ($error === '') return [];
        preg_match_all('/(?:^|[;\r\n])\s*(akademik|bahasa|tak|keuangan|mata_kuliah|jadwal_sidang|skpi|maju_sidang|basila_foto)\s*:/i', $error, $matches);
        return $matches[1] === [] ? ['*'] : array_unique(array_map('strtolower', $matches[1]));
    }
}
```

## 6. Tahap 3 —lint dan jalankan test

```bash
php -l app/Support/AcademicProfileJsonNormalizer.php
php -l tests/Unit/AcademicProfileJsonNormalizerTest.php
```

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/AcademicProfileJsonNormalizerTest.php
```

Target: **25 test baru passed**. Test murni, tidak memakai RefreshDatabase/model/HTTP; environment Podman tetap diberikan agar konsisten dengan alur lokalmu. Tidak perlu SQLite.

Regresi kontrak policy/required upload yang sekarang masih dipertahankan:

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Unit/AcademicProfileJsonNormalizerTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```

Target fokus **46 passed** bila 21 test lama belum berubah. Policy lama memang belum diubah pada 65: test lama null+3→unknown tetap benar untuk kode lama, sementara kontrak terbaru pada bagian1 akan dipasang bersama adapter/database/finalizer pada checkpoint berikutnya. Jangan mengganti assertion lama sendiri agar seolah integrasi sudah selesai.

Tidak perlu mengulang test browser/Node karena file JS, Blade, controller, importer dan policy belum berubah. Tidak menjalankan full suite non-TA yang masih mempunyai temuan Auth61f.

## 7. Tahap 4 —uji pembaca melalui Tinker, opsional

```bash
php artisan tinker
```

Tempel satu baris lengkap, bukan dipotong pada awal operator:

```php
dump((new App\Support\AcademicProfileJsonNormalizer)->normalize(['nilai_bahasa' => null, 'jenis_tes_bahasa' => 'Online ECCT', 'jumlah_tes_bahasa' => 3, 'riwayat_nilai_bahasa' => []], ['bahasa']));
```

Harus menghasilkan `updates.language.score=null`, `type=Online ECCT`, `attempts=3`, `history=[]`, `skipped=[]`. Ini hanya pembacaan; belum menampilkan upload Surat Permakluman pada halaman.

```php
dump((new App\Support\AcademicProfileJsonNormalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '7'], ['keuangan']));
```

Harus menghasilkan BPP status LUNAS dengan `period=null`, bukan mengarang 2627/1. Contoh konteks periode yang nanti diberikan importer:

```php
dump((new App\Support\AcademicProfileJsonNormalizer)->normalize(['status_keuangan' => 'LUNAS', 'semester' => '7'], ['keuangan'], '2627/1'));
```

Harus menghasilkan `period=2627/1`. Parameter ini bukan instruksi menyisipkan periode palsu ke JSON nyata. Sumber konteks resmi dan penyimpanannya dikerjakan pada integrasi berikutnya; file sekarang belum memuatnya.

Keluar Tinker:

```php
exit;
```

## 8. Tahap 5 —salin dua file setelah test lulus

Jalankan pada laptop:

```bash
bash <<'BASH'
set -euo pipefail
task_source=/home/xdranel/Code/Project/php-laravel/dadu
task_target=/home/xdranel/Code/Project/secret/dadu-test
task_files=(
  app/Support/AcademicProfileJsonNormalizer.php
  tests/Unit/AcademicProfileJsonNormalizerTest.php
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

Tidak menyalin JSON nyata dari Downloads, tidak menghapus pengajuan/draft, dan belum perlu rebuild/deploy server. Laporkan hasil test dan salinan agar asisten melakukan rescan.

## 9. Urutan berikutnya setelah 65 lulus

1. Hubungkan normalizer ke importer dengan penyimpanan ringkasan bahasa, persetujuan, dan konteks BPP; pertahankan data menu yang tidak dipilih/error. Riwayat hasil impor diperbarui per mahasiswa dan tidak mencampur baris milik sumber lain.
2. Ubah pembacaan fakta/policy TA agar memakai `status_skpi`, ringkasan nilai/jumlah resmi, dan persetujuan terbaru. Null score+jumlah≥3 mengikuti Surat Permakluman. Perbarui fixture test lama yang masih memakai bentuk sementara, termasuk dummy JSON, pada checkpoint yang sama.
3. Terima nama KRS TUGAS AKHIR/TUGAS AKHIR 2 pada helper TA dan pembacaan data kampus yang terkait, tetap berdasarkan periode akademik aktif. Sesuaikan test lama yang sengaja menolak nama generik.
4. Tampilkan persetujuan/bahasa/BPP pada form dan snapshot historis secara konsisten; BPP tanpa periode tetap diberi keterangan belum terkonfirmasi, bukan diklaim semester berjalan. Finalizer membaca fakta baru kembali, snapshot final tidak bergantung data live.
5. Uji impor ulang, sebagian menu, error TAK, KREDIT→LUNAS periode sama, mahasiswa lain tidak berubah, ECCT null+3, nilai 450, dan perubahan fakta sesudah tinjau. Setelah seluruh rangkaian selesai barulah uji staging Docker mengikuti 64.

Tidak ada permintaan konfirmasi aturan yang menahan checkpoint 65. Ketiadaan periode BPP pada contoh nyata hanya membatasi klaim periode, bukan menambah syarat kirim mahasiswa. Jangan menonaktifkan lima syarat akademik untuk mengatasi data yang belum diimpor.

## 10. Verifikasi panduan oleh asisten

Sebelum dan sesudah penulisan, source/deployment kedua repo discan dan tetap tidak berubah. Snippet class/test lulus `php -l`. 25 closure test yang sama dijalankan secara standalone memakai assertion Pest/PHPUnit vendor dengan konfigurasi kosong di `/tmp`, tanpa bootstrap Laravel/DB: 25 lulus. Ini bukan laporan `php artisan test` dari aplikasi pengguna.

Probe pembaca pada 260 baris JSON nyata, hanya di memori tanpa impor DB: 36 status SKPI approved, 6 persetujuan sidang approved, 260 grup bahasa terbaca, 101 grup TAK dilewati karena error, 260 BPP belum mempunyai periode terkonfirmasi. Angka tersebut adalah baris dari tiga file, bukan jumlah mahasiswa unik lintas file. Tidak menyimpan identitas/data nyata ke repo.

Referensi CLI/testing: [Laravel 13 testing](https://laravel.com/docs/13.x/testing), diperiksa melalui Context7 dan signature command vendor lokal.
