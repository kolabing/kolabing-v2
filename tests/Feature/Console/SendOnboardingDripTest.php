<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\SendTransactionalEmail;
use App\Models\BusinessProfile;
use App\Models\CommunityMember;
use App\Models\OnboardingDripState;
use App\Models\Profile;
use App\Services\OnboardingDripService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SendOnboardingDripTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Backdate a drip state's scheduled_for/anchor_at directly — Eloquent mass
     * assignment guards timestamps via the model's casts but the point here is
     * to simulate time having passed, same technique as
     * SendBusinessReactivationRemindersTest::backdateActivity().
     */
    private function backdateSchedule(OnboardingDripState $state, int $hoursAgo): void
    {
        DB::table('onboarding_drip_states')->where('id', $state->id)->update([
            'anchor_at' => now()->subHours($hoursAgo),
            'scheduled_for' => now()->subHour(),
        ]);
    }

    public function test_start_for_profile_creates_state_scheduled_at_step_zero(): void
    {
        $profile = Profile::factory()->business()->create();

        app(OnboardingDripService::class)->startForProfile($profile);

        $this->assertDatabaseHas('onboarding_drip_states', [
            'profile_id' => $profile->id,
            'next_sequence' => 0,
        ]);
    }

    public function test_sends_welcome_email_at_step_zero(): void
    {
        Bus::fake();

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        app(OnboardingDripService::class)->startForProfile($profile);
        $state = OnboardingDripState::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->backdateSchedule($state, 0);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->to === $profile->email
            && $job->data['alias'] === 'business-welcome-01');

        $state->refresh();
        $this->assertSame(1, $state->next_sequence);
    }

    public function test_skips_complete_profile_nudge_when_profile_already_complete(): void
    {
        Bus::fake();

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Complete Cafe',
            'business_type' => 'cafe',
        ]);
        $state = OnboardingDripState::query()->create([
            'profile_id' => $profile->id,
            'anchor_at' => now()->subHours(48),
            'next_sequence' => 1,
            'scheduled_for' => now()->subHour(),
        ]);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertNotDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['alias'] === 'complete-profile-business');

        $state->refresh();
        $this->assertSame(2, $state->next_sequence, 'step should advance even when skipped');
    }

    public function test_sends_activation_nudge_when_no_first_action_taken(): void
    {
        Bus::fake();

        $profile = Profile::factory()->community()->create();
        $state = OnboardingDripState::query()->create([
            'profile_id' => $profile->id,
            'anchor_at' => now()->subHours(120),
            'next_sequence' => 2,
            'scheduled_for' => now()->subHour(),
        ]);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->to === $profile->email
            && $job->data['alias'] === 'activation-community');
    }

    public function test_cancels_drip_once_attendee_is_fully_activated(): void
    {
        Bus::fake();

        $profile = Profile::factory()->attendee()->create([
            'name' => 'Dana',
            'handle' => 'dana',
            'city_id' => null,
            'interests' => ['running'],
        ]);
        CommunityMember::factory()->create(['profile_id' => $profile->id]);

        $state = OnboardingDripState::query()->create([
            'profile_id' => $profile->id,
            'anchor_at' => now()->subHours(240),
            'next_sequence' => 3,
            'scheduled_for' => now()->subHour(),
        ]);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertNotDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->data['alias'] === 'inactive-nudge');

        $state->refresh();
        $this->assertNotNull($state->cancelled_at);
    }

    public function test_sends_inactive_nudge_at_final_step_and_cancels_after(): void
    {
        Bus::fake();

        $profile = Profile::factory()->attendee()->create();
        $state = OnboardingDripState::query()->create([
            'profile_id' => $profile->id,
            'anchor_at' => now()->subHours(240),
            'next_sequence' => 3,
            'scheduled_for' => now()->subHour(),
        ]);

        $this->artisan('app:send-onboarding-drip')->assertSuccessful();

        Bus::assertDispatched(SendTransactionalEmail::class, fn (SendTransactionalEmail $job): bool => $job->to === $profile->email
            && $job->data['alias'] === 'inactive-nudge');

        $state->refresh();
        $this->assertNotNull($state->cancelled_at, 'drip should be fully cancelled after the last step');
    }
}
