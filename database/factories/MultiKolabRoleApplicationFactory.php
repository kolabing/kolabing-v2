<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\UserType;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MultiKolabRoleApplication>
 */
class MultiKolabRoleApplicationFactory extends Factory
{
    /**
     * @var class-string<MultiKolabRoleApplication>
     */
    protected $model = MultiKolabRoleApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'multi_kolab_role_id' => MultiKolabRole::factory(),
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community->value,
            'status' => MultiKolabRoleApplicationStatus::Pending,
            'pitch' => fake()->paragraph(),
            'availability' => fake()->sentence(),
            'kolab_id' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => MultiKolabRoleApplicationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }
}
