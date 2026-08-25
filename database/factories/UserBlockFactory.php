<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
class UserBlockFactory extends Factory
{
    /**
     * @var class-string<UserBlock>
     */
    protected $model = UserBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blocker_profile_id' => Profile::factory(),
            'blocked_profile_id' => Profile::factory(),
        ];
    }
}
