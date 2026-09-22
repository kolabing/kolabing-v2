<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SalesOutreachDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The outreach pitch itself (BE-NF-65).
 *
 * Subject and body come from the draft — generated, then reviewed and possibly
 * edited by a maintainer. Everything structural does not: the CTA, its URL, the
 * greeting, the sign-off and the estimate disclaimer are fixed in the template and
 * localised through `lang/{en,es}/sales_mail.php`.
 *
 * That split is the same security boundary `AdminProfileWelcomeMail` draws for
 * admin-editable content, and it carries more weight here because the editable half
 * was written by a language model. A generated paragraph can be wrong; a generated
 * link could be anything.
 */
class SalesPitchMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly SalesOutreachDraft $draft,
    ) {
        // Every fixed string in the template resolves in the pitch's own language,
        // not the queue worker's. Without this the body is Spanish and the button
        // underneath it is English — caught on the welcome email the same way.
        $this->locale($draft->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->draft->subject);
    }

    public function content(): Content
    {
        $business = $this->draft->business?->businessProfile;
        $community = $this->draft->community?->communityProfile;

        return new Content(
            markdown: 'mail.sales-pitch',
            with: [
                'bodyMarkdown' => $this->draft->body_markdown,
                'businessName' => $business?->name ?? '',
                'communityName' => $community?->name ?? '',
                'coverImageUrl' => $this->draft->cover_image_url,
                'idea' => $this->draft->selectedIdea(),
                'attendees' => $this->draft->expected_attendees,
                'avgSpend' => $this->money($this->draft->avg_spend_cents),
                'revenue' => $this->money($this->draft->estimated_revenue_cents),
                'ctaUrl' => rtrim((string) config('app.url'), '/').'/register',
            ],
        );
    }

    private function money(int $cents): string
    {
        $currency = (string) config('sales_outreach.revenue.currency');

        return $currency.' '.number_format($cents / 100, 0, '.', ',');
    }
}
