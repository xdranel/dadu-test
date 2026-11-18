<?php

use App\Enums\AccessGroup;

uses(Tests\TestCase::class);

test('label dan badge tersedia untuk semua 4 case AccessGroup', function () {
    $cases = AccessGroup::cases();

    expect($cases)->toHaveCount(4);

    foreach ($cases as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->badge())->toBeString()->not->toBeEmpty();
    }

    expect(AccessGroup::MAHASISWA->label())->toBe('Mahasiswa')
        ->and(AccessGroup::DOSEN_STAF->label())->toBe('Dosen & Staf')
        ->and(AccessGroup::AKADEMIK->label())->toBe('Akademik')
        ->and(AccessGroup::PUBLIK->label())->toBe('Publik');
});

test('mapping visibilitas lama ke AccessGroup benar', function () {
    expect(AccessGroup::fromLegacyVisibility('publik'))->toBe(AccessGroup::PUBLIK)
        ->and(AccessGroup::fromLegacyVisibility('Publik'))->toBe(AccessGroup::PUBLIK)
        ->and(AccessGroup::fromLegacyVisibility('PUBLIK'))->toBe(AccessGroup::PUBLIK)
        ->and(AccessGroup::fromLegacyVisibility('internal'))->toBe(AccessGroup::DOSEN_STAF)
        ->and(AccessGroup::fromLegacyVisibility('pegawai'))->toBe(AccessGroup::DOSEN_STAF)
        ->and(AccessGroup::fromLegacyVisibility('dosen'))->toBe(AccessGroup::DOSEN_STAF)
        ->and(AccessGroup::fromLegacyVisibility('private'))->toBe(AccessGroup::AKADEMIK)
        ->and(AccessGroup::fromLegacyVisibility('privat'))->toBe(AccessGroup::AKADEMIK);

    // Kategori mahasiswa TIDAK dibackfill otomatis dari visibilitas lama
    expect(AccessGroup::fromLegacyVisibility('mahasiswa'))->toBeNull()
        ->and(AccessGroup::fromLegacyVisibility(null))->toBeNull()
        ->and(AccessGroup::fromLegacyVisibility('unknown_val'))->toBeNull();
});
