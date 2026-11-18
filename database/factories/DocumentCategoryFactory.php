<?php

namespace Database\Factories;

use App\Enums\AccessGroup;
use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    protected $model = DocumentCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name'            => ucfirst($name),
            'slug'            => Str::slug($name),
            'description'     => fake()->sentence(),
            'parent_id'       => null,
            'depth'           => 0,
            'visibility'      => 'publik',
            'access_group'    => AccessGroup::PUBLIK,
            'sort_order'      => 1,
            'is_pinned'       => false,
            'show_when_empty' => false,
        ];
    }

    /**
     * State untuk kategori mahasiswa.
     */
    public function mahasiswa(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::MAHASISWA,
        ]);
    }

    /**
     * State untuk kategori dosen & staf.
     */
    public function dosenStaf(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::DOSEN_STAF,
        ]);
    }

    /**
     * State untuk kategori akademik.
     */
    public function akademik(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::AKADEMIK,
        ]);
    }

    /**
     * State untuk kategori publik.
     */
    public function publik(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => AccessGroup::PUBLIK,
        ]);
    }

    /**
     * State untuk kategori legacy (access_group NULL).
     */
    public function legacy(string $visibility = 'publik'): static
    {
        return $this->state(fn (array $attributes) => [
            'access_group' => null,
            'visibility'   => $visibility,
        ]);
    }

    /**
     * State untuk subfolder dari parent category.
     */
    public function subfolder(DocumentCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id'    => $parent->id,
            'depth'        => $parent->depth + 1,
            'access_group' => $parent->access_group,
        ]);
    }
}
