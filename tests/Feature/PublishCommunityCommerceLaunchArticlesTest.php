<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The launch-articles migration runs once on deploy. The suite's own migrate
 * run already inserted the three posts, so each test re-runs the same class
 * against a state it builds explicitly.
 */
class PublishCommunityCommerceLaunchArticlesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SLUGS = [
        'run-community-events-instead-of-buying-ads',
        'how-to-pick-which-community-to-partner-with',
        'how-to-turn-a-community-event-into-repeat-customers',
    ];

    private function migration(): object
    {
        return include database_path('migrations/2026_09_16_090500_publish_community_commerce_launch_articles.php');
    }

    public function test_it_publishes_the_three_articles_with_faq_kept_out_of_the_body(): void
    {
        BlogPost::query()->delete();

        $this->migration()->up();

        $posts = BlogPost::query()->published()->orderByDesc('published_at')->get();
        $this->assertSame(self::SLUGS, $posts->pluck('slug')->all());
        foreach ($posts as $post) {
            $this->assertNotEmpty($post->faqPairs());
            $this->assertStringNotContainsString('>FAQ<', $post->body);
            $this->assertStringContainsString('<h2>', $post->body);
        }
    }

    public function test_it_never_overwrites_a_post_that_already_uses_the_slug(): void
    {
        BlogPost::query()->delete();
        BlogPost::factory()->create(['slug' => self::SLUGS[0], 'title' => 'Hand-written by a maintainer']);

        $this->migration()->up();

        $this->assertSame('Hand-written by a maintainer', BlogPost::query()->where('slug', self::SLUGS[0])->value('title'));
        $this->assertSame(3, BlogPost::query()->count());
    }

    public function test_rollback_keeps_a_post_the_migration_did_not_write(): void
    {
        BlogPost::query()->delete();
        BlogPost::factory()->create(['slug' => self::SLUGS[0], 'title' => 'Hand-written by a maintainer']);
        $migration = $this->migration();
        $migration->up();

        $migration->down();

        $this->assertSame([self::SLUGS[0]], BlogPost::query()->pluck('slug')->all());
    }
}
