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

    public function test_show_renders_the_faq_section_and_faqpage_schema_from_the_same_data(): void
    {
        BlogPost::factory()->create([
            'slug' => 'faq-post',
            'published_at' => now()->subDay(),
            'faq' => [
                ['question' => 'Do events beat ads?', 'answer' => 'For repeat footfall, yes.'],
                ['question' => 'Half-filled row', 'answer' => ''],
            ],
        ]);

        $this->get('/blog/faq-post')
            ->assertOk()
            ->assertSee('<h2 id="faq-heading">FAQ</h2>', false)
            ->assertSee('<h3>Do events beat ads?</h3>', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Do events beat ads?"', false)
            ->assertSee('"text":"For repeat footfall, yes."', false)
            ->assertDontSee('Half-filled row');
    }

    public function test_show_emits_no_faq_when_the_post_has_none(): void
    {
        BlogPost::factory()->create(['slug' => 'no-faq', 'published_at' => now()->subDay(), 'faq' => null]);

        $this->get('/blog/no-faq')
            ->assertOk()
            ->assertDontSee('faq-heading', false)
            ->assertDontSee('FAQPage', false);
    }

    public function test_faq_text_cannot_close_the_json_ld_script_block(): void
    {
        BlogPost::factory()->create([
            'slug' => 'hostile-faq',
            'published_at' => now()->subDay(),
            'faq' => [['question' => 'Q', 'answer' => '</script><script>alert(1)</script>']],
        ]);

        $this->get('/blog/hostile-faq')
            ->assertOk()
            ->assertDontSee('</script><script>alert(1)', false)
            ->assertSee('\u003C/script\u003E', false);
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
