<?php

declare(strict_types=1);

namespace App\Services\SalesOutreach;

use App\Enums\FileUploadType;
use App\Enums\UserType;
use App\Jobs\GenerateSalesPitchCoverImage;
use App\Jobs\WriteSalesPitch;
use App\Mail\SalesPitchMail;
use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\OpenAi\OpenAiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Drives one pitch end to end: generate → choose → illustrate → preview → send.
 *
 * The ordering rule this class exists to enforce is that **nothing is sent that a
 * maintainer has not read**. Generation writes a draft; sending is a separate,
 * explicit call on an existing draft. That mirrors the welcome email's
 * preview-then-confirm flow (BE-NF-57, "i don't want them to get an unapproved
 * email") and matters more here, because this copy is written by a model rather
 * than edited by a human.
 *
 * Delivery deliberately goes through a plain queued Mailable rather than
 * {@see \App\Services\EmailService}. That service gates on the recipient's
 * `notification_preferences`, where `marketing_tips` defaults to **false** — so
 * every pitch to a freshly listed business would be silently suppressed and nobody
 * would learn why. `AdminProfileWelcomeMail` already set this precedent for
 * maintainer-initiated outreach to accounts that have not opted into anything yet.
 */
class SalesOutreachService
{
    public function __construct(
        private readonly SalesPitchGenerator $generator,
        private readonly RevenueEstimator $revenue,
        private readonly OpenAiClient $client,
        private readonly FileUploadService $uploads,
        private readonly CoverImageBrief $coverBrief,
        private readonly ProspectIntel $intel,
    ) {}

    /**
     * Create the draft and queue the writing (BE-FX-63).
     *
     * Returns in milliseconds. The pitch itself — research plus two `gpt-5.2` calls —
     * runs on a worker, because the same work measured ~23s from a laptop and three
     * to four times that from production, which is how the form POST reached
     * Cloudflare's 100s edge timeout. The tell was two complete drafts a minute
     * apart: the server had finished the first while the maintainer was looking at a
     * 504 and clicking again.
     *
     * The revenue arithmetic stays here, synchronous, because it is instant and
     * because the maintainer's own inputs belong to the request that supplied them.
     *
     * @param  array{business_profile_id: string, community_profile_id: string, locale: string, expected_attendees?: int|null, avg_spend_cents?: int|null}  $data
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function queuePitch(array $data, ?User $creator = null): SalesOutreachDraft
    {
        $business = Profile::query()->with('businessProfile')->findOrFail($data['business_profile_id']);
        $community = Profile::query()->with('communityProfile.city')->findOrFail($data['community_profile_id']);

        $this->assertPair($business, $community);

        $locale = $this->assertLocale($data['locale']);

        if (! $this->client->isConfigured()) {
            throw new RuntimeException('OPENAI_API_KEY is not set, so no pitch can be written.');
        }

        $attendees = (int) ($data['expected_attendees'] ?? 0) > 0
            ? (int) $data['expected_attendees']
            : $this->revenue->suggestedAttendees($community);

        $avgSpend = (int) ($data['avg_spend_cents'] ?? 0) > 0
            ? (int) $data['avg_spend_cents']
            : $this->revenue->defaultAvgSpendCents();

        $draft = SalesOutreachDraft::query()->create([
            'business_profile_id' => $business->id,
            'community_profile_id' => $community->id,
            'locale' => $locale,
            'expected_attendees' => $attendees,
            'avg_spend_cents' => $avgSpend,
            'estimated_revenue_cents' => $this->revenue->estimateCents($attendees, $avgSpend),
            'status' => SalesOutreachDraft::STATUS_DRAFT,
            'generation_status' => SalesOutreachDraft::GENERATION_PENDING,
            'created_by' => $creator?->id,
        ]);

        WriteSalesPitch::dispatch($draft->id);

        return $draft;
    }

    /**
     * Do the slow half: research the business, generate the ideas, write the copy.
     *
     * Runs on a worker — see {@see WriteSalesPitch}. Failures are recorded on the
     * draft as well as thrown, because the page is polling and cannot otherwise tell
     * "slow" from "dead".
     *
     * @throws RuntimeException
     */
    public function writePitch(SalesOutreachDraft $draft): SalesOutreachDraft
    {
        $business = $draft->business;
        $community = $draft->community;

        if ($business === null || $community === null) {
            throw new RuntimeException('This draft no longer has both profiles.');
        }

        try {
            $ideas = $this->generator->generateIdeas($business, $community, $draft->locale);

            // Researched once, here, and stored — a claim made to a real business has
            // to stay explainable after their hours change and their site is redesigned.
            $intel = $this->intel->gather($business);

            $email = $this->generator->composeEmail(
                $business,
                $community,
                $ideas[0],
                $draft->expected_attendees,
                $this->money($draft->estimated_revenue_cents),
                $this->money($draft->avg_spend_cents),
                $draft->locale,
                $intel,
            );
        } catch (Throwable $e) {
            $draft->update([
                'generation_status' => SalesOutreachDraft::GENERATION_FAILED,
                'generation_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }

        $draft->update([
            'kolab_ideas' => $ideas,
            'intel' => $intel,
            'angle' => $email['angle'] ?: null,
            'selected_idea_index' => 0,
            'subject' => $email['subject'],
            'body_markdown' => $email['body_markdown'],
            'whatsapp_message' => $email['whatsapp_message'] ?: null,
            'generation_status' => SalesOutreachDraft::GENERATION_READY,
            'generation_error' => null,
        ]);

        return $draft->refresh();
    }

    /**
     * Switch the pitch to a different generated idea and rewrite the email around it.
     *
     * The copy is regenerated rather than patched: an email written for a wine
     * tasting does not become an email for a morning run club by swapping the title,
     * and shipping one that half-reads as the other is worse than the original.
     *
     * @throws RuntimeException
     */
    public function selectIdea(SalesOutreachDraft $draft, int $index): SalesOutreachDraft
    {
        $this->assertEditable($draft);

        $ideas = $draft->kolab_ideas;

        if (! isset($ideas[$index])) {
            throw new InvalidArgumentException('That idea does not exist on this draft.');
        }

        // The stored research is reused rather than re-gathered: it describes the
        // business, which has not changed because a different idea was picked, and
        // re-fetching would make switching idea cost two network round trips.
        $email = $this->generator->composeEmail(
            $draft->business,
            $draft->community,
            $ideas[$index],
            $draft->expected_attendees,
            $this->money($draft->estimated_revenue_cents),
            $this->money($draft->avg_spend_cents),
            $draft->locale,
            is_array($draft->intel) ? $draft->intel : [],
        );

        $draft->update([
            'selected_idea_index' => $index,
            'angle' => $email['angle'] ?: $draft->angle,
            'subject' => $email['subject'],
            'body_markdown' => $email['body_markdown'],
            'whatsapp_message' => $email['whatsapp_message'] ?: $draft->whatsapp_message,
            // The old cover illustrates the old idea; keeping it would attach a wine
            // cellar to a running event. Cleared, not regenerated — images cost money
            // and the maintainer may not want one at all.
            'cover_image_url' => null,
            'cover_image_status' => SalesOutreachDraft::IMAGE_IDLE,
            'cover_image_error' => null,
        ]);

        return $draft->refresh();
    }

    /**
     * Queue the cover for the selected idea (BE-FX-61).
     *
     * Returns immediately. Drawing the image inside the web request produced a
     * Cloudflare 504 in production — generation alone measured ~25s and the upload
     * follows it, which is more than any gateway will hold a connection open for.
     * Raising a timeout would only have moved the failure.
     *
     * The validation that can be done cheaply is done here, synchronously, so an
     * obviously impossible request fails on the screen the maintainer is looking at
     * rather than silently in a worker.
     *
     * @throws RuntimeException
     */
    public function queueCoverImage(SalesOutreachDraft $draft): SalesOutreachDraft
    {
        $this->assertEditable($draft);

        $idea = $draft->selectedIdea();

        if ($idea === null || blank($idea['cover_image_prompt'] ?? null)) {
            throw new RuntimeException('This draft has no image brief to draw from.');
        }

        if (! $this->client->isConfigured()) {
            throw new RuntimeException('OPENAI_API_KEY is not set, so no image can be generated.');
        }

        // Marked pending BEFORE dispatch: a worker fast enough to finish first
        // would otherwise have its 'ready' overwritten back to 'pending'.
        $draft->update([
            'cover_image_status' => SalesOutreachDraft::IMAGE_PENDING,
            'cover_image_error' => null,
        ]);

        GenerateSalesPitchCoverImage::dispatch($draft->id);

        return $draft->refresh();
    }

    /**
     * Actually draw it. Runs on a worker — see {@see GenerateSalesPitchCoverImage}.
     *
     * Failures are recorded on the draft as well as thrown, because the thrown
     * exception reaches a log the maintainer is not reading; the draft is the thing
     * they are looking at.
     *
     * @throws RuntimeException
     */
    public function drawCoverImage(SalesOutreachDraft $draft): SalesOutreachDraft
    {
        $idea = $draft->selectedIdea();

        if ($idea === null || blank($idea['cover_image_prompt'] ?? null)) {
            throw new RuntimeException('This draft has no image brief to draw from.');
        }

        try {
            // The brief is built from the pair's stored data and grounded in their
            // own photographs — see CoverImageBrief. The model's one-line scene is
            // the opening direction, not the whole instruction.
            $brief = $this->coverBrief->for($draft);

            $base64 = $this->client->imageWithReferences($brief['prompt'], $brief['references']);

            $url = $this->uploads->uploadFromBase64(
                $base64,
                FileUploadType::CoverPhoto,
                $draft->id,
            );
        } catch (Throwable $e) {
            $draft->update([
                'cover_image_status' => SalesOutreachDraft::IMAGE_FAILED,
                'cover_image_error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }

        $draft->update([
            'cover_image_url' => $url,
            'cover_image_status' => SalesOutreachDraft::IMAGE_READY,
            'cover_image_error' => null,
        ]);

        return $draft->refresh();
    }

    /**
     * Save a maintainer's edits to the copy.
     *
     * Editing is not a nicety here: a model wrote this, and the person whose name is
     * effectively on the email has to be able to fix it before it leaves.
     *
     * @param  array{subject: string, body_markdown: string}  $data
     */
    public function updateCopy(SalesOutreachDraft $draft, array $data): SalesOutreachDraft
    {
        $this->assertEditable($draft);

        $draft->update([
            'subject' => $data['subject'],
            'body_markdown' => $data['body_markdown'],
        ]);

        return $draft->refresh();
    }

    public function previewHtml(SalesOutreachDraft $draft): string
    {
        return $this->mailable($draft)->render();
    }

    /**
     * Send it. One-way, hence the guard: a second click must not mail the owner twice.
     *
     * @throws RuntimeException
     */
    public function send(SalesOutreachDraft $draft): SalesOutreachDraft
    {
        if ($draft->isSent()) {
            throw new RuntimeException('This pitch has already been sent.');
        }

        $recipient = $draft->business;

        if (blank($recipient?->email)) {
            throw new RuntimeException('That business has no email address on file.');
        }

        Mail::to($recipient->email)->queue($this->mailable($draft));

        $draft->update([
            'status' => SalesOutreachDraft::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Log::info('Sales pitch queued', [
            'draft_id' => $draft->id,
            'business_profile_id' => $draft->business_profile_id,
            'community_profile_id' => $draft->community_profile_id,
            'locale' => $draft->locale,
        ]);

        return $draft->refresh();
    }

    private function mailable(SalesOutreachDraft $draft): SalesPitchMail
    {
        return new SalesPitchMail($draft->loadMissing([
            'business.businessProfile',
            'community.communityProfile',
        ]));
    }

    /**
     * Both halves must be what they claim. Pitching a community to a community, or a
     * business to a business, produces copy that reads as nonsense to the recipient.
     */
    private function assertPair(Profile $business, Profile $community): void
    {
        if (! $business->isBusiness()) {
            throw new InvalidArgumentException('The recipient must be a business profile.');
        }

        if ($community->user_type !== UserType::Community) {
            throw new InvalidArgumentException('The pitched partner must be a community profile.');
        }
    }

    private function assertLocale(string $locale): string
    {
        $allowed = (array) config('sales_outreach.locales');

        if (! in_array($locale, $allowed, true)) {
            throw new InvalidArgumentException('That language is not available for outreach.');
        }

        return $locale;
    }

    private function assertEditable(SalesOutreachDraft $draft): void
    {
        if ($draft->isSent()) {
            throw new RuntimeException('This pitch has already been sent and can no longer be changed.');
        }

        // Nothing to edit, illustrate or re-idea until the writing finishes —
        // and a cover drawn now would illustrate an idea that does not exist yet.
        if (! $draft->isWritten()) {
            throw new RuntimeException('This pitch is still being written. Wait for it to finish.');
        }
    }

    /**
     * Cents to something a person reads. Kept here rather than in the prompt so the
     * model never has to format — or worse, re-derive — a monetary figure.
     */
    private function money(int $cents): string
    {
        $currency = (string) config('sales_outreach.revenue.currency');

        return $currency.' '.number_format($cents / 100, 0, '.', ',');
    }
}
