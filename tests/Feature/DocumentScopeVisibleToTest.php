<?php

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Document::query()->forceDelete();
    DocumentCategory::query()->forceDelete();

    // Pastikan role-role yang dibutuhkan ada
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'Akademik']);
    Role::firstOrCreate(['name' => 'Pelaksana']);
    Role::firstOrCreate(['name' => 'Pemimpin']);
    Role::firstOrCreate(['name' => 'Pengamat']);
    Role::firstOrCreate(['name' => 'Petugas']);
    Role::firstOrCreate(['name' => 'mahasiswa']);
    Role::firstOrCreate(['name' => 'tamu_khusus']);

    // Setup folder untuk tiap access_group
    $this->catPublik = DocumentCategory::factory()->publik()->create(['name' => 'Folder Publik']);
    $this->catDosenStaf = DocumentCategory::factory()->dosenStaf()->create(['name' => 'Folder Dosen Staf']);
    $this->catAkademik = DocumentCategory::factory()->akademik()->create(['name' => 'Folder Akademik']);
    $this->catMahasiswa = DocumentCategory::factory()->mahasiswa()->create(['name' => 'Folder Mahasiswa']);

    // Setup dokumen untuk tiap kategori
    $this->docPublik = Document::factory()->publik()->create([
        'document_category_id' => $this->catPublik->id,
        'title' => 'Dokumen Publik',
    ]);

    $this->docDosenStaf = Document::factory()->dosenStaf()->create([
        'document_category_id' => $this->catDosenStaf->id,
        'title' => 'Dokumen Dosen Staf',
    ]);

    $this->docAkademik = Document::factory()->akademik()->create([
        'document_category_id' => $this->catAkademik->id,
        'title' => 'Dokumen Akademik',
    ]);

    $this->docMhsA = Document::factory()->mahasiswa('0123')->create([
        'document_category_id' => $this->catMahasiswa->id,
        'title' => 'Dokumen Mahasiswa A',
        'owner_nim' => '0123',
    ]);

    $this->docMhsB = Document::factory()->mahasiswa('0999')->create([
        'document_category_id' => $this->catMahasiswa->id,
        'title' => 'Dokumen Mahasiswa B',
        'owner_nim' => '0999',
    ]);

    $this->docMhsDifferentZero = Document::factory()->mahasiswa('123')->create([
        'document_category_id' => $this->catMahasiswa->id,
        'title' => 'Dokumen Mahasiswa 123 (tanpa leading zero)',
        'owner_nim' => '123',
    ]);

    // Setup dokumen legacy (access_group = null)
    $this->legacyCatPublik = DocumentCategory::factory()->legacy('publik')->create(['name' => 'Legacy Publik']);
    $this->legacyCatInternal = DocumentCategory::factory()->legacy('internal')->create(['name' => 'Legacy Internal']);
    $this->legacyCatPrivate = DocumentCategory::factory()->legacy('private')->create(['name' => 'Legacy Private']);

    $this->docLegacyPublik = Document::factory()->legacy('publik')->create([
        'document_category_id' => $this->legacyCatPublik->id,
        'title' => 'Dokumen Legacy Publik',
    ]);

    $this->docLegacyInternal = Document::factory()->legacy('internal')->create([
        'document_category_id' => $this->legacyCatInternal->id,
        'title' => 'Dokumen Legacy Internal',
    ]);

    $this->docLegacyPrivate = Document::factory()->legacy('private')->create([
        'document_category_id' => $this->legacyCatPrivate->id,
        'title' => 'Dokumen Legacy Private',
    ]);
});

test('guest: hanya melihat dokumen publik', function () {
    $visibleDocs = Document::visibleTo(null)->pluck('id');

    expect($visibleDocs)->toContain($this->docPublik->id)
        ->toContain($this->docLegacyPublik->id)
        ->not->toContain($this->docDosenStaf->id)
        ->not->toContain($this->docAkademik->id)
        ->not->toContain($this->docMhsA->id)
        ->not->toContain($this->docMhsB->id)
        ->not->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docLegacyPrivate->id);
});

test('mahasiswa A: melihat publik + mahasiswa miliknya (owner_nim cocok); TIDAK melihat mahasiswa milik NIM lain, dosen_staf, akademik', function () {
    $mhsUserA = User::factory()->create([
        'status' => 'active',
        'employee_id' => '0123',
    ]);
    $mhsUserA->assignRole('mahasiswa');

    $visibleDocs = Document::visibleTo($mhsUserA)->pluck('id');

    expect($visibleDocs)->toContain($this->docPublik->id)
        ->toContain($this->docMhsA->id)
        ->toContain($this->docLegacyPublik->id)
        ->not->toContain($this->docMhsB->id)
        ->not->toContain($this->docMhsDifferentZero->id)
        ->not->toContain($this->docDosenStaf->id)
        ->not->toContain($this->docAkademik->id)
        ->not->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docLegacyPrivate->id);
});

test('mahasiswa tanpa NIM: tidak melihat dokumen kategori mahasiswa apa pun', function () {
    $mhsWithoutNim = User::factory()->create([
        'status' => 'active',
        'employee_id' => null,
    ]);
    $mhsWithoutNim->assignRole('mahasiswa');

    $visibleDocs = Document::visibleTo($mhsWithoutNim)->pluck('id');

    expect($visibleDocs)->toContain($this->docPublik->id)
        ->toContain($this->docLegacyPublik->id)
        ->not->toContain($this->docMhsA->id)
        ->not->toContain($this->docMhsB->id)
        ->not->toContain($this->docMhsDifferentZero->id)
        ->not->toContain($this->docDosenStaf->id)
        ->not->toContain($this->docAkademik->id);
});

test('NIM dibandingkan sebagai string (leading zero tetap valid; "0123" != "123")', function () {
    $user0123 = User::factory()->create([
        'status' => 'active',
        'employee_id' => '0123',
    ]);
    $user0123->assignRole('mahasiswa');

    $user123 = User::factory()->create([
        'status' => 'active',
        'employee_id' => '123',
    ]);
    $user123->assignRole('mahasiswa');

    $visible0123 = Document::visibleTo($user0123)->pluck('id');
    $visible123 = Document::visibleTo($user123)->pluck('id');

    // User dengan NIM 0123 HANYA melihat dokumen dengan owner_nim '0123', bukan '123'
    expect($visible0123)->toContain($this->docMhsA->id)
        ->not->toContain($this->docMhsDifferentZero->id);

    // User dengan NIM 123 HANYA melihat dokumen dengan owner_nim '123', bukan '0123'
    expect($visible123)->toContain($this->docMhsDifferentZero->id)
        ->not->toContain($this->docMhsA->id);
});

test('pelaksana, pemimpin, pengamat: melihat publik + dosen_staf; tidak melihat akademik dan mahasiswa', function () {
    foreach (['Pelaksana', 'Pemimpin', 'Pengamat'] as $roleName) {
        $staffUser = User::factory()->create(['status' => 'active']);
        $staffUser->assignRole($roleName);

        $visibleDocs = Document::visibleTo($staffUser)->pluck('id');

        expect($visibleDocs)->toContain($this->docPublik->id)
            ->toContain($this->docDosenStaf->id)
            ->toContain($this->docLegacyPublik->id)
            ->toContain($this->docLegacyInternal->id)
            ->not->toContain($this->docAkademik->id)
            ->not->toContain($this->docMhsA->id)
            ->not->toContain($this->docMhsB->id)
            ->not->toContain($this->docLegacyPrivate->id);
    }
});

test('admin: melihat semua kategori dokumen', function () {
    foreach (['admin', 'Akademik'] as $roleName) {
        $adminUser = User::factory()->create(['status' => 'active']);
        $adminUser->assignRole($roleName);

        $visibleDocs = Document::visibleTo($adminUser)->pluck('id');

        expect($visibleDocs)->toContain($this->docPublik->id)
            ->toContain($this->docDosenStaf->id)
            ->toContain($this->docAkademik->id)
            ->toContain($this->docMhsA->id)
            ->toContain($this->docMhsB->id)
            ->toContain($this->docMhsDifferentZero->id)
            ->toContain($this->docLegacyPublik->id)
            ->toContain($this->docLegacyInternal->id)
            ->toContain($this->docLegacyPrivate->id);
    }
});

test('role petugas: melihat publik + dosen_staf (dan legacy internal); tidak melihat akademik, private, dan mahasiswa', function () {
    $petugas = User::factory()->create(['status' => 'active']);
    $petugas->assignRole('Petugas');

    $visibleDocs = Document::visibleTo($petugas)->pluck('id');

    expect($visibleDocs)->toContain($this->docPublik->id)
        ->toContain($this->docDosenStaf->id)
        ->toContain($this->docLegacyPublik->id)
        ->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docAkademik->id)
        ->not->toContain($this->docMhsA->id)
        ->not->toContain($this->docMhsB->id)
        ->not->toContain($this->docLegacyPrivate->id);

    $visibleCats = DocumentCategory::visibleTo($petugas)->pluck('id');

    expect($visibleCats)->toContain($this->catPublik->id)
        ->toContain($this->catDosenStaf->id)
        ->toContain($this->legacyCatPublik->id)
        ->toContain($this->legacyCatInternal->id)
        ->not->toContain($this->catAkademik->id)
        ->not->toContain($this->catMahasiswa->id)
        ->not->toContain($this->legacyCatPrivate->id);
});

test('user tanpa role dikenal: hanya melihat publik', function () {
    $unknownUser = User::factory()->create(['status' => 'active']);
    $unknownUser->assignRole('tamu_khusus');

    $visibleDocs = Document::visibleTo($unknownUser)->pluck('id');

    expect($visibleDocs)->toContain($this->docPublik->id)
        ->toContain($this->docLegacyPublik->id)
        ->not->toContain($this->docDosenStaf->id)
        ->not->toContain($this->docAkademik->id)
        ->not->toContain($this->docMhsA->id)
        ->not->toContain($this->docMhsB->id)
        ->not->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docLegacyPrivate->id);
});

test('dokumen access_group NULL mengikuti logika visibilitas lama', function () {
    // Guest: hanya legacy publik
    $guestDocs = Document::whereNull('access_group')->visibleTo(null)->pluck('id');
    expect($guestDocs)->toContain($this->docLegacyPublik->id)
        ->not->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docLegacyPrivate->id);

    // Pelaksana: legacy publik + legacy internal
    $pelaksana = User::factory()->create(['status' => 'active']);
    $pelaksana->assignRole('Pelaksana');
    $staffDocs = Document::whereNull('access_group')->visibleTo($pelaksana)->pluck('id');
    expect($staffDocs)->toContain($this->docLegacyPublik->id)
        ->toContain($this->docLegacyInternal->id)
        ->not->toContain($this->docLegacyPrivate->id);

    // Admin: legacy publik + internal + private
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('admin');
    $adminDocs = Document::whereNull('access_group')->visibleTo($admin)->pluck('id');
    expect($adminDocs)->toContain($this->docLegacyPublik->id)
        ->toContain($this->docLegacyInternal->id)
        ->toContain($this->docLegacyPrivate->id);
});

test('DocumentCategory scope visibleTo mengikuti aturan matriks akses', function () {
    // Guest
    $guestCats = DocumentCategory::visibleTo(null)->pluck('id');
    expect($guestCats)->toContain($this->catPublik->id)
        ->toContain($this->legacyCatPublik->id)
        ->not->toContain($this->catDosenStaf->id)
        ->not->toContain($this->catAkademik->id);

    // Staff Pelaksana
    $staff = User::factory()->create(['status' => 'active']);
    $staff->assignRole('Pelaksana');
    $staffCats = DocumentCategory::visibleTo($staff)->pluck('id');
    expect($staffCats)->toContain($this->catPublik->id)
        ->toContain($this->catDosenStaf->id)
        ->toContain($this->legacyCatPublik->id)
        ->toContain($this->legacyCatInternal->id)
        ->not->toContain($this->catAkademik->id);

    // Admin
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('admin');
    $adminCats = DocumentCategory::visibleTo($admin)->pluck('id');
    expect($adminCats)->toContain($this->catPublik->id)
        ->toContain($this->catDosenStaf->id)
        ->toContain($this->catAkademik->id)
        ->toContain($this->catMahasiswa->id);

    // Mahasiswa: HANYA folder publik di explorer folder; folder mahasiswa disembunyikan
    $mhs = User::factory()->create(['status' => 'active', 'employee_id' => '0123']);
    $mhs->assignRole('mahasiswa');
    $mhsCats = DocumentCategory::visibleTo($mhs)->pluck('id');
    expect($mhsCats)->toContain($this->catPublik->id)
        ->toContain($this->legacyCatPublik->id)
        ->not->toContain($this->catMahasiswa->id)
        ->not->toContain($this->catDosenStaf->id)
        ->not->toContain($this->catAkademik->id);
});
