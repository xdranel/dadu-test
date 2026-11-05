# 10 — Hasil rescan folder asli: B dan C1

Scan langsung `/home/xdranel/Code/Project/php-laravel/dadu/`; tidak menunggu salinan ke dadu-test. Semua perubahan berikut dikerjakan pengguna. Asisten tidak mengubah source/database atau menjalankan suite aplikasi.

## 1. Tinggi: model dokumen final tertimpa

`app/Models/SidangTaRegistrationDocument.php` di folder asli sekarang berisi field `draft_id` dan relasi `draft()/sourceDocument()`. Ini seharusnya isi **model baru draft**, bukan pengganti model final. Akibatnya final upload tidak punya fillable `sidang_ta_registration_id`, dan download final kehilangan relasi `registration()`.

Pulihkan **seluruh** isi `app/Models/SidangTaRegistrationDocument.php` menjadi:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidangTaRegistrationDocument extends Model
{
    protected $fillable = [
        'sidang_ta_registration_id', 'document_type', 'file_name', 'file_path',
        'file_size', 'file_type', 'status', 'notes',
    ];

    public function registration()
    {
        return $this->belongsTo(SidangTaRegistration::class, 'sidang_ta_registration_id');
    }
}
```

Kemudian **buat file berbeda** `app/Models/SidangTaRegistrationDraftDocument.php` dengan kode lengkap pada [09 bagian 3](09-checkpoint-c1-struktur-draft-mysql.md#3-buat-model-draft). Nama class harus `SidangTaRegistrationDraftDocument`, bukan `SidangTaRegistrationDocument`.

## 2. Tinggi: relasi documents draft menunjuk model salah

Pada `app/Models/SidangTaRegistrationDraft.php`, ganti method documents menjadi:

```php
public function documents(): HasMany
{
    return $this->hasMany(SidangTaRegistrationDraftDocument::class, 'draft_id');
}
```

Model berbeda tersebut memakai tabel `sidang_ta_registration_draft_documents`. Jangan menambahkan kolom `draft_id` ke tabel final untuk menyembunyikan salah relasi.

## 3. Perbaiki test duplikasi agar tidak lolos akibat salah tabel

Test `draft_documents_have_unique_types_per_draft` pada panduan 09 sebelumnya memakai `throws(QueryException::class)` pada seluruh test. Ini terlalu luas: error salah kolom/tabel pada insert pertama juga dapat dianggap lulus. **Ganti test itu** di `tests/Feature/SidangTaDraftSchemaTest.php` dengan:

```php
test('draft_documents_have_unique_types_per_draft', function () {
    $draft = ($this->makeDraft)();
    $metadata = [
        'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
        'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
        'file_size' => 100, 'file_type' => 'pdf',
    ];

    $first = $draft->documents()->create($metadata);
    expect($first->getTable())->toBe('sidang_ta_registration_draft_documents');
    expect($first->exists)->toBeTrue();

    $duplicateRejected = false;
    try {
        $draft->documents()->create($metadata);
    } catch (QueryException $exception) {
        // 1062 adalah duplicate entry MySQL, bukan unknown column / table missing.
        expect((int) $exception->errorInfo[1])->toBe(1062);
        $duplicateRejected = true;
    }

    expect($duplicateRejected)->toBeTrue();
    expect($draft->documents()->count())->toBe(1);
});
```

Tambahkan **test kesembilan** untuk memastikan model final tetap bekerja:

```php
test('final_document_model_keeps_registration_relation', function () {
    $registration = SidangTaRegistration::create([
        'sidang_ta_period_id' => $this->period->id,
        'student_number' => '1202200001', 'student_name' => 'Student Test',
        'student_phone' => '081234567890', 'parent_phone' => '081234567891',
        'graduation_payment_date' => today()->toDateString(),
        'diploma_biodata_accurate' => true, 'status' => 'submitted',
    ]);
    $document = $registration->documents()->create([
        'document_type' => 'ta_data_capture', 'file_name' => 'final.pdf',
        'file_path' => 'sidang_ta/test/final.pdf', 'status' => 'pending',
    ]);

    expect($document->getTable())->toBe('sidang_ta_registration_documents');
    expect($document->fresh()->registration->is($registration))->toBeTrue();
    expect($registration->fresh()->lock_version)->toBe(1);
});
```

Sembilan test schema ini hanya menguji metadata, bukan membuat/mengecek PDF fisik. Jalankan bersama test B dengan koneksi MySQL sesuai 09.

## 4. Rendah: typo pada nama migrasi

File aktual bernama `2026_11_01_115038_create_sidang_ta_regristration_drafts.php`; ejaan `regristration` berlebih huruf r. Isi migrasi dan nama tabel cocok dengan rancangan, termasuk FK pendek dan urutan down yang benar.

Jika sudah dijalankan, **pertahankan nama file itu** karena Laravel mencatat basename migrasi. Mengganti nama lalu migrate dapat mencoba membuat tabel/kolom yang sudah ada. Jika sama sekali belum dijalankan di database mana pun, boleh perbaiki ejaan sebelum migrate. Tidak perlu migrasi koreksi hanya untuk typo nama file, dan jangan membuat salinan kedua memakai nama pada contoh 09.

## 5. Rendah: variabel tidak dipakai pada create periode

Setelah penghapusan logika mematikan periode lain, variabel `$period` pada `storePeriod()` tidak dipakai. Boleh sederhanakan dari `$period = SidangTaPeriod::create(...)` menjadi `SidangTaPeriod::create(...)`. Tidak perlu refactor controller lain. Pada updatePeriod parameter `$period` tetap diperlukan.

## 6. phpunit.xml bersifat lokal sementara

Pengguna akan mempertahankan pengaturan MySQL Podman selama pengerjaan student, lalu mengembalikan phpunit.xml sebelum commit/push. Petugas nantinya dapat menggunakan pengaturan lokal yang sama. Jangan menyimpulkan konfigurasi itu harus menjadi default tim.

Sebelum commit, bandingkan `git diff -- phpunit.xml` di folder asli dan pulihkan hanya perubahan konfigurasi lokal yang dimaksud, tanpa menimpa perubahan tim. Jangan menjalankan restore otomatis dari asisten. Simpan parameter lokal di catatan pribadi; jangan memasukkan password server ke commit. Jika kembali ke phpunit.xml awal tetapi ingin menjalankan test MySQL, berikan seluruh DB_* yang diperlukan melalui environment proses seperti langkah 11; test duplikasi 1062 memang ditujukan untuk MySQL.

## Urutan pengerjaan

1. Pulihkan model final; buat model draft-document; perbaiki relasi documents draft.
2. Perketat test duplikasi dan tambahkan regresi final.
3. Jalankan sembilan test schema + sembilan test periode di MySQL Podman. Tidak perlu reset database aplikasi.
4. Setelah lolos, lanjutkan [11 — draft tahap 1](11-checkpoint-c2-draft-tahap-satu.md). Kode source belum diubah oleh asisten.
