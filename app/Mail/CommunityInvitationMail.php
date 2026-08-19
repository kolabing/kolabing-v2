<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CommunityInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You have been invited to join <community>". Queued so a slow SMTP never
 * blocks the leader's invite request.
 */
class CommunityInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly CommunityInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $this->invitation->loadMissing('community');

        return new Envelope(
            subject: __('You have been invited to join :community', [
                'community' => $this->invitation->community?->name ?? __('a Kolabing community'),
            ]),
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['community', 'invitedBy']);

        $community = $this->invitation->community;

        return new Content(
            markdown: 'mail.community-invitation',
            with: [
                'communityName' => $community?->name ?? __('a Kolabing community'),
                'inviterName' => $this->invitation->invitedBy?->name,
                // The /c/{slug} landing page resolves the slug and the ?i=
                // invitation token together.
                'joinUrl' => rtrim((string) config('communities.invite_base_url'), '/')
                    .'/'.($community?->slug ?? '').'?i='.$this->invitation->token,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
