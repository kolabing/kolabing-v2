<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PointEventType;
use App\Models\EarnedBadge;
use App\Models\Profile;
use App\Models\Wallet;
use App\Services\GamificationWalletService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationWalletServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private GamificationWalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GamificationWalletService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | awardPoints()
    |--------------------------------------------------------------------------
    */

    public function test_award_points_creates_wallet_if_not_exists(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 1,
        ]);
    }

    public function test_award_points_creates_ledger_entry(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => 1,
            'event_type' => 'collaboration_complete',
            'reference_id' => 'collab-uuid',
            'description' => 'Collaboration completed',
        ]);
    }

    public function test_award_points_increments_existing_wallet(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 10,
        ]);

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 11,
        ]);
    }

    public function test_award_points_with_referral_conversion(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            50,
            PointEventType::ReferralConversion,
            'referral-uuid',
            'Referral: BCN Yoga Studio subscribed'
        );

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'points' => 50,
        ]);
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => 50,
            'event_type' => 'referral_conversion',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | evaluateBadges()
    |--------------------------------------------------------------------------
    */

    public function test_first_kolab_badge_awarded_after_first_collaboration_complete(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'first_kolab',
        ]);
    }

    public function test_content_creator_badge_awarded_after_3_reviews(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        for ($i = 1; $i <= 3; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::ReviewPosted,
                "review-$i",
                "Review $i"
            );
        }

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'content_creator',
        ]);
    }

    public function test_content_creator_badge_not_awarded_with_only_2_reviews(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        for ($i = 1; $i <= 2; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::ReviewPosted,
                "review-$i",
                "Review $i"
            );
        }

        $this->assertDatabaseMissing('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'content_creator',
        ]);
    }

    public function test_community_earner_badge_awarded_at_100_points(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 99]);

        $this->service->awardPoints(
            $profile->id,
            1,
            PointEventType::CollaborationComplete,
            'collab-uuid',
            'Collaboration completed'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'community_earner',
        ]);
    }

    public function test_referral_pioneer_badge_awarded_after_first_referral(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints(
            $profile->id,
            50,
            PointEventType::ReferralConversion,
            'referral-uuid',
            'Referral converted'
        );

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'referral_pioneer',
        ]);
    }

    public function test_power_partner_badge_awarded_after_5_collaborations(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create(['profile_id' => $profile->id, 'points' => 0]);

        for ($i = 1; $i <= 5; $i++) {
            $this->service->awardPoints(
                $profile->id,
                1,
                PointEventType::CollaborationComplete,
                "collab-$i",
                "Collab $i"
            );
        }

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $profile->id,
            'badge_slug' => 'power_partner',
        ]);
    }

    public function test_badge_not_awarded_twice(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->service->awardPoints($profile->id, 1, PointEventType::CollaborationComplete, 'c1', 'C1');
        $this->service->awardPoints($profile->id, 1, PointEventType::CollaborationComplete, 'c2', 'C2');

        $count = EarnedBadge::query()
            ->where('profile_id', $profile->id)
            ->where('badge_slug', 'first_kolab')
            ->count();

        $this->assertSame(1, $count);
    }

    /*
    |--------------------------------------------------------------------------
    | getOrCreateWallet()
    |--------------------------------------------------------------------------
    */

    public function test_get_or_create_wallet_creates_new_wallet(): void
    {
        $profile = Profile::factory()->community()->create();

        $wallet = $this->service->getOrCreateWallet($profile->id);

        $this->assertInstanceOf(Wallet::class, $wallet);
        $this->assertSame($profile->id, $wallet->profile_id);
        $this->assertSame(0, $wallet->points);
    }

    public function test_get_or_create_wallet_returns_existing(): void
    {
        $profile = Profile::factory()->community()->create();
        $existing = Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 42,
        ]);

        $wallet = $this->service->getOrCreateWallet($profile->id);

        $this->assertSame($existing->id, $wallet->id);
        $this->assertSame(42, $wallet->points);
    }
}
