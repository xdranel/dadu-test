# 12 — Rescan C2 yang sudah dipindah

Source pada `app`, `routes`, `resources`, `database`, `tests`, `config`, dan `bootstrap` cocok dengan folder asli; hanya file SQLite yang ada di dadu-test berbeda keberadaannya. Tidak perlu dipakai untuk pengujian MySQL. Hasil lulus C2 adalah laporan pengguna; asisten tidak menjalankan suite aplikasi.

## Koreksi sebelum D1

1. `app/Services/SidangTaStudentContext.php`: ubah pesan `deedngan` menjadi `dengan` dan tambahkan titik akhir.
2. `app/Http/Requests/SaveSidangTaDraftStepRequest.php`: larangan `source_registration` salah key; seharusnya `source_registration_id`. Saat ini bukan bypass karena service tidak menggunakan key tersebut, tetapi request tidak menolaknya secara eksplisit. Request pengganti pada dokumen 13 sudah memperbaikinya.
3. `app/Models/SidangTaRegistrationDocument.php`: hapus `draft()` dan `sourceDocument()` beserta import `BelongsTo` yang tidak lagi dipakai. Kolom relasi itu hanya ada pada tabel dokumen draft. Pertahankan `registration()`, fillable final dan cast file_size. Model `SidangTaRegistrationDraftDocument` tetap memiliki kedua relasi tersebut.

Migrasi aktual sekarang `2026_11_01_123207_create_sidang_ta_registration_drafts.php`, satu file, skema sesuai C1. Nama lama dari catatan 10 tidak ditemukan. Jangan membuat migrasi baru atau mengganti basename lagi hanya mengikuti contoh nama dokumen. Status tabel migrations tidak diperiksa pada scan filesystem ini.

## Tambahan test regresi pada file C2 yang ada

Tidak perlu membuat file lagi. Tambahkan ke `tests/Feature/SidangTaDraftTest.php`; fixture/import existing dipakai:

```php
test('rejects_source_registration_id_on_new_draft_request', function () {
    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), array_replace($this->data, [
            'source_registration_id' => 123,
        ]))->assertUnprocessable()->assertJsonValidationErrors('source_registration_id');
    expect(SidangTaRegistrationDraft::count())->toBe(0);
});

test('read_only_owner_cannot_start_or_save_draft', function () {
    $id = $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertCreated()->json('data.id');
    $this->studentUser->revokePermissionTo('sidang_ta_registration_create');
    $this->studentUser->unsetRelation('permissions');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->studentUser)
        ->postJson(route('sidang-ta-drafts.store'), $this->data)->assertForbidden();
    $this->postJson(route('sidang-ta-drafts.steps.store', ['draft' => $id, 'step' => 1]),
        array_replace($this->data, ['version' => 1]))->assertForbidden();
    expect(SidangTaRegistrationDraft::findOrFail($id)->version)->toBe(1);
});
```

Setelah D1, test lama `cannot_skip_to_unimplemented_stage` diganti sesuai instruksi 13 karena tahap 2–4 sudah diimplementasikan, tetapi tetap memerlukan prasyarat. Perintah testing tetap menggunakan MySQL Podman; `phpunit.xml` lokal dikembalikan sebelum commit/push sesuai keputusan pengguna.

## Perintah pembuatan file berikutnya

Mulai dokumen 13, setiap file baru dilengkapi command. Command generator hanya dijalankan pengguna di folder asli. Untuk service/support class, `php artisan make:class`; request `make:request`; feature test `make:test ... --pest`. Jangan memakai `--force` pada file yang sudah ada. File existing cukup diedit, tidak dibuat ulang.

Berikutnya [13 — upload draft baru tahap 2–4](13-checkpoint-d1-upload-draft.md). Revisi pengajuan final adalah D2 terpisah; jangan menghapus larangan source_registration_id sebelum desain revisinya dipasang.
