<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\IntentType;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Support\PublicKolabPoster;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * How the open web is allowed to describe whoever posted a Kolab.
 *
 * The interesting cases are all degradations: a community with no type set, a city
 * column holding the literal string "Unknown" (an older client wrote those, and there
 * are rows like it in production), a business with no extended profile yet. Each must
 * produce a sentence a stranger can read, and none must produce a community's name.
 */
class PublicKolabPosterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function kolab(Profile $creator, string $city = 'Barcelona'): Kolab
    {
        return Kolab::factory()->make([
            'creator_profile_id' => $creator->id,
            'intent_type' => IntentType::CommunitySeeking,
            'preferred_city' => $city,
        ])->setRelation('creatorProfile', $creator);
    }

    public function test_a_community_is_described_by_type_and_city(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Barcelona Runners',
            'community_type' => 'run_club',
        ]);

        $described = PublicKolabPoster::describe($this->kolab($profile->fresh()));

        $this->assertSame('A run club in Barcelona', $described['description']);
        $this->assertFalse($described['is_named']);
        $this->assertNull($described['name']);
    }

    public function test_a_hyphenated_type_slug_reads_as_words(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'community_type' => 'supper-club',
        ]);

        // §3.4: the label reads "Supper Club", never "Supper_Club" or "supper-club".
        $this->assertSame(
            'A supper club in Barcelona',
            PublicKolabPoster::describe($this->kolab($profile->fresh()))['description']
        );
    }

    public function test_a_community_with_no_type_still_reads_as_a_sentence(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'community_type' => null,
        ]);

        $this->assertSame(
            'A community in Barcelona',
            PublicKolabPoster::describe($this->kolab($profile->fresh()))['description']
        );
    }

    /** "Unknown" is a real value in `preferred_city`; printing it would be worse than omitting the place. */
    public function test_the_literal_unknown_city_is_dropped_rather_than_printed(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'community_type' => 'run_club',
        ]);

        $described = PublicKolabPoster::describe($this->kolab($profile->fresh(), 'Unknown'));

        $this->assertSame('A local run club', $described['description']);
        $this->assertStringNotContainsString('Unknown', $described['description']);
    }

    public function test_an_empty_city_is_dropped_too(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id, 'community_type' => 'run_club']);

        $this->assertSame(
            'A local run club',
            PublicKolabPoster::describe($this->kolab($profile->fresh(), ''))['description']
        );
    }

    public function test_a_business_is_named(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Honest Greens',
        ]);

        $described = PublicKolabPoster::describe($this->kolab($profile->fresh()));

        $this->assertSame('Honest Greens', $described['description']);
        $this->assertSame('Honest Greens', $described['name']);
        $this->assertTrue($described['is_named']);
    }

    public function test_a_business_with_no_name_yet_falls_back_to_a_description(): void
    {
        $profile = Profile::factory()->business()->create(['name' => null]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => null,
            'business_type' => 'restaurant',
        ]);

        $described = PublicKolabPoster::describe($this->kolab($profile->fresh()));

        $this->assertSame('A restaurant in Barcelona', $described['description']);
        $this->assertFalse($described['is_named']);
    }
}
