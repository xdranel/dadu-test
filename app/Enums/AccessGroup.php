<?php

namespace App\Enums;

enum AccessGroup: string
{
    case MAHASISWA = 'mahasiswa';
    case DOSEN_STAF = 'dosen_staf';
    case AKADEMIK = 'akademik';
    case PUBLIK = 'publik';

    /**
     * Dapatkan label tampilan untuk kategori akses ini.
     */
    public function label(): string
    {
        return config("documents.access_groups.{$this->value}.label", match ($this) {
            self::MAHASISWA => 'Mahasiswa',
            self::DOSEN_STAF => 'Dosen & Staf',
            self::AKADEMIK => 'Akademik',
            self::PUBLIK => 'Publik',
        });
    }

    /**
     * Dapatkan kelas CSS badge untuk kategori akses ini.
     */
    public function badge(): string
    {
        return config("documents.access_groups.{$this->value}.badge", match ($this) {
            self::MAHASISWA => 'bg-sky-50 text-sky-700 border border-sky-200/60',
            self::DOSEN_STAF => 'bg-amber-50 text-amber-700 border border-amber-200/60',
            self::AKADEMIK => 'bg-violet-50 text-violet-700 border border-violet-200/60',
            self::PUBLIK => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        });
    }

    /**
     * Mapping dari visibilitas lama (legacy) ke AccessGroup baru.
     * publik    -> publik
     * internal  -> dosen_staf
     * private   -> akademik
     * (Kategori mahasiswa TIDAK dibackfill otomatis; itu tahap 2.)
     */
    public static function fromLegacyVisibility(?string $visibility): ?self
    {
        if ($visibility === null) {
            return null;
        }

        return match (strtolower(trim($visibility))) {
            'publik' => self::PUBLIK,
            'internal', 'pegawai', 'dosen' => self::DOSEN_STAF,
            'private', 'privat' => self::AKADEMIK,
            default => null,
        };
    }
}
