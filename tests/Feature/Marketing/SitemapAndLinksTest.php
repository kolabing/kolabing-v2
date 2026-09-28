<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use App\Models\RankingPage;
use App\Support\PublicProfileLink;
use Database\Seeders\BlogPostSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Bug report 28 Sep 2026, items 9 and 10: the blog label, the GEO posts that 404 on
 * prod until BlogPostSeeder runs, the sitemap's index pages and nameless profiles,
 * and `kolabing.com/c/<business-slug>` 301-ing to a 404.
 */
class SitemapAndLinksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(?string $name, int $photos = 3): Profile
    {
        $profile = Profile::factory()->business()->create(['name' => null]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id, 'name' => $name]);

        foreach (range(1, $photos) as $i) {
            ProfileGalleryPhoto::factory()->create([
                'profile_id' => $profile->id,
                'url' => "https://cdn.example/{$profile->id}-{$i}.jpg",
            ]);
        }

        return $profile->fresh();
    }

    public function test_the_blog_uses_the_community_led_footfall_label(): void
    {
        $this->get('http://kolabing.com/blog')
            ->assertOk()
            ->assertSee('Community-led footfall')
            ->assertDontSee('Community Commerce');
    }

    public function test_the_geo_posts_render_once_the_blog_seeder_has_run(): void
    {
        $this->get('http://kolabing.com/blog/what-is-community-led-footfall')->assertNotFound();

        $this->seed(BlogPostSeeder::class);

        foreach ([
            'what-is-community-led-footfall',
            'how-to-get-more-footfall-without-paying-for-ads',
            'how-local-businesses-partner-with-community-groups',
            'is-influencer-marketing-worth-it-for-a-local-business',
            'how-your-community-can-get-a-free-venue-for-its-next-event',
        ] as $slug) {
            $this->get('http://kolabing.com/blog/'.$slug)
                ->assertOk()
                ->assertSee('"@type":"Article"', false);
        }

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('blog.show', 'what-is-community-led-footfall').'</loc>', false);
    }

    public function test_the_sitemap_always_lists_the_events_hub(): void
    {
        // No public event exists: the hub is still an indexable page.
        $this->get('http://kolabing.com/events')
            ->assertOk()
            ->assertDontSee('noindex', false);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('public-events').'</loc>', false);
    }

    public function test_the_sitemap_lists_the_communities_hub_once_a_city_is_published(): void
    {
        // A published topic page alone leaves the hub without a city, and the hub
        // page serves noindex in that state, so it stays out of the sitemap.
        RankingPage::query()->create([
            'city' => 'Barcelona', 'topic' => 'run-clubs', 'slug' => 'run-clubs',
            'title' => 'Run clubs in Barcelona', 'published' => true,
        ]);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertDontSee('<loc>'.route('directory.index').'</loc>', false);

        RankingPage::query()->create([
            'city' => 'Barcelona', 'topic' => null, 'slug' => 'barcelona',
            'title' => 'Best communities in Barcelona', 'published' => true,
        ]);

        $this->get('http://kolabing.com/communities')
            ->assertOk()
            ->assertDontSee('noindex', false);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('directory.index').'</loc>', false)
            ->assertSee('<loc>'.route('directory.city', 'barcelona').'</loc>', false);
    }

    public function test_the_sitemap_leaves_out_profiles_without_a_display_name(): void
    {
        $named = $this->business('Cafe Luna');
        $nameless = $this->business(null);
        $blank = $this->business('   ');

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/p/'.PublicProfileLink::slugFor($named)), false)
            ->assertDontSee(url('/p/'.PublicProfileLink::slugFor($nameless)), false)
            ->assertDontSee(url('/p/'.PublicProfileLink::slugFor($blank)), false);

        // Still reachable for whoever holds the link, but not indexable.
        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($nameless))
            ->assertOk()
            ->assertSee('Kolabing member')
            ->assertSee('noindex,follow', false);
        $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($named))
            ->assertOk()
            ->assertSee('Cafe Luna')
            ->assertDontSee('noindex', false);
    }

    public function test_a_business_name_slug_under_c_reaches_the_business_profile(): void
    {
        $business = $this->business('LabTwentyTwo Barcelona', 0);
        $profileUrl = PublicProfileLink::urlFor($business);

        // The marketing host still hands /c/ to the app host...
        $this->get('http://kolabing.com/c/labtwentytwo-barcelona')
            ->assertRedirect(rtrim((string) config('webapp.url'), '/').'/c/labtwentytwo-barcelona');

        // ...which now sends a business slug to its public profile instead of a 404.
        $this->get('http://'.config('webapp.host').'/c/labtwentytwo-barcelona')
            ->assertStatus(301)
            ->assertRedirect($profileUrl);

        // The canonical /p/ slug shape works under /c/ too.
        $this->get('http://'.config('webapp.host').'/c/'.PublicProfileLink::slugFor($business))
            ->assertRedirect($profileUrl);

        $this->get($profileUrl)->assertOk()->assertSee('LabTwentyTwo Barcelona');
    }

    public function test_a_c_slug_that_only_partly_matches_a_name_is_still_404(): void
    {
        $this->business('LabTwentyTwo Barcelona', 0);

        $this->get('http://'.config('webapp.host').'/c/labtwentytwo')->assertNotFound();
        $this->get('http://'.config('webapp.host').'/c/barcelona')->assertNotFound();
    }

    public function test_a_community_profile_name_slug_under_c_also_resolves(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id, 'name' => 'Gràcia Supper Club']);

        $this->get('http://'.config('webapp.host').'/c/'.PublicProfileLink::slugFor($profile->fresh()))
            ->assertRedirect(PublicProfileLink::urlFor($profile->fresh()));
    }
}
