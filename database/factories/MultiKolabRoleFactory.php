<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MultiKolabCompensationType;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabRoleStatus;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MultiKolabRole>
 */
class MultiKolabRoleFactory extends Factory
{
    /**
     * @var class-string<MultiKolabRole>
     */
    protected $model = MultiKolabRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'multi_kolab_event_id' => MultiKolabEvent::factory(),
            'status' => MultiKolabRoleStatus::Open,
            'title' => fake()->jobTitle(),
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'positions_needed' => 1,
            'positions_filled' => 0,
            'required' => true,
            'need' => fake()->sentence(),
            'receive' => fake()->sentence(),
            'compensation_type' => MultiKolabCompensationType::ValueExchange,
            'requirements' => fake()->sentence(),
            'details' => fake()->paragraph(),
        ];
    }

    public function filled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MultiKolabRoleStatus::Filled,
            'positions_filled' => $attributes['positions_needed'] ?? 1,
        ]);
    }
}
