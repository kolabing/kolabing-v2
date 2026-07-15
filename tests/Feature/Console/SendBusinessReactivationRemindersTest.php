<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\BusinessSubscription;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SendBusinessReactivationRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Backdate a profile's updated_at directly via the query builder — Eloquent
     * mass assignment guards timestamps, so create()/update() can't set this.
     */
    private function backdateActivity(Profile $profile, int $daysAgo): void
    {
        DB::table('profiles')->where('id', $profile->id)->update(['updated_at' => now()->subDays($daysAgo)]);
    }

    public function test_notifies_inactive_business_with_active_subscription(): void
    {
        $business = Profile::factory()->business()->create();
        $this->backdateActivity($business, 20);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $this->artisan('app:send-business-reactivation-reminders')
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => 'reactivation_prompt',
        ]);
    }

    public function test_does_not_notify_recently_active_business(): void
    {
        $business = Profile::factory()->business()->create();
        $this->backdateActivity($business, 2);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $this->artisan('app:send-business-reactivation-reminders')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $business->id,
            'type' => 'reactivation_prompt',
        ]);
    }

    public function test_does_not_notify_business_without_active_subscription(): void
    {
        $business = Profile::factory()->business()->create();
        $this->backdateActivity($business, 20);
        BusinessSubscription::factory()->cancelled()->create(['profile_id' => $business->id]);

        $this->artisan('app:send-business-reactivation-reminders')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $business->id,
            'type' => 'reactivation_prompt',
        ]);
    }

    public function test_does_not_send_a_second_reminder_within_the_resend_window(): void
    {
        $business = Profile::factory()->business()->create();
        $this->backdateActivity($business, 20);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $this->artisan('app:send-business-reactivation-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);

        $this->artisan('app:send-business-reactivation-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
    }
}
