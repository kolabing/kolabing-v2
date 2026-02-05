<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeeProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendee_profile_is_created_with_defaults(): void
    {
        $profile = Profile::factory()->attendee()->create();
        $attendeeProfile = AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $this->assertDatabaseHas('attendee_profiles', [
            'id' => $attendeeProfile->id,
            'profile_id' => $profile->id,
            'total_points' => 0,
            'total_challenges_completed' => 0,
            'total_events_attended' => 0,
            'global_rank' => null,
        ]);
    }

    public function test_profile_has_attendee_profile_relationship(): void
    {
        $profile = Profile::factory()->attendee()->create();
        $attendeeProfile = AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $profile->refresh();

        $this->assertNotNull($profile->attendeeProfile);
        $this->assertInstanceOf(AttendeeProfile::class, $profile->attendeeProfile);
        $this->assertEquals($attendeeProfile->id, $profile->attendeeProfile->id);
    }

    public function test_profile_is_attendee_returns_correct_value(): void
    {
        $attendeeProfile = Profile::factory()->attendee()->create();
        $businessProfile = Profile::factory()->business()->create();
        $communityProfile = Profile::factory()->community()->create();

        $this->assertTrue($attendeeProfile->isAttendee());
        $this->assertFalse($businessProfile->isAttendee());
        $this->assertFalse($communityProfile->isAttendee());
    }

    public function test_attendee_user_type_value(): void
    {
        $this->assertEquals('attendee', UserType::Attendee->value);
    }

    public function test_get_extended_profile_returns_attendee_profile(): void
    {
        $profile = Profile::factory()->attendee()->create();
        $attendeeProfile = AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $profile->refresh();

        $extendedProfile = $profile->getExtendedProfile();

        $this->assertNotNull($extendedProfile);
        $this->assertInstanceOf(AttendeeProfile::class, $extendedProfile);
        $this->assertEquals($attendeeProfile->id, $extendedProfile->id);
    }
}
