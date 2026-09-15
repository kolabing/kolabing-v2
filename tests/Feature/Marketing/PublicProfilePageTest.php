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
 * the full review list, reviewer identities, past-event detail, or collaboration
 * partners does not break the page — it quietly gives away the reason to sign up.
 * Basic business info (website/Instagram/phone/address) is deliberately NOT in that
 * category as of 2026-09-15 — see PublicProfilePageController's doc comment.
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

    /**
     * Policy reversed 2026-09-15 (Daniel, benchmarking against Yelp/Google Business
     * Profile/TripAdvisor — none of them gate basic business info): website and
     * Instagram ARE now public, same as every benchmarked platform. What actually
     * drives signup is in-app messaging and the full review/past-event/partner
     * detail — see the other tests in this class for those guards, which are
     * unchanged. tiktok has no rendering wired (community-only field, not part of
     * this pass) so it still must not leak.
     */
    public function test_website_and_instagram_are_public_but_tiktok_is_not_wired(): void
    {
        $profile = $this->community();

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile))
            ->assertOk()
            ->assertSee('barcelona-runners.example', false)
            ->assertSee('instagram.com/barcelonarunners', false)
            ->assertDontSee('bcnrunners');
    }

    public function test_a_business_shows_its_phone_and_address_publicly(): void
    {
        $profile = Profile::factory()->business()->create(['phone_number' => '+52 55 1234 5678']);

        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Cafe Luna',
            'primary_venue' => ['formatted_address' => 'Av. Insurgentes 123, CDMX'],
        ]);

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile->fresh()))
            ->assertOk()
            ->assertSee('+52 55 1234 5678', false)
            ->assertSee('Av. Insurgentes 123, CDMX', false);
    }

    public function test_a_bare_instagram_handle_is_normalised_into_a_real_link(): void
    {
        $profile = $this->business('Cafe Luna');
        $profile->businessProfile()->update(['instagram' => '@cafeluna']);

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile->fresh()))
            ->assertOk()
            ->assertSee('href="https://instagram.com/cafeluna"', false)
            ->assertDontSee('href="@cafeluna"', false);
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

    /**
     * `business_profiles.offer_photos` (written by the admin Google Maps import,
     * admin.users._places-import) was never read here -- so a business that imported
     * 6 real photos showed exactly one (profile_photo) on its own public page. Caught
     * live 2026-09-15 benchmarking against real listing platforms (Yelp/Google
     * Business/TripAdvisor all show a real multi-photo gallery).
     */
    public function test_offer_photos_from_the_maps_import_appear_in_the_public_gallery(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Cafe con Fotos',
            'profile_photo' => 'https://cdn.example/main.jpg',
            'offer_photos' => [
                'https://cdn.example/offer-1.jpg',
                'https://cdn.example/offer-2.jpg',
            ],
        ]);

        $response = $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile->fresh()));

        $response->assertOk()
            ->assertSee('main.jpg', false)
            ->assertSee('offer-1.jpg', false);
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

    public function test_the_sitemap_only_lists_profiles_worth_reading(): void
    {
        // "Has a completed collaboration" used to be the bar, which let a seeded
        // test account into the index and would have published hundreds of empty,
        // near-identical profiles. The bar is now something a reader comes for.
        $withReview = $this->community();
        $reviewer = $this->business();
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $reviewer->id,
            'applicant_profile_id' => $withReview->id,
            'status' => 'completed',
        ]);
        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $withReview->id,
            'rating' => 5,
        ]);

        $withPhotos = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $withPhotos->id, 'name' => 'Photo Heavy Club']);
        foreach (range(1, 3) as $i) {
            ProfileGalleryPhoto::factory()->create([
                'profile_id' => $withPhotos->id,
                'url' => "https://cdn.example/gallery-{$i}.jpg",
            ]);
        }

        // Nothing to show, even though the collaboration completed — this is the
        // shape the production test account had.
        $empty = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $empty->id, 'name' => 'Empty Shell']);
        $kolab = Kolab::factory()->published()->forCreator($empty)->create();
        Collaboration::factory()->create([
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $empty->id,
            'applicant_profile_id' => $this->business()->id,
            'status' => 'completed',
        ]);

        $response = $this->get('http://kolabing.com/sitemap.xml');

        $response->assertOk()
            ->assertSee(url('/p/'.PublicProfileLink::slugFor($withReview)), false)
            ->assertSee(url('/p/'.PublicProfileLink::slugFor($withPhotos->fresh())), false)
            ->assertDontSee(url('/p/'.PublicProfileLink::slugFor($empty->fresh())), false);
    }

    public function test_an_empty_profile_page_asks_not_to_be_indexed(): void
    {
        $empty = $this->community();

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($empty))
            ->assertOk()
            // Still reachable — people share these links — but not indexable.
            ->assertSee('noindex,follow', false);
    }

    public function test_a_profile_with_photos_is_indexable(): void
    {
        $profile = $this->community();
        foreach (range(1, 3) as $i) {
            ProfileGalleryPhoto::factory()->create([
                'profile_id' => $profile->id,
                'url' => "https://cdn.example/p{$i}.jpg",
                'sort_order' => $i,
            ]);
        }

        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile))
            ->assertOk()
            ->assertDontSee('noindex', false);
    }

    public function test_the_meta_description_says_something_without_a_rating(): void
    {
        // A bare name + type was 56 characters on a real production profile.
        $profile = $this->business('Cafe Luna', 'A neighbourhood cafe on Carrer de Sants that hosts small tastings.');

        $response = $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile));

        preg_match('/<meta name="description" content="([^"]*)"/', $response->getContent(), $m);

        $this->assertNotEmpty($m, 'no meta description rendered');
        $this->assertGreaterThan(100, mb_strlen($m[1]), 'meta description is still too thin: '.$m[1]);
        $this->assertStringContainsString('neighbourhood cafe', $m[1]);
    }
}
