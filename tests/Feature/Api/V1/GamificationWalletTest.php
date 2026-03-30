<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\GamificationBadgeSlug;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationWalletTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/wallet
    |--------------------------------------------------------------------------
    */

    public function test_wallet_returns_auto_created_wallet_for_new_user(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.redeemed_points', 0)
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.eur_value', 0)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.can_withdraw', false)
            ->assertJsonPath('data.pending_withdrawal', false)
            ->assertJsonPath('data.withdrawal_threshold', 375);
    }

    public function test_wallet_returns_existing_wallet_with_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 127,
            'redeemed_points' => 0,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('data.points', 127)
            ->assertJsonPath('data.available_points', 127)
            ->assertJsonPath('data.eur_value', 25.4)
            ->assertJsonPath('data.can_withdraw', false);
    }

    public function test_wallet_shows_can_withdraw_true_when_eligible(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/wallet');

        $response->assertOk()
            ->assertJsonPath('data.can_withdraw', true)
            ->assertJsonPath('data.progress', 1);
    }

    public function test_wallet_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/gamification/wallet');

        $response->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/ledger
    |--------------------------------------------------------------------------
    */

    public function test_ledger_returns_paginated_entries(): void
    {
        $profile = $this->createCommunityProfile();
        PointLedger::factory()->count(3)->forProfile($profile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'points', 'event_type', 'description', 'reference_id', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_ledger_returns_empty_for_user_without_entries(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_ledger_does_not_show_other_users_entries(): void
    {
        $profile = $this->createCommunityProfile();
        $otherProfile = $this->createCommunityProfile();
        PointLedger::factory()->forProfile($otherProfile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_ledger_pagination_works(): void
    {
        $profile = $this->createCommunityProfile();
        PointLedger::factory()->count(25)->forProfile($profile)->collaborationComplete()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/ledger?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/badges
    |--------------------------------------------------------------------------
    */

    public function test_badges_returns_all_5_badges(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/badges');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data');

        $slugs = collect($response->json('data'))->pluck('slug')->sort()->values()->toArray();
        $this->assertSame([
            'community_earner',
            'content_creator',
            'first_kolab',
            'power_partner',
            'referral_pioneer',
        ], $slugs);
    }

    public function test_badges_shows_unlocked_status_for_earned_badges(): void
    {
        $profile = $this->createCommunityProfile();
        EarnedBadge::factory()->forProfile($profile)->slug(GamificationBadgeSlug::FirstKolab)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/badges');

        $response->assertOk();

        $data = collect($response->json('data'));
        $firstKolab = $data->firstWhere('slug', 'first_kolab');
        $contentCreator = $data->firstWhere('slug', 'content_creator');

        $this->assertTrue($firstKolab['is_unlocked']);
        $this->assertNotNull($firstKolab['earned_at']);
        $this->assertFalse($contentCreator['is_unlocked']);
        $this->assertNull($contentCreator['earned_at']);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/gamification/referral-code
    |--------------------------------------------------------------------------
    */

    public function test_referral_code_creates_code_on_first_access(): void
    {
        $profile = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/referral-code');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['code', 'referral_link', 'total_conversions', 'total_points_earned'],
            ])
            ->assertJsonPath('data.total_conversions', 0);

        $this->assertStringStartsWith('KOLAB-', $response->json('data.code'));
        $this->assertDatabaseHas('referral_codes', ['profile_id' => $profile->id]);
    }

    public function test_referral_code_returns_existing_code(): void
    {
        $profile = $this->createCommunityProfile();
        ReferralCode::factory()->forProfile($profile)->create(['code' => 'KOLAB-TEST']);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/gamification/referral-code');

        $response->assertOk()
            ->assertJsonPath('data.code', 'KOLAB-TEST');
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/v1/gamification/withdrawal
    |--------------------------------------------------------------------------
    */

    public function test_withdrawal_succeeds_with_enough_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points', 375)
            ->assertJsonPath('data.eur_amount', 75)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.account_holder', 'BCN Running Club SL');

        // IBAN should be masked
        $this->assertStringContainsString('****', $response->json('data.iban'));

        // Wallet should be updated
        $this->assertDatabaseHas('wallets', [
            'profile_id' => $profile->id,
            'redeemed_points' => 375,
            'pending_withdrawal' => true,
        ]);

        // Ledger entry should exist
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'points' => -375,
            'event_type' => 'withdrawal',
        ]);
    }

    public function test_withdrawal_fails_with_insufficient_points(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withPoints(120)->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient points. Need 375, have 120.');
    }

    public function test_withdrawal_fails_with_pending_withdrawal(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->pendingWithdrawal()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A withdrawal is already pending.');
    }

    public function test_withdrawal_fails_without_iban(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'account_holder' => 'BCN Running Club SL',
            ]);

        $response->assertStatus(422);
    }

    public function test_withdrawal_fails_without_account_holder(): void
    {
        $profile = $this->createCommunityProfile();
        Wallet::factory()->withdrawable()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/gamification/withdrawal', [
                'iban' => 'ES7921000813610123456789',
            ]);

        $response->assertStatus(422);
    }
}
