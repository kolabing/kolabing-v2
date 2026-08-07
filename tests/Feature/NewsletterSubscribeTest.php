<?php

namespace Tests\Feature;

use App\Enums\NewsletterAudience;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NewsletterSubscribeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_visitor_can_join_the_mailing_list(): void
    {
        $response = $this->postJson('/newsletter', [
            'email' => 'hello@example.com',
            'audience' => 'business',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'hello@example.com',
            'audience' => NewsletterAudience::Business->value,
            'source' => 'landing_popup',
        ]);
    }

    public function test_audience_is_optional(): void
    {
        $this->postJson('/newsletter', ['email' => 'noaud@example.com'])
            ->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'noaud@example.com',
            'audience' => null,
        ]);
    }

    public function test_the_email_is_stored_lowercased_and_trimmed(): void
    {
        $this->postJson('/newsletter', ['email' => '  MixedCase@Example.COM ']);

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'mixedcase@example.com',
        ]);
    }

    public function test_a_repeat_signup_is_idempotent(): void
    {
        $this->postJson('/newsletter', ['email' => 'dup@example.com', 'audience' => 'community'])->assertOk();
        $this->postJson('/newsletter', ['email' => 'dup@example.com', 'audience' => 'business'])->assertOk();

        $this->assertSame(1, NewsletterSubscriber::query()->where('email', 'dup@example.com')->count());
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'dup@example.com',
            'audience' => NewsletterAudience::Business->value,
        ]);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->postJson('/newsletter', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_an_invalid_audience_is_rejected(): void
    {
        $this->postJson('/newsletter', ['email' => 'ok@example.com', 'audience' => 'martian'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audience');
    }

    public function test_the_honeypot_blocks_bots(): void
    {
        $this->postJson('/newsletter', [
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertStatus(422)->assertJsonValidationErrors('website');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }
}
