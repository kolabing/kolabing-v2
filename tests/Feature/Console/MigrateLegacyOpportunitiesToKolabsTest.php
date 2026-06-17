<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MigrateLegacyOpportunitiesToKolabsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_reports_without_writing(): void
    {
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $creator->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($creator)
            ->create(['title' => 'Legacy rooftop collab']);

        $this->artisan('kolabs:migrate-legacy-opportunities --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseMissing('kolabs', [
            'id' => $opportunity->id,
        ]);
    }

    public function test_command_migrates_legacy_opportunities_and_backfills_links(): void
    {
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $creator->id]);
        $applicant = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($creator)
            ->create([
                'title' => 'Legacy rooftop collab',
                'description' => 'Legacy description',
                'preferred_city' => 'Sevilla',
                'business_offer' => ['venue_space' => true],
                'community_deliverables' => ['social_media_content' => true],
                'categories' => ['food', 'wellness'],
                'venue_mode' => 'business_provides',
                'address' => 'Calle Sol 1',
                'offer_photo' => 'https://example.com/offer.jpg',
            ]);

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($applicant)
            ->create();

        $collaboration = Collaboration::factory()
            ->forApplication($application)
            ->forOpportunity($opportunity)
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->create();

        $this->artisan('kolabs:migrate-legacy-opportunities')
            ->assertSuccessful();

        $this->assertDatabaseHas('kolabs', [
            'id' => $opportunity->id,
            'creator_profile_id' => $creator->id,
            'title' => 'Legacy rooftop collab',
            'status' => 'published',
            'preferred_city' => 'Sevilla',
        ]);

        $this->assertSame($opportunity->id, $application->fresh()->kolab_id);
        $this->assertSame($opportunity->id, $collaboration->fresh()->kolab_id);

        $kolab = Kolab::query()->findOrFail($opportunity->id);
        $this->assertSame(['url' => 'https://example.com/offer.jpg', 'type' => 'image'], $kolab->media[0]);
    }
}
