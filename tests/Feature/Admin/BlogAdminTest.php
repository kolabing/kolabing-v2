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
}
