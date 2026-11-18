<?php

namespace Database\Factories;

use App\Enums\AccessGroup;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_category_id' => DocumentCategory::factory(),
            'title'                => fake()->sentence(3),
            'abbreviation'         => strtoupper(fake()->lexify('???')),
            'description'          => fake()->paragraph(),
            'file_name'            => fake()->word() . '.pdf',
            'original_file_name'   => fake()->word() . '.pdf',
            'file_path'            => 'documents/' . fake()->uuid() . '.pdf',
            'file_type'            => 'pdf',
            'file_size'            => fake()->numberBetween(1000, 500000),
            'visibility'           => 'publik',
            'access_group'         => AccessGroup::PUBLIK,
            'owner_nim'            => null,
            'keywords'             => fake()->words(3, true),
            'download_count'       => 0,
        ];
    }

    /**
     * State untuk dokumen mahasiswa.
     */
    public function mahasiswa(?string $ownerNim = null): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::MAHASISWA,
            'owner_nim'    => $ownerNim ?? fake()->numerify('1201######'),
        ])->afterCreating(function (Document $doc) use ($ownerNim) {
            $nim = $ownerNim ?? $doc->owner_nim;
            if ($nim) {
                $doc->nims()->firstOrCreate(['nim' => (string) $nim]);
            }
        });
    }

    /**
     * State untuk dokumen dosen & staf.
     */
    public function dosenStaf(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::DOSEN_STAF,
            'owner_nim'    => null,
        ]);
    }

    /**
     * State untuk dokumen akademik.
     */
    public function akademik(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::AKADEMIK,
            'owner_nim'    => null,
        ]);
    }

    /**
     * State untuk dokumen publik.
     */
    public function publik(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::PUBLIK,
            'owner_nim'    => null,
        ]);
    }

    /**
     * State untuk dokumen legacy (access_group NULL).
     */
    public function legacy(string $visibility = 'publik'): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => null,
            'visibility'   => $visibility,
            'owner_nim'    => null,
        ]);
    }

    /**
     * State untuk dokumen di root (tanpa folder).
     */
    public function withoutCategory(?AccessGroup $accessGroup = AccessGroup::PUBLIK): static
    {
        return $this->state(fn (array $attributes) => [
            'document_category_id' => null,
            'access_group'         => $accessGroup,
        ]);
    }
}
