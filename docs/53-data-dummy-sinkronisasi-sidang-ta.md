# 53 — Data dummy hasil meet melalui Sinkronisasi Data

**Tujuan:** menyediakan JSON dummy yang didukung importer untuk menguji SKPI, TAK, EPRT, dan KRS TA 2 sebelum data resmi tersedia.
**Status:** checkpoint 52 dilaporkan lulus unit/manual; sepuluh file tersalin dan identik. KRS sudah dikonfirmasi sebagai **TUGAS AKHIR 2**. Pengguna mengizinkan data dummy; tiga JSON dibuat di `docs/fixtures/sidang-ta/`. Kode aplikasi tetap tidak diubah asisten.

## 1. Berkas dan skenario

Tiga file JSON sudah dibuat di workspace:

- [01 — Profil mahasiswa](fixtures/sidang-ta/01-mahasiswa-dummy.json)
- [02 — KRS TA 2](fixtures/sidang-ta/02-krs-ta2-dummy.json)
- [03 — Profil akademik](fixtures/sidang-ta/03-profil-akademik-dummy.json)

Semua memakai wrapper `source` dan `data` yang diterima importer. Empat NIM baru sengaja dipisahkan agar percobaan EPRT dan skor dari skenario berbeda tidak bercampur. Akun serta data Krisna tidak ditimpa.

| NIM | Nama | Lima syarat akademik | EPRT | Hasil adapter 50 |
| --- | --- | --- | --- | --- |
| 1202299001 | Dummy TA EPRT Otomatis | Semua memenuhi | 450, 1 tes | Lulus akademik, automatic |
| 1202299002 | Dummy TA Surat Permakluman | Semua memenuhi | 449, 3 tes | Lulus akademik, waiver |
| 1202299003 | Dummy TA Tes EPRT Ulang | Semua memenuhi | 449, 2 tes | Lulus akademik, retest |
| 1202299004 | Dummy TA TAK Kurang | TAK 59, empat syarat lain memenuhi | 450, 1 tes | Hanya TAK menjadi blocker, automatic |

Hasil ini adalah keluaran adapter, bukan bukti bahwa pengiriman sudah diizinkan. Form meet dan aturan penolakan saat Kirim belum diaktifkan. Tanggal tes dummy adalah 1–3 Oktober 2026; TAK tidak memiliki tanggal kedaluwarsa. KRS memakai semester studi 8, periode Ganjil 2026/2027. Data ini hanya untuk pengujian lokal.

## 2. Salin fixture ke folder asli

Jalankan di folder asli:

```bash
cd /home/xdranel/Code/Project/php-laravel/dadu
mkdir -p docs/fixtures/sidang-ta
cp /home/xdranel/Code/Project/secret/dadu-test/docs/fixtures/sidang-ta/*.json docs/fixtures/sidang-ta/
```

Opsional: salin ke subfolder dummy di lokasi backup yang diminta, agar terpisah dari JSON resmi:

```bash
mkdir -p '/home/xdranel/Code/Project/secret/ssc-final-project-test/Dadu JSON BACKUP/dummy-sidang-ta'
cp docs/fixtures/sidang-ta/*.json '/home/xdranel/Code/Project/secret/ssc-final-project-test/Dadu JSON BACKUP/dummy-sidang-ta/'
```

Command penyalinan ke folder backup dijalankan pengguna. Asisten hanya membuat artefak di workspace. Impor setiap file secara terpisah dengan tipe yang sesuai pada IT Panel.

## 3. Test integrasi sebelum impor manual

Buat file test baru: `tests/Feature/SidangTaDummyImportTest.php`.

```bash
php artisan make:test SidangTaDummyImportTest --pest
```

Isi seluruh file dengan kode berikut:

```php
<?php

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentCourse;
use App\Models\StudentEprt;
use App\Models\StudentSkpi;
use App\Models\StudentTak;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\SidangTaAdmissionFacts;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function meetDummyJson(string $filename): array
{
    return json_decode(file_get_contents(base_path('docs/fixtures/sidang-ta/'.$filename)), true, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', config('app.timezone')));
    $user = User::factory()->create(['status' => 'active', 'auth_source' => 'manual', 'must_change_password' => false]);
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'sync_execute', 'guard_name' => 'web']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    AcademicPeriod::query()->update(['is_active' => false]);
    $this->academicPeriod = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Dummy import', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $this->importDummy = function (string $type, string $filename, ?array $payload = null) {
        $payload ??= meetDummyJson($filename);
        return $this->from(route('sync.index'))->post(route('sync.import-json'), [
            'type' => $type,
            'json_file' => UploadedFile::fake()->createWithContent($filename, json_encode($payload, JSON_THROW_ON_ERROR)),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    };
    $this->importAll = function () {
        ($this->importDummy)('student', '01-mahasiswa-dummy.json');
        ($this->importDummy)('krs', '02-krs-ta2-dummy.json');
        ($this->importDummy)('academic_profile', '03-profil-akademik-dummy.json');
    };
});

afterEach(function () { $this->travelBack(); });

test('meet_dummy_json_import_reaches_admission_facts_for_four_scenarios', function () {
    ($this->importAll)();
    foreach ([
        ['1202299001', true, 'automatic', 1],
        ['1202299002', true, 'waiver', 3],
        ['1202299003', true, 'retest', 2],
        ['1202299004', false, 'automatic', 1],
    ] as [$nim, $academicPassed, $mode, $attempts]) {
        $student = Student::where('student_number', $nim)->firstOrFail();
        $result = app(SidangTaAdmissionFacts::class)->forStudent($student);
        expect($result['academic']['academic_passed'])->toBe($academicPassed);
        expect($result['eprt']['mode'])->toBe($mode);
        expect($result['eprt_facts']['confirmed_attempts'])->toBe($attempts);
        expect($result['facts']['skpi_approved'])->toBeTrue();
        expect($result['facts']['level_2_passed'])->toBeTrue();
        expect($result['krs_status'])->toBe('eligible');
        $krs = StudentCourse::where('student_number', $nim)->firstOrFail();
        expect((int) $krs->academic_period_id)->toBe($this->academicPeriod->id);
        if (!$academicPassed) expect(array_keys($result['academic']['blockers']))->toBe(['tak_score']);
    }
    $log = SyncLog::where('module', 'academic_profile')->latest('id')->firstOrFail();
    expect((int) $log->skipped_records)->toBe(0);
    expect($log->status)->toBe('completed');
});

test('meet_dummy_import_repeat_does_not_inflate_eprt_attempts_or_duplicate_records', function () {
    ($this->importAll)();
    ($this->importAll)();
    $nims = ['1202299001', '1202299002', '1202299003', '1202299004'];
    expect(Student::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentCourse::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentSkpi::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentTak::whereIn('student_number', $nims)->count())->toBe(4);
    expect(StudentEprt::whereIn('student_number', $nims)->count())->toBe(7);
    $student = Student::where('student_number', '1202299002')->firstOrFail();
    $result = app(SidangTaAdmissionFacts::class)->forStudent($student);
    expect($result['eprt_facts']['confirmed_attempts'])->toBe(3);
    expect($result['eprt']['mode'])->toBe('waiver');
});

test('meet_dummy_academic_profile_name_mismatch_skips_without_writing_approval_or_scores', function () {
    ($this->importDummy)('student', '01-mahasiswa-dummy.json');
    $row = meetDummyJson('03-profil-akademik-dummy.json')['data'][0];
    $row['nama'] = 'Nama sengaja berbeda';
    ($this->importDummy)('academic_profile', '03-profil-akademik-dummy.json', ['data' => [$row]]);
    $log = SyncLog::where('module', 'academic_profile')->latest('id')->firstOrFail();
    expect((int) $log->skipped_records)->toBe(1);
    expect(StudentSkpi::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(StudentEprt::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(StudentTak::where('student_number', $row['nim'])->exists())->toBeFalse();
    expect(Student::where('student_number', $row['nim'])->value('name'))->toBe('Dummy TA EPRT Otomatis');
});

```

Test menggunakan endpoint upload IT Panel dan importer yang sudah ada. Selain memeriksa empat skenario, test memastikan impor ulang tidak menggandakan percobaan dan nama yang berbeda ditolak. Waktu test dibekukan pada 4 November 2026. Tidak diperlukan seed atau reset database aplikasi.

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDummyImportTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDummyImportTest.php tests/Feature/SidangTaAdmissionFactsTest.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```

Target: **3 test baru**, atau **36 passed** untuk command gabungan. Feature TA bertambah tiga dari baseline checkpoint 52 (213 jika sebelumnya 210); Unit TA 21 dan Node 12 tetap. Salin JSON pada bagian 2 dahulu agar file dapat dibaca test. Impor manual ke database aplikasi tidak diperlukan untuk menjalankan test pada `dadu_testing`.

## 4. Cek periode akademik sebelum impor manual

Periksa lewat Tinker:

```bash
php artisan tinker
```

```php
dump(App\Models\AcademicPeriod::where('is_active', true)->get(['id', 'year', 'semester', 'label'])->toArray());
```

Fixture utama membutuhkan **satu periode aktif dengan year `2026/2027` dan semester `1`**. Ini periode akademik KRS, berbeda dari periode pendaftaran sidang TA.

Jika periode aplikasi berbeda, ubah **salinan file 02 di folder backup dummy**: sesuaikan `tahun_akademik` dan `semester_term` pada keempat baris. Term adalah `GANJIL` untuk semester 1 atau `GENAP` untuk semester 2. Angka `semester: 8` tetap semester studi mahasiswa.

Jangan mengubah fixture utama di `docs/fixtures/` untuk keperluan manual: test otomatis memang membuat periode Ganjil 2026/2027. Tidak perlu mengganti periode aplikasi hanya untuk mengikuti dummy. Jika belum ada periode akademik aktif, atur melalui pengaturan yang biasa dipakai, lalu sesuaikan salinan JSON.

## 5. Urutan impor IT Panel

Login dengan akun yang memiliki izin Sinkronisasi Data. Buka IT Panel → Sinkronisasi Data, lalu:

1. Pilih **Profil Mahasiswa (student)** dan unggah file 01. Empat mahasiswa baru harus terbentuk.
2. Pilih **KRS Mahasiswa (krs)** dan unggah file 02. Gunakan salinan yang sudah disesuaikan jika periode aplikasi berbeda.
3. Pilih **Profil Akademik (iGracias + BASILA)** dan unggah file 03. Importer mencocokkan NIM dan nama, sehingga mahasiswa harus sudah ada terlebih dahulu.
4. Periksa log `academic_profile`: seharusnya 4 diproses, 0 dilewati, tanpa mismatch atau error. Pesan sukses saja belum cukup karena impor parsial juga bisa menghasilkan pesan sukses.

File 03 berisi `skpi.approval_status`, `tak.total_points`, dan daftar `eprt` dengan nomor percobaan, tanggal, serta skor total. Semua field tersebut sudah dibaca importer saat ini.

Impor ulang file yang sama seharusnya memperbarui data tanpa menambah jumlah tes. Importer **tidak menghapus tes lama yang tidak dikirim**. Karena itu, jangan mengganti skenario 450 menjadi 449 atau tiga tes menjadi dua tes pada NIM yang sama. Pakai empat mahasiswa yang sudah disediakan agar data lama tidak memengaruhi hasil.

## 6. Periksa hasil pembacaan fakta

Di Tinker database aplikasi:

```php
foreach (['1202299001', '1202299002', '1202299003', '1202299004'] as $nim) {
    $student = App\Models\Student::where('student_number', $nim)->firstOrFail();
    $result = app(App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
    dump([
        'nim' => $nim,
        'krs' => $result['krs_status'],
        'academic_passed' => $result['academic']['academic_passed'],
        'blockers' => $result['academic']['blockers'],
        'eprt' => $result['eprt']['mode'],
        'attempts' => $result['eprt_facts']['confirmed_attempts'],
    ]);
}
```

Hasil harus mengikuti tabel bagian 1. Jika KRS belum eligible, periksa tahun/term, `academic_period_id`, dan jumlah periode aktif. Jika skor atau SKPI belum diketahui, periksa log impor, tipe file, NIM, dan nama.

JSON membuat record **Student**, bukan akun **User**. Untuk login mahasiswa dummy, ikuti [55 bagian8A0](55-checkpoint-meet-e3-form-dan-tinjau-versi-dua.md): pembuatan akun lokal melalui Tinker. Form akun manual IT Panel hanya menyediakan role petugas, sehingga arahan sebelumnya membuat mahasiswa dari form itu tidak berlaku. Akun User tidak diperlukan untuk membaca Facts lewat Tinker atau menjalankan test53. Empat Student dummy ini belum otomatis mempunyai pengajuan TA.

## 7. Persetujuan sidang iGracias masih perlu dukungan importer

Importer belum menyimpan field persetujuan sidang iGracias. Approval foto BASILA mempunyai arti berbeda dan tidak digunakan sebagai penggantinya. Menambahkan field persetujuan sidang sekarang akan diabaikan importer.

Karena itu, tiga file impor tidak mengklaim telah mengisi persetujuan sidang. Berikut contoh kontrak dummy untuk dukungan field selanjutnya. **Jangan mengimpor contoh ini:** payload minimal juga dapat menyebabkan field lain yang tidak disertakan berubah menjadi nilai default.

```json
{
  "source": "DUMMY - PROPOSED CONTRACT, BELUM DIDUKUNG IMPORTER",
  "data": [
    {
      "nim": "1202299001",
      "nama": "Dummy TA EPRT Otomatis",
      "sidang_ta_approval": {
        "status": "approved",
        "approved_at": "2026-10-01T09:00:00+07:00"
      }
    }
  ]
}
```

Nama field ini masih usulan internal untuk dummy. Format resmi tetap menunggu konfirmasi. Dukungan penyimpanan dan normalisasi harus ditambahkan dahulu sebelum payload tersebut dapat diimpor. Persetujuan ini tidak menjadi syarat akademik keenam; pengguna menetapkan lima syarat akademik.

## 8. Pemindahan dan kelanjutan

Setelah test dan impor/Tinker berhasil, pindahkan `tests/Feature/SidangTaDummyImportTest.php` ke dadu-test. JSON utama sudah tersedia di workspace. Jangan mengganti fixture utama dengan salinan manual yang periodenya berbeda.

Langkah berikutnya adalah aktivasi persyaratan upload meet, kondisi EPRT, dan pemeriksaan saat Kirim, memakai data dummy ini sebagai bahan uji integrasi. Impor data saja belum mengaktifkan flow baru. Tunggu rescan akhir seluruh cakupan meet sebelum commit petugas.


## 9. Pemeriksaan oleh asisten

Ketiga JSON valid dan memuat empat pasangan NIM/nama yang cocok pada seluruh file. Tujuh catatan EPRT mempunyai nomor/tanggal unik per mahasiswa. Evaluasi empat skenario memakai class policy aktual menghasilkan keluaran yang diharapkan. Seluruh snippet PHP lulus pemeriksaan sintaks.

Review independen terhadap route, permission, importer dan skema tidak menemukan blocker pada format atau tiga test. Namun asisten belum menjalankan test database atau impor manual. Pemeriksaan di atas tidak menggantikan hasil test pada Podman. Source aplikasi kedua folder tetap tidak diubah; perubahan hanya pada Markdown dan JSON dummy yang diizinkan pengguna.
