<?php

namespace Database\Factories;

use App\Models\CrmAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmActivity>
 */
class CrmActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_account_id' => CrmAccount::query()->where('type', 'community')->value('id'),
            'type' => 'note',
            'actor' => $this->faker->firstName(),
            'body' => $this->faker->sentence(),
            'meta' => null,
        ];
    }
}
