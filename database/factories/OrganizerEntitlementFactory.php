<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizerCapability;
use App\Models\OrganizerEntitlement;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizerEntitlement>
 */
class OrganizerEntitlementFactory extends Factory
{
    /**
     * @var class-string<OrganizerEntitlement>
     */
    protected $model = OrganizerEntitlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->business(),
            'capability' => OrganizerCapability::EventCreator,
            'source' => 'maintainer',
            'granted_at' => now(),
            'expires_at' => now()->addMonths(12),
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }
}
