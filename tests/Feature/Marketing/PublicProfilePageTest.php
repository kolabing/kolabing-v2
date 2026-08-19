<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use App\Support\PublicProfileLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The shareable teaser at kolabing.com/p/{slug}.
 *
 * The point of these tests is the WALL: this page is public and indexable, so every
 * assertion about what it must NOT contain is load-bearing. A regression that leaks
 * contact details or the review list does not break the page — it quietly gives away
 * the reason to sign up.
 */
class PublicProfilePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(): Profile
    {
        $profile = Profile::factory()->community()->create();

        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Barcelona Runners',
            'about' => 'We run every Sunday morning along the beach and we are 400 members strong.',
            'instagram' => 'barcelonarunners',
            'website' => 'https://barcelona-runners.example',
            'tiktok' => 'bcnrunners',
            // Left out on purpose: the avatar occupies the first aggregated photo
            // slot, and these tests pin how many GALLERY photos surface.
            'profile_photo' => null,
        ]);

        return $profile->fresh();
    }

    private function business(string $name = 'Cafe Luna', ?string $about = null): Profile
    {
        $profile = Profile::factory()->business()->create();

        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'about' => $about,
        ]);

        return $profile->fresh();
    }

    public function test_the_page_renders_for_a_community(): void
    {
        $profile = $this->community();

        $response = $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile));

        $response->assertOk()
            ->assertSee('Barcelona Runners')
            ->assertSee('We run every Sunday morning', false)
            // The whole point of the page: turn a visitor into an account.
            ->assertSee('Create your free account')
            ->assertSee(rtrim(config('webapp.url'), '/').'/register', false);
    }

    public function test_the_page_renders_for_a_business_too(): void
    {
        // `kolabs.past_events` is written by any creator, so businesses have always
        // had this data; only the community-scoped endpoint made it look otherwise.
        $profile = $this->business('Cafe Luna', 'A neighbourhood cafe.');

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile))
            ->assertOk()
            ->assertSee('Cafe Luna')
            ->assertSee('A neighbourhood cafe.');
    }

    public function test_contact_details_are_never_in_the_public_html(): void
    {
        // Contact details are the reason to create an account. They must be absent
        // from the markup, not hidden with CSS.
        $profile = $this->community();

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile))
            ->assertOk()
            ->assertDontSee('barcelona-runners.example')
            ->assertDontSee('instagram.com/barcelonarunners')
            ->assertDontSee('barcelonarunners')
            ->assertDontSee('bcnrunners');
    }

    public function test_only_one_review_shows_and_the_reviewer_stays_anonymous(): void
    {
        $reviewed = $this->community();
        $reviewer = $this->business('Cafe Luna');

        foreach ([
            ['comment' => 'They filled our terrace on a Tuesday.', 'daysAgo' => 30],
            ['comment' => 'Second quote nobody should see for free.', 'daysAgo' => 2],
        ] as $row) {
            $collaboration = Collaboration::factory()->create([
                'creator_profile_id' => $reviewer->id,
                'applicant_profile_id' => $reviewed->id,
                'status' => 'completed',
            ]);

            CollaborationReview::factory()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $reviewer->id,
                'reviewed_profile_id' => $reviewed->id,
                'rating' => 5,
                'public_comment' => $row['comment'],
                'public_comment_visible' => true,
                'created_at' => now()->subDays($row['daysAgo']),
            ]);
        }

        $response = $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($reviewed));

        $response->assertOk()
            // Newest visible comment only…
            ->assertSee('Second quote nobody should see for free.', false)
            ->assertDontSee('They filled our terrace on a Tuesday.', false)
            // …and never who wrote it.
            ->assertDontSee('Cafe Luna')
            ->assertSee('Verified business partner');
    }

    public function test_a_review_whose_author_kept_it_private_is_not_quoted(): void
    {
        $reviewed = $this->community();
        $reviewer = $this->business();

        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $reviewer->id,
            'applicant_profile_id' => $reviewed->id,
            'status' => 'completed',
        ]);

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
            'rating' => 4,
            'public_comment' => 'Private feedback, not for the open web.',
            'public_comment_visible' => false,
        ]);

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($reviewed))
            ->assertOk()
            ->assertDontSee('Private feedback, not for the open web.', false);
    }

    public function test_at_most_three_photos_are_public_and_the_rest_are_counted(): void
    {
        $profile = $this->community();

        foreach (range(1, 6) as $i) {
            ProfileGalleryPhoto::factory()->create([
                'profile_id' => $profile->id,
                'url' => "https://cdn.example/photo-{$i}.jpg",
                'sort_order' => $i,
            ]);
        }

        $response = $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile));

        $response->assertOk()
            ->assertSee('photo-1.jpg', false)
            ->assertSee('photo-3.jpg', false)
            ->assertDontSee('photo-4.jpg', false)
            ->assertSee('more in the app');
    }

    public function test_the_page_carries_seo_metadata_and_a_canonical_slug(): void
    {
        $profile = $this->community();
        $slug = PublicProfileLink::slugFor($profile);

        $response = $this->get('http://kolabing.com/p/'.$slug);

        $response->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/p/'.$slug).'">', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('og:type" content="profile', false)
            ->assertSee('index,follow', false);
    }

    public function test_a_business_page_declares_itself_a_local_business(): void
    {
        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($this->business()))
            ->assertOk()
            ->assertSee('"@type":"LocalBusiness"', false);
    }

    public function test_an_aggregate_rating_is_only_claimed_when_reviews_exist(): void
    {
        $profile = $this->community();

        // No reviews → claiming a rating in structured data would be a lie Google
        // penalises, so the block must be absent entirely.
        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile))
            ->assertOk()
            ->assertDontSee('aggregateRating', false);
    }

    public function test_attendees_have_no_public_page(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($attendee->fresh()))
            ->assertNotFound();
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->get('http://kolabing.com/p/nobody-abc123')->assertNotFound();
        $this->get('http://kolabing.com/p/whatever')->assertNotFound();
    }

    public function test_a_renamed_profile_keeps_its_old_links_working(): void
    {
        // The readable half of the slug is decoration; the uuid tail resolves it.
        $profile = $this->community();
        $oldSlug = PublicProfileLink::slugFor($profile);

        $profile->communityProfile()->update(['name' => 'Barcelona Trail Club']);
        $profile->refresh();

        $this->get('http://kolabing.com/p/'.$oldSlug)->assertOk()->assertSee('Barcelona Trail Club');
    }

    public function test_a_full_uuid_also_resolves(): void
    {
        $profile = $this->community();

        $this->get('http://kolabing.com/p/'.$profile->id)->assertOk()->assertSee('Barcelona Runners');
    }

    public function test_the_sitemap_lists_profiles_with_a_completed_collaboration(): void
    {
        $withHistory = $this->community();
        $withoutHistory = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->forCreator($withHistory)->create();
        Collaboration::factory()->create([
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $withHistory->id,
            'applicant_profile_id' => $this->business()->id,
            'status' => 'completed',
        ]);

        $response = $this->get('http://kolabing.com/sitemap.xml');

        $response->assertOk()
            ->assertSee(url('/p/'.PublicProfileLink::slugFor($withHistory)), false)
            // An empty profile is a thin page; it stays out of the index.
            ->assertDontSee(url('/p/'.PublicProfileLink::slugFor($withoutHistory->fresh())), false);
    }
}
