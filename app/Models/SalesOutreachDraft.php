<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sales pitch (BE-NF-65): which business, which community, the generated Kolab
 * ideas, the chosen one, its cover image, the revenue arithmetic and the copy.
 *
 * Persisted from the moment of generation so a pitch survives review, editing and
 * re-preview without paying OpenAI again — and so what was actually sent to a real
 * business stays auditable afterwards.
 *
 * @property-read list<array<string, string>> $kolab_ideas
 */
class SalesOutreachDraft extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    /** Cover generation is asynchronous (BE-FX-61); these are its states. */
    public const IMAGE_IDLE = 'idle';

    public const IMAGE_PENDING = 'pending';

    public const IMAGE_READY = 'ready';

    public const IMAGE_FAILED = 'failed';

    protected $fillable = [
        'business_profile_id',
        'community_profile_id',
        'locale',
        'kolab_ideas',
        'intel',
        'angle',
        'selected_idea_index',
        'cover_image_url',
        'cover_image_status',
        'cover_image_error',
        'expected_attendees',
        'avg_spend_cents',
        'estimated_revenue_cents',
        'subject',
        'body_markdown',
        'whatsapp_message',
        'status',
        'sent_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kolab_ideas' => 'array',
            'intel' => 'array',
            'selected_idea_index' => 'integer',
            'expected_attendees' => 'integer',
            'avg_spend_cents' => 'integer',
            'estimated_revenue_cents' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'business_profile_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'community_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The idea this pitch is actually about.
     *
     * Falls back to the first rather than returning null: `selected_idea_index` is
     * user input, and a draft whose index drifted out of range should still render a
     * pitch a maintainer can read and fix, not a blank screen.
     *
     * @return array<string, string>|null
     */
    public function selectedIdea(): ?array
    {
        $ideas = $this->kolab_ideas;

        if (! is_array($ideas) || $ideas === []) {
            return null;
        }

        return $ideas[$this->selected_idea_index] ?? $ideas[0];
    }

    /**
     * A `wa.me` deep link that opens WhatsApp with the message already typed.
     *
     * Deliberately NOT the WhatsApp Business API. That requires pre-approved
     * templates for business-initiated conversations, and messaging people who never
     * opted in breaches Meta's Business Messaging Policy — it gets numbers banned.
     * A link a maintainer clicks, which opens their own WhatsApp with the text
     * filled in and lets them press send, is compliant, needs no integration, and is
     * what small sales teams actually do.
     *
     * Null when there is no message or no number to send it to.
     */
    public function whatsappLink(): ?string
    {
        $message = trim((string) $this->whatsapp_message);

        if ($message === '') {
            return null;
        }

        $raw = $this->business?->phone_number
            ?? ($this->business?->businessProfile?->primary_venue['phone_number'] ?? null);

        // wa.me wants digits only, country code included, no plus and no spaces.
        $number = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isCoverPending(): bool
    {
        return $this->cover_image_status === self::IMAGE_PENDING;
    }

    public function coverFailed(): bool
    {
        return $this->cover_image_status === self::IMAGE_FAILED;
    }
}
