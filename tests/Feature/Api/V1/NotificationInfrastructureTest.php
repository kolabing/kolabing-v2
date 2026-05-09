<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationInfrastructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notification_tables_expose_the_new_persistence_contract(): void
    {
        $this->assertTrue(Schema::hasTable('device_tokens'));
        $this->assertTrue(Schema::hasTable('notification_deliveries'));

        $this->assertTrue(Schema::hasColumns('notifications', [
            'deeplink',
            'image_url',
            'data',
            'priority',
            'is_in_app',
            'is_push',
            'dedupe_key',
            'queued_at',
        ]));

        $this->assertTrue(Schema::hasColumns('notification_preferences', [
            'messages_enabled',
            'applications_enabled',
            'collaborations_enabled',
            'rewards_enabled',
            'marketing_enabled',
            'quiet_hours_start',
            'quiet_hours_end',
            'timezone',
        ]));
    }

    public function test_notifications_index_returns_new_notification_contract_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        Notification::factory()->forProfile($profile)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'notification_id',
                        'type',
                        'title',
                        'body',
                        'deeplink',
                        'priority',
                        'target_id',
                        'target_type',
                    ],
                ],
            ]);
    }

    public function test_notification_preferences_endpoint_returns_new_flags_and_quiet_hours(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        NotificationPreference::factory()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'messages_enabled',
                    'applications_enabled',
                    'collaborations_enabled',
                    'rewards_enabled',
                    'marketing_enabled',
                    'quiet_hours_start',
                    'quiet_hours_end',
                    'timezone',
                ],
            ]);
    }
}
