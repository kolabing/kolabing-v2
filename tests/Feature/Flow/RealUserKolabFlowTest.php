<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Enums\ProductType;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\CommunityType;
use App\Models\OfferOption;
use App\Models\Profile;
use App\Support\OfferOptionValues;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CommunityTypeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Real-user end-to-end happy path for the core kolab flow.
 *
 * Drives the live API exactly as the mobile app would — real registration and
 * bearer-token auth, no `actingAs` shortcut — through the whole chain:
 *
 *   business registers → logs in → (paid) creates & publishes a kolab →
 *   community registers → applies → business accepts (collaboration created) →
 *   the two parties exchange chat messages.
 *
 * Deliberately ONE broad scenario rather than granular coverage: a smoke
 * pipeline proving the entire flow is wired together end to end.
 */
class RealUserKolabFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reference taxonomies the registration + kolab-create payloads validate
        // against (exists: rules on cities / business_types / community_types).
        $this->seed([CitySeeder::class, BusinessTypeSeeder::class, CommunityTypeSeeder::class]);
    }

    public function test_full_kolab_flow_from_registration_to_chat(): void
    {
        $city = City::query()->firstOrFail();
        $businessType = BusinessType::query()->firstOrFail();
        $communityType = CommunityType::query()->firstOrFail();
        $offering = OfferOptionValues::for(OfferOption::KIND_OFFERING)[0];

        // 1. Business registers (product path — no venue) and gets a bearer token.
        $this->postJson('/api/v1/auth/register/business', [
            'email' => 'flow.business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Flow Test Cafe',
            'business_type' => $businessType->slug,
            'has_venue' => false,
            'city_id' => $city->id,
        ])->assertCreated()->assertJsonPath('success', true);

        // 2. Business logs in with the password it just set (round-trip proof).
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'flow.business@example.com',
            'password' => 'password123',
        ]);
        $login->assertOk()->assertJsonPath('success', true);
        $businessToken = $login->json('data.token');
        $this->assertNotEmpty($businessToken);

        // Publishing requires an active subscription. Registration already creates
        // an inactive subscription row, so activate it directly to simulate a paid
        // business (Stripe handles this in production) without calling Stripe.
        $business = Profile::query()->where('email', 'flow.business@example.com')->firstOrFail();
        $business->subscription()->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
        ]);

        // 3. Business creates a product-promotion kolab (starts as a draft).
        $createKolab = $this->asToken($businessToken)->postJson('/api/v1/kolabs', [
            'intent_type' => 'product_promotion',
            'title' => 'Promote our specialty coffee beans',
            'description' => 'We want local communities to feature our single-origin coffee at their events.',
            'preferred_city' => $city->name,
            'offering' => [$offering],
            'product_name' => 'Single-origin coffee',
            'product_type' => ProductType::values()[0],
        ]);
        $createKolab->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.intent_type', 'product_promotion');
        $kolabId = $createKolab->json('data.id');

        // 4. Business publishes the kolab so communities can discover & apply.
        $this->asToken($businessToken)
            ->postJson("/api/v1/kolabs/{$kolabId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // 5. A different community account registers and gets its own token.
        $registerCommunity = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'flow.community@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Flow Runners Club',
            'community_type' => $communityType->slug,
            'city_id' => $city->id,
        ]);
        $registerCommunity->assertCreated()->assertJsonPath('success', true);
        $communityToken = $registerCommunity->json('data.token');
        $this->assertNotEmpty($communityToken);

        // 6. Community applies to the published kolab.
        $apply = $this->asToken($communityToken)->postJson("/api/v1/kolabs/{$kolabId}/applications", [
            'message' => 'We would love to feature your coffee at our weekly run.',
            'availability' => 'Available on weekday evenings and most weekends throughout the month.',
        ]);
        $apply->assertCreated()->assertJsonPath('success', true);
        $applicationId = $apply->json('data.id');

        // 7. Business sees the application in its received list for the kolab.
        $this->asToken($businessToken)
            ->getJson("/api/v1/kolabs/{$kolabId}/applications")
            ->assertOk()
            ->assertJsonPath('success', true);

        // 8. Business accepts the application → a collaboration is created.
        $accept = $this->asToken($businessToken)->postJson("/api/v1/applications/{$applicationId}/accept", [
            'scheduled_date' => now()->addWeek()->toDateString(),
        ]);
        $accept->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', 'accepted');

        $collaborationId = $accept->json('data.collaboration.id');
        $this->assertNotEmpty($collaborationId);
        $this->assertDatabaseHas('collaborations', [
            'id' => $collaborationId,
            'kolab_id' => $kolabId,
        ]);

        // 9. Both parties exchange a chat message on the application thread.
        $this->asToken($communityToken)
            ->postJson("/api/v1/applications/{$applicationId}/messages", [
                'content' => 'Excited to collaborate with you!',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->asToken($businessToken)
            ->postJson("/api/v1/applications/{$applicationId}/messages", [
                'content' => 'Welcome aboard — let us plan the details.',
            ])
            ->assertCreated();

        // 10. The conversation is retrievable: both messages are on the thread.
        $this->asToken($communityToken)
            ->getJson("/api/v1/applications/{$applicationId}/messages")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * Authenticate the next request as the bearer-token's owner.
     *
     * Sanctum's guard caches the first user it resolves for the lifetime of the
     * test, so a plain withToken() switch would keep returning the first actor.
     * Forgetting the resolved guards forces re-resolution from this token.
     */
    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
