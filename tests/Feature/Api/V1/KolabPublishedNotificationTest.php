<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabPublishedNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publishing_a_kolab_notifies_the_business_creator(): void
    {
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($business)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => 'kolab_published',
            'target_id' => $kolab->id,
        ]);
    }

    public function test_publishing_a_kolab_does_not_notify_a_community_creator(): void
    {
        $community = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $community->id,
            'type' => 'kolab_published',
        ]);
    }
}
