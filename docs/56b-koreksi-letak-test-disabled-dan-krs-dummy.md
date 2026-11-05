# 56b — Letak test BPP, tombol disabled, dan KRS dummy 002

Lanjutan koreksi 56a. Terapkan di folder asli; asisten hanya menulis docs. Perubahan aplikasi tetap Anda lakukan dan uji dahulu, baru disalin ke `dadu-test`.

## 1. Hasil rescan sebelum perubahan

### Dua kegagalan test

Kedua test BPP ditemukan di `tests/Feature/SidangTaWizardUiTest.php`. File itu menggunakan helper `completeDraft`, sedangkan kedua test memanggil `meetDraft` dan `reviewPage`. Kedua helper tersebut tersedia di `tests/Feature/SidangTaMeetUiTest.php`. Itulah sebabnya gagal pada baris `$draft = ($this->meetDraft)()`.

Perbaikannya memindahkan kedua deklarasi test ke file yang mempunyai fixture/helper sesuai. Tidak perlu menambahkan helper baru atau mengubah assertion menjadi longgar. Import `Symfony\Component\DomCrawler\Crawler` juga sudah tersedia di file tujuan.

### Tombol Kirim

Kedua tombol dan submit guard sudah memakai `$sendBlocked` dengan benar. KRS yang unknown membuat tombol disabled sehingga modal memang tidak terbuka. Yang belum sesuai adalah **penampilan** tombol: belum ada class disabled seperti pada Tinjau/Lanjutkan di tahap lain. Di sini ditambahkan tampilan redup, kursor disabled, dan atribut aksesibilitas; gate tidak dilonggarkan.

### KRS dummy 002 — sudah terkonfirmasi dari Tinker

Hasil pengguna:

- `academic_periods` kosong.
- KRS NIM1202299002 bernama `TUGAS AKHIR 2`, tahun2026/2027.
- `academic_period_id` pada KRS masih null.

Resolver `ta2KrsStatus()` memerlukan tepat satu periode akademik aktif, kemudian mencari KRS mahasiswa yang terhubung ke periode itu. Nama matakuliah yang benar saja belum cukup. Importer KRS mencari periode berdasarkan tahun dan GANJIL/GENAP; importer tidak membuat periode akademik ketika master masih kosong.

Ini persoalan konfigurasi/relasi data lokal, bukan alasan untuk menerima KRS dari sembarang semester. **Periode akademik berbeda dengan periode pendaftaran Sidang TA.** Membuat Periode Sidang TA pada menu petugas tidak mengisi `academic_periods`.

Rescan lainnya: kode perubahan56a sudah sesuai selain penempatan kedua test; perbedaan pada beberapa file hanya baris kosong. Status BPP, card beranda, import ekspor, dan kondisi konfirmasi tidak perlu diulang dari awal.

## 2. Pindahkan dua test BPP

1. Buka `tests/Feature/SidangTaWizardUiTest.php`.
2. Hapus **seluruh deklarasi** dua test berikut dari file tersebut, mulai `test(...)` sampai penutup `});` milik masing-masing:
   - `meet_ui_bpp_is_visible_on_every_step_without_adding_an_academic_gate`
   - `meet_ui_missing_bpp_is_displayed_as_unavailable`
3. Pertahankan test lama di file itu, termasuk assertion positif Status BPP dari56a.
4. Buka **`tests/Feature/SidangTaMeetUiTest.php`**. Tambahkan kode berikut satu kali setelah test existing, pada level file, di luar closure test/beforeEach lain.

```php
test('meet_ui_bpp_is_visible_on_every_step_without_adding_an_academic_gate', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'paid']);
    foreach ([1, 2, 3, 4] as $step) {
        $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => $step]))
            ->assertOk()->getContent());
        $panel = $crawler->filter('[data-academic-panel]');
        expect($panel->text(null, true))->toContain('Status BPP')->toContain('Periode pembayaran belum terkonfirmasi');
        expect($panel->filter('[data-academic-field="bpp_status"]')->text(null, true))->toBe('Lunas');
    }
    $this->student->bppPayment()->firstOrFail()->update(['status' => 'unpaid']);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-admission-gate]')->count())->toBe(5);
    expect($crawler->filter('[data-open-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
    expect($crawler->filter('[data-confirm-registration]')->getNode(0)->hasAttribute('disabled'))->toBeFalse();
});
```

```php
test('meet_ui_missing_bpp_is_displayed_as_unavailable', function () {
    $draft = ($this->meetDraft)();
    $this->student->bppPayment()->delete();
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.edit', ['draft' => $draft, 'step' => 2]))
        ->assertOk()->getContent());
    expect($crawler->filter('[data-academic-panel] [data-academic-field="bpp_status"]')->text(null, true))->toBe('Belum tersedia');
});
```

Tidak ada file test baru yang perlu dibuat. Jangan menjalankan `make:test` untuk menimpa kedua file existing ini. Test BPP menggunakan `beforeEach`, `meetDraft`, dan `reviewPage` milik file tujuan.

## 3. Perjelas penampilan tombol disabled

Pada `resources/views/sidang_ta_registrations/wizard-review.blade.php`, lakukan tiga penggantian berikut. Contoh lengkap pada56/56a juga sudah diperbarui agar penggantian berikutnya tidak mengembalikan gaya lama.

### A. Hubungkan alasan dengan tombol

Cari:

```blade
<p data-meet-send-held role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
```

Ganti dengan:

```blade
<p id="registration-send-readiness" data-meet-send-held role="status" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
```

### B. Tombol pembuka modal — ganti seluruh element button

```blade
<button data-open-registration type="button"
                            class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                            :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                            @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                            @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                            aria-haspopup="dialog" aria-controls="confirm-send-registration"
                            onclick="document.getElementById('confirm-send-registration').showModal()">Kirim Pendaftaran
                    </button>
```

### C. Tombol konfirmasi — ganti seluruh element button

```blade
<button data-confirm-registration type="submit"
                                class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                                aria-disabled="{{ $sendBlocked ? 'true' : 'false' }}"
                                :aria-disabled="sending || {{ $sendBlocked ? 'true' : 'false' }} ? 'true' : 'false'"
                                @if($sendBlocked) aria-describedby="registration-send-readiness" @endif
                                @disabled($sendBlocked) :disabled="sending || {{ $sendBlocked ? 'true' : 'false' }}"
                                x-text="sending ? 'Sedang mengirim…' : '{{ $confirmLabel }}'">{{ $confirmLabel }}</button>
```

Tetap pertahankan `@submit="if (sending || ...` pada form dan seluruh modal. Tombol aktif hanya bila `can_submit` true; saat false tombol tidak membuka modal dan tampak disabled. Alasan tetap terlihat pada pemeriksaan akademik/EPRT.

Class ini memakai modifier [disabled Tailwind v3](https://v3.tailwindcss.com/docs/hover-focus-and-other-states#disabled), sama dengan pola yang sudah digunakan pada tombol Tinjau tahap4.

### Test tambahan untuk keadaan KRS dan tombol

Tambahkan test berikut satu kali pada **`tests/Feature/SidangTaMeetUiTest.php`**, setelah dua test BPP tadi. File ini menjadi **15 test**. Test memeriksa kondisi KRS unknown, tampilan/atribut kedua tombol, kemudian aktif kembali sesudah relasi periode dipulihkan. Modal dan tombol pembukanya tetap tersedia pada keadaan siap kirim.

```php
test('meet_ui_unconfirmed_krs_disables_send_visibly_and_reenables_after_period_link_is_restored', function () {
    $draft = ($this->meetDraft)();
    $krs = StudentCourse::where('student_number', $this->student->student_number)->firstOrFail();
    $periodId = $krs->academic_period_id;
    $krs->update(['academic_period_id' => null]);
    $crawler = new Crawler(($this->reviewPage)($draft)->getContent());
    expect($crawler->filter('[data-admission-gate="ta2_registered"]')->attr('data-gate-state'))->toBe('unknown');
    expect($crawler->filter('#registration-send-readiness')->count())->toBe(1);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeTrue();
        expect($button->attr(':disabled'))->toBe('sending || true');
        expect($button->attr('aria-disabled'))->toBe('true');
        expect($button->attr('aria-describedby'))->toBe('registration-send-readiness');
        expect($button->attr('class'))->toContain('disabled:opacity-50')->toContain('disabled:cursor-not-allowed');
    }
    $krs->update(['academic_period_id' => $periodId]);
    $crawler = new Crawler($this->get(route('sidang-ta-drafts.preview', $draft))->assertOk()->getContent());
    expect($crawler->filter('[data-admission-gate="ta2_registered"]')->attr('data-gate-state'))->toBe('passed');
    expect($crawler->filter('#registration-send-readiness')->count())->toBe(0);
    foreach (['[data-open-registration]', '[data-confirm-registration]'] as $selector) {
        $button = $crawler->filter($selector);
        expect($button->getNode(0)->hasAttribute('disabled'))->toBeFalse();
        expect($button->attr(':disabled'))->toBe('sending || false');
        expect($button->attr('aria-disabled'))->toBe('false');
    }
    expect($crawler->filter('[data-open-registration]')->attr('aria-controls'))->toBe('confirm-send-registration');
    expect($crawler->filter('#confirm-send-registration')->count())->toBe(1);
    expect(SidangTaRegistration::count())->toBe(0);
});
```

## 4. Tambahkan test importer untuk kasus periode kosong

Tambahkan test berikut di akhir **`tests/Feature/SidangTaDummyImportTest.php`**. File existing ini sudah mempunyai helper `importAll`/`importDummy`, import model, dan fixture yang dibutuhkan. Totalnya menjadi **4 test**.

```php
test('meet_dummy_krs_can_be_linked_by_reimport_after_academic_period_is_initialized', function () {
    $this->academicPeriod->delete();
    ($this->importAll)();
    $student = Student::where('student_number', '1202299002')->firstOrFail();
    $krs = StudentCourse::where('student_number', $student->student_number)->firstOrFail();
    expect($krs->course_name)->toBe('TUGAS AKHIR 2');
    expect($krs->academic_period_id)->toBeNull();
    expect(app(SidangTaAdmissionFacts::class)->forStudent($student)['krs_status'])->toBe('unknown');
    $period = AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
        'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    ($this->importDummy)('krs', '02-krs-ta2-dummy.json');
    $facts = app(SidangTaAdmissionFacts::class)->forStudent($student);
    expect((int) $krs->fresh()->academic_period_id)->toBe($period->id);
    expect($facts['krs_status'])->toBe('eligible');
    expect($facts['academic']['academic_passed'])->toBeTrue();
    expect($facts['eprt']['mode'])->toBe('waiver');
    expect(StudentCourse::whereIn('student_number', ['1202299001', '1202299002', '1202299003', '1202299004'])->count())->toBe(4);
    expect(StudentEprt::where('student_number', $student->student_number)->count())->toBe(3);
});
```

Kasus ini mengikuti error lokal yang benar-benar terjadi: impor KRS sebelum periode tersedia → status unknown → buat periode yang sesuai → impor ulang **KRS saja** → hubungan terbentuk tanpa menggandakan KRS atau riwayat EPRT.

## 5. Jalankan test sebelum manual data

### A. Tiga test UI yang dipindah/ditambahkan

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetUiTest.php --filter='meet_ui_bpp|meet_ui_missing_bpp|meet_ui_unconfirmed_krs'
```
Target3passed.

### B. Empat test dummy/importer

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaDummyImportTest.php
```
Target4passed.

### C. Regresi test yang tadi gagal karena salah file

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaWizardUiTest.php tests/Feature/SidangTaStaffNavigationTest.php
```
File WizardUi tidak lagi memuat test yang membutuhkan helper meetDraft.

### D. Fokus finalisasi, UI, dashboard, dan importer

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTaMeetFinalizationTest.php tests/Feature/SidangTaMeetDraftTest.php tests/Feature/SidangTaMeetUiTest.php tests/Feature/SidangTaFinalizationTest.php tests/Feature/SidangTaReviewExportTest.php tests/Feature/SidangTaDashboardCardTest.php tests/Feature/SidangTaDummyImportTest.php
```
Target **89 passed**: 27+14+15+13+10+6+4. Pemindahan dua test tidak menambah jumlah keseluruhan. Ada dua kasus baru dari56b: satu UI tombol/KRS dan satu importer.

### E. Seluruh Sidang TA

```bash
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing DB_USERNAME=dadu DB_PASSWORD=dadu_local_2026 DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_0900_ai_ci DB_SOCKET= php artisan test tests/Feature/SidangTa*Test.php tests/Unit/SidangTaAdmissionPolicyTest.php tests/Unit/SidangTaMeetRequirementsTest.php
```
Jika baseline56a berjumlah295kasus, target cakupan yang sama sekarang **297 passed**. Jalankan ketiga Node test seperti sebelumnya; kode JavaScript tidak berubah.

```bash
node tests/Js/sidang-ta-entry.test.mjs
node tests/Js/sidang-ta-wizard.test.mjs
node tests/Js/sidang-ta-document-preview.test.mjs
php artisan view:clear
```

Jalankan Vite seperti biasa. Jika memakai asset hasil build, jalankan `npm run build` agar gaya disabled tersedia. Tidak ada perubahan CSS global yang perlu ditulis.

## 6. Perbaiki data lokal dummy002 — setelah test lulus

Unit/Feature test menggunakan database `dadu_testing`, sedangkan manual browser menggunakan `dadu`. Membuat periode pada fixture test tidak membuat periode pada database browser.

Langkah di bawah khusus keadaan yang sudah Anda laporkan: master periode kosong dan dummy KRS GANJIL2026/2027. Tidak perlu mengubah policy, membuat pengajuan final, menghapus draft, atau menjalankan migrate:fresh.

### A. Buat periode akademik yang sesuai pada aplikasi lokal

Dari folder asli:

```bash
php artisan tinker
```

Tempel **blok lengkap** di bawah. Assignment closure dibuat terbuka sampai `});`, sehingga PsySH menunggu seluruh blok. Perintah menolak database yang bukan `dadu`, environment production, dummy yang tidak cocok, atau periode aktif lain. Bila periode target sudah ada, ia dipakai kembali; tidak membuat duplikat dan tidak menonaktifkan periode lain.

```php
$periodId = Illuminate\Support\Facades\DB::transaction(function () {
    if (app()->environment('production') || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'dadu') {
        throw new RuntimeException('Gunakan aplikasi lokal dengan database dadu.');
    }
    App\Models\Student::where('student_number', '1202299002')->where('email', 'dummy-ta-2@mahasiswa.test')->firstOrFail();
    $active = App\Models\AcademicPeriod::where('is_active', true)->lockForUpdate()->get();
    $period = App\Models\AcademicPeriod::where('year', '2026/2027')->where('semester', 1)->lockForUpdate()->first();
    foreach ($active as $row) {
        if ($period === null || (int) $row->id !== (int) $period->id) {
            throw new RuntimeException('Periode aktif lain ditemukan. Periksa konfigurasi; tidak ada periode yang dinonaktifkan otomatis.');
        }
    }
    if ($period === null) {
        $period = App\Models\AcademicPeriod::create(['year' => '2026/2027', 'semester' => 1,
            'label' => 'Ganjil 2026/2027', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    } elseif (!$period->is_active) {
        $period->update(['is_active' => true]);
    }
    return $period->id;
});
dump(['academic_period_id' => $periodId]);
```
Nilai tahun/semester mengikuti fixture: `2026/2027` dan **semester1 = Ganjil**. Ini bukan semester mahasiswa8. Tanggal mengikuti fixture test/dummy yang sudah digunakan, bukan perubahan jadwal periode pendaftaran Sidang TA.

Sesudah langkah ini, KRS lama masih mungkin null sampai langkah B dilakukan. Periode baru tidak otomatis memperbarui row KRS yang sudah diimpor sebelumnya.

### B. Import ulang hanya KRS melalui IT Panel

1. Login Superadmin/akun yang mempunyai akses Sinkronisasi Data.
2. IT Panel → Sinkronisasi Data → import JSON, pilih jenis **KRS**.
3. Pilih file yang sama: `docs/fixtures/sidang-ta/02-krs-ta2-dummy.json` pada folder asli. Tidak perlu membuat atau mengedit JSON baru.
4. Import file tersebut. Keempat NIM dummy diperbarui relasi periodenya melalui importer yang sama. Tidak menyentuh berkas draft atau mengirim pendaftaran.
5. **Tidak perlu mengimpor ulang mahasiswa, profil akademik, SKPI, TAK, atau EPRT.** Pemulihan ini hanya membutuhkan file KRS.

### C. Verifikasi baca saja

Di Tinker lokal, jalankan setiap baris lengkap berikut:

```php
dump(Illuminate\Support\Facades\DB::connection()->getDatabaseName());
dump(App\Models\AcademicPeriod::where('is_active', true)->get(['id', 'year', 'semester', 'is_active'])->toArray());
dump(App\Models\StudentCourse::where('student_number', '1202299002')->get(['course_name', 'academic_year', 'academic_period_id'])->toArray());
$owner = App\Models\User::where('email', 'dummy-ta-2@mahasiswa.test')->firstOrFail();
$student = app(App\Services\SidangTaStudentContext::class)->studentFor($owner);
$facts = app(App\Services\SidangTaAdmissionFacts::class)->forStudent($student);
dump(['student_number' => $student->student_number, 'krs_status' => $facts['krs_status'], 'academic_blockers' => $facts['academic']['blockers'], 'eprt' => $facts['eprt']]);
```
Hasil yang diharapkan:

- Database `dadu`.
- Tepat satu periode aktif2026/2027semester1.
- KRS dummy002 mempunyai `academic_period_id` sama dengan ID periode aktif tadi.
- `krs_status` = `eligible`.
- `academic_blockers` kosong jika empat gate lainnya masih sesuai dummy; EPRT mode `waiver`.

Jika muncul blocker lain, tangani alasan tersebut. Jangan mengaktifkan tombol secara paksa atau meloloskan data JSON resmi yang belum tersedia.

## 7. Manual tombol, modal, dan petugas

1. Sebelum memperbaiki data KRS, halaman Tinjau memperlihatkan alasan KRS belum terkonfirmasi; kedua tombol Kirim benar-benar disabled dengan tampilan redup/kursor disabled. Tidak terbukanya modal pada keadaan ini memang benar.
2. Sesudah langkah6, login dummy002 dan lanjutkan **draft versi2 yang sudah ada**. Tidak perlu membuat draft baru atau mengganti metadata versi lagi.
3. Tinjau ulang/hard refresh. KRS sekarang Terdaftar dan semua gate harus terpenuhi. Jika dokumen serta mode waiver lengkap, tombol Kirim menjadi aktif.
4. Klik Kirim: modal konfirmasi terbuka. Periksa kembali menutup modal tanpa mengirim; Ya mengirim pendaftaran.
5. Setelah pengiriman berhasil, pengajuan final dummy002 muncul pada Review Sidang TA petugas. Sebelum itu, draft tidak tampil sebagai pengajuan final.
6. Preview, snapshot dan ekspor tetap diuji seperti56a. Aksi verifikasi/keputusan versi2 tetap ditahan sementara sesuai cakupan56; checkpoint ini belum membuka alur petugas baru.

Jika langkah6 sudah menghasilkan eligible dan blocker kosong tetapi tombol tetap disabled, kirim hasil baca saja berikut (periksa dulu view:clear/hard refresh):

```php
$draft = App\Models\SidangTaRegistrationDraft::where('owner_user_id', $owner->id)->where('student_number', $student->student_number)->where('status', 'active')->latest()->first();
dump($draft?->only(['id', 'requirements_version', 'source_registration_id', 'version', 'reviewed_version']));
```
Variabel owner/student pada blok terakhir berasal dari langkah6C pada sesi Tinker yang sama. Jika sesi ditutup, jalankan ulang langkah6C dahulu. Jangan menjalankan query fluent yang dipotong menjadi baris baru diawali `->`.

## 8. Pemindahan setelah semua lulus

Belum ada file yang perlu dihapus dari project. Dua **deklarasi test** dipindah dari WizardUi ke MeetUi, bukan menghapus salah satu file test.

Karena perubahan56/56a belum dipindahkan, salin **21 file** berikut sesudah test/manual lulus. Ini 20file56a ditambah `SidangTaDummyImportTest.php` yang sekarang memiliki test import periode kosong.

- `app/Services/SidangTaDraftFinalizer.php`
- `app/Support/SidangTaReviewEligibility.php`
- `app/Models/SidangTaRegistration.php`
- `app/Services/SidangTaReviewExport.php`
- `app/Services/SidangTaRegistrationReview.php`
- `app/Http/Controllers/SidangTaRegistrationController.php`
- `resources/views/sidang_ta_registrations/admin/show.blade.php`
- `resources/views/sidang_ta_registrations/admin/index.blade.php`
- `resources/views/sidang_ta_registrations/partials/detail-academic.blade.php`
- `app/Http/Controllers/SidangTaDraftController.php`
- `resources/views/sidang_ta_registrations/wizard-review.blade.php`
- `tests/Feature/SidangTaMeetDraftTest.php`
- `tests/Feature/SidangTaMeetUiTest.php`
- `tests/Feature/SidangTaReviewExportTest.php`
- `tests/Feature/SidangTaMeetFinalizationTest.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `resources/views/sidang_ta_registrations/partials/wizard-academic.blade.php`
- `tests/Feature/SidangTaWizardUiTest.php`
- `tests/Feature/SidangTaDashboardCardTest.php`
- `tests/Feature/SidangTaDummyImportTest.php`

Tidak perlu menyalin `.env`, cache, hasil build, atau mengganti konfigurasi PHPUnit untuk koreksi ini. Periode akademik yang dibuat adalah data database lokal; tidak ada migration baru untuk dipindahkan.

## 9. Rescan dan verifikasi asisten

Sebelum perubahan dokumentasi: source kedua folder direkam hash, lokasi test/helper ditelusuri, kondisi tombol serta CSS dibandingkan tombol tahap lain, dan resolver/importer KRS diperiksa terhadap hasil Tinker pengguna. Tidak ada perubahan source selain yang sudah dilakukan pengguna.

Usulan koreksi di `/tmp` diperiksa dengan lint tiga file test lengkap serta blok Tinker, render Blade dalam memori104assertion untuk wizard/Tinjau (termasuk disabled/ARIA), dan22assertion beranda/panel BPP. Pemeriksaan independen terhadap fixture/importer/Tinker tidak menemukan blocker. Pemeriksaan ini tidak menjalankan Laravel bootstrap, Feature test DB, build, atau browser.

Setiap penulisan/perubahan docs diikuti rescan hash app/resources/routes/tests/database/migrations/config pada kedua folder. Source aplikasi tetap tidak diubah asisten. Test89/297 dan manual browser tetap perlu Anda jalankan sesudah menerapkan panduan.
