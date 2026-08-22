<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MultiKolabEventStatus;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabEventStatusEvent;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MultiKolabEventStatusEvent>
 */
class MultiKolabEventStatusEventFactory extends Factory
{
    /**
     * @var class-string<MultiKolabEventStatusEvent>
     */
    protected $model = MultiKolabEventStatusEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'multi_kolab_event_id' => MultiKolabEvent::factory(),
            'status' => MultiKolabEventStatus::Recruiting,
            'actor_profile_id' => Profile::factory()->business(),
            'reason' => null,
        ];
    }
}
