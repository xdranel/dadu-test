<?php

use App\Enums\AccessGroup;
use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Document::query()->forceDelete();
    DocumentCategory::query()->forceDelete();

    // Roles
    $this->roleAdmin = Role::firstOrCreate(['name' => 'admin']);
    $this->roleAkademik = Role::firstOrCreate(['name' => 'Akademik']);
    $this->rolePetugas = Role::firstOrCreate(['name' => 'Petugas']);
    $this->rolePelaksana = Role::firstOrCreate(['name' => 'Pelaksana']);
    $this->rolePemimpin = Role::firstOrCreate(['name' => 'Pemimpin']);
    $this->rolePengamat = Role::firstOrCreate(['name' => 'Pengamat']);
    $this->roleMahasiswa = Role::firstOrCreate(['name' => 'mahasiswa']);

    // Permissions
    Permission::firstOrCreate(['name' => 'public_document_read']);
    Permission::firstOrCreate(['name' => 'document_read']);
    Permission::firstOrCreate(['name' => 'document_create']);
    Permission::firstOrCreate(['name' => 'document_update']);
    Permission::firstOrCreate(['name' => 'document_delete']);
    Permission::firstOrCreate(['name' => 'document_category_read']);
    Permission::firstOrCreate(['name' => 'document_category_create']);
    Permission::firstOrCreate(['name' => 'document_category_update']);
    Permission::firstOrCreate(['name' => 'document_category_delete']);

    // Users
    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo([
        'document_create', 'document_read', 'document_update', 'document_delete',
        'document_category_create', 'document_category_read', 'document_category_update', 'document_category_delete',
        'public_document_read'
    ]);

    $this->superadmin = User::factory()->create(['status' => 'active']);
    $this->superadmin->assignRole('Akademik');

    // Petugas dengan permissions
    $this->petugasWithPerm = User::factory()->create(['status' => 'active']);
    $this->petugasWithPerm->assignRole('Petugas');
    $this->petugasWithPerm->givePermissionTo([
        'public_document_read', 'document_read',
        'document_create', 'document_update', 'document_delete',
        'document_category_create', 'document_category_read', 'document_category_update', 'document_category_delete'
    ]);

    // Petugas tanpa create permissions
    $this->petugasWithoutPerm = User::factory()->create(['status' => 'active']);
    $this->petugasWithoutPerm->assignRole('Petugas');
    $this->petugasWithoutPerm->givePermissionTo(['public_document_read', 'document_category_read']);

    $this->pelaksana = User::factory()->create(['status' => 'active']);
    $this->pelaksana->assignRole('Pelaksana');
    $this->pelaksana->givePermissionTo(['document_create', 'document_read', 'public_document_read', 'document_category_create', 'document_category_read']);

    $this->mahasiswa = User::factory()->create(['status' => 'active', 'employee_id' => '012345']);
    $this->mahasiswa->assignRole('mahasiswa');
    $this->mahasiswa->givePermissionTo(['public_document_read']);

    // Folders with access_group
    $this->folderPublik = DocumentCategory::factory()->publik()->create();
    $this->folderDosenStaf = DocumentCategory::factory()->dosenStaf()->create();
    $this->folderAkademik = DocumentCategory::factory()->akademik()->create();
    $this->folderMahasiswa = DocumentCategory::factory()->mahasiswa()->create();

    // Legacy folders (null access_group)
    $this->legacyFolderPublik = DocumentCategory::factory()->legacy('publik')->create();
    $this->legacyFolderInternal = DocumentCategory::factory()->legacy('internal')->create();
    $this->legacyFolderPrivate = DocumentCategory::factory()->legacy('private')->create();

    // Documents with access_group
    $this->docPublik = Document::factory()->publik()->create(['document_category_id' => $this->folderPublik->id]);
    $this->docDosenStaf = Document::factory()->dosenStaf()->create(['document_category_id' => $this->folderDosenStaf->id]);
    $this->docAkademik = Document::factory()->akademik()->create(['document_category_id' => $this->folderAkademik->id]);
    $this->docMhsOwn = Document::factory()->mahasiswa('012345')->create(['document_category_id' => $this->folderMahasiswa->id, 'owner_nim' => '012345']);
    $this->docMhsOther = Document::factory()->mahasiswa('999999')->create(['document_category_id' => $this->folderMahasiswa->id, 'owner_nim' => '999999']);

    // Legacy documents (null access_group)
    $this->legacyDocPublik = Document::factory()->legacy('publik')->create(['document_category_id' => $this->legacyFolderPublik->id]);
    $this->legacyDocInternal = Document::factory()->legacy('internal')->create(['document_category_id' => $this->legacyFolderInternal->id]);
    $this->legacyDocPrivate = Document::factory()->legacy('private')->create(['document_category_id' => $this->legacyFolderPrivate->id]);
});

test('role petugas dapat melihat dokumen dan folder publik dan internal, tetapi tidak akademik/private/mahasiswa', function () {
    // Dokumen & folder publik: boleh
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->docPublik))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('download', $this->docPublik))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyDocPublik))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->folderPublik))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyFolderPublik))->toBeTrue();

    // Dokumen & folder internal (dosen_staf / legacy internal): boleh
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->docDosenStaf))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('download', $this->docDosenStaf))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyDocInternal))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->folderDosenStaf))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyFolderInternal))->toBeTrue();

    // Dokumen & folder akademik / legacy private / mahasiswa orang lain: DITOLAK
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->docAkademik))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('download', $this->docAkademik))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyDocPrivate))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->docMhsOwn))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->folderAkademik))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->folderMahasiswa))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->legacyFolderPrivate))->toBeFalse();
});

test('otorisasi upload dokumen menggunakan Spatie permission document_create', function () {
    // Admin operasional & Superadmin: boleh
    expect(Gate::forUser($this->admin)->allows('create', Document::class))->toBeTrue();
    expect(Gate::forUser($this->superadmin)->allows('create', Document::class))->toBeTrue();

    // Pelaksana dengan permission document_create: boleh
    expect(Gate::forUser($this->pelaksana)->allows('create', Document::class))->toBeTrue();

    // Petugas dengan permission document_create: boleh secara umum dan di folder publik
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', Document::class))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [Document::class, $this->folderPublik]))->toBeTrue();

    // Petugas DITOLAK jika mencoba upload ke folder non-publik
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [Document::class, $this->folderDosenStaf]))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [Document::class, $this->folderAkademik]))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [Document::class, $this->folderMahasiswa]))->toBeFalse();

    // User tanpa permission document_create: ditolak
    expect(Gate::forUser($this->petugasWithoutPerm)->allows('create', Document::class))->toBeFalse();
    expect(Gate::forUser($this->mahasiswa)->allows('create', Document::class))->toBeFalse();
});

test('otorisasi buat dan kelola folder menggunakan Spatie permission document_category_create', function () {
    // Admin & Superadmin: boleh
    expect(Gate::forUser($this->admin)->allows('create', DocumentCategory::class))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('update', $this->folderPublik))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('delete', $this->folderPublik))->toBeTrue();

    expect(Gate::forUser($this->superadmin)->allows('create', DocumentCategory::class))->toBeTrue();
    expect(Gate::forUser($this->superadmin)->allows('update', $this->folderPublik))->toBeTrue();
    expect(Gate::forUser($this->superadmin)->allows('delete', $this->folderPublik))->toBeTrue();

    // Pelaksana dengan permission: boleh
    expect(Gate::forUser($this->pelaksana)->allows('create', DocumentCategory::class))->toBeTrue();

    // Petugas dengan permission: boleh buat folder di root atau di folder publik
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', DocumentCategory::class))->toBeTrue();
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [DocumentCategory::class, $this->folderPublik]))->toBeTrue();

    // Petugas DITOLAK jika mencoba membuat subfolder di bawah folder non-publik
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [DocumentCategory::class, $this->folderDosenStaf]))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('create', [DocumentCategory::class, $this->folderAkademik]))->toBeFalse();

    // User tanpa permission: ditolak
    expect(Gate::forUser($this->petugasWithoutPerm)->allows('create', DocumentCategory::class))->toBeFalse();
    expect(Gate::forUser($this->mahasiswa)->allows('create', DocumentCategory::class))->toBeFalse();
});

test('FormRequest StoreDocumentRequest menolak Petugas di folder atau visibilitas non-publik', function () {
    // Petugas upload ke folder publik dengan visibility publik: DISETUJUI
    $reqOk = new StoreDocumentRequest();
    $reqOk->setUserResolver(fn () => $this->petugasWithPerm);
    $reqOk->merge([
        'document_category_id' => $this->folderPublik->id,
        'visibility' => 'publik',
    ]);
    expect($reqOk->authorize())->toBeTrue();

    // Petugas upload ke folder non-publik: DITOLAK
    $reqNonPublikFolder = new StoreDocumentRequest();
    $reqNonPublikFolder->setUserResolver(fn () => $this->petugasWithPerm);
    $reqNonPublikFolder->merge([
        'document_category_id' => $this->folderDosenStaf->id,
        'visibility' => 'publik',
    ]);
    expect($reqNonPublikFolder->authorize())->toBeFalse();

    // Petugas upload dengan visibility non-publik (internal): DITOLAK
    $reqNonPublikVis = new StoreDocumentRequest();
    $reqNonPublikVis->setUserResolver(fn () => $this->petugasWithPerm);
    $reqNonPublikVis->merge([
        'document_category_id' => $this->folderPublik->id,
        'visibility' => 'internal',
    ]);
    expect($reqNonPublikVis->authorize())->toBeFalse();

    // User tanpa permission (mahasiswa): DITOLAK
    $reqMhs = new StoreDocumentRequest();
    $reqMhs->setUserResolver(fn () => $this->mahasiswa);
    $reqMhs->merge([
        'document_category_id' => $this->folderPublik->id,
        'visibility' => 'publik',
    ]);
    expect($reqMhs->authorize())->toBeFalse();
});

test('FormRequest StoreDocumentCategoryRequest menolak Petugas di folder atau visibilitas non-publik', function () {
    // Petugas buat folder di root: DISETUJUI
    $reqRoot = new StoreDocumentCategoryRequest();
    $reqRoot->setUserResolver(fn () => $this->petugasWithPerm);
    $reqRoot->merge([
        'name' => 'Folder Baru Petugas',
        'visibility' => 'publik',
        'parent_id' => null,
    ]);
    expect($reqRoot->authorize())->toBeTrue();

    // Petugas buat subfolder di folder publik: DISETUJUI
    $reqSub = new StoreDocumentCategoryRequest();
    $reqSub->setUserResolver(fn () => $this->petugasWithPerm);
    $reqSub->merge([
        'name' => 'Subfolder Publik',
        'visibility' => 'publik',
        'parent_id' => $this->folderPublik->id,
    ]);
    expect($reqSub->authorize())->toBeTrue();

    // Petugas buat subfolder di folder non-publik: DITOLAK
    $reqNonPublikParent = new StoreDocumentCategoryRequest();
    $reqNonPublikParent->setUserResolver(fn () => $this->petugasWithPerm);
    $reqNonPublikParent->merge([
        'name' => 'Subfolder Dosen',
        'visibility' => 'publik',
        'parent_id' => $this->folderDosenStaf->id,
    ]);
    expect($reqNonPublikParent->authorize())->toBeFalse();

    // Petugas buat folder dengan visibilitas non-publik: DITOLAK
    $reqNonPublikVis = new StoreDocumentCategoryRequest();
    $reqNonPublikVis->setUserResolver(fn () => $this->petugasWithPerm);
    $reqNonPublikVis->merge([
        'name' => 'Folder Internal',
        'visibility' => 'internal',
        'parent_id' => null,
    ]);
    expect($reqNonPublikVis->authorize())->toBeFalse();

    // User tanpa permission (mahasiswa): DITOLAK
    $reqMhs = new StoreDocumentCategoryRequest();
    $reqMhs->setUserResolver(fn () => $this->mahasiswa);
    $reqMhs->merge([
        'name' => 'Folder Mahasiswa',
        'visibility' => 'publik',
        'parent_id' => null,
    ]);
    expect($reqMhs->authorize())->toBeFalse();
});

test('mahasiswa dapat membaca dokumen publik dan dokumen miliknya sendiri', function () {
    // Publik: boleh
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->docPublik))->toBeTrue();
    expect(Gate::forUser($this->mahasiswa)->allows('download', $this->docPublik))->toBeTrue();

    // Mahasiswa miliknya (NIM cocok): boleh
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->docMhsOwn))->toBeTrue();
    expect(Gate::forUser($this->mahasiswa)->allows('download', $this->docMhsOwn))->toBeTrue();

    // Mahasiswa milik orang lain: ditolak
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->docMhsOther))->toBeFalse();
    expect(Gate::forUser($this->mahasiswa)->allows('download', $this->docMhsOther))->toBeFalse();

    // Kategori lain: ditolak
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->docDosenStaf))->toBeFalse();
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->docAkademik))->toBeFalse();

    // Struktur folder mahasiswa: disembunyikan dari mahasiswa & petugas; hanya admin/Akademik yang boleh melihat
    expect(Gate::forUser($this->mahasiswa)->allows('view', $this->folderMahasiswa))->toBeFalse();
    expect(Gate::forUser($this->petugasWithPerm)->allows('view', $this->folderMahasiswa))->toBeFalse();
    expect(Gate::forUser($this->admin)->allows('view', $this->folderMahasiswa))->toBeTrue();
    expect(Gate::forUser($this->superadmin)->allows('view', $this->folderMahasiswa))->toBeTrue();
});

test('tombol UI upload dan buat folder tampil berdasarkan permission Spatie', function () {
    // Admin: tombol terlihat
    $this->actingAs($this->admin)
        ->blade('@include("documents.partials.page-header")')
        ->assertSee('Upload Dokumen')
        ->assertSee('Buat Folder');

    // Petugas dengan permission: tombol terlihat
    $this->actingAs($this->petugasWithPerm)
        ->blade('@include("documents.partials.page-header")')
        ->assertSee('Upload Dokumen')
        ->assertSee('Buat Folder');

    // Pelaksana dengan permission: tombol terlihat
    $this->actingAs($this->pelaksana)
        ->blade('@include("documents.partials.page-header")')
        ->assertSee('Upload Dokumen')
        ->assertSee('Buat Folder');

    // Petugas tanpa permission: tidak melihat tombol
    $this->actingAs($this->petugasWithoutPerm)
        ->blade('@include("documents.partials.page-header")')
        ->assertDontSee('Upload Dokumen')
        ->assertDontSee('Buat Folder');

    // Mahasiswa tanpa permission: tidak melihat tombol
    $this->actingAs($this->mahasiswa)
        ->blade('@include("documents.partials.page-header")')
        ->assertDontSee('Upload Dokumen')
        ->assertDontSee('Buat Folder');
});
