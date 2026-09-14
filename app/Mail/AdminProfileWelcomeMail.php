<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AdminWelcomeEmailTemplate;
use App\Models\Profile;
use App\Services\WelcomeEmailTemplateRenderer;
use App\Support\PublicProfileLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a maintainer quick-adds a listing-first business/community: the profile
 * already exists and is listed, this email is the owner's first contact with it — set a
 * password (reusing the standard reset-password flow as the create-password link, same
 * as AppServiceProvider::configurePasswordReset) and see what's live.
 *
 * Content is admin-editable per locale (AdminWelcomeEmailTemplate, /admin/email-templates)
 * rather than hardcoded Blade — Daniel 2026-09-14: "make the template editable in admin
 * dashboard in all languages (add/edit/remove languages)". The two action buttons and
 * their URLs (including the one-time reset token) stay fixed in this class, never part
 * of the editable markdown — see WelcomeEmailTemplateRenderer.
 */
class AdminProfileWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Profile $profile,
        public readonly string $resetToken,
        public readonly AdminWelcomeEmailTemplate $template,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = $this->renderedTemplate();

        return new Envelope(subject: $rendered['subject']);
    }

    public function content(): Content
    {
        $rendered = $this->renderedTemplate();

        return new Content(
            markdown: 'mail.admin-profile-welcome',
            with: [
                'introMarkdown' => $rendered['intro'],
                'nextStepsMarkdown' => $rendered['next_steps'],
                'footerMarkdown' => $rendered['footer'],
                'profileUrl' => PublicProfileLink::urlFor($this->profile),
                'createPasswordUrl' => config('app.url').'/reset-password?token='.$this->resetToken
                    .'&email='.urlencode($this->profile->email),
            ],
        );
    }

    /**
     * @return array{subject: string, intro: string, next_steps: string, footer: string}
     */
    private function renderedTemplate(): array
    {
        $this->profile->loadMissing(['businessProfile', 'communityProfile']);

        $name = $this->profile->businessProfile?->name
            ?? $this->profile->communityProfile?->name
            ?? $this->profile->email;

        $isBusiness = $this->profile->isBusiness();
        $who = $this->whoWordFor($this->template->locale, $isBusiness);

        return app(WelcomeEmailTemplateRenderer::class)->render($this->template, [
            'name' => $name,
            'who' => $who,
        ]);
    }

    /**
     * "communities"/"businesses" localized per the counterpart the recipient is being
     * found by (a business is found BY communities, and vice versa) — not itself an
     * admin-editable string, since it has to agree grammatically with the surrounding
     * sentence in every locale and a typo here would read as broken, not just off-brand.
     */
    private function whoWordFor(string $locale, bool $isBusiness): string
    {
        $words = [
            'en' => ['business' => 'communities', 'community' => 'businesses'],
            'es' => ['business' => 'las comunidades', 'community' => 'los negocios'],
            'ca' => ['business' => 'les comunitats', 'community' => 'els negocis'],
        ];

        $key = $isBusiness ? 'business' : 'community';

        return $words[$locale][$key] ?? $words['en'][$key];
    }
}
