<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * ROLES §2.5: a free business cannot open a community's full profile or contact.
 *
 * `PublicProfileResource` had no subscription check of any kind (BE-FX-22), so the
 * identity a business is meant to pay for was one `GET /profiles/{id}` away for
 * anyone holding an id. The mobile app drew a blur over a payload that carried the
 * real name; the web panel drew nothing at all. Neither was a gate.
 *
 * The most damaging regression available here is not the mask failing to apply — it
 * is the mask applying to the wrong people. `Profile::hasActiveSubscription()`
 * returns false for **every** non-business, so a condition that tests the
 * subscription before the role masks every community and attendee on the platform.
 * Half the tests below exist for that one line.
 */
class PublicProfileMaskTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(bool $subscribed = false, string $name = 'Eixample 46'): Profile
    {
        $profile = Profile::factory()->business()->create([
            'avatar_url' => 'https://cdn.test/business.png',
        ]);

        BusinessProfile::factory()->create(['profile_id' => $profile->id, 'name' => $name]);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);
        }

        return $profile->fresh();
    }

    private function community(string $name = 'Barcelona Run Club'): Profile
    {
        $profile = Profile::factory()->community()->create([
            'avatar_url' => 'https://cdn.test/community.png',
            'handle' => \Illuminate\Support\Str::slug($name),
        ]);

        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'about' => 'We run every Sunday from the rambla.',
            'instagram' => '@barcelonarunclub',
            'website' => 'https://barcelonarunclub.example',
        ]);

        return $profile->fresh();
    }

    private function openProfile(Profile $viewer, Profile $subject): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$subject->id}/public-profile");
    }

    /** The other door onto the same identity — the one BE-FX-22 was filed against. */
    private function showProfile(Profile $viewer, Profile $subject): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$subject->id}");
    }

    // ── The mask ─────────────────────────────────────────────────────────

    public function test_a_free_business_cannot_read_a_communitys_identity(): void
    {
        $response = $this->openProfile($this->business(), $this->community())->assertOk();

        $response->assertJsonPath('data.identity_masked', true);

        foreach (['display_name', 'avatar_url', 'profile_photo', 'about', 'instagram', 'website', 'tiktok'] as $field) {
            $this->assertNull(
                $response->json("data.{$field}"),
                "data.{$field} must be withheld from a free business (ROLES §2.5)."
            );
        }

        // `public_url` is built from the handle, so it hands over the name in the
        // one field a mask would be assumed to have covered.
        $this->assertNull($response->json('data.public_url'));

        // Past events and collaborations name the partners; the gallery is the
        // community's own photographs (§4.2).
        foreach (['gallery', 'photos', 'past_events', 'past_collaborations'] as $list) {
            $this->assertSame([], $response->json("data.{$list}"), "data.{$list} must be empty under the mask.");
        }
    }

    /** A blur, not a block: everything that is not identity survives (golden rule 5). */
    public function test_the_mask_leaves_everything_that_is_not_identity(): void
    {
        $community = $this->community();

        $response = $this->openProfile($this->business(), $community)->assertOk();

        $response->assertJsonPath('data.user_type', 'community')
            ->assertJsonPath('data.id', $community->id);

        foreach (['community_type', 'city_name', 'public_stats'] as $kept) {
            $this->assertArrayHasKey($kept, $response->json('data'));
        }
    }

    public function test_subscribing_reveals_the_identity(): void
    {
        $response = $this->openProfile($this->business(subscribed: true), $this->community())->assertOk();

        $response->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');

        $this->assertNotNull($response->json('data.avatar_url'));
        $this->assertNotNull($response->json('data.instagram'));
        $this->assertNotNull($response->json('data.public_url'));
    }

    // ── Who must NEVER be masked ─────────────────────────────────────────

    /**
     * The regression this whole file exists for. A community has no subscription,
     * so a condition written subscription-first masks every community viewer.
     */
    public function test_a_community_viewer_is_never_masked_even_though_it_has_no_subscription(): void
    {
        $this->openProfile($this->community('Sevilla Run Club'), $this->community())
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');
    }

    /** Same trap, other role: an attendee has no subscription either. */
    public function test_an_attendee_viewer_is_never_masked(): void
    {
        $this->openProfile(Profile::factory()->attendee()->create(), $this->community())
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');
    }

    /** There is no business-identity paywall — only a community's is withheld. */
    public function test_a_free_business_still_sees_another_business(): void
    {
        $this->openProfile($this->business(), $this->business(name: 'Honest Greens'))
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Honest Greens');
    }

    public function test_nobody_is_masked_from_their_own_profile(): void
    {
        $community = $this->community();

        $this->openProfile($community, $community)
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');
    }

    // ── GET /profiles/{id} — the same identity, the other door ───────────

    /**
     * BE-FX-22 was filed against this endpoint by name: masking only the profile
     * *page* would leave the identity one plainer request away, which is not a mask
     * at all. Both resources now ask the same shared question.
     */
    public function test_the_plain_profile_endpoint_is_masked_too(): void
    {
        $response = $this->showProfile($this->business(), $this->community())->assertOk();

        $response->assertJsonPath('data.identity_masked', true);

        foreach (['display_name', 'avatar_url', 'logo_url', 'profile_photo', 'handle', 'about', 'instagram', 'website'] as $field) {
            $this->assertNull(
                $response->json("data.{$field}"),
                "data.{$field} must be withheld on GET /profiles/{id} as well (BE-FX-22)."
            );
        }

        // The reviewer list names this community's partners and links to them.
        $this->assertSame([], $response->json('data.recent_reviews'));

        // Still a blur: the aggregate reputation and the count survive.
        $this->assertArrayHasKey('reputation', $response->json('data'));
        $this->assertArrayHasKey('completed_kolabs_count', $response->json('data'));
    }

    public function test_the_plain_profile_endpoint_reveals_on_subscription(): void
    {
        $this->showProfile($this->business(subscribed: true), $this->community())
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');
    }

    /** Same short-circuit, same trap, on this endpoint too. */
    public function test_the_plain_profile_endpoint_never_masks_a_community_viewer(): void
    {
        $this->showProfile($this->community('Sevilla Run Club'), $this->community())
            ->assertOk()
            ->assertJsonPath('data.identity_masked', false)
            ->assertJsonPath('data.display_name', 'Barcelona Run Club');
    }
}
