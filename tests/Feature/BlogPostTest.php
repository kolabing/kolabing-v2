<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlogPostTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_lists_published_and_hides_drafts_and_future(): void
    {
        BlogPost::factory()->create(['title' => 'Foot traffic without ads', 'published_at' => now()->subDay()]);
        BlogPost::factory()->draft()->create(['title' => 'Secret draft']);
        BlogPost::factory()->scheduled()->create(['title' => 'Coming soon post']);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Foot traffic without ads')
            ->assertDontSee('Secret draft')
            ->assertDontSee('Coming soon post');
    }

    public function test_show_renders_published_post_with_article_schema(): void
    {
        BlogPost::factory()->create([
            'slug' => 'foot-traffic-without-paid-ads',
            'title' => 'How to get foot traffic without paid ads',
            'body' => '<h2>Answer</h2><p>Partner with a local community.</p>',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/blog/foot-traffic-without-paid-ads')
            ->assertOk()
            ->assertSee('How to get foot traffic without paid ads')
            ->assertSee('Partner with a local community.', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee('"@type":"Organization"', false);
    }

    public function test_show_404s_for_a_draft(): void
    {
        BlogPost::factory()->draft()->create(['slug' => 'hidden-draft']);

        $this->get('/blog/hidden-draft')->assertNotFound();
    }

    public function test_show_404s_for_a_scheduled_future_post(): void
    {
        BlogPost::factory()->scheduled()->create(['slug' => 'future-post']);

        $this->get('/blog/future-post')->assertNotFound();
    }

    public function test_sitemap_includes_published_post_and_blog_index_but_not_drafts(): void
    {
        BlogPost::factory()->create(['slug' => 'live-post', 'published_at' => now()->subDay()]);
        BlogPost::factory()->draft()->create(['slug' => 'draft-post']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/blog'), false)
            ->assertSee(url('/blog/live-post'), false)
            ->assertDontSee(url('/blog/draft-post'), false);
    }

    public function test_llms_txt_lists_published_posts(): void
    {
        BlogPost::factory()->create(['slug' => 'llms-post', 'title' => 'Community event marketing', 'published_at' => now()->subDay()]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('Community event marketing')
            ->assertSee(url('/blog/llms-post'), false);
    }

    public function test_homepage_emits_canonical_and_faq_schema(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }
}
