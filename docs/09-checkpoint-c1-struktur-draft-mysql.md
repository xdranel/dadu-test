# 09 — Checkpoint C1: struktur draft dan test MySQL Podman

Checkpoint B dilaporkan lulus oleh pengguna. Berikutnya kita membangun fondasi draft yang bisa diuji sebelum endpoint dan UI dipasang. C1 mencakup migrasi, dua model, relasi periode dan kode test lengkap. C2 melanjutkan service/ownership HTTP serta simpan tahap 1. Seluruh kode dalam dokumen ini disalin dan dikerjakan pengguna di folder asli; asisten tetap hanya mengubah Markdown.

## 1. Gunakan MySQL Podman untuk testing

SQLite tidak diperlukan. Kita memakai MySQL 8.4 di container `dadu-mysql84` yang sudah digunakan aplikasi. `RefreshDatabase` dapat menjalankan migrasi ulang yang menghapus tabel pada database tujuan test. Karena itu contoh memakai schema `dadu_testing` **di container yang sama**, supaya data aplikasi `dadu_test` tidak ikut terhapus. Ini tidak memerlukan server/container tambahan.

Jalankan satu kali (password root sesuai saat membuat container):

```bash
podman start dadu-mysql84
podman exec -it dadu-mysql84 mysql -u root -p
```

Di prompt MySQL:

```sql
CREATE DATABASE IF NOT EXISTS dadu_testing
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON dadu_testing.* TO 'dadu'@'%';
EXIT;
```

Pada `phpunit.xml` di folder asli, ganti **dua baris** DB_CONNECTION dan DB_DATABASE lama, kemudian tambahkan/selaraskan host/port/user berikut di elemen `<php>` (jangan membuat key duplikat):

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3307"/>
<env name="DB_DATABASE" value="dadu_testing"/>
<env name="DB_USERNAME" value="dadu"/>
<env name="DB_PASSWORD" value="dadu_local_2026"/>
<env name="DB_CHARSET" value="utf8mb4"/>
<env name="DB_COLLATION" value="utf8mb4_0900_ai_ci"/>
<env name="DB_SOCKET" value=""/>
```

Sesuaikan password/port jika container dibuat berbeda. Password di atas hanya contoh lokal yang sudah digunakan di panduan; jangan menaruh kredensial server sebenarnya pada file yang dilacak Git. Nilai `DB_URL` eksternal perlu dikosongkan pada proses test agar tidak menimpa koneksi tersebut. `.env` aplikasi tetap boleh menunjuk `dadu_test`.

Jika sengaja mengganti database test menjadi `dadu_test` yang sama, test dapat mereset seluruh data lokal itu, termasuk akun/periode/berkas metadata. Fakta bahwa container berbeda dari server produksi tidak membuat RefreshDatabase mempertahankan data lokal. Contoh siap jalan di dokumen ini tetap menggunakan `dadu_testing`.

**Hasil rescan:** baca [10](10-rescan-b-c1-koreksi-model-dokumen.md) untuk koreksi file model final yang tertimpa, relasi draft dan typo basename migrasi aktual. Kode test duplikasi di dokumen ini sudah diperketat dan regresi model final ditambahkan. Jangan membuat migrasi kedua jika file aktual dengan timestamp berbeda sudah ada.

## 2. Buat migrasi baru

Buat `database/migrations/2026_11_01_000003_create_sidang_ta_registration_drafts.php`. Jika timestamp sudah dipakai, pilih timestamp setelah migrasi TA terakhir. **Jangan mengubah migrasi lama.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(1);
        });

        Schema::create('sidang_ta_registration_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('owner_user_id');
            $table->string('student_number', 20)->index('sta_draft_student_idx');
            $table->unsignedBigInteger('sidang_ta_period_id');
            $table->unsignedBigInteger('source_registration_id')->nullable();
            $table->unsignedInteger('source_version')->nullable();
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('reviewed_version')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('submitted_registration_id')->nullable();
            $table->timestamps();

            $table->foreign('owner_user_id', 'sta_draft_owner_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('sidang_ta_period_id', 'sta_draft_period_fk')
                ->references('id')->on('sidang_ta_periods')->restrictOnDelete();
            $table->foreign('source_registration_id', 'sta_draft_source_fk')
                ->references('id')->on('sidang_ta_registrations')->restrictOnDelete();
            $table->foreign('submitted_registration_id', 'sta_draft_submitted_fk')
                ->references('id')->on('sidang_ta_registrations')->restrictOnDelete();
        });

        Schema::create('sidang_ta_registration_draft_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_id');
            $table->string('document_type', 80);
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_type', 10)->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->timestamps();

            $table->unique(['draft_id', 'document_type'], 'sta_draft_doc_type_uq');
            $table->foreign('draft_id', 'sta_draft_doc_draft_fk')
                ->references('id')->on('sidang_ta_registration_drafts')->cascadeOnDelete();
            $table->foreign('source_document_id', 'sta_draft_doc_source_fk')
                ->references('id')->on('sidang_ta_registration_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_ta_registration_draft_documents');
        Schema::dropIfExists('sidang_ta_registration_drafts');
        Schema::table('sidang_ta_registrations', function (Blueprint $table) {
            $table->dropColumn('lock_version');
        });
    }
};
```

Semua nama FK/index dibuat pendek agar tidak melewati batas identifier MySQL. Belum ada unique satu draft aktif per NIM: itu membutuhkan service dengan lock Student pada C2, bukan unique NIM yang juga akan melarang histori consumed.

## 3. Buat model draft

Isi lengkap `app/Models/SidangTaRegistrationDraft.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidangTaRegistrationDraft extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'owner_user_id', 'student_number', 'sidang_ta_period_id',
        'source_registration_id', 'source_version', 'payload', 'version',
        'reviewed_version', 'status', 'submitted_registration_id',
    ];

    protected $attributes = [
        'payload' => '{}', 'version' => 1, 'status' => self::STATUS_ACTIVE,
    ];

    protected $casts = [
        'payload' => 'array', 'version' => 'integer',
        'source_version' => 'integer', 'reviewed_version' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SidangTaPeriod::class, 'sidang_ta_period_id');
    }

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'source_registration_id');
    }

    public function submittedRegistration(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistration::class, 'submitted_registration_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SidangTaRegistrationDraftDocument::class, 'draft_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->getKey());
    }
}
```

`ownedBy()` adalah filter query, **bukan pengganti otorisasi HTTP**. Belum ada endpoint baru pada C1. Nantinya service mengisi metadata internal dari user/database, bukan `$request->all()` meskipun field tercantum pada fillable.

Isi lengkap `app/Models/SidangTaRegistrationDraftDocument.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidangTaRegistrationDraftDocument extends Model
{
    protected $fillable = [
        'draft_id', 'document_type', 'file_name', 'file_path',
        'file_size', 'file_type', 'source_document_id',
    ];

    protected $casts = ['file_size' => 'integer'];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistrationDraft::class, 'draft_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SidangTaRegistrationDocument::class, 'source_document_id');
    }
}
```

## 4. Tambahkan relasi dan guard periode

Pada `app/Models/SidangTaPeriod.php`, tambahkan method berikut di dalam class; method yang sudah ada dipertahankan:

```php
public function drafts()
{
    return $this->hasMany(SidangTaRegistrationDraft::class, 'sidang_ta_period_id');
}
```

Pada array `$casts` model `SidangTaRegistration`, tambahkan satu entry:

```php
'lock_version' => 'integer',
```

Tidak perlu memasukkan lock_version ke fillable final. Service/review akan mengaturnya dari server pada tahap berikutnya.

Ganti method `deletePeriod()` di controller TA dengan:

```php
public function deletePeriod(SidangTaPeriod $period)
{
    abort_if(
        $period->registrations()->exists() || $period->drafts()->exists(),
        409,
        'Periode yang memiliki pendaftaran atau draft tidak dapat dihapus.'
    );
    $period->delete();

    return back()->with('success', 'Periode Sidang TA berhasil dihapus.');
}
```

Jalankan migrasi baru pada database aplikasi lokal setelah berkas siap, sebelum mengakses controller yang menggunakan tabel draft:

```bash
php artisan config:clear
php artisan migrate
```

## 5. Kode test lengkap C1

Buat `tests/Feature/SidangTaDraftSchemaTest.php`. Ini test fondasi data, bukan test wizard/upload/otorisasi endpoint yang belum dibuat. RefreshDatabase sudah dipasang global pada `tests/Pest.php`.

```php
<?php

use App\Models\SidangTaPeriod;
use App\Models\SidangTaRegistration;
use App\Models\SidangTaRegistrationDraft;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->owner = User::factory()->create([
        'status' => 'active', 'auth_source' => 'manual',
        'must_change_password' => false,
    ]);
    $this->period = SidangTaPeriod::create([
        'name' => 'Periode test draft', 'is_active' => true,
        'open_at' => now()->subDay(), 'close_at' => now()->addDay(),
    ]);
    $this->makeDraft = function (array $overrides = []) {
        return SidangTaRegistrationDraft::create(array_replace([
            'owner_user_id' => $this->owner->id,
            'student_number' => '1202200001',
            'sidang_ta_period_id' => $this->period->id,
            'payload' => ['student_phone' => '081234567890'],
        ], $overrides));
    };
});

test('draft_schema_exists_without_changing_final_statuses', function () {
    expect(Schema::hasTable('sidang_ta_registration_drafts'))->toBeTrue();
    expect(Schema::hasTable('sidang_ta_registration_draft_documents'))->toBeTrue();
    expect(Schema::hasColumn('sidang_ta_registrations', 'lock_version'))->toBeTrue();
});

test('draft_persists_uuid_payload_and_owner_relations', function () {
    $draft = ($this->makeDraft)();
    $loaded = SidangTaRegistrationDraft::findOrFail($draft->id);

    expect(Str::isUuid($loaded->id))->toBeTrue();
    expect($loaded->payload)->toBe(['student_phone' => '081234567890']);
    expect($loaded->version)->toBe(1);
    expect($loaded->status)->toBe('active');
    expect($loaded->reviewed_version)->toBeNull();
    expect($loaded->owner->is($this->owner))->toBeTrue();
    expect($loaded->period->is($this->period))->toBeTrue();
    expect($this->period->drafts()->whereKey($draft->id)->exists())->toBeTrue();
});

test('draft_query_filters_by_owner', function () {
    $own = ($this->makeDraft)();
    $other = User::factory()->create(['status' => 'active']);
    ($this->makeDraft)(['owner_user_id' => $other->id]);

    expect(SidangTaRegistrationDraft::query()->ownedBy($this->owner)->pluck('id')->all())
        ->toBe([$own->id]);
});

test('draft_does_not_create_final_submission', function () {
    $before = SidangTaRegistration::count();
    ($this->makeDraft)();

    expect(SidangTaRegistration::count())->toBe($before);
});

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

test('same_document_type_is_allowed_for_different_drafts', function () {
    $first = ($this->makeDraft)();
    $second = ($this->makeDraft)(['student_number' => '1202200002']);
    foreach ([$first, $second] as $draft) {
        $document = $draft->documents()->create([
            'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
            'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
        ]);
        expect($document->draft->is($draft))->toBeTrue();
    }
    expect($first->documents()->count())->toBe(1);
    expect($second->documents()->count())->toBe(1);
});

test('deleting_draft_cascades_only_its_document_metadata', function () {
    $draft = ($this->makeDraft)();
    $document = $draft->documents()->create([
        'document_type' => 'ta_data_capture', 'file_name' => 'capture.pdf',
        'file_path' => "sidang_ta/drafts/{$draft->id}/capture.pdf",
    ]);
    $draft->delete();

    $this->assertDatabaseMissing('sidang_ta_registration_draft_documents', [
        'id' => $document->id,
    ]);
    expect($this->period->fresh())->not->toBeNull();
    expect($this->owner->fresh())->not->toBeNull();
});

test('period_with_draft_cannot_be_deleted_through_controller', function () {
    ($this->makeDraft)();
    $permission = Permission::firstOrCreate([
        'name' => 'sidang_ta_period_delete', 'guard_name' => 'web',
    ]);
    $this->owner->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->owner)
        ->delete(route('admin.sidang-ta-periods.destroy', $this->period))
        ->assertStatus(409);

    $this->assertDatabaseHas('sidang_ta_periods', ['id' => $this->period->id]);
});

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

Test metadata tidak menulis PDF fisik: hanya memeriksa skema/relasi. Test filesystem, download, input berbahaya dan ownership HTTP ditambahkan pada C2/D ketika endpoint-nya ada. `delete()` model pada test hanya membuktikan cascade DB; kode aplikasi nantinya memakai service discard dengan cleanup filesystem.

## 6. Jalankan test di MySQL Podman

Setelah phpunit.xml diarahkan ke MySQL, jalankan dari folder asli:

```bash
php artisan config:clear
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaDraftSchemaTest.php
DB_URL= DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dadu_testing php artisan test tests/Feature/SidangTaPeriodTest.php
```

Username/password memakai phpunit.xml yang disesuaikan langkah 1. Jangan memindahkan SQLite `:memory:` ke environment proses. Test schema berjumlah sembilan sesudah regresi model final; test periode B berjumlah sembilan. Jika gagal saat migrasi sebelum assertion, kirim error pertama dan nama migrasi, bukan menonaktifkan FK atau mengganti kode test agar error hilang.

## 7. Hasil yang dikirim untuk rescan

- Migrasi baru, dua model baru, model periode, model final dengan cast lock_version, controller deletePeriod dan test schema.
- Hasil kedua perintah test serta konfigurasi testing yang relevan tanpa password jika berbeda.
- `phpunit.xml` bila ikut dipindah: kredensial hanya contoh lokal.

C1 selesai bila kedua suite lulus dan guard periode tidak merusak pengelolaan periode B. Ini belum berarti draft sudah dapat diisi dari browser. C2 selanjutnya membuat service/context/request/controller dan test lengkap tahap 1; jangan mengganti form atau final store sekarang agar tidak memasang jalur setengah jadi.

## Verifikasi dokumen

Context7 dicoba tetapi koneksi layanan gagal. Referensi fallback: [UUID Eloquent](https://laravel.com/docs/13.x/eloquent#uuid-and-ulid-keys), [migrations](https://laravel.com/docs/13.x/migrations), [database testing](https://laravel.com/docs/13.x/database-testing), serta source vendor terpasang. Asisten hanya memeriksa sintaks blok PHP dan konsistensi sumber, tidak menjalankan migrasi/test atau mengubah database.
