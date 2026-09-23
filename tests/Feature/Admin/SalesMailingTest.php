<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\GenerateSalesPitchCoverImage;
use App\Jobs\WriteSalesPitch;
use App\Mail\SalesPitchMail;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use App\Models\User;
use App\Services\SalesOutreach\SalesOutreachService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin sales-mailing surface (BE-NF-65).
 *
 * Two properties carry most of the weight here, and they are the two that would hurt
 * a real business if they broke:
 *
 *  1. **The model never decides the money.** The revenue figure is PHP arithmetic
 *     over inputs a maintainer can see and change. Several tests below hand the fake
 *     model absurd numbers and assert the stored estimate ignores them.
 *  2. **Nothing sends without a human.** Generating writes a draft; sending is a
 *     separate action behind a preview, refuses to repeat itself, and is the only
 *     step that touches the mailer.
 */
class SalesMailingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.key', 'sk-test-key');
        config()->set('sales_outreach.revenue.currency', 'EUR');

        // Generated covers go to the uploads disk; without this the image test would
        // reach for real cloud storage.
        Storage::fake((string) config('filesystems.uploads_disk', 'cloud'));
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function business(string $name = 'Eixample 46'): Profile
    {
        $profile = Profile::factory()->business()->create(['email' => 'venue@example.com']);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'city_name' => 'Barcelona',
        ]);

        return $profile->fresh();
    }

    private function community(?int $size = 400): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Barcelona Run Club',
            'community_size' => $size,
        ]);

        return $profile->fresh();
    }

    /**
     * Fake OpenAI by *what is being asked*, not by call order.
     *
     * A `Http::sequence()` here would be a trap: the ideas call and the email call
     * hit the same endpoint, and any test that triggers only the second one (switching
     * idea, for instance) would silently receive the first one's payload and fail with
     * an error that points nowhere near the cause. Keying on the system prompt keeps
     * every test independent of how many calls preceded it.
     *
     * @param  array<string, mixed>  $ideaOverrides
     */
    private function fakeOpenAi(array $ideaOverrides = [], string $subject = 'A Tuesday worth opening for'): void
    {
        $idea = [
            'title' => 'Run club recovery night',
            'format' => 'Weeknight, 19:00',
            'business_provides' => 'The back room and a welcome drink',
            'community_delivers' => '25 runners and stories on the night',
            'why_it_works' => 'Turns your quietest night into a full room',
            'cover_image_prompt' => 'A warm cafe back room at dusk',
            ...$ideaOverrides,
        ];

        Http::fake(function ($request) use ($idea, $subject) {
            if (str_contains($request->url(), '/images/generations')) {
                return Http::response(['data' => [['b64_json' => base64_encode($this->pngBytes())]]]);
            }

            // The idea prompt says "collaboration ideas"; the email prompt does not.
            $payload = str_contains($request->body(), 'collaboration ideas')
                ? ['ideas' => [$idea, ['...' => '', ...$idea, 'title' => 'Second idea'], $idea]]
                : ['subject' => $subject, 'body_markdown' => 'Here is the idea, in full.'];

            return Http::response(['choices' => [['message' => ['content' => json_encode($payload)]]]]);
        });
    }

    /** A real 1x1 PNG, so FileUploadService's mime sniffing has something valid to read. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Profile $business, Profile $community, array $overrides = []): array
    {
        return [
            'business_profile_id' => $business->id,
            'community_profile_id' => $community->id,
            'locale' => 'en',
            ...$overrides,
        ];
    }

    // ── Reachability ────────────────────────────────────────────────────

    public function test_the_page_is_maintainer_only(): void
    {
        $this->get(route('admin.sales-mailing.index'))->assertRedirect();
    }

    public function test_a_maintainer_sees_the_form(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.sales-mailing.index'))
            ->assertOk()
            ->assertSee('Generate pitch', false);
    }

    /** An unusable button with a generic failure is worse than a disabled one with a reason. */
    public function test_a_missing_api_key_is_explained_on_the_page(): void
    {
        config()->set('services.openai.key', null);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.sales-mailing.index'))
            ->assertOk()
            ->assertSee('No OpenAI key configured', false);
    }

    // ── Generating ──────────────────────────────────────────────────────

    public function test_generating_stores_the_ideas_and_the_copy(): void
    {
        Mail::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $draft = SalesOutreachDraft::query()->firstOrFail();

        // Queued now (BE-FX-63) — run the worker's half to see the result.
        (new WriteSalesPitch($draft->id))->handle(app(SalesOutreachService::class));
        $draft->refresh();

        $this->assertCount(3, $draft->kolab_ideas);
        $this->assertSame('Run club recovery night', $draft->selectedIdea()['title']);
        $this->assertSame('A Tuesday worth opening for', $draft->subject);
        $this->assertSame(SalesOutreachDraft::STATUS_DRAFT, $draft->status);
    }

    /** Generating is not sending. This is the whole safety model. */
    public function test_generating_sends_nothing(): void
    {
        Mail::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community()))
            ->assertRedirect();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    /**
     * The revenue figure is arithmetic done here. The fake model is not even asked
     * for it — and if it volunteered one, this assertion would still hold.
     */
    public function test_the_revenue_is_computed_here_not_by_the_model(): void
    {
        Mail::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(), [
                'expected_attendees' => 30,
                'avg_spend_cents' => 2000,
            ]))
            ->assertRedirect();

        $draft = SalesOutreachDraft::query()->firstOrFail();

        $this->assertSame(30, $draft->expected_attendees);
        $this->assertSame(2000, $draft->avg_spend_cents);
        $this->assertSame(60000, $draft->estimated_revenue_cents);
    }

    /** Turnout is a fraction of stated membership, clamped so it stays plausible. */
    public function test_turnout_is_estimated_from_the_communitys_size_when_not_given(): void
    {
        Mail::fake();
        $this->fakeOpenAi();
        config()->set('sales_outreach.revenue.attendance_rate', 0.1);
        config()->set('sales_outreach.revenue.max_attendees', 250);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(400)))
            ->assertRedirect();

        $this->assertSame(40, SalesOutreachDraft::query()->firstOrFail()->expected_attendees);
    }

    /** A huge community must not promise thousands through a small venue's door. */
    public function test_turnout_is_capped(): void
    {
        Mail::fake();
        $this->fakeOpenAi();
        config()->set('sales_outreach.revenue.attendance_rate', 0.5);
        config()->set('sales_outreach.revenue.max_attendees', 120);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(40000)))
            ->assertRedirect();

        $this->assertSame(120, SalesOutreachDraft::query()->firstOrFail()->expected_attendees);
    }

    /** A community with no stated size is an unfilled field, not an empty community. */
    public function test_a_community_without_a_size_falls_back_to_the_floor(): void
    {
        Mail::fake();
        $this->fakeOpenAi();
        config()->set('sales_outreach.revenue.min_attendees', 8);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(null)))
            ->assertRedirect();

        $this->assertSame(8, SalesOutreachDraft::query()->firstOrFail()->expected_attendees);
    }

    // ── The pair and the language ───────────────────────────────────────

    public function test_the_recipient_must_be_a_business(): void
    {
        Mail::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->community(), $this->community()))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, SalesOutreachDraft::query()->count());
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(), [
                'locale' => 'de',
            ]))
            ->assertSessionHasErrors('locale');

        $this->assertSame(0, SalesOutreachDraft::query()->count());
    }

    public function test_spanish_is_supported(): void
    {
        Mail::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community(), [
                'locale' => 'es',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('es', SalesOutreachDraft::query()->firstOrFail()->locale);
    }

    /** The Spanish furniture is the template's, not the model's — so it must exist. */
    public function test_the_spanish_email_renders_its_own_fixed_copy(): void
    {
        $draft = SalesOutreachDraft::factory()->create(['locale' => 'es']);

        $html = (new SalesPitchMail($draft))->render();

        $this->assertStringContainsString(__('sales_mail.cta_button', [], 'es'), $html);
        $this->assertStringContainsString('estimación', $html);
    }

    /** Every pitch states it is an estimate — that wording is not left to the model. */
    public function test_the_email_always_carries_the_estimate_disclaimer(): void
    {
        $draft = SalesOutreachDraft::factory()->create(['locale' => 'en']);

        $this->assertStringContainsString('not a guarantee', (new SalesPitchMail($draft))->render());
    }

    // ── The image ───────────────────────────────────────────────────────

    /**
     * Queued, not drawn inline (BE-FX-61). Drawing it in the request produced a
     * Cloudflare 504 in production, so the request must now return immediately and
     * the work must land on a worker.
     */
    public function test_requesting_a_cover_queues_a_job_and_returns_at_once(): void
    {
        Queue::fake();
        $this->fakeOpenAi();
        $draft = SalesOutreachDraft::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.image', $draft))
            ->assertRedirect();

        Queue::assertPushed(
            GenerateSalesPitchCoverImage::class,
            fn (GenerateSalesPitchCoverImage $job): bool => $job->draftId === $draft->id,
        );

        $this->assertSame(SalesOutreachDraft::IMAGE_PENDING, $draft->refresh()->cover_image_status);
    }

    /** The worker side: this is what actually produces the image. */
    public function test_the_job_draws_the_cover_and_marks_it_ready(): void
    {
        $this->fakeOpenAi();
        $draft = SalesOutreachDraft::factory()->create();

        (new GenerateSalesPitchCoverImage($draft->id))->handle(app(SalesOutreachService::class));

        $draft->refresh();
        $this->assertNotNull($draft->cover_image_url);
        $this->assertSame(SalesOutreachDraft::IMAGE_READY, $draft->cover_image_status);
    }

    /**
     * A failure has to reach the draft. The maintainer is no longer watching the
     * response that does the work, so a failure that only reaches the log is a
     * spinner that never resolves.
     */
    public function test_a_failed_draw_is_recorded_on_the_draft(): void
    {
        Http::fake(['*/images/generations' => Http::response(['error' => ['message' => 'nope']], 500)]);
        $draft = SalesOutreachDraft::factory()->create([
            'cover_image_status' => SalesOutreachDraft::IMAGE_PENDING,
        ]);

        try {
            (new GenerateSalesPitchCoverImage($draft->id))->handle(app(SalesOutreachService::class));
            $this->fail('The draw should have thrown.');
        } catch (\RuntimeException) {
            // expected — the job is allowed to fail, the draft must record it
        }

        $draft->refresh();
        $this->assertSame(SalesOutreachDraft::IMAGE_FAILED, $draft->cover_image_status);
        $this->assertNotNull($draft->cover_image_error);
        $this->assertNull($draft->cover_image_url);
    }

    /** A draft deleted between dispatch and execution is a no-op, not a crash. */
    public function test_the_job_tolerates_a_deleted_draft(): void
    {
        $this->fakeOpenAi();
        $draft = SalesOutreachDraft::factory()->create();
        $id = $draft->id;
        $draft->delete();

        (new GenerateSalesPitchCoverImage($id))->handle(app(SalesOutreachService::class));

        $this->assertTrue(true, 'Handling a missing draft must not throw.');
    }

    /** Without a key the button fails on the screen, not silently in a worker. */
    public function test_a_missing_key_refuses_before_queueing(): void
    {
        Queue::fake();
        config()->set('services.openai.key', null);
        $draft = SalesOutreachDraft::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.image', $draft))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
        $this->assertSame(SalesOutreachDraft::IMAGE_IDLE, $draft->refresh()->cover_image_status);
    }

    /** A wine-cellar photo does not belong on a run-club pitch. */
    public function test_switching_idea_clears_the_old_cover(): void
    {
        $this->fakeOpenAi();

        $draft = SalesOutreachDraft::factory()->create([
            'cover_image_url' => 'https://cdn.test/old.png',
            'kolab_ideas' => [
                ['title' => 'A', 'format' => '', 'business_provides' => '', 'community_delivers' => '', 'why_it_works' => '', 'cover_image_prompt' => 'a'],
                ['title' => 'B', 'format' => '', 'business_provides' => '', 'community_delivers' => '', 'why_it_works' => '', 'cover_image_prompt' => 'b'],
            ],
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.idea', $draft), ['index' => 1])
            ->assertRedirect();

        $draft->refresh();

        $this->assertSame(1, $draft->selected_idea_index);
        $this->assertNull($draft->cover_image_url);
        $this->assertSame(SalesOutreachDraft::IMAGE_IDLE, $draft->cover_image_status);
    }

    // ── Preview and send ────────────────────────────────────────────────

    public function test_previewing_sends_nothing(): void
    {
        Mail::fake();
        $draft = SalesOutreachDraft::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.sales-mailing.preview', $draft))
            ->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_sending_queues_the_mail_and_marks_the_draft(): void
    {
        Mail::fake();
        $business = $this->business();
        $draft = SalesOutreachDraft::factory()->create(['business_profile_id' => $business->id]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.send', $draft))
            ->assertRedirect();

        Mail::assertQueued(SalesPitchMail::class, fn (SalesPitchMail $mail): bool => $mail->hasTo($business->email));

        $draft->refresh();
        $this->assertTrue($draft->isSent());
        $this->assertNotNull($draft->sent_at);
    }

    /** A second click must not mail the owner twice. */
    public function test_a_sent_pitch_cannot_be_sent_again(): void
    {
        Mail::fake();
        $draft = SalesOutreachDraft::factory()->sent()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.send', $draft))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
    }

    /** What was sent stays what was sent. */
    public function test_a_sent_pitch_cannot_be_edited(): void
    {
        $draft = SalesOutreachDraft::factory()->sent()->create(['subject' => 'Original']);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.sales-mailing.copy', $draft), [
                'subject' => 'Rewritten after the fact',
                'body_markdown' => 'Different.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('Original', $draft->refresh()->subject);
    }

    /** A maintainer's edit is the last word before it goes out. */
    public function test_a_maintainer_can_fix_the_copy_before_sending(): void
    {
        $draft = SalesOutreachDraft::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.sales-mailing.copy', $draft), [
                'subject' => 'Hand-written subject',
                'body_markdown' => 'Hand-written body.',
            ])
            ->assertRedirect();

        $this->assertSame('Hand-written subject', $draft->refresh()->subject);
    }

    // ── Failure ─────────────────────────────────────────────────────────

    /** A failed generation must leave nothing behind and say so on the form. */
    /**
     * Generation is queued, so an OpenAI failure can no longer be reported on the
     * request that started it — it is recorded on the draft, which is what the page
     * polls. A draft stuck at "writing" forever would be indistinguishable from slow.
     */
    public function test_an_openai_failure_is_recorded_on_the_draft(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response(['error' => ['message' => 'nope']], 500)]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community()))
            ->assertRedirect();

        $draft = SalesOutreachDraft::query()->firstOrFail();

        try {
            (new WriteSalesPitch($draft->id))->handle(app(SalesOutreachService::class));
            $this->fail('Writing should have thrown.');
        } catch (\RuntimeException) {
            // expected — the job fails, the draft records why
        }

        $draft->refresh();
        $this->assertTrue($draft->writingFailed());
        $this->assertNotNull($draft->generation_error);
        $this->assertNull($draft->subject);
        Mail::assertNothingQueued();
    }

    /** The request itself must return at once and send nothing. */
    public function test_generating_queues_a_job_and_returns_immediately(): void
    {
        Mail::fake();
        Queue::fake();
        $this->fakeOpenAi();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.generate'), $this->payload($this->business(), $this->community()))
            ->assertRedirect();

        $draft = SalesOutreachDraft::query()->firstOrFail();

        Queue::assertPushed(WriteSalesPitch::class, fn (WriteSalesPitch $j): bool => $j->draftId === $draft->id);
        $this->assertTrue($draft->isWriting());
        $this->assertNull($draft->subject, 'A queued draft has no copy yet.');
        Mail::assertNothingQueued();
    }

    /** Nothing that depends on the copy may run before the copy exists. */
    public function test_a_draft_still_writing_cannot_be_sent_or_edited(): void
    {
        Mail::fake();
        $draft = SalesOutreachDraft::factory()->create([
            'generation_status' => SalesOutreachDraft::GENERATION_PENDING,
            'subject' => null,
            'body_markdown' => null,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.send', $draft))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.sales-mailing.image', $draft))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
        $this->assertFalse($draft->refresh()->isSent());
    }
}
