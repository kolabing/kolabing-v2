<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Profile;
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
 */
class AdminProfileWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Profile $profile,
        public readonly string $resetToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Kolabing listing is live — set your password'),
        );
    }

    public function content(): Content
    {
        $this->profile->loadMissing(['businessProfile', 'communityProfile']);

        $name = $this->profile->businessProfile?->name
            ?? $this->profile->communityProfile?->name
            ?? $this->profile->email;

        return new Content(
            markdown: 'mail.admin-profile-welcome',
            with: [
                'name' => $name,
                'isBusiness' => $this->profile->isBusiness(),
                'profileUrl' => PublicProfileLink::urlFor($this->profile),
                'createPasswordUrl' => config('app.url').'/reset-password?token='.$this->resetToken
                    .'&email='.urlencode($this->profile->email),
            ],
        );
    }
}
