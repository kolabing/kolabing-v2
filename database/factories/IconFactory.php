<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Icon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Icon>
 */
class IconFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->words(2, true));

        return [
            'label' => Str::headline($slug),
            'slug' => $slug,
            'filename' => 'category-'.$slug.'.svg',
            'source' => Icon::SOURCE_UPLOADED,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function bundled(): static
    {
        return $this->state(fn (): array => ['source' => Icon::SOURCE_BUNDLED]);
    }
}
