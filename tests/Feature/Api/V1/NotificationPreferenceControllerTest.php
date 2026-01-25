<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Show Notification Preferences Tests
    |--------------------------------------------------------------------------
    */

    public function test_show_notification_preferences_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/notification-preferences');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_show_notification_preferences_returns_existing_preferences(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);
        NotificationPreference::factory()->create([
            'profile_id' => $profile->id,
            'email_notifications' => true,
            'whatsapp_notifications' => false,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => false,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_notifications', true)
            ->assertJsonPath('data.whatsapp_notifications', false)
            ->assertJsonPath('data.new_application_alerts', true)
            ->assertJsonPath('data.collaboration_updates', true)
            ->assertJsonPath('data.marketing_tips', false)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'email_notifications',
                    'whatsapp_notifications',
                    'new_application_alerts',
                    'collaboration_updates',
                    'marketing_tips',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_notification_preferences_creates_default_preferences_if_not_exists(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        // Verify no preferences exist
        $this->assertDatabaseMissing('notification_preferences', [
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_notifications', true)
            ->assertJsonPath('data.whatsapp_notifications', true)
            ->assertJsonPath('data.new_application_alerts', true)
            ->assertJsonPath('data.collaboration_updates', true)
            ->assertJsonPath('data.marketing_tips', false);

        // Verify preferences were created with defaults
        $this->assertDatabaseHas('notification_preferences', [
            'profile_id' => $profile->id,
            'email_notifications' => true,
            'whatsapp_notifications' => true,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Notification Preferences Tests
    |--------------------------------------------------------------------------
    */

    public function test_update_notification_preferences_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/me/notification-preferences', [
            'email_notifications' => false,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_update_notification_preferences_successfully(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);
        NotificationPreference::factory()->create([
            'profile_id' => $profile->id,
            'email_notifications' => true,
            'whatsapp_notifications' => true,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => false,
        ]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', [
                'email_notifications' => false,
                'whatsapp_notifications' => false,
                'marketing_tips' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Notification preferences updated successfully')
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.whatsapp_notifications', false)
            ->assertJsonPath('data.new_application_alerts', true)
            ->assertJsonPath('data.collaboration_updates', true)
            ->assertJsonPath('data.marketing_tips', true);

        // Verify database was updated
        $this->assertDatabaseHas('notification_preferences', [
            'profile_id' => $profile->id,
            'email_notifications' => false,
            'whatsapp_notifications' => false,
            'marketing_tips' => true,
        ]);
    }

    public function test_update_notification_preferences_creates_if_not_exists(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        // Verify no preferences exist
        $this->assertDatabaseMissing('notification_preferences', [
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', [
                'email_notifications' => false,
                'marketing_tips' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.marketing_tips', true);

        // Verify preferences were created and updated
        $this->assertDatabaseHas('notification_preferences', [
            'profile_id' => $profile->id,
            'email_notifications' => false,
            'marketing_tips' => true,
        ]);
    }

    public function test_update_notification_preferences_allows_partial_updates(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);
        NotificationPreference::factory()->create([
            'profile_id' => $profile->id,
            'email_notifications' => true,
            'whatsapp_notifications' => true,
            'new_application_alerts' => true,
            'collaboration_updates' => true,
            'marketing_tips' => false,
        ]);

        // Only update one field
        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', [
                'email_notifications' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.whatsapp_notifications', true)
            ->assertJsonPath('data.new_application_alerts', true)
            ->assertJsonPath('data.collaboration_updates', true)
            ->assertJsonPath('data.marketing_tips', false);
    }

    public function test_update_notification_preferences_validates_boolean_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', [
                'email_notifications' => 'not-a-boolean',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email_notifications'],
            ]);
    }

    public function test_update_notification_preferences_ignores_unknown_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);
        NotificationPreference::factory()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/me/notification-preferences', [
                'email_notifications' => false,
                'unknown_field' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonMissing(['data.unknown_field']);
    }
}
