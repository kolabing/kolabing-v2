<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BlogAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_index_rejects_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->get('/admin/blog')
            ->assertForbidden();
    }

    public function test_index_redirects_a_guest_to_login(): void
    {
        $this->get('/admin/blog')->assertRedirect('/admin/login');
    }

    public function test_maintainer_can_create_a_post(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post('/admin/blog', [
                'title' => 'How to get foot traffic without paid ads',
                'slug' => 'foot-traffic-without-paid-ads',
                'description' => 'A local-business playbook.',
                'body' => '<p>Partner with a nearby community.</p>',
                'author_name' => 'Clark',
                'locale' => 'en',
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('admin.blog.index'));

        $this->assertDatabaseHas('blog_posts', [
            'slug' => 'foot-traffic-without-paid-ads',
            'author_name' => 'Clark',
        ]);
    }

    public function test_slug_must_be_unique(): void
    {
        BlogPost::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post('/admin/blog', [
                'title' => 'Dup', 'slug' => 'taken', 'description' => 'x', 'body' => '<p>x</p>',
                'author_name' => 'Clark', 'locale' => 'en',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_maintainer_can_update_and_delete(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Old']);

        $this->actingAs($this->maintainer(), 'admin')
            ->put("/admin/blog/{$post->slug}", [
                'title' => 'New title', 'slug' => $post->slug, 'description' => 'y',
                'body' => '<p>y</p>', 'author_name' => 'Clark', 'locale' => 'en',
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('admin.blog.index'));
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'title' => 'New title']);

        $this->actingAs($this->maintainer(), 'admin')
            ->delete("/admin/blog/{$post->slug}")
            ->assertRedirect(route('admin.blog.index'));
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'FAQ post', 'slug' => 'faq-post', 'description' => 'x',
            'body' => '<p>x</p>', 'author_name' => 'Clark', 'locale' => 'en',
            'published_at' => now()->toDateTimeString(),
        ], $overrides);
    }

    public function test_maintainer_can_save_faq_rows_and_blank_rows_are_dropped(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post('/admin/blog', $this->payload(['faq' => [
                ['question' => '  Do events beat ads? ', 'answer' => 'For repeat footfall, yes.'],
                ['question' => '', 'answer' => ''],
            ]]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.blog.index'));

        $this->assertSame(
            [['question' => 'Do events beat ads?', 'answer' => 'For repeat footfall, yes.']],
            BlogPost::query()->where('slug', 'faq-post')->firstOrFail()->faq,
        );
    }

    public function test_removing_every_faq_row_clears_the_faq(): void
    {
        $post = BlogPost::factory()->create([
            'slug' => 'faq-post',
            'faq' => [['question' => 'Q', 'answer' => 'A']],
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->put("/admin/blog/{$post->slug}", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull($post->fresh()->faq);
    }

    public function test_a_faq_row_needs_both_a_question_and_an_answer(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post('/admin/blog', $this->payload(['faq' => [['question' => 'Only a question', 'answer' => '']]]))
            ->assertSessionHasErrors('faq.0.answer');

        $this->assertDatabaseMissing('blog_posts', ['slug' => 'faq-post']);
    }

    public function test_edit_form_shows_existing_faq_rows(): void
    {
        $post = BlogPost::factory()->create(['faq' => [['question' => 'Stored question?', 'answer' => 'Stored answer.']]]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get("/admin/blog/{$post->slug}/edit")
            ->assertOk()
            ->assertSee('value="Stored question?"', false)
            ->assertSee('Stored answer.');
    }
}
